import React, { useState, useEffect } from 'react';
import { 
    Activity, Play, Calendar, Shield, Trash2, Edit3, 
    ArrowLeft, ExternalLink, Network, FileText, AlertTriangle, 
    Layers, Lock, Clock, Ban, Terminal, RefreshCw, Archive, Server
} from 'lucide-react';
import { 
    TargetDetailLayout, DetailHero, DetailCard, InfoGrid, 
    MetricCard, Timeline, RelationshipCard, TabNavigation, 
    ActionDropdown, EmptyState 
} from '../components/ui/primitives_targets';
import { 
    TargetTypeBadge, TargetEnvironmentBadge, 
    TargetCriticalityBadge, TargetStatusBadge 
} from '../components/ui/primitives_targets';
import { Skeleton } from '../components/ui/primitives';
import { useAuth } from '../context/AuthContext';

export default function TargetDetailPage({ targetId, onBack, onEdit }) {
    const { checkAuthStatus } = useAuth();
    const [target, setTarget] = useState(null);
    const [activities, setActivities] = useState([]);
    const [loading, setLoading] = useState(true);
    const [loadingActivity, setLoadingActivity] = useState(true);
    const [error, setError] = useState(null);

    // Tab state: Overview, Scans, Findings, History, Notes, Evidence
    const [activeTab, setActiveTab] = useState('overview');

    const fetchTargetDetails = async () => {
        setLoading(true);
        try {
            const response = await fetch(`/api/targets/${targetId}`, {
                headers: {
                    'Accept': 'application/json',
                }
            });
            if (response.status === 401) {
                checkAuthStatus();
                return;
            }
            if (!response.ok) {
                throw new Error('Failed to load target details');
            }
            const data = await response.json();
            setTarget(data.data);
        } catch (err) {
            setError(err.message);
        } finally {
            setLoading(false);
        }
    };

    const fetchActivities = async () => {
        setLoadingActivity(true);
        try {
            const response = await fetch(`/api/targets/${targetId}/activity`, {
                headers: {
                    'Accept': 'application/json',
                }
            });
            if (response.status === 401) {
                checkAuthStatus();
                return;
            }
            if (!response.ok) {
                throw new Error('Failed to load target activity logs');
            }
            const data = await response.json();
            setActivities(data.data);
        } catch (err) {
            console.error(err);
        } finally {
            setLoadingActivity(false);
        }
    };

    useEffect(() => {
        fetchTargetDetails();
        fetchActivities();
    }, [targetId]);

    if (loading) {
        return (
            <div className="py-12 flex justify-center items-center">
                <span className="text-xs text-slate-500 font-semibold">Loading target details...</span>
            </div>
        );
    }

    if (error || !target) {
        return (
            <div className="p-8 text-center">
                <p className="text-sm font-semibold text-red-600">Error loading details</p>
                <p className="text-xs text-slate-400 mt-1">{error || 'Target not found'}</p>
                <button
                    onClick={onBack}
                    className="mt-4 px-3 py-1.5 text-xs border border-slate-200 rounded-lg hover:bg-slate-50 transition-colors"
                >
                    Back to Targets
                </button>
            </div>
        );
    }

    // Breadcrumbs hierarchy
    const breadcrumbs = [
        { label: 'Inventory', href: '#', onClick: onBack },
        { label: 'Targets', href: '#', onClick: onBack },
        { label: target.name },
    ];

    // Actions dropdown configuration
    const actionItems = [
        { label: 'Run Scan', icon: Play, onClick: () => alert('Initiating immediate vulnerability scan...') },
        { label: 'Schedule Scan', icon: Calendar, onClick: () => alert('Scan scheduling dialog...') },
        { label: 'Export Details', icon: FileText, onClick: () => alert('Generating scope report PDF...') },
        { label: 'Clone Target', icon: Layers, onClick: () => alert('Cloning target configuration...') },
        { label: 'Edit Target', icon: Edit3, onClick: () => onEdit(target.id) },
        { label: 'Archive Target', icon: Archive, onClick: () => alert('Archiving target...') },
        { label: 'Delete Target', icon: Trash2, isDanger: true, onClick: () => alert('Initiating deletion check...') },
    ];

    // KPI Metric card items
    const kpis = [
        { label: 'Last Scan', value: 'Aug 04, 2026', subtext: 'Assessment completed', color: 'blue', icon: Calendar },
        { label: 'Risk Score', value: '4.80 / 10', subtext: 'Medium severity rating', color: 'violet', icon: Shield },
        { label: 'Open Findings', value: '28 Active', subtext: '5 critical severity', color: 'red', icon: AlertTriangle },
        { label: 'Linked Assets', value: '4 Assets', subtext: 'Configured edge resources', color: 'emerald', icon: Network },
    ];

    // Map audit activity log events
    const timelineEvents = activities.map(log => ({
        title: log.user?.name || 'System',
        description: log.action === 'created' ? 'registered target scope' : 'updated target details',
        meta: new Date(log.created_at).toLocaleString(),
        icon: Activity
    }));

    return (
        <TargetDetailLayout breadcrumbs={breadcrumbs}>
            
            {/* Hero Card */}
            <DetailHero
                title={target.name}
                subtitle={target.uuid}
                badges={[
                    <TargetStatusBadge status={target.status} />,
                    <TargetEnvironmentBadge env={target.environment} />,
                    <TargetCriticalityBadge criticality={target.criticality} />,
                    <TargetTypeBadge type={target.type} />
                ]}
                actions={
                    <>
                        <button
                            onClick={onBack}
                            className="inline-flex items-center gap-1 px-3 py-2 bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 text-xs font-semibold rounded-lg shadow-sm transition-colors focus:outline-none"
                        >
                            <ArrowLeft size={13} /> Back
                        </button>
                        <ActionDropdown label="Actions" items={actionItems} />
                    </>
                }
                kpiCards={kpis}
            />

            {/* Two-Column Grid */}
            <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
                
                {/* Left Column (70%) */}
                <div className="lg:col-span-2 flex flex-col gap-6">
                    
                    {/* Tab Navigation */}
                    <TabNavigation
                        tabs={[
                            { id: 'overview', label: 'Overview' },
                            { id: 'scans', label: 'Scans', badge: '12' },
                            { id: 'findings', label: 'Findings', badge: '28' },
                            { id: 'history', label: 'History' },
                            { id: 'notes', label: 'Notes' },
                            { id: 'evidence', label: 'Evidence' },
                        ]}
                        activeTab={activeTab}
                        onChange={setActiveTab}
                    />

                    {/* Tab Panels */}
                    {activeTab === 'overview' && (
                        <div className="flex flex-col gap-6">
                            
                            {/* Overview specifications details */}
                            <DetailCard title="Target Technical Specification" subtitle="Detailed testing parameters.">
                                <InfoGrid
                                    items={[
                                        { label: 'Target Value', value: <span className="font-mono text-xs select-all text-slate-900 bg-slate-50 border border-slate-100 rounded px-2 py-1 block w-fit max-w-full truncate">{target.value}</span> },
                                        { label: 'Description', value: target.description || 'No description provided.' },
                                        { label: 'Business Purpose', value: 'Production VPN access portal hosting external staff authentication endpoints.' },
                                        { label: 'Testing Scope', value: 'Allowed for full vulnerability assessment.' },
                                        { label: 'Authentication Requirements', value: 'Pre-authenticated / Cookie validation required' },
                                        { label: 'Allowed Testing Window', value: 'Weekends 00:00 - 08:00 UTC only' },
                                        { label: 'Allowed IP Ranges', value: '192.168.100.0/24, 10.10.0.0/16' },
                                        { label: 'Excluded Tests', value: 'No aggressive denial-of-service simulations.' },
                                        { label: 'Allowed Ports', value: 'TCP 80, 443, 8443' },
                                        { label: 'Protocols', value: 'HTTP/2, TLSv1.3' },
                                    ]}
                                    columns={2}
                                />
                            </DetailCard>

                            {/* Testing Guidelines / Scope notes */}
                            {target.scope_notes && (
                                <DetailCard title="Scope & Testing Guidelines" subtitle="Testing rules and regulatory compliance bounds.">
                                    <div className="text-xs text-slate-600 bg-slate-50 border border-slate-200/60 rounded-lg p-4 leading-relaxed whitespace-pre-wrap">
                                        {target.scope_notes}
                                    </div>
                                </DetailCard>
                            )}
                        </div>
                    )}

                    {activeTab === 'scans' && (
                        <DetailCard title="Associated Scans" subtitle="Historical records of vulnerability assessments.">
                            <EmptyState
                                title="No assessments logged"
                                description="No active or pending scans have been triggered for this target. Select 'Run Scan' above to initiate an assessment."
                                icon={Play}
                            />
                        </DetailCard>
                    )}

                    {activeTab === 'findings' && (
                        <DetailCard title="Vulnerability Findings" subtitle="Triage vulnerabilities flagged within this target scope.">
                            <EmptyState
                                title="No vulnerabilities active"
                                description="All detected vulnerabilities have been triaged and resolved for this target environment."
                                icon={Shield}
                            />
                        </DetailCard>
                    )}

                    {activeTab === 'history' && (
                        <DetailCard title="Scan Log History" subtitle="Audit records of previous execution runs.">
                            <EmptyState
                                title="Scan history clean"
                                description="This target scope does not have any historical scan records logged in the system."
                                icon={Calendar}
                            />
                        </DetailCard>
                    )}

                    {activeTab === 'notes' && (
                        <DetailCard title="Analyst Scope Notes" subtitle="Internal notes shared between security analysts.">
                            <EmptyState
                                title="No notes recorded"
                                description="Share technical details, test accounts, or bypass instructions. Add notes to coordinate assessment steps."
                                icon={FileText}
                            />
                        </DetailCard>
                    )}

                    {activeTab === 'evidence' && (
                        <DetailCard title="Verification Evidence" subtitle="Uploaded configuration screenshots, reports, or logs.">
                            <EmptyState
                                title="No evidence uploaded"
                                description="Upload screenshot proofs, configurations, or manual curl output traces to document compliance checks."
                                icon={Terminal}
                            />
                        </DetailCard>
                    )}
                </div>

                {/* Right Column (30%) */}
                <div className="flex flex-col gap-6">
                    
                    {/* Information detail card */}
                    <DetailCard title="Target Metadata" subtitle="Timeline and classification attributes.">
                        <div className="flex flex-col gap-4">
                            <InfoGrid
                                items={[
                                    { label: 'Owner', value: 'SOC Cyber Assessment Team' },
                                    { label: 'Business Unit', value: 'Corporate IT Infrastructure' },
                                    { label: 'Environment', value: <TargetEnvironmentBadge env={target.environment} /> },
                                    { label: 'Classification', value: 'Restricted Internal Domain' },
                                    { label: 'Created By', value: target.created_by?.name || 'Unknown' },
                                    { label: 'Created At', value: new Date(target.created_at).toLocaleDateString() },
                                    { label: 'Updated At', value: new Date(target.updated_at).toLocaleDateString() },
                                ]}
                                columns={1}
                            />
                            
                            {/* Tags list */}
                            <div>
                                <span className="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-2 select-none">Tags</span>
                                {target.tags && target.tags.length > 0 ? (
                                    <div className="flex flex-wrap gap-1.5">
                                        {target.tags.map(t => (
                                            <span 
                                                key={t.id} 
                                                className="text-[10px] font-semibold px-2 py-0.5 rounded"
                                                style={{ backgroundColor: t.color || '#f1f5f9', color: '#1e293b' }}
                                            >
                                                {t.name}
                                            </span>
                                        ))}
                                    </div>
                                ) : (
                                    <span className="text-xs text-slate-400 italic">No tags registered.</span>
                                )}
                            </div>
                        </div>
                    </DetailCard>

                    {/* Associated Relationships */}
                    <DetailCard title="Linked Inventory Items" subtitle="Entity relationships inside the assessment catalog.">
                        <div className="flex flex-col gap-3">
                            <RelationshipCard
                                title="Linked Assets"
                                icon={Server}
                                items={[
                                    { label: 'portal.internal', onClick: () => alert('Navigate to asset details...') },
                                    { label: 'auth.internal', onClick: () => alert('Navigate to asset details...') },
                                ]}
                            />
                            <RelationshipCard
                                title="Security Reports"
                                icon={FileText}
                                items={[
                                    { label: 'SSO Assessment Report v1', onClick: () => alert('Open PDF report...') },
                                ]}
                            />
                        </div>
                    </DetailCard>

                    {/* Timeline Log Card */}
                    <DetailCard title="Audit Activity Log" subtitle="Recent configuration events.">
                        {loadingActivity ? (
                            <div className="space-y-3">
                                <Skeleton className="h-4 w-full" />
                                <Skeleton className="h-4 w-3/4" />
                            </div>
                        ) : timelineEvents.length === 0 ? (
                            <span className="text-xs text-slate-400 italic">No logs recorded.</span>
                        ) : (
                            <Timeline events={timelineEvents} />
                        )}
                    </DetailCard>
                </div>
            </div>
        </TargetDetailLayout>
    );
}
