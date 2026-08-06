import './bootstrap';
import '../css/app.css';
import React, { useState } from 'react';
import { createRoot } from 'react-dom/client';
import { AuthProvider } from './context/AuthContext';
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
import { useAuth } from './context/AuthContext';
import { Loader2 } from 'lucide-react';

const pageLabels = {
    dashboard: 'Dashboard',
    assets:    'Assets',
    targets:   'Targets',
    scans:     'Scans',
    findings:  'Findings',
    risks:     'Risk Register',
    reports:   'Reports',
    ai:        'AI Assistant',
    users:     'Users',
    settings:  'Settings',
};

export default function App() {
    const { user, loading } = useAuth();
    const [activePage,   setActivePage]   = useState('dashboard');
    const [sidebarOpen,  setSidebarOpen]  = useState(true);
    const [darkMode,     setDarkMode]     = useState(false);
    
    // Sub-view page routing states
    const [currentView,  setCurrentView]  = useState('list'); // list, create, edit, detail
    const [activeAssetId, setActiveAssetId] = useState(null);
    const [activeTargetId, setActiveTargetId] = useState(null);
    const [activeScanId, setActiveScanId] = useState(null);
    const [activeFindingId, setActiveFindingId] = useState(null);
    const [activeRiskId, setActiveRiskId] = useState(null);
    const [activeReportId, setActiveReportId] = useState(null);

    const handleNavigate = (page) => {
        setActivePage(page);
        setCurrentView('list');
        setActiveAssetId(null);
        setActiveTargetId(null);
        setActiveScanId(null);
        setActiveFindingId(null);
        setActiveRiskId(null);
        setActiveReportId(null);
    };

    const handleViewDetail = (id) => {
        if (activePage === 'assets') {
            setActiveAssetId(id);
        } else if (activePage === 'targets') {
            setActiveTargetId(id);
        } else if (activePage === 'scans') {
            setActiveScanId(id);
        } else if (activePage === 'findings') {
            setActiveFindingId(id);
        } else if (activePage === 'risks') {
            setActiveRiskId(id);
        } else if (activePage === 'reports') {
            setActiveReportId(id);
        }
        setCurrentView('detail');
    };

    const handleViewReport = (id) => {
        if (activePage === 'scans') {
            setActiveScanId(id);
            setCurrentView('report');
        }
    };

    const handleViewEdit = (id) => {
        if (activePage === 'assets') {
            setActiveAssetId(id);
        } else if (activePage === 'targets') {
            setActiveTargetId(id);
        } else if (activePage === 'scans') {
            setActiveScanId(id);
        } else if (activePage === 'findings') {
            setActiveFindingId(id);
        } else if (activePage === 'risks') {
            setActiveRiskId(id);
        } else if (activePage === 'reports') {
            setActiveReportId(id);
        }
        setCurrentView('edit');
    };

    const handleViewCreate = () => {
        setCurrentView('create');
    };

    const handleFormSaved = () => {
        setCurrentView('list');
        setActiveAssetId(null);
        setActiveTargetId(null);
        setActiveScanId(null);
        setActiveFindingId(null);
    };

    const renderPage = () => {
        if (activePage === 'dashboard') {
            return <DashboardPage />;
        }

        if (activePage === 'assets') {
            switch (currentView) {
                case 'create':
                    return (
                        <AssetFormPage 
                            onSave={handleFormSaved} 
                            onCancel={() => setCurrentView('list')} 
                            onUnauthorized={() => {}}
                        />
                    );
                case 'edit':
                    return (
                        <AssetFormPage 
                            assetId={activeAssetId} 
                            onSave={handleFormSaved} 
                            onCancel={() => setCurrentView('list')} 
                            onUnauthorized={() => {}}
                        />
                    );
                case 'detail':
                    return (
                        <AssetDetailPage 
                            assetId={activeAssetId} 
                            onBack={() => setCurrentView('list')} 
                            onEdit={handleViewEdit} 
                            onUnauthorized={() => {}}
                        />
                    );
                case 'list':
                default:
                    return (
                        <AssetsPage 
                            onNavigateToCreate={handleViewCreate}
                            onNavigateToEdit={handleViewEdit}
                            onNavigateToDetail={handleViewDetail}
                            onUnauthorized={() => {}}
                        />
                    );
            }
        }

        if (activePage === 'targets') {
            switch (currentView) {
                case 'create':
                    return (
                        <TargetFormPage 
                            onSave={handleFormSaved} 
                            onCancel={() => setCurrentView('list')} 
                        />
                    );
                case 'edit':
                    return (
                        <TargetFormPage 
                            targetId={activeTargetId} 
                            onSave={handleFormSaved} 
                            onCancel={() => setCurrentView('list')} 
                        />
                    );
                case 'detail':
                    return (
                        <TargetDetailPage 
                            targetId={activeTargetId} 
                            onBack={() => setCurrentView('list')} 
                            onEdit={handleViewEdit} 
                        />
                    );
                case 'list':
                default:
                    return (
                        <TargetsPage 
                            onNavigateToCreate={handleViewCreate}
                            onNavigateToEdit={handleViewEdit}
                            onNavigateToDetail={handleViewDetail}
                        />
                    );
            }
        }

        if (activePage === 'scans') {
            switch (currentView) {
                case 'create':
                    return (
                        <ScanWizardPage
                            onSave={handleFormSaved}
                            onCancel={() => setCurrentView('list')}
                        />
                    );
                case 'edit':
                    return (
                        <ScanFormPage 
                            scanId={activeScanId} 
                            onSave={handleFormSaved} 
                            onCancel={() => setCurrentView('list')} 
                        />
                    );
                case 'detail':
                    return (
                        <ScanDetailPage 
                            scanId={activeScanId} 
                            onBack={() => setCurrentView('list')} 
                            onEdit={handleViewEdit} 
                            onReport={handleViewReport}
                        />
                    );
                case 'report':
                    return (
                        <ScanReportPage
                            scanId={activeScanId}
                            onBack={() => setCurrentView('list')}
                            onViewDetail={handleViewDetail}
                        />
                    );
                case 'list':
                default:
                    return (
                        <ScansPage 
                            onNavigateToCreate={handleViewCreate}
                            onNavigateToEdit={handleViewEdit}
                            onNavigateToDetail={handleViewDetail}
                            onNavigateToReport={handleViewReport}
                        />
                    );
            }
        }

        if (activePage === 'findings') {
            switch (currentView) {
                case 'create':
                    return (
                        <FindingFormPage 
                            onSave={handleFormSaved} 
                            onCancel={() => setCurrentView('list')} 
                        />
                    );
                case 'edit':
                    return (
                        <FindingFormPage 
                            findingId={activeFindingId} 
                            onSave={handleFormSaved} 
                            onCancel={() => setCurrentView('list')} 
                        />
                    );
                case 'detail':
                    return (
                        <FindingDetailPage 
                            findingId={activeFindingId} 
                            onBack={() => setCurrentView('list')} 
                            onEdit={handleViewEdit} 
                        />
                    );
                case 'list':
                default:
                    return (
                        <FindingsPage 
                            onNavigateToCreate={handleViewCreate}
                            onNavigateToEdit={handleViewEdit}
                            onNavigateToDetail={handleViewDetail}
                        />
                    );
            }
        }

        if (activePage === 'risks') {
            switch (currentView) {
                case 'create':
                    return (
                        <RiskFormPage 
                            onSave={handleFormSaved} 
                            onCancel={() => setCurrentView('list')} 
                        />
                    );
                case 'edit':
                    return (
                        <RiskFormPage 
                            riskId={activeRiskId} 
                            onSave={handleFormSaved} 
                            onCancel={() => setCurrentView('list')} 
                        />
                    );
                case 'detail':
                    return (
                        <RiskDetailPage 
                            riskId={activeRiskId} 
                            onBack={() => setCurrentView('list')} 
                            onEdit={handleViewEdit} 
                        />
                    );
                case 'list':
                default:
                    return (
                        <RiskDashboardPage 
                            onNavigateToCreate={handleViewCreate}
                            onNavigateToEdit={handleViewEdit}
                            onNavigateToDetail={handleViewDetail}
                        />
                    );
            }
        }
        if (activePage === 'reports') {
            switch (currentView) {
                case 'detail':
                    return (
                        <ReportDetailPage 
                            reportId={activeReportId} 
                            onBack={() => setCurrentView('list')} 
                            onEdit={handleViewEdit} 
                        />
                    );
                case 'list':
                default:
                    return (
                        <ReportsPage 
                            onNavigateToCreate={handleViewCreate}
                            onNavigateToEdit={handleViewEdit}
                            onNavigateToDetail={handleViewDetail}
                        />
                    );
            }
        }

        return <PlaceholderPage title={pageLabels[activePage] || activePage} />;
    };

    // Show initial session validation loading screen
    if (loading) {
        return (
            <div className="min-h-screen bg-slate-50 flex flex-col items-center justify-center gap-3">
                <Loader2 className="animate-spin text-brand-600" size={32} />
                <span className="text-xs font-semibold text-slate-500">Initializing session...</span>
            </div>
        );
    }

    // Redirect guest users to Login Page
    if (!user) {
        return <LoginPage />;
    }

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
