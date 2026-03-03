// SOURCE: CIE_v232_Semrush_CSV_Import_Spec.docx Section 5.1 (6-zone layout)
// SOURCE: CIE_v232_Writer_View.jsx — visual theme reference
import React, { useContext, useEffect, useRef, useState } from 'react';
import THEME from '../theme';
import { AppContext } from '../App';
import { canModifyConfig } from '../lib/rbac';
import { semrushImportApi } from '../services/api';

const MAX_FILE_SIZE_BYTES = 10 * 1024 * 1024; // 10MB

const SemrushImport = () => {
    const { user, addNotification } = useContext(AppContext);
    const isAdmin = canModifyConfig(user);

    const [file, setFile] = useState(null);
    const [fileError, setFileError] = useState('');
    const [status, setStatus] = useState(null); // { type: 'success' | 'error', message: string }
    const [busy, setBusy] = useState(false);

    const [latestImport, setLatestImport] = useState(null);
    const [loadingHistory, setLoadingHistory] = useState(true);
    const [historyError, setHistoryError] = useState('');
    const [deleteBusy, setDeleteBusy] = useState(false);

    const inputRef = useRef(null);

    const hasImports = !!latestImport && latestImport.import_batch && latestImport.row_count > 0;

    useEffect(() => {
        const loadLatest = async () => {
            setLoadingHistory(true);
            setHistoryError('');
            try {
                const res = await semrushImportApi.latest();
                const data = res.data?.data ?? res.data ?? {};
                setLatestImport({
                    import_batch: data.import_batch || null,
                    row_count: typeof data.row_count === 'number' ? data.row_count : 0,
                });
            } catch (e) {
                setHistoryError('Failed to load import history.');
            } finally {
                setLoadingHistory(false);
            }
        };
        if (isAdmin) {
            loadLatest();
        } else {
            setLoadingHistory(false);
        }
    }, [isAdmin]);

    const validateFile = (f) => {
        if (!f) return 'Please select a CSV file to upload.';
        if (!f.name.toLowerCase().endsWith('.csv')) {
            return 'Only .csv files are supported.';
        }
        if (f.size > MAX_FILE_SIZE_BYTES) {
            return 'File is too large. Maximum size is 10MB.';
        }
        return '';
    };

    const handleFileSelected = (f) => {
        setStatus(null);
        const error = validateFile(f);
        if (error) {
            setFile(null);
            setFileError(error);
            return;
        }
        setFileError('');
        setFile(f);
    };

    const handleInputChange = (e) => {
        const f = e.target.files && e.target.files[0];
        if (f) {
            handleFileSelected(f);
        }
    };

    const handleDrop = (e) => {
        e.preventDefault();
        e.stopPropagation();
        if (!isAdmin) return;
        const f = e.dataTransfer.files && e.dataTransfer.files[0];
        if (f) {
            handleFileSelected(f);
        }
    };

    const handleDragOver = (e) => {
        e.preventDefault();
        e.stopPropagation();
    };

    const handleBrowseClick = () => {
        if (!isAdmin) return;
        if (inputRef.current) {
            inputRef.current.click();
        }
    };

    const handleImport = async () => {
        if (!isAdmin) {
            addNotification({ type: 'error', message: 'Only admins can import Semrush data.' });
            return;
        }
        const error = validateFile(file);
        if (error) {
            setFileError(error);
            return;
        }
        setBusy(true);
        setStatus(null);
        try {
            // Optional FileReader usage per spec (can be used for client-side preview/logging)
            const reader = new FileReader();
            reader.readAsText(file);

            const res = await semrushImportApi.importFile(file);
            const payload = res.data?.data ?? res.data ?? {};
            const rowsImported = payload.rows_imported ?? 0;
            setStatus({
                type: 'success',
                message: `Import completed successfully. ${rowsImported} rows imported.`,
            });
            addNotification({ type: 'success', message: 'Semrush CSV imported successfully.' });
            setFile(null);
            if (inputRef.current) {
                inputRef.current.value = '';
            }
            // Refresh latest history
            try {
                const latestRes = await semrushImportApi.latest();
                const data = latestRes.data?.data ?? latestRes.data ?? {};
                setLatestImport({
                    import_batch: data.import_batch || null,
                    row_count: typeof data.row_count === 'number' ? data.row_count : 0,
                });
            } catch {
                // ignore, already have previous history
            }
        } catch (e) {
            const resp = e?.response;
            const message =
                resp?.data?.message ||
                resp?.data?.error ||
                'Import failed. Please check the CSV file and try again.';
            setStatus({ type: 'error', message });
            setFileError('');
        } finally {
            setBusy(false);
        }
    };

    const handleDeleteBatch = async () => {
        if (!isAdmin || !latestImport || !latestImport.import_batch) return;
        setDeleteBusy(true);
        try {
            await semrushImportApi.deleteBatch(latestImport.import_batch);
            addNotification({ type: 'success', message: `Deleted Semrush import for ${latestImport.import_batch}.` });
            setLatestImport({ import_batch: null, row_count: 0 });
        } catch (e) {
            setStatus({
                type: 'error',
                message: 'Failed to delete import batch.',
            });
        } finally {
            setDeleteBusy(false);
        }
    };

    if (!isAdmin) {
        return (
            <div style={{ padding: 30, maxWidth: 720 }}>
                <h1 className="page-title">Semrush Data Import</h1>
                <div className="page-subtitle">Admin only — upload Semrush Organic Research CSV exports.</div>
                <div
                    className="card"
                    style={{
                        marginTop: 16,
                        padding: 16,
                        border: `1px solid ${THEME.redBorder}`,
                        background: THEME.redBg,
                        color: THEME.red,
                        fontSize: '0.8rem',
                    }}
                >
                    🔒 You do not have permission to access this screen. Only admins can import Semrush data.
                </div>
            </div>
        );
    }

    return (
        <div style={{ maxWidth: 960 }}>
            {/* Zone Header */}
            <div style={{ marginBottom: 16 }}>
                <h1 className="page-title">Semrush Data Import</h1>
                <div className="page-subtitle">
                    Upload a CSV export from Semrush Organic Research → Positions. Refreshes keyword and competitor gap cards for all writers.
                </div>
            </div>

            {/* Zone A — Upload */}
            <div className="card" style={{ marginBottom: 16 }}>
                <div style={{ fontSize: '0.78rem', fontWeight: 700, color: THEME.text, marginBottom: 8 }}>
                    Upload CSV
                </div>
                <div
                    onDrop={handleDrop}
                    onDragOver={handleDragOver}
                    onClick={handleBrowseClick}
                    style={{
                        border: `1px dashed ${THEME.border}`,
                        borderRadius: 6,
                        padding: 20,
                        textAlign: 'center',
                        background: THEME.surface,
                        cursor: 'pointer',
                        marginBottom: 8,
                    }}
                >
                    <div style={{ fontSize: '0.8rem', color: THEME.text }}>
                        Drop a .csv file here, or <span style={{ color: THEME.accent, textDecoration: 'underline' }}>click to browse</span>.
                    </div>
                    <div style={{ marginTop: 4, fontSize: '0.7rem', color: THEME.textMid }}>Maximum size: 10MB. Semrush Organic Research → Positions export only.</div>
                    {file && (
                        <div style={{ marginTop: 10, fontSize: '0.72rem', color: THEME.text }}>
                            Selected file: <strong>{file.name}</strong> ({(file.size / 1024).toFixed(1)} KB)
                        </div>
                    )}
                </div>
                <input
                    ref={inputRef}
                    type="file"
                    accept=".csv,text/csv"
                    style={{ display: 'none' }}
                    onChange={handleInputChange}
                />
                {fileError && (
                    <div style={{ marginTop: 4, fontSize: '0.7rem', color: THEME.red }}>
                        {fileError}
                    </div>
                )}
                <div style={{ marginTop: 12, textAlign: 'right' }}>
                    <button
                        type="button"
                        className="btn btn-primary"
                        onClick={handleImport}
                        disabled={!file || busy}
                    >
                        {busy ? 'Validating and Importing…' : 'Validate and Import'}
                    </button>
                </div>
            </div>

            {/* Zone B — Status */}
            <div className="card" style={{ marginBottom: 16 }}>
                <div style={{ fontSize: '0.78rem', fontWeight: 700, color: THEME.text, marginBottom: 8 }}>
                    Status
                </div>
                {status ? (
                    <div
                        style={{
                            padding: 10,
                            borderRadius: 6,
                            border:
                                status.type === 'success'
                                    ? `1px solid ${THEME.greenBorder}`
                                    : `1px solid ${THEME.redBorder}`,
                            background:
                                status.type === 'success'
                                    ? THEME.greenBg
                                    : THEME.redBg,
                            color: status.type === 'success' ? THEME.green : THEME.red,
                            fontSize: '0.78rem',
                        }}
                    >
                        {status.message}
                    </div>
                ) : (
                    <div style={{ fontSize: '0.76rem', color: THEME.textMid }}>
                        No import has been run in this session yet.
                    </div>
                )}
            </div>

            {/* Zone C — Import History + Zone D Empty State */}
            <div className="card">
                <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 8 }}>
                    <div style={{ fontSize: '0.78rem', fontWeight: 700, color: THEME.text }}>
                        Import History
                    </div>
                </div>

                {loadingHistory ? (
                    <div style={{ padding: 8, fontSize: '0.76rem', color: THEME.textMid }}>
                        Loading import history…
                    </div>
                ) : hasImports ? (
                    <div style={{ overflowX: 'auto' }}>
                        <table style={{ width: '100%', borderCollapse: 'collapse', fontSize: '0.76rem' }}>
                            <thead>
                                <tr style={{ textAlign: 'left', borderBottom: `1px solid ${THEME.border}` }}>
                                    <th style={{ padding: '6px 4px' }}>Date</th>
                                    <th style={{ padding: '6px 4px' }}>Keywords imported</th>
                                    <th style={{ padding: '6px 4px' }}>Uploaded by</th>
                                    <th style={{ padding: '6px 4px' }}>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td style={{ padding: '6px 4px' }}>{latestImport.import_batch}</td>
                                    <td style={{ padding: '6px 4px' }}>{latestImport.row_count}</td>
                                    <td style={{ padding: '6px 4px' }}>Admin</td>
                                    <td style={{ padding: '6px 4px' }}>
                                        <button
                                            type="button"
                                            className="btn btn-secondary btn-sm"
                                            onClick={handleDeleteBatch}
                                            disabled={deleteBusy}
                                        >
                                            {deleteBusy ? 'Deleting…' : 'Delete'}
                                        </button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                        {historyError && (
                            <div style={{ marginTop: 6, fontSize: '0.72rem', color: THEME.red }}>
                                {historyError}
                            </div>
                        )}
                    </div>
                ) : (
                    // Zone D — Empty State Guidance
                    <div
                        style={{
                            padding: 12,
                            borderRadius: 6,
                            background: THEME.muted,
                            border: `1px dashed ${THEME.border}`,
                            fontSize: '0.76rem',
                            color: THEME.textMid,
                        }}
                    >
                        No keyword data yet. Upload a Semrush CSV export above.
                    </div>
                )}
            </div>
        </div>
    );
};

export default SemrushImport;

