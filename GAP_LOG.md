# CIE v2.3.2 — Gap Log
# SOURCE: CLAUDE.md Section 20, Axiom 1 — Code without a traceable source document reference does not exist.

---

## DB-01 | 2026-03-12

**Description:** Migrations 027 and 063 create/rename FAQ tables. Schema differs from remediation spec column names:
- **faq_templates** (027): has `product_class`, `question`, `is_required`, `display_order` — spec mentions `intent_key`, `template_text`, `created_at`.
- **sku_faq_responses** (063 renames sku_faqs): has `answer` — spec mentions `response_text`.

**Action:** Do NOT rename columns without spec confirmation. Tables exist and are in use.

---

## GAP-1 | Task 3 | 2026-03-12

**Description:** Tier change request API routes are not declared in `cie_v231_openapi.yaml`. The locked API contract (CLAUDE.md Section 18; CIE_v232_FINAL_Developer_Instruction.docx R1) does not define paths for:
- POST tier-change-requests (store)
- POST tier-change-requests/{id}/approve-portfolio
- POST tier-change-requests/{id}/approve-finance
- POST tier-change-requests/{id}/reject

**Blocker:** `backend/php/routes/api.php` must not add routes that are not in the OpenAPI spec.

**Escalation:** Project owner — add tier change request paths to the OpenAPI contract and then wire routes in api.php. TierChangeController.php is implemented and ready; routes are intentionally omitted until the spec is updated.

---

## FIX 2 | embed/similarity routes | 2026-03-12

**Description:** openapi.yaml defines POST `/sku/{sku_id}/embed` and POST `/sku/{sku_id}/similarity`. No PHP controller method exists in SkuController (or elsewhere) for these; they are likely implemented by the Python Engine.

**Action:** Do not fabricate PHP handlers. Logged per FIX 2 Task B — restore only when controller exists. If these are proxy routes, add closure proxy in api.php mirroring the suggestion status proxy; otherwise implement or document Python-only usage.

---

## FIX 9 | Decay notification delivery | 2026-03-12

**Description:** _dispatch_decay_notification in decay_cron.py writes to audit_log only. CIE_v232_Hardening_Addendum.pdf specifies Week 2 (alert) and Week 4 (escalated) as "send notification to Content Owner + SEO Governor" / "escalate to Commercial Director". No email, Slack, or N8N webhook for delivery is defined in source docs.

**Action:** Decay notification delivery mechanism not defined in source docs. audit_log insert is implemented; external delivery (email/Slack/N8N) to be wired by project owner if required.

---
