# CIE v2.3.2 - Complete API Reference & Workflow

## 1. DEPLOYMENT ARCHITECTURE

```
┌─────────────────────────────────────────────────────────────────────┐
│                    FRONTEND (React 18)                              │
│                  http://localhost:8080                              │
│  ┌───────────────────────────────────────────────────────────────┐  │
│  │ Pages: Dashboard, SkuEdit, AiAudit, Clusters, Config, etc.    │  │
│  │ State: Zustand store with auth + notifications                │  │
│  │ Client: axios with VITE_API_URL = http://localhost:9000/api   │  │
│  └───────────────────────────────────────────────────────────────┘  │
└────────────────────────────────┬────────────────────────────────────┘
                                 │ HTTP
                                 ↓
┌────────────────────────────────────────────────────────────────────┐
│              PHP API (Laravel patterns)                            │
│              http://localhost:9000                                │
│  ┌─────────────────────────────────────────────────────────────┐ │
│  │ Routes: /api/skus, /api/audit, /api/briefs, etc.           │ │
│  │ Controllers: SkuController, AuditController, etc.          │ │
│  │ Services: ValidationService, PythonWorkerClient           │ │
│  │ Models: Sku, Cluster, ValidationLog, AuditResult         │ │
│  └─────────────────────────────────────────────────────────────┘ │
└────────────┬──────────────────────────────────────────┬────────────┘
             │ HTTP (internal)                          │ Database
             ↓                                          ↓
┌──────────────────────────┐                 ┌──────────────────────┐
│   PYTHON API (Flask)     │                 │   MySQL 8.0          │
│ http://localhost:5000    │                 │ localhost:3306       │
│ ┌──────────────────────┐ │                 │                      │
│ │ Endpoints:           │ │                 │ Tables:              │
│ │ /validate-vector     │ │                 │ - skus               │
│ │ /queue/audit         │ │                 │ - clusters           │
│ │ /queue/brief-gen     │ │                 │ - validation_logs    │
│ │ /audits/{id}         │ │                 │ - audit_results      │
│ │ /health              │ │                 │ - content_briefs     │
│ │                      │ │                 │ - users              │
│ │ Workers:             │ │                 │ - roles              │
│ │ - AI Audit Engine    │ │                 │ - tier_history       │
│ │ - Brief Generator    │ │                 │ - validation_logs    │
│ │ - Vector Embeddings  │ │                 │ - erp_sync_log       │
│ └──────────────────────┘ │                 │                      │
│                          │                 │ Credentials:         │
│ External APIs:           │                 │ user: cie_user       │
│ - OpenAI (embeddings)    │                 │ pass: cie_password   │
│ - Anthropic API          │                 └──────────────────────┘
│ - Google Vertex AI       │
└──────────────┬───────────┘
               │ Cache
               ↓
         ┌──────────────┐
         │  Redis 7.0   │
         │ :6379        │
         │              │
         │ - Queues     │
         │ - Vectors    │
         │ - Sessions   │
         └──────────────┘
```

---

## 2. REQUEST/RESPONSE FLOW DIAGRAM

### Flow 1: Create/Edit SKU with Validation

```
┌──────────┐
│ Frontend │ PUT /api/skus/{id} {title, desc, ...}
│ SkuEdit  │────────────────────────────────────────→ ┌─────────────────┐
└──────────┘                                          │ PHP API         │
                                                      │ SkuController   │
                                                      │                 │
                                                      │ 1. Update SKU   │
                                                      │ 2. Call         │
                                                      │    ValidationSvc│
                                                      │    .validate()  │
                                                      │                 │
                                                      │ ┌──────────────┐│
                                                      │ │ ValidationSvc││
                                                      │ │ Chains:      ││
                                                      │ │ • G1 gate    ││
                                                      │ │ • G2 gate    ││
                                                      │ │ • G3 gate    ││
                                                      │ │ • G4 gate    ││
                                                      │ │ • Vector val ││
                                                      │ └──────┬───────┘│
                                                      │        │       │
                                                      │        ↓       │
                                                      │ PythonWorkerClt│
                                                      │ .validateVector│
                                                      └────────┬───────┘
                                                               │ HTTP
                                                               ↓
                                                       ┌──────────────┐
                                                       │ Python API   │
                                                       │ /validate    │
                                                       │ -vector      │
                                                       │              │
                                                       │ 1. Get embed │
                                                       │ 2. Find      │
                                                       │    cluster   │
                                                       │ 3. Calc      │
                                                       │    cosine    │
                                                       └──────┬───────┘
                                                              │ JSON
                                                              ↓
                                                    {valid, similarity}

← ← ← ← ← ← ← ← ← RESPONSE CHAIN ← ← ← ← ← ← ←

Response:
{
  "sku": {id, title, desc, ...},
  "validation": {
    "valid": true/false,
    "status": "VALID|DEGRADED|INVALID",
    "results": [G1, G2, G3, G4, Vector],
    "next_action": "..."
  }
}
```

### Flow 2: Run AI Audit

```
┌──────────┐
│ Frontend │ POST /api/audit/{sku_id}
│ AiAudit  │───────────────────────────→ ┌─────────────────┐
└──────────┘                             │ PHP API         │
                                         │ AuditController │
                                         │                 │
                                         │ 1. Get SKU      │
                                         │ 2. Queue audit  │
                                         │    job          │
                                         │ 3. Return 202   │
                                         │    (queued)     │
                                         │                 │
                                         │ ┌──────────────┐│
                                         │ │ Python       ││
                                         │ │ WorkerClient ││
                                         │ │ .queueAudit()││
                                         │ └──────┬───────┘│
                                         └────────┬───────┘
                                                  │ HTTP
                                                  ↓
                                          ┌──────────────┐
                                          │ Python API   │
                                          │ /queue/audit │
                                          │              │
                                          │ 1. Generate  │
                                          │    audit_id  │
                                          │ 2. Store in  │
                                          │    queue     │
                                          │ 3. Return 202│
                                          └──────┬───────┘
                                                 │
                                                 ↓
                                          [In Background]
                                          Audit Worker:
                                          1. Fetch SKU
                                          2. Query 20 ?s
                                          3. Call 4 AI
                                             engines
                                          4. Store results
                                          5. Trigger brief
                                             generation

Response (202):
{
  "sku_id": 123,
  "status": "queued",
  "audit_id": "...",
  "message": "Audit queued"
}

Later polling:
GET /api/audit-result/{audit_id}
← {status, results, engines...}
```

### Flow 3: Get Validation Results

```
┌──────────┐
│ Frontend │ GET /api/skus/{id}
│ SkuEdit  │─────────────────→ ┌─────────────────┐
└──────────┘                   │ PHP API         │
                               │ SkuController   │
                               │ .show()         │
                               │                 │
                               │ Query with:     │
                               │ - Sku data      │
                               │ - Cluster ref   │
                               │ - Intents       │
                               └────────┬────────┘
                                        │ MySQL
                                        ↓
                                    [Database]

Response:
{
  "sku": {
    "id": 123,
    "sku_code": "LMP-COT-...",
    "title": "...",
    "description": "...",
    "validation_status": "VALID",
    "tier": "HERO",
    "primary_cluster_id": 45,
    "primaryCluster": {...},
    "skuIntents": [...]
  },
  "instructions": {
    "tier_lock_reason": "...",
    "cms_banner": "...",
    "field_tooltips": {...}
  }
}
```

---

## 3. API ENDPOINTS REFERENCE

### 3.1 Authentication
```
POST /api/auth/login
Payload:
  {
    "email": "user@company.com",
    "password": "..."
  }
Response:
  {
    "token": "Bearer ...",
    "user": {id, email, role, ...}
  }
```

### 3.2 SKU Management
```
GET /api/skus?tier=HERO&search=cable
→ [{id, sku_code, title, tier, validation_status, ...}]

GET /api/skus/{id}
→ {sku: {...}, instructions: {...}}

POST /api/skus
Payload:
  {
    "sku_code": "...",
    "title": "...",
    "description": "...",
    "primary_cluster_id": 45
  }
Response:
  {
    "sku": {...},
    "validation": {...}
  }
Status: 201

PUT /api/skus/{id}
Payload: {fields to update}
Response:
  {
    "sku": {...},
    "validation": {...}
  }
Status: 200
```

### 3.3 Validation
```
POST /api/skus/{id}/validate
→
{
  "valid": true,
  "status": "VALID|DEGRADED|INVALID",
  "validation_log_id": 999,
  "results": [
    {gate: "G1", passed: true, reason: "..."},
    {gate: "G2", passed: true, reason: "..."},
    {gate: "G3", passed: true, reason: "..."},
    {gate: "G4", passed: true, reason: "..."},
    {gate: "G5_VECTOR", passed: true, similarity: 0.85, reason: "..."}
  ],
  "next_action": "Ready for publication",
  "ai_validation_pending": false
}
Status: 200
```

### 3.4 Audit Management
```
POST /api/audit/{sku_id}
→
{
  "sku_id": 123,
  "status": "queued",
  "audit_id": "...",
  "message": "..."
}
Status: 202

GET /api/audit/{sku_id}/history
→ [{id, sku_id, created_at, engines: [{engine, score, status}]}, ...]

GET /api/audit-result/{audit_id}
→ {status, results, ...}
Status: 200 (if done) or 202 (if pending)
```

### 3.5 Brief Management
```
GET /api/briefs
→ [{id, sku_id, title, brief_text, created_at}, ...]

POST /api/briefs
Payload:
  {
    "sku_id": 123,
    "title": "...",
    "brief_text": "..."
  }
Response: {id, sku_id, title, ...}
Status: 201

GET /api/briefs/{id}
→ {id, sku_id, title, brief_text, ...}
```

### 3.6 Cluster Management
```
GET /api/clusters
→ [{id, name, description, vector_count, ...}]

GET /api/clusters/{id}
→ {id, name, description, vectors: [...]}

POST /api/clusters
Payload: {name, description}
Response: {id, name, ...}
Status: 201

PUT /api/clusters/{id}
Payload: {name, description}
Response: {id, name, ...}
```

### 3.7 Tier Management
```
POST /api/tiers/recalculate
→
{
  "recalculated": true,
  "affected_skus": 456,
  "timestamp": "2026-02-16T10:00:00Z"
}
Status: 200
```

### 3.8 Python Worker Endpoints
```
GET /health
→ {status: "healthy", service: "python-worker"}

POST /validate-vector
Payload: {description, cluster_id, sku_id}
→ {valid, similarity, reason}

POST /queue/audit
Payload: {sku_id}
→ {queued: true, audit_id: "..."}
Status: 202

POST /queue/brief-generation
Payload: {sku_id, title, category}
→ {queued: true, brief_id: "..."}
Status: 202

GET /audits/{audit_id}
→ {sku_id, status, engines: [...], results: [...]}

GET /briefs/{brief_id}
→ {sku_id, status, brief_text, ...}
```

---

## 4. COMPLETE WORKFLOW CONNECTIONS

### Path A: Create SKU → Validation → Audit (Success Path)
```
1. Frontend: POST /skus
   ↓
2. SkuController.store()
   - Create SKU in DB
   - Call ValidationService.validate(sku)
   ↓
3. ValidationService.validate()
   - Run G1 (title/intent gate) ✓
   - Run G2 (description gate) ✓
   - Run G3 (URL gate) ✓
   - Run G4 (answer block gate) ✓
   - Call PythonWorkerClient.validateVector()
   ↓
4. PythonWorkerClient.validateVector()
   - HTTP POST python:5000/validate-vector
   ↓
5. Python API /validate-vector
   - Get embedding via OpenAI
   - Query cluster vector from DB
   - Calculate cosine similarity
   - Return {valid, similarity}
   ↓
6. ValidationService completes
   - Create ValidationLog entry
   - Return {valid: true, status: "VALID", next_action: "Ready"}
   ↓
7. SkuController returns response
   ↓
8. Frontend shows validation results
   - "Ready for publication"
   - Allow user to trigger audit
   ↓
9. Frontend: POST /audit/{sku_id}
   ↓
10. AuditController.runAudit()
    - Call PythonWorkerClient.queueAudit()
    ↓
11. PythonWorkerClient.queueAudit()
    - HTTP POST python:5000/queue/audit
    ↓
12. Python API /queue/audit
    - Generate audit_id
    - Store in queue
    - Return audit_id (202 Accepted)
    ↓
13. [Background] Audit Worker starts
    - Fetch 20 golden questions
    - Query 4 AI engines
    - Store results in AuditResult table
    - Trigger brief generation
    ↓
14. Frontend periodically polls
    - GET /api/audit-result/{audit_id}
    - Until status = "completed"
    ↓
15. Display audit results
    - Citation scores per engine
    - Citation % per category
    - Decay alerts if needed
```

### Path B: Edit SKU → Validation Fails (Degraded Path)
```
1. Frontend: PUT /skus/123
   ↓
2. SkuController.update()
   - Update SKU fields
   - Call ValidationService.validate()
   ↓
3. ValidationService validates gates
   - G1: Title Intent ✓
   - G2: Description ✓
   - G3: URL ✓
   - G4: Answer Block ✗ (too short)
   - Vector: (not reached, G4 blocking)
   ↓
4. ValidationService determines status
   - blockingFailure = G4 result
   - status = INVALID
   ↓
5. Return validation result
   {
     "valid": false,
     "status": "INVALID",
     "results": [{gate: "G4", passed: false, reason: "Too short"}],
     "next_action": "Fix validation errors before publication"
   }
   ↓
6. Frontend shows validation error
   - Highlight failing gate
   - Show required character count
   - Disable publication button
   ↓
7. User fixes answer block
   - PUT /skus/123 (resubmit)
   - Validation re-runs
   - Now passes all gates
```

### Path C: Vector Validation Fails (Degraded/Soft Fail)
```
1. SKU passes all gates (G1-G4)
2. Vector validation called
3. Python API returns similarity: 0.55 (below threshold 0.72)
4. Vector gate blocking = true BUT
5. ValidationService creates log with status = DEGRADED
6. Return:
   {
     "valid": false,
     "status": "DEGRADED",
     "ai_validation_pending": true,
     "next_action": "Soft fail - publication blocked but will retry auto"
   }
7. Backend schedules retry job
8. User can still save SKU, but not publish
9. AI engines will re-validate overnight
```

---

## 5. ERROR HANDLING & FAIL-SOFT MECHANISMS

### Python Worker Unavailable
```
PHP tries: POST python:5000/validate-vector
Response: Connection timeout
PythonWorkerClient: Catches RequestException
Returns: {valid: false, blocking: false, reason: "Service unavailable"}
Result: Gate doesn't block, marked DEGRADED
Effect: SKU saved, publication delayed, retry scheduled
```

### Database Connection Error
```
ValidationService tries: $sku->update()
Exception: PDOException
Caught by: Controller error handler
Return HTTP 500 with error message
Frontend: Shows "Temporary system error, please retry"
```

### OpenAI API Error
```
Python tries: openai.Embedding.create()
Error: Rate limit, invalid key, timeout
Caught: PythonWorkerClient logs error
Returns: {similar: 0, reason: "External service error"}
Effect: Soft fails, doesn't block publication
```

---

## 6. ENVIRONMENT CONFIGURATION

### .env (Root)
```
APP_ENV=local
APP_DEBUG=true
DB_HOST=db
DB_USERNAME=cie_user
DB_PASSWORD=cie_password
REDIS_URL=redis://redis:6379/0
PYTHON_API_URL=http://python-worker:5000
SIMILARITY_THRESHOLD=0.72
```

### frontend/.env.local
```
VITE_API_URL=http://localhost:9000/api
VITE_PYTHON_API_URL=http://localhost:5000
VITE_ENABLE_DEBUG=true
```

### docker-compose.yml
```yaml
php-api:
  environment:
    - DB_HOST=db
    - PYTHON_API_URL=http://python-worker:5000

python-worker:
  environment:
    - DB_HOST=db
    - PYTHON_API_URL=http://python-worker:5000
```

---

## 7. TESTING VERIFICATION CHECKLIST

- [ ] Python API health: `curl http://localhost:5000/health`
- [ ] Frontend loads: http://localhost:8080
- [ ] Login works: POST /api/auth/login
- [ ] List SKUs: GET /api/skus
- [ ] Create SKU: POST /api/skus (validation runs)
- [ ] Vector validation: POST python:5000/validate-vector
- [ ] Queue audit: POST /api/audit/{sku_id}
- [ ] Poll audit: GET /api/audit-result/{id}
- [ ] Validation logs: Check DB validation_logs table
- [ ] Fail-soft: Stop Python, try validation (should degrade)

---

## 8. DEPLOYMENT COMMANDS

```bash
# Build and start
docker-compose up -d

# Run migrations
docker-compose exec php-api php artisan migrate

# Seed data
docker-compose exec php-api php artisan db:seed

# Check logs
docker-compose logs -f php-api
docker-compose logs -f python-worker

# Stop all
docker-compose down
```

---

## 9. NEXT STEPS (TODO)

- [ ] Implement Redis job queue (replace in-memory)
- [ ] Create audit worker loop (process queue items)
- [ ] Implement brief generation worker
- [ ] Add ERP sync worker
- [ ] Setup monitoring/alerting
- [ ] Add comprehensive logging
- [ ] Create e2e test suite
- [ ] Deploy to staging/production
