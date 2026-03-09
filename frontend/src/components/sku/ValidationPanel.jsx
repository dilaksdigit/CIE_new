import React from 'react';

// SOURCE: CIE_v232_UI_Restructure_Instructions.docx §1.5 (gate display rules)
// SOURCE: CIE_v232_Developer_Amendment_Pack_v2.docx §7 Trap 4
const GATE_LABEL_MAP = {
  g1: "Title pattern",
  g2: "Main search intent",
  g3: "Secondary intents",
  g4: "Answer block",
  g5: "Technical details",
  g6: "Commercial policy",
  g7: "Expert authority",
  vector_similarity: "Content focus",
  G1: "Title pattern",
  G2: "Main search intent",
  G3: "Secondary intents",
  G4: "Answer block",
  G5: "Technical details",
  G6: "Commercial policy",
  G7: "Expert authority",
  VECTOR_SIMILARITY: "Content focus",
};

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
                            <strong>{GATE_LABEL_MAP[gate.gate_name] ?? GATE_LABEL_MAP[gate.gate_name?.toLowerCase()] ?? "Content check"}</strong>
                            {gate.blocking && !gate.passed && <span className="blocking-badge">BLOCKING</span>}
                        </div>
                        <div className="gate-reason">{gate.user_message}</div>
                    </div>
                ))}
            </div>
        </div>
    );
}

export default ValidationPanel;
