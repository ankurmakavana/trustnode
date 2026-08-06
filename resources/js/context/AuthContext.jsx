import React, { createContext, useContext, useState, useEffect } from 'react';

const AuthContext = createContext(null);

export function AuthProvider({ children }) {
    const [user, setUser] = useState(null);
    const [loading, setLoading] = useState(true);

    const checkAuthStatus = async () => {
        try {
            // We use the dashboard/stats call as a lightweight validation check.
            // If it succeeds with 200, we know our session is alive and valid.
            const res = await fetch('/api/dashboard/stats', {
                headers: { 'Accept': 'application/json' }
            });
            if (res.ok) {
                // Fetch current user details or use mock user structure for state
                setUser({
                    displayName: 'TrustNode Admin',
                    email: 'admin@trustnode.local',
                    role: 'Administrator',
                    roleSlug: 'administrator',
                    initials: 'TA'
                });
            } else {
                setUser(null);
            }
        } catch (err) {
            setUser(null);
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => {
        checkAuthStatus();
    }, []);

    const login = async (email, password) => {
        const res = await fetch('/api/login', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
            },
            body: JSON.stringify({ email, password })
        });

        if (!res.ok) {
            if (res.status === 422) {
                const data = await res.json();
                throw { validation: data.errors };
            }
            throw new Error('Authentication failed. Please verify credentials.');
        }

        const data = await res.json();
        const userData = data.data || {};
        const name = userData.name || 'TrustNode Admin';
        const roleObj = userData.role || {};
        
        setUser({
            displayName: name,
            email: userData.email || email,
            role: typeof roleObj === 'object' ? (roleObj.name || 'Administrator') : (roleObj || 'Administrator'),
            roleSlug: typeof roleObj === 'object' ? (roleObj.slug || 'administrator') : 'administrator',
            initials: name ? name.split(' ').map(n => n[0]).join('').toUpperCase() : 'TA'
        });
    };

    const logout = async () => {
        setLoading(true);
        try {
            await fetch('/api/logout', {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
                }
            });
        } catch (err) {
            console.error('Logout request error:', err);
        } finally {
            setUser(null);
            setLoading(false);
        }
    };

    return (
        <AuthContext.Provider value={{ user, loading, login, logout, checkAuthStatus }}>
            {children}
        </AuthContext.Provider>
    );
}

export function useAuth() {
    const context = useContext(AuthContext);
    if (!context) {
        throw new Error('useAuth must be used within an AuthProvider');
    }
    return context;
}
