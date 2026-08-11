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
import AssetsPage from './pages/AssetsPage';
import AssetFormPage from './pages/AssetFormPage';
import AssetDetailPage from './pages/AssetDetailPage';
import TargetsPage from './pages/TargetsPage';
import TargetFormPage from './pages/TargetFormPage';
import TargetDetailPage from './pages/TargetDetailPage';
import ScansPage from './pages/ScansPage';
import ScanFormPage from './pages/ScanFormPage';
import ScanWizardPage from './pages/ScanWizardPage';
import ScanDetailPage from './pages/ScanDetailPage';
import ScanReportPage from './pages/ScanReportPage';
import PlaceholderPage from './pages/PlaceholderPage';
import LoginPage from './pages/LoginPage';
import FindingsPage from './pages/FindingsPage';
import FindingFormPage from './pages/FindingFormPage';
import FindingDetailPage from './pages/FindingDetailPage';
import RiskDashboardPage from './pages/RiskDashboardPage';
import RiskFormPage from './pages/RiskFormPage';
import RiskDetailPage from './pages/RiskDetailPage';
import ReportsPage from './pages/ReportsPage';
import ReportDetailPage from './pages/ReportDetailPage';
import ComplianceDashboardPage from './pages/ComplianceDashboardPage';
import ComplianceDetailPage from './pages/ComplianceDetailPage';
import IntegrationsPage from './pages/IntegrationsPage';
import ConnectorPage from './pages/ConnectorPage';
import IntegrationDetailPage from './pages/IntegrationDetailPage';
import RepositoriesPage from './pages/RepositoriesPage';
import { Loader2 } from 'lucide-react';

const pageLabels = {
    repositories: 'Repositories',
    dashboard: 'Dashboard',
    assets:    'Assets',
    targets:   'Targets',
    scans:     'Scans',
    findings:  'Findings',
    risks:     'Risk Register',
    reports:   'Reports',
    compliance: 'Compliance',
    integrations: 'Integrations',
    ai:        'AI Assistant',
    users:     'Users',
    settings:  'Settings',
};

// ─── Route Wrappers to map URL params to component props ─────────────────────
function AssetDetailRoute() {
    const { id } = useParams();
    const navigate = useNavigate();
    return <AssetDetailPage assetId={id} onBack={() => navigate('/assets')} onEdit={(id) => navigate(`/assets/${id}/edit`)} />;
}

function AssetEditRoute() {
    const { id } = useParams();
    const navigate = useNavigate();
    return <AssetFormPage assetId={id} onSave={() => navigate('/assets')} onCancel={() => navigate('/assets')} />;
}

function TargetDetailRoute() {
    const { id } = useParams();
    const navigate = useNavigate();
    return <TargetDetailPage targetId={id} onBack={() => navigate('/targets')} onEdit={(id) => navigate(`/targets/${id}/edit`)} />;
}

function TargetEditRoute() {
    const { id } = useParams();
    const navigate = useNavigate();
    return <TargetFormPage targetId={id} onSave={() => navigate('/targets')} onCancel={() => navigate('/targets')} />;
}

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

function RiskDetailRoute() {
    const { id } = useParams();
    const navigate = useNavigate();
    return <RiskDetailPage riskId={id} onBack={() => navigate('/risk-register')} onEdit={(id) => navigate(`/risk-register/${id}/edit`)} />;
}

function RiskEditRoute() {
    const { id } = useParams();
    const navigate = useNavigate();
    return <RiskFormPage riskId={id} onSave={() => navigate('/risk-register')} onCancel={() => navigate('/risk-register')} />;
}

function ReportDetailRoute() {
    const { id } = useParams();
    const navigate = useNavigate();
    return <ReportDetailPage reportId={id} onBack={() => navigate('/reports')} onEdit={(id) => navigate(`/reports/${id}/edit`)} />;
}

function ComplianceDetailRoute() {
    const { framework } = useParams();
    const navigate = useNavigate();
    return <ComplianceDetailPage frameworkCode={framework} onBack={() => navigate('/compliance')} />;
}

function ConnectorRoute() {
    const { connector } = useParams();
    const navigate = useNavigate();
    return (
        <ConnectorPage 
            connectorCode={connector} 
            onBack={() => navigate('/integrations')} 
        />
    );
}

function IntegrationDetailRoute() {
    const { connector, connection } = useParams();
    const navigate = useNavigate();
    return (
        <IntegrationDetailPage 
            integrationId={connection} 
            onBack={() => navigate(`/integrations/${connector}`)} 
        />
    );
}

function MainAppLayout() {
    const { user, loading } = useAuth();
    const [sidebarOpen, setSidebarOpen] = useState(true);
    const [darkMode, setDarkMode] = useState(false);
    const navigate = useNavigate();
    const location = useLocation();

    // Map active path to sidebar active page highlighting
    const getActivePage = () => {
        const path = location.pathname;
        if (path.startsWith('/dashboard')) return 'dashboard';
        if (path.startsWith('/assets')) return 'assets';
        if (path.startsWith('/repositories')) return 'repositories';
        if (path.startsWith('/targets')) return 'targets';
        if (path.startsWith('/scans')) return 'scans';
        if (path.startsWith('/findings')) return 'findings';
        if (path.startsWith('/risk-register')) return 'risks';
        if (path.startsWith('/reports')) return 'reports';
        if (path.startsWith('/compliance')) return 'compliance';
        if (path.startsWith('/integrations')) return 'integrations';
        if (path.startsWith('/users')) return 'users';
        if (path.startsWith('/settings')) return 'settings';
        return 'dashboard';
    };

    const handleNavigate = (page) => {
        if (page === 'risks') {
            navigate('/risk-register');
        } else {
            navigate(`/${page}`);
        }
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
                            
                            {/* Assets */}
                            <Route path="/assets" element={<AssetsPage onNavigateToCreate={() => navigate('/assets/new')} onNavigateToEdit={(id) => navigate(`/assets/${id}/edit`)} onNavigateToDetail={(id) => navigate(`/assets/${id}`)} />} />
                            <Route path="/assets/new" element={<AssetFormPage onSave={() => navigate('/assets')} onCancel={() => navigate('/assets')} />} />
                            <Route path="/assets/:id" element={<AssetDetailRoute />} />
                            <Route path="/assets/:id/edit" element={<AssetEditRoute />} />

                            {/* Repositories */}
                            <Route path="/repositories" element={<RepositoriesPage />} />

                            {/* Targets */}
                            <Route path="/targets" element={<TargetsPage onNavigateToCreate={() => navigate('/targets/new')} onNavigateToEdit={(id) => navigate(`/targets/${id}/edit`)} onNavigateToDetail={(id) => navigate(`/targets/${id}`)} />} />
                            <Route path="/targets/new" element={<TargetFormPage onSave={() => navigate('/targets')} onCancel={() => navigate('/targets')} />} />
                            <Route path="/targets/:id" element={<TargetDetailRoute />} />
                            <Route path="/targets/:id/edit" element={<TargetEditRoute />} />

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

                            {/* Risk Register */}
                            <Route path="/risk-register" element={<RiskDashboardPage onNavigateToCreate={() => navigate('/risk-register/new')} onNavigateToEdit={(id) => navigate(`/risk-register/${id}/edit`)} onNavigateToDetail={(id) => navigate(`/risk-register/${id}`)} />} />
                            <Route path="/risk-register/new" element={<RiskFormPage onSave={() => navigate('/risk-register')} onCancel={() => navigate('/risk-register')} />} />
                            <Route path="/risk-register/:id" element={<RiskDetailRoute />} />
                            <Route path="/risk-register/:id/edit" element={<RiskEditRoute />} />

                            {/* Reports */}
                            <Route path="/reports" element={<ReportsPage onNavigateToCreate={() => {}} onNavigateToEdit={(id) => navigate(`/reports/${id}/edit`)} onNavigateToDetail={(id) => navigate(`/reports/${id}`)} />} />
                            <Route path="/reports/:id" element={<ReportDetailRoute />} />

                            {/* Compliance */}
                            <Route path="/compliance" element={<ComplianceDashboardPage onNavigateToDetail={(code) => navigate(`/compliance/${code}`)} />} />
                            <Route path="/compliance/:framework" element={<ComplianceDetailRoute />} />

                            {/* Integrations */}
                            <Route path="/integrations" element={<IntegrationsPage onNavigateToConnector={(connector) => navigate(`/integrations/${connector.code}`)} onNavigateToDetail={(conn) => navigate(`/integrations/${conn.code}/${conn.uuid}`)} />} />
                            <Route path="/integrations/:connector" element={<ConnectorRoute />} />
                            <Route path="/integrations/:connector/:connection" element={<IntegrationDetailRoute />} />

                            {/* Users & Settings */}
                            <Route path="/users" element={<PlaceholderPage title="Users" />} />
                            <Route path="/settings" element={<PlaceholderPage title="Settings" />} />
                            
                            <Route path="*" element={<Navigate to="/dashboard" replace />} />
                        </Routes>
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
