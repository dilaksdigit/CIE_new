# SOURCE: CIE_Master_Developer_Build_Spec.docx §4.2
# FIX: AI-04 / AI-14 — Canonical ai_agent_call helper with env-driven config and structured logging

from __future__ import annotations

import hashlib
import json
import logging
import os
import re
from typing import Optional

import anthropic
import requests

logger = logging.getLogger(__name__)


def sanitize_pii(text: str) -> str:
    value = str(text or "")
    value = re.sub(r"[\w.+-]+@[\w-]+\.[\w.-]+", "[EMAIL_REDACTED]", value)
    return value


def _insert_audit_log(sku_id: Optional[str], function_name: Optional[str], err: Exception, timeout_value: int) -> None:
    try:
        from src.utils.mysql_connect import pymysql_connect_dict_cursor

        conn = pymysql_connect_dict_cursor()
        try:
            with conn.cursor() as cur:
                cur.execute(
                    """
                    INSERT INTO audit_log (entity_type, entity_id, action, field_name, old_value, new_value, actor_id, metadata, created_at)
                    VALUES (%s, %s, %s, %s, %s, %s, %s, %s, NOW())
                    """,
                    (
                        "ai_agent",
                        str(sku_id or ""),
                        f"ai_agent_{function_name or 'unknown'}_failed",
                        "llm_response",
                        None,
                        json.dumps({"error_type": type(err).__name__, "message": str(err)[:500]}),
                        "system",
                        json.dumps({"function": function_name, "timeout": timeout_value}),
                    ),
                )
            conn.commit()
        finally:
            conn.close()
    except Exception as exc:  # pragma: no cover
        logger.warning("Failed to write ai_agent failure audit_log: %s", exc)


def _is_configured(value: Optional[str]) -> bool:
    token = (value or "").strip()
    return token not in ("", "...", "sk-ant-...", "AIza...")


def _resolve_gemini_model(raw: Optional[str]) -> str:
    return (raw or "").strip()


def _call_anthropic(
    api_key: str,
    model: str,
    system_prompt: str,
    user_message: str,
    max_tokens: int,
) -> str:
    client = anthropic.Anthropic(api_key=api_key)
    message = client.messages.create(
        model=model,
        max_tokens=max_tokens,
        system=system_prompt,
        messages=[{"role": "user", "content": user_message}],
    )
    return message.content[0].text if message.content else ""


def _call_gemini(
    api_key: str,
    model: str,
    system_prompt: str,
    user_message: str,
    max_tokens: int,
) -> str:
    base = "https://generativelanguage.googleapis.com"
    model_name = model if model.startswith("models/") else f"models/{model}"
    payload = {
        "systemInstruction": {"parts": [{"text": system_prompt}]},
        "contents": [{"role": "user", "parts": [{"text": user_message}]}],
        "generationConfig": {"maxOutputTokens": max_tokens, "temperature": 0.2},
    }
    urls = [
        f"{base}/v1beta/{model_name}:generateContent?key={api_key}",
        f"{base}/v1/{model_name}:generateContent?key={api_key}",
    ]
    last_error: Optional[str] = None
    for url in urls:
        try:
            resp = requests.post(url, json=payload, timeout=30)
            resp.raise_for_status()
            data = resp.json() if resp.content else {}
            candidates = data.get("candidates") or []
            if not candidates:
                prompt_feedback = data.get("promptFeedback") or {}
                block_reason = prompt_feedback.get("blockReason")
                if block_reason:
                    last_error = f"Gemini blocked prompt: {block_reason}"
                    continue
                last_error = "Gemini returned no candidates"
                continue
            content = (candidates[0] or {}).get("content") or {}
            parts = content.get("parts") or []
            text = "".join(str(p.get("text") or "") for p in parts).strip()
            if text:
                return text
            last_error = "Gemini returned empty text"
        except Exception as exc:
            last_error = str(exc)
            continue
    raise RuntimeError(last_error or "Gemini request failed")


def ai_agent_call(
    system_prompt: str,
    user_message: str,
    max_tokens: int = 1000,
    sku_id: Optional[str] = None,
    function_name: Optional[str] = None,
) -> str:
    """
    Canonical AI Agent helper. All Anthropic calls MUST use this function.
    - API key from environment (never hardcoded)
    - Model from environment (supports Anthropic and Gemini with fallback)
    - Structured logging per Build Pack §4.5 (prompt hash; no raw PII in logs)
    """
    prompt_hash = hashlib.sha256(
        (system_prompt + user_message).encode("utf-8")
    ).hexdigest()

    provider = (os.environ.get("AI_PROVIDER") or "").strip().lower()
    local_mode = provider == "local" or os.environ.get("LOCAL_LLM_MODE", "").strip().lower() == "true"
    if local_mode:
        # LOCAL LLM PATH — OpenAI-compatible API (testing only; production path below)
        import openai

        base_url = os.environ.get("LOCAL_LLM_BASE_URL", "http://localhost:1234/v1")
        api_key = (os.environ.get("OPENAI_API_KEY") or "local-dummy-key").strip()
        model = (os.environ.get("LOCAL_LLM_MODEL") or "").strip() or "default-local-model"
        client = openai.OpenAI(base_url=base_url, api_key=api_key)
        try:
            response = client.chat.completions.create(
                model=model,
                max_tokens=max_tokens,
                messages=[
                    {"role": "system", "content": sanitize_pii(system_prompt)},
                    {"role": "user", "content": sanitize_pii(user_message)},
                ],
                temperature=0.3,
            )
            result = response.choices[0].message.content or ""
        except Exception as e:  # pragma: no cover - local server errors
            _insert_audit_log(sku_id, function_name, e, 30)
            result = json.dumps({"error": str(e), "fallback": True})

        logger.info(
            "AI Agent call: sku_id=%s function=%s provider=%s max_tokens=%s prompt_hash=%s success=%s prompt_chars=%s response_chars=%s",
            sku_id,
            function_name,
            "local_openai_compat",
            max_tokens,
            prompt_hash,
            True,
            len(user_message),
            len(result),
        )
        return result

    preferred_provider = (os.environ.get("AI_PROVIDER") or "anthropic").strip().lower()
    anthropic_key = (os.environ.get("ANTHROPIC_API_KEY") or "").strip()
    anthropic_model = os.environ.get("ANTHROPIC_MODEL", "").strip()
    gemini_key = (os.environ.get("GEMINI_API_KEY") or "").strip()
    gemini_model = _resolve_gemini_model(os.environ.get("GEMINI_MODEL"))

    providers = [preferred_provider]
    if preferred_provider == "anthropic":
        providers.append("gemini")
    elif preferred_provider == "gemini":
        providers.append("anthropic")
    else:
        providers = ["anthropic", "gemini"]

    last_error: Optional[Exception] = None
    selected_provider = None
    result = ""
    for provider in providers:
        try:
            if provider == "anthropic":
                if not _is_configured(anthropic_key):
                    continue
                if not anthropic_model:
                    raise ValueError(
                        "ANTHROPIC_MODEL environment variable is required but not set"
                    )
                result = _call_anthropic(
                    anthropic_key,
                    anthropic_model,
                    sanitize_pii(system_prompt),
                    sanitize_pii(user_message),
                    max_tokens,
                )
                selected_provider = "anthropic"
                break
            if provider == "gemini":
                if not _is_configured(gemini_key):
                    continue
                result = _call_gemini(
                    gemini_key,
                    gemini_model,
                    sanitize_pii(system_prompt),
                    sanitize_pii(user_message),
                    max_tokens,
                )
                selected_provider = "gemini"
                break
        except Exception as exc:  # pragma: no cover - runtime provider failures
            last_error = exc
            continue

    if selected_provider is None:
        if last_error is not None:
            _insert_audit_log(sku_id, function_name, last_error, 30)
            raise last_error
        raise RuntimeError(
            "No AI provider configured. Set GEMINI_API_KEY or ANTHROPIC_API_KEY."
        )

    logger.info(
        "AI Agent call: sku_id=%s function=%s provider=%s max_tokens=%s prompt_hash=%s success=%s prompt_chars=%s response_chars=%s",
        sku_id,
        function_name,
        selected_provider,
        max_tokens,
        prompt_hash,
        True,
        len(user_message),
        len(result),
    )
    return result
