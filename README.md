# CIE v2.3.2 - Catalog Intelligence Engine

## 🚀 Overview
CIE is an enterprise-grade product content management system designed for scale and intelligence. Built with a twin-engine architecture (PHP for core governance and Python for AI-powered processing), it automates product data enrichment, validation, and distribution.

## ✨ Key Features
- **AI-Powered Validation**: Multi-layer audit engine using LLMs (GPT-4, Claude 3.5, Gemini 1.5) for content accuracy.
- **Tier-Based Governance**: Advanced permission systems ensuring data integrity across organizational levels.
- **Automated Quality Enforcement**: Real-time validation gates for product data and media.
- **ERP Synchronization**: Seamless integration connectors for SAP, Dynamics 365, and custom ERPs.
- **Vector-Based Intelligent Search**: Semantic search capabilities for product catalogs.
- **Automated Marketing Copy**: Generation of SEO-optimized product descriptions and marketing briefs.

## 🛠 Tech Stack
### Core Modernization
- **Backend (Governance)**: [PHP 8.1+](file:///c:/Dilaksan/CIE/cie-v232/backend/php) (Laravel 9+ core patterns)
- **Backend (Intelligence)**: [Python 3.11+](file:///c:/Dilaksan/CIE/cie-v232/backend/python) (FastAPI, LangChain)
- **Frontend**: [React 18.2+](file:///c:/Dilaksan/CIE/cie-v232/frontend) (Vite, Zustand, Tailwind CSS)
- **Database**: PostgreSQL 16 & Redis 7.0 (`DB_CONNECTION=pgsql` — see `database/postgres/README.md`)
- **AI/ML**: OpenAI, Anthropic, Google Vertex AI

## 📂 Project Structure
```text
├── backend/
│   ├── php/          # Core Business Logic & Governance API
│   └── python/       # AI Engines, Jobs, & ERP Sync Workers
├── frontend/         # Modern React SPA
├── database/         # Migrations, Seeds, and Schema DB
├── docs/             # Technical & User Documentation
├── infrastructure/   # Docker, K8s, and Terraform configs
├── monitoring/       # Prometheus/Grafana Dashboards
└── scripts/          # Automation & Setup utilities
```

## 🚀 Getting Started

**👉 Start here**: [START_HERE.md](START_HERE.md) — Overview of what was implemented and how to verify everything works.

### Prerequisites
- Docker & Docker Compose
- Node.js 18+
- PHP 8.1+ & Composer (for local development)
- Python 3.11+ (for local development)

### Quick Start (Docker)
1. **Clone and Setup ENV**:
   ```bash
   cp .env.example .env
   ```
2. **Launch Services** (first boot applies PostgreSQL schema from `database/postgres/init/`):
   ```bash
   docker-compose up -d --build
   ```
3. **Verify database**:
   ```bash
   docker-compose exec db psql -U cie_user -d cie_v232 -c "\dt"
   python scripts/verify_pg_indexes.py
   ```
4. **Access Services**:
   - Frontend: http://localhost:3000
   - PHP API: http://localhost:9000/api
   - Python API: http://localhost:5000

### For Complete Setup Instructions
Refer to [IMPLEMENTATION_GUIDE.md](IMPLEMENTATION_GUIDE.md) for detailed step-by-step local development setup.

## 📖 Documentation Guide

| Document | Purpose | Read Time |
|----------|---------|-----------|
| [START_HERE.md](START_HERE.md) | **Quick overview** - What was built, how to verify | 5 min |
| [IMPLEMENTATION_GUIDE.md](IMPLEMENTATION_GUIDE.md) | **Full setup guide** - Environment, database, docker, testing | 30 min |
| [MASTER_SUMMARY.md](MASTER_SUMMARY.md) | **Architecture summary** - All 7 critical fixes | 10 min |
| [WORKFLOW_WIRING_SUMMARY.md](WORKFLOW_WIRING_SUMMARY.md) | **Technical wiring** - How components connect | 20 min |
| [QUICK_START_GUIDE.md](QUICK_START_GUIDE.md) | **Test procedures** - Health checks, SKU creation, audits | 15 min |
| [SYSTEM_ARCHITECTURE_COMPLETE.md](SYSTEM_ARCHITECTURE_COMPLETE.md) | **Architecture diagrams** - Visual overview of system | 10 min |
| [API_REFERENCE_COMPLETE.md](docs/API_REFERENCE_COMPLETE.md) | **Full API docs** - All endpoints with examples | 30 min |

**Recommended Reading Path**:
1. START_HERE.md (5 min) → Overview
2. QUICK_START_GUIDE.md (15 min) → Get it running
3. IMPLEMENTATION_GUIDE.md (30 min) → Deep setup
4. API_REFERENCE_COMPLETE.md (30 min) → For development

## ⚖️ License
Internal Engine - Confidential (Refer to [LICENSE](file:///c:/Dilaksan/CIE/cie-v232/LICENSE))

9d1c32d9ac8609c9290270b360b7d4a1622c1038
