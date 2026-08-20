import React, { useState, useEffect } from 'react';
import { 
    Search, ShieldAlert, ArrowUpRight, Loader2, RefreshCcw, 
    SlidersHorizontal, Calendar, X, AlertTriangle, Shield, CheckCircle2, ChevronRight, Edit2, Trash2, Plus
} from 'lucide-react';
import { SeverityBadge, StatusBadge, CvssIndicator } from '../components/ui/primitives_findings';
import axios from 'axios';

export default function FindingsPage({ onNavigateToCreate, onNavigateToEdit, onNavigateToDetail }) {
    const [findings, setFindings] = useState([]);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState(null);

    // Filter states
    const [search, setSearch] = useState('');
    const [severity, setSeverity] = useState('');
    const [status, setStatus] = useState('');
    const [targetId, setTargetId] = useState('');
    const [scanId, setScanId] = useState('');
    const [assetId, setAssetId] = useState('');
    const [cvssMin, setCvssMin] = useState('');
    const [cvssMax, setCvssMax] = useState('');
    const [dateFrom, setDateFrom] = useState('');
    const [dateTo, setDateTo] = useState('');

    const [page, setPage] = useState(1);
    const [meta, setMeta] = useState(null);

    // Dropdowns data
    const [targets, setTargets] = useState([]);
    const [scans, setScans] = useState([]);
    const [assets, setAssets] = useState([]);

    // Role state
    const [isManagerOrAdmin, setIsManagerOrAdmin] = useState(false);

    // Summary counts
    const [summary, setSummary] = useState({
        critical: 0,
        high: 0,
        medium: 0,
        low: 0,
        resolved: 0
    });

    // Fetch related resource dropdowns on mount
    useEffect(() => {
        const fetchDropdowns = async () => {
            try {
                const [tRes, sRes, aRes] = await Promise.all([
                    axios.get('/api/targets', { params: { per_page: 100 } }),
                    axios.get('/api/scans', { params: { per_page: 100 } }),
                    axios.get('/api/assets', { params: { per_page: 100 } })
                ]);
                setTargets(tRes.data.data || []);
                setScans(sRes.data.data || []);
                setAssets(aRes.data.data || []);
            } catch (err) {
                console.error('Failed to load filter option dropdowns:', err);
            }
        };
        fetchDropdowns();

        const checkRole = async () => {
            try {
                await axios.get('/api/dashboard/stats');
                setIsManagerOrAdmin(true); 
            } catch (err) {
                setIsManagerOrAdmin(false);
            }
        };
        checkRole();
    }, []);

    // Fetch findings with filters
    const fetchFindings = async () => {
        setLoading(true);
        setError(null);
        try {
            const params = {
                search,
                severity,
                status,
                target_id: targetId,
                scan_id: scanId,
                asset_id: assetId,
                cvss_min: cvssMin,
                cvss_max: cvssMax,
                date_from: dateFrom,
                date_to: dateTo,
                page,
            };
            const response = await axios.get('/api/findings', { params });
            const data = response.data.data || [];
            setFindings(data);
            setMeta(response.data.meta || null);

            // Compute summary metrics from current findings load
            let crit = 0, hg = 0, md = 0, lw = 0, res = 0;
            data.forEach(f => {
                const sev = (f.severity || '').toLowerCase();
                const st = (f.status || '').toLowerCase();
                if (sev === 'critical') crit++;
                if (sev === 'high') hg++;
                if (sev === 'medium') md++;
                if (sev === 'low') lw++;
                if (st === 'remediated' || st === 'resolved') res++;
            });
            setSummary({ critical: crit, high: hg, medium: md, low: lw, resolved: res });

        } catch (err) {
            console.error('Failed to load findings catalog:', err);
            setError('Failed to fetch findings from repository.');
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => {
        fetchFindings();
    }, [search, severity, status, targetId, scanId, assetId, cvssMin, cvssMax, dateFrom, dateTo, page]);

    const handleClearFilters = () => {
        setSearch('');
        setSeverity('');
        setStatus('');
        setTargetId('');
        setScanId('');
        setAssetId('');
        setCvssMin('');
        setCvssMax('');
        setDateFrom('');
        setDateTo('');
        setPage(1);
    };

    const handleDelete = async (id) => {
        if (!confirm('Are you sure you want to permanently delete this security finding?')) return;
        try {
            await axios.delete(`/api/findings/${id}`);
            fetchFindings();
        } catch (err) {
            alert('Failed to delete finding: ' + (err.response?.data?.message || 'Access denied.'));
        }
    };

    const activeFiltersCount = [
        search, severity, status, targetId, scanId, assetId, cvssMin, cvssMax, dateFrom, dateTo
    ].filter(Boolean).length;

    return (
        <div className="space-y-6">
            {/* ── Title & Actions Header */}
            <div className="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                <div>
                    <h1 className="text-xl font-bold text-slate-900 tracking-tight flex items-center gap-2">
                        <ShieldAlert className="text-brand-600" size={22} strokeWidth={2} />
                        Findings Catalog
                    </h1>
                    <p className="text-xs text-slate-500 mt-0.5">
                        Enterprise vulnerability management repository and risk mitigation workspace.
                    </p>
                </div>
                {isManagerOrAdmin && (
                    <button
                        onClick={onNavigateToCreate}
                        className="inline-flex items-center justify-center gap-1.5 px-3.5 py-2 text-xs font-semibold text-white bg-brand-600 hover:bg-brand-700 active:bg-brand-800 rounded-lg shadow-sm border border-brand-700 transition"
                    >
                        Log Manual Finding
                    </button>
                )}
            </div>

            {/* ── Metrics Cards Summary */}
            <div className="grid grid-cols-2 sm:grid-cols-5 gap-3.5">
                {[
                    { label: 'Critical', value: summary.critical, border: 'border-l-rose-500 bg-rose-50/10 text-rose-800' },
                    { label: 'High',     value: summary.high,     border: 'border-l-orange-500 bg-orange-50/10 text-orange-850' },
                    { label: 'Medium',   value: summary.medium,   border: 'border-l-amber-500 bg-amber-50/10 text-amber-800' },
                    { label: 'Low',      value: summary.low,      border: 'border-l-sky-500 bg-sky-50/10 text-sky-855' },
                    { label: 'Resolved', value: summary.resolved, border: 'border-l-emerald-500 bg-emerald-50/10 text-emerald-800' },
                ].map((card, i) => (
                    <div key={i} className={`bg-white border border-slate-200 border-l-4 ${card.border} rounded-xl p-4 shadow-sm flex flex-col justify-between`}>
                        <span className="text-[10px] uppercase font-bold text-slate-400 tracking-wider">{card.label}</span>
                        <span className="text-xl font-bold mt-1.5">{card.value}</span>
                    </div>
                ))}
            </div>

            {/* ── Premium Filter Panel */}
            <div className="bg-white border border-slate-200 rounded-xl p-4 shadow-sm space-y-4">
                <div className="flex items-center justify-between border-b border-slate-100 pb-2">
                    <div className="flex items-center gap-1.5 text-xs font-bold text-slate-700">
                        <SlidersHorizontal size={14} className="text-slate-400" />
                        <span>Filter Findings</span>
                        {activeFiltersCount > 0 && (
                            <span className="bg-brand-100 text-brand-700 text-[10px] px-1.5 py-0.5 rounded-full font-bold ml-1">
                                {activeFiltersCount} active
                            </span>
                        )}
                    </div>
                    {activeFiltersCount > 0 && (
                        <button
                            onClick={handleClearFilters}
                            className="text-[10px] text-slate-400 hover:text-slate-700 font-semibold flex items-center gap-1 transition"
                        >
                            <RefreshCcw size={10} /> Clear Filters
                        </button>
                    )}
                </div>

                <div className="grid grid-cols-1 md:grid-cols-4 gap-3.5">
                    {/* Search */}
                    <div className="md:col-span-2 space-y-1">
                        <label className="text-[10px] font-bold text-slate-500 uppercase">Search</label>
                        <div className="relative">
                            <Search className="absolute left-3 top-2.5 text-slate-400" size={14} />
                            <input
                                type="text"
                                placeholder="Search by ID, title, CVE or CWE..."
                                value={search}
                                onChange={(e) => { setSearch(e.target.value); setPage(1); }}
                                className="w-full pl-8 pr-3 py-2 border border-slate-200 rounded-lg text-xs text-slate-800 bg-white placeholder-slate-400 focus:outline-none focus:border-brand-400 transition"
                            />
                        </div>
                    </div>

                    {/* Severity */}
                    <div className="space-y-1">
                        <label className="text-[10px] font-bold text-slate-500 uppercase">Severity</label>
                        <select
                            value={severity}
                            onChange={(e) => { setSeverity(e.target.value); setPage(1); }}
                            className="w-full border border-slate-200 rounded-lg text-xs text-slate-700 py-2 px-3 focus:outline-none focus:border-brand-400 transition bg-white"
                        >
                            <option value="">All Severities</option>
                            <option value="critical">Critical</option>
                            <option value="high">High</option>
                            <option value="medium">Medium</option>
                            <option value="low">Low</option>
                            <option value="info">Info</option>
                        </select>
                    </div>

                    {/* Status */}
                    <div className="space-y-1">
                        <label className="text-[10px] font-bold text-slate-500 uppercase">Workflow Status</label>
                        <select
                            value={status}
                            onChange={(e) => { setStatus(e.target.value); setPage(1); }}
                            className="w-full border border-slate-200 rounded-lg text-xs text-slate-700 py-2 px-3 focus:outline-none focus:border-brand-400 transition bg-white"
                        >
                            <option value="">All Statuses</option>
                            <option value="open">New</option>
                            <option value="in_progress">In Progress</option>
                            <option value="remediated">Resolved</option>
                            <option value="false_positive">False Positive</option>
                            <option value="risk_accepted">Accepted Risk</option>
                        </select>
                    </div>

                    {/* Target Scope */}
                    <div className="space-y-1">
                        <label className="text-[10px] font-bold text-slate-500 uppercase">Target Scope</label>
                        <select
                            value={targetId}
                            onChange={(e) => { setTargetId(e.target.value); setPage(1); }}
                            className="w-full border border-slate-200 rounded-lg text-xs text-slate-700 py-2 px-3 focus:outline-none focus:border-brand-400 transition bg-white"
                        >
                            <option value="">All Targets</option>
                            {targets.map((t) => (
                                <option key={t.id} value={t.id}>{t.name}</option>
                            ))}
                        </select>
                    </div>

                    {/* Origin Scan */}
                    <div className="space-y-1">
                        <label className="text-[10px] font-bold text-slate-500 uppercase">Origin Scan</label>
                        <select
                            value={scanId}
                            onChange={(e) => { setScanId(e.target.value); setPage(1); }}
                            className="w-full border border-slate-200 rounded-lg text-xs text-slate-700 py-2 px-3 focus:outline-none focus:border-brand-400 transition bg-white"
                        >
                            <option value="">All Scans</option>
                            {scans.map((s) => (
                                <option key={s.id} value={s.id}>{s.name}</option>
                            ))}
                        </select>
                    </div>

                    {/* Mapped Asset */}
                    <div className="space-y-1">
                        <label className="text-[10px] font-bold text-slate-500 uppercase">Mapped Asset</label>
                        <select
                            value={assetId}
                            onChange={(e) => { setAssetId(e.target.value); setPage(1); }}
                            className="w-full border border-slate-200 rounded-lg text-xs text-slate-700 py-2 px-3 focus:outline-none focus:border-brand-400 transition bg-white"
                        >
                            <option value="">All Assets</option>
                            {assets.map((a) => (
                                <option key={a.id} value={a.id}>{a.name}</option>
                            ))}
                        </select>
                    </div>

                    {/* CVSS Score Bounds */}
                    <div className="space-y-1">
                        <label className="text-[10px] font-bold text-slate-500 uppercase">CVSS Score Range</label>
                        <div className="flex items-center gap-2">
                            <input
                                type="number"
                                step="0.1"
                                min="0"
                                max="10"
                                placeholder="Min"
                                value={cvssMin}
                                onChange={(e) => { setCvssMin(e.target.value); setPage(1); }}
                                className="w-full px-2 py-1.5 border border-slate-200 rounded-lg text-xs text-slate-800 bg-white placeholder-slate-400 focus:outline-none focus:border-brand-400 transition"
                            />
                            <span className="text-slate-400 text-xs">to</span>
                            <input
                                type="number"
                                step="0.1"
                                min="0"
                                max="10"
                                placeholder="Max"
                                value={cvssMax}
                                onChange={(e) => { setCvssMax(e.target.value); setPage(1); }}
                                className="w-full px-2 py-1.5 border border-slate-200 rounded-lg text-xs text-slate-800 bg-white placeholder-slate-400 focus:outline-none focus:border-brand-400 transition"
                            />
                        </div>
                    </div>
                </div>
            </div>

            {/* ── Findings Table */}
            {error && (
                <div className="bg-rose-50 border border-rose-200 text-rose-800 text-xs p-4 rounded-xl flex items-center gap-2 font-medium">
                    <AlertTriangle size={15} className="text-rose-500" />
                    <span>{error}</span>
                </div>
            )}

            {loading ? (
                <div className="bg-white border border-slate-200 rounded-xl p-12 flex flex-col items-center justify-center gap-3">
                    <Loader2 className="animate-spin text-brand-600" size={24} />
                    <span className="text-xs font-semibold text-slate-500">Querying findings repository...</span>
                </div>
            ) : findings.length === 0 ? (
                <div className="bg-white border border-slate-200 rounded-xl p-16 text-center space-y-3.5 shadow-sm">
                    <div className="w-12 h-12 rounded-full bg-slate-50 border border-slate-200 flex items-center justify-center mx-auto text-slate-400">
                        <ShieldAlert size={20} />
                    </div>
                    <div className="max-w-xs mx-auto">
                        <h3 className="text-sm font-bold text-slate-800">No vulnerabilities detected</h3>
                        <p className="text-xs text-slate-400 mt-1">
                            No security vulnerabilities match the chosen filter configuration.
                        </p>
                    </div>
                </div>
            ) : (
                <div className="bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden">
                    <div className="overflow-x-auto">
                        <table className="w-full text-left border-collapse table-fixed min-w-[1200px]">
                            <thead>
                                <tr className="border-b border-slate-200 bg-slate-50/50 text-[10px] uppercase font-bold text-slate-500 tracking-wider">
                                    <th className="py-3.5 px-4 w-28">Severity</th>
                                    <th className="py-3.5 px-4 w-72">Title / CVE</th>
                                    <th className="py-3.5 px-4 w-24">CVSS</th>
                                    <th className="py-3.5 px-4 w-40">Target</th>
                                    <th className="py-3.5 px-4 w-40">Scan</th>
                                    <th className="py-3.5 px-4 w-40">Target / Asset</th>
                                    <th className="py-3.5 px-4 w-28">Status</th>
                                    <th className="py-3.5 px-4 w-32">Detected</th>
                                    <th className="py-3.5 px-4 w-32">Updated</th>
                                    <th className="py-3.5 px-4 w-20 text-right pr-5">Actions</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100 text-xs text-slate-700">
                                {findings.map((f) => (
                                    <tr 
                                        key={f.id} 
                                        onClick={() => onNavigateToDetail(f.id)}
                                        className="hover:bg-slate-50/40 transition-colors cursor-pointer group"
                                    >
                                        {/* Severity Badge */}
                                        <td className="py-4 px-4 align-middle">
                                            <SeverityBadge severity={f.severity} />
                                        </td>

                                        {/* Title / CVE */}
                                        <td className="py-4 px-4 align-middle">
                                            <div className="space-y-1">
                                                <span className="font-bold text-slate-800 group-hover:text-brand-600 block truncate transition-colors">
                                                    {f.title}
                                                </span>
                                                <div className="flex items-center gap-1.5">
                                                    <span className="text-[9px] font-mono font-bold text-slate-400 uppercase tracking-wider">{f.finding_id}</span>
                                                    {f.cve && (
                                                        <span className="text-[9px] font-mono px-1 py-0.5 rounded bg-slate-100 text-slate-500 font-bold border border-slate-200">
                                                            {f.cve}
                                                        </span>
                                                    )}
                                                </div>
                                            </div>
                                        </td>

                                        {/* CVSS indicator */}
                                        <td className="py-4 px-4 align-middle">
                                            {f.cvss_score ? (
                                                <CvssIndicator score={f.cvss_score} />
                                            ) : (
                                                <span className="text-slate-350 font-mono">-</span>
                                            )}
                                        </td>

                                        {/* Target */}
                                        <td className="py-4 px-4 align-middle truncate">
                                            {f.target ? (
                                                <div className="space-y-0.5">
                                                    <span className="font-semibold text-slate-800 block truncate">{f.target.name}</span>
                                                    <span className="text-[10px] font-mono text-slate-400 block truncate">{f.target.value}</span>
                                                </div>
                                            ) : (
                                                <span className="text-slate-350 italic">Unmapped</span>
                                            )}
                                        </td>

                                        {/* Origin Scan */}
                                        <td className="py-4 px-4 align-middle truncate">
                                            {f.scan ? (
                                                <span className="font-semibold text-slate-700 block truncate" title={f.scan.name}>
                                                    {f.scan.name}
                                                </span>
                                            ) : (
                                                <span className="text-slate-350 italic">Manual Entry</span>
                                            )}
                                        </td>

                                        {/* Asset */}
                                        <td className="py-4 px-4 align-middle truncate">
                                            {f.asset ? (
                                                <span className="font-semibold text-slate-700 block truncate">
                                                    {f.asset.name}
                                                </span>
                                            ) : f.target ? (
                                                <span className="font-semibold text-slate-700 block truncate">
                                                    {f.target.name}
                                                </span>
                                            ) : f.url ? (
                                                <span className="font-semibold text-slate-700 block truncate" title={f.url}>
                                                    {f.url.length > 25 ? f.url.substring(0, 25) + '...' : f.url}
                                                </span>
                                            ) : (
                                                <span className="text-slate-350 italic">Unmapped</span>
                                            )}
                                        </td>

                                        {/* Workflow Status */}
                                        <td className="py-4 px-4 align-middle">
                                            <StatusBadge status={f.status} />
                                        </td>

                                        {/* Detected at */}
                                        <td className="py-4 px-4 align-middle font-mono text-[10px] text-slate-400">
                                            {f.created_at ? new Date(f.created_at).toLocaleDateString() : '-'}
                                        </td>

                                        {/* Updated at */}
                                        <td className="py-4 px-4 align-middle font-mono text-[10px] text-slate-400">
                                            {f.updated_at ? new Date(f.updated_at).toLocaleDateString() : '-'}
                                        </td>

                                        {/* Hover actions */}
                                        <td className="py-4 px-4 align-middle text-right pr-5" onClick={e => e.stopPropagation()}>
                                            <div className="flex items-center justify-end gap-1.5 opacity-0 group-hover:opacity-100 transition-opacity">
                                                <button
                                                    onClick={() => onNavigateToDetail(f.id)}
                                                    className="p-1 border border-slate-200 rounded hover:bg-slate-100 text-slate-500 hover:text-slate-800 transition"
                                                    title="View details"
                                                >
                                                    <ArrowUpRight size={14} />
                                                </button>
                                                {isManagerOrAdmin && (
                                                    <>
                                                        <button
                                                            onClick={() => onNavigateToEdit(f.id)}
                                                            className="p-1 border border-slate-200 rounded hover:bg-slate-100 text-slate-500 hover:text-slate-800 transition"
                                                            title="Edit finding"
                                                        >
                                                            <Edit2 size={13} />
                                                        </button>
                                                        <button
                                                            onClick={() => handleDelete(f.id)}
                                                            className="p-1 border border-slate-200 rounded hover:bg-rose-50 text-slate-550 hover:text-rose-600 transition"
                                                            title="Delete finding"
                                                        >
                                                            <Trash2 size={13} />
                                                        </button>
                                                    </>
                                                )}
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>

                    {/* Pagination Footer */}
                    {meta && meta.last_page > 1 && (
                        <div className="border-t border-slate-100 px-5 py-3.5 bg-slate-50/50 flex items-center justify-between gap-4">
                            <span className="text-[10px] font-bold text-slate-500 uppercase tracking-wide">
                                Page {meta.current_page} of {meta.last_page} ({meta.total} total findings)
                            </span>
                            <div className="flex items-center gap-1.5">
                                <button
                                    disabled={meta.current_page === 1}
                                    onClick={() => setPage(meta.current_page - 1)}
                                    className="px-3 py-1.5 text-[10px] font-bold border border-slate-200 rounded-lg bg-white hover:bg-slate-50 text-slate-700 disabled:opacity-40 transition shadow-sm"
                                >
                                    Previous
                                </button>
                                <button
                                    disabled={meta.current_page === meta.last_page}
                                    onClick={() => setPage(meta.current_page + 1)}
                                    className="px-3 py-1.5 text-[10px] font-bold border border-slate-200 rounded-lg bg-white hover:bg-slate-50 text-slate-700 disabled:opacity-40 transition shadow-sm"
                                >
                                    Next
                                </button>
                            </div>
                        </div>
                    )}
                </div>
            )}
        </div>
    );
}
