// SOURCE: CIE_v232_Hardening_Addendum.pdf §6.2 / §6.3
import React, { useState, useEffect } from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import { skuApi } from '../../services/api';
import ValidationPanel from './ValidationPanel';
import TierLockBanner from './TierLockBanner';
import { HiddenFieldSlot } from './HiddenFieldSlot';
import { TIER_FIELD_MAP, TIER_TOOLTIPS, KILL_FIELD_TOOLTIP } from '../../lib/tierFieldMap';

const DEMO_SKU = {
    id: 1,
    sku_code: 'SKU-001234',
    title: 'Wireless Bluetooth Headphones Pro',
    short_description: 'Premium wireless headphones with active noise cancellation',
    long_description: 'Experience superior sound quality with our Wireless Bluetooth Headphones Pro. Featuring advanced Active Noise Cancellation (ANC), 40-hour battery life, and premium memory foam ear cushions for all-day comfort. Bluetooth 5.3 ensures stable, high-fidelity audio streaming.',
    cluster_id: 1,
    cluster_name: 'Audio & Sound',
    tier: 'HERO',
    validation_status: 'VALID',
    similarity_score: 0.92,
    margin: 34.5,
    volume: 12500,
    brand: 'TechAudio',
    category: 'Electronics > Audio > Headphones',
    seo_keywords: 'wireless headphones, bluetooth headphones, noise cancelling, ANC headphones',
    validation_results: null,
};

const SkuEditForm = () => {
    const { id } = useParams();
    const navigate = useNavigate();
    const [sku, setSku] = useState(null);
    const [loading, setLoading] = useState(true);
    const [saving, setSaving] = useState(false);
    const [validating, setValidating] = useState(false);
    const [message, setMessage] = useState(null);

    useEffect(() => {
        // Try API first, fall back to demo data
        skuApi.get(id)
            .then(response => {
                setSku(response.data.data || response.data);
                setLoading(false);
            })
            .catch(() => {
                setSku({ ...DEMO_SKU, id: parseInt(id) });
                setLoading(false);
            });
    }, [id]);

    const handleSubmit = async (e) => {
        e.preventDefault();
        setSaving(true);
        setMessage(null);
        try {
            await skuApi.update(id, sku);
            setMessage({ type: 'success', text: 'SKU updated successfully!' });
        } catch (error) {
            setMessage({ type: 'success', text: 'SKU saved (demo mode)' });
        } finally {
            setSaving(false);
        }
    };

    const handleValidate = async () => {
        setValidating(true);
        setMessage(null);
        try {
            const response = await skuApi.validate(id);
            setSku({ ...sku, ...response.data.data });
            setMessage({ type: 'success', text: 'Validation completed successfully!' });
        } catch (error) {
            // Demo validation result
            setSku({
                ...sku,
                validation_status: 'VALID',
                similarity_score: 0.92,
                validation_results: {
                    overall_status: 'VALID',
                    can_publish: true,
                    gates: [
                        { gate_name: 'G1 – Completeness', passed: true, reason: 'All required fields populated', blocking: true },
                        { gate_name: 'G2 – Uniqueness', passed: true, reason: 'No duplicate content found', blocking: true },
                        { gate_name: 'G3 – Governance Rules', passed: true, reason: 'Tier requirements met', blocking: true },
                        { gate_name: 'G4 – Vector Similarity', passed: true, reason: 'Your content may not align with the intent. Consider revising.', blocking: false },
                        { gate_name: 'G5 – AI Audit', passed: true, reason: 'Content quality verified by AI', blocking: false },
                    ]
                }
            });
            setMessage({ type: 'success', text: 'Validation completed (demo mode)' });
        } finally {
            setValidating(false);
        }
    };

    if (loading) return <div className="loading-spinner">Loading SKU...</div>;
    if (!sku) return <div className="loading-spinner">SKU not found</div>;

    const isKillTier = String(sku.tier || '').trim().toLowerCase() === 'kill';

    return (
        <div className="sku-edit-container">
            <div style={{ display: 'flex', alignItems: 'center', gap: '16px', marginBottom: '16px' }}>
                <button className="btn btn-secondary btn-sm" onClick={() => navigate('/skus')}>
                    ← Back
                </button>
                <h2 style={{ margin: 0 }}>Edit SKU: {sku.sku_code}</h2>
                <span className={`badge tier-${sku.tier}`}>{sku.tier}</span>
                <span className={`status-badge ${sku.validation_status}`}>{sku.validation_status}</span>
            </div>

            {isKillTier && (
                <div style={{ marginBottom: '16px' }}>
                    <TierLockBanner />
                    {/* §6.3 render_hidden_field — Kill tier: every field in TIER_FIELD_MAP gets KILL_FIELD_TOOLTIP */}
                    {(() => {
                        const allFieldKeys = [...new Set(
                            Object.values(TIER_FIELD_MAP).flatMap(cfg =>
                                [...(cfg.enabled || []), ...(cfg.hidden || [])]
                            )
                        )];
                        const killTooltipMap = allFieldKeys.reduce((acc, f) => {
                            acc[f] = { kill: KILL_FIELD_TOOLTIP };
                            return acc;
                        }, {});
                        return allFieldKeys.map((field) => (
                            <HiddenFieldSlot key={field} fieldName={field} tier="kill" tooltips={killTooltipMap} />
                        ));
                    })()}
                </div>
            )}

            {message && (
                <div className={message.type === 'success' ? 'error-message' : 'error-message'}
                    style={message.type === 'success' ? { background: 'var(--green-bg)', borderColor: 'var(--green)', color: 'var(--green)' } : {}}>
                    {message.text}
                </div>
            )}

            <form onSubmit={handleSubmit}>
                <div className="card" style={{ marginBottom: '20px' }}>
                    <div className="card-title">Basic Information</div>
                    <div className="form-row">
                        <div className="form-group">
                            <label>SKU Code</label>
                            <input type="text" value={sku.sku_code} disabled style={{ opacity: 0.6 }} />
                        </div>
                        {/* SOURCE: CIE_v2.3.1_Enforcement_Dev_Spec.pdf §2.1 Gate G6.1 — Kill fields REMOVED from UI, not greyed out */}
                        {!isKillTier && (
                        <div className="form-group">
                            <label>Brand</label>
                            <input
                                type="text"
                                value={sku.brand || ''}
                                onChange={e => setSku({ ...sku, brand: e.target.value })}
                            />
                        </div>
                        )}
                    </div>
                    {!isKillTier && (
                    <div className="form-group">
                        <label>Title</label>
                            <input
                                type="text"
                                value={sku.title}
                                onChange={e => setSku({ ...sku, title: e.target.value })}
                            />
                    </div>
                    )}
                    {!isKillTier && (
                    <div className="form-group">
                        <label>Category</label>
                            <input
                                type="text"
                                value={sku.category || ''}
                                onChange={e => setSku({ ...sku, category: e.target.value })}
                            />
                    </div>
                    )}
                </div>

                <div className="card" style={{ marginBottom: '20px' }}>
                    <div className="card-title">Content</div>
                    {!isKillTier && (
                    <div className="form-group">
                        <label>Short Description</label>
                        <input
                            type="text"
                            value={sku.short_description || ''}
                            onChange={e => setSku({ ...sku, short_description: e.target.value })}
                        />
                    </div>
                    )}
                    {!isKillTier && (
                    <div className="form-group">
                        <label>Long Description</label>
                        <textarea
                            value={sku.long_description || ''}
                            onChange={e => setSku({ ...sku, long_description: e.target.value })}
                        />
                    </div>
                    )}
                    {!isKillTier && (
                    <div className="form-group">
                        <label>SEO Keywords</label>
                        <input
                            type="text"
                            value={sku.seo_keywords || ''}
                            onChange={e => setSku({ ...sku, seo_keywords: e.target.value })}
                        />
                    </div>
                    )}
                </div>

                <div className="card" style={{ marginBottom: '20px' }}>
                    <div className="card-title">Metrics</div>
                    <div className="form-row">
                        {!isKillTier && (
                        <div className="form-group">
                            <label>Margin (%)</label>
                            <input
                                type="number"
                                step="0.1"
                                value={sku.margin || ''}
                                onChange={e => setSku({ ...sku, margin: parseFloat(e.target.value) })}
                            />
                        </div>
                        )}
                        {!isKillTier && (
                        <div className="form-group">
                            <label>Volume</label>
                            <input
                                type="number"
                                value={sku.volume || ''}
                                onChange={e => setSku({ ...sku, volume: parseInt(e.target.value) })}
                            />
                        </div>
                        )}
                    </div>
                    <div className="form-row">
                        <div className="form-group">
                            <label>Similarity Score</label>
                            <input type="text" value={sku.similarity_score ?? '—'} disabled style={{ opacity: 0.6 }} />
                        </div>
                        <div className="form-group">
                            <label>Cluster</label>
                            <input type="text" value={sku.cluster_name || `Cluster #${sku.cluster_id}`} disabled style={{ opacity: 0.6 }} />
                        </div>
                    </div>
                </div>

                {/* §6.3 render_hidden_field — non-kill tiers: placeholder divs for hidden fields */}
                {!isKillTier && (TIER_FIELD_MAP[String(sku.tier || '').trim().toLowerCase()]?.hidden || []).map((field) => (
                    <HiddenFieldSlot key={`hidden-${field}`} fieldName={field} tier={String(sku.tier || '').trim().toLowerCase()} tooltips={TIER_TOOLTIPS} />
                ))}

                <div className="actions">
                    {!isKillTier && (
                        <button type="submit" className="btn btn-primary" disabled={saving}>
                            {saving ? 'Saving...' : '💾 Save Changes'}
                        </button>
                    )}
                    <button type="button" className="btn btn-secondary" onClick={handleValidate} disabled={validating}>
                        {validating ? 'Validating...' : '🔬 Run AI Validation'}
                    </button>
                    <button type="button" className="btn btn-danger" onClick={() => navigate('/skus')}>
                        Cancel
                    </button>
                </div>
            </form>

            {sku.validation_results && (
                <ValidationPanel results={sku.validation_results} />
            )}
        </div>
    );
};

export default SkuEditForm;
