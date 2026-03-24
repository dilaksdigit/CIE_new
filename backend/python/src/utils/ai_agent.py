# SOURCE: CIE_Master_Developer_Build_Spec.docx §4.2
# FIX: AI-04 / AI-14 — Canonical ai_agent_call helper with env-driven config and structured logging

from __future__ import annotations

import hashlib
import logging
import os
from typing import Optional

import anthropic

logger = logging.getLogger(__name__)


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
    - Model from environment (falls back to claude-sonnet-4-6)
    - Structured logging per Build Pack §4.5 (prompt hash; no raw PII in logs)
    """
    api_key = os.environ["ANTHROPIC_API_KEY"]
    model = os.environ.get("ANTHROPIC_MODEL", "claude-sonnet-4-6")

    prompt_hash = hashlib.sha256(
        (system_prompt + user_message).encode("utf-8")
    ).hexdigest()

    client = anthropic.Anthropic(api_key=api_key)

    message = client.messages.create(
        model=model,
        max_tokens=max_tokens,
        system=system_prompt,
        messages=[{"role": "user", "content": user_message}],
    )
    result = message.content[0].text if message.content else ""

    logger.info(
        "AI Agent call: sku_id=%s function=%s max_tokens=%s prompt_hash=%s success=%s",
        sku_id,
        function_name,
        max_tokens,
        prompt_hash,
        True,
    )
    return result
