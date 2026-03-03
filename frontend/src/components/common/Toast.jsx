import React, { useContext } from 'react';
import { AppContext } from '../../App';
import THEME from '../../theme';

const Toast = () => {
    const { notifications } = useContext(AppContext);

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

                let background = THEME.blueBg;
                let borderLeftColor = THEME.blue;
                let borderColor = THEME.blueBorder;

                if (isError) {
                    background = THEME.redBg;
                    borderLeftColor = THEME.red;
                    borderColor = THEME.redBorder;
                } else if (isSuccess) {
                    background = THEME.greenBg;
                    borderLeftColor = THEME.green;
                    borderColor = THEME.greenBorder;
                } else if (isWarning) {
                    background = THEME.amberBg;
                    borderLeftColor = THEME.amber;
                    borderColor = THEME.amberBorder;
                }

                return (
                    <div
                        key={n.id}
                        style={{
                            background,
                            color: THEME.text,
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
