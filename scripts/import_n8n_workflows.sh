#!/usr/bin/env bash
# SOURCE: Production Completion Roadmap P5.6 — import all CIE N8N workflows
# Requires: N8N running at N8N_BASE_URL with API key in N8N_API_KEY
#
# Channel publish (PHP ChannelDeployService): activate shopify_deploy + gmc_deploy after import.
# W6_channel_deploy.json is optional orchestration; PHP uses /webhook/shopify-deploy and /webhook/gmc-deploy by default.

set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
N8N_URL="${N8N_BASE_URL:-http://localhost:5678}"
WORKFLOW_DIR="${ROOT}/n8n/workflows"

if [ -z "${N8N_API_KEY:-}" ]; then
  echo "ERROR: Set N8N_API_KEY before running." >&2
  exit 1
fi

for workflow in \
  shopify_deploy.json \
  gmc_deploy.json \
  W1_erp_sync_tier_calc.json \
  W2_vision_ai_extract.json \
  W3_semantic_embed_cluster.json \
  W4_content_draft_generation.json \
  W5_gate_validation.json \
  W6_channel_deploy.json \
  W7_decay_check.json \
  W8_ai_audit_scheduler.json
do
  path="${WORKFLOW_DIR}/${workflow}"
  if [ ! -f "$path" ]; then
    echo "SKIP (missing): $workflow"
    continue
  fi
  echo "Importing $workflow..."
  curl -sS -X POST "${N8N_URL}/api/v1/workflows" \
    -H "X-N8N-API-KEY: ${N8N_API_KEY}" \
    -H "Content-Type: application/json" \
    -d @"$path"
  echo ""
done

echo "All workflows processed."
