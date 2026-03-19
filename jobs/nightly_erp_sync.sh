#!/bin/bash
# Nightly ERP sync — reads from configured connector (CSV/REST/ODBC)
# and pushes to PHP /api/v1/erp/sync for tier recalculation.
cd "$(dirname "$0")/../backend/python" || exit 1
python3 -m src.jobs.nightly_erp_sync
