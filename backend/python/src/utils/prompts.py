# SOURCE: CIE_Master_Developer_Build_Spec.docx §4.3
# FIX: AI-05/06/07 — Standard system prompt per spec

from __future__ import annotations

import json
from typing import Any, Dict, List, Sequence

STANDARD_SYSTEM_PROMPT = """You are an expert e-commerce SEO strategist in 2026 specialising in UK lighting and electrical products.

You have deep knowledge of Google's Helpful Content System, AI Overview behaviour, and structured data requirements.

The business sells lighting cables, lampshades, bulbs, and pendants. Products are technical and safety-regulated.

You are assisting a content writer who is the sole operator of a content intelligence system.

Your role is to generate specific, deployable content — not advice or commentary.

Always write for AI parsing: entity-rich, structured, direct answers first.

Never use marketing language ('premium', 'best', 'amazing'). Always use technical facts.

Output ONLY the requested JSON schema. No preamble. No explanation. Just the JSON."""


def build_standard_system_prompt() -> str:
    """Returns the Build Pack §4.3 standard system prompt."""
    return STANDARD_SYSTEM_PROMPT


def build_suggest_user_prompt(sku_data: Dict[str, Any]) -> str:
    """Builds the user message for content pre-fill suggestion."""
    certs = sku_data.get("certifications") or []
    cert_text = ", ".join(certs) if isinstance(certs, list) else str(certs)
    extra = ""
    tq = sku_data.get("top_gsc_queries") or []
    if isinstance(tq, list) and tq:
        lines = "\n".join(f"- {q}" for q in tq if q is not None and str(q).strip() != "")
        if lines:
            extra += "\n\nTop queries / search terms for this SKU (volume-ranked where available):\n" + lines
    cab = sku_data.get("competitor_answer_blocks") or []
    if isinstance(cab, list) and cab:
        lines = "\n".join(f"- {a}" for a in cab if a is not None and str(a).strip() != "")
        if lines:
            extra += "\n\nCompetitor / engine answer excerpts (context only):\n" + lines
    ps = sku_data.get("product_specs")
    if isinstance(ps, dict) and ps:
        extra += "\n\nExisting product specs:\n" + json.dumps(ps, indent=2)

    return f"""Generate content suggestions for the following SKU:

SKU Code: {sku_data.get('sku_code', '')}
Product Name: {sku_data.get('product_name', '')}
Category: {sku_data.get('category', '')}
Cluster ID: {sku_data.get('cluster_id', '')}
Cluster Intent: {sku_data.get('cluster_intent', '')}
Primary Intent: {sku_data.get('primary_intent', '')}
Tier: {sku_data.get('tier', '')}
Certifications: {cert_text}{extra}

Return a JSON object with these exact fields:
{{
  "shopify_title": "Intent-first title | Attribute | Brand",
  "meta_title": "Max 65 chars, keyword-rich",
  "meta_description": "120-160 chars, action-oriented",
  "answer_block": "250-300 chars, starts with direct answer, contains primary intent keyword",
  "best_for": ["Use case 1", "Use case 2", "Use case 3"],
  "not_for": ["Exclusion 1", "Exclusion 2"],
  "expert_authority": "Compliance statement with specific standard reference",
  "faq": [
    {{"question": "Primary intent question", "answer": "Direct answer, 50-80 words"}},
    {{"question": "Comparison question", "answer": "Direct answer"}},
    {{"question": "Safety/compatibility question", "answer": "Direct answer"}}
  ],
  "alt_text": "Descriptive alt text, 80-125 chars, no keyword stuffing",
  "confidence_score": 0.87,
  "suggestion_notes": "One sentence explaining the key SEO decision made"
}}"""


def build_titles_user_prompt(data: Dict[str, Any]) -> str:
    """Intent-first title candidates (3) for POST /api/v1/ai-agent/titles."""
    attrs = data.get("attributes") or {}
    if not isinstance(attrs, dict):
        attrs = {}
    return f"""Generate exactly 3 intent-first Shopify title candidates for this product.

Product: {data.get('product_name', '')}
Cluster: {data.get('cluster_id', '')}
Primary Intent: {data.get('primary_intent', '')}
Tier: {data.get('tier', '')}
Attributes: {json.dumps(attrs)}

Rules:
- Intent keyword MUST lead the title (not attribute-stacking).
- Each title must be unique and serve a different angle of the primary intent.
- Max 250 characters per title.

Output ONLY this JSON schema:
{{
  "titles": [
    {{"title": "...", "rationale": "One sentence explaining which intent element this serves"}},
    {{"title": "...", "rationale": "..."}},
    {{"title": "...", "rationale": "..."}}
  ]
}}"""


def build_revision_user_prompt(data: Dict[str, Any]) -> str:
    """Revised answer block from brief / decay context."""
    failing = json.dumps(data.get("failing_questions", []), indent=2)
    competitors = json.dumps(data.get("competitor_answers", []), indent=2)
    return f"""Generate a revised Answer Block for a Hero SKU that has lost AI citation visibility.

Current Answer Block: {data.get('current_answer_block', '')}
Cluster: {data.get('cluster_id', '')}
Primary Intent: {data.get('primary_intent', '')}

Failing audit questions (these are the questions where AI engines no longer cite our product):
{failing}

Competitor answer blocks (what competitors are saying that IS getting cited):
{competitors}

Rules:
- Revised answer block must be 250-300 characters.
- Must start with a direct answer to the primary intent.
- Must contain the primary intent keyword.
- Must be entity-rich and factual — no marketing language.
- Address the specific failing questions where possible.

Output ONLY this JSON:
{{
  "revised_answer_block": "...",
  "revision_rationale": "One sentence explaining what changed and why",
  "addressed_questions": ["question_id_1", "question_id_2"]
}}"""


def build_decay_suggested_revision_user_message(
    sku: Dict[str, Any],
    failing_questions: Sequence[Dict[str, Any]],
    current_answer_block: str,
    competitor_answers: Sequence[str],
) -> str:
    """
    User message for decay / auto-brief revision direction.
    SOURCE: CIE_Master_Developer_Build_Spec.docx Section 12.3 / §4.2 — JSON key ai_suggested_revision (backward compatible).
    """
    failing_qs_text = json.dumps(
        [{"id": q.get("id"), "text": q.get("text", "")} for q in failing_questions]
    )
    return f"""
SKU: {sku.get('title') or sku.get('sku_code') or sku.get('id')} (ID: {sku.get('id')})
Tier: HERO
Failing audit questions (score=0): {failing_qs_text}
Current AI Answer Block: {current_answer_block}
Top competitor answers: {list(competitor_answers)}

Return ONLY a JSON object with one key:
{{ "ai_suggested_revision": "<specific revision direction, 1-3 sentences, not generic>" }}
"""
