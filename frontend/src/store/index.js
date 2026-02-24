import { create } from 'zustand';

const getStoredUser = () => {
    try {
        const u = sessionStorage.getItem('cie_user');
        return u ? JSON.parse(u) : null;
    } catch {
        return null;
    }
};
const getStoredToken = () => sessionStorage.getItem('cie_token') || null;

const useStore = create((set, get) => ({
    // Auth state (sessionStorage per spec — not localStorage)
    user: getStoredUser(),
    token: getStoredToken(),
    isAuthenticated: !!getStoredToken(),

    login: (user, token) => {
        sessionStorage.setItem('cie_token', token);
        sessionStorage.setItem('cie_user', JSON.stringify(user));
        set({ user, token, isAuthenticated: true });
    },

    logout: () => {
        sessionStorage.removeItem('cie_token');
        sessionStorage.removeItem('cie_user');
        set({ user: null, token: null, isAuthenticated: false });
    },

    // SKU state
    skus: [],
    selectedSku: null,
    skuLoading: false,

    setSkus: (skus) => set({ skus }),
    setSelectedSku: (sku) => set({ selectedSku: sku }),
    setSkuLoading: (loading) => set({ skuLoading: loading }),

    // Notifications
    notifications: [],
    addNotification: (notification) => {
        const id = Date.now();
        set((state) => ({
            notifications: [...state.notifications, { ...notification, id }]
        }));
        setTimeout(() => {
            set((state) => ({
                notifications: state.notifications.filter(n => n.id !== id)
            }));
        }, 4000);
    },
}));

export default useStore;
