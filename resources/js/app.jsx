import './bootstrap';
import '../css/app.css';
import React, { useState } from 'react';
import { createRoot } from 'react-dom/client';
import { BrowserRouter, Routes, Route, Navigate, useNavigate, useParams, useLocation } from 'react-router-dom';
import { AuthProvider, useAuth } from './context/AuthContext';
import ErrorBoundary from './components/ErrorBoundary';
import Sidebar from './components/Sidebar';
import Header from './components/Header';
import DashboardPage from './pages/DashboardPage';
import ScansPage from './pages/ScansPage';
import ScanFormPage from './pages/ScanFormPage';
import ScanWizardPage from './pages/ScanWizardPage';
import ScanDetailPage from './pages/ScanDetailPage';
import ScanReportPage from './pages/ScanReportPage';
import SettingsPage from './pages/SettingsPage';
import AgentPage from './pages/AgentPage';
import PermissionsPage from './pages/PermissionsPage';
import LoginPage from './pages/LoginPage';
import SetupPage from './pages/SetupPage';
import FindingsPage from './pages/FindingsPage';
import FindingFormPage from './pages/FindingFormPage';
import FindingDetailPage from './pages/FindingDetailPage';
import ReportsPage from './pages/ReportsPage';
import ReportDetailPage from './pages/ReportDetailPage';
import { Loader2 } from 'lucide-react';

const pageLabels = {
    dashboard: 'Dashboard',
    scans:     'Scans',
    findings:  'Findings',
    reports:   'Reports',
    agent:     'Agent',
    permissions: 'Permissions',
    settings:  'Settings',
};

// ─── Route Wrappers to map URL params to component props ─────────────────────

function ScanDetailRoute() {
    const { id } = useParams();
    const navigate = useNavigate();
    return (
        <ScanDetailPage 
            scanId={id} 
            onBack={() => navigate('/scans')} 
            onEdit={(id) => navigate(`/scans/${id}/edit`)} 
            onReport={(id) => navigate(`/scans/${id}/report`)} 
        />
    );
}

// ─── ScanEditRoute ───────────────────────────────────────────────────────────
function ScanEditRoute() {
    const { id } = useParams();
    const navigate = useNavigate();
    return <ScanFormPage scanId={id} onSave={() => navigate('/scans')} onCancel={() => navigate('/scans')} />;
}

function ScanReportRoute() {
    const { id } = useParams();
    const navigate = useNavigate();
    return <ScanReportPage scanId={id} onBack={() => navigate('/scans')} onViewDetail={(id) => navigate(`/findings/${id}`)} />;
}

function FindingDetailRoute() {
    const { id } = useParams();
    const navigate = useNavigate();
    return <FindingDetailPage findingId={id} onBack={() => navigate('/findings')} onEdit={(id) => navigate(`/findings/${id}/edit`)} />;
}

function FindingEditRoute() {
    const { id } = useParams();
    const navigate = useNavigate();
    return <FindingFormPage findingId={id} onSave={() => navigate('/findings')} onCancel={() => navigate('/findings')} />;
}

function ReportDetailRoute() {
    const { id } = useParams();
    const navigate = useNavigate();
    return <ReportDetailPage reportId={id} onBack={() => navigate('/reports')} onEdit={(id) => navigate(`/reports/${id}/edit`)} />;
}

function MainAppLayout() {
    const { user, loading, setupRequired } = useAuth();
    const [sidebarOpen, setSidebarOpen] = useState(true);
    const [darkMode, setDarkMode] = useState(false);
    const navigate = useNavigate();
    const location = useLocation();

    const getActivePage = () => {
        const path = location.pathname;
        if (path.startsWith('/dashboard')) return 'dashboard';
        if (path.startsWith('/scans')) return 'scans';
        if (path.startsWith('/findings')) return 'findings';
        if (path.startsWith('/reports')) return 'reports';
        if (path.startsWith('/agent')) return 'agent';
        if (path.startsWith('/permissions')) return 'permissions';
        if (path.startsWith('/settings')) return 'settings';
        return 'dashboard';
    };

    const handleNavigate = (page) => {
        navigate(`/${page}`);
    };

    if (loading) {
        return (
            <div className="min-h-screen bg-slate-50 flex flex-col items-center justify-center gap-3">
                <Loader2 className="animate-spin text-brand-600" size={32} />
                <span className="text-xs font-semibold text-slate-500">Initializing session...</span>
            </div>
        );
    }
    if (!user) {
        if (setupRequired) {
            return <SetupPage />;
        }
        return <LoginPage />;
    }

    const activePage = getActivePage();

    return (
        <div className="flex h-screen overflow-hidden bg-slate-50">
            {/* Sidebar */}
            <Sidebar
                activePage={activePage}
                onNavigate={handleNavigate}
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
                        <Routes>
                            <Route path="/" element={<Navigate to="/dashboard" replace />} />
                            <Route path="/dashboard" element={<DashboardPage />} />

                            {/* Scans */}
                            <Route path="/scans" element={<ScansPage onNavigateToCreate={() => navigate('/scans/new')} onNavigateToEdit={(id) => navigate(`/scans/${id}/edit`)} onNavigateToDetail={(id) => navigate(`/scans/${id}`)} onNavigateToReport={(id) => navigate(`/scans/${id}/report`)} />} />
                            <Route path="/scans/new" element={<ScanWizardPage onSave={() => navigate('/scans')} onCancel={() => navigate('/scans')} />} />
                            <Route path="/scans/:id" element={<ScanDetailRoute />} />
                            <Route path="/scans/:id/edit" element={<ScanEditRoute />} />
                            <Route path="/scans/:id/report" element={<ScanReportRoute />} />

                            {/* Findings */}
                            <Route path="/findings" element={<FindingsPage onNavigateToCreate={() => navigate('/findings/new')} onNavigateToEdit={(id) => navigate(`/findings/${id}/edit`)} onNavigateToDetail={(id) => navigate(`/findings/${id}`)} />} />
                            <Route path="/findings/new" element={<FindingFormPage onSave={() => navigate('/findings')} onCancel={() => navigate('/findings')} />} />
                            <Route path="/findings/:id" element={<FindingDetailRoute />} />
                            <Route path="/findings/:id/edit" element={<FindingEditRoute />} />

                            {/* Reports */}
                            <Route path="/reports" element={<ReportsPage onNavigateToCreate={() => {}} onNavigateToEdit={(id) => navigate(`/reports/${id}/edit`)} onNavigateToDetail={(id) => navigate(`/reports/${id}`)} />} />
                            <Route path="/reports/:id" element={<ReportDetailRoute />} />

                            {/* Agent */}
                            <Route path="/agent" element={<AgentPage />} />

                            {/* Permissions */}
                            <Route path="/permissions" element={<PermissionsPage />} />

                            {/* Settings */}
                            <Route path="/settings" element={<SettingsPage />} />
                            
                            <Route path="*" element={<Navigate to="/dashboard" replace />} />
                        </Routes>
                    </div>
                </main>

                {/* Footer */}
                <footer className="shrink-0 border-t border-slate-200 bg-white px-5 py-2.5 flex items-center justify-between">
                    <span className="text-xs text-slate-400">
                        TrustNode · v1.0.0-foundation
                    </span>
                    <span className="text-xs text-slate-400">
                        © 2026 TrustNode · Developer Security Console
                    </span>
                </footer>
            </div>
        </div>
    );
}

export default function App() {
    return (
        <ErrorBoundary>
            <BrowserRouter>
                <MainAppLayout />
            </BrowserRouter>
        </ErrorBoundary>
    );
}

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
