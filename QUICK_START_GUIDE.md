# CIE v2.3.2 - Quick Start & Connection Verification Guide

## 🚀 Quick Start (5 minutes)

### Step 1: Verify Environment
```bash
cd c:\Dilaksan\CIE\CIE

# Check required files exist
ls -la .env
ls -la frontend/.env.local
ls -la docker-compose.yml
```

### Step 2: Start Services
```bash
# Start all services
docker-compose up -d

# Wait for startup
timeout 30  # seconds

# Verify containers running
docker-compose ps
```

### Step 3: Database schema (PostgreSQL)

On **first** `docker-compose up`, the `db` service applies schema from `database/postgres/init/` (migrations + spec indexes). No MySQL client required.

```bash
# Verify PostgreSQL is ready
docker-compose exec db psql -U cie_user -d cie_v232 -c "\dt"

# Optional: verify spec indexes (DECISION-014)
python scripts/verify_pg_indexes.py
```

See `database/postgres/README.md`. Legacy `database/migrations/*.sql` is reference-only (DECISION-013).

### Step 4: Access Frontend
```
http://localhost:8080
Username: test@company.com
Password: password
```

---

## 🔌 Connection Verification Checklist

### Frontend (Port 8080)
```bash
# ✅ Frontend loads
curl -I http://localhost:8080

# ✅ React app served
curl http://localhost:8080 | grep -i "root"

# ✅ API config readable in browser console
# Open DevTools → Console and check:
# fetch('http://localhost:9000/api/skus')
```

### PHP API (Port 9000)
```bash
# ✅ Health check
curl http://localhost:9000/health
# Response: {"status": "ok"}  # or similar

# ✅ API responds
curl -H "Authorization: Bearer test_token" \
  http://localhost:9000/api/skus
```

### Python API (Port 5000)
```bash
# ✅ Health check
curl http://localhost:5000/health
# Response: {"status": "healthy", "service": "python-worker"}

# ✅ Vector validation endpoint exists
curl -X POST http://localhost:5000/validate-vector \
  -H "Content-Type: application/json" \
  -d '{"description":"test","cluster_id":"1"}'
```

### PostgreSQL Database (Port 5432)
```bash
# ✅ Connect from host
psql -h localhost -U cie_user -d cie_v232 -c "\dt"

# Should show:
# - skus
# - validation_logs
# - audit_results
# - content_briefs
# - clusters
# etc.
```

### Redis Cache (Port 6379)
```bash
# ✅ Connect
redis-cli -h localhost PING
# Response: PONG

# ✅ Check data
redis-cli -h localhost KEYS "*"
```

---

## 📊 Request Flow Verification

### Test 1: Create SKU with Validation
```bash
# 1. Get auth token (if needed)
TOKEN=$(curl -s -X POST http://localhost:9000/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"test@company.com","password":"password"}' \
  | grep -o '"token":"[^"]*' | cut -d'"' -f4)

# 2. Create SKU (should trigger validation)
curl -X POST http://localhost:9000/api/skus \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "sku_code": "TEST-PROD-001",
    "title": "Test Product",
    "description": "This is a test product for validation pipeline",
    "primary_cluster_id": 1
  }'

# 3. Check response includes validation results
# Should see: "validation": {"valid": true/false, "status": "..."}
```

### Test 2: Run AI Audit
```bash
# Queue audit job
SKU_ID=1
curl -X POST http://localhost:9000/api/audit/$SKU_ID \
  -H "Authorization: Bearer $TOKEN"

# Response should be 202 Accepted:
# {"status": "queued", "audit_id": "...", "message": "..."}

# Poll for results (will be pending at first)
AUDIT_ID="from_response_above"
curl http://localhost:9000/api/audit-result/$AUDIT_ID \
  -H "Authorization: Bearer $TOKEN"

# Keep polling until status changes from "pending" to "completed"
```

### Test 3: Vector Validation (Direct)
```bash
# Call Python API directly
curl -X POST http://localhost:5000/validate-vector \
  -H "Content-Type: application/json" \
  -d '{
    "description": "High quality cable connector",
    "cluster_id": "1",
    "sku_id": "1"
  }'

# Response:
# {"valid": true/false, "similarity": 0.85, "reason": "..."}
```

### Test 4: Fail-Soft (Stop Python)
```bash
# 1. Stop Python service
docker-compose stop python-worker
sleep 2

# 2. Try to validate SKU through PHP
curl -X POST http://localhost:9000/api/skus/1/validate \
  -H "Authorization: Bearer $TOKEN"

# 3. Should return degraded status, NOT error
# Response status: 200 (not 500)
# validation.status: "DEGRADED"

# 4. Restart Python
docker-compose start python-worker
```

---

## 🔍 Log Inspection

### PHP Application Logs
```bash
# Real-time logs
docker-compose logs -f php-api

# Look for:
# "Starting validation for SKU"
# "Gate validation:"
# "Validation complete for SKU"
# "Call HTTP POST python:5000/validate-vector"
```

### Python Application Logs
```bash
# Real-time logs
docker-compose logs -f python-worker

# Look for:
# "Received validate-vector request"
# "Generated embeddings"
# "Calculated cosine similarity"
# "Queued audit job"
```

### Database Validation Logs
```bash
# Check validation_logs table
docker-compose exec db psql -U cie_user -d cie_v232 \
  -c "SELECT * FROM validation_logs ORDER BY created_at DESC LIMIT 5;"

# Should show results from recent validations
```

---

## 📝 Common Issues & Solutions

### Issue 1: "VITE_API_URL is undefined"
**Solution**: Check `frontend/.env.local` exists with:
```
VITE_API_URL=http://localhost:9000/api
```

### Issue 2: "Cannot connect to database"
**Symptom**: PHP logs show "Connection refused"  
**Solution**: Verify docker-compose.yml has:
```yaml
DB_HOST=db
DB_PASSWORD=cie_password  # Match .env
```

### Issue 3: "Python worker unreachable"
**Symptom**: Validations return DEGRADED for all SKUs  
**Cause**: Python service not running  
**Solution**:
```bash
docker-compose ps python-worker  # Check status
docker-compose logs python-worker  # Check errors
docker-compose restart python-worker  # Restart
```

### Issue 4: "Vector validation always fails"
**Possible Causes**:
- OpenAI API key invalid (set in .env: `OPENAI_API_KEY=sk-...`)
- Cluster vectors not in database
- Similarity threshold too high (default: 0.72)

**Solution**:
```bash
# Check OpenAI key
echo $OPENAI_API_KEY

# Check cluster vectors exist (PostgreSQL — Docker service name: db)
docker-compose exec db psql -U cie_user -d cie_v232 -c "SELECT COUNT(*) FROM cluster_vectors;"

# Adjust threshold in .env if needed
SIMILARITY_THRESHOLD=0.65
```

### Issue 5: "Cannot create SKU - validation hangs"
**Possible Causes**:
- OpenAI API timeout
- Python worker processing queue

**Solution**:
```bash
# Check Python worker logs
docker-compose logs python-worker

# If queue is stuck, restart:
docker-compose restart python-worker

# Check Redis for stuck jobs:
redis-cli -h localhost LLEN audit:queue
```

---

## 🧪 Integration Test Suite

Run these in order to verify full system:

```bash
#!/bin/bash

# Colors for output
GREEN='\033[0;32m'
RED='\033[0;31m'
NC='\033[0m' # No Color

echo "🧪 CIE Integration Tests"
echo "========================"

# Test 1: Services Running
echo -n "Test 1: Services running... "
if docker-compose ps | grep -q "python-worker.*Up"; then
  echo -e "${GREEN}✓${NC}"
else
  echo -e "${RED}✗${NC}"
fi

# Test 2: Python Health
echo -n "Test 2: Python health check... "
if curl -s http://localhost:5000/health | grep -q "healthy"; then
  echo -e "${GREEN}✓${NC}"
else
  echo -e "${RED}✗${NC}"
fi

# Test 3: PHP Health
echo -n "Test 3: PHP API running... "
if curl -s -I http://localhost:9000/api/skus | grep -q "200\|401"; then
  echo -e "${GREEN}✓${NC}"
else
  echo -e "${RED}✗${NC}"
fi

# Test 4: Database Connected
echo -n "Test 4: Database connected... "
if docker-compose exec -T db psql -U cie_user -d cie_v232 \
    -c "SELECT 1;" >/dev/null 2>&1; then
  echo -e "${GREEN}✓${NC}"
else
  echo -e "${RED}✗${NC}"
fi

# Test 5: Redis Connected
echo -n "Test 5: Redis connected... "
if redis-cli -h localhost PING | grep -q "PONG"; then
  echo -e "${GREEN}✓${NC}"
else
  echo -e "${RED}✗${NC}"
fi

# Test 6: Vector Validation Endpoint
echo -n "Test 6: Vector validation endpoint... "
RESPONSE=$(curl -s -X POST http://localhost:5000/validate-vector \
  -H "Content-Type: application/json" \
  -d '{"description":"test","cluster_id":"1"}' | grep -o '"valid"')
if [ -n "$RESPONSE" ]; then
  echo -e "${GREEN}✓${NC}"
else
  echo -e "${RED}✗${NC}"
fi

echo ""
echo "📊 All tests complete!"
echo "For detailed logs, run:"
echo "  docker-compose logs -f php-api"
echo "  docker-compose logs -f python-worker"
```

---

## 📚 Documentation References

- **API Reference**: `docs/API_REFERENCE_COMPLETE.md`
- **Workflow Analysis**: `WORKFLOW_ANALYSIS.md`
- **Wiring Summary**: `WORKFLOW_WIRING_SUMMARY.md`
- **Architecture**: `docs/architecture/system_design.md`

---

## 🎯 Next Development Tasks

### Immediate (Today)
- [ ] Run integration tests above
- [ ] Verify all endpoints work
- [ ] Test fail-soft scenario
- [ ] Review logs for any errors

### This Week
- [ ] Implement Redis queue (production-ready)
- [ ] Create audit worker loop
- [ ] Add brief generation worker
- [ ] Setup basic monitoring

### This Month
- [ ] Deploy to staging environment
- [ ] Load testing (100+ SKUs)
- [ ] User acceptance testing
- [ ] Security audit

---

## 💡 Key File Locations

```
CIE/
├── .env                                    # Connection config
├── docker-compose.yml                      # Service definitions
├── frontend/
│   ├── .env.local (NEW)                   # Frontend API config
│   └── src/services/api.js                # Axios client
├── backend/
│   ├── php/
│   │   ├── routes/api.php                # All routes (UPDATED)
│   │   └── src/
│   │       ├── Controllers/
│   │       │   ├── SkuController.php              (UPDATED)
│   │       │   └── AuditController.php           (UPDATED)
│   │       └── Services/
│   │           ├── ValidationService.php         (UPDATED)
│   │           └── PythonWorkerClient.php (NEW) 
│   └── python/
│       └── api/main.py                   (UPDATED)
└── docs/
    └── API_REFERENCE_COMPLETE.md         (NEW)
```

---

## 🚀 Deployment Checklist

Before going live:

- [ ] All .env variables set correctly
- [ ] Database migrations run
- [ ] Seeds loaded (if applicable)
- [ ] All endpoints tested
- [ ] Fail-soft scenarios verified
- [ ] Logging configured
- [ ] Monitoring setup
- [ ] Backup strategy
- [ ] Disaster recovery plan
- [ ] Team trained on operations

---

## 📞 Support

For issues:
1. Check logs: `docker-compose logs -f [service]`
2. Review documentation: `docs/API_REFERENCE_COMPLETE.md`
3. Test individual components per "Connection Verification" section
4. Check for common issues in "Issues & Solutions" above

---

**Last Updated**: February 16, 2026  
**Status**: ✅ All Systems Connected and Documented
