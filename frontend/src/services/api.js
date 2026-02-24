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
    login: (email, password) => api.post('/auth/login', { email: email.trim(), password }),
    register: (name, email, password, password_confirmation, role) => api.post('/auth/register', {
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
    get: (skuId) => api.get(`/v1/skus/${skuId}`),
    validate: (skuId, data) => api.post(`/v1/skus/${skuId}/validate`, data),
    publish: (skuId) => api.put(`/v1/skus/${skuId}`, { validation_status: 'VALID' }),
};

// ====== SKUs ======
export const skuApi = {
    list: (params) => api.get('/skus', { params }),
    get: (id) => api.get(`/skus/${id}`),
    create: (data) => api.post('/skus', data),
    update: (id, data) => api.put(`/skus/${id}`, data),
    validate: (id) => api.post(`/skus/${id}/validate`),
    stats: () => api.get('/skus/stats'),
  faqSuggestions: (id) => api.get(`/skus/${id}/faq-suggestions`),
};

// ====== Clusters ======
export const clusterApi = {
    list: (params) => api.get('/clusters', { params }),
    create: (data) => api.post('/clusters', data),
    update: (id, data) => api.put(`/clusters/${id}`, data),
};

// ====== Tiers ======
export const tierApi = {
    recalculate: () => api.post('/tiers/recalculate'),
};

// ====== Audit ======
export const auditApi = {
    run: (skuId) => api.post(`/audit/${skuId}`),
};

// ====== Briefs ======
export const briefApi = {
    list: (params) => api.get('/briefs', { params }),
    create: (data) => api.post('/briefs', data),
};

// ====== Taxonomy (Unified API 7.1) ======
/** GET /api/taxonomy/intents?tier=X — returns allowed intent enums for that tier */
export const taxonomyApi = {
    getIntents: (tier) => api.get('/taxonomy/intents', { params: tier ? { tier: tier.toLowerCase() } : {} }),
};

// ====== Config ======
export const configApi = {
    get: () => api.get('/config'),
    update: (data) => api.put('/config', data),
};

// ====== Dashboard (S4 Maturity, Decay, Effort, Staff KPIs) ======
export const dashboardApi = {
    getSummary: () => api.get('/dashboard/summary'),
    getDecayAlerts: () => api.get('/dashboard/decay-alerts'),
};

// ====== Audit Results ======
export const auditResultApi = {
    getBySkuId: (skuId) => api.get(`/skus/${skuId}/audit-results`),
    getDecayAlerts: () => api.get('/dashboard/decay-alerts'),
    getWeeklyScores: () => api.get('/audit-results/weekly-scores'),
    saveWeeklyScore: (data) => api.post('/audit-results/weekly-scores', data),
};

// ====== Audit Log (immutable trail) ======
export const auditLogApi = {
    getLogs: (params) => api.get('/audit-logs', { params }),
};

export default api;
