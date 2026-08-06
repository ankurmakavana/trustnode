import React, { useState, useEffect } from 'react';
import { 
    Search, ShieldAlert, ArrowUpRight, Loader2, RefreshCcw, 
    SlidersHorizontal, Calendar, X, AlertTriangle, Shield, CheckCircle2, ChevronRight, Edit2, Trash2, Plus, Grid, ListFilter
} from 'lucide-react';
import axios from 'axios';

export default function RiskDashboardPage({ onNavigateToCreate, onNavigateToEdit, onNavigateToDetail }) {
    const [risks, setRisks] = useState([]);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState(null);

    // Filters
    const [search, setSearch] = useState('');
    const [riskLevel, setRiskLevel] = useState('');
    const [status, setStatus] = useState('');
    const [ownerId, setOwnerId] = useState('');
    const [cellLikelihood, setCellLikelihood] = useState('');
    const [cellImpact, setCellImpact] = useState('');
    const [page, setPage] = useState(1);
    const [meta, setMeta] = useState(null);

    // Users list for owner filter
    const [users, setUsers] = useState([]);
    const [summary, setSummary] = useState({
        open: 0,
        critical: 0,
        high: 0,
        accepted: 0,
        resolved: 0
    });

    const likelihoods = ['Almost Certain', 'Likely', 'Possible', 'Unlikely', 'Rare'];
    const impacts = ['Negligible', 'Minor', 'Moderate', 'Major', 'Catastrophic'];

    // Fetch users
    useEffect(() => {
        axios.get('/api/assets') // dummy check
            .then(() => {
                setUsers([
                    { id: 1, name: 'TrustNode Admin' },
                    { id: 2, name: 'Security Analyst' }
                ]);
            })
            .catch(err => console.error(err));
    }, []);

    const fetchRisks = async () => {
        setLoading(true);
        setError(null);
        try {
            const params = {
                search,
                risk_level: riskLevel,
                status,
                owner_id: ownerId,
                likelihood: cellLikelihood,
                impact: cellImpact,
                page
            };
            const response = await axios.get('/api/risks', { params });
            const data = response.data.data || [];
            setRisks(data);
            setMeta(response.data || null);

            // Calculate stats dynamically from data
            let op = 0, crit = 0, hg = 0, acc = 0, res = 0;
            data.forEach(r => {
                const st = (r.status || '').toLowerCase();
                const lv = (r.risk_level || '').toLowerCase();
                if (st === 'open' || st === 'mitigating') op++;
                if (lv === 'critical') crit++;
                if (lv === 'high') hg++;
                if (st === 'accepted') acc++;
                if (st === 'resolved' || st === 'closed') res++;
            });
            setSummary({ open: op, critical: crit, high: hg, accepted: acc, resolved: res });
        } catch (err) {
            console.error('Failed to load risk catalog:', err);
            setError('Failed to fetch risk catalog from register.');
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => {
        fetchRisks();
    }, [search, riskLevel, status, ownerId, cellLikelihood, cellImpact, page]);

    const handleClearFilters = () => {
        setSearch('');
        setRiskLevel('');
        setStatus('');
        setOwnerId('');
        setCellLikelihood('');
        setCellImpact('');
        setPage(1);
    };

    const handleDelete = async (id) => {
        if (!confirm('Are you sure you want to permanently delete this risk register?')) return;
        try {
            await axios.delete(`/api/risks/${id}`);
            fetchRisks();
        } catch (err) {
            alert('Failed to delete risk: ' + (err.response?.data?.message || 'Access denied.'));
        }
    };

    const getCellColor = (l, i) => {
        const lMap = { 'Almost Certain': 5, 'Likely': 4, 'Possible': 3, 'Unlikely': 2, 'Rare': 1 };
        const iMap = { 'Catastrophic': 5, 'Major': 4, 'Moderate': 3, 'Minor': 2, 'Negligible': 1 };
        const score = lMap[l] * iMap[i];
        
        const isSelected = cellLikelihood === l && cellImpact === i;
        let baseClass = '';
        if (score >= 16) {
            baseClass = 'bg-rose-500 hover:bg-rose-600 text-white';
        } else if (score >= 10) {
            baseClass = 'bg-orange-500 hover:bg-orange-600 text-white';
        } else if (score >= 5) {
            baseClass = 'bg-amber-400 hover:bg-amber-500 text-slate-900';
        } else {
            baseClass = 'bg-emerald-500 hover:bg-emerald-600 text-white';
        }

        if (isSelected) {
            return `${baseClass} ring-4 ring-brand-600 ring-offset-2 z-10 scale-105`;
        }
        return baseClass;
    };

    const getLevelBadgeClass = (level) => {
        switch (level) {
            case 'Critical': return 'bg-rose-100 text-rose-800 border-rose-200';
            case 'High': return 'bg-orange-100 text-orange-850 border-orange-200';
            case 'Medium': return 'bg-amber-100 text-amber-800 border-amber-200';
            default: return 'bg-emerald-100 text-emerald-805 border-emerald-200';
        }
    };

    const getStatusBadgeClass = (st) => {
        switch (st) {
            case 'Mitigating': return 'bg-blue-100 text-blue-800 border-blue-200';
            case 'Accepted': return 'bg-purple-100 text-purple-800 border-purple-200';
            case 'Resolved': return 'bg-emerald-100 text-emerald-800 border-emerald-200';
            case 'Closed': return 'bg-slate-100 text-slate-650 border-slate-200';
            default: return 'bg-amber-100 text-amber-800 border-amber-200';
        }
    };

    const activeFiltersCount = [
        search, riskLevel, status, ownerId, cellLikelihood, cellImpact
    ].filter(Boolean).length;

    return (
        <div className="space-y-6">
            {/* Title & Actions Header */}
            <div className="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                <div>
                    <h1 className="text-xl font-bold text-slate-900 tracking-tight flex items-center gap-2">
                        <Shield className="text-brand-600" size={22} strokeWidth={2} />
                        Enterprise Risk Register
                    </h1>
                    <p className="text-xs text-slate-500 mt-0.5">
                        Translate technical vulnerability findings into strategic corporate business risks.
                    </p>
                </div>
                <button
                    onClick={onNavigateToCreate}
                    className="inline-flex items-center justify-center gap-1.5 px-3.5 py-2 text-xs font-semibold text-white bg-brand-600 hover:bg-brand-700 active:bg-brand-800 rounded-lg shadow-sm border border-brand-700 transition"
                >
                    <Plus size={14} />
                    Register Corporate Risk
                </button>
            </div>

            {/* Summary cards */}
            <div className="grid grid-cols-2 sm:grid-cols-5 gap-3.5">
                {[
                    { label: 'Open Risks', value: summary.open, border: 'border-l-blue-500 bg-blue-50/10 text-blue-800' },
                    { label: 'Critical Risks', value: summary.critical, border: 'border-l-rose-500 bg-rose-50/10 text-rose-800' },
                    { label: 'High Risks', value: summary.high, border: 'border-l-orange-500 bg-orange-50/10 text-orange-850' },
                    { label: 'Accepted Risks', value: summary.accepted, border: 'border-l-purple-500 bg-purple-50/10 text-purple-800' },
                    { label: 'Resolved Risks', value: summary.resolved, border: 'border-l-emerald-500 bg-emerald-50/10 text-emerald-800' },
                ].map((card, i) => (
                    <div key={i} className={`bg-white border border-slate-200 border-l-4 ${card.border} rounded-xl p-4 shadow-sm flex flex-col justify-between`}>
                        <span className="text-[10px] uppercase font-bold text-slate-400 tracking-wider">{card.label}</span>
                        <span className="text-xl font-bold mt-1.5">{card.value}</span>
                    </div>
                ))}
            </div>

            {/* Interactive 5x5 Risk Matrix */}
            <div className="bg-white border border-slate-200 rounded-xl p-6 shadow-sm space-y-4">
                <div className="flex items-center justify-between border-b border-slate-100 pb-2">
                    <span className="text-xs font-bold text-slate-700 uppercase tracking-wider flex items-center gap-1.5">
                        <Grid size={14} className="text-slate-400" />
                        Risk Likelihood vs Impact Matrix
                    </span>
                    {(cellLikelihood || cellImpact) && (
                        <button
                            onClick={() => { setCellLikelihood(''); setCellImpact(''); }}
                            className="text-[10px] text-brand-600 hover:text-brand-700 font-semibold flex items-center gap-1 transition"
                        >
                            Reset Matrix Filter
                        </button>
                    )}
                </div>

                <div className="overflow-x-auto">
                    <div className="min-w-[650px] grid grid-cols-6 gap-2 text-center text-[10px] font-bold text-slate-500">
                        <div className="flex items-center justify-center font-normal italic">Likelihood \ Impact</div>
                        {impacts.map(imp => (
                            <div key={imp} className="py-2 bg-slate-50 border border-slate-100 rounded-lg">{imp}</div>
                        ))}

                        {likelihoods.map(lik => (
                            <React.Fragment key={lik}>
                                <div className="py-4 bg-slate-50 border border-slate-100 rounded-lg flex items-center justify-center">{lik}</div>
                                {impacts.map(imp => (
                                    <button
                                        key={imp}
                                        type="button"
                                        onClick={() => {
                                            if (cellLikelihood === lik && cellImpact === imp) {
                                                setCellLikelihood('');
                                                setCellImpact('');
                                            } else {
                                                setCellLikelihood(lik);
                                                setCellImpact(imp);
                                                setPage(1);
                                            }
                                        }}
                                        className={`py-4 rounded-lg font-bold text-xs transition-all ${getCellColor(lik, imp)}`}
                                    >
                                        {lik === 'Almost Certain' && imp === 'Catastrophic' ? '25 (Critical)' : 
                                         lik === 'Rare' && imp === 'Negligible' ? '1 (Low)' : ''}
                                    </button>
                                ))}
                            </React.Fragment>
                        ))}
                    </div>
                </div>
            </div>

            {/* Filters panel */}
            <div className="bg-white border border-slate-200 rounded-xl p-4 shadow-sm space-y-4">
                <div className="flex items-center justify-between border-b border-slate-100 pb-2">
                    <div className="flex items-center gap-1.5 text-xs font-bold text-slate-700">
                        <SlidersHorizontal size={14} className="text-slate-400" />
                        <span>Filter Register</span>
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
                    <div className="md:col-span-2 space-y-1.5">
                        <label className="text-[10px] font-bold text-slate-500 uppercase">Search Risks</label>
                        <div className="relative">
                            <Search className="absolute left-3 top-2.5 text-slate-400" size={14} />
                            <input
                                type="text"
                                placeholder="Search by ID, title or description..."
                                value={search}
                                onChange={(e) => { setSearch(e.target.value); setPage(1); }}
                                className="w-full pl-8 pr-3 py-2 border border-slate-200 rounded-lg text-xs text-slate-805 bg-white placeholder-slate-400 focus:outline-none focus:border-brand-400 transition"
                            />
                        </div>
                    </div>
                    <div className="space-y-1.5">
                        <label className="text-[10px] font-bold text-slate-500 uppercase">Severity Level</label>
                        <select
                            value={riskLevel}
                            onChange={(e) => { setRiskLevel(e.target.value); setPage(1); }}
                            className="w-full border border-slate-200 rounded-lg text-xs text-slate-700 py-2 px-3 focus:outline-none focus:border-brand-400 transition bg-white"
                        >
                            <option value="">All Levels</option>
                            <option value="Critical">Critical</option>
                            <option value="High">High</option>
                            <option value="Medium">Medium</option>
                            <option value="Low">Low</option>
                        </select>
                    </div>
                    <div className="space-y-1.5">
                        <label className="text-[10px] font-bold text-slate-500 uppercase">Workflow Status</label>
                        <select
                            value={status}
                            onChange={(e) => { setStatus(e.target.value); setPage(1); }}
                            className="w-full border border-slate-200 rounded-lg text-xs text-slate-700 py-2 px-3 focus:outline-none focus:border-brand-400 transition bg-white"
                        >
                            <option value="">All Statuses</option>
                            <option value="Open">Open</option>
                            <option value="Mitigating">Mitigating</option>
                            <option value="Accepted">Accepted</option>
                            <option value="Resolved">Resolved</option>
                            <option value="Closed">Closed</option>
                        </select>
                    </div>
                </div>
            </div>

            {/* Risks Table */}
            {error && (
                <div className="bg-rose-50 border border-rose-200 text-rose-800 text-xs p-4 rounded-xl flex items-center gap-2 font-medium">
                    <AlertTriangle size={15} className="text-rose-500" />
                    <span>{error}</span>
                </div>
            )}

            {loading ? (
                <div className="bg-white border border-slate-200 rounded-xl p-12 flex flex-col items-center justify-center gap-3">
                    <Loader2 className="animate-spin text-brand-600" size={24} />
                    <span className="text-xs font-semibold text-slate-500">Querying corporate risk registry...</span>
                </div>
            ) : risks.length === 0 ? (
                <div className="bg-white border border-slate-200 rounded-xl p-16 text-center space-y-3.5 shadow-sm">
                    <div className="w-12 h-12 rounded-full bg-slate-50 border border-slate-200 flex items-center justify-center mx-auto text-slate-400">
                        <Shield size={20} />
                    </div>
                    <div className="max-w-xs mx-auto">
                        <h3 className="text-sm font-bold text-slate-800">No risks registered</h3>
                        <p className="text-xs text-slate-400 mt-1">
                            No threat risk parameters match the current search configuration.
                        </p>
                    </div>
                </div>
            ) : (
                <div className="bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden">
                    <div className="overflow-x-auto">
                        <table className="w-full text-left border-collapse table-fixed min-w-[1000px]">
                            <thead>
                                <tr className="border-b border-slate-200 bg-slate-50/50 text-[10px] uppercase font-bold text-slate-500 tracking-wider">
                                    <th className="py-3.5 px-4 w-64">Risk Identifier / Title</th>
                                    <th className="py-3.5 px-4 w-28">Level</th>
                                    <th className="py-3.5 px-4 w-24">Matrix Score</th>
                                    <th className="py-3.5 px-4 w-40">Owner</th>
                                    <th className="py-3.5 px-4 w-32">Status</th>
                                    <th className="py-3.5 px-4 w-36">Review Date</th>
                                    <th className="py-3.5 px-4 w-40">Related Findings</th>
                                    <th className="py-3.5 px-4 w-20 text-right pr-5">Actions</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100 text-xs text-slate-700">
                                {risks.map((r) => (
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
                                                <span className="text-[9px] font-mono font-bold text-slate-400 tracking-wider block">{r.risk_id}</span>
                                            </div>
                                        </td>
                                        <td className="py-4 px-4 align-middle">
                                            <span className={`px-2 py-0.5 rounded text-[10px] font-bold border ${getLevelBadgeClass(r.risk_level)}`}>
                                                {r.risk_level}
                                            </span>
                                        </td>
                                        <td className="py-4 px-4 align-middle font-mono font-bold text-slate-700">
                                            {r.risk_score} / 25
                                        </td>
                                        <td className="py-4 px-4 align-middle truncate font-semibold text-slate-850">
                                            {r.owner ? r.owner.name : 'Unassigned'}
                                        </td>
                                        <td className="py-4 px-4 align-middle">
                                            <span className={`px-2 py-0.5 rounded text-[10px] font-bold border ${getStatusBadgeClass(r.status)}`}>
                                                {r.status}
                                            </span>
                                        </td>
                                        <td className="py-4 px-4 align-middle font-mono text-[10px] text-slate-400">
                                            {r.review_date ? new Date(r.review_date).toLocaleDateString() : 'N/A'}
                                        </td>
                                        <td className="py-4 px-4 align-middle">
                                            {r.findings && r.findings.length > 0 ? (
                                                <span className="bg-slate-100 text-slate-600 font-mono font-bold text-[9px] px-1.5 py-0.5 rounded border border-slate-200">
                                                    {r.findings.length} findings linked
                                                </span>
                                            ) : (
                                                <span className="text-slate-355 italic text-[10px]">None linked</span>
                                            )}
                                        </td>
                                        <td className="py-4 px-4 align-middle text-right pr-5" onClick={e => e.stopPropagation()}>
                                            <div className="flex items-center justify-end gap-1.5 opacity-0 group-hover:opacity-100 transition-opacity">
                                                <button
                                                    onClick={() => onNavigateToDetail(r.id)}
                                                    className="p-1 border border-slate-200 rounded hover:bg-slate-100 text-slate-500 hover:text-slate-800 transition"
                                                    title="View details"
                                                >
                                                    <ArrowUpRight size={14} />
                                                </button>
                                                <button
                                                    onClick={() => onNavigateToEdit(r.id)}
                                                    className="p-1 border border-slate-200 rounded hover:bg-slate-100 text-slate-500 hover:text-slate-800 transition"
                                                    title="Edit risk"
                                                >
                                                    <Edit2 size={13} />
                                                </button>
                                                <button
                                                    onClick={() => handleDelete(r.id)}
                                                    className="p-1 border border-slate-200 rounded hover:bg-rose-50 text-slate-500 hover:text-rose-600 transition"
                                                    title="Delete risk"
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
        </div>
    );
}