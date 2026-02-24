import React, { useState, useEffect } from 'react';
import {
    ReadinessBar,
    SectionTitle
} from '../components/common/UIComponents';
import { clusterApi } from '../services/api';
import useStore from '../store';

const Clusters = () => {
    const [clusters, setClusters] = useState([]);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState(null);
    const { addNotification } = useStore();

    useEffect(() => {
        const fetchClusters = async () => {
            try {
                setLoading(true);
                setError(null);
                const response = await clusterApi.list();
                const clusterData = response.data.data || [];
                setClusters(clusterData);
            } catch (err) {
                console.error('Failed to fetch clusters:', err);
                setError('Failed to load clusters from database');
                addNotification({
                    type: 'error',
                    message: 'Could not load clusters'
                });
            } finally {
                setLoading(false);
            }
        };
        fetchClusters();
    }, [addNotification]);

    if (loading) return <div style={{ padding: 40, textAlign: 'center', color: 'var(--text-dim)' }}>Loading clusters...</div>;
    if (error) return <div style={{ padding: 40, textAlign: 'center', color: 'var(--red)' }}>{error}</div>;
    return (
        <div>
            <div className="mb-20">
                <h1 className="page-title">Cluster Manager</h1>
                <div className="page-subtitle">SEO/AI Governor — semantic cluster taxonomy governance</div>
            </div>

            <div className="data-table">
                <table>
                    <thead>
                        <tr>
                            <th>Cluster ID</th>
                            <th>Name</th>
                            <th>Primary Intent</th>
                            <th>SKUs</th>
                            <th>Avg Readiness</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        {clusters.map(cl => (
                            <tr key={cl.id}>
                                <td className="mono">{cl.id}</td>
                                <td>{cl.name}</td>
                                <td>
                                    <span style={{
                                        padding: "2px 8px", borderRadius: 3, fontSize: "0.65rem",
                                        background: "var(--accent-dim)", color: "var(--accent)", fontWeight: 600,
                                        border: `1px solid var(--accent)22`,
                                    }}>{cl.intent_type || cl.primary_intent || 'General'}</span>
                                </td>
                                <td className="mono">{cl.sku_count || 0}</td>
                                <td><ReadinessBar value={cl.avg_readiness || 0} /></td>
                                <td>
                                    <button className="btn btn-secondary btn-sm">Edit</button>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
            <div className="alert-banner warning">
                ⚠ Cluster changes require quarterly review. Changes affect all SKUs in the cluster. Governor-only permission.
            </div>
        </div>
    );
};

export default Clusters;
