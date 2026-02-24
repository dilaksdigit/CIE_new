# CIE v2.3.1 — Complete API Inventory

**Base URLs:**
- **PHP (CMS):** `http://localhost:8080/api` (or `/api` via Vite proxy)
- **Python (FastAPI):** `http://localhost:8000` (main port)

---

## 📋 PHP Backend APIs (Port 8080)

### Authentication (No auth required)
| Method | Endpoint | Purpose | Used By |
|--------|----------|---------|---------|
| POST | `/api/auth/login` | User login | Frontend: `authApi.login()` |
| POST | `/api/auth/register` | User registration | Frontend: `authApi.register()` |

### SKU Management (Auth required)
| Method | Endpoint | Purpose | Used By | RBAC |
|--------|----------|---------|---------|------|
| GET | `/api/skus` | List SKUs (with filters) | Frontend: `skuApi.list()` | All authenticated |
| GET | `/api/skus/stats` | SKU statistics | Frontend: `skuApi.stats()` | All authenticated |
| GET | `/api/skus/{id}` | Get single SKU | Frontend: `skuApi.get()` | All authenticated |
| POST | `/api/skus` | Create SKU | Frontend: `skuApi.create()` | CONTENT_EDITOR, ADMIN |
| PUT | `/api/skus/{id}` | Update SKU | Frontend: `skuApi.update()` | CONTENT_EDITOR, ADMIN |
| POST | `/api/skus/{id}/validate` | Validate SKU (G1–G7) | Frontend: `skuApi.validate()` | All authenticated |
| GET | `/api/skus/{id}/readiness` | Per-channel readiness scores | Frontend: (not yet) | All authenticated |

### Clusters (Auth required)
| Method | Endpoint | Purpose | Used By | RBAC |
|--------|----------|---------|---------|------|
| GET | `/api/clusters` | List clusters | Frontend: `clusterApi.list()` | All authenticated |
| GET | `/api/clusters/{id}` | Get cluster | Frontend: (not yet) | All authenticated |
| POST | `/api/clusters` | Create cluster | Frontend: `clusterApi.create()` | SEO_GOVERNOR, ADMIN |
| PUT | `/api/clusters/{id}` | Update cluster | Frontend: `clusterApi.update()` | SEO_GOVERNOR, ADMIN |

### Taxonomy (Auth required)
| Method | Endpoint | Purpose | Used By | RBAC |
|--------|----------|---------|---------|------|
| GET | `/api/taxonomy/intents` | Get 9-intent taxonomy (optional `?tier=X`) | Frontend: `taxonomyApi.getIntents()` | All authenticated |

### Tiers (Auth required)
| Method | Endpoint | Purpose | Used By | RBAC |
|--------|----------|---------|---------|------|
| POST | `/api/tiers/recalculate` | Recalculate tiers from ERP data | Frontend: `tierApi.recalculate()` | FINANCE, ADMIN |

### Audit (Auth required)
| Method | Endpoint | Purpose | Used By | RBAC |
|--------|----------|---------|---------|------|
| POST | `/api/audit/{sku_id}` | Run audit for single SKU | Frontend: `auditApi.run()` | AI_OPS, ADMIN |
| POST | `/api/audit/run` | Run audit by category (20 questions) | Frontend: (not yet) | AI_OPS, ADMIN |
| GET | `/api/audit/{sku_id}/history` | Audit history for SKU | Frontend: (not yet) | All authenticated |
| GET | `/api/audit-result/{auditId}` | Get audit result | Frontend: (not yet) | All authenticated |
| GET | `/api/audit/results/{category}` | Latest audit results by category | Frontend: (not yet) | All authenticated |

### Briefs (Auth required)
| Method | Endpoint | Purpose | Used By | RBAC |
|--------|----------|---------|---------|------|
| GET | `/api/briefs` | List briefs | Frontend: `briefApi.list()` | All authenticated |
| POST | `/api/briefs` | Create brief | Frontend: `briefApi.create()` | CONTENT_EDITOR, ADMIN |
| POST | `/api/brief/generate` | Auto-generate brief (Week 3 decay) | Frontend: (not yet) | All authenticated |
| GET | `/api/briefs/{id}` | Get brief | Frontend: (not yet) | All authenticated |

### ERP Integration (Auth required)
| Method | Endpoint | Purpose | Used By | RBAC |
|--------|----------|---------|---------|------|
| POST | `/api/erp/sync` | Receive ERP data push; recompute tiers | External ERP system | FINANCE, ADMIN |

### Config (Auth required)
| Method | Endpoint | Purpose | Used By | RBAC |
|--------|----------|---------|---------|------|
| GET | `/api/config` | Get config | Frontend: `configApi.get()` | All authenticated |
| PUT | `/api/config` | Update config | Frontend: `configApi.update()` | All authenticated |

### Audit Results (Auth required)
| Method | Endpoint | Purpose | Used By | RBAC |
|--------|----------|---------|---------|------|
| GET | `/api/skus/{skuId}/audit-results` | Get audit results for SKU | Frontend: `auditResultApi.getBySkuId()` | All authenticated |
| GET | `/api/audit-results/decay-alerts` | Get decay alerts | Frontend: `auditResultApi.getDecayAlerts()` | All authenticated |
| GET | `/api/audit-results/weekly-scores` | Get weekly scores | Frontend: `auditResultApi.getWeeklyScores()` | All authenticated |

### Unified API v1 (Auth required)
All routes above are also available under `/api/v1/` prefix for spec compliance:
- `/api/v1/skus`, `/api/v1/skus/stats`, `/api/v1/skus/{id}`, `/api/v1/skus/{id}/readiness`
- `/api/v1/clusters`, `/api/v1/clusters/{id}`
- `/api/v1/taxonomy/intents`
- `/api/v1/tiers/recalculate`
- `/api/v1/audit/run`, `/api/v1/audit/results/{category}`
- `/api/v1/brief/generate`
- `/api/v1/erp/sync`

---

## 🐍 Python Backend APIs (Port 8000 — FastAPI)

### Service Info
| Method | Endpoint | Purpose | Used By |
|--------|----------|---------|---------|
| GET | `/` | Service info + endpoint list | Health checks |
| GET | `/health` | Health check | Health monitors |
| GET | `/docs` | OpenAPI/Swagger docs | Developers |

### Semantic / Vector Operations
| Method | Endpoint | Purpose | Used By | Fail-Soft |
|--------|----------|---------|---------|-----------|
| POST | `/api/v1/sku/embed` | Generate embedding (OpenAI text-embedding-3-small, 1536 dims) | PHP → Python | ✅ Yes (degraded response) |
| POST | `/api/v1/sku/similarity` | Cosine similarity vs cluster centroid (Redis cache) | PHP → Python | ✅ Yes (status: pending) |
| POST | `/validate-vector` | Legacy vector validation | PHP → Python | ❌ No (500 on error) |

### Title Validation & Suggestion
| Method | Endpoint | Purpose | Used By |
|--------|----------|---------|---------|
| POST | `/api/v1/title/validate` | Validate title (pipe, intent keyword, no brand, 120 chars) | PHP → Python |
| POST | `/api/v1/title/suggest` | Generate compliant title (intent → attributes) | PHP → Python |

### Pre-Publish Validation (Gates)
| Method | Endpoint | Purpose | Used By |
|--------|----------|---------|---------|
| POST | `/api/v1/sku/validate` | G1–G7 + G6.1 validation (8 gates) | PHP → Python |

### Queue / Jobs
| Method | Endpoint | Purpose | Used By |
|--------|----------|---------|---------|
| POST | `/queue/audit` | Queue AI audit job | PHP → Python |
| POST | `/queue/brief-generation` | Queue brief generation | PHP → Python |
| GET | `/audits/{audit_id}` | Poll audit result | PHP → Python |
| GET | `/briefs/{brief_id}` | Poll brief result | PHP → Python |

---

## 🎨 Frontend API Calls (via `src/services/api.js`)

All frontend API calls use the `api` axios instance with baseURL from `VITE_API_URL` (defaults to `/api`), which proxies to PHP backend on port 8080.

### Auth API (`authApi`)
- `login(email, password)` → `POST /api/auth/login`
- `register(name, email, password, password_confirmation, role)` → `POST /api/auth/register`

### SKU API (`skuApi`)
- `list(params)` → `GET /api/skus` (with query params: search, tier, category)
- `get(id)` → `GET /api/skus/{id}`
- `create(data)` → `POST /api/skus`
- `update(id, data)` → `PUT /api/skus/{id}`
- `validate(id)` → `POST /api/skus/{id}/validate`
- `stats()` → `GET /api/skus/stats`

### Cluster API (`clusterApi`)
- `list(params)` → `GET /api/clusters`
- `create(data)` → `POST /api/clusters`
- `update(id, data)` → `PUT /api/clusters/{id}`

### Tier API (`tierApi`)
- `recalculate()` → `POST /api/tiers/recalculate`

### Audit API (`auditApi`)
- `run(skuId)` → `POST /api/audit/{skuId}`

### Brief API (`briefApi`)
- `list(params)` → `GET /api/briefs`
- `create(data)` → `POST /api/briefs`

### Taxonomy API (`taxonomyApi`)
- `getIntents(tier)` → `GET /api/taxonomy/intents?tier={tier}`

### Config API (`configApi`)
- `get()` → `GET /api/config`
- `update(data)` → `PUT /api/config`

### Audit Result API (`auditResultApi`)
- `getBySkuId(skuId)` → `GET /api/skus/{skuId}/audit-results`
- `getDecayAlerts()` → `GET /api/audit-results/decay-alerts`
- `getWeeklyScores()` → `GET /api/audit-results/weekly-scores`

---

## 📊 Summary Statistics

- **PHP Backend:** 30+ endpoints (auth, SKUs, clusters, taxonomy, tiers, audit, briefs, ERP, config)
- **Python Backend:** 12 endpoints (embed, similarity, validate, title, queue, health)
- **Frontend API Calls:** 20+ functions across 8 API modules
- **Total Unique Endpoints:** ~42 (including v1 variants)

---

## 🔗 Cross-Service Calls

### PHP → Python
- `POST /api/v1/sku/embed` (via `PYTHON_API_URL`)
- `POST /api/v1/sku/similarity` (via `PYTHON_API_URL`)
- `POST /api/v1/sku/validate` (via `PYTHON_API_URL`)
- `POST /api/v1/title/validate` (via `PYTHON_API_URL`)
- `POST /api/v1/title/suggest` (via `PYTHON_API_URL`)
- `POST /queue/audit` (via `PYTHON_API_URL`)
- `POST /queue/brief-generation` (via `PYTHON_API_URL`)

### Frontend → PHP
- All calls via `/api/*` (proxied by Vite to `http://localhost:8080`)

### Frontend → Python (Direct)
- None currently — all Python calls go through PHP backend

---

## 📝 Notes

1. **Authentication:** All PHP endpoints except `/auth/login` and `/auth/register` require Bearer token (set via `Authorization` header from `localStorage.getItem('cie_token')`).
2. **RBAC:** Many endpoints have role-based access control (see RBAC column above).
3. **Unified API v1:** All core endpoints are also available under `/api/v1/` prefix for spec compliance.
4. **Fail-Soft:** Python embed/similarity endpoints return degraded responses (not 500) when OpenAI API is unavailable (v2.3.2).
5. **Ports:** PHP = 8080, Python = 8000 (main port).
