import React from 'react';
import useStore from '../../store';

const Toast = () => {
    const { notifications } = useStore();

    if (notifications.length === 0) return null;

    return (
        <div style={{
            position: 'fixed', bottom: '24px', right: '24px',
            display: 'flex', flexDirection: 'column', gap: '8px', zIndex: 1000,
        }}>
            {notifications.map(n => (
                <div key={n.id} style={{
                    background: n.type === 'error' ? 'rgba(239,68,68,0.95)' :
                        n.type === 'success' ? 'rgba(16,185,129,0.95)' : 'rgba(99,102,241,0.95)',
                    color: 'white', padding: '12px 20px', borderRadius: '10px',
                    fontSize: '14px', fontWeight: 500, boxShadow: '0 8px 24px rgba(0,0,0,0.4)',
                    animation: 'fadeIn 0.3s ease',
                }}>
                    {n.message}
                </div>
            ))}
        </div>
    );
};

export default Toast;
