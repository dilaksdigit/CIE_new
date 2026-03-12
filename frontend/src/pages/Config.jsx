import React, { useState, useEffect, useContext } from 'react';
import { configApi } from '../services/api';
import { AppContext } from '../App';
import { canModifyConfig } from '../lib/rbac';

function normalizeConfig(raw) {
    if (!raw || typeof raw !== 'object') return null;
    return {
        gate_thresholds: raw.gate_thresholds || {},
        tier_score_weights: raw.tier_score_weights || {},
        channel_thresholds: raw.channel_thresholds || {},
        audit_settings: raw.audit_settings || {},
    };
}

const Config = () => {
    const { user, addNotification } = useContext(AppContext);
    const [config, setConfig] = useState(null);
    const [editingConfig, setEditingConfig] = useState(null);
    const [loading, setLoading] = useState(true);
    const [saving, setSaving] = useState(false);
    const [isEditing, setIsEditing] = useState(false);

    const canEditConfig = canModifyConfig(user);

    useEffect(() => {
        const fetchConfig = async () => {
            try {
                const response = await configApi.get();
                const raw = response.data?.data ?? response.data;
                const configData = normalizeConfig(raw);
                setConfig(configData);
                setEditingConfig(JSON.parse(JSON.stringify(configData)));
            } catch (err) {
                console.error('Failed to fetch config:', err);
                addNotification({ type: 'error', message: 'Failed to load configuration. Business rules unavailable.' });
                setConfig(null);
            } finally {
                setLoading(false);
            }
        };
        fetchConfig();
    }, []);

    const handleSaveConfig = async () => {
        if (!canEditConfig) {
            addNotification({ type: 'error', message: 'Only admins can modify configuration' });
            return;
        }

        setSaving(true);
        try {
            await configApi.update(editingConfig);
            setConfig(editingConfig);
            setIsEditing(false);
            addNotification({ type: 'success', message: 'Configuration updated successfully' });
        } catch (err) {
            console.error('Failed to save config:', err);
            addNotification({ type: 'error', message: 'Failed to save configuration' });
        } finally {
            setSaving(false);
        }
    };

    const handleCancel = () => {
        setEditingConfig(config ? JSON.parse(JSON.stringify(config)) : null);
        setIsEditing(false);
    };

    const updateNestedValue = (section, key, value) => {
        setEditingConfig(prev => ({
            ...prev,
            [section]: {
                ...prev[section],
                [key]: value
            }
        }));
    };

    if (loading) return <div style={{ padding: 40, textAlign: 'center', color: 'var(--text-dim)' }}>Loading configuration...</div>;
    if (!config) return <div style={{ padding: 40, textAlign: 'center', color: 'var(--red)' }}>Failed to load business rules from server. No fallback defaults available.</div>;
    const displayConfig = config;

    const sections = [
        {
            title: "Gate Thresholds",
            key: "gate_thresholds",
            fields: [
                { label: "Answer Block Min", key: "answer_block_min", unit: "chars", type: "number" },
                { label: "Answer Block Max", key: "answer_block_max", unit: "chars", type: "number" },
                { label: "Title Max Length", key: "title_max_length", unit: "chars", type: "number" },
                { label: "Vector Threshold", key: "vector_threshold", unit: "cosine", type: "number" },
                { label: "Title Intent Min", key: "title_intent_min", unit: "chars", type: "number" },
            ]
        },
        {
            title: "Tier Score Weights",
            key: "tier_score_weights",
            fields: [
                { label: "Margin Weight", key: "margin_weight", unit: "", type: "number" },
                { label: "Velocity Weight", key: "velocity_weight", unit: "", type: "number" },
                { label: "Return Rate Weight", key: "return_rate_weight", unit: "", type: "number" },
                { label: "Margin Rank Weight", key: "margin_rank_weight", unit: "", type: "number" },
                { label: "Hero Threshold", key: "hero_threshold", unit: "%", type: "number" },
            ]
        },
        {
            title: "Channel Thresholds",
            key: "channel_thresholds",
            fields: [
                { label: "Hero Compete Min", key: "hero_compete_min", unit: "%", type: "number" },
                { label: "Support Compete Min", key: "support_compete_min", unit: "%", type: "number" },
                { label: "Harvest", key: "harvest", unit: "", type: "text" },
                { label: "Kill", key: "kill", unit: "", type: "text" },
                { label: "Feed Regen Time", key: "feed_regen_time", unit: "UTC", type: "text" },
            ]
        },
        {
            title: "Audit Settings",
            key: "audit_settings",
            fields: [
                { label: "Audit Day", key: "audit_day", unit: "", type: "text" },
                { label: "Audit Time", key: "audit_time", unit: "UTC", type: "text" },
                { label: "Questions/Category", key: "questions_per_category", unit: "", type: "number" },
                { label: "Engines", key: "engines", unit: "", type: "number" },
                { label: "Decay Trigger", key: "decay_trigger", unit: "", type: "text" },
            ]
        },
    ];

    return (
        <div>
            <div className="mb-20 flex justify-between items-start">
                <div>
                    <h1 className="page-title">Configuration</h1>
                    <div className="page-subtitle">Admin only — system thresholds, vocabularies, and templates</div>
                </div>
                {canEditConfig && (
                    <div className="flex gap-8">
                        {!isEditing ? (
                            <button className="btn btn-secondary" onClick={() => setIsEditing(true)}>
                                Edit Configuration
                            </button>
                        ) : (
                            <>
                                <button className="btn btn-secondary" onClick={handleCancel} disabled={saving}>
                                    Cancel
                                </button>
                                <button className="btn btn-primary" onClick={handleSaveConfig} disabled={saving}>
                                    {saving ? 'Saving...' : 'Save Changes'}
                                </button>
                            </>
                        )}
                    </div>
                )}
            </div>

            {/* SPEC: CLAUDE.md §11 — 0.72 visible to ADMIN only. Writer roles are blocked at RBAC level via canModifyConfig. */}
            {!canEditConfig && (
                <div style={{
                    padding: '12px 16px',
                    background: 'var(--orange-bg)',
                    border: '1px solid var(--orange)',
                    borderRadius: 6,
                    marginBottom: 20,
                    color: 'var(--orange)',
                    fontSize: '0.75rem'
                }}>
                    Read-only mode. Only admins can modify configuration.
                </div>
            )}

            <div className="flex gap-14 flex-wrap">
                {sections.map(section => (
                    <div key={section.key} className="card" style={{ flex: 1, minWidth: 260 }}>
                        <div style={{ fontSize: "0.68rem", fontWeight: 700, color: "var(--text)", marginBottom: 12, textTransform: "uppercase", letterSpacing: "0.04em" }}>
                            {section.title}
                        </div>
                        {section.fields.map(field => (
                            <div key={field.key} className="flex justify-between items-center" style={{ padding: "8px 0", borderBottom: '1px solid var(--border-light)' }}>
                                <span style={{ fontSize: "0.7rem", color: "var(--text-muted)" }}>{field.label}</span>
                                <div className="flex items-center gap-4">
                                    {isEditing && canEditConfig ? (
                                        <input
                                            type={field.type}
                                            value={editingConfig?.[section.key]?.[field.key] ?? ''}
                                            onChange={(e) => {
                                                const value = field.type === 'number' ? parseFloat(e.target.value) : e.target.value;
                                                updateNestedValue(section.key, field.key, value);
                                            }}
                                            style={{
                                                padding: "2px 8px",
                                                background: "var(--surface-alt)",
                                                border: "1px solid var(--border)",
                                                borderRadius: 3,
                                                fontSize: "0.7rem",
                                                color: "var(--text)",
                                                fontFamily: "var(--mono)",
                                                fontWeight: 600,
                                                width: 80,
                                            }}
                                        />
                                    ) : (
                                        <span style={{
                                            padding: "2px 8px",
                                            background: "var(--surface-alt)",
                                            border: "1px solid var(--border)",
                                            borderRadius: 3,
                                            fontSize: "0.7rem",
                                            color: "var(--text)",
                                            fontFamily: "var(--mono)",
                                            fontWeight: 600,
                                        }}>
                                            {(displayConfig[section.key] || {})[field.key]}
                                        </span>
                                    )}
                                    {field.unit && <span style={{ fontSize: "0.55rem", color: "var(--text-dim)" }}>{field.unit}</span>}
                                </div>
                            </div>
                        ))}
                    </div>
                ))}
            </div>
        </div>
    );
};

export default Config;
