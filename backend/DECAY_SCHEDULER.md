# Decay escalation scheduler (v2.3.2)

**Entry points**

1. **PHP-native (recommended)**  
   `php artisan cie:decay-check`  
   - Reads latest quorum-met run from `ai_audit_runs`, citation scores from `ai_audit_results`.  
   - For each Hero SKU: zero citation → increment `decay_consecutive_zeros`; week 3 → set `decay_status = auto_brief` and create `content_briefs` row (DECAY_REFRESH), queue Python brief-generation.  
   - Scheduled in `app/Console/Kernel.php`: weekly Monday 06:30.

2. **Artisan (Python runner)**  
   `php artisan decay:run`  
   - Runs `backend/python/run_decay_escalation.py` if the file exists.  
   - Scheduled: weekly Monday 06:00.

3. **Cron (direct Python)**  
   `0 6 * * 1 cd /path/to/CIE/backend/python && python run_decay_escalation.py`  
   - Requires `DATABASE_URL` or `DB_*` env. Run after the weekly AI audit.

**PHP-only (no audit DB)**  
`php artisan decay:run --php-only` — use `DecayService::processWeeklyDecay()` per SKU when you supply citation scores from your own pipeline.
