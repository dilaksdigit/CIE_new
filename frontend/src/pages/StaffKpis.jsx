import React, { useState, useEffect, useCallback } from 'react';
import { MiniBarChart, RoleBadge, TrendLine } from '../components/common/UIComponents';
import { auditResultApi, dashboardApi, configApi } from '../services/api';

const StaffKpis = () => {
    const [staffKpis, setStaffKpis] = useState([]);
    const [weeklyScores, setWeeklyScores] = useState([]);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState(null);
    const [thresholds, setThresholds] = useState(null);
    const [weekStart, setWeekStart] = useState('');
    const [score, setScore] = useState(1);
    const [notes, setNotes] = useState('');
    const [saveBusy, setSaveBusy] = useState(false);
    const [saveMessage, setSaveMessage] = useState('');

    const fetchKpis = useCallback(async () => {
        try {
            setLoading(true);
            setError(null);
            const res = await dashboardApi.getSummary();
            const data = res.data?.data ?? {};
            const weeklyRes = await auditResultApi.getWeeklyScores().catch(() => ({ data: { data: [] } }));
            setStaffKpis(data.staff_kpis ?? []);
            setWeeklyScores(Array.isArray(weeklyRes.data?.data) ? weeklyRes.data.data : []);
        } catch (e) {
            console.error('Staff KPIs failed:', e);
            setError('Failed to load staff KPIs');
        } finally {
            setLoading(false);
        }
    }, []);

    useEffect(() => {
        fetchKpis();
    }, [fetchKpis]);

    useEffect(() => {
        configApi.get().then(res => {
            const raw = res.data?.data ?? res.data ?? {};
            setThresholds(raw);
        }).catch(e => {
            console.error('Failed to load business rules for staff KPIs:', e);
        });
    }, []);

    const handleSaveWeeklyScore = async (e) => {
        e.preventDefault();
        if (!weekStart || score < 1 || score > 10) return;
        setSaveBusy(true);
        setSaveMessage('');
        try {
            await auditResultApi.saveWeeklyScore({ week_start: weekStart, score: Number(score), notes: notes || null });
            setSaveMessage('Saved.');
            setWeekStart('');
            setScore(1);
            setNotes('');
            fetchKpis();
        } catch (err) {
            setSaveMessage(err.response?.data?.message || 'Save failed');
        } finally {
            setSaveBusy(false);
        }
    };

    if (loading) return <div style={{ padding: 40, textAlign: 'center', color: 'var(--text-dim)' }}>Loading staff KPIs...</div>;
    if (error) return <div style={{ padding: 40, textAlign: 'center', color: 'var(--red)' }}>{error}</div>;

    const leaderboardData = staffKpis
        .filter(s => s.validations > 0)
        .sort((a, b) => (b.validations || 0) - (a.validations || 0))
        .slice(0, 6)
        .map(s => ({
            label: (s.user_name || 'Unknown').split(' ')[0],
            value: s.validations || 0,
            color: (s.gate_pass_rate || 0) >= (thresholds?.staff?.gate_pass_rate_green ?? 80) ? 'var(--green)' : (s.gate_pass_rate || 0) >= (thresholds?.staff?.gate_pass_rate_amber ?? 60) ? 'var(--amber)' : 'var(--accent)',
        }));

    const weeklyTrendData = weeklyScores.slice(0, 12).map((row) => ({
        label: String(row.week_start || '').slice(5),
        value: Number(row.score || 0),
        color: Number(row.score || 0) >= (thresholds?.staff?.weekly_score_green ?? 8) ? 'var(--green)' : Number(row.score || 0) >= (thresholds?.staff?.weekly_score_amber ?? 6) ? 'var(--amber)' : 'var(--red)',
    }));
    // SOURCE: CIE_v232_UI_Restructure_Instructions.docx §7 Step 5

    return (
        <div>
            <div className="mb-20">
                <h1 className="page-title">Staff Performance</h1>
                <div className="page-subtitle">KPI tracking per staff member plus weekly score trend</div>
            </div>

            <div className="data-table mb-16">
                <table>
                    <thead>
                        <tr>
                            <th>Staff</th>
                            <th>Role</th>
                            <th>Validations</th>
                            <th>Gate pass rate</th>
                            <th>Rework count</th>
                            <th>Hours spent</th>
                        </tr>
                    </thead>
                    <tbody>
                        {staffKpis.length === 0 ? (
                            <tr><td colSpan={6} style={{ textAlign: 'center', color: 'var(--text-dim)', padding: 24 }}>No staff KPI data this week.</td></tr>
                        ) : (
                            staffKpis.map((s) => (
                                <tr key={s.user_id}>
                                    <td style={{ fontSize: '0.8rem', fontWeight: 500 }}>{s.user_name || 'Unknown'}</td>
                                    <td><RoleBadge role={s.role} /></td>
                                    <td className="mono" style={{ fontSize: '0.7rem' }}>{s.validations ?? 0}</td>
                                    <td>
                                        <span style={{
                                            color: (s.gate_pass_rate || 0) >= (thresholds?.staff?.gate_pass_rate_green ?? 80) ? 'var(--green)' : (s.gate_pass_rate || 0) >= (thresholds?.staff?.gate_pass_rate_amber ?? 60) ? 'var(--amber)' : 'var(--red)',
                                            fontWeight: 600
                                        }}>{s.gate_pass_rate ?? 0}%</span>
                                    </td>
                                    <td>
                                        <span style={{
                                            color: (s.rework_count || 0) <= 2 ? 'var(--green)' : (s.rework_count || 0) <= 5 ? 'var(--amber)' : 'var(--red)',
                                            fontWeight: 600
                                        }}>{s.rework_count ?? 0}</span>
                                    </td>
                                    <td className="mono" style={{ fontSize: '0.7rem' }}>{s.hours_spent ?? 0}h</td>
                                </tr>
                            ))
                        )}
                    </tbody>
                </table>
            </div>

            {leaderboardData.length > 0 && (
                <div className="card">
                    <div style={{ fontSize: "0.7rem", fontWeight: 700, color: "var(--text)", marginBottom: 12 }}>Weekly validations — Leaderboard</div>
                    <MiniBarChart width={400} height={60} data={leaderboardData} />
                </div>
            )}

            <div className="card" style={{ marginBottom: 16 }}>
                <div style={{ fontSize: '0.85rem', fontWeight: 700, color: 'var(--text)', marginBottom: 10 }}>Add / edit weekly score</div>
                <form onSubmit={handleSaveWeeklyScore} style={{ display: 'flex', flexWrap: 'wrap', gap: 12, alignItems: 'flex-end' }}>
                    <label style={{ display: 'flex', flexDirection: 'column', gap: 4 }}>
                        <span style={{ fontSize: '0.75rem', color: 'var(--text-muted)' }}>Week start</span>
                        <input type="date" value={weekStart} onChange={(e) => setWeekStart(e.target.value)} required style={{ padding: 6, borderRadius: 4, border: '1px solid var(--border)' }} />
                    </label>
                    <label style={{ display: 'flex', flexDirection: 'column', gap: 4 }}>
                        <span style={{ fontSize: '0.75rem', color: 'var(--text-muted)' }}>Score (1–10)</span>
                        {/* SOURCE: CIE_v232_Developer_Amendment_Pack_v2.docx §4.1 */}
                        <input type="number" min={1} max={10} step={1} value={score} onChange={(e) => setScore(Number(e.target.value))} style={{ width: 56, padding: 6, borderRadius: 4, border: '1px solid var(--border)' }} />
                    </label>
                    <label style={{ display: 'flex', flexDirection: 'column', gap: 4, flex: '1 1 200px' }}>
                        <span style={{ fontSize: '0.75rem', color: 'var(--text-muted)' }}>Notes</span>
                        <textarea value={notes} onChange={(e) => setNotes(e.target.value)} placeholder="Optional notes" rows={1} style={{ padding: 6, borderRadius: 4, border: '1px solid var(--border)', resize: 'vertical' }} />
                    </label>
                    <button type="submit" disabled={saveBusy || score < 1 || score > 10} className="btn btn-primary" style={{ padding: '8px 14px' }}>{saveBusy ? 'Saving…' : 'Save'}</button>
                </form>
                {saveMessage && <div style={{ marginTop: 8, fontSize: '0.8rem', color: saveMessage === 'Saved.' ? 'var(--green)' : 'var(--red)' }}>{saveMessage}</div>}
            </div>

            <div className="data-table mb-16" style={{ marginTop: 16 }}>
                <table>
                    <thead>
                        <tr>
                            <th>Week start</th>
                            <th>Weekly score (1-10)</th>
                            <th>Notes</th>
                        </tr>
                    </thead>
                    <tbody>
                        {weeklyScores.length === 0 ? (
                            <tr><td colSpan={3} style={{ textAlign: 'center', color: 'var(--text-dim)', padding: 24 }}>No weekly score data available.</td></tr>
                        ) : (
                            weeklyScores.map((row) => (
                                <tr key={row.id}>
                                    <td className="mono" style={{ fontSize: '0.72rem' }}>{row.week_start}</td>
                                    <td style={{ fontWeight: 700 }}>{row.score}</td>
                                    <td style={{ fontSize: '0.75rem', color: 'var(--text-muted)' }}>{row.notes || '-'}</td>
                                </tr>
                            ))
                        )}
                    </tbody>
                </table>
            </div>

            {weeklyTrendData.length > 0 && (
                <div className="card">
                    <div style={{ fontSize: "0.7rem", fontWeight: 700, color: "var(--text)", marginBottom: 12 }}>Weekly score trend</div>
                    {/* SOURCE: CIE_v232_UI_Restructure_Instructions.docx §7 Step 5 */}
                    <TrendLine data={weeklyTrendData.map((d) => d.value)} width={420} height={65} />
                </div>
            )}
        </div>
    );
};

export default StaffKpis;
