import React, { createContext, useContext, useState, useEffect } from 'react';

const AuthContext = createContext(null);

/**
 * Read the CSRF token injected by Laravel into the page meta tag.
 * app.blade.php must contain: <meta name="csrf-token" content="{{ csrf_token() }}">
 */
function getCsrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
}

/**
 * Build a normalised user object from the /api/auth/me response payload.
 */
function buildUser(data) {
    const name    = data.name  || 'TrustNode User';
    const roleObj = data.role  || {};
    return {
        uuid:        data.uuid  || null,
        displayName: name,
        email:       data.email || '',
        status:      data.status || 'active',
        role:        typeof roleObj === 'object' ? (roleObj.name || 'Administrator') : (roleObj || 'Administrator'),
        roleSlug:    typeof roleObj === 'object' ? (roleObj.slug || 'administrator') : 'administrator',
        initials:    name.split(' ').map(n => n[0]).join('').toUpperCase().slice(0, 2) || 'TN',
    };
}

export function AuthProvider({ children }) {
    const [user,    setUser]    = useState(null);
    const [loading, setLoading] = useState(true);

    /**
     * Check current session by calling the public /api/auth/me endpoint.
     *
     * This endpoint ALWAYS returns HTTP 200:
     *   - { authenticated: true,  data: { ... } }  → user is logged in
     *   - { authenticated: false }                  → no active session
     *
     * This means the browser console will NEVER show a 401 on page load.
     */
    const checkAuthStatus = async () => {
        try {
            const res = await fetch('/api/auth/me', {
                credentials: 'include',
                headers:     { 'Accept': 'application/json' },
            });

            if (res.ok) {
                const json = await res.json();
                if (json.authenticated && json.data) {
                    setUser(buildUser(json.data));
                } else {
                    setUser(null);
                }
            } else {
                setUser(null);
            }
        } catch {
            setUser(null);
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => {
        checkAuthStatus();
    }, []);

    /**
     * Submit credentials to /api/login.
     * credentials:'include' ensures the browser stores the session cookie
     * returned by Set-Cookie in the response headers.
     */
    const login = async (email, password) => {
        const res = await fetch('/api/login', {
            method:      'POST',
            credentials: 'include',
            headers: {
                'Content-Type': 'application/json',
                'Accept':       'application/json',
                'X-CSRF-TOKEN': getCsrfToken(),
            },
            body: JSON.stringify({ email, password }),
        });

        if (!res.ok) {
            if (res.status === 422) {
                const data = await res.json();
                throw { validation: data.errors };
            }
            const data = await res.json().catch(() => ({}));
            throw new Error(data.message || 'Authentication failed. Please verify your credentials.');
        }

        const data     = await res.json();
        const userData = data.data || {};
        setUser(buildUser(userData));
    };

    /**
     * Terminate the current session on the backend and clear local state.
     * credentials:'include' ensures the session cookie is sent so Laravel
     * can invalidate the correct session row.
     */
    const logout = async () => {
        setLoading(true);
        try {
            await fetch('/api/logout', {
                method:      'POST',
                credentials: 'include',
                headers: {
                    'Accept':       'application/json',
                    'X-CSRF-TOKEN': getCsrfToken(),
                },
            });
        } catch (err) {
            // Network error — still clear local state so the UI resets
            console.error('Logout request failed:', err);
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
