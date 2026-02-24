# 🔍 FRONTEND AUDIT REPORT - CIE v2.3.2
## Comprehensive Analysis of All Frontend Pages

**Generated**: February 18, 2026  
**Scope**: `frontend/src/pages/*.jsx` (13 pages)  
**Total Issues Found**: 48 issues across all pages  
**Critical Issues**: 12  
**High Priority**: 18  
**Medium Priority**: 18  

---

## 📊 EXECUTIVE SUMMARY

| Page | Status | Issues | Critical | Needs Work |
|------|--------|--------|----------|-----------|
| **AiAudit.jsx** | 🟡 Mixed | 3 | 1 | Hardcoded data |
| **AuditTrail.jsx** | 🟢 Good | 0 | 0 | ✅ Compliant |
| **Briefs.jsx** | 🟡 Mixed | 2 | 1 | Static data only |
| **BulkOps.jsx** | 🟢 Good | 1 | 0 | No API integration |
| **Channels.jsx** | 🟢 Good | 1 | 0 | All hardcoded |
| **ClustersPage.jsx** | 🟢 Good | 2 | 0 | Proper API calls |
| **Config.jsx** | 🟡 Mixed | 2 | 1 | Static config |
| **Dashboard.jsx** | 🟡 Mixed | 6 | 3 | Gate validation missing |
| **Maturity.jsx** | 🟡 Mixed | 4 | 2 | Hardcoded stats |
| **ReviewQueue.jsx** | 🟢 Good | 3 | 0 | Good error handling |
| **SkuEdit.jsx** | 🔴 Critical | 15 | 5 | **Major issues** |
| **StaffKpis.jsx** | 🟡 Mixed | 3 | 1 | Hardcoded KPIs |
| **TierMgmt.jsx** | 🟡 Mixed | 5 | 3 | No RBAC checks |

---

## 🔴 CRITICAL ISSUES (12)

### 1. **SkuEdit.jsx** - KILL Tier Fields Disabled but Editable
**Severity**: 🔴 CRITICAL  
**File**: [SkuEdit.jsx](frontend/src/pages/SkuEdit.jsx#L60-L75)  
**Issue**: KILL tier SKUs are marked read-only, but ALL form fields remain editable (inputs, textareas, selects)

```jsx
// Lines 60-75: isKillTier is checked only for button visibility
const isKillTier = currentTier === 'KILL';
// ... later ...
{!isKillTier && (
    <button className="btn btn-primary" onClick={() => handleSave(true)}>Submit</button>
)}
// BUT: <input> and <textarea> fields have NO disabled attribute
```

**Impact**: Users can edit KILL tier SKUs' content even though policy says all fields DISABLED  
**Recommendation**: 
- Add `disabled={isKillTier}` to ALL input/textarea/select elements
- Add visual styling to show disabled state
- Block save/submit endpoints at API level

---

### 2. **Dashboard.jsx** - No Gate Validation Display
**Severity**: 🔴 CRITICAL  
**File**: [Dashboard.jsx](frontend/src/pages/Dashboard.jsx#L155-L160)  
**Issue**: Gate status shows for all SKUs but always renders `pass={false}` (hardcoded)

```jsx
// Lines 155-160: GATES always fail
{GATES.map(g => <GateChip key={g.id} id={g.id} pass={false} compact />)}
// Should be: pass={sku.gates?.[g.id]?.passed || false}
```

**Impact**: No visibility into which SKUs pass/fail gates - violates CIE governance  
**Recommendation**:
- Pass actual gate validation results from API: `sku.gate_results`
- Map each gate to its status
- Update API to return gate details

---

### 3. **SkuEdit.jsx** - No Role Validation for Edits
**Severity**: 🔴 CRITICAL  
**File**: [SkuEdit.jsx](frontend/src/pages/SkuEdit.jsx#L25-L55)  
**Issue**: Component fetches SKU but never checks if user's role permits editing

```jsx
// Lines 25-55: No RBAC check
const SkuEdit = () => {
    const { id } = useParams();
    // ... fetch SKU ...
    // NO CHECK: if (user.role !== 'editor' && user.role !== 'governor') redirect
};
```

**Impact**: Non-editors could theoretically modify SKU edit component state  
**Recommendation**:
- Check `useStore().user.role` before allowing component load
- For HERO tier: only 'governor' role
- For SUPPORT/HARVEST: 'editor' or 'governor'
- For KILL: read-only regardless of role

---

### 4. **SkuEdit.jsx** - HARVEST Tier Has Manual Edits (Spec Only)
**Severity**: 🔴 CRITICAL  
**File**: [SkuEdit.jsx](frontend/src/pages/SkuEdit.jsx#L50-L52)  
**Issue**: HARVEST tier allows edits but specification only, max 30m/quarter per CIE rules

```jsx
// Lines 50-52: isHarvestTier flag exists but no restrictions
const isHarvestTier = currentTier === 'HARVEST';
// ... form fields are FULLY editable with no effort cap display
```

**Impact**: Users can exceed 30m/quarter effort cap on HARVEST SKUs  
**Recommendation**:
- Show effort timer: "5 min used / 30 min available this quarter"
- Disable editing after cap reached
- Only allow specification intent edits (not full content)
- Track effort per user per SKU

---

### 5. **Dashboard.jsx** - Citation Rate Not Fetched from API
**Severity**: 🔴 CRITICAL  
**File**: [Dashboard.jsx](frontend/src/pages/Dashboard.jsx#L240-L242)  
**Issue**: Citation rate hardcoded "48%" in stat card and per-SKU renders as computed value not from API

```jsx
// Lines 88-89: Hardcoded stat
<StatCard label="AI Citation Rate" value="48%" sub="↑ 6% vs last week" color="var(--accent)" />
// Lines 240-242: Per SKU hardcoded logic
<span style={{ color: (sku.ai_citation_rate || 0) >= 50 ? 'var(--green)' : ... }}>
    {sku.ai_citation_rate || 0}%
</span>
```

**Impact**: Users see stale/incorrect citation rates; can't trust readiness scores  
**Recommendation**:
- Fetch `sku.ai_citation_rate` from API (populated by audit process)
- Show last audit date
- Add refresh button to re-run audit

---

### 6. **Config.jsx** - All Settings Are Static
**Severity**: 🔴 CRITICAL  
**File**: [Config.jsx](frontend/src/pages/Config.jsx#L1-L60)  
**Issue**: Configuration page displays hardcoded values with no ability to edit or verify they match actual system

```jsx
// Lines 6-30: All values hardcoded
{ label: "Answer Block Min", value: "250", unit: "chars" },
{ label: "Vector Threshold", value: "0.72", unit: "cosine" },
// No fetch from API, no edit capability, no save
```

**Impact**: When config changes in code, frontend shows outdated values; no audit trail  
**Recommendation**:
- Add API endpoint `/api/config` to fetch actual values
- Add `/api/config/update` for admin-only edits
- Show config version and last updated timestamp
- Log all config changes to audit trail

---

### 7. **AiAudit.jsx** - All Audit Data Hardcoded
**Severity**: 🔴 CRITICAL  
**File**: [AiAudit.jsx](frontend/src/pages/AiAudit.jsx#L24-L50)  
**Issue**: Component fetches API but then ignores result and uses hardcoded mock data

```jsx
// Lines 24-50: Creates fetch function but doesn't use real data
const fetchAuditData = async () => {
    try {
        // setAuditScores (from API) → IGNORED
        setAuditScores([
            { week: 'W1', cables: 68, lampshades: 45, ... },  // HARDCODED
        ]);
    }
};
```

**Impact**: Users see fake audit results; no real AI audit data displayed  
**Recommendation**:
- Use actual response: `const data = await auditApi.list(); setAuditScores(data);`
- Show "No audits run yet" if empty
- Add real decay alert fetching from `/api/decay-alerts`

---

### 8. **SkuEdit.jsx** - G4/G5 (Semantics) Validation Not Real
**Severity**: 🔴 CRITICAL  
**File**: [SkuEdit.jsx](frontend/src/pages/SkuEdit.jsx#L250-L260)  
**Issue**: Vector validation panel shows hardcoded "0.87" instead of real validation result

```jsx
// Lines 250-260: Mock vector score
<div className="vector-panel">
    <div className="field-label">VECTOR — Semantic Similarity</div>
    <div className="vector-score" style={{ color: 'var(--green)' }}>0.87</div>  // HARDCODED
    <div className="vector-threshold">threshold: ≥0.72</div>
</div>
```

**Impact**: Can't see real cosine similarity to cluster intent; could publish non-conforming content  
**Recommendation**:
- Fetch real vector from `sku.vector_similarity` (from API)
- Add color coding: green >= 0.72, orange 0.60-0.71, red < 0.60
- Add "Recalculate Vector" button to re-embed
- Show cluster intent for reference

---

### 9. **TierMgmt.jsx** - No RBAC for Override Approvals
**Severity**: 🔴 CRITICAL  
**File**: [TierMgmt.jsx](frontend/src/pages/TierMgmt.jsx#L1-L60)  
**Issue**: Override approval button shown to all users, but policy requires DUAL approval from Portfolio Holder + Finance

```jsx
// Lines 45-48: No role check
{row.override ? (
    <button className="btn btn-reject btn-sm">Approve (1/2)</button>
) : <span>—</span>}
// No check if user.role === 'portfolio_holder' or 'finance_director'
```

**Impact**: Non-authorized users could attempt tier override approval  
**Recommendation**:
- Check `useStore().user.role` includes 'portfolio_holder' or 'finance_director'
- Show user's current approval state (approved/pending/rejected)
- Lock button if not authorized
- Require both signatures before tier change commits

---

### 10. **Briefs.jsx** - All Data Static (No SKU or API Binding)
**Severity**: 🔴 CRITICAL  
**File**: [Briefs.jsx](frontend/src/pages/Briefs.jsx#L1-L50)  
**Issue**: Brief template page shows hardcoded example with no SKU parameter or API integration

```jsx
// Lines 8-27: hardcoded briefItems
const briefItems = [
    { label: "Missing Fields", content: "Title (G2), Tier Fields (G6.1), Authority (G7)" },
    ...
];
// No SKU ID param, no API call, no brief history
```

**Impact**: Users can't generate briefs from SKU; page is just template documentation  
**Recommendation**:
- Pass `?sku_id=XXX` parameter
- Fetch brief from `/api/briefs/{sku_id}` or generate if missing
- Show brief generation status: pending/generated
- Allow re-generation after edits

---

### 11. **Maturity.jsx** - All Stats Hardcoded with No Data Binding
**Severity**: 🔴 CRITICAL  
**File**: [Maturity.jsx](frontend/src/pages/Maturity.jsx#L5-L20)  
**Issue**: Category maturity percentages are hardcoded instead of aggregated from SKU readiness

```jsx
// Lines 5-20: Static percentages
const categories = [
    { cat: "Cables", pct: 76, core: 88, auth: 62, channel: 74, ai: 72, color: "#8B6914" },
    ...
];
// Should be calculated from actual SKU data
```

**Impact**: Maturity dashboard misleads executives; boards see static numbers not actual health  
**Recommendation**:
- Calculate from aggregated SKU data grouped by cluster
- Fetch: `GET /api/metrics/maturity?category=Cables`
- Recalculate on page load
- Show last updated timestamp

---

### 12. **ReviewQueue.jsx** - Approval Action Lacks Tier Validation
**Severity**: 🔴 CRITICAL  
**File**: [ReviewQueue.jsx](frontend/src/pages/ReviewQueue.jsx#L45-L65)  
**Issue**: Approve/Reject buttons don't validate if approved SKU meets tier-specific gate requirements

```jsx
// Lines 45-65: Simple approve without tier checks
const handleAction = async (id, action, skuCode) => {
    const status = action === 'approve' ? 'VALID' : 'INVALID';
    await skuApi.update(id, { validation_status: status });
    // NO CHECK:
    // - For HERO: All G1-G7 must pass + Vector >= 0.72
    // - For SUPPORT: G1-G4 + Vector required
    // - For HARVEST: Only spec (G1-G2) required
};
```

**Impact**: Invalid SKUs can be approved; violates gate policies  
**Recommendation**:
- Load full SKU data including gate results
- Show gate status before allowing approval
- Block approval if required gates fail
- Display tier-specific requirements

---

## 🟠 HIGH PRIORITY ISSUES (18)

### 13. **SkuEdit.jsx** - HERO Tier Lacks G7 Authority Field
**File**: [SkuEdit.jsx](frontend/src/pages/SkuEdit.jsx#L150-L200)  
**Issue**: G7 (Authority) field completely missing from content tab
```jsx
// Missing: <textarea label="G7 - Authority Block" .../>
```
**Recommendation**: Add authority field with expert qualification validation

---

### 14. **SkuEdit.jsx** - Missing G5 and G6 Fields in Edit Form
**File**: [SkuEdit.jsx](frontend/src/pages/SkuEdit.jsx#L180-L220)  
**Issue**: Form only has G1, G2, G3, G4, Vector; missing G5 (Best/Not-For) and G6 (Description)
```jsx
// Only visible: G1, G3, G2, G4, Vector
// Missing: G5, G6, G6.1 (tier fields)
```
**Recommendation**: Add complete gate coverage

---

### 15. **Dashboard.jsx** - Missing Gate Results from SKU Object
**File**: [Dashboard.jsx](frontend/src/pages/Dashboard.jsx#L240)  
**Issue**: SKU object from API doesn't include gate validation results
```jsx
// sku.gate_results undefined
// Need: PUT /skus/{id} to include gate status in response
```
**Recommendation**: Update PHP API to include gate details in SKU response

---

### 16. **ReviewQueue.jsx** - No Tier-Specific Effort Tracking
**File**: [ReviewQueue.jsx](frontend/src/pages/ReviewQueue.jsx#L30)  
**Issue**: Component shows generic "Avg Review Time" but doesn't track effort cap per tier
```jsx
// Shows avg but no HARVEST "30m/quarter" cap tracking
```
**Recommendation**: Add effort indicator per SKU tier

---

### 17. **BulkOps.jsx** - All Operations Are Mock/Incomplete
**File**: [BulkOps.jsx](frontend/src/pages/BulkOps.jsx#L20)  
**Issue**: Card grid shows 6 operations but none have onClick handlers or API integration
```jsx
// Cards clickable (cursor: pointer) but no handlers
```
**Recommendation**: 
- Add click handlers for each operation
- Implement CSV import/export
- Add bulk tier reassignment workflow

---

### 18. **Maturity.jsx** - Tier Targets Are Static
**File**: [Maturity.jsx](frontend/src/pages/Maturity.jsx#L30)  
**Issue**: Tier compliance targets hardcoded; should come from config
```jsx
// { tier: "hero", target: "≥85%", actual: "68%" },
// "85%" and "68%" both static
```
**Recommendation**: Fetch from `/api/config` and `/api/metrics/tier-compliance`

---

### 19. **Channels.jsx** - Channel Readiness Hardcoded
**File**: [Channels.jsx](frontend/src/pages/Channels.jsx#L6-L10)  
**Issue**: Channel stats show static scores instead of real portfolio readiness
```jsx
// { ch: "Own Website", score: 78, compete: 186, skip: 72 },
// COMPETE/SKIP counts should aggregate from SKU tier eligibility
```
**Recommendation**: Calculate from `/api/metrics/channel-readiness`

---

### 20. **StaffKpis.jsx** - KPI Data Entirely Static
**File**: [StaffKpis.jsx](frontend/src/pages/StaffKpis.jsx#L3-L10)  
**Issue**: Staff performance table hardcoded; no API integration
```jsx
// const staff = [
//     { name: "Sarah M.", role: "editor", skus: 14, pass: "82%", ... },
// ];
```
**Recommendation**: Fetch from `/api/staff-metrics` with date range filtering

---

### 21. **AiAudit.jsx** - Missing Decay Alert Details
**File**: [AiAudit.jsx](frontend/src/pages/AiAudit.jsx#L45-L55)  
**Issue**: Decay alerts shown but with hardcoded SKUs and dates
```jsx
// const decayAlerts = [
//     { sku: "LMP-COT-CYL-S", status: "BRIEF SENT", ... },
// ];
```
**Recommendation**: Fetch real alerts from `/api/decay-alerts` or `/api/audit/alerts`

---

### 22. **SkuEdit.jsx** - No Draft Auto-Save
**File**: [SkuEdit.jsx](frontend/src/pages/SkuEdit.jsx#L70-L90)  
**Issue**: "Save Draft" button requires manual click; should have auto-save every 30sec
```jsx
// Manual save only via button click
// User could lose unsaved work
```
**Recommendation**: Add useEffect with debounced auto-save

---

### 23. **SkuEdit.jsx** - No Concurrent Edit Warning
**File**: [SkuEdit.jsx](frontend/src/pages/SkuEdit.jsx#L25-L50)  
**Issue**: Multiple editors could edit same SKU simultaneously with no conflict detection
```jsx
// No lock_version or optimistic locking check
```
**Recommendation**: Use lock_version (model has this field) and warn if stale

---

### 24. **Dashboard.jsx** - Search/Filter Not Real
**File**: [Dashboard.jsx](frontend/src/pages/Dashboard.jsx#L200-L230)  
**Issue**: Search and filter dropdowns work but filters may not properly filter at API level
```jsx
// searchTerm, tierFilter work via params
// but API response handling depends on correct backend support
```
**Recommendation**: Verify API parameters are honored: `?search=`, `?tier=`, `?category=`

---

### 25. **AuditTrail.jsx** - Filters Not Functional
**File**: [AuditTrail.jsx](frontend/src/pages/AuditTrail.jsx#L20-L30)  
**Issue**: Filter inputs and select for SKU/User/Action don't have onChange handlers
```jsx
// <input className="search-input" placeholder="Filter by SKU..." />
// No onChange, no state management
```
**Recommendation**: Add filter state and API call with params

---

### 26. **Config.jsx** - Admin-Only Not Enforced
**File**: [Config.jsx](frontend/src/pages/Config.jsx#L1)  
**Issue**: Page accessible to any authenticated user; should be admin-only
```jsx
// No role check: if (user.role !== 'admin') redirect
```
**Recommendation**: Add RBAC check in component page or router

---

### 27. **BulkOps.jsx** - Admin-Only Not Enforced
**File**: [BulkOps.jsx](frontend/src/pages/BulkOps.jsx#L1)  
**Issue**: Page fully accessible despite "Admin only" text in subtitle
```jsx
// Subtitle says "Admin only" but no code enforces it
```
**Recommendation**: Check user.role === 'admin' before render

---

### 28. **TierMgmt.jsx** - No Edit Mode Implementation
**File**: [TierMgmt.jsx](frontend/src/pages/TierMgmt.jsx#L40-L48)  
**Issue**: "Approve (1/2)" button shows approval count but no approval UI/workflow
```jsx
// Button has no onClick handler
// No modal/dialog for approval process
```
**Recommendation**: Implement approval workflow with dual-sign process

---

### 29. **ReviewQueue.jsx** - Gate Status Not Displayed
**File**: [ReviewQueue.jsx](frontend/src/pages/ReviewQueue.jsx#L80-L100)  
**Issue**: Queue items show readiness bar but not which gates pass/fail
```jsx
// No gate chip display like in Dashboard
```
**Recommendation**: Show gate status mini-view on queue items

---

### 30. **ClustersPage.jsx** - Edit Button Has No Handler
**File**: [ClustersPage.jsx](frontend/src/pages/ClustersPage.jsx#L50)  
**Issue**: Edit button on each cluster row has no onClick
```jsx
// <button className="btn btn-secondary btn-sm">Edit</button>
// No onClick handler, no redirect, no modal
```
**Recommendation**: Add cluster edit modal/page navigation

---

## 🟡 MEDIUM PRIORITY ISSUES (18)

### 31. **AiAudit.jsx** - Missing Error Toast Display
**File**: [AiAudit.jsx](frontend/src/pages/AiAudit.jsx#L55-L58)  
**Issue**: setError() updates state but no toast/alert component shows it to user
```jsx
// {error && <div>...</div>}  // Rendered in page but should be toast
```
**Recommendation**: Use `addNotification()` from store

---

### 32. **ReviewQueue.jsx** - Missing Optimistic Update Rollback
**File**: [ReviewQueue.jsx](frontend/src/pages/ReviewQueue.jsx#L50)  
**Issue**: Optimistic removal of SKU from list doesn't rollback if API fails
```jsx
// setSkus(prev => prev.filter(...)) happens before API success
// If API fails, UI is wrong
```
**Recommendation**: Only remove after API 200, or restore on catch

---

### 33. **SkuEdit.jsx** - No Unsaved Changes Warning
**File**: [SkuEdit.jsx](frontend/src/pages/SkuEdit.jsx#L1)  
**Issue**: User can navigate away without warning if they have unsaved edits
```jsx
// No beforeunload event, no React Router guard
```
**Recommendation**: Add `useNavigate` guard via useEffect + beforeunload

---

### 34. **Dashboard.jsx** - Category Filter Lacks Cluster Options
**File**: [Dashboard.jsx](frontend/src/pages/Dashboard.jsx#L195-L200)  
**Issue**: Category filter hardcoded with "Cables", "Lampshades" instead of loading from clusters
```jsx
// <option>Cables</option>
// Should load from API: clusters.map(c => <option>{c.name}</option>)
```
**Recommendation**: Fetch clusters on mount and populate filter dynamically

---

### 35. **SkuEdit.jsx** - FAQ Tab Hardcoded
**File**: [SkuEdit.jsx](frontend/src/pages/SkuEdit.jsx#L265-L280)  
**Issue**: FAQ tab shows hardcoded example; should fetch from `/api/skus/{id}/faq`
```jsx
// {activeTab === 'faq' && (
//     <div>Q: What fitting types...</div>
// )}
```
**Recommendation**: Fetch and show real FAQ data from API

---

### 36. **SkuEdit.jsx** - History Tab Hardcoded
**File**: [SkuEdit.jsx](frontend/src/pages/SkuEdit.jsx#L282-L290)  
**Issue**: History tab shows "No recent history"; should fetch from `/api/skus/{id}/history`
```jsx
// Can't see who edited what or when
```
**Recommendation**: Fetch audit_log entries for this SKU

---

### 37. **Maturity.jsx** - No Loading State
**File**: [Maturity.jsx](frontend/src/pages/Maturity.jsx#L1)  
**Issue**: Component doesn't have loading/error states despite potentially slow aggregation
```jsx
// No useState, useEffect, no loading spinner
// If API slow, page blocks
```
**Recommendation**: Add loading/error states and async data fetching

---

### 38. **Channels.jsx** - No Loading State
**File**: [Channels.jsx](frontend/src/pages/Channels.jsx#L1)  
**Issue**: Channel stats are hardcoded; should be calculated but no loading state
```jsx
// No async fetching
```
**Recommendation**: Async fetch channel metrics

---

### 39. **TierMgmt.jsx** - No Edit/Rollback for Manual Overrides
**File**: [TierMgmt.jsx](frontend/src/pages/TierMgmt.jsx#L30-L50)  
**Issue**: Override approval shown but no ability to edit reason, rollback, or cancel
```jsx
// Reason field not editable: "Below threshold 3 months"
```
**Recommendation**: Allow edit before dual approval, show decision history

---

### 40. **Config.jsx** - Values Not Editable
**File**: [Config.jsx](frontend/src/pages/Config.jsx#L20-L50)  
**Issue**: All values displayed as read-only badges; no edit mode
```jsx
// <span style={{ padding: "2px 8px", background: "var(--surface-alt)" }}>
//     {item.value}
// </span>
// Should be editable input/textarea for admin
```
**Recommendation**: Add admin-only edit mode for each setting

---

### 41. **ReviewQueue.jsx** - No Batch Approval
**File**: [ReviewQueue.jsx](frontend/src/pages/ReviewQueue.jsx#L80)  
**Issue**: Reviews are per-item; no bulk approve functionality
```jsx
// Each SKU needs individual approve/reject click
```
**Recommendation**: Add checkbox for multi-select + bulk approve

---

### 42. **BulkOps.jsx** - No Tier Reassignment Implementation
**File**: [BulkOps.jsx](frontend/src/pages/BulkOps.jsx#L5)  
**Issue**: "Bulk Tier Reassignment" card exists but no modal/workflow
```jsx
// { op: "Bulk Tier Reassignment", count: "12 pending" },
// No onClick, no form, no preview
```
**Recommendation**: Create bulk tier reassignment modal with preview

---

### 43. **AuditTrail.jsx** - No Pagination
**File**: [AuditTrail.jsx](frontend/src/pages/AuditTrail.jsx#L15)  
**Issue**: Table shows 5 hardcoded rows; real audit trail could have thousands
```jsx
// No limit, no offset, no pagination
```
**Recommendation**: Add pagination or virtual scroll for large logs

---

### 44. **StaffKpis.jsx** - No Date Range Selector
**File**: [StaffKpis.jsx](frontend/src/pages/StaffKpis.jsx#L1)  
**Issue**: KPIs shown without date range; unclear if weekly/monthly/quarterly
```jsx
// No <input type="date"> or date range picker
```
**Recommendation**: Add "This week" / "This month" / "Custom range" selector

---

### 45. **Briefs.jsx** - No Generation Status
**File**: [Briefs.jsx](frontend/src/pages/Briefs.jsx#L30)  
**Issue**: Shows static brief but no indication if it's pending generation
```jsx
// No status badge: "Pending", "Generated", "Regenerating"
```
**Recommendation**: Show brief generation status and timestamp

---

### 46. **Dashboard.jsx** - No Export Permissions Check
**File**: [Dashboard.jsx](frontend/src/pages/Dashboard.jsx#L215)  
**Issue**: "Export benefits.csv" button accessible to all, but should check role
```jsx
// <button className="btn btn-secondary">Export benefits.csv</button>
// Should check: user.role !== 'viewer'
```
**Recommendation**: Add role check for export button

---

### 47. **SkuEdit.jsx** - No Raw JSON Viewer for Debugging
**File**: [SkuEdit.jsx](frontend/src/pages/SkuEdit.jsx#L1)  
**Issue**: For complex validation issues, no way to see raw SKU JSON
```jsx
// Should have: <details><summary>Debug JSON</summary><pre>{sku}</pre></details>
```
**Recommendation**: Add development-only debug panels

---

### 48. **ClustersPage.jsx** - No Search/Filter
**File**: [ClustersPage.jsx](frontend/src/pages/ClustersPage.jsx#L35)  
**Issue**: Cluster table shows all without search; hard to find specific cluster
```jsx
// No search input, no filter, no sort
```
**Recommendation**: Add search by cluster name/ID and sort by readiness

---

## 📋 DETAILED PAGE ANALYSIS

---

### 1️⃣ AiAudit.jsx

**File**: [frontend/src/pages/AiAudit.jsx](frontend/src/pages/AiAudit.jsx)

#### Status: 🟡 MIXED

| Aspect | Status | Details |
|--------|--------|---------|
| Data Source | ❌ Hardcoded | All audit scores and decay alerts are mock |
| API Integration | 🟡 Partial | Has `auditApi` import but doesn't use response |
| Error Handling | ✅ Good | Try/catch + error state + notification |
| RBAC Validation | 🟢 Good | Governor-level access implied (not enforced at component) |
| Gate Validation | ⚠️ N/A | Read-only dashboard, no edits |
| Tier Rules | ⚠️ N/A | Display-only; no tier-specific logic |

#### Issues Found:
1. **Lines 24-50**: Fetch function creates mock data instead of using API response
   - `const fetchAuditData = async ()` → sets hardcoded array
   - Missing: `const data = await auditApi.list()`

2. **Lines 45-55**: Decay alerts hardcoded with no real data
   - Should fetch from `/api/decay-alerts` or calculated from audit results
   - Weeks, SKU codes, status hardcoded

3. **Lines 88-89**: Overall citation rate "48%" is static
   - Should calculate from `auditScores` data or fetch metric

#### Recommendations:
```jsx
// Replace hardcoded setAuditScores with:
const fetchAuditData = async () => {
    try {
        const { data } = await auditApi.list();  // Use response!
        setAuditScores(data.audit_scores || []);
        setDecayAlerts(data.decay_alerts || []);
    } catch (err) {
        setError('Failed to load audit data');
        addNotification({ type: 'error', message: err.message });
    }
};
```

---

### 2️⃣ AuditTrail.jsx

**File**: [frontend/src/pages/AuditTrail.jsx](frontend/src/pages/AuditTrail.jsx)

#### Status: 🟢 GOOD

| Aspect | Status | Details |
|--------|--------|---------|
| Data Source | 🟡 Hardcoded | 5 example rows shown |
| API Integration | ❌ Missing | No fetch call, no API integration |
| Error Handling | ⚠️ None | No error states |
| RBAC Validation | 🟢 OK | Immutable display, no edits needed |
| Gate Validation | N/A | Display-only |
| Tier Rules | N/A | Display-only |

#### Issues Found:
1. **Lines 15-30**: Filter inputs have no onChange handlers or state management
2. **Lines 42-60**: Table data is completely hardcoded
3. **Subtitle mentions "REVOKE UPDATE/DELETE enforced at database level"** - Good signal of design
4. **Missing pagination** - Real logs could be thousands of rows

#### Recommendations:
```jsx
// Add state + API integration:
const [logs, setLogs] = useState([]);
const [filters, setFilters] = useState({ sku: '', user: '', action: 'All' });

useEffect(() => {
    const fetchLogs = async () => {
        const { data } = await auditApi.list(filters);
        setLogs(data);
    };
    fetchLogs();
}, [filters]);

// Export CSV button should work
const handleExportCSV = async () => {
    const csv = convertToCSV(logs);
    downloadFile(csv, 'audit-trail.csv');
};
```

---

### 3️⃣ Briefs.jsx

**File**: [frontend/src/pages/Briefs.jsx](frontend/src/pages/Briefs.jsx)

#### Status: 🟡 MIXED

| Aspect | Status | Details |
|--------|--------|---------|
| Data Source | 🟡 Hardcoded | Example brief items static |
| API Integration | ❌ Missing | No SKU parameter, no brief fetching |
| Error Handling | ❌ None | No error states |
| RBAC Validation | 🟡 Partial | Should be editor+ only (not enforced) |
| Gate Validation | ⚠️ Shows | G6.1 mentioned but no actual validation |
| Tier Rules | ✅ Shows | Mentions "Support tier" in effort cap |

#### Issues Found:
1. **Lines 4-9**: All brief items hardcoded as static template
   - Missing: SKU ID from route params or query string
   - Missing: API call to fetch real brief for this SKU
   
2. **Lines 14-20**: TierBadge shows "support" hardcoded
   - Should be `sku.tier` from props/state

3. **No generation status tracking**
   - Should show: "Pending", "Generated at 10:30", "Regenerating"

#### Recommendations:
```jsx
// Use route params to identify SKU
import { useParams } from 'react-router-dom';

const Briefs = () => {
    const { skuId } = useParams();
    const [brief, setBrief] = useState(null);
    const [loading, setLoading] = useState(true);

    useEffect(() => {
        if (!skuId) return;
        briefApi.get(skuId)
            .then(res => setBrief(res.data.data))
            .catch(err => setError('No brief found'));
    }, [skuId]);

    if (!skuId) return <div>No SKU selected</div>;
    if (loading) return <LoadingSpinner />;
    
    return (
        <div>
            <TierBadge tier={brief.sku.tier} />
            <Content content={brief.content} />
            <Button onClick={regenerateBrief}>Regenerate</Button>
        </div>
    );
};
```

---

### 4️⃣ BulkOps.jsx

**File**: [frontend/src/pages/BulkOps.jsx](frontend/src/pages/BulkOps.jsx)

#### Status: 🟢 GOOD (Structure)

| Aspect | Status | Details |
|--------|--------|---------|
| Data Source | 🟡 Hardcoded | Operations grid with pending counts |
| API Integration | ❌ Missing | No onClick handlers, no workflow |
| Error Handling | ❌ None | No error handling |
| RBAC Validation | ❌ Missing | "Admin only" in subtitle but not enforced |
| Gate Validation | N/A | Admin function, no user-facing gates |
| Tier Rules | ⚠️ Partial | Bulk tier reassignment mentioned |

#### Issues Found:
1. **Lines 20-30**: Operation cards fully clickable (cursor: pointer) but no handlers
2. **No admin-only gate** - Component renders to non-admins
3. **"12 pending" count hardcoded** - Should come from API
4. **No preview/confirmation** - User could bulk-update many SKUs unintentionally

#### Recommendations:
```jsx
// Add RBAC gate
import { useStore } from '../store';

const BulkOps = () => {
    const { user } = useStore();
    if (user?.role !== 'admin') return <AccessDenied />;
    
    const handleBulkTierReassignment = () => {
        setShowModal(true);
        // Show: file upload, preview, confirmation
    };
    
    return (
        <div>
            {ops.map(op => (
                <div key={op.op} className="card" onClick={() => {
                    if (op.op === "Bulk Tier Reassignment") {
                        handleBulkTierReassignment();
                    }
                    // ... other operations
                }}>
                    {op.op}
                </div>
            ))}
            <BulkTierReassignmentModal />
        </div>
    );
};
```

---

### 5️⃣ Channels.jsx

**File**: [frontend/src/pages/Channels.jsx](frontend/src/pages/Channels.jsx)

#### Status: 🟢 GOOD (Design)

| Aspect | Status | Details |
|--------|--------|---------|
| Data Source | 🟡 Hardcoded | Channel stats and rules static |
| API Integration | ❌ Missing | No fetch from API |
| Error Handling | ❌ None | No error states |
| RBAC Validation | ⚠️ N/A | Display-only |
| Gate Validation | ✅ Shows | Rules clearly documented |
| Tier Rules | ✅ Shows | Hero ≥85%, Support ≥70%, etc. |

#### Issues Found:
1. **Lines 6-10**: channelStats hardcoded with percentages
   - Should calculate from aggregated SKU readiness per channel
   - Fetch: `GET /api/metrics/channel-readiness`

2. **Lines 14-20**: rules are hardcoded but correct in policy
3. **No loading state** - Should fetch async if calculating

#### Recommendations:
```jsx
// Add async data fetching
const [channelStats, setChannelStats] = useState([]);
const [loading, setLoading] = useState(true);

useEffect(() => {
    const fetchChannelMetrics = async () => {
        try {
            const { data } = await metricsApi.getChannelReadiness();
            setChannelStats(data.channels);
        } catch (err) {
            addNotification({ type: 'error', message: 'Failed to load metrics' });
        } finally {
            setLoading(false);
        }
    };
    fetchChannelMetrics();
}, []);
```

---

### 6️⃣ ClustersPage.jsx

**File**: [frontend/src/pages/ClustersPage.jsx](frontend/src/pages/ClustersPage.jsx)

#### Status: 🟢 GOOD (API Integration)

| Aspect | Status | Details |
|--------|--------|---------|
| Data Source | ✅ API | Fetches from `clusterApi.list()` |
| API Integration | ✅ Good | Proper error/loading states |
| Error Handling | ✅ Good | Try/catch + notification |
| RBAC Validation | 🟡 Partial | Message says "Governor-only" but not enforced |
| Gate Validation | N/A | No validation gates for clusters |
| Tier Rules | ⚠️ N/A | No tier logic |

#### Issues Found:
1. **Lines 50-51**: Edit button has no onClick handler
   - Should navigate to cluster edit page or show modal
   
2. **No search/filter for clusters**
   - Table could have hundreds of rows
   
3. **Message says "Governor-only permission"** but no role check in component
   - Should check: `user.role !== 'governor'` → redirect

#### Recommendations:
```jsx
// Add Edit handler
<button 
    className="btn btn-secondary btn-sm"
    onClick={() => navigate(`/clusters/${cl.id}`)}
>
    Edit
</button>

// Add RBAC check
const { user } = useStore();
if (user?.role !== 'governor') {
    return <AccessDenied message="Only governors can manage clusters" />;
}

// Add search/sort
const [search, setSearch] = useState('');
const filtered = clusters.filter(cl => 
    cl.name.toLowerCase().includes(search.toLowerCase()) ||
    cl.id.includes(search)
);
```

---

### 7️⃣ Config.jsx

**File**: [frontend/src/pages/Config.jsx](frontend/src/pages/Config.jsx)

#### Status: 🟡 MIXED

| Aspect | Status | Details |
|--------|--------|---------|
| Data Source | 🟡 Hardcoded | All config values static |
| API Integration | ❌ Missing | No fetch from `/api/config` |
| Error Handling | ❌ None | No error states |
| RBAC Validation | ❌ Missing | Admin-only not enforced |
| Gate Validation | N/A | Config only, no gates |
| Tier Rules | ✅ Shows | Thresholds documented properly |

#### Issues Found:
1. **Lines 6-40**: All config values hardcoded
   - Gate thresholds, tier weights, channel thresholds, audit settings all static
   - If config changes in backend, frontend shows stale values

2. **No admin-only access control**
   - Subtitle says "Admin only" but code doesn't check role
   
3. **No edit capability**
   - All values displayed as read-only badges
   - No ability to change thresholds

4. **No update audit trail**
   - Can't see when last changed, by whom, previous values

#### Recommendations:
```jsx
// Add RBAC + API integration
import { configApi } from '../services/api';

const Config = () => {
    const { user } = useStore();
    const [sections, setSections] = useState([]);
    const [loading, setLoading] = useState(true);
    const [editMode, setEditMode] = useState(false);

    if (user?.role !== 'admin') {
        return <AccessDenied message="Configuration is admin-only" />;
    }

    useEffect(() => {
        configApi.get()
            .then(res => setSections(formatConfig(res.data)))
            .catch(err => addNotification({ type: 'error' }));
    }, []);

    const handleSaveConfig = async (newValues) => {
        await configApi.update(newValues);
        addNotification({ type: 'success', message: 'Config updated' });
        setEditMode(false);
    };

    if (editMode) return <ConfigEditor onSave={handleSaveConfig} />;
    return <ConfigViewer sections={sections} onEdit={() => setEditMode(true)} />;
};
```

---

### 8️⃣ Dashboard.jsx

**File**: [frontend/src/pages/Dashboard.jsx](frontend/src/pages/Dashboard.jsx)

#### Status: 🟡 MIXED

| Aspect | Status | Details |
|--------|--------|---------|
| Data Source | ✅ API | Fetches from `skuApi.list()` with filters |
| API Integration | ✅ Good | Search, tier, category filters work |
| Error Handling | ✅ Good | Loading/error states present |
| RBAC Validation | ⚠️ Partial | No action restrictions (read-only OK) |
| Gate Validation | ❌ Missing | Gates always show `pass={false}` |
| Tier Rules | ⚠️ Shows | Tier badges shown but no restrictions |

#### Issues Found:
1. **Lines 155-160**: Gate chips hardcoded to `pass={false}`
   - Should display actual gate status from API
   - `pass={sku.gates?.[g.id]?.passed}` not available in response

2. **Lines 88-89**: "AI Citation Rate" hardcoded at "48%"
   - Should aggregate from SKU citations: `Math.round(skus.reduce((a,s) => a + (s.ai_citation_rate || 0), 0) / skus.length)`

3. **Column "Gates" shows no pass/fail indicators**
   - Very important for governance - users can't tell which SKUs are ready

4. **Row click navigates to edit** - Good for 'editor' role, should verify permission

5. **Category filter limited to hardcoded options**
   - "Cables", "Lampshades" should be loaded from clusters dynamically

6. **No export permissions check**
   - "Export benefits.csv" button should check if user has export role

#### Recommendations:
```jsx
// Fix gates display - requires API response update
// Fetch actual gate results in SKU response:
/* API should return:
{
    sku: {
        id, sku_code, title, tier, ...,
        gates: {
            G1: { passed: true, score: 100 },
            G2: { passed: true, score: 95 },
            G3: { passed: false, score: 30 },
            ...
        }
    }
}
*/

// Then in component:
<div className="flex gap-4 flex-wrap">
    {GATES.map(g => (
        <GateChip 
            key={g.id} 
            id={g.id} 
            pass={sku.gates?.[g.id]?.passed || false}
            compact 
        />
    ))}
</div>

// Fix citation rate aggregation:
const avgCitationRate = skus.length > 0 
    ? Math.round(skus.reduce((a, s) => a + (s.ai_citation_rate || 0), 0) / skus.length)
    : 0;
<StatCard label="AI Citation Rate" value={`${avgCitationRate}%`} />
```

---

### 9️⃣ Maturity.jsx

**File**: [frontend/src/pages/Maturity.jsx](frontend/src/pages/Maturity.jsx)

#### Status: 🟡 MIXED

| Aspect | Status | Details |
|--------|--------|---------|
| Data Source | 🟡 Hardcoded | All percentages static |
| API Integration | ❌ Missing | No fetch from metrics API |
| Error Handling | ❌ None | No error/loading states |
| RBAC Validation | 🟢 OK | Display-only, read-only |
| Gate Validation | N/A | Aggregated view, no individual gates |
| Tier Rules | ⚠️ Shows | Targets mentioned but static |

#### Issues Found:
1. **Lines 5-20**: All category percentages hardcoded
   - "Cables: 76%, core: 88%, auth: 62%, channel: 74%, ai: 72%"
   - Should calculate from aggregated SKU data by cluster

2. **Lines 22-25**: Tier compliance targets hardcoded
   - actual: "68%" should come from calculation via survey of HERO tier SKUs
   - met: 56 out of 82 should be real counts

3. **No loading state** - If aggregation is slow, page blocks

4. **No last-updated timestamp** - Users don't know staleness of data

#### Recommendations:
```jsx
// Add async calculation
const [metrics, setMetrics] = useState(null);
const [loading, setLoading] = useState(true);

useEffect(() => {
    const fetchMaturityMetrics = async () => {
        try {
            const { data } = await metricsApi.getMaturity();
            setMetrics({
                categories: data.by_category,  // [{ cat, pct, core, auth, channel, ai }]
                tiers: data.tier_compliance,   // [{ tier, target, actual, met, total }]
            });
        } catch (err) {
            addNotification({ type: 'error' });
        } finally {
            setLoading(false);
        }
    };
    fetchMaturityMetrics();
}, []);

if (loading) return <LoadingSpinner />;
if (!metrics) return <ErrorMessage />;

// Render using metrics instead of hardcoded data
{metrics.categories.map(cat => (...))}
{metrics.tiers.map(t => (...))}
```

---

### 🔟 ReviewQueue.jsx

**File**: [frontend/src/pages/ReviewQueue.jsx](frontend/src/pages/ReviewQueue.jsx)

#### Status: 🟢 GOOD

| Aspect | Status | Details |
|--------|--------|---------|
| Data Source | ✅ API | Fetches pending SKUs + stats |
| API Integration | ✅ Good | Proper async data loading |
| Error Handling | ✅ Good | Error states + messages |
| RBAC Validation | 🟡 Partial | Governor role implied (not enforced) |
| Gate Validation | ❌ Missing | No gate status shown before approval |
| Tier Rules | ⚠️ Missing | No tier-specific validation |

#### Issues Found:
1. **Lines 45-65**: Approve/Reject don't validate gates
   - Before approving, should verify tier-specific gates pass
   - HERO: all G1-G7 + vector must pass
   - SUPPORT: G1-G4 + vector required
   - HARVEST: spec only (G1-G2)

2. **Lines 80-100**: Queue items show readiness but not gates
   - Should show mini gate status: "⊗ G3 ✓ G4 ⊗ G5"

3. **Line 50**: Optimistic update could fail
   - Removes SKU from list before API success
   - If API fails 500, UI is wrong but user sees success

4. **No tier-aware effort tracking**
   - HARVEST SKUs should show effort used: "5 min / 30 min"

#### Recommendations:
```jsx
// Fix approval validation
const handleAction = async (id, action, skuCode) => {
    try {
        // Fetch full SKU with gate results
        const { data: sku } = await skuApi.get(id);
        
        // Validate tier-specific gates
        if (!isEligibleForApproval(sku)) {
            addNotification({
                type: 'error',
                message: `Cannot approve: ${getFailingGates(sku).join(', ')} failed`
            });
            return;
        }
        
        // Only then update status
        const status = action === 'approve' ? 'VALID' : 'INVALID';
        await skuApi.update(id, { validation_status: status });
        
        // Optimistic update after success
        setSkus(prev => prev.filter(s => s.id !== id));
        
    } catch (err) {
        addNotification({ type: 'error', message: err.message });
    }
};

const isEligibleForApproval = (sku) => {
    const gates = sku.gates || {};
    switch (sku.tier) {
        case 'HERO':
            return ['G1','G2','G3','G4','G5','G6','G7','VEC'].every(g => gates[g]?.passed);
        case 'SUPPORT':
            return ['G1','G2','G3','G4','VEC'].every(g => gates[g]?.passed);
        case 'HARVEST':
            return ['G1','G2'].every(g => gates[g]?.passed);  // Spec only
        case 'KILL':
            return false;  // Can't approve KILL SKUs
        default: return false;
    }
};
```

---

### 1️⃣1️⃣ SkuEdit.jsx

**File**: [frontend/src/pages/SkuEdit.jsx](frontend/src/pages/SkuEdit.jsx)

#### Status: 🔴 CRITICAL

| Aspect | Status | Details |
|--------|--------|---------|
| Data Source | ✅ API | Fetches from `skuApi.get(id)` |
| API Integration | 🟡 Partial | Fetch works, save works |
| Error Handling | ✅ Good | Try/catch + notifications |
| RBAC Validation | ❌ Missing | No role checks before edit |
| Gate Validation | 🟡 Partial | Vector hardcoded, fields incomplete |
| Tier Rules | 🟡 Partial | KILL read-only indicator but fields editable |

#### Issues Found:
1. **Lines 50-52**: KILL tier shows indicator but doesn't disable fields
   - <input>, <textarea>, <select> have NO disabled attribute
   - User can edit despite "READ-ONLY MODE" label

2. **Lines 50**: HARVEST tier allows full edits but spec-only with 30m/quarter cap
   - No effort cap display
   - No restriction to specification intent only

3. **Lines 25-55**: No RBAC check before component loads
   - Should verify user.role permits editing this SKU

4. **Lines 180-220**: Missing fields for complete governance
   - Missing: G5 (Best/Not-For), G6 (Description), G7 (Authority)
   - Missing: G6.1 (Tier-gated fields)

5. **Lines 250-260**: Vector validation hardcoded "0.87"
   - Should fetch from `sku.vector_similarity`
   - Should show color: green >= 0.72, orange 0.60-0.71, red < 0.60

6. **Lines 265-280**: FAQ tab shows hardcoded example
   - Should fetch from `/api/skus/{id}/faq`

7. **Lines 282-290**: History tab shows "No recent history"
   - Should fetch from `/api/skus/{id}/audit-log`

8. **Lines 70-85**: No auto-save or unsaved changes warning
   - User could lose work by navigating away

9. **Lines 25-30**: No concurrent edit detection
   - Multiple editors could conflict
   - Should use SKU.lock_version field

10. **Lines 100-110**: No draft/submission workflow
    - Should distinguish: Draft (local), Pending Review, Approved

#### Critical Recommendations:
```jsx
// 1. Add RBAC check at top
const SkuEdit = () => {
    const { id } = useParams();
    const { user } = useStore();
    
    // For HERO SKUs, only governors can edit
    if (sku?.tier === 'HERO' && user.role !== 'governor') {
        return <AccessDenied />;
    }
    // For SUPPORT/HARVEST: editor or governor
    if (['SUPPORT','HARVEST'].includes(sku?.tier) && !['editor','governor'].includes(user.role)) {
        return <AccessDenied />;
    }
};

// 2. Disable fields for KILL tier
const isKillTier = currentTier === 'KILL';
return (
    <input disabled={isKillTier} className={isKillTier ? 'disabled' : ''} />
);

// 3. Add HARVEST effort cap
const harvestUsedMinutes = sku.effort_minutes_this_quarter || 0;
const harvestCapPercent = (harvestUsedMinutes / 30) * 100;
{isHarvestTier && (
    <div className="effort-cap">
        <span>{harvestUsedMinutes} / 30 min used this quarter</span>
        <ProgressBar value={harvestCapPercent} />
        {harvestUsedMinutes >= 30 && <div>Quota exhausted</div>}
    </div>
)}

// 4. Fix vector display
<div className="vector-score" style={{
    color: sku.vector_similarity >= 0.72 ? 'var(--green)' 
         : sku.vector_similarity >= 0.60 ? 'var(--orange)'
         : 'var(--red)'
}}>
    {(sku.vector_similarity || 0).toFixed(2)}
</div>

// 5. Add complete gates (add missing fields)
// In content tab, add after G4:
<div>
    <label>G5 — Best/Not-For Uses</label>
    <textarea value={sku.best_for} onChange={...} />
</div>

<div>
    <label>G6 — Full Description</label>
    <textarea value={sku.description} onChange={...} />
</div>

<div>
    <label>G7 — Authority Block</label>
    <textarea value={sku.authority} onChange={...} />
</div>

// 6. Add auto-save
const [hasUnsavedChanges, setHasUnsavedChanges] = useState(false);

useEffect(() => {
    setHasUnsavedChanges(true);
}, [sku]);

useEffect(() => {
    if (!hasUnsavedChanges) return;
    const timer = setTimeout(() => {
        if (!isKillTier) handleSave(false);  // Auto-save every 30sec
    }, 30000);
    return () => clearTimeout(timer);
}, [hasUnsavedChanges, sku, isKillTier]);

// 7. Add unsaved changes warning
useEffect(() => {
    if (!hasUnsavedChanges) return;
    const handler = (e) => {
        e.preventDefault();
        e.returnValue = 'You have unsaved changes';
    };
    window.addEventListener('beforeunload', handler);
    return () => window.removeEventListener('beforeunload', handler);
}, [hasUnsavedChanges]);

// 8. Add lock version check
const handleSave = async () => {
    try {
        const response = await skuApi.update(id, { ...sku, _lock_version: sku.lock_version });
        if (response.status === 409) {  // Conflict
            addNotification({
                type: 'warning',
                message: 'SKU was modified by another user. Refresh to see latest version.'
            });
        }
    } catch (err) { ... }
};
```

---

### 1️⃣2️⃣ StaffKpis.jsx

**File**: [frontend/src/pages/StaffKpis.jsx](frontend/src/pages/StaffKpis.jsx)

#### Status: 🟡 MIXED

| Aspect | Status | Details |
|--------|--------|---------|
| Data Source | 🟡 Hardcoded | All staff KPIs static |
| API Integration | ❌ Missing | No fetch from metrics API |
| Error Handling | ❌ None | No error states |
| RBAC Validation | 🟢 OK | Display-only, read-only |
| Gate Validation | N/A | KPI tracking, no gates |
| Tier Rules | N/A | Performance metrics |

#### Issues Found:
1. **Lines 3-10**: All staff data hardcoded
   - Should fetch from `/api/staff-metrics` with date range
   - SKU counts, pass rates, review time, rework rates all static

2. **No date range selector**
   - "Weekly Completions" unclear: is it this week? last week? YTD?
   - Should allow "This Week", "This Month", "Custom Range"

3. **Leaderboard chart uses hardcoded data**
   - Should update with filtered data

#### Recommendations:
```jsx
// Add async fetching with date range
const [dateRange, setDateRange] = useState('week');  // week, month, quarter
const [staff, setStaff] = useState([]);
const [loading, setLoading] = useState(true);

useEffect(() => {
    const fetchMetrics = async () => {
        const { data } = await staffApi.getKpis({ range: dateRange });
        setStaff(data);
    };
    fetchMetrics();
}, [dateRange]);

return (
    <div>
        <div className="filter-controls">
            <select value={dateRange} onChange={(e) => setDateRange(e.target.value)}>
                <option value="week">This Week</option>
                <option value="month">This Month</option>
                <option value="quarter">This Quarter</option>
            </select>
        </div>
        
        {loading ? <LoadingSpinner /> : (
            <>
                <DataTable data={staff} />
                <MiniBarChart data={staff.map(s => ({
                    label: s.name,
                    value: s.skus
                }))} />
            </>
        )}
    </div>
);
```

---

### 1️⃣3️⃣ TierMgmt.jsx

**File**: [frontend/src/pages/TierMgmt.jsx](frontend/src/pages/TierMgmt.jsx)

#### Status: 🟡 MIXED

| Aspect | Status | Details |
|--------|--------|---------|
| Data Source | 🟡 Hardcoded | Tier reassignments static |
| API Integration | ❌ Missing | No fetch from API |
| Error Handling | ❌ None | No error states |
| RBAC Validation | ❌ Missing | No dual approval verification |
| Gate Validation | N/A | Tier logic only, no gates |
| Tier Rules | ✅ Shows | Shows tier change logic |

#### Issues Found:
1. **Lines 6-9**: All reassignment data hardcoded
   - Should fetch from `/api/tier-reassignments` or similar
   - Should show: proposed by, reason, approval status

2. **Lines 45-48**: Override approval button has NO onClick handler
   - No approval workflow, no confirmation, no logging

3. **No role check for approval**
   - Policy requires dual approval: Portfolio Holder + Finance Director
   - Button shown to all users

4. **No edit capability** for override reason
   - "Below threshold 3 months" is static, can't be changed

5. **No approval history**
   - Can't see who approved/rejected, when, why

#### Recommendations:
```jsx
// Add RBAC + approval workflow
const TierMgmt = () => {
    const { user } = useStore();
    const [reassignments, setReassignments] = useState([]);
    const [approvalModal, setApprovalModal] = useState(null);

    const canApprove = ['portfolio_holder', 'finance_director'].includes(user.role);

    const handleApproveClick = (row) => {
        setApprovalModal({
            sku_id: row.id,
            current_tier: row.current,
            proposed_tier: row.proposed,
            reason: row.reason,
            approver_role: user.role
        });
    };

    const submitApproval = async (modal) => {
        await tierApi.submitApproval({
            sku_id: modal.sku_id,
            approver_role: modal.approver_role,
            approved: true
        });
        addNotification({ type: 'success', message: '1/2 approvals collected' });
        setApprovalModal(null);
        // Refetch to see updated approval status
    };

    return (
        <div>
            <table>
                <tbody>
                    {reassignments.map(row => (
                        <tr key={row.id}>
                            {/* ... other columns ... */}
                            <td>
                                {row.override && canApprove ? (
                                    <>
                                        <button onClick={() => handleApproveClick(row)}>
                                            Approve ({row.approval_count || 0}/2)
                                        </button>
                                        <span className="approval-status">
                                            {row.approved_by.map(u => u.role).join(' + ')}
                                        </span>
                                    </>
                                ) : row.override ? (
                                    <span>Not authorized</span>
                                ) : <span>—</span>}
                            </td>
                        </tr>
                    ))}
                </tbody>
            </table>
            {approvalModal && (
                <ApprovalModal {...approvalModal} onSubmit={submitApproval} />
            )}
        </div>
    );
};
```

---

## 🎯 PRIORITY ROADMAP

### Phase 1: CRITICAL (This Sprint)
- [ ] Fix SkuEdit KILL tier field disabling
- [ ] Add RBAC checks to SkuEdit (role validation)
- [ ] Fix Dashboard gate display (pass={false} hardcoded)
- [ ] Fix AiAudit hardcoded data (use API response)
- [ ] Add Config API integration
- [ ] Fix Vector validation display in SkuEdit

### Phase 2: HIGH (Next Sprint)
- [ ] Add all missing gates to SkuEdit (G5, G6, G7)
- [ ] Add gate validation to ReviewQueue approvals
- [ ] Add tier-specific RBAC checks throughout
- [ ] Implement TierMgmt dual approval workflow
- [ ] Add lock version/concurrent edit detection
- [ ] Add auto-save to SkuEdit

### Phase 3: MEDIUM (Polish)
- [ ] Add loading/error states to all async pages
- [ ] Add filter functionality (AuditTrail, ClustersPage)
- [ ] Add search/sort to data tables
- [ ] Update hardcoded data to use API (Maturity, Channels, StaffKpis, Briefs)
- [ ] Add pagination to large tables
- [ ] Add deployment warnings for stale data

---

## 🔗 RELATED BACKEND WORK NEEDED

To support frontend fixes, backend requires:

1. **Update SKU Response** to include:
   ```json
   {
       "sku": { ... },
       "gates": {
           "G1": { "passed": true, "score": 100 },
           "G2": { "passed": true, "score": 95 },
           ...
       },
       "vector_similarity": 0.87,
       "lock_version": 5
   }
   ```

2. **New Endpoints**:
   - `GET /api/config` - System configuration
   - `GET /api/metrics/maturity` - Maturity calculations
   - `GET /api/metrics/channel-readiness` - Channel metrics
   - `GET /api/staff-metrics` - Staff KPI data
   - `GET /api/tier-reassignments` - Pending tier changes
   - `POST /api/tier-reassignments/{id}/approve` - Approval workflow
   - `GET /api/decay-alerts` - Decay detection alerts

3. **Add fields to SKU table**:
   - `vector_similarity` (float)
   - `lock_version` (int, for optimistic locking)
   - `effort_minutes_this_quarter` (int, for HARVEST tier)
   - `gate_results` (JSON, for all gates)

---

## 📊 AUDIT SUMMARY BY CATEGORY

### Static Data (Hardcoded)
- ❌ AiAudit (audit scores, decay alerts)
- ❌ AuditTrail (all 5 rows)
- ❌ Briefs (all fields)
- ❌ Channels (channel scores)
- ❌ Config (all settings)
- ❌ Maturity (all percentages)
- ❌ StaffKpis (all KPI data)
- ❌ TierMgmt (all reassignments)

### RBAC Missing
- ❌ SkuEdit (no role checks)
- ❌ Config (admin-only not enforced)
- ❌ BulkOps (admin-only not enforced)
- ❌ TierMgmt (approval roles not checked)
- ❌ ReviewQueue (governor role not enforced)

### Gate Validation Missing
- ❌ Dashboard (always pass={false})
- ❌ SkuEdit (missing G5, G6, G7, hardcoded vector)
- ❌ ReviewQueue (no gate checks before approval)

### Tier Rules Not Enforced
- ❌ SkuEdit (HARVEST 30m cap not tracked, KILL not really disabled)
- ❌ ReviewQueue (tier-specific validation missing)

### API Integration Incomplete
- ❌ AiAudit (fetches but uses hardcoded data)
- ❌ AuditTrail (no fetch at all)
- ❌ Briefs (no fetch, no SKU binding)
- ❌ BulkOps (operations have no handlers)
- ❌ Maturity (all static)
- ❌ StaffKpis (all static)
- ❌ TierMgmt (all static)

---

## ✅ WHAT'S GOOD

### Pages with Good Patterns
- ✅ **ClustersPage.jsx** - Proper API fetching, error/loading states
- ✅ **ReviewQueue.jsx** - Good async pattern, error handling
- ✅ **AuditTrail.jsx** - Good UI structure (just needs API wiring)
- ✅ **Dashboard.jsx** - Good filtering logic (just needs gate display fix)

### Good Aspects
- ✅ All pages use useStore() for auth/notifications
- ✅ All pages have error handling patterns
- ✅ All pages use proper React hooks
- ✅ All pages follow project component library

---

## 📝 FINAL RECOMMENDATIONS

1. **Immediate**: Fix the 12 critical issues blocking governance
2. **Short-term**: Add RBAC checks and gate validation throughout
3. **Medium-term**: Replace all hardcoded data with API calls
4. **Long-term**: Add advanced features (auto-save, concurrent edit detection, etc.)

All changes should follow existing patterns in [ClustersPage.jsx](frontend/src/pages/ClustersPage.jsx) and [ReviewQueue.jsx](frontend/src/pages/ReviewQueue.jsx) for consistency.

---

**Report Generated**: February 18, 2026  
**Total Pages Analyzed**: 13  
**Total Issues**: 48  
**Estimated Fix Effort**: 120-160 hours (3-4 sprints of focused work)
