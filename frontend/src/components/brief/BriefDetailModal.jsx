import React from 'react';

// SOURCE: CIE_v232_UI_Restructure_Instructions.docx §3 — AI Suggestions panel exactly 4 card types
const SUGGESTION_TYPES = [
    { key: 'keyword', label: 'Keyword Opportunity', iconColor: 'var(--green)', sourceTag: 'Semrush' },
    { key: 'citation', label: 'AI Visibility Issue', iconColor: 'var(--red)', sourceTag: 'AI Audit' },
    { key: 'trend', label: 'Trending Search', iconColor: 'var(--blue)', sourceTag: 'Google Analytics' },
    { key: 'competitor', label: 'Competitor Gap', iconColor: 'var(--amber)', sourceTag: 'AI Audit + Semrush' },
];

const PRIORITY_BADGE = { high: 'HIGH', medium: 'MED', low: 'LOW' };

const BriefDetailModal = ({ open = true, onClose, suggestions = [], skuId = null }) => {
    if (open === false) return null;

    const dismissSuggestion = async (id) => {
        if (!skuId) return;
        try {
            const res = await fetch(`/api/v1/sku/${encodeURIComponent(skuId)}/suggestions/${encodeURIComponent(id)}/status`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', Accept: 'application/json', Authorization: `Bearer ${sessionStorage.getItem('cie_token')}` },
                body: JSON.stringify({ status: 'dismissed' }),
            });
            if (!res.ok) throw new Error('Dismiss failed');
            onClose?.();
        } catch (_) {}
    };

    const byType = (type) => (Array.isArray(suggestions) ? suggestions : []).filter((s) => (s.type || '').toLowerCase() === type);
    const hasAny = suggestions && suggestions.length > 0;

    return (
        <div className="modal" role="dialog" aria-modal="true">
            <div className="card" style={{ maxWidth: 560 }}>
                <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 16 }}>
                    <h2 style={{ margin: 0, fontSize: '1rem' }}>Brief detail</h2>
                    {onClose && <button type="button" onClick={onClose} className="btn" style={{ padding: '4px 10px' }}>Close</button>}
                </div>
                {/* SOURCE: CIE_v232_UI_Restructure_Instructions.docx §3 — AI Suggestions panel, 4 card types */}
                <div style={{ fontSize: '0.85rem', fontWeight: 700, color: 'var(--text)', marginBottom: 10 }}>AI Suggestions</div>
                {SUGGESTION_TYPES.map(({ key, label, icon, iconColor, sourceTag }) => {
                    const items = byType(key);
                    return (
                        <div key={key} style={{ marginBottom: 12, padding: 10, border: '1px solid var(--border)', borderRadius: 6 }}>
                            <div style={{ fontSize: '0.75rem', fontWeight: 600, color: iconColor, marginBottom: 6 }}><span style={{ marginRight: 6 }}>{icon}</span>{label} — {sourceTag}</div>
                            {items.length === 0 ? (
                                <p style={{ fontSize: '0.75rem', color: 'var(--text-muted)', margin: 0 }}>No suggestions right now. This product looks good.</p>
                            ) : (
                                items.map((s) => (
                                    <div key={s.id || s.suggestion_id} style={{ padding: '8px 0', borderTop: '1px solid var(--border)' }}>
                                        <span style={{ fontSize: '0.7rem', fontWeight: 700, color: 'var(--amber)', marginRight: 8 }}>{PRIORITY_BADGE[s.priority] || 'MED'}</span>
                                        <p style={{ fontSize: '0.75rem', color: 'var(--text)', margin: '4px 0' }}>{s.explanation || s.body || s.message || '—'}</p>
                                        <span style={{ fontSize: '0.65rem', color: 'var(--text-muted)' }}>{sourceTag}{s.date_range ? ` · ${s.date_range}` : ''}</span>
                                        <button type="button" onClick={() => dismissSuggestion(s.id || s.suggestion_id)} className="btn" style={{ marginTop: 6, padding: '4px 8px', fontSize: '0.7rem' }}>Dismiss</button>
                                    </div>
                                ))
                            )}
                        </div>
                    );
                })}
            </div>
        </div>
    );
};

export default BriefDetailModal;
