import React, { useState, useEffect } from 'react';
import { configApi } from '../services/api';
import useStore from '../store';
import { canModifyConfig } from '../lib/rbac';

const DEFAULT_CONFIG = {
    gate_thresholds: {
        answer_block_min: 250,
        answer_block_max: 300,
        title_max_length: 250,
        vector_threshold: 0.72,
        title_intent_min: 20,
    },
    tier_score_weights: {
        margin_weight: 0.30,
        velocity_weight: 0.30,
        return_rate_weight: 0.20,
        margin_rank_weight: 0.20,
        hero_threshold: 75,
    },
    channel_thresholds: {
        hero_compete_min: 85,
        support_compete_min: 70,
        harvest: "Excluded",
        kill: "Excluded",
        feed_regen_time: "02:00",
    },
    audit_settings: {
        audit_day: "Monday",
        audit_time: "06:00",
        questions_per_category: 20,
        engines: 4,
        decay_trigger: "Week 3",
    },
};

function normalizeConfig(raw) {
    if (!raw || typeof raw !== 'object') return DEFAULT_CONFIG;
    return {
        gate_thresholds: { ...DEFAULT_CONFIG.gate_thresholds, ...(raw.gate_thresholds || {}) },
        tier_score_weights: { ...DEFAULT_CONFIG.tier_score_weights, ...(raw.tier_score_weights || {}) },
        channel_thresholds: { ...DEFAULT_CONFIG.channel_thresholds, ...(raw.channel_thresholds || {}) },
        audit_settings: { ...DEFAULT_CONFIG.audit_settings, ...(raw.audit_settings || {}) },
    };
}

const Config = () => {
    const { user, addNotification } = useStore();
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
                addNotification({ type: 'error', message: 'Failed to load configuration' });
                const configData = DEFAULT_CONFIG;
                setConfig(configData);
                setEditingConfig(JSON.parse(JSON.stringify(configData)));
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
        setEditingConfig(JSON.parse(JSON.stringify(config || DEFAULT_CONFIG)));
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
    const displayConfig = config || DEFAULT_CONFIG;

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
                    🔒 Read-only mode. Only admins can modify configuration.
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
