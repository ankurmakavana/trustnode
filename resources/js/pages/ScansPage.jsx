import React, { useState, useEffect, useCallback, useRef } from 'react';
import {
    Search, Plus, RefreshCw, ChevronLeft, ChevronRight,
    Eye, Edit2, Trash2, Square, Pause, Play, RotateCcw,
    FileText, X, ScanLine, AlertTriangle, CheckCircle2,
    Activity, Clock, Loader2, Filter, CalendarDays,
} from 'lucide-react';
import axios from 'axios';
import { ScanStatusBadge, ScanTypeBadge, ScanEngineBadge, ProgressBar, ScanRowSkeleton } from '../components/ui/primitives_scans';

// ─── Helpers ──────────────────────────────────────────────────────────────────

function fmt(iso) {
    if (!iso) return '—';
    const d = new Date(iso);
    return d.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' })
        + ' ' + d.toLocaleTimeString('en-GB', { hour: '2-digit', minute: '2-digit' });
}

function fmtDuration(seconds) {
    if (!seconds) return '—';
    const m = Math.floor(seconds / 60);
    const s = seconds % 60;
    if (m === 0) return `${s}s`;
    return `${m}m ${s}s`;
}

function fmtRelative(iso) {
    if (!iso) return '—';
    const diff = Date.now() - new Date(iso).getTime();
    const mins = Math.floor(diff / 60000);
    if (mins < 1) return 'just now';
    if (mins < 60) return `${mins}m ago`;
    const hrs = Math.floor(mins / 60);
    if (hrs < 24) return `${hrs}h ago`;
    return `${Math.floor(hrs / 24)}d ago`;
}

// ─── Summary Card ─────────────────────────────────────────────────────────────

function SummaryCard({ label, value, icon: Icon, color, sub }) {
    const colors = {
        blue:    'bg-blue-50 text-blue-600 border-blue-100',
        amber:   'bg-amber-50 text-amber-600 border-amber-100',
        emerald: 'bg-emerald-50 text-emerald-600 border-emerald-100',
        rose:    'bg-rose-50 text-rose-600 border-rose-100',
        slate:   'bg-slate-50 text-slate-500 border-slate-100',
        violet:  'bg-violet-50 text-violet-600 border-violet-100',
    };
    return (
        <div className="bg-white border border-slate-200 rounded-xl p-4 flex items-start gap-3.5 shadow-sm">
            <div className={`w-9 h-9 rounded-lg flex items-center justify-center border shrink-0 ${colors[color] ?? colors.slate}`}>
                <Icon size={16} strokeWidth={2.5} />
            </div>
            <div className="min-w-0">
                <div className="text-2xl font-bold text-slate-900 leading-none">{value}</div>
                <div className="text-xs font-semibold text-slate-500 mt-1">{label}</div>
                {sub && <div className="text-[10px] text-slate-400 mt-0.5">{sub}</div>}
            </div>
        </div>
    );
}

// ─── Context-Aware Action Buttons ─────────────────────────────────────────────

function ScanActions({ scan, onDetail, onEdit, onDelete }) {
    const status = String(scan.status ?? '').toLowerCase();

    return (
        <div className="inline-flex items-center gap-0.5">
            {/* Always available: view */}
            <ActionBtn
                icon={Eye}
                label="View Details"
                onClick={() => onDetail(scan.id)}
                cls="text-slate-400 hover:text-brand-600 hover:bg-brand-50"
            />

            {/* Running → pause (visual only, no backend action yet), stop */}
            {status === 'running' && (
                <>
                    <ActionBtn icon={Pause}  label="Pause Scan"   onClick={() => {}} cls="text-slate-400 hover:text-amber-600 hover:bg-amber-50" />
                    <ActionBtn icon={Square} label="Stop Scan"    onClick={() => onDelete(scan)} cls="text-slate-400 hover:text-rose-600 hover:bg-rose-50" />
                </>
            )}

            {/* Queued → cancel */}
            {status === 'queued' && (
                <ActionBtn icon={X} label="Cancel Scan" onClick={() => onDelete(scan)} cls="text-slate-400 hover:text-rose-600 hover:bg-rose-50" />
            )}

            {/* Completed → edit (run again), view findings placeholder */}
            {status === 'completed' && (
                <>
                    <ActionBtn icon={FileText}   label="View Report"  onClick={() => onDetail(scan.id)} cls="text-slate-400 hover:text-emerald-600 hover:bg-emerald-50" />
                    <ActionBtn icon={RotateCcw}  label="Run Again"    onClick={() => onEdit(scan.id)}   cls="text-slate-400 hover:text-blue-600 hover:bg-blue-50" />
                </>
            )}

            {/* Failed → retry, edit */}
            {(status === 'failed') && (
                <>
                    <ActionBtn icon={RotateCcw} label="Retry Scan"  onClick={() => onEdit(scan.id)} cls="text-slate-400 hover:text-violet-600 hover:bg-violet-50" />
                    <ActionBtn icon={Edit2}     label="Edit Config"  onClick={() => onEdit(scan.id)} cls="text-slate-400 hover:text-slate-700 hover:bg-slate-100" />
                </>
            )}

            {/* Scheduled / default → edit, delete */}
            {(status === 'scheduled' || status === 'cancelled' || !['running','queued','completed','failed'].includes(status)) && (
                <>
                    <ActionBtn icon={Edit2}  label="Edit Config"  onClick={() => onEdit(scan.id)}  cls="text-slate-400 hover:text-slate-700 hover:bg-slate-100" />
                    <ActionBtn icon={Trash2} label="Delete Scan"  onClick={() => onDelete(scan)}   cls="text-slate-400 hover:text-rose-600 hover:bg-rose-50" />
                </>
            )}
        </div>
    );
}

function ActionBtn({ icon: Icon, label, onClick, cls }) {
    return (
        <button
            type="button"
            title={label}
            aria-label={label}
            onClick={onClick}
            className={`p-1.5 rounded-md transition-colors ${cls}`}
        >
            <Icon size={14} strokeWidth={2} />
        </button>
    );
}

// ─── Empty State ──────────────────────────────────────────────────────────────

function EmptyState({ hasFilters, onClear, onCreate }) {
    return (
        <div className="flex flex-col items-center justify-center py-20 px-6 text-center">
            <div className="w-16 h-16 rounded-2xl bg-slate-100 border border-slate-200 flex items-center justify-center mb-5">
                <ScanLine size={28} className="text-slate-400" strokeWidth={1.5} />
            </div>
            <h3 className="text-base font-bold text-slate-800 mb-1">
                {hasFilters ? 'No scans match your filters' : 'No scan runs yet'}
            </h3>
            <p className="text-sm text-slate-500 max-w-xs mb-6">
                {hasFilters
                    ? 'Try adjusting or clearing your search and filter criteria.'
                    : 'Configure and trigger your first vulnerability assessment run.'}
            </p>
            {hasFilters ? (
                <button
                    type="button"
                    onClick={onClear}
                    className="inline-flex items-center gap-1.5 px-4 py-2 text-sm font-semibold text-slate-700 bg-white border border-slate-300 rounded-lg hover:bg-slate-50 transition"
                >
                    <X size={14} /> Clear Filters
                </button>
            ) : (
                <button
                    type="button"
                    onClick={onCreate}
                    className="inline-flex items-center gap-1.5 px-4 py-2 text-sm font-semibold text-white bg-brand-600 hover:bg-brand-700 rounded-lg transition shadow-sm"
                >
                    <Plus size={14} /> Create First Scan
                </button>
            )}
        </div>
    );
}

// ─── Error State ──────────────────────────────────────────────────────────────

function ErrorState({ message, onRetry }) {
    return (
        <div className="flex flex-col items-center justify-center py-16 px-6 text-center">
            <div className="w-14 h-14 rounded-2xl bg-rose-50 border border-rose-200 flex items-center justify-center mb-4">
                <AlertTriangle size={24} className="text-rose-500" strokeWidth={1.5} />
            </div>
            <h3 className="text-sm font-bold text-slate-800 mb-1">Failed to load scans</h3>
            <p className="text-xs text-slate-500 max-w-xs mb-5">{message}</p>
            <button
                type="button"
                onClick={onRetry}
                className="inline-flex items-center gap-1.5 px-4 py-2 text-sm font-semibold text-white bg-rose-600 hover:bg-rose-700 rounded-lg transition"
            >
                <RefreshCw size={13} /> Retry
            </button>
        </div>
    );
}

// ─── Column Header ────────────────────────────────────────────────────────────

function Th({ children, className = '' }) {
    return (
        <th className={`px-4 py-3 text-[10px] font-bold text-slate-400 uppercase tracking-widest whitespace-nowrap select-none ${className}`}>
            {children}
        </th>
    );
}

// ─── Main Component ───────────────────────────────────────────────────────────

const SCAN_TYPES    = ['web_application','network_ip','port_discovery','api_vulnerability','container_audit','cloud_infrastructure','internal_network','external_surface'];
const SCAN_ENGINES  = ['nmap','owasp_zap','nuclei','nikto','trivy','nessus','custom'];
const SCAN_STATUSES = ['queued','running','scheduled','completed','failed','cancelled'];

const TYPE_LABELS   = { web_application:'Web Application', network_ip:'Network IP', port_discovery:'Port Discovery', api_vulnerability:'API Vulnerability', container_audit:'Container Audit', cloud_infrastructure:'Cloud Infrastructure', internal_network:'Internal Network', external_surface:'External Surface' };
const ENGINE_LABELS = { nmap:'Nmap', owasp_zap:'OWASP ZAP', nuclei:'Nuclei', nikto:'Nikto', trivy:'Trivy', nessus:'Nessus', custom:'Custom' };
const STATUS_LABELS = { queued:'Queued', running:'Running', scheduled:'Scheduled', completed:'Completed', failed:'Failed', cancelled:'Cancelled' };

export default function ScansPage({ onNavigateToCreate, onNavigateToEdit, onNavigateToDetail }) {
    const [scans,      setScans]      = useState([]);
    const [stats,      setStats]      = useState(null);
    const [loading,    setLoading]    = useState(true);
    const [error,      setError]      = useState(null);
    const [refreshing, setRefreshing] = useState(false);

    const [search,         setSearch]         = useState('');
    const [selectedType,   setSelectedType]   = useState('');
    const [selectedEngine, setSelectedEngine] = useState('');
    const [selectedStatus, setSelectedStatus] = useState('');
    const [dateFrom,       setDateFrom]       = useState('');
    const [dateTo,         setDateTo]         = useState('');
    const [sortBy,         setSortBy]         = useState('created_at');
    const [sortOrder,      setSortOrder]      = useState('desc');
    const [page,           setPage]           = useState(1);
    const [perPage]                           = useState(15);
    const [totalPages,     setTotalPages]     = useState(1);
    const [totalItems,     setTotalItems]     = useState(0);

    const searchRef = useRef(null);

    const hasFilters = !!(search || selectedType || selectedEngine || selectedStatus || dateFrom || dateTo);

    const clearFilters = () => {
        setSearch(''); setSelectedType(''); setSelectedEngine('');
        setSelectedStatus(''); setDateFrom(''); setDateTo('');
        setPage(1);
        searchRef.current?.focus();
    };

    const fetchScans = useCallback(async (showRefresh = false) => {
        if (showRefresh) setRefreshing(true);
        else setLoading(true);
        setError(null);

        try {
            const params = {
                search:      search || undefined,
                type:        selectedType   || undefined,
                engine:      selectedEngine || undefined,
                status:      selectedStatus || undefined,
                date_from:   dateFrom       || undefined,
                date_to:     dateTo         || undefined,
                sort_by:     sortBy,
                sort_order:  sortOrder,
                page,
                per_page:    perPage,
            };
            const res = await axios.get('/api/scans', { params });
            const data = res.data;
            setScans(data.data ?? []);
            if (data.meta) {
                setTotalPages(data.meta.last_page  ?? 1);
                setTotalItems(data.meta.total      ?? 0);
            }
            // Derive summary stats from the current full collection
            if (!showRefresh && !hasFilters) {
                const all = data.data ?? [];
                const today = new Date().toDateString();
                setStats({
                    total:         data.meta?.total ?? all.length,
                    running:       all.filter(s => s.status === 'running').length,
                    queued:        all.filter(s => s.status === 'queued').length,
                    completedToday:all.filter(s => s.status === 'completed' && s.completed_at && new Date(s.completed_at).toDateString() === today).length,
                    completed:     all.filter(s => s.status === 'completed').length,
                    failed:        all.filter(s => s.status === 'failed').length,
                });
            }
        } catch (err) {
            console.error('Scans fetch error:', err);
            setError(err.response?.data?.message ?? 'Failed to load scans. Please check your connection.');
        } finally {
            setLoading(false);
            setRefreshing(false);
        }
    }, [search, selectedType, selectedEngine, selectedStatus, dateFrom, dateTo, sortBy, sortOrder, page, perPage]);

    useEffect(() => { fetchScans(); }, [fetchScans]);

    // Auto-refresh every 30 s if any scan is running
    useEffect(() => {
        const hasRunning = scans.some(s => s.status === 'running');
        if (!hasRunning) return;
        const id = setInterval(() => fetchScans(true), 30000);
        return () => clearInterval(id);
    }, [scans, fetchScans]);

    const handleDelete = async (scan) => {
        const verb = scan.status === 'running' ? 'stop' : scan.status === 'queued' ? 'cancel' : 'delete';
        if (!window.confirm(`${verb.charAt(0).toUpperCase() + verb.slice(1)} scan "${scan.name}"?`)) return;
        try {
            await axios.delete(`/api/scans/${scan.id}`);
            fetchScans(true);
        } catch (err) {
            alert(err.response?.data?.message ?? `Failed to ${verb} scan.`);
        }
    };

    // Compute success rate
    const successRate = stats && (stats.completed + stats.failed) > 0
        ? Math.round((stats.completed / (stats.completed + stats.failed)) * 100)
        : null;

    const from = (page - 1) * perPage + 1;
    const to   = Math.min(page * perPage, totalItems);

    return (
        <div className="space-y-5" role="main" aria-label="Scan Management">

            {/* ── Page Header ───────────────────────────────────────────── */}
            <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <h1 className="text-2xl font-bold text-slate-900 tracking-tight">Scan Management</h1>
                    <p className="text-sm text-slate-500 mt-0.5">Configure, trigger and monitor vulnerability assessment runs.</p>
                </div>
                <div className="flex items-center gap-2">
                    <button
                        type="button"
                        onClick={() => fetchScans(true)}
                        disabled={refreshing}
                        aria-label="Refresh scans list"
                        className="inline-flex items-center gap-1.5 px-3 py-2 text-sm font-semibold text-slate-600 bg-white border border-slate-300 rounded-lg hover:bg-slate-50 disabled:opacity-50 transition shadow-sm"
                    >
                        <RefreshCw size={13} className={refreshing ? 'animate-spin' : ''} />
                        Refresh
                    </button>
                    <button
                        type="button"
                        onClick={onNavigateToCreate}
                        className="inline-flex items-center gap-1.5 px-4 py-2 text-sm font-semibold text-white bg-brand-600 hover:bg-brand-700 active:bg-brand-800 rounded-lg shadow-sm border border-brand-700 transition focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500"
                    >
                        <Plus size={15} />
                        New Scan
                    </button>
                </div>
            </div>

            {/* ── Summary Cards ─────────────────────────────────────────── */}
            {stats && (
                <div className="grid grid-cols-2 sm:grid-cols-3 xl:grid-cols-5 gap-3">
                    <SummaryCard label="Total Scans"      value={stats.total}           icon={ScanLine}      color="slate"   />
                    <SummaryCard label="Running"           value={stats.running}          icon={Activity}      color="blue"    sub="Active assessments" />
                    <SummaryCard label="Queued"            value={stats.queued}           icon={Clock}         color="amber"   sub="Pending execution" />
                    <SummaryCard label="Completed Today"   value={stats.completedToday}   icon={CheckCircle2}  color="emerald" sub="Since midnight UTC" />
                    <SummaryCard
                        label="Success Rate"
                        value={successRate !== null ? `${successRate}%` : '—'}
                        icon={Activity}
                        color="violet"
                        sub={`${stats.completed} completed · ${stats.failed} failed`}
                    />
                </div>
            )}

            {/* ── Toolbar ───────────────────────────────────────────────── */}
            <div className="bg-white border border-slate-200 rounded-xl p-4 shadow-sm space-y-3">
                <div className="flex items-center gap-2 flex-wrap">
                    <Filter size={13} className="text-slate-400 shrink-0" />
                    <span className="text-xs font-bold text-slate-500 uppercase tracking-wider">Filters</span>
                    {hasFilters && (
                        <button
                            type="button"
                            onClick={clearFilters}
                            className="ml-auto inline-flex items-center gap-1 text-xs font-semibold text-slate-500 hover:text-slate-800 transition"
                        >
                            <X size={11} /> Clear all
                        </button>
                    )}
                </div>

                <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 xl:grid-cols-6 gap-2.5">
                    {/* Search */}
                    <div className="relative sm:col-span-2">
                        <Search className="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none" size={13} />
                        <input
                            ref={searchRef}
                            type="search"
                            placeholder="Search scans or targets…"
                            value={search}
                            onChange={e => { setSearch(e.target.value); setPage(1); }}
                            aria-label="Search scans"
                            className="w-full pl-8 pr-3 py-2 border border-slate-200 rounded-lg text-sm text-slate-800 placeholder-slate-400 bg-slate-50 focus:bg-white focus:outline-none focus:ring-2 focus:ring-brand-500/20 focus:border-brand-400 transition"
                        />
                    </div>

                    {/* Type */}
                    <select
                        value={selectedType}
                        onChange={e => { setSelectedType(e.target.value); setPage(1); }}
                        aria-label="Filter by scan type"
                        className="border border-slate-200 rounded-lg text-sm text-slate-700 py-2 px-3 bg-slate-50 focus:bg-white focus:outline-none focus:ring-2 focus:ring-brand-500/20 focus:border-brand-400 transition"
                    >
                        <option value="">All Types</option>
                        {SCAN_TYPES.map(t => <option key={t} value={t}>{TYPE_LABELS[t]}</option>)}
                    </select>

                    {/* Engine */}
                    <select
                        value={selectedEngine}
                        onChange={e => { setSelectedEngine(e.target.value); setPage(1); }}
                        aria-label="Filter by engine"
                        className="border border-slate-200 rounded-lg text-sm text-slate-700 py-2 px-3 bg-slate-50 focus:bg-white focus:outline-none focus:ring-2 focus:ring-brand-500/20 focus:border-brand-400 transition"
                    >
                        <option value="">All Engines</option>
                        {SCAN_ENGINES.map(e => <option key={e} value={e}>{ENGINE_LABELS[e]}</option>)}
                    </select>

                    {/* Status */}
                    <select
                        value={selectedStatus}
                        onChange={e => { setSelectedStatus(e.target.value); setPage(1); }}
                        aria-label="Filter by status"
                        className="border border-slate-200 rounded-lg text-sm text-slate-700 py-2 px-3 bg-slate-50 focus:bg-white focus:outline-none focus:ring-2 focus:ring-brand-500/20 focus:border-brand-400 transition"
                    >
                        <option value="">All Statuses</option>
                        {SCAN_STATUSES.map(s => <option key={s} value={s}>{STATUS_LABELS[s]}</option>)}
                    </select>

                    {/* Date range */}
                    <div className="flex items-center gap-1.5 xl:col-span-1">
                        <CalendarDays size={13} className="text-slate-400 shrink-0" />
                        <input
                            type="date"
                            value={dateFrom}
                            onChange={e => { setDateFrom(e.target.value); setPage(1); }}
                            aria-label="From date"
                            className="flex-1 border border-slate-200 rounded-lg text-xs text-slate-700 py-2 px-2 bg-slate-50 focus:bg-white focus:outline-none focus:border-brand-400 transition"
                        />
                        <span className="text-xs text-slate-400 shrink-0">to</span>
                        <input
                            type="date"
                            value={dateTo}
                            onChange={e => { setDateTo(e.target.value); setPage(1); }}
                            aria-label="To date"
                            className="flex-1 border border-slate-200 rounded-lg text-xs text-slate-700 py-2 px-2 bg-slate-50 focus:bg-white focus:outline-none focus:border-brand-400 transition"
                        />
                    </div>
                </div>
            </div>

            {/* ── Table Card ────────────────────────────────────────────── */}
            <div className="bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden">

                {/* Result count + sort */}
                {!loading && !error && scans.length > 0 && (
                    <div className="flex items-center justify-between px-4 py-2.5 border-b border-slate-100 bg-slate-50/50">
                        <span className="text-xs font-medium text-slate-500">
                            Showing <span className="font-bold text-slate-700">{from}–{to}</span> of{' '}
                            <span className="font-bold text-slate-700">{totalItems}</span> scans
                            {hasFilters && <span className="ml-1 text-slate-400">(filtered)</span>}
                        </span>
                        <select
                            value={`${sortBy}:${sortOrder}`}
                            onChange={e => {
                                const [col, dir] = e.target.value.split(':');
                                setSortBy(col); setSortOrder(dir); setPage(1);
                            }}
                            aria-label="Sort order"
                            className="text-xs border border-slate-200 rounded-md px-2 py-1 bg-white text-slate-600 focus:outline-none focus:border-brand-400"
                        >
                            <option value="created_at:desc">Newest first</option>
                            <option value="created_at:asc">Oldest first</option>
                            <option value="name:asc">Name A–Z</option>
                            <option value="name:desc">Name Z–A</option>
                            <option value="status:asc">Status</option>
                            <option value="progress:desc">Progress</option>
                        </select>
                    </div>
                )}

                <div className="overflow-x-auto">
                    <table className="w-full text-sm" role="table" aria-label="Scans table">
                        {/* Sticky header */}
                        <thead className="sticky top-0 z-10">
                            <tr className="bg-slate-50 border-b border-slate-200">
                                <Th className="pl-5">Scan</Th>
                                <Th>Target</Th>
                                <Th>Type</Th>
                                <Th>Engine</Th>
                                <Th>Status</Th>
                                <Th className="min-w-[160px]">Progress</Th>
                                <Th>Schedule</Th>
                                <Th>Started</Th>
                                <Th>Duration</Th>
                                <Th className="pr-5 text-right">Actions</Th>
                            </tr>
                        </thead>

                        <tbody className="divide-y divide-slate-100">
                            {/* Loading skeletons */}
                            {loading && Array.from({ length: 6 }).map((_, i) => <ScanRowSkeleton key={i} />)}

                            {/* Error */}
                            {!loading && error && (
                                <tr>
                                    <td colSpan={10}>
                                        <ErrorState message={error} onRetry={() => fetchScans()} />
                                    </td>
                                </tr>
                            )}

                            {/* Empty */}
                            {!loading && !error && scans.length === 0 && (
                                <tr>
                                    <td colSpan={10}>
                                        <EmptyState
                                            hasFilters={hasFilters}
                                            onClear={clearFilters}
                                            onCreate={onNavigateToCreate}
                                        />
                                    </td>
                                </tr>
                            )}

                            {/* Data rows */}
                            {!loading && !error && scans.map(scan => (
                                <tr
                                    key={scan.id}
                                    className="hover:bg-slate-50/70 transition-colors group"
                                    role="row"
                                >
                                    {/* Scan name + description */}
                                    <td className="pl-5 pr-4 py-3.5 max-w-[220px]">
                                        <button
                                            type="button"
                                            onClick={() => onNavigateToDetail(scan.id)}
                                            className="text-left w-full"
                                            aria-label={`View details for ${scan.name}`}
                                        >
                                            <div className="font-semibold text-slate-800 group-hover:text-brand-600 transition-colors truncate">
                                                {scan.name}
                                            </div>
                                            {scan.description && (
                                                <div className="text-[11px] text-slate-400 truncate mt-0.5 max-w-[200px]">
                                                    {scan.description}
                                                </div>
                                            )}
                                        </button>
                                    </td>

                                    {/* Target */}
                                    <td className="px-4 py-3.5 max-w-[180px]">
                                        <span className="font-mono text-[11px] text-slate-600 bg-slate-50 border border-slate-200 px-1.5 py-0.5 rounded truncate block">
                                            {scan.target ?? '—'}
                                        </span>
                                    </td>

                                    {/* Type */}
                                    <td className="px-4 py-3.5 whitespace-nowrap">
                                        <ScanTypeBadge type={scan.type} />
                                    </td>

                                    {/* Engine */}
                                    <td className="px-4 py-3.5 whitespace-nowrap">
                                        <ScanEngineBadge engine={scan.engine} />
                                    </td>

                                    {/* Status */}
                                    <td className="px-4 py-3.5 whitespace-nowrap">
                                        <ScanStatusBadge status={scan.status} />
                                    </td>

                                    {/* Progress */}
                                    <td className="px-4 py-3.5 w-[160px]">
                                        <ProgressBar progress={scan.progress} status={scan.status} compact />
                                    </td>

                                    {/* Schedule */}
                                    <td className="px-4 py-3.5 whitespace-nowrap">
                                        {scan.schedule ? (
                                            <span className="font-mono text-[11px] text-slate-600 bg-slate-50 border border-slate-200 px-1.5 py-0.5 rounded">
                                                {scan.schedule}
                                            </span>
                                        ) : (
                                            <span className="text-xs text-slate-400 italic">Manual</span>
                                        )}
                                    </td>

                                    {/* Started */}
                                    <td className="px-4 py-3.5 whitespace-nowrap text-xs text-slate-500" title={scan.started_at ? fmt(scan.started_at) : ''}>
                                        {scan.started_at ? fmtRelative(scan.started_at) : '—'}
                                    </td>

                                    {/* Duration */}
                                    <td className="px-4 py-3.5 whitespace-nowrap text-xs text-slate-500 font-mono">
                                        {fmtDuration(scan.duration)}
                                    </td>

                                    {/* Actions */}
                                    <td className="pr-5 pl-2 py-3.5 text-right">
                                        <ScanActions
                                            scan={scan}
                                            onDetail={onNavigateToDetail}
                                            onEdit={onNavigateToEdit}
                                            onDelete={handleDelete}
                                        />
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>

                {/* ── Pagination Footer ─────────────────────────────────── */}
                {!loading && !error && totalPages > 1 && (
                    <div className="border-t border-slate-100 bg-slate-50/50 px-5 py-3 flex flex-col sm:flex-row items-center justify-between gap-3">
                        <span className="text-xs text-slate-500 font-medium order-2 sm:order-1">
                            Page <span className="font-bold text-slate-700">{page}</span> of{' '}
                            <span className="font-bold text-slate-700">{totalPages}</span>
                        </span>
                        <div className="flex items-center gap-1 order-1 sm:order-2" role="navigation" aria-label="Pagination">
                            <button
                                type="button"
                                onClick={() => setPage(1)}
                                disabled={page === 1}
                                aria-label="First page"
                                className="px-2 py-1.5 text-xs border border-slate-200 rounded-md bg-white hover:bg-slate-50 disabled:opacity-40 disabled:cursor-not-allowed transition"
                            >«</button>
                            <button
                                type="button"
                                onClick={() => setPage(p => Math.max(1, p - 1))}
                                disabled={page === 1}
                                aria-label="Previous page"
                                className="p-1.5 border border-slate-200 rounded-md bg-white hover:bg-slate-50 disabled:opacity-40 disabled:cursor-not-allowed transition"
                            >
                                <ChevronLeft size={13} />
                            </button>
                            {/* Page number chips */}
                            {Array.from({ length: Math.min(5, totalPages) }, (_, i) => {
                                const p = Math.min(Math.max(page - 2 + i, 1), totalPages - 4 + i);
                                return p;
                            }).filter((v, i, a) => a.indexOf(v) === i && v >= 1 && v <= totalPages).map(p => (
                                <button
                                    key={p}
                                    type="button"
                                    onClick={() => setPage(p)}
                                    aria-label={`Page ${p}`}
                                    aria-current={p === page ? 'page' : undefined}
                                    className={`w-7 h-7 text-xs font-semibold rounded-md border transition ${
                                        p === page
                                            ? 'bg-brand-600 text-white border-brand-600'
                                            : 'bg-white text-slate-600 border-slate-200 hover:bg-slate-50'
                                    }`}
                                >
                                    {p}
                                </button>
                            ))}
                            <button
                                type="button"
                                onClick={() => setPage(p => Math.min(totalPages, p + 1))}
                                disabled={page === totalPages}
                                aria-label="Next page"
                                className="p-1.5 border border-slate-200 rounded-md bg-white hover:bg-slate-50 disabled:opacity-40 disabled:cursor-not-allowed transition"
                            >
                                <ChevronRight size={13} />
                            </button>
                            <button
                                type="button"
                                onClick={() => setPage(totalPages)}
                                disabled={page === totalPages}
                                aria-label="Last page"
                                className="px-2 py-1.5 text-xs border border-slate-200 rounded-md bg-white hover:bg-slate-50 disabled:opacity-40 disabled:cursor-not-allowed transition"
                            >»</button>
                        </div>
                    </div>
                )}

                {/* Refreshing indicator */}
                {refreshing && !loading && (
                    <div className="absolute top-2 right-2 flex items-center gap-1.5 text-xs text-slate-400 bg-white border border-slate-200 rounded-full px-2.5 py-1 shadow-sm">
                        <Loader2 size={10} className="animate-spin" /> Refreshing…
                    </div>
                )}
            </div>
        </div>
    );
}
