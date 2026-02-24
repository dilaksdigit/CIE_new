# Full workflow + gate check

## What was done

1. **G7 gate** – Accepts canonical `expert_authority` (single statement) so Hero/Support can pass with your payload.
2. **POST /api/skus/{id}/intents** – Attaches primary + secondary intents so G2/G3 can pass after create.
3. **SkuController store** – Only sends columns that exist on `skus` (avoids 500 when `expert_authority`/`ai_answer_block` are missing).
4. **Migration 037** – Adds `ai_answer_block` and `expert_authority` to `skus` so G4 and G7 can pass when present in the payload.
5. **Script** – `run_full_workflow_check.ps1` runs: create SKU → attach intents → validate → print gate results and expected vs actual.

## Run the workflow

1. **Start API** (from repo root, document root = backend/php/public):
   ```bash
   cd backend/php && php -S localhost:8080 -t public
   ```

2. **Apply migration** (so G4/G7 can use answer block and expert authority):
   ```bash
   mysql -u root -p your_db < database/migrations/037_add_ai_answer_block_and_expert_authority_to_skus.sql
   ```
   If columns already exist, skip or run once (remove duplicate ALTER if needed).

3. **Run the check** (from repo root):
   ```powershell
   .\scripts\run_full_workflow_check.ps1 -BaseUrl "http://localhost:8080" -Token "demo-token" -JsonPath "backend\php\sample_cbl_blk_payload.json" -UniqueSku
   ```
   Use `-UniqueSku` to avoid duplicate `sku_code` on repeated runs.

## Payload file

`backend/php/sample_cbl_blk_payload.json` contains the CBL-BLK-3C-1M sample with expected gate results (G1–G7 PASS, overall ALL_PASS). The script creates the SKU, attaches intents (Compatibility + Installation/How-To, Specification), then validates and prints each gate and expected vs actual.

## If validation returns no gate list

If you see "Overall: Valid=False" but no per-gate lines, the API may be returning `results` under a different key or validation may be throwing (e.g. missing `sku_gate_status` or `validation_logs`). Check the raw response or logs. The script now tries both `data.results` and `data.gates` and shows `data.error` if present.
