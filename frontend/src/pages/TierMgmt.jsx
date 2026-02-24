import React, { useState, useEffect } from 'react';
import { TierBadge } from '../components/common/UIComponents';
import { skuApi } from '../services/api';
import useStore from '../store';
import { canApproveTierAsPortfolioHolder, canApproveTierAsFinance, canTriggerTierRecalculation } from '../lib/rbac';

const TierMgmt = () => {
    const { user, addNotification } = useStore();
    const [tierRequests, setTierRequests] = useState([]);
    const [loading, setLoading] = useState(true);
    const [approving, setApproving] = useState({});

    // DUAL sign-off: Portfolio Holder AND Finance must both approve manual tier changes
    const canApproveAsPH = canApproveTierAsPortfolioHolder(user);
    const canApproveAsFinance = canApproveTierAsFinance(user);
    const canTriggerRecalc = canTriggerTierRecalculation(user);

    useEffect(() => {
        const fetchTierRequests = async () => {
            try {
                // API endpoint needed: GET /tier-reassignments?status=pending
                const response = await skuApi.list({ status: 'pending_tier_change' });
                const requests = response.data.data || [];
                setTierRequests(requests.map(sku => ({
                    id: sku.id,
                    sku_code: sku.sku_code,
                    current: sku.tier,
                    score: sku.readiness_score,
                    proposed: sku.proposed_tier || sku.tier,
                    reason: sku.tier_change_reason || 'Score update',
                    override: sku.tier !== sku.proposed_tier,
                    portfolio_holder_approved: sku.portfolio_holder_approved,
                    finance_director_approved: sku.finance_director_approved,
                })));
            } catch (err) {
                console.error('Failed to fetch tier requests:', err);
                addNotification({ type: 'error', message: 'Failed to load tier requests' });
                // Fallback to empty list
                setTierRequests([]);
            } finally {
                setLoading(false);
            }
        };
        fetchTierRequests();
    }, []);

    const handleApprove = async (skuId) => {
        const canApprove = canApproveAsPH || canApproveAsFinance;
        if (!canApprove) {
            addNotification({ type: 'error', message: 'You do not have permission to approve tier changes' });
            return;
        }

        setApproving(prev => ({ ...prev, [skuId]: true }));
        try {
            const approvalType = canApproveAsFinance ? 'FINANCE' : 'PORTFOLIO_HOLDER';
            // API endpoint needed: POST /skus/{id}/approve-tier-change
            await skuApi.update(skuId, { tier_approval: approvalType });
            
            addNotification({ 
                type: 'success', 
                message: `Tier change approved by ${approvalType.replace('_', ' ')}` 
            });

            // Refresh the list
            const response = await skuApi.list({ status: 'pending_tier_change' });
            const requests = response.data.data || [];
            setTierRequests(requests.map(sku => ({
                id: sku.id,
                sku_code: sku.sku_code,
                current: sku.tier,
                score: sku.readiness_score,
                proposed: sku.proposed_tier || sku.tier,
                reason: sku.tier_change_reason || 'Score update',
                override: sku.tier !== sku.proposed_tier,
                portfolio_holder_approved: sku.portfolio_holder_approved,
                finance_director_approved: sku.finance_director_approved,
            })));
        } catch (err) {
            console.error('Approval failed:', err);
            addNotification({ type: 'error', message: 'Failed to approve tier change' });
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
                    🔒 Read-only mode. Only Portfolio Holders and Finance Directors can approve tier changes.
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
                                <th>SKU</th>
                                <th>Current Tier</th>
                                <th>Score</th>
                                <th>Proposed</th>
                                <th>Reason</th>
                                <th>Approvals</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            {tierRequests.map(row => (
                                <tr key={row.id}>
                                    <td className="mono" style={{ fontSize: '0.75rem' }}>{row.sku_code}</td>
                                    <td><TierBadge tier={row.current} size="xs" /></td>
                                    <td className="mono">{row.score}</td>
                                    <td>
                                        {row.proposed !== row.current ? (
                                            <div className="flex items-center gap-4">
                                                <TierBadge tier={row.current} size="xs" />
                                                <span style={{ color: "var(--text-dim)" }}>→</span>
                                                <TierBadge tier={row.proposed} size="xs" />
                                            </div>
                                        ) : <TierBadge tier={row.proposed} size="xs" />}
                                    </td>
                                    <td style={{ fontSize: '0.7rem' }}>{row.reason}</td>
                                    <td style={{ fontSize: '0.65rem', color: 'var(--text-muted)' }}>
                                        <div className="flex gap-4 items-center">
                                            <span title="Portfolio Holder">
                                                {row.portfolio_holder_approved ? '✓ PH' : '○ PH'}
                                            </span>
                                            <span title="Finance Director">
                                                {row.finance_director_approved ? '✓ FD' : '○ FD'}
                                            </span>
                                        </div>
                                    </td>
                                    <td>
                                        {row.override && (canApproveAsPH || canApproveAsFinance) && (
                                            <button 
                                                className="btn btn-secondary btn-sm"
                                                onClick={() => handleApprove(row.id)}
                                                disabled={approving[row.id]}
                                                title={canApproveAsFinance ? "Approve as Finance" : "Approve as Portfolio Holder"}
                                            >
                                                {approving[row.id] ? 'Approving...' : `Approve (${(row.portfolio_holder_approved ? 1 : 0) + (row.finance_director_approved ? 1 : 0)}/2)`}
                                            </button>
                                        )}
                                        {!row.override && (
                                            <span style={{ fontSize: "0.65rem", color: "var(--text-dim)" }}>Auto (no manual override)</span>
                                        )}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            )}

            <div className="alert-banner danger" style={{ marginTop: 20 }}>
                ⏳ DUAL APPROVAL REQUIRED: Tier changes need both Portfolio Holder AND Finance Director approval before applying. Review the rationale carefully.
            </div>
        </div>
    );
};

export default TierMgmt;
