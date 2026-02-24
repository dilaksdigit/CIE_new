import React from 'react';
import useStore from '../../store';

const C = {
  bg:           "#FAFAF8",
  surface:      "#FFFFFF",
  muted:        "#F5F4F1",
  border:       "#E5E3DE",
  text:         "#2D2B28",
  textMid:      "#6B6860",
  textLight:    "#9B978F",
  accent:       "#5B7A3A",
  accentLight:  "#EEF2E8",
  accentBorder: "#C5D4B0",
  hero:         "#8B6914",
  heroBg:       "#FDF6E3",
  heroBorder:   "#E8D5A0",
  support:      "#3D6B8E",
  supportBg:    "#EBF3F9",
  supportBorder:"#B5D0E3",
  harvest:      "#9E7C1A",
  harvestBg:    "#FFF8E7",
  harvestBorder:"#E8D49A",
  kill:         "#A63D2F",
  killBg:       "#FDEEEB",
  killBorder:   "#E5B5AD",
  green:        "#2E7D32",
  greenBg:      "#E8F5E9",
  greenBorder:  "#A5D6A7",
  red:          "#C62828",
  redBg:        "#FFEBEE",
  redBorder:    "#EF9A9A",
  amber:        "#E65100",
  amberBg:      "#FFFDE7",
  amberBorder:  "#FFCC80",
  blue:         "#1565C0",
  blueBg:       "#E3F2FD",
  blueBorder:   "#90CAF9",
};

const Toast = () => {
    const { notifications } = useStore();

    if (notifications.length === 0) return null;

    return (
        <div style={{
            position: 'fixed', bottom: '24px', right: '24px',
            display: 'flex', flexDirection: 'column', gap: '8px', zIndex: 1000,
        }}>
            {notifications.map((n) => {
                const isError = n.type === 'error';
                const isSuccess = n.type === 'success';
                const isWarning = n.type === 'warning';

                let background = C.blueBg;
                let borderLeftColor = C.blue;
                let borderColor = C.blueBorder;

                if (isError) {
                    background = C.redBg;
                    borderLeftColor = C.red;
                    borderColor = C.redBorder;
                } else if (isSuccess) {
                    background = C.greenBg;
                    borderLeftColor = C.green;
                    borderColor = C.greenBorder;
                } else if (isWarning) {
                    background = C.amberBg;
                    borderLeftColor = C.amber;
                    borderColor = C.amberBorder;
                }

                return (
                    <div
                        key={n.id}
                        style={{
                            background,
                            color: C.text,
                            padding: '12px 20px',
                            borderRadius: '10px',
                            fontSize: '14px',
                            fontWeight: 500,
                            boxShadow: '0 2px 8px rgba(0,0,0,0.04)',
                            animation: 'fadeIn 0.3s ease',
                            border: `1px solid ${borderColor}`,
                            borderLeft: `4px solid ${borderLeftColor}`,
                        }}
                    >
                        {n.message}
                    </div>
                );
            })}
        </div>
    );
};

export default Toast;
