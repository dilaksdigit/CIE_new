// SOURCE: CIE_v232_Hardening_Addendum.pdf §6.2 / §6.3
import React from 'react';

export function HiddenFieldSlot({ fieldName, tier, tooltips }) {
    const message = tooltips[fieldName]?.[tier];
    if (!message) return null;
    return (
        <div className="cie-hidden-field-info" style={{
            padding: "10px 14px",
            background: "#F5F5F5",
            border: "1px dashed #BDBDBD",
            borderRadius: 4,
            fontSize: "0.78rem",
            color: "#757575",
            marginBottom: 12
        }}>
            {message}
        </div>
    );
}

export default HiddenFieldSlot;
