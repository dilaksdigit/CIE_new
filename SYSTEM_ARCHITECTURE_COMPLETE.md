# CIE v2.3.2 - Complete System Architecture & Connection Map

> **Database:** PostgreSQL 16 is system of record (`DECISION-013`). Use port **5432** and `database/postgres/` for schema — not legacy MySQL paths.

## 🏗️ System Architecture Overview

```
┌─────────────────────────────────────────────────────────────────────────────────────┐
│                          🌐 THIRD-PARTY SERVICES                                     │
│  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐           │
│  │  OpenAI GPU  │  │ Anthropic    │  │  Google SGE  │  │ Perplexity   │           │
│  │ Embeddings & │  │  Claude 3.5  │  │  Gemini 1.5  │  │   Research   │           │
│  │   API        │  │   Sonnet     │  │              │  │              │           │
│  └──────┬───────┘  └──────┬───────┘  └──────┬───────┘  └──────┬───────┘           │
│         │                 │                 │                 │                    │
│         └─────────────────┴─────────────────┴─────────────────┘                    │
│                                    ▲                                               │
│                                    │ HTTPS API Calls                               │
└────────────────────────────────────┼───────────────────────────────────────────────┘
                                     │
                    ┌────────────────┴────────────────┐
                    │                                 │
        ┌───────────▼──────────┐            ┌────────▼─────────┐
        │   VECTORS CACHE      │            │  AUDIT QUEUES    │
        │                      │            │                  │
        │  cluster_vectors TB  │            │  Redis           │
        │  └─ cluster_id       │            │  ┌─ audit:queue  │
        │  └─ vector (1536)    │            │  └─ brief:queue  │
        │  └─ updated_at       │            │                  │
        └───────────────────────┘            └──────────────────┘
                    │                               ▲
                    │                               │
        ┌───────────▼──────────────────────────────┴──────────┐
        │                                                      │
        │  🐍 PYTHON WORKER (Flask) - PORT 5000             │
        │  ┌──────────────────────────────────────────────┐ │
        │  │ endpoints = [                                │ │
        │  │   /health                                    │ │
        │  │   /validate-vector (POST)                    │ │
        │  │   /queue/audit (POST)                        │ │
        │  │   /queue/brief-generation (POST)             │ │
        │  │   /audits/{id} (GET)                         │ │
        │  │   /briefs/{id} (GET)                         │ │
        │  │ ]                                            │ │
        │  └──────────────────────────────────────────────┘ │
        │  ┌──────────────────────────────────────────────┐ │
        │  │ services = [                                 │ │
        │  │   vector/embedding.py (OpenAI)              │ │
        │  │   vector/validation.py (cosine sim)         │ │
        │  │   ai_audit/audit_engine.py (44 engines)     │ │
        │  │   brief_generator/generator.py              │ │
        │  │   erp_sync/connectors/*.py                  │ │
        │  │   jobs/*.py (workers, schedulers)           │ │
        │  │ ]                                            │ │
        │  └──────────────────────────────────────────────┘ │
        │                                                    │
        │  [Background Workers]                             │
        │  • Audit Job Processor (watch queue)              │
        │  • Brief Generator (watch queue)                  │
        │  • ERP Sync Cron (nightly 2 AM)                  │
        │  • Vector Retry Queue (hourly)                    │
        │  • Decay Check (weekly Mon)                       │
        └──────────────┬───────────────────────────────────┘
                       │ HTTP API Calls
                       │ FROM PHP
                       │
        ┌──────────────▼──────────────┐
        │    🐘 PostgreSQL 16 - PORT 5432 │
        │                             │
        │  cie_v232 database          │
        │  ┌──────────────────────┐  │
        │  │ CORE TABLES:         │  │
        │  │ • users              │  │
        │  │ • roles              │  │
        │  │ • skus               │  │
        │  │ • clusters           │  │
        │  │ • intents            │  │
        │  │ • sku_intents        │  │
        │  │                      │  │
        │  │ AUDIT TABLES:        │  │
        │  │ • validation_logs    │  │
        │  │ • audit_results      │  │
        │  │ • content_briefs     │  │
        │  │ • tier_history       │  │
        │  │ • audit_log          │  │
        │  │ • erp_sync_log       │  │
        │  │                      │  │
        │  │ VECTOR TABLES:       │  │
        │  │ • cluster_vectors    │  │
        │  │ • sku_vectors        │  │
        │  └──────────────────────┘  │
        └──────────────┬──────────────┘
                       │
                       │ Database Queries
                       │
        ┌──────────────▼─────────────────────────────────┐
        │                                                │
        │  🔥 PHP API (Laravel Patterns) - PORT 9000     │
        │  ┌────────────────────────────────────────┐   │
        │  │ routes/api.php (ALL ROUTES)            │   │
        │  │ ├─ GET    /skus                        │   │
        │  │ ├─ GET    /skus/{id}                   │   │
        │  │ ├─ POST   /skus                        │   │
        │  │ ├─ PUT    /skus/{id}                   │   │
        │  │ ├─ POST   /skus/{id}/validate          │   │
        │  │ ├─ POST   /audit/{sku_id}              │   │
        │  │ ├─ GET    /audit/{sku_id}/history      │   │
        │  │ ├─ GET    /audit-result/{audit_id}     │   │
        │  │ ├─ GET    /clusters                    │   │
        │  │ ├─ POST   /clusters                    │   │
        │  │ ├─ GET    /briefs                      │   │
        │  │ └─ POST   /briefs                      │   │
        │  └────────────────────────────────────────┘   │
        │  ┌────────────────────────────────────────┐   │
        │  │ Controllers:                           │   │
        │  │ ├─ SkuController (UPDATED)            │   │
        │  │ │  ├─ index()                         │   │
        │  │ │  ├─ show()                          │   │
        │  │ │  ├─ store() + validate              │   │
        │  │ │  └─ update() + validate             │   │
        │  │ │                                      │   │
        │  │ ├─ AuditController (UPDATED)         │   │
        │  │ │  ├─ runAudit() → queue job         │   │
        │  │ │  ├─ history()                       │   │
        │  │ │  └─ getResult()                     │   │
        │  │ │                                      │   │
        │  │ ├─ ValidationController               │   │
        │  │ │  └─ validate($sku_id)              │   │
        │  │ │                                      │   │
        │  │ ├─ BriefController                    │   │
        │  │ ├─ ClusterController                  │   │
        │  │ └─ TierController                     │   │
        │  └────────────────────────────────────────┘   │
        │  ┌────────────────────────────────────────┐   │
        │  │ Services:                              │   │
        │  │ ├─ ValidationService (UPDATED)        │   │
        │  │ │  └─ validate($sku)                 │   │
        │  │ │     ├─ Run G1-4 gates             │   │
        │  │ │     ├─ Call validateVector()      │   │
        │  │ │     ├─ Create ValidationLog       │   │
        │  │ │     └─ Return status              │   │
        │  │ │                                      │   │
        │  │ ├─ PythonWorkerClient (NEW!)         │   │
        │  │ │  ├─ validateVector()              │   │
        │  │ │  ├─ queueAudit()                  │   │
        │  │ │  ├─ queueBriefGeneration()        │   │
        │  │ │  ├─ getAuditResult()              │   │
        │  │ │  ├─ health()                      │   │
        │  │ │  └─ [error handling]              │   │
        │  │ │                                      │   │
        │  │ └─ Other services...                  │   │
        │  └────────────────────────────────────────┘   │
        │                                                │
        │  Models (Database Layer):                      │
        │  ├─ Sku                                        │
        │  ├─ Cluster                                    │
        │  ├─ ValidationLog                              │
        │  ├─ AuditResult                                │
        │  ├─ ContentBrief                               │
        │  ├─ User, Role, Intent                         │
        │  └─ etc...                                     │
        └────────────────┬──────────────────────────────┘
                         │ HTTP API
                         │
        ┌────────────────▼──────────────────┐
        │                                   │
        │  ⚛️ REACT SPA - PORT 8080         │
        │  (Vite dev server)                │
        │                                   │
        │  ┌──────────────────────────────┐│
        │  │ Pages:                      ││
        │  │ ├─ Dashboard                ││
        │  │ ├─ SkuEdit (with validation)││
        │  │ ├─ AiAudit (polling results)││
        │  │ ├─ ReviewQueue              ││
        │  │ ├─ Clusters                 ││
        │  │ ├─ Briefs                   ││
        │  │ ├─ Config                   ││
        │  │ ├─ AuditTrail               ││
        │  │ └─ etc...                   ││
        │  └──────────────────────────────┘│
        │                                   │
        │  ┌──────────────────────────────┐│
        │  │ Services:                   ││
        │  │ ├─ src/services/api.js      ││
        │  │ │  └─ axios client          ││
        │  │ │     baseURL: env.VITE_... ││
        │  │ │     Headers: Auth token   ││
        │  │ │     Interceptors: 401     ││
        │  │ │                           ││
        │  │ └─ Methods:                 ││
        │  │    ├─ authApi.login()       ││
        │  │    ├─ skuApi.list()         ││
        │  │    ├─ skuApi.create()       ││
        │  │    ├─ skuApi.update()       ││
        │  │    ├─ auditApi.run()        ││
        │  │    ├─ briefApi.create()     ││
        │  │    └─ ...                   ││
        │  └──────────────────────────────┘│
        │                                   │
        │  ┌──────────────────────────────┐│
        │  │ State (Zustand):            ││
        │  │ ├─ Auth (user, token)       ││
        │  │ ├─ SKU (list, selected)     ││
        │  │ ├─ Notifications            ││
        │  │ └─ ...                      ││
        │  └──────────────────────────────┘│
        │                                   │
        │  ┌──────────────────────────────┐│
        │  │ Configuration:              ││
        │  │ ├─ .env.local               ││
        │  │ │  ├─ VITE_API_URL          ││
        │  │ │  └─ VITE_PYTHON_API_URL   ││
        │  │ │                           ││
        │  │ └─ vite.config.js           ││
        │  └──────────────────────────────┘│
        └───────────────────────────────────┘
                         ▲
                         │ HTTPS
                         │ User Browsing
                         │
            ┌────────────┴────────────┐
            │                         │
            │   👤 END USERS          │
            │                         │
            │ • Editors               │
            │ • SEO Governors         │
            │ • AI Operations         │
            │ • Admins                │
            │ • Finance               │
            └─────────────────────────┘
```

---

## 🔄 Data Flow: Create SKU with Validation

```
User Input (Frontend)
  ↓
{"title": "...", "description": "...", "cluster_id": 1}
  ↓
POST /api/skus
  ↓
SkuController.store()
  │
  ├─ 1. Create Sku model in DB ✓
  │   sku = Sku::create($data)
  │
  ├─ 2. Call ValidationService.validate($sku) ✓
  │   │
  │   ├─ 2.1 Initialize validation ✓
  │   │   results = []
  │   │   blockingFailure = null
  │   │   isDegraded = false
  │   │
  │   ├─ 2.2 Run G1 Gate (Title Intent) ✓
  │   │   if (strlen(title) < 20) FAIL
  │   │
  │   ├─ 2.3 Run G2 Gate (Description) ✓
  │   │   if (strlen(desc) < 100) FAIL
  │   │
  │   ├─ 2.4 Run G3 Gate (URL) ✓
  │   │   if (!valid_url) FAIL
  │   │
  │   ├─ 2.5 Run G4 Gate (Answer Block) ✓
  │   │   if (strlen(answer) not in [250, 300]) FAIL
  │   │
  │   └─ 2.6 Run G5 Vector Validation ✓
  │       if (sku.primary_cluster_id):
  │           call PythonWorkerClient.validateVector()
  │           │
  │           ├─ HTTP POST python:5000/validate-vector
  │           │   {description, cluster_id, sku_id}
  │           │
  │           └─ Response:
  │               {
  │                 "valid": true/false,
  │                 "similarity": 0.85,
  │                 "reason": "..."
  │               }
  │
  ├─ 3. Determine validation status ✓
  │   if (blockingFailure):
  │       status = INVALID
  │   elif (isDegraded):
  │       status = DEGRADED
  │   else:
  │       status = VALID
  │
  ├─ 4. Persist ValidationLog ✓
  │   ValidationLog::create([
  │     'sku_id' => $sku->id,
  │     'validation_status' => $status,
  │     'results_json' => json_encode($results),
  │     'passed' => ($status == VALID)
  │   ])
  │
  └─ 5. Return response
      {
        "sku": {...},
        "validation": {
          "valid": true,
          "status": "VALID",
          "validation_log_id": 999,
          "results": [
            {gate: "G1", passed: true},
            {gate: "G2", passed: true},
            {gate: "G3", passed: true},
            {gate: "G4", passed: true},
            {
              gate: "G5_VECTOR",
              passed: true,
              similarity: 0.85,
              reason: "Similarity 0.85 >= threshold 0.72"
            }
          ],
          "next_action": "Ready for publication",
          "ai_validation_pending": false
        }
      }
        ↓
Response to Frontend
        ↓
Frontend renders:
  ✓ SKU created successfully
  ✓ All validation gates passed
  ✓ Enable "Publish" button
  ✓ Enable "Run Audit" button
```

---

## 📋 Data Flow: Run AI Audit

```
User clicks "Run Audit" (Frontend)
  ↓
Frontend state update: auditRunning = true
  ↓
POST /api/audit/123 {sku_id: 123}
  ↓
AuditController.runAudit(sku_id=123)
  │
  ├─ 1. Get SKU ✓
  │   sku = Sku::findOrFail(123)
  │
  ├─ 2. Queue audit job ✓
  │   result = pythonClient.queueAudit(123)
  │   │
  │   ├─ HTTP POST python:5000/queue/audit
  │   │   {sku_id: 123}
  │   │
  │   └─ Python Worker Response:
  │       {
  │         "queued": true,
  │         "audit_id": "550e8400-e29b-41d4-a716-446655440000"
  │       }
  │
  └─ 3. Return 202 Accepted ✓
      HTTP 202
      {
        "sku_id": 123,
        "status": "queued",
        "audit_id": "550e8400-...",
        "message": "Audit queued"
      }
        ↓
Response to Frontend
        ↓
Frontend:
  • Updates auditId state
  • Shows spinner "Audit in progress..."
  • Starts polling loop: GET /api/audit-result/{auditId}
        ↓
Loop (every 5 seconds):
  GET /api/audit-result/550e8400-...
        ↓
  AuditController.getResult(auditId)
        ↓
  pythonClient.getAuditResult(auditId)
        ↓
  GET python:5000/audits/550e8400-...
        ↓
  If audit still processing:
    Response: {status: "pending"} (HTTP 202)
        ↓
  If audit completed:
    Response: {
      status: "completed",
      engines: [
        {
          engine: "ChatGPT",
          status: "SUCCESS",
          citation_score: 62,
          results: [...]
        },
        {
          engine: "Claude",
          citation_score: 58,
          ...
        },
        {
          engine: "Perplexity",
          citation_score: 52,
          ...
        },
        {
          engine: "Gemini",
          citation_score: 48,
          ...
        }
      ],
      overall_citation: 55,
      decay_status: "DECLINING",
      brief_generated: true
    }
        ↓
  Frontend polling stops
  Shows results dashboard
  Displays alerts for decay
  Enables brief view
```

---

## 🛡️ Error Handling Flows

### Scenario: Python Worker Down

```
POST /api/skus → SkuController.store()
  ↓
ValidationService.validate()
  ↓
Call validateVector()
  ↓
PythonWorkerClient.validateVector()
  ↓
try {
  $response = $http->post('python:5000/validate-vector')
} catch (RequestException $e) {
  Log::error("Python validation failed: {$e->getMessage()}")
  
  return [
    'valid' => false,
    'blocking' => false,  ← KEY: Don't block
    'reason' => 'Service unavailable'
  ]
}
  ↓
ValidationService receives soft-fail result
  ↓
isDegraded = true
  ↓
status = DEGRADED
  ↓
ValidationLog created with DEGRADED status
  ↓
Return to frontend:
{
  "valid": false,
  "status": "DEGRADED",
  "next_action": "Service degradation - publication delayed",
  "ai_validation_pending": true
}
  ↓
Frontend:
  • ⚠️ Shows warning banner
  • ✓ Allows SKU to be saved
  • ✗ Blocks publication
  • 🔄 Shows "Retry scheduled"
  ↓
Backend schedules retry:
  • ValidationLog marked for retry
  • Job added to retry queue
  • Will validate when Python comes back up
```

### Scenario: OpenAI API Rate Limit

```
Python Worker /validate-vector
  ↓
vector = embedding.get_embedding(description)
  ↓
try {
  openai.Embedding.create(text=description, model="...")
} catch (RateLimitError) as e:
  Log::error("OpenAI rate limit: {$e}")
  return {
    'valid': False,
    'blocking': False,
    'similarity': 0.0,
    'reason': 'External API rate limit'
  }
}
  ↓
Response sent to PHP
  ↓
ValidationService marks DEGRADED
  ↓
Retry scheduled (backoff exponential)
  ↓
User can continue, system recovers automatically
```

---

## 🔐 Security & Authentication Flow

```
User logs in
  ↓
POST /api/auth/login {email, password}
  ↓
AuthController.login()
  ├─ Validate credentials against users table
  ├─ Generate JWT token
  └─ Return {token, user}
  ↓
Frontend stores token in localStorage
  ↓
Every subsequent request:
  ↓
axios interceptor adds:
  Authorization: "Bearer {token}"
  ↓
PHP middleware 'auth' validates:
  ├─ Is token provided?
  ├─ Is token valid?
  ├─ Is token expired?
  └─ Is user still active?
  ↓
Optional 'rbac:ROLE1,ROLE2' middleware:
  ├─ Extract user.role from token
  ├─ Check if role in allowed list
  └─ Deny if unauthorized
  ↓
If invalid/expired:
  ↓
  Response: HTTP 401 Unauthorized
  ↓
  Frontend catches in interceptor:
  localStorage.removeItem('cie_token')
  localStorage.removeItem('cie_user')
  navigate('/login')
  ↓
  User must login again
```

---

## 📊 Database Connection & Query Flow

```
PHP Service → PDO Connection Pool
  ↓
Eloquent (Laravel ORM)
  ↓
Model: Sku
  ↓
SELECT queries:
  ├─ Sku::all() → SELECT * FROM skus
  ├─ Sku::find(123) → SELECT * FROM skus WHERE id = 123
  └─ Sku::with(['relationships']) → JOIN queries
  ↓
INSERT queries:
  └─ Sku::create([...]) → BEGIN TRANSACTION, INSERT, COMMIT
  ↓
UPDATE queries:
  └─ $sku->update([...]) → UPDATE skus SET ... WHERE id = ?
  ↓
DELETE queries:
  └─ $sku->delete() → DELETE FROM skus WHERE id = ?
  ↓
Transactions (for critical operations):
  ├─ DB::transaction(function () {
  │   Sku::create(...)
  │   ValidationLog::create(...)
  │ })
  └─ If error: ROLLBACK

Python Worker:
  ├─ SQLAlchemy ORM (optional)
  ├─ OR direct SQL queries
  └─ Reads cluster_vectors table
      SELECT vector FROM cluster_vectors
      WHERE cluster_id = ? LIMIT 1
```

---

## 🚀 Deployment & Scaling Architecture

```
Local Development (Current)
  ├─ Frontend: localhost:8080
  ├─ PHP: localhost:9000
  ├─ Python: localhost:5000
  └─ PostgreSQL: localhost:5432

Docker Compose (Staging)
  ├─ Container: frontend
  ├─ Container: php-api
  ├─ Container: python-worker
  ├─ Container: mysql
  ├─ Container: redis
  └─ Network: CIE (inter-container DNS)

Kubernetes (Production Ready)
  ├─ Deployment: frontend-app
  │   ├─ Replicas: 2-3
  │   └─ Service: LoadBalancer
  │
  ├─ Deployment: php-api
  │   ├─ Replicas: 3-5
  │   ├─ HPA: CPU 70%
  │   └─ Service: ClusterIP
  │
  ├─ Deployment: python-worker
  │   ├─ Replicas: 2-3 (basic)
  │   ├─ Replicas: 5-10 (with workload queue)
  │   ├─ HPA: Queue depth metric
  │   └─ Service: ClusterIP
  │
  ├─ StatefulSet: PostgreSQL
  │   ├─ PVC: 100GB+
  │   └─ Backup: Daily
  │
  ├─ StatefulSet: Redis
  │   ├─ PVC: 10GB
  │   └─ Backup: Hourly
  │
  └─ Ingress: External routing
      ├─ /api/* → PHP service
      ├─ / → Frontend service
      └─ TLS cert
```

---

## 📈 Traffic & Load Distribution

```
                    [End Users]
                         ↓ HTTPS
         ┌───────────────┼───────────────┐
         ↓               ↓               ↓
    [Browser]      [Browser]       [Browser]
    
         ↓               ↓               ↓
         └───────────────┼───────────────┘
                    ↓ (Round Robin)
              [Load Balancer / Nginx]
              Port 80/443
              
         ┌────────────────┼────────────────┐
         ↓                ↓                ↓
    [Frontend]       [Frontend]       [Frontend]
    React SPA        React SPA        React SPA
    
         └────────────────┼────────────────┘
                    ↓ API Calls
                  [API Gateway / Nginx]
         
         ┌────────┬────────┬────────┐
         ↓        ↓        ↓        ↓
      [PHP]   [PHP]   [PHP]   [PHP]
      API #1  API #2  API #3  API #4
      
              (Shared PostgreSQL + Redis)
              
         ┌────────┬────────┬────────┐
         ↓        ↓        ↓        ↓
    [Python] [Python] [Python] [Python]
    Worker   Worker   Worker   Worker
    
    Concurrency:
    • 3-5 PHP instances (stateless)
    • 2-3 Python workers (processing queue)
    • 1 PostgreSQL primary, 1-2 replicas
    • 1 Redis instance (or cluster)
```

---

## 🎯 Summary: All Connections Wired

| Connection | Before | After | Status |
|-----------|--------|-------|--------|
| **Frontend → PHP** | Hardcoded localhost | Via VITE_API_URL env | ✅ |
| **PHP → Python** | ❌ None | HTTP via PythonWorkerClient | ✅ |
| **PHP → PostgreSQL** | ✅ Implicit | ✅ Explicit via PDO (pgsql) | ✅ |
| **Python → PostgreSQL** | ✅ Configured | ✅ psycopg2 (`db_connect.py`) | ✅ |
| **Both → Redis** | ❌ None (in-mem) | ✅ Redis service | ✅ |
| **External APIs** | ✅ Config only | ✅ Called from Python | ✅ |
| **Validation Gates** | ❌ Incomplete | ✅ Full G1-G5 pipeline | ✅ |
| **Audit Queueing** | ❌ Mock | ✅ Real queue | ✅ |
| **Error Handling** | ❌ None | ✅ Fail-soft everywhere | ✅ |
| **Logging** | ⚠️ Minimal | ✅ Comprehensive | ✅ |
| **Documentation** | ❌ Scattered | ✅ Complete API ref | ✅ |

---

**Status**: ✅ **ALL SYSTEMS CONNECTED AND DOCUMENTED**

**Last Updated**: February 16, 2026  
**Version**: CIE v2.3.2  
**Author**: System Integration  

For detailed workflows, see:  
- `WORKFLOW_ANALYSIS.md` - Problem identification  
- `WORKFLOW_WIRING_SUMMARY.md` - Complete solutions  
- `QUICK_START_GUIDE.md` - Developer setup  
- `API_REFERENCE_COMPLETE.md` - API documentation  
