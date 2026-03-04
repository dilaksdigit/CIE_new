# CLAUDE.md — CIE v2.3.2 Standing Instructions

> **This file is the standing context for Cursor (AI coding tool) and all 5 LLM Spaces.**
> Load it into every Space alongside the spec documents. Cursor should read it automatically from the project root.
>
> Last updated: March 2026 | Project: Catalog Intelligence Engine v2.3.2

---

## 1. WHAT THIS PROJECT IS

CIE (Catalog Intelligence Engine) v2.3.2 is a closed-loop SEO content management system for an e-commerce lighting business. It enforces quality gates on product descriptions and auto-publishes approved content to Shopify (primary) and Google Merchant Center. Amazon is deferred to a future phase.

The system serves exactly 2 primary users:
- **Content Writer** — edits product content through 7 enforcement gates
- **KPI Reviewer** — monitors performance metrics, does NOT edit content
- **Admin** — uploads Semrush CSV, manages config, manages users

CIE is NOT a suggestion engine. It ENFORCES rules at API level with zero human override.

---

## 2. GOVERNING AXIOMS

### Axiom 1 — Traceability Is Existence
Code without a traceable source document reference does not exist in this system. It will be deleted, not reviewed. The burden of proof is on the developer to show the reference — not on the reviewer to find the gap. If the source document section does not exist: STOP. Escalate. Do not interpret, assume, or build.

### Axiom 2 — Pattern Interception
Every violation is assessed as a potential pattern. First instance: correct and log. Second instance of the SAME violation type: automatic escalation and component freeze regardless of severity. The cost of a pattern always exceeds the cost of the correction that prevents it.

---

## 3. HARD RULES — BREAK ANY OF THESE AND THE CODE IS REJECTED

| # | Rule |
|---|------|
| R1 | NO new API endpoints. The OpenAPI contract (cie_v231_openapi.yaml) is locked. Only exception: POST /api/admin/semrush-import (already specced). |
| R2 | NO human approval queue. When all 7 gates pass, content auto-publishes to Shopify + GMC. The gates ARE the approval. |
| R3 | NO gate codes in the UI. All gate messages must be plain English the writer understands. "G6.1_FAIL" is forbidden. |
| R4 | NO cosine similarity numbers visible to the writer. Internal processing only. Writer sees pass/fail with guidance text. |
| R5 | NO new npm packages without written approval from project owner. |
| R6 | DESKTOP ONLY. Minimum viewport 1280px. No responsive/mobile CSS. |
| R7 | NO RBAC modifications. The 8-role hierarchy is locked. |
| R8 | NO existing screen logic rewrites unless explicitly required by spec. |

---

## 4. CHANNEL PRIORITY (DECISION-001)

| Channel | Status | Action |
|---------|--------|--------|
| **Shopify** | **PRIMARY** | Build first. Test first. Measure first. All auto-publish flows target Shopify. |
| **Google Merchant Center** | SECONDARY | Builds alongside Shopify. Uses same Google service account. Simpler integration. |
| **Amazon SP-API** | **DEFERRED** | NOT in v2.3.2 scope. Do not build. Do not apply for API access. Do not code Amazon endpoints. |
| **AI Assistants** | INDIRECT | No direct deploy. Citation measured via weekly audit. Depends on Shopify + schema being live. |

**When any spec document says "all channels" or "channel deploy", read it as: Shopify + GMC only.**
Amazon references in other spec documents are future-state, not current build requirements.

---

## 5. TECH STACK

| Layer | Technology | Notes |
|-------|-----------|-------|
| Backend | PHP Laravel | CMS, API endpoints, RBAC, gate validation |
| AI Services | Python FastAPI | Vector similarity, title engine, AI audit runner |
| Workflows | N8N | Channel deployment, ERP sync, audit scheduling |
| Frontend | React | 15 routes, light theme, desktop only (1280px+) |
| Database | MySQL (utf8mb4) | All tables defined in canonical schema |
| Code Generation | Cursor | All code generated via Drift-Safe Master Loop |

---

## 6. THE 7 PUBLISH GATES

No SKU can be published without passing every applicable gate. The system returns a 400-level error and the save is rejected. There is no manual override. There is no exception request form.

| Gate | Name | Rule | Override |
|------|------|------|----------|
| G1 | Cluster ID | Valid Cluster_ID from approved semantic contract | NONE |
| G2 | Primary Intent | Exactly 1 from locked 9-intent taxonomy | NONE |
| G3 | Secondary Intent(s) | Min 1, max 3 from locked taxonomy | NONE |
| G4 | Answer Block | 250–300 characters. Must contain primary intent keyword. | NONE |
| G5 | Best-For / Not-For | Min 2 best_for + min 1 not_for (Hero/Support only) | NONE |
| G6 | Description Quality | Min word count + semantic validation | NONE |
| G6.1 | Tier Lock | Kill = ALL fields blocked. Harvest = limited fields. | NONE |
| G7 | Channel Readiness | Score >= threshold for target channel (Shopify/GMC) | NONE |

### The 9-Intent Taxonomy (Locked)
Compatibility, Comparison, Problem-Solving, Inspiration, Specification, Installation, Safety/Compliance, Replacement, Bulk/Trade

No intents may be added or modified without a formal spec change through the Change Protocol.

---

## 7. TIER SYSTEM

Every SKU is assigned a commercial tier based on ERP data (margin, CPPC, velocity). The tier controls content effort, field visibility, AI features, and channel deployment.

| Tier | Content Effort | AI Features | JSON-LD | Field Access |
|------|---------------|-------------|---------|--------------|
| **Hero** | Maximum. Full CIE coverage. Priority in writer queue. | Full: drafting, suggestions, audit, FAQ | Full schema + Wikidata entities | All fields enabled |
| **Support** | Focused. Key fields only. | Drafting + suggestions | Standard schema, no Wikidata | Most fields enabled. Expert Authority optional. |
| **Harvest** | Maintenance only. Minimal rewrites. | Suggestions only | Basic schema | Limited fields. No FAQ. No Wikidata. |
| **Kill** | ZERO. No content effort. All editing disabled. | None | None | ALL FIELDS BLOCKED. Read-only. |

**Tier recomputation:** Triggered by ERP sync. When tier changes, a `tier_history` entry is created. Tier changes propagate to field visibility, queue priority, and channel readiness immediately.

---

## 8. UI THEME — LIGHT BUSINESS TOOL

All screens use the light business-tool palette defined in CIE_v232_UI_Mockup.jsx.

### Core Colours
```
bg:          #FAFAFA     (page background)
surface:     #FFFFFF     (cards, panels)
surfaceAlt:  #F5F5F4     (alternating rows)
border:      #E5E5E5     (card borders)
text:        #2D2D2D     (primary text)
textMuted:   #6B6B6B     (secondary text)
textDim:     #999999     (labels, timestamps)
accent:      #5B7A3A     (olive — primary action buttons)
```

### Tier Badge Colours
```
Hero:    bg #FDF6E3, border #E8D5A0, text #8B6914
Support: bg #EBF3F9, border #B5D0E3, text #3D6B8E
Harvest: bg #FFF8E7, border #E8D49A, text #B8860B
Kill:    bg #FDEEEB, border #E5B5AD, text #A63D2F
```

### Gate Status Colours
```
Pass:  bg #E8F5E9, border #A5D6A7, text #2E7D32
Fail:  bg #FFEBEE, border #EF9A9A, text #C62828
```

### Rules
- Desktop only. Min viewport 1280px. No responsive breakpoints.
- No dark mode. No theme toggle.
- Font: system sans-serif stack. Monospace for data values (Source Code Pro / Consolas).
- No emojis in production UI. Tier badges use text, not icons.
- All tables: alternating row shading. Header row uses navy (#1F2D54) with white text.

---

## 9. DATABASE KEY RULES

- Charset: utf8mb4 on all tables and columns
- audit_log table is IMMUTABLE: UPDATE and DELETE are blocked at database trigger level
- Foreign keys enforced on all relationships
- Tier stored as ENUM('hero','support','harvest','kill') — lowercase, no variants
- Intent stored as ENUM matching the 9-intent taxonomy exactly
- All timestamps: TIMESTAMP type, UTC timezone
- semrush_imports table has 4 additional columns (v2.3.2): position, competitor_position, import_batch_id, imported_at
- semrush_content_snapshots table auto-created when content is published

---

## 10. API RULES

- All endpoints defined in cie_v231_openapi.yaml. Do NOT add new endpoints.
- All responses follow the schema in the OpenAPI spec. Do not invent error formats.
- All API calls use HTTPS. No plaintext credentials in logs.
- API keys are NEVER returned in API responses.
- Rate limits: Shopify 2 calls/sec. GMC Content API 50 calls/min.
- RBAC enforced on every endpoint. Writer cannot access admin routes. Reviewer cannot edit content.
- Every content modification creates an audit_log entry. No exceptions.

---

## 11. VECTOR VALIDATION

- Embedding model: OpenAI text-embedding-3-small
- Cosine similarity threshold: **0.72** (exact — do not round to 0.7)
- Behaviour: **fail-soft**. Below 0.72 = WARNING, not block. Content saves with warning. Audit log records the fail-soft event.
- Writer sees: "Your content may not align with the intent. Consider revising." NOT the number 0.72.
- Threshold is configurable in admin Config screen (S8), stored in config table.

---

## 12. AI AUDIT SYSTEM

- 4 engines: ChatGPT (OpenAI API), Gemini (Google API), Perplexity (Perplexity API), Google SGE (manual/scraping initially)
- 20 golden questions per product category
- Citation scoring: 0 = not mentioned, 1 = cited, 2 = summarised, 3 = recommended as best
- Degradation quorum: 3 of 4 engines must agree on degradation before action triggers
- Decay loop: 3 consecutive weeks of zero citation on a Hero SKU → auto-generate refresh brief
- Runs weekly (Monday 6am via N8N scheduled workflow)
- Results stored in ai_audit_results table with engine, question_id, score, response_hash, run_date

---

## 13. SEMRUSH INTEGRATION (v2.3.2 Addition)

- NO API integration. Manual CSV upload by admin every Monday.
- CSV columns: keyword, search_volume, keyword_difficulty, intent, position, sku_code, cluster_id (optional), competitor_url (optional), competitor_position (optional), trend (optional)
- Upload goes through /admin/semrush-import screen
- Each upload gets a UUID import_batch_id for week-over-week comparison
- When content is published, system auto-creates a semrush_content_snapshot with baseline keyword positions
- Snapshot tracks 30 days, then auto-concludes
- New screen /review/semrush has 3 zones: Rank Movement, Competitor Gaps, Quick Wins
- Quick Wins = keywords where position 11-30, difficulty <40, volume >500, tier Hero/Support only
- Writer queue gets informational badges (⚡ Quick Win, 🔍 Gaps) but sort order is NOT changed

---

## 14. THE 4-SOURCE MEASUREMENT LOOP

Every content change is measured from 4 angles:

| Source | What | How | Timing |
|--------|------|-----|--------|
| Google Search Console | Impressions, clicks, CTR, position | API pull | Continuous, compare Day 0 vs Day 14 vs Day 30 |
| Google Analytics 4 | Sessions, bounce rate, conversion | API pull | Continuous, revenue attribution per page |
| AI Citation Audit | Citation scores from 4 AI engines | Weekly Python job | Every Monday |
| Semrush CSV | Keyword rank, competitor gaps, difficulty | Admin uploads every Monday | Week-over-week comparison |

30-day loop: publish → baseline captured → weekly measurement → Day 30 conclusion → pattern identified.

---

## 15. CONTENT HEALTH SCORE (CHS)

Score 0–100 per URL. 5 weighted components:

| Component | Weight | Source |
|-----------|--------|--------|
| Intent Alignment | 25% | Gate G2 + G3 validation |
| Semantic Coverage | 20% | Vector similarity score |
| Technical SEO Hygiene | 20% | Meta length, schema, alt text, slug |
| Competitive Gap | 20% | Semrush data: 100 - (gap_keywords / total_keywords × 100) |
| AI Readiness | 15% | AI audit citation scores |

If no Semrush data exists for a SKU, Competitive Gap component shows "No Data" (not 0, not error).

---

## 16. DOCUMENT AUTHORITY ORDER

When two documents conflict, the higher-ranked document wins. Always.

| Priority | Document |
|----------|----------|
| 1 (HIGHEST) | CIE_v232_DriftSafe_v3_Alignment_Patch.docx |
| 2 | CIE_v232_Semrush_Performance_Loop_Addendum.docx |
| 3 | CIE_v232_Hardening_Addendum.docx |
| 4 | CIE_v2_3_1_Enforcement_Dev_Spec.docx |
| 5 | CIE_v2.3_Enforcement_Edition.docx |
| 6 | CIE_v231_Developer_Build_Pack.docx |
| 7 | cie_v231_openapi.yaml |
| 8 | CIE_v232_FINAL_Developer_Instruction.docx |
| 9 | This file (CLAUDE.md) — context and rationale, advisory not authoritative |

---

## 17. KEY DESIGN DECISIONS (from project owner conversation)

These decisions were made during the design phase and are FINAL unless formally changed through the Change Protocol.

### DECISION-001: Channel Priority
- Shopify = PRIMARY. GMC = SECONDARY (builds). Amazon = DEFERRED (do not build).
- Rationale: Focus development effort on the channel that drives revenue now. Amazon adds complexity with SP-API approval delays and different content format requirements. Add it after Shopify is proven.

### DECISION-002: No Human Approval Queue
- Old CIE had a reviewer approval step between gate validation and publish. Removed.
- Rationale: The 7 gates ARE the quality control. Adding a human in the loop reintroduces the bottleneck the system was built to eliminate. If the gates pass, the content is good enough to publish.

### DECISION-003: 2 Users, Not 8 Roles
- The RBAC still defines 8 roles (for future expansion), but the system is built for exactly 2 active users: 1 writer, 1 reviewer + 1 admin.
- Rationale: The business has 2 people who interact with CIE daily. Building for 8 roles adds UI complexity with no current value. The RBAC infrastructure supports future scale-up without code changes.

### DECISION-004: Semrush CSV, Not API
- Semrush data enters via manual CSV upload, not API integration.
- Rationale: Semrush API requires a paid tier the client may not have. CSV upload takes 5 minutes per week and gives the admin control over what data enters the system. API integration can be added later without architectural changes — the import table structure is the same.

### DECISION-005: Fail-Soft Vector Validation
- Vector similarity below 0.72 produces a WARNING, not a block.
- Rationale: Hard-blocking on cosine similarity would prevent writers from saving work in progress. The warning ensures they see the issue. The audit log records the fail-soft event for governance tracking.

### DECISION-006: Kill SKU = Total Lockout
- Kill-tier SKUs have ALL fields disabled. The writer can view but not edit. No content effort is permitted.
- Rationale: Kill SKUs are identified as negative net value. Any content time spent on them is waste. The system enforces this at field level, not just at queue level.

### DECISION-007: Drift-Safe v3 Alignment with 5 Spaces
- CIE uses 5 LLM Spaces (not v3's 4). Space 2 (Prompt Generator) is kept separate from Space 1.
- Rationale: v3 was designed for teams with a DTL who handles prompt generation as part of Space 1. CIE has a solo freelance developer with no DTL. Keeping Space 2 separate gives a clear two-step process: confirm requirement, then write prompt.

### DECISION-008: Light Theme Only
- No dark mode. No theme toggle. Light business-tool palette throughout.
- Rationale: The 2 users work in a business environment during office hours. Dark mode adds CSS complexity, testing burden, and design decisions with zero business value.

### DECISION-009: Desktop Only (1280px+)
- No responsive design. No mobile styles. No tablet optimisation.
- Rationale: Both users work on desktop machines. Mobile/responsive adds 30-40% more frontend work for a use case that doesn't exist.

### DECISION-010: Auto-Publish on Gate Pass
- When all 7 gates pass and writer clicks submit, content auto-deploys to Shopify + GMC.
- Rationale: This is the "Create Once, Deploy Everywhere" principle. The writer submits once. The system handles Shopify metafield updates and GMC feed generation. No manual copy-paste to each channel.

---

## 18. LOCKED COMPONENTS — DO NOT MODIFY

These components are finalised. Any change requires formal Change Protocol.

- **Intent taxonomy** (9 intents) — locked
- **Gate definitions** (G1-G7 + G6.1) — locked
- **Tier definitions** (Hero/Support/Harvest/Kill) — locked
- **OpenAPI contract** (cie_v231_openapi.yaml) — locked
- **RBAC role hierarchy** (8 roles) — locked
- **Colour palette** (light theme as defined in Section 8) — locked
- **Database schema** (canonical schema v2.3.2 + Semrush addendum) — locked
- **Cosine similarity threshold** (0.72) — default value locked, but configurable via admin Config screen
- **AI audit scoring scale** (0-3) — locked
- **Degradation quorum** (3 of 4 engines) — locked

---

## 19. ENVIRONMENT VARIABLES (Complete List)

```env
# Database
DB_HOST=
DB_PORT=3306
DB_DATABASE=cie_v232
DB_USERNAME=
DB_PASSWORD=

# AI Services
OPENAI_API_KEY=
OPENAI_EMBEDDING_MODEL=text-embedding-3-small
OPENAI_CHAT_MODEL=gpt-4o
ANTHROPIC_API_KEY=
ANTHROPIC_MODEL=claude-sonnet-4-20250514
GEMINI_API_KEY=
GEMINI_MODEL=gemini-pro
PERPLEXITY_API_KEY=
PERPLEXITY_MODEL=sonar-pro

# Google Services (shared service account)
GOOGLE_SERVICE_ACCOUNT_JSON=/path/to/service-account.json
GSC_PROPERTY=https://www.example.com
GA4_PROPERTY_ID=123456789
GMC_MERCHANT_ID=123456789

# Shopify (PRIMARY CHANNEL)
SHOPIFY_STORE_DOMAIN=store.myshopify.com
SHOPIFY_ADMIN_ACCESS_TOKEN=

# Amazon (DEFERRED — do not configure for v2.3.2)
# AMAZON_CLIENT_ID=
# AMAZON_CLIENT_SECRET=
# AMAZON_REFRESH_TOKEN=
# AMAZON_MARKETPLACE_ID=

# ERP
ERP_API_URL=
ERP_API_KEY=

# N8N
N8N_BASE_URL=http://localhost:5678
N8N_WEBHOOK_SECRET=

# Application
APP_ENV=production
APP_DEBUG=false
APP_URL=https://cie.example.com
VECTOR_SIMILARITY_THRESHOLD=0.72
```

**Security rule:** All API calls use HTTPS. No plaintext credentials in logs. API keys are NEVER returned in API responses. Webhook payloads verified with HMAC signatures. All secrets in .env only — never in code, never in commits.

---

## 20. IF YOU ARE UNSURE

1. **Is the behaviour documented?** → Search the spec documents.
2. **Can't find it?** → Search CLAUDE.md (this file) for context.
3. **Found context but no authorisation?** → It's a spec gap. STOP. Log GAP_LOG.md. Escalate.
4. **Tempted to just build it?** → That is Drift Type 1 (Silent Build). It will be deleted.
5. **Spec seems vague?** → That is a potential Drift Type 2 (Loose Interpretation). Flag ambiguity. Escalate.
6. **Want to add something helpful?** → That is Drift Type 3 (Judgment Habit). Log in Space 4 as proposal. Wait for approval.

**The spec is the system. The code is just today's implementation of it.**

---

*CIE v2.3.2 | CLAUDE.md | March 2026 | Drift-Safe Development v3 Aligned*
