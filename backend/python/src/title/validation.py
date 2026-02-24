"""
CIE v2.3.1 — Title validation and suggestion.
NON-NEGOTIABLE: Intent → Cluster → Attributes. First segment = user intent/problem; after pipe = attributes.
6.1 Title Formula: [INTENT_PHRASE] + ' | ' + [PRODUCT_CLASS] + ' ' + [KEY_ATTRIBUTES]; max 250 chars.
G2: Text before '|' must NOT start with colour, material, or dimension; must contain intent-related word.
"""
import os
import re
from typing import Any

# 6.1 Max length (including separator). Rules block also allows 120; use 250 per Title Formula.
MAX_TITLE_LEN = 250
PIPE = "|"

# G2: Words that must NOT start the segment before the pipe (attribute-stacking).
FORBIDDEN_LEADING_WORDS: set[str] = {
    # Colours
    "blue", "red", "white", "black", "grey", "gray", "taupe", "gold", "silver", "bronze",
    "cream", "beige", "brown", "green", "yellow", "chrome", "brass", "nickel", "clear",
    # Materials
    "fabric", "glass", "metal", "wood", "plastic", "ceramic", "drum", "pleated",
    # Dimensions / specs (leading number or common spec words)
    "30cm", "35cm", "40cm", "1m", "1.5m", "e27", "e14", "b22", "gu10", "4w", "6w",
}
# Pattern: starts with optional digits then units (cm, mm, m, w, watts, k)
LEADING_SPEC_PATTERN = re.compile(r"^\s*\d+\s*(?:cm|mm|m|w|watts?|k)\b", re.I)


# Primary intent (normalized key) → keywords/synonyms that MUST appear in the segment BEFORE the pipe
# So the title addresses the user's intent first (not attribute-stacking).
INTENT_TITLE_KEYWORDS: dict[str, list[str]] = {
    "problem_solving": ["solution", "solve", "problem", "fix", "need", "how to", "help", "lighting for", "for low", "glare-free", "warm"],
    "comparison": ["comparison", "compare", "vs", "versus", "alternative", "or", "versus"],
    "compatibility": ["compatible", "compatibility", "fit", "fits", "works with", "for ceiling", "for e27", "for b22", "fitting", "connect", "power"],
    "specification": ["specification", "spec", "technical", "details", "wattage", "max", "rating", "rated", "pendant cable", "cable set"],
    "installation": ["install", "installation", "wire", "wiring", "how to", "fitting", "safe", "simple", "made simple"],
    "troubleshooting": ["troubleshoot", "flickering", "issue", "fix", "repair", "problem"],
    "inspiration": ["inspiration", "style", "design", "ideas", "modern", "look", "warm", "diffused", "for living", "for kitchen"],
    "regulatory": ["regulatory", "safety", "compliant", "rated", "bathroom", "ip", "safe", "bs ", "en "],
    "replacement": ["replacement", "replace", "refill", "for floor lamp", "for pendant"],
}


def _norm_intent(primary_intent: str) -> str:
    if not primary_intent or not primary_intent.strip():
        return ""
    return primary_intent.strip().lower().replace(" ", "_").replace("-", "_")


def _get_intent_keywords(primary_intent: str) -> list[str]:
    key = _norm_intent(primary_intent)
    if not key:
        return []
    # Allow label variants (e.g. "Problem-Solving" -> problem_solving)
    for k, keywords in INTENT_TITLE_KEYWORDS.items():
        if k == key or k.replace("_", " ") in primary_intent.lower():
            return keywords
    return INTENT_TITLE_KEYWORDS.get(key, [])


def _load_brand_prefixes() -> set[str]:
    """Brand names that title must NOT start with. Load from env CIE_TITLE_BRAND_PREFIXES (comma-separated) or leave empty."""
    raw = os.environ.get("CIE_TITLE_BRAND_PREFIXES", "")
    if not raw:
        return set()
    return {s.strip().lower() for s in raw.split(",") if s.strip()}


def validate_title(
    title: str,
    primary_intent: str,
    cluster_id: str,
    brand_prefixes: set[str] | None = None,
) -> dict[str, Any]:
    """
    Validate product title against CIE rules.

    Rules:
    - Title must contain a pipe separator '|'
    - Text BEFORE the pipe = user intent/problem (not attributes)
    - Text AFTER the pipe = product attributes (size, colour, fitting)
    - Primary intent keyword (or synonym) must appear before the pipe
    - Title must not start with brand name
    - G2: Before pipe must NOT start with colour, material, or dimension
    - Max 250 characters (6.1); configurable via MAX_TITLE_LEN.

    Returns:
        { "valid": bool, "issues": list[str], "suggested_fix": str | None }
    """
    issues: list[str] = []
    title = (title or "").strip()
    primary_intent = (primary_intent or "").strip()

    if not title:
        return {"valid": False, "issues": ["Title is required."], "suggested_fix": None}

    # Max length (6.1: 250)
    if len(title) > MAX_TITLE_LEN:
        issues.append(f"Title must not exceed {MAX_TITLE_LEN} characters (current: {len(title)}).")

    # Must contain pipe
    if PIPE not in title:
        issues.append("Title must contain a pipe separator '|' (intent phrase before, attributes after).")
        return {"valid": False, "issues": issues, "suggested_fix": None}

    parts = title.split(PIPE, 1)
    before = (parts[0] or "").strip()
    after = (parts[1] or "").strip()

    if not before:
        issues.append("Text before the pipe must describe user intent/problem, not be empty.")

    if not after:
        issues.append("Text after the pipe must contain product attributes (e.g. size, colour, fitting).")

    # Primary intent keyword (or synonym) must appear before the pipe
    if primary_intent:
        keywords = _get_intent_keywords(primary_intent)
        before_lower = before.lower()
        if keywords and not any(kw in before_lower for kw in keywords):
            issues.append(
                "The primary intent keyword (or synonym) must appear in the part before the pipe. "
                "First segment should address the user's intent/problem, not attributes."
            )

    # Title must not start with brand name
    if brand_prefixes is None:
        brand_prefixes = _load_brand_prefixes()
    if brand_prefixes and before:
        first_word = before.split()[0].lower() if before.split() else ""
        if first_word in brand_prefixes:
            issues.append("Title must not start with a brand name. Lead with intent/problem instead.")

    # G2: Text before '|' must NOT start with colour, material, or dimension
    if before:
        first_word = before.split()[0].lower() if before.split() else ""
        if first_word in FORBIDDEN_LEADING_WORDS:
            issues.append(
                "Text before the pipe must not start with a colour, material, or dimension. "
                "Lead with intent/problem (e.g. 'Warm Glare-Free Lighting for Living Rooms | ...')."
            )
        if LEADING_SPEC_PATTERN.match(before):
            issues.append(
                "Text before the pipe should describe intent/problem, not start with size/spec (e.g. '30cm' or '4W')."
            )

    valid = len(issues) == 0
    suggested_fix = _build_suggested_fix(title, before, after, issues, primary_intent) if issues else None
    return {"valid": valid, "issues": issues, "suggested_fix": suggested_fix}


def _build_suggested_fix(
    title: str,
    before: str,
    after: str,
    issues: list[str],
    primary_intent: str,
) -> str | None:
    """Build a suggested title fix when possible (e.g. trim to 120 chars, or reorder if only attribute-stacking)."""
    if not before or not after:
        return None
    # If only over length, suggest trim
    if len(title) > MAX_TITLE_LEN:
        if len(before) > MAX_TITLE_LEN // 2:
            before_trim = before[: (MAX_TITLE_LEN // 2) - 3].rsplit(maxsplit=1)[0] + "..."
        else:
            before_trim = before
        after_trim = after[: MAX_TITLE_LEN - len(before_trim) - 2].rsplit(maxsplit=1)[0] if len(after) + len(before_trim) + 2 > MAX_TITLE_LEN else after
        return f"{before_trim} {PIPE} {after_trim}"[:MAX_TITLE_LEN]
    return None


# -------- Title suggestion: 6.2 Intent phrase templates by product class --------
# Category × intent_key → template (placeholders: Product, Target Fitting, Benefit, Room/Context, etc.)

TEMPLATES_BY_CATEGORY_INTENT: dict[tuple[str, str], str] = {
    # Cables
    ("cables", "compatibility"): "{Product} for {Target Fitting} — {Benefit}",
    ("cables", "installation"): "Easy-Install {Product} for {Application}",
    # Lampshades
    ("lampshades", "problem_solving"): "{Benefit} for {Room/Context}",
    ("lampshades", "comparison"): "{Material A} vs {Material B} — {Decision Help}",
    ("lampshades", "replacement"): "Replacement {Product} for {Existing Setup}",
    # Bulbs
    ("bulbs", "compatibility"): "{Bulb Type} for {Fitting} — {Key Spec}",
    ("bulbs", "specification"): "{Output} {Bulb Type} — {Equivalence}",
    # Pendants
    ("pendants", "inspiration"): "{Style} {Product} Ideas for {Space}",
    ("pendants", "regulatory"): "{Safety Standard} {Product} for {Environment}",
}
# Fallback: intent-only templates (no category)
INTENT_FALLBACK_TEMPLATES: dict[str, str] = {
    "problem_solving": "{Product} — {Benefit} for {Room/Context}",
    "comparison": "{Product} — Compare for {Room/Context}",
    "compatibility": "{Product} for {Target Fitting} — {Benefit}",
    "specification": "{Product} — {Key Spec}",
    "installation": "Easy-Install {Product} for {Application}",
    "troubleshooting": "{Product} to Fix {Room/Context} Issues",
    "inspiration": "{Style} {Product} Ideas for {Space}",
    "regulatory": "{Safety Standard} {Product} for {Environment}",
    "replacement": "Replacement {Product} for {Existing Setup}",
}

MIN_INTENT_PHRASE_LEN = 20  # 6.3: intent phrase too short = validation error in generation


def _get_intent_phrase_template(category: str, intent_key: str) -> str:
    """Pick template from 6.2 (category × intent) or fallback to intent-only."""
    cat = (category or "").strip().lower().replace(" ", "_")
    key = _norm_intent(intent_key) or intent_key.replace(" ", "_").lower()
    return TEMPLATES_BY_CATEGORY_INTENT.get((cat, key)) or INTENT_FALLBACK_TEMPLATES.get(key) or "{Product} for {Room/Context}"


def _intent_phrase_templates(primary_intent: str) -> list[str]:
    """Legacy: templates for the intent-led first segment (before pipe)."""
    key = _norm_intent(primary_intent)
    templates = {
        "problem_solving": ["{product_type} for {use_case}", "Warm Glare-Free {product_type} for {use_case}", "{product_type} — Solution for {use_case}"],
        "comparison": ["{product_type} — Compare Options for {use_case}", "{product_type} for {use_case}"],
        "compatibility": ["{product_type} for Ceiling Lights — Safe Wiring Made Simple", "{product_type} — Fits {use_case}", "{product_type} for {use_case}"],
        "specification": ["{product_type} — Technical Details for {use_case}", "{product_type} for {use_case}"],
        "installation": ["{product_type} — Safe Installation for {use_case}", "How to Fit {product_type} for {use_case}"],
        "troubleshooting": ["{product_type} to Fix {use_case} Issues", "{product_type} for {use_case}"],
        "inspiration": ["Warm Diffused {product_type} for {use_case}", "{product_type} — Style and Design for {use_case}"],
        "regulatory": ["{product_type} — Compliant and Safe for {use_case}", "Rated {product_type} for {use_case}"],
        "replacement": ["Replacement {product_type} for {use_case}", "{product_type} — Replace or Refill for {use_case}"],
    }
    return [templates.get(key, "{product_type} for {use_case}")]


def _extract_primary_benefit(intent_statement: str | None) -> str:
    """Extract a short benefit phrase from cluster intent statement (e.g. first clause)."""
    if not intent_statement or not intent_statement.strip():
        return "Quality and Value"
    s = intent_statement.strip()
    for sep in ("—", "-", ".", ","):
        if sep in s:
            s = s.split(sep)[0].strip()
    return s[:60] if len(s) > 60 else s


def suggest_title(
    cluster_id: str,
    primary_intent: str,
    attributes: dict[str, Any],
) -> str:
    """
    Generate a CIE-compliant title per 6.1: [INTENT_PHRASE] + ' | ' + [PRODUCT_CLASS] + ' ' + [KEY_ATTRIBUTES].
    Uses 6.2 intent phrase templates by category × intent when category is provided.

    Attributes (from cluster + SKU):
    - category: cables | lampshades | bulbs | pendants | floor_lamps | ceiling_lights | accessories
    - product_type / Product: e.g. "Pendant Cable Set", "Fabric Drum Lampshade"
    - intent_statement / benefit: from cluster; Benefit in templates
    - fitting_type / Target Fitting / Fitting: e.g. "E27", "Ceiling Lights"
    - primary_use_context / Room/Context / Application: e.g. "Living Rooms", "DIY Ceiling Lighting"
    - material_name / Material A: e.g. "Fabric"; Material B for comparison
    - primary_dimension / size: e.g. "30cm", "1m"
    - colour / color; wattage; output (lumens); equivalence (e.g. "40W Incandescent Equivalent")
    - style, space, safety_standard, environment for inspiration/regulatory
    """
    primary_intent = (primary_intent or "").strip()
    intent_key = _norm_intent(primary_intent)
    category = (attributes.get("category") or attributes.get("product_category") or "").strip().lower().replace(" ", "_")
    product = (attributes.get("product_type") or attributes.get("product_type_label") or attributes.get("Product") or "Product").strip()
    product_class = (attributes.get("product_class_label") or product).strip()
    benefit = _extract_primary_benefit(attributes.get("intent_statement") or attributes.get("benefit"))
    target_fitting = (attributes.get("fitting_type") or attributes.get("Target Fitting") or attributes.get("fitting") or "Your Fitting").strip()
    room_context = (attributes.get("primary_use_context") or attributes.get("Room/Context") or attributes.get("use_case") or attributes.get("cluster_use_case") or "Your Setup").strip()
    application = (attributes.get("Application") or room_context).strip()
    material_a = (attributes.get("material_name") or attributes.get("Material A") or attributes.get("material") or "").strip()
    material_b = (attributes.get("Material B") or "").strip()
    decision_help = (attributes.get("Decision Help") or "Compare options").strip()
    existing_setup = (attributes.get("Existing Setup") or room_context).strip()
    bulb_type = (attributes.get("Bulb Type") or product).strip()
    key_spec = (attributes.get("Key Spec") or attributes.get("key_spec") or "Key Spec").strip()
    output = (attributes.get("Output") or attributes.get("output") or "").strip()
    equivalence = (attributes.get("Equivalence") or attributes.get("equivalence") or "").strip()
    style = (attributes.get("Style") or attributes.get("style") or "Modern").strip()
    space = (attributes.get("Space") or room_context).strip()
    safety_standard = (attributes.get("Safety Standard") or attributes.get("safety_standard") or "Rated").strip()
    environment = (attributes.get("Environment") or attributes.get("environment") or "Bathroom Zones").strip()

    template = _get_intent_phrase_template(category, primary_intent)
    format_args: dict[str, Any] = {
        "Product": product,
        "Target Fitting": target_fitting,
        "Fitting": target_fitting,
        "Benefit": benefit,
        "Room/Context": room_context,
        "Application": application,
        "Material A": material_a or "Fabric",
        "Material B": material_b or "Glass",
        "Decision Help": decision_help,
        "Existing Setup": existing_setup,
        "Bulb Type": bulb_type,
        "Key Spec": key_spec,
        "Output": output,
        "Equivalence": equivalence,
        "Style": style,
        "Space": space,
        "Safety Standard": safety_standard,
        "Environment": environment,
    }
    try:
        intent_phrase = template.format(**format_args)
    except KeyError:
        intent_phrase = f"{product} for {room_context} — {benefit}"

    if len(intent_phrase) < MIN_INTENT_PHRASE_LEN:
        intent_phrase = f"{intent_phrase} — {benefit}"[:MAX_TITLE_LEN]
    intent_phrase = intent_phrase.strip() or f"{product} for {room_context}"

    # Phase 2: KEY_ATTRIBUTES (6.3) — material, primary_dimension, fitting_type, colour
    attr_order = ["material_name", "material", "primary_dimension", "size", "colour", "color", "fitting_type", "fitting", "wattage", "style", "finish"]
    after_parts: list[str] = []
    seen: set[str] = set()
    for k in attr_order:
        if k in attributes and attributes[k] not in (None, ""):
            v = str(attributes[k]).strip()
            if v and k not in seen:
                after_parts.append(v)
                seen.add(k)
    for k, v in attributes.items():
        if k in ("category", "product_type", "product_type_label", "Product", "use_case", "cluster_use_case", "intent_statement", "benefit", "primary_use_context", "Room/Context", "Application", "Target Fitting", "Fitting", "Material A", "Material B", "Decision Help", "Existing Setup", "Bulb Type", "Key Spec", "Output", "Equivalence", "Style", "Space", "Safety Standard", "Environment", "product_category"):
            continue
        if v is None or v == "" or k in seen:
            continue
        v = str(v).strip()
        if v:
            after_parts.append(v)
            seen.add(k)

    attribute_str = " ".join(after_parts) if after_parts else product_class

    # 6.1: TITLE = [INTENT_PHRASE] + ' | ' + [PRODUCT_CLASS] + ' ' + [KEY_ATTRIBUTES]
    after_pipe = f"{product_class} {attribute_str}".strip() or product
    title = f"{intent_phrase} {PIPE} {after_pipe}"

    # 6.3: Truncate attributes only, never intent phrase
    if len(title) > MAX_TITLE_LEN:
        title = _truncate_attributes(intent_phrase, after_pipe, MAX_TITLE_LEN)
    return title


def _truncate_attributes(intent_phrase: str, after_pipe: str, max_len: int) -> str:
    """Shorten the segment after the pipe so total length <= max_len."""
    sep_len = len(f" {PIPE} ")
    allowed_after = max(0, max_len - len(intent_phrase) - sep_len)
    if allowed_after <= 0:
        return intent_phrase + f" {PIPE} " + after_pipe[: max_len - len(intent_phrase) - sep_len]
    if len(after_pipe) <= allowed_after:
        return f"{intent_phrase} {PIPE} {after_pipe}"
    truncated = after_pipe[: allowed_after - 3].rsplit(maxsplit=1)[0]
    return f"{intent_phrase} {PIPE} {truncated}"
