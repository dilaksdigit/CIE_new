// SOURCE: CIE_v232_Developer_Amendment_Pack_v2.docx Sections 4.2 & 5; openapi validate + content flow
import axios from 'axios';

const api = axios.create({
    baseURL: import.meta.env.VITE_API_URL || '/api',
    headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
    }
});

// Attach auth token to every request (sessionStorage)
api.interceptors.request.use((config) => {
    const token = sessionStorage.getItem('cie_token');
    if (token) {
        config.headers.Authorization = `Bearer ${token}`;
    }
    return config;
});

// Handle 401 responses globally
api.interceptors.response.use(
    (response) => response,
    (error) => {
        if (error.response?.status === 401) {
            sessionStorage.removeItem('cie_token');
            sessionStorage.removeItem('cie_user');
            window.location.href = '/login';
        }
        return Promise.reject(error);
    }
);

// ====== Auth ======
export const authApi = {
    login: (email, password) => api.post('/v1/auth/login', { email: email.trim(), password }),
    register: (name, email, password, password_confirmation, role) => api.post('/v1/auth/register', {
        name,
        email,
        password,
        password_confirmation,
        role
    }),
};

// ====== Writer Queue ======
export const queueApi = {
    today: () => api.get('/v1/queue/today'),
};

// ====== Writer Edit (Phase 4) ======
export const writerEditApi = {
    get: (skuId) => api.get(`/v1/sku/${skuId}`),
    validate: (skuId, data) => api.post(`/v1/sku/${skuId}/validate`, data),
    publish: (skuId, data) => api.put(`/v1/sku/${skuId}/content`, data),
};

/**
 * Publish SKU: validate then persist content (openapi flow).
 * Step 1: POST /v1/sku/{skuId}/validate with content payload.
 * Step 2: If all gates pass (200), PUT /v1/sku/{skuId}/content to persist content.
 */
export async function publishSku(skuId, contentPayload) {
    await api.post(`/v1/sku/${skuId}/validate`, {
        sku_id: skuId,
        action: 'publish',
        ...contentPayload,
    });
    await api.put(`/v1/sku/${skuId}/content`, contentPayload);
    return { ok: true };
}

// ====== SKUs ======
export const skuApi = {
    list: (params) => api.get('/v1/sku', { params }),
    get: (id) => api.get(`/v1/sku/${id}`),
    create: (data) => api.post('/v1/sku', data),
    update: (id, data) => api.put(`/v1/sku/${id}/content`, data),
    validate: (id) => api.post(`/v1/sku/${id}/validate`),
    stats: () => api.get('/v1/sku/stats'),
    faqSuggestions: (id) => api.get(`/v1/sku/${id}/faq-suggestions`),
    getRollbackContent: (id) => api.get(`/v1/sku/${id}/rollback-content`),
    publish: (id) => api.post(`/v1/sku/${id}/publish`),
};

// ====== Clusters ======
export const clusterApi = {
    list: (params) => api.get('/v1/clusters', { params }),
    create: (data) => api.post('/v1/clusters', data),
    update: (id, data) => api.put(`/v1/clusters/${id}`, data),
};

// ====== Tiers ======
export const tierApi = {
    recalculate: () => api.post('/v1/tiers/recalculate'),
};

// ====== Audit ======
export const auditApi = {
    run: (category) => api.post('/v1/audit/run', { category }),
};

// ====== Briefs ======
export const briefApi = {
    list: (params) => api.get('/v1/briefs', { params }),
    create: (data) => api.post('/v1/brief/generate', data),
};

// ====== Taxonomy (Unified API 7.1) ======
/** GET /api/v1/taxonomy/intents?tier=X — returns allowed intent enums for that tier */
export const taxonomyApi = {
    getIntents: (tier) => api.get('/v1/taxonomy/intents', { params: tier ? { tier: tier.toLowerCase() } : {} }),
};

// ====== Config ======
export const configApi = {
    get: () => api.get('/v1/config'),
    update: (data) => api.put('/v1/config', data),
};

// ====== Admin Business Rules (Phase 0 Check 0.1) ======
export const businessRulesApi = {
    list: (params) => api.get('/v1/admin/business-rules', { params }),
    update: (key, value) => api.put(`/v1/admin/business-rules/${encodeURIComponent(key)}`, { value }),
    approve: (key) => api.post(`/v1/admin/business-rules/${encodeURIComponent(key)}/approve`),
    getAudit: () => api.get('/v1/admin/business-rules/audit'),
};

// ====== Semrush Import (Admin) ======
export const semrushImportApi = {
    importFile: (file) => {
        const formData = new FormData();
        formData.append('file', file);
        return api.post('/admin/semrush-import', formData, {
            headers: {
                'Content-Type': 'multipart/form-data',
            },
        });
    },
    latest: (params) => api.get('/v1/admin/semrush-import/latest', { params: params || {} }),
    deleteBatch: (batchDate) => api.delete(`/v1/admin/semrush-import/${encodeURIComponent(batchDate)}`),
};

// ====== Dashboard (S4 Maturity, Decay, Effort, Staff KPIs) ======
export const dashboardApi = {
    getSummary: () => api.get('/v1/dashboard/summary'),
    getDecayAlerts: () => api.get('/v1/dashboard/decay-alerts'),
    getChannelStats: () => api.get('/v1/dashboard/channel-stats'),
};

// ====== Audit Results ======
// SOURCE: openapi.yaml /audit-results/weekly-scores GET (weeks param default 12)
export const auditResultApi = {
    getBySkuId: (skuId) => api.get(`/v1/sku/${skuId}/audit-results`),
    getDecayAlerts: () => api.get('/v1/dashboard/decay-alerts'),
    getWeeklyScores: () => api.get('/v1/audit-results/weekly-scores', { params: { weeks: 12 } }),
    saveWeeklyScore: (payload) => api.post('/v1/audit-results/weekly-scores', payload),
};

// ====== Audit Log (immutable trail) ======
export const auditLogApi = {
    getLogs: (params) => api.get('/v1/audit-logs', { params }),
};

// ====== Shopify product pull (admin) ======
export const shopifyApi = {
    status: () => api.get('/v1/shopify/status'),
    getProducts: (params) => api.get('/v1/shopify/products', { params: params || {} }),
    sync: () => api.post('/v1/shopify/sync'),
};

// ====== FAQ templates (for Bulk Ops FAQ apply) ======
export const faqApi = {
    getTemplates: (params) => api.get('/v1/faq/templates', { params: params || {} }),
};

// ====== ERP Sync (Admin) — manual trigger ======
export const erpSyncApi = {
    sync: (payload) => api.post('/admin/erp-sync', payload),
};

// ====== Bulk Ops (Admin) — zero hardcode; summary + execution from API ======
export const bulkOpsApi = {
    getSummary: () => api.get('/v1/admin/bulk-ops/summary'),
    listTierChangeRequests: (params) => api.get('/v1/admin/bulk-ops/tier-change-requests', { params: params || {} }),
    clusterAssignment: (payload) => api.post('/v1/admin/bulk-ops/cluster-assignment', payload),
    statusChange: (payload) => api.post('/v1/admin/bulk-ops/status-change', payload),
    faqApply: (payload) => api.post('/v1/admin/bulk-ops/faq-apply', payload),
    exportCsv: () => api.get('/v1/admin/bulk-ops/export', { params: { format: 'csv' }, responseType: 'blob' }),
};

export default api;
