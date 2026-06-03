"""
PostgreSQL upsert / UUID fragments.
"""

# sync_status.uq_sync_status_service (service)
SQL_UPSERT_SYNC_STATUS_OK = """
    INSERT INTO sync_status (service, status, last_success_at, last_error, last_error_at)
    VALUES (%s, 'ok', NOW(), NULL, NULL)
    ON CONFLICT (service) DO UPDATE SET
        status = EXCLUDED.status,
        last_success_at = EXCLUDED.last_success_at,
        last_error = NULL,
        last_error_at = NULL
"""

SQL_UPSERT_SYNC_STATUS_OK_NAMED = """
    INSERT INTO sync_status (service, status, last_success_at, last_error, last_error_at)
    VALUES (%s, %s, NOW(), NULL, NULL)
    ON CONFLICT (service) DO UPDATE SET
        status = EXCLUDED.status,
        last_success_at = EXCLUDED.last_success_at,
        last_error = NULL,
        last_error_at = NULL
"""

SQL_UPSERT_SYNC_STATUS_ERROR = """
    INSERT INTO sync_status (service, status, last_error, last_error_at)
    VALUES (%s, %s, %s, NOW())
    ON CONFLICT (service) DO UPDATE SET
        status = EXCLUDED.status,
        last_error = EXCLUDED.last_error,
        last_error_at = EXCLUDED.last_error_at
"""

# ai_audit_results.unique_run_question_engine
SQL_UPSERT_AI_AUDIT_RESULT = """
    INSERT INTO ai_audit_results
        (run_id, question_id, engine, score, response_hash, skip_reason,
         cited_sku_id, week_ending, is_available, consecutive_zero_weeks)
    VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s, %s)
    ON CONFLICT (run_id, question_id, engine) DO UPDATE SET
        score = EXCLUDED.score,
        response_hash = EXCLUDED.response_hash,
        skip_reason = EXCLUDED.skip_reason,
        cited_sku_id = EXCLUDED.cited_sku_id,
        week_ending = EXCLUDED.week_ending,
        is_available = EXCLUDED.is_available,
        consecutive_zero_weeks = EXCLUDED.consecutive_zero_weeks
"""

# sku_gate_status.uq_gate_status_sku_code (sku_id, gate_code)
SQL_UPSERT_SKU_GATE_STATUS = """
    INSERT INTO sku_gate_status (sku_id, gate_code, status, error_code, checked_at)
    VALUES (%s, %s, %s, %s, NOW())
    ON CONFLICT (sku_id, gate_code) DO UPDATE SET
        status = EXCLUDED.status,
        error_code = EXCLUDED.error_code,
        checked_at = NOW()
"""

SQL_UPSERT_SKU_GATE_STATUS_G4_VECTOR = """
    INSERT INTO sku_gate_status (sku_id, gate_code, status, error_code, checked_at)
    VALUES (%s, 'G4_VECTOR', %s, %s, NOW())
    ON CONFLICT (sku_id, gate_code) DO UPDATE SET
        status = EXCLUDED.status,
        error_code = EXCLUDED.error_code,
        checked_at = NOW()
"""

# audit_log fallback when sync_status write fails (new_value = %s)
SQL_INSERT_AUDIT_LOG_SYSTEM = """
    INSERT INTO audit_log (
        entity_type, entity_id, action, field_name, old_value, new_value,
        actor_id, actor_role, timestamp, user_id
    )
    VALUES (
        'system', gen_random_uuid()::text, %s, NULL, NULL, %s,
        NULL, 'system', NOW(), NULL
    )
"""
