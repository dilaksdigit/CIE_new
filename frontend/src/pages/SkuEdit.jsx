import React, { useState, useEffect, useContext } from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import {
    TierBadge,
    GateChip,
    ReadinessBar,
    RoleBadge,
    SectionTitle,
    TrafficLight,
    GATES
} from '../components/common/UIComponents';
import { skuApi, clusterApi, taxonomyApi } from '../services/api';
import { AppContext } from '../App';
import {
    canEditSkuAny,
    canEditContentFieldsForTier,
    canEditExpertAuthority,
    canAssignCluster,
    canPublishSku,
} from '../lib/rbac';
import { getTierBanner, isFieldEnabledForTier, getMaxSecondaryIntents, normalizeTier } from '../lib/tierFieldMap';

const TIERS = {
    HERO: { label: "HERO", color: "#8B6914", bg: "#FDF6E3", border: "#E8D5A0" },
    SUPPORT: { label: "SUPPORT", color: "#3D6B8E", bg: "#EBF3F9", border: "#B5D0E3" },
    HARVEST: { label: "HARVEST", color: "#9E7C1A", bg: "#FFF8E7", border: "#E8D49A" },
    KILL: { label: "KILL", color: "#A63D2F", bg: "#FDEEEB", border: "#E5B5AD" },
};

const SkuEdit = () => {
    const { id: routeId, skuId } = useParams();
    const id = routeId || skuId;
    const navigate = useNavigate();
    const { user, addNotification } = useContext(AppContext);
    const [activeTab, setActiveTab] = useState('content');
    const [sku, setSku] = useState(null);
    const [loading, setLoading] = useState(true);
    const [saving, setSaving] = useState(false);
    const [unauthorizedReason, setUnauthorizedReason] = useState(null);
    const [clusters, setClusters] = useState([]);
    const [intentsForTier, setIntentsForTier] = useState([]);

    // RBAC: field-level permissions (see frontend/src/lib/rbac.js)
    const canEditContent = () => sku && canEditContentFieldsForTier(user, sku);
    const canEditExpert = () => sku && canEditExpertAuthority(user, sku);
    const canEditCluster = () => canAssignCluster(user);
    const canSubmitForReview = () => sku && canPublishSku(user, sku);
    const canEditAny = () => sku && canEditSkuAny(user, sku);

    useEffect(() => {
        if (!id) {
            setLoading(false);
            return;
        }
        const fetchSku = async () => {
            try {
                const response = await skuApi.get(id);
                const skuData = response.data.data.sku;
                
                // Check authorization AFTER loading SKU (viewer or no edit permission = read-only)
                if (!user) {
                    setUnauthorizedReason('Must be logged in');
                } else if (skuData && !canEditSkuAny(user, skuData)) {
                    setUnauthorizedReason(`Your role (${user.role}) cannot edit this SKU. View-only access.`);
                } else {
                    setUnauthorizedReason(null);
                }
                setSku(skuData);
            } catch (err) {
                console.error('Failed to fetch SKU:', err);
                addNotification({ type: 'error', message: 'Failed to load SKU' });
            } finally {
                setLoading(false);
            }
        };
        fetchSku();
    }, [id, user]);

    // Fetch clusters for SEO Governor so they can assign G1
    useEffect(() => {
        if (!canAssignCluster(user)) return;
        const fetchClusters = async () => {
            try {
                const res = await clusterApi.list();
                const list = res.data?.data ?? res.data ?? [];
                setClusters(Array.isArray(list) ? list : []);
            } catch (err) {
                console.error('Failed to load clusters:', err);
            }
        };
        fetchClusters();
    }, [user]);

    // GET /api/taxonomy/intents?tier=X — allowed intents for this tier (Unified API 7.1)
    useEffect(() => {
        const tier = sku?.tier ? normalizeTier(sku.tier) : '';
        if (!tier || tier === 'kill') {
            setIntentsForTier([]);
            return;
        }
        const fetchIntents = async () => {
            try {
                const res = await taxonomyApi.getIntents(tier);
                const data = res.data?.data ?? res.data;
                const list = data?.intents ?? [];
                setIntentsForTier(Array.isArray(list) ? list : []);
            } catch (err) {
                console.error('Failed to load taxonomy intents:', err);
                setIntentsForTier([]);
            }
        };
        fetchIntents();
    }, [sku?.tier]);

    const handleSave = async (isSubmit = false) => {
        if (!user) return;
        if (sku?.tier === 'KILL') {
            addNotification({ type: 'error', message: 'Cannot edit KILL tier SKUs. All edit permissions revoked.' });
            return;
        }
        if (!canEditAny()) {
            addNotification({ type: 'error', message: 'You do not have permission to edit this SKU' });
            return;
        }

        // Content editors CANNOT override validation gate failures (RBAC critical rule)
        if (isSubmit) {
            const blockedGates = [];
            if (!sku.title || sku.title.length < 50) blockedGates.push('G2');
            if (!sku.short_description || sku.short_description.length < 250) blockedGates.push('G4');
            if (blockedGates.length > 0) {
                addNotification({
                    type: 'error',
                    message: `Cannot submit: Gates ${blockedGates.join(', ')} not passing. Gate overrides are not permitted.`,
                });
                return;
            }
            if (!canSubmitForReview()) {
                addNotification({ type: 'error', message: 'Your role cannot submit this SKU for review' });
                return;
            }
        }

        setSaving(true);
        try {
            const payload = { ...sku };
            if (isSubmit) {
                payload.validation_status = 'PENDING';
            }

            console.log('Saving SKU:', { id, payload });
            const response = await skuApi.update(id, payload);
            console.log('Save response:', response);
            // Keep local SKU in sync with server (e.g. lock_version) so next save doesn't trigger version conflict
            const updatedSku = response.data?.data?.sku;
            if (updatedSku) setSku(updatedSku);
            addNotification({
                type: 'success',
                message: isSubmit ? 'Submitted for review' : 'Draft saved successfully'
            });
            if (isSubmit) navigate('/review');
        } catch (err) {
            console.error('Save failed:', err.response?.data || err.message);
            const errorMsg = err.response?.data?.error || err.response?.data?.message || err.message || 'Failed to save changes';
            addNotification({ type: 'error', message: errorMsg });
        } finally {
            setSaving(false);
        }
    };

    if (loading) return <div style={{ padding: 40, textAlign: 'center' }}>Loading SKU details...</div>;

    if (!id) {
        return (
            <div style={{ padding: 60, textAlign: 'center', color: 'var(--text-dim)' }}>
                <div style={{ fontSize: '3rem', marginBottom: 20 }}>✎</div>
                <h2>No SKU Selected</h2>
                <p>Please select a SKU from the <a href="/" style={{ color: 'var(--hero)', fontWeight: 600 }}>Dashboard</a> to begin editing.</p>
            </div>
        );
    }

    if (!sku) {
        return (
            <div style={{ padding: 60, textAlign: 'center' }}>
                <div style={{ fontSize: '3rem', color: 'var(--red)', marginBottom: 20 }}>⚠</div>
                <h2 style={{ color: 'var(--text)' }}>SKU Not Found</h2>
                <p style={{ color: 'var(--text-dim)', marginBottom: 24 }}>The SKU ID "{id}" does not exist in the database or you lack permission to view it.</p>
                <button className="btn btn-secondary" onClick={() => navigate('/')}>Return to Dashboard</button>
            </div>
        );
    }

    // Show unauthorized message if user lacks permission
    if (unauthorizedReason) {
        return (
            <div style={{ padding: 60, textAlign: 'center' }}>
                <div style={{ fontSize: '3rem', color: 'var(--orange)', marginBottom: 20 }}>🔒</div>
                <h2 style={{ color: 'var(--text)' }}>Access Denied</h2>
                <p style={{ color: 'var(--text-dim)', marginBottom: 24 }}>{unauthorizedReason}</p>
                <p style={{ color: 'var(--text-muted)', fontSize: '0.85rem', marginBottom: 24 }}>
                    You can still view this SKU in read-only mode. Contact your Portfolio Holder for editing access.
                </p>
                <button className="btn btn-secondary" onClick={() => navigate('/')}>Return to Dashboard</button>
            </div>
        );
    }

    const currentTier = sku.tier || 'SUPPORT';
    const tierStyle = TIERS[currentTier] || TIERS.SUPPORT;

    const tabs = [
        { id: 'content', label: 'Content' },
        { id: 'faq', label: 'FAQ' },
        { id: 'authority', label: 'Authority' },
        { id: 'channels', label: 'Channels' },
        { id: 'history', label: 'History' },
    ];

    const isKillTier = currentTier === 'KILL';
    const isHarvestTier = currentTier === 'HARVEST';

    return (
        <div>
            {/* Tier Header Banner */}
            <div style={{
                background: tierStyle.bg, border: `1px solid ${tierStyle.border}`,
                borderRadius: 6, padding: "12px 18px", marginBottom: 14, display: "flex", justifyContent: "space-between", alignItems: "center",
            }}>
                <div>
                    <div className="flex items-center gap-12">
                        <TierBadge tier={currentTier} size="md" />
                        <span style={{ fontSize: "1rem", fontWeight: 700, color: "var(--text)" }}>{sku.sku_code}</span>
                        <span style={{ fontSize: "0.8rem", color: "var(--text-muted)" }}>— {sku.title}</span>
                    </div>
                    <div style={{ fontSize: "0.62rem", color: tierStyle.color, marginTop: 4 }}>
                        {getTierBanner(currentTier) || `${currentTier} TIER`}
                    </div>
                </div>
                <div className="flex gap-8" style={{ flexShrink: 0, minWidth: 'fit-content' }}>
                    {!isKillTier && canEditAny() && (
                        <>
                            <button className="btn btn-secondary" onClick={() => handleSave(false)} disabled={saving} style={{ cursor: 'pointer', pointerEvents: 'auto' }}>
                                {saving ? 'Saving...' : 'Save Draft'}
                            </button>
                            {canSubmitForReview() && (
                                <button className="btn btn-primary" onClick={() => handleSave(true)} disabled={saving} style={{ cursor: 'pointer', pointerEvents: 'auto' }}>
                                    Submit for Review
                                </button>
                            )}
                        </>
                    )}
                    {isKillTier && (
                        <div style={{ fontSize: '0.7rem', color: 'var(--red)', fontWeight: 600 }}>{getTierBanner(currentTier)}</div>
                    )}
                    {!isKillTier && !canEditAny() && (
                        <div style={{ fontSize: '0.7rem', color: 'var(--orange)', fontWeight: 600 }}>READ-ONLY</div>
                    )}
                </div>
            </div>

            {/* Gate Status Bar */}
            <div className="card mb-14 flex items-center gap-12 flex-wrap" style={{ padding: '10px 18px' }}>
                <span style={{ fontSize: "0.62rem", color: "var(--text-dim)", fontWeight: 700, textTransform: "uppercase", letterSpacing: "0.08em" }}>GATE STATUS</span>
                <div style={{ width: 1, height: 20, background: "var(--border)" }} />
                {GATES.map(g => (
                    <GateChip 
                        key={g.id} 
                        id={g.id} 
                        pass={sku.gates?.[g.id]?.passed || false} 
                    />
                ))}
                <div style={{ marginLeft: "auto", display: "flex", alignItems: "center", gap: 8 }}>
                    <span style={{ fontSize: "0.62rem", color: "var(--text-dim)" }}>Readiness:</span>
                    <ReadinessBar value={sku.readiness_score || 0} width={100} />
                </div>
            </div>

            <div className="flex gap-14">
                <div style={{ flex: 1 }}>
                    <div className="tab-bar">
                        {tabs.map(tab => (
                            <button
                                key={tab.id}
                                onClick={() => setActiveTab(tab.id)}
                                className={`tab-btn ${activeTab === tab.id ? 'active' : ''}`}
                            >
                                {tab.label}
                            </button>
                        ))}
                    </div>

                    {activeTab === 'content' && (
                        <div className="flex flex-col gap-14">
                            <div className="flex gap-12">
                                <div style={{ flex: 1 }}>
                                    <label className="field-label">Cluster ID <GateChip id="G1" pass={sku.gates?.G1?.passed || false} compact /></label>
                                    {!isKillTier && canEditCluster() ? (
                                        <select
                                            className="field-input field-select"
                                            value={sku.primary_cluster_id || sku.primaryCluster?.id || ''}
                                            onChange={(e) => setSku({ ...sku, primary_cluster_id: e.target.value || null })}
                                        >
                                            <option value="">Unassigned</option>
                                            {clusters.map((cl) => (
                                                <option key={cl.id} value={cl.id}>{cl.name || cl.id}</option>
                                            ))}
                                        </select>
                                    ) : (
                                        <>
                                            <div className="field-input readonly">{sku.primaryCluster?.name || 'Unassigned'}</div>
                                            <div style={{ fontSize: '0.7rem', color: 'var(--text-muted)', marginTop: 4 }}>Only SEO Governor can assign cluster</div>
                                        </>
                                    )}
                                </div>
                                {!isKillTier && (
                                <div style={{ flex: 1 }}>
                                    <label className="field-label">Primary Intent <GateChip id="G2" pass={sku.gates?.G2?.passed || false} compact /></label>
                                    <select className="field-input field-select" disabled={!canEditContent()}
                                        value={sku.primary_intent || ''} 
                                        onChange={(e) => setSku({ ...sku, primary_intent: e.target.value })}>
                                        <option value="">Select Intent</option>
                                        {intentsForTier.length > 0
                                            ? intentsForTier.map((intent) => (
                                                <option key={intent.intent_id} value={intent.label || intent.intent_key}>{intent.label || intent.intent_key}</option>
                                            ))
                                            : <option value="" disabled>No intents available</option>}
                                    </select>
                                </div>
                                )}
                            </div>

                            {!isKillTier && (
                            <div>
                                <label className="field-label">
                                    Title <GateChip id="G3" pass={sku.gates?.G3?.passed || false} compact />
                                    <span className="char-count">{sku.title?.length || 0}/250 chars</span>
                                </label>
                                <input
                                    className={`field-input ${sku.title && sku.title.length >= 50 ? 'valid' : 'invalid'}`}
                                    value={sku.title || ''}
                                    disabled={!canEditContent()}
                                    onChange={(e) => setSku({ ...sku, title: e.target.value })}
                                    placeholder="Product title (min 50 chars)"
                                />
                            </div>
                            )}

                            {!isKillTier && isFieldEnabledForTier(currentTier, 'answer_block') && (
                                <div>
                                    <label className="field-label">
                                        Answer Block <GateChip id="G4" pass={sku.gates?.G4?.passed || false} compact />
                                        <span className="char-count">{sku.short_description?.length || 0}/300 chars</span>
                                    </label>
                                    <textarea
                                        className={`field-textarea ${sku.short_description && sku.short_description.length >= 250 ? 'valid' : 'invalid'}`}
                                        rows={3}
                                        value={sku.short_description || ''}
                                        disabled={!canEditContent()}
                                        onChange={(e) => setSku({ ...sku, short_description: e.target.value })}
                                        placeholder="Answer block (min 250 chars, max 300)"
                                    />
                                </div>
                            )}

                            <div className="vector-panel">
                                <div>
                                    <div className="field-label">VECTOR — Semantic Similarity <GateChip id="VEC" pass={sku.vector_gate_status === 'pass'} compact /></div>
                                    <div style={{ fontSize: "0.7rem", color: "var(--text-muted)" }}>Description alignment with cluster intent</div>
                                </div>
                                <div style={{ textAlign: "right" }}>
                                    <div className="vector-score" style={{ color: sku.vector_gate_status === 'pass' ? 'var(--green)' : 'var(--orange)' }}>
                                        {sku.vector_gate_status === 'pass' ? 'Good' : sku.vector_gate_status === 'fail' ? 'Review' : '–'}
                                    </div>
                                    <div className="vector-threshold">{sku.vector_gate_status === 'fail' ? 'Your content may not align with the intent. Consider revising.' : 'Description must align with product cluster intent.'}</div>
                                </div>
                            </div>

                            {/* G5: Best-For / Not-For — visible per tier (hero/support; hidden harvest/kill) */}
                            {!isKillTier && isFieldEnabledForTier(currentTier, 'best_for') && (
                                <>
                                    <div>
                                        <label className="field-label">
                                            Best-For Applications <GateChip id="G5" pass={sku.gates?.G5?.passed || false} compact />
                                        </label>
                                        <textarea
                                            className="field-textarea"
                                            rows={2}
                                            value={sku.best_for || ''}
                                            disabled={!canEditContent()}
                                            onChange={(e) => setSku({ ...sku, best_for: e.target.value })}
                                            placeholder="Applications where this product excels (min 2 items)"
                                        />
                                    </div>
                                    <div>
                                        <label className="field-label">
                                            Not-For Applications <GateChip id="G5" pass={sku.gates?.G5?.passed || false} compact />
                                        </label>
                                        <textarea
                                            className="field-textarea"
                                            rows={2}
                                            value={sku.not_for || ''}
                                            disabled={!canEditContent()}
                                            onChange={(e) => setSku({ ...sku, not_for: e.target.value })}
                                            placeholder="Applications where this product should NOT be used (min 1 item)"
                                        />
                                    </div>
                                </>
                            )}

                            {/* G6: Full Product Description (HERO only) */}
                            {currentTier === 'HERO' && (
                                <div>
                                    <label className="field-label">
                                        Full Description <GateChip id="G6" pass={sku.gates?.G6?.passed || false} compact />
                                    </label>
                                    <textarea
                                        className="field-textarea"
                                        rows={4}
                                        value={sku.long_description || ''}
                                        disabled={!canEditContent()}
                                        onChange={(e) => setSku({ ...sku, long_description: e.target.value })}
                                        placeholder="Comprehensive product description for HERO tier (1000+ chars recommended)"
                                    />
                                </div>
                            )}

                            {/* G7: Expert Authority — visible per tier (hero/support; hidden harvest/kill) */}
                            {!isKillTier && isFieldEnabledForTier(currentTier, 'expert_authority') && (
                                <div>
                                    <label className="field-label">
                                        Expert Authority <GateChip id="G7" pass={sku.gates?.G7?.passed || false} compact />
                                    </label>
                                    <input
                                        className="field-input"
                                        type="text"
                                        value={sku.expert_authority_name || ''}
                                        disabled={!canEditExpert()}
                                        onChange={(e) => setSku({ ...sku, expert_authority_name: e.target.value })}
                                        placeholder="Expert name or organization providing authority"
                                    />
                                </div>
                            )}
                        </div>
                    )}

                    {activeTab === 'faq' && (
                        <div className="card">
                            <SectionTitle sub="Auto-generated from golden query set — editable by editors">FAQ Templates</SectionTitle>
                            {sku.faqs && sku.faqs.length > 0 ? (
                                <div style={{ padding: "12px 0" }}>
                                    {sku.faqs.map((faq, idx) => (
                                        <div key={idx} style={{ marginBottom: 12, paddingBottom: 12, borderBottom: '1px solid var(--border-light)' }}>
                                            <div style={{ fontSize: "0.8rem", fontWeight: 600, color: "var(--accent)", marginBottom: 6 }}>Q: {faq.question}</div>
                                            <div style={{ fontSize: "0.75rem", color: "var(--text)" }}>A: {faq.answer}</div>
                                        </div>
                                    ))}
                                </div>
                            ) : (
                                <div style={{ padding: "12px 0", color: 'var(--text-dim)', fontSize: '0.75rem' }}>No FAQs available. FAQs are auto-generated from the AI audit process.</div>
                            )}
                        </div>
                    )}

                    {activeTab === 'history' && (
                        <div className="card">
                            <SectionTitle sub="Immutable audit trail for this SKU">Change History</SectionTitle>
                            {sku.history && sku.history.length > 0 ? (
                                <div style={{ padding: "10px 0" }}>
                                    {sku.history.map((entry, idx) => (
                                        <div key={idx} style={{ padding: "8px 0", borderBottom: '1px solid var(--border-light)', fontSize: '0.75rem' }}>
                                            <div style={{ color: 'var(--text)', fontWeight: 600 }}>
                                                {entry.user_name} · {new Date(entry.created_at).toLocaleString()}
                                            </div>
                                            <div style={{ color: 'var(--text-muted)', marginTop: 2 }}>
                                                {entry.action}: {entry.old_value} → {entry.new_value}
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            ) : (
                                <div style={{ padding: "10px 0", color: 'var(--text-dim)', fontSize: '0.75rem' }}>No change history available yet.</div>
                            )}
                        </div>
                    )}
                </div>

                <div style={{ width: 220, flexShrink: 0 }} className="flex flex-col gap-12">
                    <div className="card" style={{ padding: 14 }}>
                        <div className="field-label">ERP Data</div>
                        {[
                            { label: "Margin", value: `${sku.margin_percent || 0}%` },
                            { label: "Velocity", value: `${sku.annual_volume || 0}/yr` },
                            { label: "Status", value: sku.validation_status },
                        ].map(d => (
                            <div key={d.label} className="flex justify-between" style={{ padding: "4px 0", borderBottom: '1px solid var(--border-light)' }}>
                                <span style={{ fontSize: "0.7rem", color: "var(--text-muted)" }}>{d.label}</span>
                                <span style={{ fontSize: "0.7rem", color: "var(--text)", fontFamily: "var(--mono)", fontWeight: 600 }}>{d.value}</span>
                            </div>
                        ))}
                    </div>
                </div>
            </div>
        </div>
    );
};

export default SkuEdit;
