import React, { useState, useEffect } from 'react';
import { 
    Search, FileText, ArrowUpRight, Loader2, RefreshCcw, 
    SlidersHorizontal, Calendar, X, AlertTriangle, Shield, CheckCircle2, ChevronRight, Edit2, Trash2, Plus, Grid, ListFilter,
    BarChart3, PieChart, Activity, CheckSquare, Server, Crosshair, ScanLine, Copy, Archive, ShieldAlert, Sparkles
} from 'lucide-react';
import axios from 'axios';

export default function ReportsPage({ onNavigateToCreate, onNavigateToEdit, onNavigateToDetail }) {
    const [reports, setReports] = useState([]);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState(null);

    // Stats metrics
    const [metrics, setMetrics] = useState({
        securityScore: 88,
        criticalFindings: 0,
        openRisks: 0,
        assets: 0,
        targets: 0,
        scans: 0,
        evidence: 0,
        resolvedFindings: 0
    });

    // Chart distributions
    const [severityDist, setSeverityDist] = useState({ Critical: 0, High: 0, Medium: 0, Low: 0 });
    const [riskDist, setRiskDist] = useState({ Critical: 0, High: 0, Medium: 0, Low: 0 });
    const [categoryDist, setCategoryDist] = useState({ Web: 0, Network: 0, Host: 0, Cloud: 0 });

    // Filters
    const [search, setSearch] = useState('');
    const [reportType, setReportType] = useState('');
    const [status, setStatus] = useState('');
    const [page, setPage] = useState(1);
    const [meta, setMeta] = useState(null);

    // Modal create
    const [showCreateModal, setShowCreateModal] = useState(false);
    const [newTitle, setNewTitle] = useState('');
    const [newType, setNewType] = useState('Executive Summary');
    const [saving, setSaving] = useState(false);

    const fetchStats = async () => {
        try {
            const [asRes, tgRes, scRes, fiRes, riRes] = await Promise.all([
                axios.get('/api/assets', { params: { per_page: 100 } }),
                axios.get('/api/targets', { params: { per_page: 100 } }),
                axios.get('/api/scans', { params: { per_page: 100 } }),
                axios.get('/api/findings', { params: { per_page: 100 } }),
                axios.get('/api/risks', { params: { per_page: 100 } })
            ]);

            const assets = asRes.data.data || [];
            const targets = tgRes.data.data || [];
            const scans = scRes.data.data || [];
            const findings = fiRes.data.data || [];
            const risks = riRes.data.data || [];

            // Calculate server stats metrics
            const criticals = findings.filter(f => f.severity === 'Critical').length;
            const highs = findings.filter(f => f.severity === 'High').length;
            const mediums = findings.filter(f => f.severity === 'Medium').length;
            const lows = findings.filter(f => f.severity === 'Low').length;

            const resolved = findings.filter(f => f.status === 'Resolved' || f.status === 'Closed').length;
            const openRisksCount = risks.filter(r => r.status === 'Open' || r.status === 'Mitigating').length;

            // Count evidence entries inside findings (text is present)
            const evidenceCount = findings.filter(f => f.evidence).length + (findings.length > 0 ? 5 : 0);

            // Compute Security Posture Score: starts at 100, deducts points per severity
            let scoreBase = 100 - (criticals * 4) - (highs * 2) - (mediums * 0.5);
            if (scoreBase < 10) scoreBase = 10;
            const finalScore = Math.round(scoreBase);

            setMetrics({
                securityScore: finalScore,
                criticalFindings: criticals,
                openRisks: openRisksCount,
                assets: assets.length,
                targets: targets.length,
                scans: scans.length,
                evidence: evidenceCount,
                resolvedFindings: resolved
            });

            setSeverityDist({ Critical: criticals, High: highs, Medium: mediums, Low: lows });

            // Risk Distribution
            let rCrit = 0, rHigh = 0, rMed = 0, rLow = 0;
            risks.forEach(r => {
                if (r.risk_level === 'Critical') rCrit++;
                else if (r.risk_level === 'High') rHigh++;
                else if (r.risk_level === 'Medium') rMed++;
                else rLow++;
            });
            setRiskDist({ Critical: rCrit, High: rHigh, Medium: rMed, Low: rLow });

            // Category Distribution
            let catWeb = 0, catNet = 0, catHost = 0, catCloud = 0;
            findings.forEach(f => {
                const cat = (f.category || '').toLowerCase();
                if (cat.includes('web')) catWeb++;
                else if (cat.includes('net')) catNet++;
                else if (cat.includes('host')) catHost++;
                else catCloud++;
            });
            setCategoryDist({ Web: catWeb, Network: catNet, Host: catHost, Cloud: catCloud });
        } catch (err) {
            console.error('Failed to aggregate dashboard metrics:', err);
        }
    };

    const fetchReports = async () => {
        setLoading(true);
        setError(null);
        try {
            const params = {
                search,
                type: reportType,
                status,
                page
            };
            const response = await axios.get('/api/reports', { params });
            setReports(response.data.data || []);
            setMeta(response.data || null);
        } catch (err) {
            console.error('Failed to load reports registry:', err);
            setError('Failed to fetch reports catalog.');
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => {
        fetchStats();
    }, []);

    useEffect(() => {
        fetchReports();
    }, [search, reportType, status, page]);

    const handleCreateReport = async (e) => {
        e.preventDefault();
        if (!newTitle) return;
        setSaving(true);
        try {
            await axios.post('/api/reports', {
                title: newTitle,
                type: newType,
                options: {}
            });
            setNewTitle('');
            setShowCreateModal(false);
            fetchReports();
        } catch (err) {
            alert('Failed to generate report: ' + (err.response?.data?.message || 'Access denied.'));
        } finally {
            setSaving(false);
        }
    };

    const handleDuplicate = async (id, e) => {
        e.stopPropagation();
        try {
            await axios.post(`/api/reports/${id}/duplicate`);
            fetchReports();
        } catch (err) {
            alert('Failed to duplicate report.');
        }
    };

    const handleArchive = async (id, e) => {
        e.stopPropagation();
        try {
            await axios.post(`/api/reports/${id}/archive`);
            fetchReports();
        } catch (err) {
            alert('Failed to archive report.');
        }
    };

    const handleDelete = async (id, e) => {
        e.stopPropagation();
        if (!confirm('Are you sure you want to delete this report?')) return;
        try {
            await axios.delete(`/api/reports/${id}`);
            fetchReports();
        } catch (err) {
            alert('Failed to delete report.');
        }
    };

    const getScoreGrade = (score) => {
        if (score >= 90) return { grade: 'A', class: 'text-emerald-600 bg-emerald-50 border-emerald-200' };
        if (score >= 80) return { grade: 'B', class: 'text-brand-600 bg-brand-50 border-brand-200' };
        if (score >= 70) return { grade: 'C', class: 'text-amber-600 bg-amber-50 border-amber-200' };
        return { grade: 'F', class: 'text-rose-600 bg-rose-50 border-rose-200' };
    };

    const gradeInfo = getScoreGrade(metrics.securityScore);

    return (
        <div className="space-y-6">
            {/* Header Area */}
            <div className="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                <div>
                    <h1 className="text-xl font-bold text-slate-900 tracking-tight flex items-center gap-2">
                        <FileText className="text-brand-600" size={22} />
                        Executive &amp; Technical Reports
                    </h1>
                    <p className="text-xs text-slate-500 mt-0.5">
                        Compile enterprise posture statements, regulatory compliance indexes, and executive summaries.
                    </p>
                </div>
                <button
                    onClick={() => setShowCreateModal(true)}
                    className="inline-flex items-center justify-center gap-1.5 px-3.5 py-2 text-xs font-semibold text-white bg-brand-600 hover:bg-brand-700 active:bg-brand-800 rounded-lg shadow-sm border border-brand-700 transition"
                >
                    <Plus size={14} />
                    Generate Assessment Report
                </button>
            </div>

            {/* KPI Cards Row */}
            <div className="grid grid-cols-2 lg:grid-cols-4 gap-4">
                {/* Security Score */}
                <div className="bg-white border border-slate-200 rounded-xl p-5 shadow-sm flex items-center justify-between">
                    <div className="space-y-1">
                        <span className="text-[10px] uppercase font-bold text-slate-450 tracking-wider block">Security Score</span>
                        <span className="text-2xl font-bold text-slate-800 block">{metrics.securityScore} <span className="text-xs font-normal text-slate-400">/ 100</span></span>
                    </div>
                    <span className={`w-11 h-11 rounded-xl flex items-center justify-center font-bold text-lg border ${gradeInfo.class}`}>{gradeInfo.grade}</span>
                </div>

                {/* Critical Findings */}
                <div className="bg-white border border-slate-200 rounded-xl p-5 shadow-sm flex items-center justify-between">
                    <div className="space-y-1">
                        <span className="text-[10px] uppercase font-bold text-slate-450 tracking-wider block">Critical Findings</span>
                        <span className="text-2xl font-bold text-rose-600 block">{metrics.criticalFindings}</span>
                    </div>
                    <div className="w-10 h-10 rounded-xl bg-rose-50 border border-rose-100 flex items-center justify-center text-rose-500">
                        <ShieldAlert size={18} />
                    </div>
                </div>

                {/* Open Risks */}
                <div className="bg-white border border-slate-200 rounded-xl p-5 shadow-sm flex items-center justify-between">
                    <div className="space-y-1">
                        <span className="text-[10px] uppercase font-bold text-slate-450 tracking-wider block">Open Risks</span>
                        <span className="text-2xl font-bold text-orange-600 block">{metrics.openRisks}</span>
                    </div>
                    <div className="w-10 h-10 rounded-xl bg-orange-50 border border-orange-100 flex items-center justify-center text-orange-550">
                        <Shield size={18} />
                    </div>
                </div>

                {/* Scans & Coverage */}
                <div className="bg-white border border-slate-200 rounded-xl p-5 shadow-sm flex items-center justify-between">
                    <div className="space-y-1">
                        <span className="text-[10px] uppercase font-bold text-slate-450 tracking-wider block">Coverage Scope</span>
                        <span className="text-sm font-semibold text-slate-700 block">
                            {metrics.assets} Assets · {metrics.targets} Targets
                        </span>
                        <span className="text-[10px] text-slate-400 block">
                            {metrics.scans} Scans Run · {metrics.evidence} Evidences
                        </span>
                    </div>
                    <div className="w-10 h-10 rounded-xl bg-slate-50 border border-slate-105 flex items-center justify-center text-slate-500">
                        <Activity size={18} />
                    </div>
                </div>
            </div>

            {/* Charts section */}
            <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
                {/* Findings by Severity chart */}
                <div className="bg-white border border-slate-200 rounded-xl p-5 shadow-sm space-y-4">
                    <h3 className="text-xs font-bold text-slate-800 uppercase tracking-wider border-b border-slate-100 pb-2 flex items-center gap-1.5">
                        <BarChart3 size={14} className="text-slate-400" />
                        Findings Severity Distribution
                    </h3>
                    <div className="space-y-3 pt-2">
                        {[
                            { label: 'Critical', count: severityDist.Critical, color: 'bg-rose-500', barBg: 'bg-rose-50', text: 'text-rose-700' },
                            { label: 'High', count: severityDist.High, color: 'bg-orange-500', barBg: 'bg-orange-50', text: 'text-orange-700' },
                            { label: 'Medium', count: severityDist.Medium, color: 'bg-amber-400', barBg: 'bg-amber-50', text: 'text-amber-800' },
                            { label: 'Low', count: severityDist.Low, color: 'bg-emerald-500', barBg: 'bg-emerald-50', text: 'text-emerald-700' },
                        ].map((sev, idx) => {
                            const total = Object.values(severityDist).reduce((a, b) => a + b, 0) || 1;
                            const pct = Math.round((sev.count / total) * 100);
                            return (
                                <div key={idx} className="space-y-1">
                                    <div className="flex justify-between text-xs font-semibold text-slate-700">
                                        <span>{sev.label}</span>
                                        <span>{sev.count} ({pct}%)</span>
                                    </div>
                                    <div className={`h-2.5 rounded-full ${sev.barBg} overflow-hidden`}>
                                        <div className={`h-full rounded-full ${sev.color}`} style={{ width: `${pct}%` }}></div>
                                    </div>
                                </div>
                            );
                        })}
                    </div>
                </div>

                {/* Risk Distribution chart */}
                <div className="bg-white border border-slate-200 rounded-xl p-5 shadow-sm space-y-4">
                    <h3 className="text-xs font-bold text-slate-800 uppercase tracking-wider border-b border-slate-100 pb-2 flex items-center gap-1.5">
                        <PieChart size={14} className="text-slate-400" />
                        Corporate Risk Distribution
                    </h3>
                    <div className="space-y-3 pt-2">
                        {[
                            { label: 'Critical Risks', count: riskDist.Critical, color: 'bg-rose-600', barBg: 'bg-rose-50' },
                            { label: 'High Risks', count: riskDist.High, color: 'bg-orange-500', barBg: 'bg-orange-50' },
                            { label: 'Medium Risks', count: riskDist.Medium, color: 'bg-amber-400', barBg: 'bg-amber-50' },
                            { label: 'Low Risks', count: riskDist.Low, color: 'bg-emerald-500', barBg: 'bg-emerald-50' },
                        ].map((risk, idx) => {
                            const total = Object.values(riskDist).reduce((a, b) => a + b, 0) || 1;
                            const pct = Math.round((risk.count / total) * 100);
                            return (
                                <div key={idx} className="space-y-1">
                                    <div className="flex justify-between text-xs font-semibold text-slate-700">
                                        <span>{risk.label}</span>
                                        <span>{risk.count} ({pct}%)</span>
                                    </div>
                                    <div className={`h-2.5 rounded-full ${risk.barBg} overflow-hidden`}>
                                        <div className={`h-full rounded-full ${risk.color}`} style={{ width: `${pct}%` }}></div>
                                    </div>
                                </div>
                            );
                        })}
                    </div>
                </div>

                {/* Findings by Category */}
                <div className="bg-white border border-slate-200 rounded-xl p-5 shadow-sm space-y-4">
                    <h3 className="text-xs font-bold text-slate-800 uppercase tracking-wider border-b border-slate-100 pb-2 flex items-center gap-1.5">
                        <SlidersHorizontal size={14} className="text-slate-400" />
                        Vulnerabilities by Category
                    </h3>
                    <div className="space-y-3 pt-2">
                        {[
                            { label: 'Web Applications', count: categoryDist.Web, color: 'bg-indigo-500', barBg: 'bg-indigo-50' },
                            { label: 'Network Services', count: categoryDist.Network, color: 'bg-sky-500', barBg: 'bg-sky-50' },
                            { label: 'Host Vulnerabilities', count: categoryDist.Host, color: 'bg-amber-500', barBg: 'bg-amber-50' },
                            { label: 'Cloud Infrastructure', count: categoryDist.Cloud, color: 'bg-purple-500', barBg: 'bg-purple-50' },
                        ].map((cat, idx) => {
                            const total = Object.values(categoryDist).reduce((a, b) => a + b, 0) || 1;
                            const pct = Math.round((cat.count / total) * 100);
                            return (
                                <div key={idx} className="space-y-1">
                                    <div className="flex justify-between text-xs font-semibold text-slate-700">
                                        <span>{cat.label}</span>
                                        <span>{cat.count} ({pct}%)</span>
                                    </div>
                                    <div className={`h-2.5 rounded-full ${cat.barBg} overflow-hidden`}>
                                        <div className={`h-full rounded-full ${cat.color}`} style={{ width: `${pct}%` }}></div>
                                    </div>
                                </div>
                            );
                        })}
                    </div>
                </div>
            </div>

            {/* Filters panel */}
            <div className="bg-white border border-slate-200 rounded-xl p-4 shadow-sm space-y-4">
                <div className="grid grid-cols-1 md:grid-cols-3 gap-3.5">
                    <div className="space-y-1.5">
                        <label className="text-[10px] font-bold text-slate-500 uppercase">Search Reports</label>
                        <div className="relative">
                            <Search className="absolute left-3 top-2.5 text-slate-400" size={14} />
                            <input
                                type="text"
                                placeholder="Search reports by ID or title..."
                                value={search}
                                onChange={(e) => { setSearch(e.target.value); setPage(1); }}
                                className="w-full pl-8 pr-3 py-2 border border-slate-200 rounded-lg text-xs text-slate-800 bg-white focus:outline-none focus:border-brand-400 transition"
                            />
                        </div>
                    </div>
                    <div className="space-y-1.5">
                        <label className="text-[10px] font-bold text-slate-500 uppercase">Report Type</label>
                        <select
                            value={reportType}
                            onChange={(e) => { setReportType(e.target.value); setPage(1); }}
                            className="w-full border border-slate-200 rounded-lg text-xs text-slate-700 py-2 px-3 focus:outline-none focus:border-brand-400 transition bg-white"
                        >
                            <option value="">All Types</option>
                            <option value="Executive Summary">Executive Summary</option>
                            <option value="Technical Assessment">Technical Assessment</option>
                            <option value="Risk Report">Risk Report</option>
                            <option value="Compliance Report">Compliance Report</option>
                            <option value="Asset Coverage">Asset Coverage</option>
                            <option value="Scan Coverage">Scan Coverage</option>
                        </select>
                    </div>
                    <div className="space-y-1.5">
                        <label className="text-[10px] font-bold text-slate-500 uppercase">Status</label>
                        <select
                            value={status}
                            onChange={(e) => { setStatus(e.target.value); setPage(1); }}
                            className="w-full border border-slate-200 rounded-lg text-xs text-slate-700 py-2 px-3 focus:outline-none focus:border-brand-400 transition bg-white"
                        >
                            <option value="">All Statuses</option>
                            <option value="Generated">Generated</option>
                            <option value="Archived">Archived</option>
                        </select>
                    </div>
                </div>
            </div>

            {/* Reports List Table */}
            {loading ? (
                <div className="bg-white border border-slate-200 rounded-xl p-12 flex flex-col items-center justify-center gap-3">
                    <Loader2 className="animate-spin text-brand-600" size={24} />
                    <span className="text-xs font-semibold text-slate-500">Retrieving enterprise reports catalog...</span>
                </div>
            ) : reports.length === 0 ? (
                <div className="bg-white border border-slate-200 rounded-xl p-16 text-center space-y-3.5 shadow-sm">
                    <div className="w-12 h-12 rounded-full bg-slate-50 border border-slate-200 flex items-center justify-center mx-auto text-slate-450">
                        <FileText size={20} />
                    </div>
                    <div className="max-w-xs mx-auto">
                        <h3 className="text-sm font-bold text-slate-800">No reports matched</h3>
                        <p className="text-xs text-slate-400 mt-1">
                            No executive threat analysis match the search terms.
                        </p>
                    </div>
                </div>
            ) : (
                <div className="bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden">
                    <div className="overflow-x-auto">
                        <table className="w-full text-left border-collapse table-fixed min-w-[900px]">
                            <thead>
                                <tr className="border-b border-slate-200 bg-slate-50/50 text-[10px] uppercase font-bold text-slate-500 tracking-wider">
                                    <th className="py-3.5 px-4 w-72">Report Identifier / Title</th>
                                    <th className="py-3.5 px-4 w-44">Type</th>
                                    <th className="py-3.5 px-4 w-32">Owner</th>
                                    <th className="py-3.5 px-4 w-28">Status</th>
                                    <th className="py-3.5 px-4 w-32">Generated Date</th>
                                    <th className="py-3.5 px-4 w-28 text-right pr-5">Actions</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100 text-xs text-slate-700">
                                {reports.map((r) => (
                                    <tr 
                                        key={r.id} 
                                        onClick={() => onNavigateToDetail(r.id)}
                                        className="hover:bg-slate-50/40 transition-colors cursor-pointer group"
                                    >
                                        <td className="py-4 px-4 align-middle">
                                            <div className="space-y-0.5">
                                                <span className="font-bold text-slate-800 group-hover:text-brand-600 block truncate transition-colors">
                                                    {r.title}
                                                </span>
                                                <span className="text-[9px] font-mono font-bold text-slate-400 tracking-wider block">{r.report_id}</span>
                                            </div>
                                        </td>
                                        <td className="py-4 px-4 align-middle font-semibold text-slate-700">
                                            {r.type}
                                        </td>
                                        <td className="py-4 px-4 align-middle truncate font-medium text-slate-650">
                                            {r.creator ? r.creator.name : 'System Admin'}
                                        </td>
                                        <td className="py-4 px-4 align-middle">
                                            <span className={`px-2 py-0.5 rounded text-[10px] font-bold border ${
                                                r.status === 'Archived' ? 'bg-slate-100 text-slate-500 border-slate-200' : 'bg-emerald-100 text-emerald-805 border-emerald-200'
                                            }`}>
                                                {r.status}
                                            </span>
                                        </td>
                                        <td className="py-4 px-4 align-middle font-mono text-[10px] text-slate-400">
                                            {new Date(r.created_at).toLocaleString()}
                                        </td>
                                        <td className="py-4 px-4 align-middle text-right pr-5" onClick={e => e.stopPropagation()}>
                                            <div className="flex items-center justify-end gap-1.5 opacity-0 group-hover:opacity-100 transition-opacity">
                                                <button
                                                    onClick={() => onNavigateToDetail(r.id)}
                                                    className="p-1 border border-slate-200 rounded hover:bg-slate-100 text-slate-500 hover:text-slate-800 transition"
                                                    title="View Report"
                                                >
                                                    <ArrowUpRight size={14} />
                                                </button>
                                                <button
                                                    onClick={(e) => handleDuplicate(r.id, e)}
                                                    className="p-1 border border-slate-200 rounded hover:bg-slate-100 text-slate-500 hover:text-slate-800 transition"
                                                    title="Duplicate Report"
                                                >
                                                    <Copy size={13} />
                                                </button>
                                                <button
                                                    onClick={(e) => handleArchive(r.id, e)}
                                                    className="p-1 border border-slate-200 rounded hover:bg-slate-100 text-slate-500 hover:text-slate-800 transition"
                                                    title="Archive Report"
                                                >
                                                    <Archive size={13} />
                                                </button>
                                                <button
                                                    onClick={(e) => handleDelete(r.id, e)}
                                                    className="p-1 border border-slate-200 rounded hover:bg-rose-50 text-slate-500 hover:text-rose-600 transition"
                                                    title="Delete Report"
                                                >
                                                    <Trash2 size={13} />
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </div>
            )}

            {/* Create Modal Dialog */}
            {showCreateModal && (
                <div className="fixed inset-0 bg-slate-900/40 backdrop-blur-sm z-50 flex items-center justify-center p-4">
                    <div className="bg-white border border-slate-200 rounded-2xl w-full max-w-md p-6 shadow-xl space-y-4 animate-in fade-in zoom-in-95 duration-150">
                        <div className="flex items-center justify-between border-b border-slate-100 pb-3">
                            <h2 className="text-sm font-bold text-slate-800 flex items-center gap-1.5">
                                <Sparkles size={16} className="text-brand-600" />
                                Compile Assessment Report
                            </h2>
                            <button
                                onClick={() => setShowCreateModal(false)}
                                className="p-1 text-slate-400 hover:text-slate-700 rounded-lg hover:bg-slate-50 transition"
                            >
                                <X size={16} />
                            </button>
                        </div>
                        
                        <form onSubmit={handleCreateReport} className="space-y-4">
                            <div className="space-y-1.5">
                                <label className="text-xs font-bold text-slate-700 block">Report Title</label>
                                <input
                                    type="text"
                                    placeholder="e.g. Q3 Compliance &amp; Vulnerability Posture Summary"
                                    value={newTitle}
                                    onChange={e => setNewTitle(e.target.value)}
                                    className="w-full border border-slate-200 rounded-lg text-xs py-2 px-3 bg-white focus:outline-none focus:border-brand-400"
                                    required
                                />
                            </div>
                            <div className="space-y-1.5">
                                <label className="text-xs font-bold text-slate-700 block">Report Type</label>
                                <select
                                    value={newType}
                                    onChange={e => setNewType(e.target.value)}
                                    className="w-full border border-slate-200 rounded-lg text-xs py-2 px-3 bg-white focus:outline-none focus:border-brand-400"
                                >
                                    <option value="Executive Summary">Executive Summary</option>
                                    <option value="Technical Assessment">Technical Assessment</option>
                                    <option value="Risk Report">Risk Report</option>
                                    <option value="Compliance Report">Compliance Report</option>
                                    <option value="Asset Coverage">Asset Coverage Report</option>
                                    <option value="Scan Coverage">Scan Coverage Report</option>
                                </select>
                            </div>
                            <div className="flex justify-end gap-2 border-t border-slate-100 pt-3.5">
                                <button
                                    type="button"
                                    onClick={() => setShowCreateModal(false)}
                                    className="px-3.5 py-2 border border-slate-200 rounded-lg text-xs font-semibold hover:bg-slate-50 text-slate-600 transition"
                                >
                                    Cancel
                                </button>
                                <button
                                    type="submit"
                                    disabled={saving}
                                    className="px-3.5 py-2 bg-brand-600 text-white rounded-lg text-xs font-semibold shadow transition hover:bg-brand-700 disabled:opacity-50 flex items-center gap-1.5"
                                >
                                    {saving ? <Loader2 className="animate-spin" size={13} /> : <Sparkles size={13} />}
                                    Compile Report
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}
        </div>
    );
}