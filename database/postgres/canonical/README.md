# PostgreSQL canonical schema assets (CIE v2.3.2)

## Authority

Greenfield database init is **PostgreSQL-native**, per:

- `CIE_Master_Developer_Build_Spec.docx` §6 (full DDL)
- `CIE_v231_Developer_Build_Pack.pdf` (per-table indexes)
- `CIE_v232_Hardening_Addendum.pdf` (`idx_retry_status` on `vector_retry_queue`)
- `CIE_v232_Semrush_CSV_Import_Spec.docx` (`semrush_imports` indexes)

Legacy `database/migrations/*.sql` is **MySQL reference only** (`DECISION-013`). Do not use
`convert_mysql_migrations_to_pg.py` as the sole greenfield path — it strips inline `KEY`/`INDEX`
from `CREATE TABLE`.

## Files

| File | Role |
|------|------|
| `03_spec_indexes.sql` | Idempotent `CREATE INDEX` / `UNIQUE INDEX` restored after migrations |
| `expected_indexes.json` | Index names for `scripts/verify_pg_indexes.py` |

Regenerate indexes after MySQL migration changes:

```bash
python scripts/generate_pg_spec_indexes.py
```

## Docker init order

1. `init/00_extensions.sql`
2. `init/01_migrations_bootstrap.sql` (table/column migrations)
3. `init/02_manual_trigger_compat.sql`
4. `init/03_spec_indexes.sql` → includes this directory’s `03_spec_indexes.sql`
