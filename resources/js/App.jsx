import React, { useState } from 'react';
import Sidebar from './components/Sidebar';
import Header from './components/Header';
import DashboardPage from './pages/DashboardPage';
import PlaceholderPage from './pages/PlaceholderPage';

const pageLabels = {
    dashboard: 'Dashboard',
    assets:    'Assets',
    targets:   'Targets',
    scans:     'Scans',
    findings:  'Findings',
    reports:   'Reports',
    ai:        'AI Assistant',
    users:     'Users',
    settings:  'Settings',
};

export default function App() {
    const [activePage,   setActivePage]   = useState('dashboard');
    const [sidebarOpen,  setSidebarOpen]  = useState(true);
    const [darkMode,     setDarkMode]     = useState(false);

    const renderPage = () => {
        if (activePage === 'dashboard') return <DashboardPage />;
        return <PlaceholderPage title={pageLabels[activePage] || activePage} />;
    };

    return (
        <div className="flex h-screen overflow-hidden bg-slate-50">
            {/* Sidebar */}
            <Sidebar
                activePage={activePage}
                onNavigate={setActivePage}
                collapsed={!sidebarOpen}
                onToggle={() => setSidebarOpen(v => !v)}
            />

            {/* Main content area */}
            <div className="flex flex-col flex-1 min-w-0 overflow-hidden">
                <Header
                    darkMode={darkMode}
                    onToggleDark={() => setDarkMode(v => !v)}
                    pageTitle={pageLabels[activePage] || activePage}
                />

                <main className="flex-1 overflow-y-auto">
                    <div className="max-w-screen-2xl mx-auto px-5 py-6">
                        {renderPage()}
                    </div>
                </main>

                {/* Footer */}
                <footer className="shrink-0 border-t border-slate-200 bg-white px-5 py-2.5 flex items-center justify-between">
                    <span className="text-xs text-slate-400">
                        TrustNode Platform · v1.0.0-beta
                    </span>
                    <span className="text-xs text-slate-400">
                        © 2026 TrustNode · Enterprise VAPT Platform
                    </span>
                </footer>
            </div>
        </div>
    );
}
