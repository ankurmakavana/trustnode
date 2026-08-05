import './bootstrap';
import '../css/app.css';

import React from 'react';
import { createRoot } from 'react-dom/client';
import App from './App';
import { AuthProvider } from './context/AuthContext';

const root = document.getElementById('app');
if (root) {
    createRoot(root).render(
        <React.StrictMode>
            <AuthProvider>
                <App />
            </AuthProvider>
        </React.StrictMode>
    );
}
