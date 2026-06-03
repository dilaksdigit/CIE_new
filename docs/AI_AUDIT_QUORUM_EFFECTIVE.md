# Effective AI Audit Quorum (Google SGE Stubbed)

**Date:** 2026-06-03  
**Scope:** Documentation only — business rules and quorum code unchanged  
**Sources:** CLAUDE.md §12/§18; `decay.quorum_minimum`; `decay.quorum_pause_minimum`; `weekly_service.py`; `audit_engine.py`; `decay_detector.py`

---

## Engine roster

| Engine ID | Live API | Quorum role |
|-----------|----------|-------------|
| `chatgpt` | Yes (OpenAI) | Counts when `is_available` / useful response |
| `gemini` | Yes (Google) | Same |
| `perplexity` | Yes | Same |
| `google_sge` | **No — stub** | Always `is_available: false` / `engine_down` |

SGE stub: `weekly_service._query_engine` returns stub for `google_sge`; `GoogleSGEEngine` in `audit_engine.py` returns `engine_down`.

---

## Weekly run quorum (Patch 2 §2.1)

Seeded rules (do not change without architect approval):

| Rule key | Default | Meaning |
|----------|---------|---------|
| `decay.quorum_minimum` | **3** | Engines that must be **fully available** for run to advance (`quorum_met=true`, decay advanced) |
| `decay.quorum_pause_minimum` | **2** | Minimum available to **persist partial results** (pause decay) |

With SGE always unavailable, **at most 3 engines** can ever count toward quorum.

| Available engines | `quorum_status` | `quorum_met` | Decay action |
|-------------------|-----------------|--------------|--------------|
| 3 (all live APIs up) | COMPLETE | true | advanced |
| 2 | PARTIAL | false | paused |
| 0–1 | FAILED | false | frozen |

**Effective interpretation with SGE stubbed:** *“3 of 4” behaves as **3 of 3 live engines** — all three APIs must respond for a COMPLETE weekly run. There is no fourth countable engine until SGE is implemented.

---

## Degradation quorum (Hero decay loop)

CLAUDE.md §18 (locked): **3 of 4 engines must agree on degradation** (score 0).

Implementation (`decay_detector.py`):

- Unavailable engines are **excluded** from `available_scores`.
- Week counts as degraded only if `sum(score==0) >= 3` among available engine aggregates.
- Loop stops if `len(available_scores) < 3`.

**Effective with SGE stubbed:** degradation requires **unanimous zero among the 3 live engines** (3 of 3), not 3 of 4. This is stricter than the literal “3 of 4” label when one engine is permanently down.

**Operational note:** Document for KPI reviewer that SGE absence does not block weekly runs when ChatGPT + Gemini + Perplexity succeed, but decay triggers need all three to report zero for the same week.

---

## Config screen

Admin config maps `engines` → `decay.quorum_minimum` (`ConfigController`). Changing quorum without spec approval violates CLAUDE.md §18.

---

## Verification SQL (staging)

```sql
SELECT run_id, category, run_date, status, engines_available, quorum_met, degraded_mode
FROM ai_audit_runs
ORDER BY run_date DESC
LIMIT 5;
```

Expect recent production/staging runs: `engines_available` ≤ 3, `quorum_met` true only when all three live engines completed the run.
