# PostgreSQL (CIE v2.3.2)

Application code (PHP Laravel + Python FastAPI) targets **PostgreSQL** via `DB_CONNECTION=pgsql` and `psycopg2`.

## Greenfield schema (spec-native)

**Authority:** Master Build Spec §6, Build Pack per-table indexes, Hardening Addendum, Semrush spec.

| Path | Role |
|------|------|
| `database/migrations/*.sql` | Legacy **MySQL reference only** (`DECISION-013`) |
| `database/postgres/migrations/*.sql` | Table/column migrations (syntax-converted from MySQL) |
| `database/postgres/canonical/03_spec_indexes.sql` | **Required** idempotent indexes stripped by the converter |
| `database/postgres/init/` | Docker init order |

Docker Compose mounts:

- `database/postgres/init/` → `/docker-entrypoint-initdb.d`
- `database/postgres/migrations/` → `/docker-entrypoint-migrations`
- `database/postgres/canonical/` → `/docker-entrypoint-canonical`

Init order on first boot:

1. `00_extensions.sql`
2. `01_migrations_bootstrap.sql` (all table migrations)
3. `02_manual_trigger_compat.sql` (immutable audit triggers)
4. `03_spec_indexes.sql` → applies `canonical/03_spec_indexes.sql`

**Do not** use `convert_mysql_migrations_to_pg.py` alone for greenfield — it removes inline `KEY`/`INDEX` from `CREATE TABLE` (`DECISION-014`).

## Regenerate

When legacy MySQL migrations change:

```bash
python scripts/convert_mysql_migrations_to_pg.py   # table DDL only (optional refresh)
python scripts/generate_pg_spec_indexes.py       # required — restores indexes
```

## Verify

```bash
psql -h localhost -U cie_user -d cie_v232 -c "\dt"
python scripts/verify_pg_indexes.py
```

Uses `DATABASE_URL` or `DB_*` from `.env`.

## Environment

```env
DB_CONNECTION=pgsql
DB_PORT=5432
DATABASE_URL=postgresql://USER:PASS@HOST:5432/cie_v232
```

## One-time content backfill

If Writer Edit shows an empty Answer Block but Description has text (legacy seed):

```bash
psql -h localhost -U cie_user -d cie_v232 -f database/migrations/155_backfill_ai_answer_block_from_long_description.sql
```
