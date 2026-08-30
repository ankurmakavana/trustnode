import React, { useState, useEffect, useCallback } from 'react';
import StatCard from '../components/StatCard';
import { SeverityChart, ScanActivityChart, PostureTrendChart, LifecycleSummaryWidget } from '../components/Charts';
import ActivityTable from '../components/ActivityTable';
import ScansTable from '../components/ScansTable';
import QuickActions from '../components/QuickActions';
import { SkeletonCard, Skeleton } from '../components/ui/primitives';
import { useAuth } from '../context/AuthContext';
import { Filter, Layers } from 'lucide-react';
import axios from 'axios';

export default function DashboardPage() {
    const { checkAuthStatus } = useAuth();
    const [stats, setStats] = useState([]);
    const [severityData, setSeverityData] = useState([]);
    const [scanActivity, setScanActivity] = useState([]);
    const [recentScans, setRecentScans] = useState([]);
    const [recentActivity, setRecentActivity] = useState([]);
    const [lifecycleSummary, setLifecycleSummary] = useState({ new: 0, recurring: 0, resolved: 0, regression: 0 });
    const [postureTrend, setPostureTrend] = useState([]);
    const [postureAssessment, setPostureAssessment] = useState('initial_baseline');
    const [loading, setLoading] = useState(true);

    // Target filters state
    const [repositories, setRepositories] = useState([]);
    const [targets, setTargets] = useState([]);
    const [selectedFilter, setSelectedFilter] = useState('all'); // 'all', 'repo:ID', 'target:ID'

    // Fetch repositories and targets for dropdown
    useEffect(() => {
        const fetchFilters = async () => {
            try {
                const [reposRes, targetsRes] = await Promise.all([
                    axios.get('/api/repositories', { params: { per_page: 50 } }).catch(() => ({ data: { data: [] } })),
                    axios.get('/api/targets', { params: { per_page: 50 } }).catch(() => ({ data: { data: [] } }))
                ]);
                setRepositories(reposRes.data.data || []);
                setTargets(targetsRes.data.data || []);
            } catch (err) {
                console.error('Failed to load filter dropdowns', err);
            }
        };
        fetchFilters();
    }, []);

    const fetchDashboardStats = useCallback(async () => {
        setLoading(true);
        try {
            const params = {};
            if (selectedFilter.startsWith('repo:')) {
                params.repository_id = selectedFilter.replace('repo:', '');
            } else if (selectedFilter.startsWith('target:')) {
                params.target_id = selectedFilter.replace('target:', '');
            }

            const response = await axios.get('/api/dashboard/stats', { params });
            const data = response.data;

            setStats(data.stats || []);
            setSeverityData(data.severityData || []);
            setScanActivity(data.scanActivity || []);
            setRecentScans(data.recentScans || []);
            setRecentActivity(data.recentActivity || []);
            setLifecycleSummary(data.lifecycleSummary || { new: 0, recurring: 0, resolved: 0, regression: 0 });
            setPostureTrend(data.postureTrend || []);
            setPostureAssessment(data.postureAssessment || 'initial_baseline');
        } catch (err) {
            if (err.response && err.response.status === 401) {
                checkAuthStatus();
            } else {
                console.error('Failed to load dashboard stats', err);
            }
        } finally {
            setLoading(false);
        }
    }, [selectedFilter, checkAuthStatus]);

    useEffect(() => {
        fetchDashboardStats();
    }, [fetchDashboardStats]);

    return (
        <div className="flex flex-col gap-6">

            {/* Target Scope Filter Header */}
            <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-3 bg-white p-3.5 px-4 rounded-xl border border-slate-200 shadow-sm">
                <div className="flex items-center gap-2">
                    <Layers size={16} className="text-brand-600" />
                    <div>
                        <span className="text-xs font-bold text-slate-800 uppercase tracking-wider block">Security Posture Scope</span>
                        <span className="text-[11px] text-slate-400">Filter security baseline and historical trends by target</span>
                    </div>
                </div>

                <div className="flex items-center gap-2">
                    <Filter size={14} className="text-slate-400" />
                    <select
                        value={selectedFilter}
                        onChange={(e) => setSelectedFilter(e.target.value)}
                        className="text-xs font-semibold text-slate-700 bg-slate-50 border border-slate-200 rounded-lg px-2.5 py-1.5 focus:outline-none focus:ring-1 focus:ring-brand-500 transition"
                    >
                        <option value="all">Whole Tenant Inventory (All Targets)</option>
                        {repositories.length > 0 && (
                            <optgroup label="Git Repositories">
                                {repositories.map((repo) => (
                                    <option key={`repo-${repo.id}`} value={`repo:${repo.id}`}>
                                        Repository: {repo.name}
                                    </option>
                                ))}
                            </optgroup>
                        )}
                        {targets.length > 0 && (
                            <optgroup label="Infrastructure Targets">
                                {targets.map((tgt) => (
                                    <option key={`target-${tgt.id}`} value={`target:${tgt.id}`}>
                                        Target: {tgt.name || tgt.target}
                                    </option>
                                ))}
                            </optgroup>
                        )}
                    </select>
                </div>
            </div>

            {/* Section: KPI Cards */}
            <section aria-label="Key metrics">
                <div className="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4">
                    {loading ? (
                        <>
                            <SkeletonCard />
                            <SkeletonCard />
                            <SkeletonCard />
                            <SkeletonCard />
                        </>
                    ) : (
                        stats.map((stat) => (
                            <StatCard key={stat.id} stat={stat} />
                        ))
                    )}
                </div>
            </section>

            {/* Section: Finding Lifecycle Summary */}
            <section aria-label="Lifecycle intelligence summary">
                {loading ? (
                    <div className="bg-white border border-slate-200 rounded-xl p-5 shadow-sm space-y-3">
                        <Skeleton className="h-4 w-48" />
                        <div className="grid grid-cols-2 sm:grid-cols-4 gap-3">
                            <Skeleton className="h-20 w-full" />
                            <Skeleton className="h-20 w-full" />
                            <Skeleton className="h-20 w-full" />
                            <Skeleton className="h-20 w-full" />
                        </div>
                    </div>
                ) : (
                    <LifecycleSummaryWidget summary={lifecycleSummary} />
                )}
            </section>

            {/* Section: Posture Trend & Severity Distribution */}
            <section aria-label="Security posture trends and severity">
                <div className="grid grid-cols-1 lg:grid-cols-3 gap-4">
                    <div className="lg:col-span-2">
                        {loading ? (
                            <div className="bg-white border border-slate-200 rounded-xl p-5 shadow-sm space-y-3 h-64">
                                <Skeleton className="h-5 w-48" />
                                <Skeleton className="h-48 w-full" />
                            </div>
                        ) : (
                            <PostureTrendChart data={postureTrend} assessment={postureAssessment} />
                        )}
                    </div>
                    <div>
                        {loading ? (
                            <div className="bg-white border border-slate-200 rounded-xl p-5 shadow-sm space-y-3 h-64">
                                <Skeleton className="h-5 w-48" />
                                <Skeleton className="h-48 w-full" />
                            </div>
                        ) : (
                            <SeverityChart data={severityData} />
                        )}
                    </div>
                </div>
            </section>

            {/* Section: Scan Activity + Quick Actions */}
            <section aria-label="Scan activity and quick actions">
                <div className="grid grid-cols-1 lg:grid-cols-3 gap-4">
                    <div className="lg:col-span-2">
                        {loading ? (
                            <div className="bg-white border border-slate-200 rounded-xl p-5 shadow-sm space-y-3 h-64">
                                <Skeleton className="h-5 w-48" />
                                <Skeleton className="h-48 w-full" />
                            </div>
                        ) : (
                            <ScanActivityChart data={scanActivity} />
                        )}
                    </div>
                    <div>
                        <QuickActions />
                    </div>
                </div>
            </section>

            {/* Section: Activity + Scans */}
            <section aria-label="Recent activity and assessments">
                <div className="grid grid-cols-1 xl:grid-cols-2 gap-4">
                    <ActivityTable data={recentActivity} />
                    <ScansTable data={recentScans} />
                </div>
            </section>

        </div>
    );
}
