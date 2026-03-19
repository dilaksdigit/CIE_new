// SOURCE: CLAUDE.md §13 — Leadership / Reviewer Semrush view (read-only). Three zones: Rank Movement, Competitor Gaps, Quick Wins.
// Palette: CLAUDE.md §8 — page bg #FAFAFA, surface #FFFFFF, table headers #1F2D54 white text, alternating rows.

import React, { useState, useEffect, useContext } from 'react';
import { AppContext } from '../../App';
import { semrushImportApi } from '../../services/api';

const PAGE_BG = '#FAFAFA';
const SURFACE = '#FFFFFF';
const TABLE_HEADER_BG = '#1F2D54';
const TABLE_HEADER_TEXT = '#FFFFFF';

const ReviewSemrush = () => {
  const { user } = useContext(AppContext);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const [rankMovement, setRankMovement] = useState([]);
  const [competitorGaps, setCompetitorGaps] = useState({});
  const [quickWins, setQuickWins] = useState([]);
  const [batchLabel, setBatchLabel] = useState('');

  useEffect(() => {
    let cancelled = false;
    const load = async () => {
      setLoading(true);
      setError(null);
      try {
        const [movRes, gapRes, winsRes, historyRes] = await Promise.all([
          semrushImportApi.latest({ filter: 'rank_movement' }),
          semrushImportApi.latest({ filter: 'competitor_gaps' }),
          semrushImportApi.latest({ filter: 'quick_wins' }),
          semrushImportApi.latest(),
        ]);
        if (cancelled) return;
        const movPayload = movRes.data?.data ?? movRes.data ?? {};
        const gapPayload = gapRes.data?.data ?? gapRes.data ?? {};
        const winsPayload = winsRes.data?.data ?? winsRes.data ?? {};
        const histPayload = historyRes.data?.data ?? historyRes.data ?? {};
        setRankMovement(Array.isArray(movPayload.rows) ? movPayload.rows : []);
        setCompetitorGaps(typeof gapPayload.by_sku === 'object' && gapPayload.by_sku !== null ? gapPayload.by_sku : {});
        setQuickWins(Array.isArray(winsPayload.rows) ? winsPayload.rows : []);
        const history = Array.isArray(histPayload.history) ? histPayload.history : [];
        setBatchLabel(history[0]?.import_batch ? `Latest batch: ${history[0].import_batch}` : '');
      } catch (e) {
        if (!cancelled) {
          setError(e.response?.status === 403 ? 'You do not have access to Semrush data.' : 'Failed to load Semrush review data.');
          setRankMovement([]);
          setCompetitorGaps({});
          setQuickWins([]);
        }
      } finally {
        if (!cancelled) setLoading(false);
      }
    };
    load();
    return () => { cancelled = true; };
  }, []);

  const tableHeaderStyle = {
    background: TABLE_HEADER_BG,
    color: TABLE_HEADER_TEXT,
    padding: '10px 12px',
    textAlign: 'left',
    fontSize: '0.78rem',
    fontWeight: 700,
  };
  const tableCellStyle = (index) => ({
    padding: '8px 12px',
    fontSize: '0.8rem',
    background: index % 2 === 0 ? SURFACE : '#F8F8F8',
  });

  if (loading) {
    return (
      <div style={{ padding: 40, textAlign: 'center', background: PAGE_BG, minHeight: '100%' }}>
        <p style={{ color: 'var(--text-muted)' }}>Loading Semrush review data…</p>
      </div>
    );
  }
  if (error) {
    return (
      <div style={{ padding: 40, textAlign: 'center', background: PAGE_BG, minHeight: '100%' }}>
        <p style={{ color: 'var(--red)' }}>{error}</p>
      </div>
    );
  }

  const skuCodes = Object.keys(competitorGaps).sort();

  return (
    <div style={{ background: PAGE_BG, minHeight: '100%', padding: 24 }}>
      <div style={{ marginBottom: 24 }}>
        <h1 className="page-title" style={{ marginBottom: 4 }}>Semrush Review</h1>
        <div className="page-subtitle" style={{ color: 'var(--text-muted)', fontSize: '0.85rem' }}>
          Leadership view — Rank Movement, Competitor Gaps, Quick Wins (read-only)
        </div>
        {batchLabel && (
          <div style={{ marginTop: 6, fontSize: '0.78rem', color: 'var(--text-dim)' }}>{batchLabel}</div>
        )}
      </div>

      {/* Zone 1: Rank Movement */}
      <section style={{ marginBottom: 32 }}>
        <h2 style={{ fontSize: '1rem', fontWeight: 700, color: 'var(--text)', marginBottom: 12 }}>
          1. Rank Movement
        </h2>
        <div style={{ background: SURFACE, borderRadius: 6, boxShadow: 'var(--shadow)', overflow: 'hidden', border: '1px solid var(--border)' }}>
          <table style={{ width: '100%', borderCollapse: 'collapse' }}>
            <thead>
              <tr>
                <th style={tableHeaderStyle}>Keyword</th>
                <th style={tableHeaderStyle}>SKU</th>
                <th style={tableHeaderStyle}>Position</th>
                <th style={tableHeaderStyle}>Prev</th>
                <th style={tableHeaderStyle}>Change</th>
                <th style={tableHeaderStyle}>Volume</th>
              </tr>
            </thead>
            <tbody>
              {rankMovement.length === 0 ? (
                <tr>
                  <td colSpan={6} style={{ padding: 16, fontSize: '0.8rem', color: 'var(--text-muted)', background: SURFACE }}>
                    No rank movement data for the latest batch.
                  </td>
                </tr>
              ) : (
                rankMovement.slice(0, 100).map((row, idx) => {
                  const pos = row.position != null ? Number(row.position) : null;
                  const prev = row.prev_position != null ? Number(row.prev_position) : null;
                  const change = prev != null && pos != null ? prev - pos : null;
                  return (
                    <tr key={`rm-${idx}-${row.keyword}-${row.sku_code}`}>
                      <td style={tableCellStyle(idx)}>{row.keyword ?? '—'}</td>
                      <td style={tableCellStyle(idx)}>{row.sku_code ?? '—'}</td>
                      <td style={tableCellStyle(idx)}>{pos ?? '—'}</td>
                      <td style={tableCellStyle(idx)}>{prev ?? '—'}</td>
                      <td style={tableCellStyle(idx)}>
                        {change != null ? (change > 0 ? `+${change}` : String(change)) : '—'}
                      </td>
                      <td style={tableCellStyle(idx)}>{row.search_volume ?? '—'}</td>
                    </tr>
                  );
                })
              )}
            </tbody>
          </table>
        </div>
      </section>

      {/* Zone 2: Competitor Gaps */}
      <section style={{ marginBottom: 32 }}>
        <h2 style={{ fontSize: '1rem', fontWeight: 700, color: 'var(--text)', marginBottom: 12 }}>
          2. Competitor Gaps
        </h2>
        <div style={{ background: SURFACE, borderRadius: 6, boxShadow: 'var(--shadow)', overflow: 'hidden', border: '1px solid var(--border)' }}>
          {skuCodes.length === 0 ? (
            <div style={{ padding: 16, fontSize: '0.8rem', color: 'var(--text-muted)' }}>
              No competitor gap keywords for the latest batch.
            </div>
          ) : (
            skuCodes.map((skuCode) => {
              const keywords = competitorGaps[skuCode] || [];
              return (
                <div key={skuCode} style={{ borderBottom: '1px solid var(--border)' }}>
                  <div style={{ ...tableHeaderStyle, fontSize: '0.75rem' }}>{skuCode || '—'} ({keywords.length} gap keywords)</div>
                  <table style={{ width: '100%', borderCollapse: 'collapse' }}>
                    <thead>
                      <tr>
                        <th style={{ ...tableHeaderStyle, background: '#2a3d6b', padding: '6px 12px' }}>Keyword</th>
                        <th style={{ ...tableHeaderStyle, background: '#2a3d6b', padding: '6px 12px' }}>Position</th>
                        <th style={{ ...tableHeaderStyle, background: '#2a3d6b', padding: '6px 12px' }}>Volume</th>
                      </tr>
                    </thead>
                    <tbody>
                      {keywords.slice(0, 50).map((kw, i) => (
                        <tr key={`${skuCode}-${i}-${kw.keyword}`}>
                          <td style={tableCellStyle(i)}>{kw.keyword ?? '—'}</td>
                          <td style={tableCellStyle(i)}>{kw.position ?? '—'}</td>
                          <td style={tableCellStyle(i)}>{kw.search_volume ?? '—'}</td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                  {keywords.length > 50 && (
                    <div style={{ padding: '6px 12px', fontSize: '0.72rem', color: 'var(--text-muted)', background: '#F8F8F8' }}>
                      +{keywords.length - 50} more
                    </div>
                  )}
                </div>
              );
            })
          )}
        </div>
      </section>

      {/* Zone 3: Quick Wins (position 11–30, difficulty &lt; 40, volume &gt; 500, Hero/Support) */}
      <section>
        <h2 style={{ fontSize: '1rem', fontWeight: 700, color: 'var(--text)', marginBottom: 12 }}>
          3. Quick Wins
        </h2>
        <div style={{ background: SURFACE, borderRadius: 6, boxShadow: 'var(--shadow)', overflow: 'hidden', border: '1px solid var(--border)' }}>
          <table style={{ width: '100%', borderCollapse: 'collapse' }}>
            <thead>
              <tr>
                <th style={tableHeaderStyle}>Keyword</th>
                <th style={tableHeaderStyle}>SKU</th>
                <th style={tableHeaderStyle}>Position</th>
                <th style={tableHeaderStyle}>Prev</th>
                <th style={tableHeaderStyle}>Volume</th>
              </tr>
            </thead>
            <tbody>
              {quickWins.length === 0 ? (
                <tr>
                  <td colSpan={5} style={{ padding: 16, fontSize: '0.8rem', color: 'var(--text-muted)', background: SURFACE }}>
                    No quick wins (position 11–30, difficulty &lt; 40, volume &gt; 500, Hero/Support).
                  </td>
                </tr>
              ) : (
                quickWins.map((row, idx) => (
                  <tr key={`qw-${idx}-${row.keyword}-${row.sku_code}`}>
                    <td style={tableCellStyle(idx)}>{row.keyword ?? '—'}</td>
                    <td style={tableCellStyle(idx)}>{row.sku_code ?? '—'}</td>
                    <td style={tableCellStyle(idx)}>{row.position ?? '—'}</td>
                    <td style={tableCellStyle(idx)}>{row.prev_position ?? '—'}</td>
                    <td style={tableCellStyle(idx)}>{row.search_volume ?? '—'}</td>
                  </tr>
                ))
              )}
            </tbody>
          </table>
        </div>
      </section>
    </div>
  );
};

export default ReviewSemrush;
