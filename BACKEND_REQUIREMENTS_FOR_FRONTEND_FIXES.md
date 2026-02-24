# 🔧 BACKEND REQUIREMENTS - Frontend Audit Fixes

**Prepared for**: PHP/Python Backend Development Team  
**Audit Date**: February 18, 2026  
**Scope**: Changes needed to support frontend fixes  

---

## 📋 OVERVIEW

The frontend audit identified 48 issues, many of which require backend API changes to resolve. This document defines:

1. API response structure changes
2. New endpoints required
3. Database schema additions
4. Validation logic updates

**Total Estimated Backend Work**: 60-80 hours

---

## 🔄 API RESPONSE STRUCTURE CHANGES

### 1. SKU Response - Add Gate Results

**Current Response**:
```json
{
    "sku": {
        "id": 1,
        "sku_code": "CBL-BLK-3C-3M",
        "title": "Black 3-Core Cable 3m",
        "tier": "HERO",
        "readiness_score": 85,
        "ai_citation_rate": 72,
        "...": "..."
    }
}
```

**Required Response**:
```json
{
    "sku": {
        "id": 1,
        "sku_code": "CBL-BLK-3C-3M",
        "title": "Black 3-Core Cable 3m",
        "tier": "HERO",
        "readiness_score": 85,
        "ai_citation_rate": 72,
        
        "gates": {
            "G1": { "passed": true, "score": 100, "message": "Cluster assigned" },
            "G2": { "passed": true, "score": 95, "message": "Title meets format" },
            "G3": { "passed": true, "score": 100, "message": "Intents defined" },
            "G4": { "passed": true, "score": 90, "message": "Answer block 278 chars" },
            "G5": { "passed": false, "score": 0, "message": "Best/Not-For not provided" },
            "G6": { "passed": true, "score": 85, "message": "Description present" },
            "G6.1": { "passed": true, "score": 100, "message": "Tier fields complete" },
            "G7": { "passed": true, "score": 80, "message": "Authority block present" },
            "VEC": { "passed": true, "score": 0.87, "message": "Vector similarity 0.87" }
        },
        "vector_similarity": 0.87,
        "lock_version": 5,
        "effort_minutes_this_quarter": 25,
        
        "...": "..."
    }
}
```

**Impact Pages**:
- Dashboard.jsx - Can display real gate status
- SkuEdit.jsx - Can show gate validation results
- ReviewQueue.jsx - Can validate gates before approval

**Effort**: 4-6 hours (PHP)

**Implementation**:
```php
// In SkuController::show()
$sku = Sku::with('gateResults')->find($id);
$response = [
    'sku' => $sku->toArray(),
    'gates' => $sku->gateResults->groupBy('gate_id')->map(fn($results) => [
        'passed' => $results->first()->passed,
        'score' => $results->first()->score,
        'message' => $results->first()->message
    ])
];
```

---

### 2. SKU List Response - Include Gates Summary

When listing SKUs (for Dashboard), include gate pass/fail status:

```json
{
    "data": [
        {
            "id": 1,
            "sku_code": "CBL-BLK-3C-3M",
            "title": "Black 3-Core Cable 3m",
            "tier": "HERO",
            "readiness_score": 85,
            "ai_citation_rate": 72,
            "gates": {
                "G1": true,
                "G2": true,
                "G3": true,
                "G4": true,
                "G5": false,
                "G6": true,
                "G6.1": true,
                "G7": true,
                "VEC": true
            },
            "vector_similarity": 0.87,
            "...": "..."
        }
    ]
}
```

**Impact Pages**:
- Dashboard.jsx - Display gate chip status

**Effort**: 2-3 hours

---

## ✅ NEW ENDPOINTS REQUIRED

### 1. GET /api/config
**Purpose**: Fetch system configuration values  
**Access**: Admin + (read-only for others)  
**Response**:
```json
{
    "data": {
        "gate_thresholds": {
            "answer_block_min": 250,
            "answer_block_max": 300,
            "title_max": 250,
            "vector_threshold": 0.72,
            "title_intent_min": 20
        },
        "tier_weights": {
            "margin": 0.30,
            "velocity": 0.30,
            "return_rate": 0.20,
            "margin_rank": 0.20,
            "hero_threshold": 75
        },
        "channel_thresholds": {
            "hero_compete_min": 85,
            "support_compete_min": 70,
            "harvest": "excluded",
            "kill": "excluded",
            "feed_regen_time": "02:00"
        },
        "audit_settings": {
            "day": "Monday",
            "time": "06:00",
            "questions_per_category": 20,
            "engines": 4,
            "decay_trigger": "Week 3"
        },
        "version": "2.3.2",
        "last_updated": "2026-02-18 10:30:00"
    }
}
```

**Effort**: 2 hours

**Note**: Values should come from database config table, not hardcoded

---

### 2. PUT /api/config (Admin Only)
**Purpose**: Update configuration  
**Access**: Admin only  
**Request**:
```json
{
    "key": "gate_thresholds.answer_block_min",
    "value": 250
}
```

**Response**:
```json
{
    "success": true,
    "message": "Config updated",
    "updated_by": "admin@example.com",
    "timestamp": "2026-02-18 10:35:00"
}
```

**Effort**: 2 hours

**Note**: Log all changes to audit_log table

---

### 3. GET /api/metrics/maturity
**Purpose**: Calculate maturity by category  
**Access**: Any authenticated user  
**Query Params**: `?category=Cables` (optional)  
**Response**:
```json
{
    "data": {
        "by_category": [
            {
                "category": "Cables",
                "percentage": 76,
                "core_fields": 88,
                "authority": 62,
                "channel_readiness": 74,
                "ai_visibility": 72,
                "color": "#8B6914"
            },
            {
                "category": "Lampshades",
                "percentage": 58,
                "...": "..."
            }
        ],
        "tier_compliance": [
            {
                "tier": "HERO",
                "target": "≥85%",
                "actual": "68%",
                "met": 56,
                "total": 82
            }
        ],
        "last_calculated": "2026-02-18 08:00:00"
    }
}
```

**Effort**: 6-8 hours (calculation logic)

**Implementation**:
```php
// Calculate per category (cluster)
// For each cluster:
//   - Count SKUs by tier
//   - Calculate readiness components
//   - Aggregate into percentages
```

---

### 4. GET /api/metrics/channel-readiness
**Purpose**: Calculate channel eligibility based on SKU readiness  
**Access**: Any authenticated  
**Response**:
```json
{
    "data": {
        "channels": [
            {
                "name": "Own Website",
                "score": 78,
                "compete": 186,
                "skip": 72,
                "total_skus": 258
            },
            {
                "name": "Google Shopping",
                "score": 71,
                "compete": 164,
                "skip": 94,
                "total_skus": 258
            }
        ],
        "last_calculated": "2026-02-18 08:00:00"
    }
}
```

**Effort**: 4-6 hours

**Logic**:
```
For each channel:
  - Get all active SKUs
  - Filter by tier rules:
    - Hero >= 85% readiness → COMPETE
    - Support >= 70% → COMPETE
    - Harvest/Kill → SKIP
  - Count COMPETE/SKIP
  - Calculate channel score
```

---

### 5. GET /api/staff-metrics
**Purpose**: Get performance KPIs per staff member  
**Access**: Governor/Admin only (own metrics visible to all)  
**Query Params**: `?range=week|month|quarter|custom` (default: week), `?start=YYYY-MM-DD&end=YYYY-MM-DD`  
**Response**:
```json
{
    "data": [
        {
            "id": 1,
            "name": "Sarah M.",
            "role": "editor",
            "skus_completed": 14,
            "first_submit_pass_rate": "82%",
            "avg_review_time_minutes": 1.2,
            "rework_rate": "8%",
            "hero_time_percent": "65%",
            "period": "2026-W7"
        }
    ],
    "range": "week",
    "start_date": "2026-02-10",
    "end_date": "2026-02-16",
    "generated_at": "2026-02-18 10:35:00"
}
```

**Effort**: 6 hours

**Queries**:
- SKUs per user this period (from audit_log)
- 1st submit pass rate (validation_logs)
- Avg review time (timestamp of creation → approval)
- Rework rate (rejections / submissions)
- HERO time (SKUs worked on that were HERO tier)

---

### 6. GET /api/decay-alerts
**Purpose**: Get SKUs with declining citation rates  
**Access**: Governance users  
**Response**:
```json
{
    "data": [
        {
            "sku_id": 12,
            "sku_code": "LMP-COT-CYL-S",
            "title": "Cotton Cylinder Shade Small",
            "weeks_declining": 4,
            "trend": [18, 12, 8, 2],
            "status": "BRIEF_SENT",
            "detection_date": "2026-02-10",
            "brief_id": 456
        }
    ],
    "total_alerts": 3,
    "threshold_week": 3,
    "last_audit_run": "2026-02-10 06:00:00"
}
```

**Effort**: 2-3 hours

**Logic**: Query audit_results where weeks_since_last_submission >= 3 and trend is declining

---

### 7. GET /api/tier-reassignments
**Purpose**: List pending/completed tier reassignments  
**Access**: Finance/Portfolio roles  
**Query Params**: `?status=pending|approved|rejected` (default: pending)  
**Response**:
```json
{
    "data": [
        {
            "id": 1,
            "sku_id": 45,
            "sku_code": "CBL-GRY-3C-1M",
            "current_tier": "HARVEST",
            "proposed_tier": "KILL",
            "reason": "Below threshold 3 months",
            "proposed_by": "system",
            "override_required": true,
            "approvals": [
                { "role": "portfolio_holder", "approved": null, "approved_by": null, "date": null },
                { "role": "finance_director", "approved": null, "approved_by": null, "date": null }
            ],
            "approval_count": 0,
            "created_at": "2026-02-15",
            "expires_at": "2026-02-25"
        }
    ]
}
```

**Effort**: 4 hours

---

### 8. POST /api/tier-reassignments/{id}/approve
**Purpose**: Submit approval for tier reassignment  
**Access**: Portfolio Holder or Finance Director only  
**Request**:
```json
{
    "approved": true,
    "reason": "Margin criteria met"
}
```

**Response**:
```json
{
    "success": true,
    "reassignment": {
        "id": 1,
        "approval_count": 1,
        "approvals": [
            { "role": "portfolio_holder", "approved": true, "approved_by": "john@example.com", "date": "2026-02-18 10:40:00" },
            { "role": "finance_director", "approved": null, "approved_by": null, "date": null }
        ],
        "status": "pending_final_approval"
    },
    "message": "1/2 approvals collected"
}
```

**Effort**: 4 hours

**Note**: When both approvals received, trigger tier change and audit_log entry

---

### 9. GET /api/skus/{id}/faq
**Purpose**: Get FAQ data for a specific SKU  
**Access**: Any authenticated  
**Response**:
```json
{
    "data": {
        "sku_id": 1,
        "sku_code": "CBL-BLK-3C-3M",
        "generated_at": "2026-02-15 14:30:00",
        "status": "generated",
        "faqs": [
            {
                "question": "What fitting types work with this cable?",
                "answer": "This cable is compatible with E27, B22, and GU10 lamp holders.",
                "source": "golden_query_set"
            }
        ],
        "can_regenerate": true
    }
}
```

**Effort**: 3 hours

---

### 10. POST /api/skus/{id}/regenerate-faq
**Purpose**: Manually regenerate FAQ for SKU  
**Access**: Editor/Governor  
**Response**:
```json
{
    "success": true,
    "status": "generating",
    "brief_id": 789,
    "message": "FA generation queued. Check back in 30 seconds."
}
```

**Effort**: 2 hours (queue to Python worker)

---

### 11. GET /api/skus/{id}/audit-log
**Purpose**: Get change history for SKU  
**Access**: Any authenticated  
**Response**:
```json
{
    "data": [
        {
            "timestamp": "2026-02-10 14:32:18",
            "user": "Sarah M.",
            "role": "editor",
            "action": "content_edit",
            "field": "short_description",
            "old_value": "...",
            "new_value": "...",
            "detail": "Updated answer block (278 chars)"
        }
    ],
    "total": 47
}
```

**Effort**: 2 hours (query existing audit_log)

---

## 📊 DATABASE SCHEMA ADDITIONS

### 1. Add Fields to `skus` Table

```sql
ALTER TABLE skus ADD COLUMN vector_similarity DECIMAL(3,2) DEFAULT NULL AFTER readiness_score;
ALTER TABLE skus ADD COLUMN lock_version INT DEFAULT 1 AFTER vector_similarity;
ALTER TABLE skus ADD COLUMN effort_minutes_this_quarter INT DEFAULT 0 AFTER lock_version;
```

**Fields**:
- `vector_similarity` (DECIMAL 0.00-1.00) - Cosine similarity score
- `lock_version` (INT) - For optimistic locking on concurrent edits
- `effort_minutes_this_quarter` (INT) - Time spent on HARVEST tier SKUs this quarter

**Effort**: 1 hour

### 2. Create `config` Table

```sql
CREATE TABLE config (
    id INT PRIMARY KEY AUTO_INCREMENT,
    section VARCHAR(100) NOT NULL,
    key VARCHAR(100) NOT NULL,
    value TEXT,
    data_type ENUM('string', 'int', 'float', 'boolean', 'json') DEFAULT 'string',
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    updated_by VARCHAR(255),
    UNIQUE KEY unique_section_key (section, key)
);
```

**Effort**: 1 hour

---

## 🔐 VALIDATION LOGIC UPDATES

### 1. SKU Create/Update Validation

Update `ValidationService` to enforce tier-specific requirements:

```php
// In ValidationService::validate()

public function validateForTier(Sku $sku) {
    $tier = $sku->tier;
    
    switch ($tier) {
        case 'HERO':
            // Require all G1-G7 gates + vector >= 0.72
            return $this->validateHero($sku);
            
        case 'SUPPORT':
            // Require G1-G4 gates + vector
            return $this->validateSupport($sku);
            
        case 'HARVEST':
            // Require only G1-G2 (specification)
            return $this->validateHarvest($sku);
            
        case 'KILL':
            // No edits allowed
            return $this->validateKill($sku);
    }
}

private function validateHero(Sku $sku): ValidationResult {
    $gates = [
        'G1' => $sku->primaryCluster !== null,
        'G2' => strlen($sku->title ?? '') > 0 && strlen($sku->title) <= 250,
        'G3' => count($sku->intents ?? []) > 0,
        'G4' => strlen($sku->short_description ?? '') >= 250 && strlen($sku->short_description) <= 300,
        'G5' => strlen($sku->best_for ?? '') > 0,
        'G6' => strlen($sku->description ?? '') > 0,
        'G6.1' => $this->validateTierFields($sku),
        'G7' => strlen($sku->authority ?? '') > 0,
    ];
    
    // Call Python for vector validation
    $vector = $this->pythonClient->validateVector($sku);
    $gates['VEC'] = $vector['similarity'] >= 0.72;
    
    return ValidationResult::make($gates);
}
```

**Effort**: 3-4 hours (PHP)

### 2. Approval Validation

Add dual-approval requirement for tier changes:

```php
// In TierController or TierService

public function submitApproval($reassignmentId, User $user) {
    $reassignment = TierReassignment::find($reassignmentId);
    
    // Only Portfolio Holder or Finance Director can approve
    if (!in_array($user->role, ['portfolio_holder', 'finance_director'])) {
        throw new UnauthorizedException('You cannot approve tier changes');
    }
    
    // Prevent same user approving twice
    if ($reassignment->approvals->pluck('approved_by')->contains($user->id)) {
        throw new ValidationException('You already approved this reassignment');
    }
    
    // Record approval
    $reassignment->approvals()->create([
        'role' => $user->role,
        'approved_by' => $user->id,
        'date' => now(),
        'reason' => request('reason')
    ]);
    
    // Check if both roles have approved
    if ($reassignment->approvals->count() >= 2) {
        // Apply tier change
        $this->applyTierChange($reassignment);
    }
}
```

**Effort**: 2-3 hours (PHP)

---

## 🐍 PYTHON BACKEND UPDATES

### 1. Update Audit Result Storage

Ensure audit results include vector similarity:

```python
# In brief_generator/generator.py or ai_audit/audit_engine.py

result = {
    'sku_id': sku_id,
    'audit_results': [...],  # Per-engine results
    'vector_similarity': vector_score,  # Add this
    'completed_at': now(),
    'status': 'completed'
}

# Save to database
db.audit_results.insert_one(result)

# Also update SKU.vector_similarity
db.skus.update_one(
    {'_id': sku_id},
    {'$set': {'vector_similarity': vector_score}}
)
```

**Effort**: 1-2 hours (Python)

### 2. Add Effort Minutes Tracking

Track time spent on HARVEST tier SKUs:

```python
# Create endpoint in Flask API
@app.route('/api/effort/<sku_id>', methods=['POST'])
def log_effort(sku_id):
    minutes = request.json.get('minutes', 0)
    user_id = request.headers.get('X-User-Id')
    sku = db.skus.find_one({'_id': sku_id})
    
    if sku['tier'] != 'HARVEST':
        return {'error': 'Only HARVEST tier tracks effort'}, 400
    
    # Add minutes to quarter total
    db.skus.update_one(
        {'_id': sku_id},
        {'$inc': {'effort_minutes_this_quarter': minutes}}
    )
    
    return {'success': True, 'total_minutes': ...}
```

**Effort**: 2 hours (Python)

---

## 📋 TESTING CHECKLIST

### Frontend Integration Tests
- [ ] Dashboard displays correct gate status from API
- [ ] SkuEdit shows all G1-G7 fields
- [ ] SkuEdit KILL tier fields are disabled
- [ ] Config page shows fetched values
- [ ] Maturity page calculates correctly
- [ ] ReviewQueue validates gates before approval
- [ ] TierMgmt shows approval status
- [ ] AiAudit displays real audit data

### Backend Unit Tests
- [ ] Gate validation works per tier
- [ ] Dual approval workflow works
- [ ] Config CRUD operations work
- [ ] Metrics calculations are accurate
- [ ] Decay alert detection works
- [ ] Staff KPI queries correct

### Integration Tests
- [ ] End-to-end: Cre SKU → Audit Runs → Dashboard Shows → Approve in Queue
- [ ] End-to-end: Tier reassignment with dual approval
- [ ] End-to-end: Config change → All SKUs recalculated

---

## 🚀 IMPLEMENTATION ORDER

**Week 1**:
1. Add DB fields to `skus` table
2. Create `config` table + API
3. Update SKU response structure to include gates
4. Implement tier validation in ValidationService

**Week 2**:
1. Add GET /api/metrics/maturity
2. Add GET /api/metrics/channel-readiness
3. Add GET /api/decay-alerts
4. Add GET /api/staff-metrics

**Week 3**:
1. Add GET/PUT /api/config endpoints
2. Add tier reassignment approval workflow
3. Add FAQ + audit log endpoints
4. Update Python for vector + effort tracking

---

## 📞 DEPENDENCIES

Frontend updates depend on these backend changes:

| Frontend Page | Required Backend | Priority |
|---------------|-----------------|----------|
| Dashboard | Gate results in SKU | CRITICAL |
| SkuEdit | Gate validation, Vector display | CRITICAL |
| Config | GET /api/config endpoint | CRITICAL |
| AiAudit | Audit data API (already have) | CRITICAL |
| Maturity | GET /api/metrics/maturity | HIGH |
| Channels | GET /api/metrics/channel-readiness | HIGH |
| ReviewQueue | Gate validation | CRITICAL |
| TierMgmt | GET /api/tier-reassignments + approval workflow | HIGH |
| StaffKpis | GET /api/staff-metrics | MEDIUM |
| Briefs | GET /api/skus/{id}/faq | MEDIUM |

---

**Total Backend Effort**: 60-80 hours  
**Timeline**: 3-4 weeks with coordinated development
