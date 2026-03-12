// SOURCE: CIE_v232_Hardening_Addendum.pdf Patch 4

import React, { useEffect, useState } from 'react';
import api from '../../services/api';

/**
 * FAQ tab: loads templates for the SKU's cluster/intent, renders one textarea per template,
 * saves via POST /sku/{id}/faq.
 * Props: { sku }
 */
function FAQTab({ sku }) {
    const [templates, setTemplates] = useState([]);
    const [answers, setAnswers] = useState({});
    const [loading, setLoading] = useState(true);
    const [saveStatus, setSaveStatus] = useState(null);
    const [saving, setSaving] = useState(false);

    const clusterId = sku?.primary_cluster_id ?? sku?.cluster_id ?? sku?.primaryCluster?.id ?? '';
    const intentKey = sku?.intent_key ?? sku?.primary_intent?.key ?? sku?.primaryIntent?.key ?? '';

    useEffect(() => {
        if (!sku?.id) return;
        setLoading(true);
        setSaveStatus(null);
        const params = {};
        if (clusterId != null && clusterId !== '') params.cluster_id = clusterId;
        if (intentKey != null && intentKey !== '') params.intent_key = intentKey;
        api.get('/v1/faq/templates', { params })
            .then((res) => {
                const list = Array.isArray(res.data) ? res.data : res.data?.data ?? [];
                setTemplates(list);
                const initial = {};
                list.forEach((t) => {
                    initial[t.id] = '';
                });
                setAnswers(initial);
            })
            .catch(() => {
                setTemplates([]);
                setSaveStatus({ error: 'Failed to load FAQ templates.' });
            })
            .finally(() => setLoading(false));
    }, [sku?.id, clusterId, intentKey]);

    const handleAnswerChange = (templateId, value) => {
        setAnswers((prev) => ({ ...prev, [templateId]: value }));
        setSaveStatus(null);
    };

    const handleSave = () => {
        if (!sku?.id) return;
        const responses = templates.map((t) => ({
            template_id: t.id,
            answer: answers[t.id] ?? '',
        }));
        if (responses.length === 0) {
            setSaveStatus({ error: 'No templates to save.' });
            return;
        }
        setSaving(true);
        setSaveStatus(null);
        api.post(`/v1/sku/${sku.id}/faq`, { responses })
            .then(() => {
                setSaveStatus({ success: true });
            })
            .catch((err) => {
                const msg = err.response?.data?.message || err.message || 'Failed to save FAQ answers.';
                setSaveStatus({ error: msg });
            })
            .finally(() => setSaving(false));
    };

    if (loading) {
        return <div style={{ padding: 12, color: 'var(--text-mid, #666)' }}>Loading FAQ templates…</div>;
    }

    if (templates.length === 0 && !saveStatus?.error) {
        return <div style={{ padding: 12, color: 'var(--text-mid, #666)' }}>No FAQ templates for this cluster/intent.</div>;
    }

    return (
        <div style={{ padding: 12 }}>
            {templates.map((t) => (
                <div key={t.id} style={{ marginBottom: 16 }}>
                    <label style={{ display: 'block', fontSize: '0.8rem', fontWeight: 600, marginBottom: 4 }}>
                        {t.question}
                        {t.is_required ? <span style={{ color: 'var(--red, #c62828)' }}> *</span> : null}
                    </label>
                    <textarea
                        value={answers[t.id] ?? ''}
                        onChange={(e) => handleAnswerChange(t.id, e.target.value)}
                        rows={3}
                        required={!!t.is_required}
                        style={{ width: '100%', padding: 8, fontSize: '0.85rem', border: '1px solid #ccc', borderRadius: 4 }}
                    />
                </div>
            ))}
            <button
                type="button"
                onClick={handleSave}
                disabled={saving}
                className="btn btn-primary"
                style={{ marginTop: 8 }}
            >
                {saving ? 'Saving…' : 'Save FAQ Answers'}
            </button>
            {saveStatus?.success && (
                <div style={{ marginTop: 8, color: 'var(--green, #2e7d32)', fontSize: '0.85rem' }}>
                    FAQ answers saved successfully.
                </div>
            )}
            {saveStatus?.error && (
                <div style={{ marginTop: 8, color: 'var(--red, #c62828)', fontSize: '0.85rem' }}>
                    {saveStatus.error}
                </div>
            )}
        </div>
    );
}

export default FAQTab;
