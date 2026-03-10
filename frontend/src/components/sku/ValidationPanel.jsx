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
  G1_BASIC_INFO: "Basic Information",
  G2_INTENT: "Primary Intent",
  G2_IMAGES: "Images",
  G3_SEO: "SEO Metadata",
  G3_SECONDARY_INTENT: "Secondary Intents",
  G4_ANSWER_BLOCK: "Answer Block",
  G4_VECTOR: "Semantic Validation",
  G5_TECHNICAL: "Technical Specifications",
  G5_BEST_NOT_FOR: "Best-For / Not-For",
  G5_VECTOR: "Semantic Validation",
  G6_COMMERCIAL: "Commercial Data",
  G6_COMMERCIAL_POLICY: "Commercial Policy",
  G6_DESCRIPTION_QUALITY: "Description Quality",
  G7_EXPERT: "Channel Readiness",
  g1_basic_info: "Basic Information",
  g2_intent: "Primary Intent",
  g3_secondary_intent: "Secondary Intents",
  g4_answer_block: "Answer Block",
  g4_vector: "Semantic Validation",
  g5_technical: "Technical Specifications",
  g6_commercial_policy: "Commercial Policy",
  g6_description_quality: "Description Quality",
  g7_expert: "Channel Readiness",
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
