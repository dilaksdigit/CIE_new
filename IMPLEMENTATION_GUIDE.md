# CIE v2.3.2 Implementation Guide

**Complete step-by-step guide to set up, develop, and deploy the Catalog Intelligence Engine v2.3.2 system.**

---

## 📋 Table of Contents

1. [Overview](#overview)
2. [Prerequisites](#prerequisites)
3. [Project Structure](#project-structure)
4. [Phase 1: Environment Setup](#phase-1-environment-setup)
5. [Phase 2: Backend (PHP) Setup](#phase-2-backend-php-setup)
6. [Phase 3: Backend (Python) Setup](#phase-3-backend-python-setup)
7. [Phase 4: Frontend Setup](#phase-4-frontend-setup)
8. [Phase 5: Database Initialization](#phase-5-database-initialization)
9. [Phase 6: Docker Orchestration](#phase-6-docker-orchestration)
10. [Phase 7: Local Development](#phase-7-local-development)
11. [Testing Procedures](#testing-procedures)
12. [Troubleshooting](#troubleshooting)
13. [Production Deployment](#production-deployment)

---

## Overview

CIE v2.3.2 is built as a distributed system with three main components:

- **PHP Backend** (Port 9000): Core business logic, validation gates, RBAC, database orchestration
- **Python Backend** (Port 5000): AI engines, vector validation, ERP sync jobs, brief generation
- **React Frontend** (Port 3000/8080): Modern SPA for SKU management, audits, reporting
- **MySQL Database** (Port 3306): Primary data store
- **Redis** (Port 6379): Queue management, caching, sessions

---

## Prerequisites

Ensure you have the following installed on your system:

### Required Software
- **Git** (2.30+)
- **Docker & Docker Compose** (Docker 20.10+, Compose 1.29+)
- **Node.js** (16.x or later) & npm/yarn
- **PHP** (8.1+) & Composer (local development only)
- **Python** (3.9+) & pip & virtualenv
- **MySQL Client** (8.0+) — for manual queries
- **Redis CLI** (optional) — for queue inspection

### System Requirements
- **RAM**: 4GB minimum (8GB recommended)
- **Disk Space**: 5GB for Docker images + dependencies
- **OS**: Linux, macOS, or Windows (via WSL or Docker Desktop)

### API Keys (For Full Functionality)
- OpenAI API key (GPT-4 access)
- Anthropic API key (Claude access)
- Google Vertex AI credentials (Gemini access)
- (Store in `.env` file)

---

## Project Structure

```
cie-v232/
├── backend/
│   ├── php/                      # Core governance logic
│   │   ├── src/
│   │   │   ├── Controllers/      # API endpoints (SKU, Audit, Brief, etc.)
│   │   │   ├── Models/           # Database models
│   │   │   ├── Services/         # Business logic (validation, tier calc)
│   │   │   ├── Validators/
│   │   │   │   └── Gates/        # G1-G7 validation gates
│   │   │   ├── Middleware/       # Auth, RBAC, logging
│   │   │   ├── Enums/            # GateType, TierType, etc.
│   │   │   └── Utils/            # Helpers, loggers
│   │   ├── public/               # Entry point
│   │   ├── routes/               # API route definitions
│   │   ├── composer.json
│   │   └── .env.example
│   │
│   └── python/                   # AI and job processing
│       ├── src/
│       │   ├── api/              # Flask API (main.py)
│       │   ├── ai_audit/         # LLM audit engines
│       │   ├── brief_generator/  # Content brief generation
│       │   ├── erp_sync/         # ERP connectors
│       │   ├── vector/           # Vector validation
│       │   ├── jobs/             # Background jobs
│       │   └── utils/            # Shared utilities
│       ├── requirements.txt
│       ├── venv/                 # Virtual environment (git-ignored)
│       └── .env.example
│
├── frontend/                     # React SPA
│   ├── src/
│   │   ├── components/           # React components
│   │   ├── pages/                # Page components
│   │   ├── services/             # API client
│   │   ├── store/                # Zustand state
│   │   ├── hooks/                # Custom hooks
│   │   ├── App.jsx
│   │   └── main.jsx
│   ├── package.json
│   ├── vite.config.js
│   └── .env.local.example
│
├── database/
│   ├── migrations/               # SQL migration files
│   ├── seeds/                    # Seed data (JSON/SQL)
│   └── schema/                   # Schema documentation
│
├── docker-compose.yml            # Multi-service orchestration
├── Makefile                      # Convenience commands
├── .env.example                  # Environment variables template
└── README.md                     # Quick reference
```

---

## Phase 1: Environment Setup

### Step 1.1: Clone and Navigate

```bash
git clone <repository-url> cie-v232
cd cie-v232
```

### Step 1.2: Create Environment Files

```bash
# Copy environment template for PHP
cp .env.example .env

# Copy template for frontend
cp frontend/.env.local.example frontend/.env.local

# Copy template for Python
cp backend/python/.env.example backend/python/.env
```

### Step 1.3: Configure Environment Variables

**Edit `.env`:**
```bash
APP_ENV=local
APP_DEBUG=true
LOG_LEVEL=debug

# Database
DB_CONNECTION=mysql
DB_HOST=db                    # Use service name (docker-compose)
DB_PORT=3306
DB_DATABASE=cie_v232
DB_USERNAME=cie_user
DB_PASSWORD=cie_password

# Redis
REDIS_HOST=redis
REDIS_PORT=6379

# Python Worker
PYTHON_API_URL=http://python-worker:5000

# API Keys (add after getting credentials)
OPENAI_API_KEY=sk-...
ANTHROPIC_API_KEY=sk-ant-...
GOOGLE_APPLICATION_CREDENTIALS=/path/to/credentials.json
```

**Edit `frontend/.env.local`:**
```bash
VITE_API_URL=http://localhost:9000/api
VITE_PYTHON_API_URL=http://localhost:5000
```

**Edit `backend/python/.env`:**
```bash
FLASK_ENV=development
FLASK_DEBUG=True

DB_HOST=db
DB_PORT=3306
DB_DATABASE=cie_v232
DB_USER=cie_user
DB_PASSWORD=cie_password

REDIS_HOST=redis
REDIS_PORT=6379

OPENAI_API_KEY=sk-...
ANTHROPIC_API_KEY=sk-ant-...
```

---

## Phase 2: Backend (PHP) Setup

### Step 2.1: Install PHP Dependencies

```bash
cd backend/php
composer install
composer dump-autoload
```

### Step 2.2: Verify Directory Structure

```bash
mkdir -p src/{Controllers,Models,Services,Middleware,Validators/Gates,Enums,Utils,Database}
mkdir -p public storage/logs storage/uploads
```

### Step 2.3: Generate Application Key (if applicable)

```bash
# For Laravel-based setup (if using Laravel's bootstrap)
php artisan key:generate
```

### Step 2.4: Check PHP Configuration

Ensure `src/` is in the Composer autoload path:

```json
{
  "autoload": {
    "psr-4": { "App\\": "src/" }
  }
}
```

---

## Phase 3: Backend (Python) Setup

### Step 3.1: Create Virtual Environment

```bash
cd backend/python
python3 -m venv venv

# Activate
# On Linux/macOS:
source venv/bin/activate

# On Windows (PowerShell):
venv\Scripts\Activate.ps1
```

### Step 3.2: Install Python Dependencies

```bash
pip install --upgrade pip
pip install -r requirements.txt
```

**Core Dependencies (`requirements.txt`):**
```
Flask==2.3.0
Flask-CORS==4.0.0
Flask-Limiter==3.3.0
openai==0.27.0+
anthropic==0.3.0+
google-generativeai==0.3.0
numpy==1.24.0+
pandas==2.0.0+
redis==5.0.0
requests==2.31.0
pymysql==1.1.0
python-dotenv==1.0.0
pydantic==1.10.0
```

### Step 3.3: Verify Python Environment

```bash
python --version        # Should be 3.9+
pip list | grep Flask   # Should show Flask 2.3.0+
```

---

## Phase 4: Frontend Setup

### Step 4.1: Initialize Vite + React

```bash
cd frontend
npm install
```

### Step 4.2: Install Feature Dependencies

```bash
npm install axios zustand react-router-dom react-hook-form react-dropzone recharts date-fns clsx
```

### Step 4.3: Verify Setup

```bash
npm run build
npm run dev          # Should start on http://localhost:5173
```

---

## Phase 5: Database Initialization

### Step 5.1: Start MySQL Container

```bash
docker-compose up -d db
```

Wait for MySQL to initialize (check logs):
```bash
docker-compose logs db | grep "ready for connections"
```

### Step 5.2: Run Migrations

Option A: Using Make (if Makefile available):
```bash
make migrate
```

Option B: Manual execution:
```bash
docker-compose exec db mysql -u cie_user -p cie_v232 < database/migrations/001_create_users_table.sql
docker-compose exec db mysql -u cie_user -p cie_v232 < database/migrations/002_create_roles_table.sql
# Continue for all migration files sequentially
```

### Step 5.3: Seed Development Data

```bash
make seed
# Or manually:
docker-compose exec db mysql -u cie_user -p cie_v232 < database/seeds/005_seed_golden_test_data.sql
```

### Step 5.4: Verify Database Connection

```bash
docker-compose exec db mysql -u cie_user -p -e "SELECT COUNT(*) FROM users;"
```

---

## Phase 6: Docker Orchestration

### Step 6.1: Build All Services

```bash
docker-compose build --no-cache
```

### Step 6.2: Start All Services

```bash
# Start in background
docker-compose up -d

# Or with logs streaming:
docker-compose up
```

### Step 6.3: Verify All Services Are Running

```bash
docker-compose ps

# Expected output:
# NAME                   STATUS              PORTS
# cie-db                 Up 2 minutes        3306/tcp
# cie-redis              Up 2 minutes        6379/tcp
# cie-php-api            Up 1 minute         9000/tcp
# cie-python-worker      Up 1 minute         5000/tcp
# cie-frontend           Up 1 minute         3000/tcp
```

### Step 6.4: Check Service Logs

```bash
docker-compose logs php-api       # PHP logs
docker-compose logs python-worker # Python logs
docker-compose logs frontend      # Frontend logs
```

---

## Phase 7: Local Development

### Step 7.1: Access Services

| Service | URL | Purpose |
|---------|-----|---------|
| Frontend | http://localhost:3000 | React SPA |
| PHP API | http://localhost:9000/api/health | Core API |
| Python API | http://localhost:5000/health | AI/Jobs |
| MySQL | localhost:3306 | Database |
| Redis | localhost:6379 | Queue/Cache |

### Step 7.2: Enable Hot Reload (Frontend)

Frontend automatically reloads on code changes when using Vite dev server.

### Step 7.3: PHP Development

For PHP backend changes:
```bash
# Restart PHP container after code changes
docker-compose restart php-api
```

### Step 7.4: Python Development

For Python changes:
```bash
# Activate Python venv locally (optional)
source backend/python/venv/bin/activate
# Edit code, Flask auto-reloads in dev mode
```

---

## Testing Procedures

### Health Checks

```bash
# PHP API
curl http://localhost:9000/api/health

# Python Worker
curl http://localhost:5000/health

# Frontend (should load HTML)
curl http://localhost:3000
```

### Create Test SKU

```bash
curl -X POST http://localhost:9000/api/skus \
  -H "Content-Type: application/json" \
  -d '{
    "sku_code": "TEST-001",
    "cluster_id": "cable-usb-c",
    "tier": "SUPPORT"
  }'
```

### Run Validation Gates

```bash
curl -X POST http://localhost:9000/api/validate \
  -H "Content-Type: application/json" \
  -d '{"sku_id": "uuid-here"}'
```

### Queue AI Audit

```bash
curl -X POST http://localhost:9000/api/audits/queue \
  -H "Content-Type: application/json" \
  -d '{"sku_id": "uuid-here"}'
```

### Poll Audit Result

```bash
curl http://localhost:9000/api/audits/{audit_id}/result
```

---

## Troubleshooting

### Database Connection Failed

**Symptom**: `Error: ECONNREFUSED` to db service

**Solution**:
```bash
# Check if MySQL container is running
docker-compose logs db

# Verify credentials in .env match docker-compose.yml
# Restart database
docker-compose restart db
```

### Python Worker Cannot Import Modules

**Symptom**: `ModuleNotFoundError: No module named 'flask'`

**Solution**:
```bash
# Verify requirements installed
pip install -r backend/python/requirements.txt
# Or reinstall in container
docker-compose rebuild python-worker
```

### Frontend Cannot Reach API

**Symptom**: CORS errors in browser console

**Solution**:
1. Verify `frontend/.env.local` has correct API URL
2. Check PHP `.env` PYTHON_API_URL is reachable
3. Ensure CORS middleware is enabled in PHP

### Redis Connection Timeout

**Symptom**: Queue operations fail

**Solution**:
```bash
docker-compose restart redis
docker-compose logs redis
```

### Database Migrations Failed

**Symptom**: `Table already exists` or `Column not found`

**Solution**:
```bash
# Check current schema
docker-compose exec db mysql -u cie_user -p cie_v232 -e "SHOW TABLES;"

# If corrupted, backup and reset
docker-compose exec db mysql -u cie_user -p cie_v232 < database/schema/clean_slate.sql
docker-compose exec db mysql -u cie_user -p cie_v232 < database/migrations/001_*.sql
```

---

## Production Deployment

### Pre-Deployment Checklist

- [ ] All environment variables set in production `.env`
- [ ] API keys configured (OpenAI, Anthropic, Google)
- [ ] Database credentials secured (not in git)
- [ ] Redis persistence enabled
- [ ] Logging configured (ELK or equivalent)
- [ ] Backups scheduled
- [ ] Health checks configured
- [ ] Load balancer in place (if scaling)

### Step 1: Build Production Images

```bash
docker-compose -f docker-compose.prod.yml build --no-cache
```

### Step 2: Set Production Environment

```bash
# .env (production)
APP_ENV=production
APP_DEBUG=false
LOG_LEVEL=warning
DB_HOST=prod-rds-endpoint.c9akciq32.us-east-1.rds.amazonaws.com
PYTHON_API_URL=https://ai.yourdomain.com
```

### Step 3: Deploy to Kubernetes (Optional)

```bash
kubectl apply -f infrastructure/kubernetes/
kubectl rollout status deployment/php-api
kubectl rollout status deployment/python-worker
```

### Step 4: Run Database Migrations

```bash
# Connect to prod pod
kubectl exec -it pod/cie-php-api -- php artisan migrate --force
```

### Step 5: Verify Deployment

```bash
curl https://api.yourdomain.com/health
curl https://yourdomain.com  # Frontend
```

---

## Useful Makefile Commands

```bash
# Install all dependencies
make install-all

# Run migrations
make migrate

# Seed development data
make seed

# Start all services
make up

# Stop all services
make down

# View logs
make logs

# Reset database (wiping data)
make db-reset
```

---

## Further Reading

- **MASTER_SUMMARY.md** — Overview of v2.3.2 architecture
- **WORKFLOW_WIRING_SUMMARY.md** — How components connect
- **API_REFERENCE_COMPLETE.md** — Full API documentation
- **QUICK_START_GUIDE.md** — Quick testing procedures
- **SYSTEM_ARCHITECTURE_COMPLETE.md** — Visual diagrams

---

## Support

For issues or questions:
1. Check **Troubleshooting** section above
2. Review logs: `docker-compose logs <service>`
3. Verify environment variables in `.env`
4. Check database connectivity: `docker-compose exec db mysql -u cie_user -p`

---

**Project**: CIE v2.3.2 — Catalog Intelligence Engine  
**Date Updated**: February 2026  
**Status**: Production Ready
