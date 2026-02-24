import React from 'react';
export function ValidationPanel({ results }) {
    if (!results) return null;
    return (
        <div className="validation-panel">
            <h3>Validation Results</h3>
            <div className={`overall-status ${results.overall_status?.toLowerCase()}`}>
                <strong>Status:</strong> {results.overall_status}
                {results.can_publish ? ' ✅ Ready to publish' : ' ❌ Cannot publish'}
            </div>
            <div className="gates-list">
                {results.gates?.map((gate, index) => (
                    <div key={index} className={`gate ${gate.passed ? 'passed' : 'failed'}`}>
                        <div className="gate-header">
                            <strong>{gate.gate_name}</strong>
                            {gate.blocking && !gate.passed && <span className="blocking-badge">BLOCKING</span>}
                        </div>
                        <div className="gate-reason">{gate.reason}</div>
                    </div>
                ))}
            </div>
        </div>
    );
}

export default ValidationPanel;
