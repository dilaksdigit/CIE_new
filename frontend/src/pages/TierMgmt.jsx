// SOURCE: CLAUDE.md Section 8 (no emojis in production UI); CIE_v232_Developer_Amendment_Pack Section 8 check #7
import React, { useState, useEffect, useContext } from 'react';
import { TierBadge } from '../components/common/UIComponents';
import { bulkOpsApi, tierChangeApi } from '../services/api';
import { AppContext } from '../App';
import { canApproveTierAsPortfolioHolder, canApproveTierAsFinance } from '../lib/rbac';

const TierMgmt = () => {
    const { user, addNotification } = useContext(AppContext);
    const [tierRequests, setTierRequests] = useState([]);
    const [loading, setLoading] = useState(true);
    const [approving, setApproving] = useState({});

    // DUAL sign-off: Portfolio Holder AND Finance must both approve manual tier changes
    const canApproveAsPH = canApproveTierAsPortfolioHolder(user);
    const canApproveAsFinance = canApproveTierAsFinance(user);

    const loadTierRequests = async () => {
        try {
            setLoading(true);
            const response = await bulkOpsApi.listTierChangeRequests();
            const data = response.data?.data ?? response.data ?? {};
            const requests = Array.isArray(data.requests) ? data.requests : [];
            setTierRequests(requests.map((row) => ({
                id: row.id,
                sku_id: row.sku_id,
                sku_code: row.sku_code,
                requested_tier: row.requested_tier,
                status: row.status,
                created_at: row.created_at,
            })));
        } catch (err) {
            console.error('Failed to fetch tier requests:', err);
            addNotification({ type: 'error', message: 'Failed to load tier requests' });
            setTierRequests([]);
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => {
        loadTierRequests();
    }, []);

    const handleApprovePortfolio = async (requestId) => {
        if (!canApproveAsPH) {
            addNotification({ type: 'error', message: 'Only Portfolio Holder can perform this approval step' });
            return;
        }

        setApproving(prev => ({ ...prev, [requestId]: true }));
        try {
            await tierChangeApi.approvePortfolio(requestId);
            addNotification({
                type: 'success',
                message: 'Portfolio approval recorded. Request moved to finance approval.',
            });
            await loadTierRequests();
        } catch (err) {
            console.error('Portfolio approval failed:', err);
            addNotification({ type: 'error', message: err.response?.data?.message || 'Portfolio approval failed' });
        } finally {
            setApproving(prev => ({ ...prev, [requestId]: false }));
        }
    };

    const handleApproveFinance = async (skuId) => {
        if (!canApproveAsFinance) {
            addNotification({ type: 'error', message: 'Only Finance can perform this approval step' });
            return;
        }

        setApproving(prev => ({ ...prev, [skuId]: true }));
        try {
            await tierChangeApi.approveFinance(skuId);
            addNotification({
                type: 'success',
                message: 'Finance approval recorded and tier applied.',
            });
            await loadTierRequests();
        } catch (err) {
            console.error('Finance approval failed:', err);
            addNotification({ type: 'error', message: err.response?.data?.message || 'Finance approval failed' });
        } finally {
            setApproving(prev => ({ ...prev, [skuId]: false }));
        }
    };

    if (loading) return <div style={{ padding: 40, textAlign: 'center', color: 'var(--text-dim)' }}>Loading tier requests...</div>;

    return (
        <div>
            <div className="mb-20">
                <h1 className="page-title">Tier Management</h1>
                <div className="page-subtitle">Finance + Portfolio Holders — dual approval required for tier changes</div>
            </div>

            {!canApproveAsPH && !canApproveAsFinance && (
                <div style={{
                    padding: '12px 16px',
                    background: 'var(--orange-bg)',
                    border: '1px solid var(--orange)',
                    borderRadius: 6,
                    marginBottom: 20,
                    color: 'var(--orange)',
                    fontSize: '0.75rem'
                }}>
                    Read-only mode. Only Portfolio Holders and Finance Directors can approve tier changes.
                </div>
            )}

            {tierRequests.length === 0 ? (
                <div style={{
                    padding: 40,
                    textAlign: 'center',
                    color: 'var(--text-dim)',
                    fontSize: '0.9rem'
                }}>
                    No pending tier changes at this time.
                </div>
            ) : (
                <div className="data-table">
                    <table>
                        <thead>
                            <tr>
                                <th>Request ID</th>
                                <th>SKU</th>
                                <th>Requested Tier</th>
                                <th>Status</th>
                                <th>Created</th>
                                <th>Portfolio Step</th>
                                <th>Finance Step</th>
                            </tr>
                        </thead>
                        <tbody>
                            {tierRequests.map(row => (
                                <tr key={row.id}>
                                    <td className="mono" style={{ fontSize: '0.65rem' }}>{row.id}</td>
                                    <td className="mono" style={{ fontSize: '0.75rem' }}>{row.sku_code}</td>
                                    <td><TierBadge tier={row.requested_tier} size="xs" /></td>
                                    <td className="mono" style={{ fontSize: '0.65rem' }}>{row.status}</td>
                                    <td className="mono" style={{ fontSize: '0.65rem' }}>{row.created_at || '—'}</td>
                                    <td>
                                        {row.status === 'pending_portfolio_approval' && canApproveAsPH ? (
                                            <button
                                                className="btn btn-secondary btn-sm"
                                                onClick={() => handleApprovePortfolio(row.id)}
                                                disabled={approving[row.id]}
                                            >
                                                {approving[row.id] ? 'Approving...' : 'Approve Portfolio'}
                                            </button>
                                        ) : (
                                            <span style={{ fontSize: '0.65rem', color: 'var(--text-dim)' }}>
                                                {row.status === 'pending_portfolio_approval' ? 'Pending' : 'Done'}
                                            </span>
                                        )}
                                    </td>
                                    <td>
                                        {row.status === 'pending_finance_approval' && canApproveAsFinance ? (
                                            <button 
                                                className="btn btn-secondary btn-sm"
                                                onClick={() => handleApproveFinance(row.sku_id)}
                                                disabled={approving[row.sku_id]}
                                            >
                                                {approving[row.sku_id] ? 'Approving...' : 'Approve Finance'}
                                            </button>
                                        ) : (
                                            <span style={{ fontSize: "0.65rem", color: "var(--text-dim)" }}>
                                                {row.status === 'approved' ? 'Done' : 'Waiting Portfolio'}
                                            </span>
                                        )}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            )}

            <div className="alert-banner danger" style={{ marginTop: 20 }}>
                DUAL APPROVAL REQUIRED: Tier changes need both Portfolio Holder and Finance approval before applying.
            </div>
        </div>
    );
};

export default TierMgmt;
