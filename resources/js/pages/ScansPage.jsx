import React, { useState, useEffect, useCallback, useRef } from 'react';
import {
    Search, Plus, RefreshCw, ChevronLeft, ChevronRight,
    Eye, Edit2, Trash2, Square, Pause, RotateCcw,
    FileText, X, ScanLine, AlertTriangle, CheckCircle2,
    Activity, Clock, Loader2, Filter, CalendarDays,
    MoreVertical, StopCircle, Terminal, Play, ChevronDown,
    CheckSquare, Square as SquareIcon, GitBranch,
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

// ─── Schedule Humanizer ───────────────────────────────────────────────────────

function humanSchedule(cron) {
    if (!cron) return null;
    const c = String(cron).trim().toLowerCase();
    if (c === '@daily'   || c === '0 0 * * *')    return 'Every Day';
    if (c === '@hourly'  || c === '0 * * * *')    return 'Every Hour';
    if (c === '@weekly'  || c === '0 0 * * 0')    return 'Weekly';
    if (c === '@monthly' || c === '0 0 1 * *')    return 'Monthly';
    if (c === '@yearly'  || c === '0 0 1 1 *')    return 'Yearly';
    if (c === '@reboot')                           return 'On Reboot';
    // Detect daily-at-X
    const dailyAt = c.match(/^(\d+)\s+(\d+)\s+\*\s+\*\s+\*$/);
    if (dailyAt) return `Daily at ${dailyAt[2].padStart(2,'0')}:${dailyAt[1].padStart(2,'0')}`;
    // Detect every-N-hours
    const everyH = c.match(/^0\s+\*\/(\d+)\s+\*\s+\*\s+\*$/);
    if (everyH) return `Every ${everyH[1]}h`;
    // Detect every-N-minutes
    const everyM = c.match(/^\*\/(\d+)\s+\*\s+\*\s+\*\s+\*$/);
    if (everyM) return `Every ${everyM[1]}m`;
    return 'Custom Cron';
}

// ─── Action Dropdown ──────────────────────────────────────────────────────────

function ScanActionMenu({ scan, onDetail, onEdit, onDelete, onReport }) {
    const [open, setOpen] = useState(false);
    const ref             = useRef(null);
    const status          = String(scan.status ?? '').toLowerCase();

    useEffect(() => {
        if (!open) return;
        const handler = (e) => { if (ref.current && !ref.current.contains(e.target)) setOpen(false); };
        document.addEventListener('mousedown', handler);
        return () => document.removeEventListener('mousedown', handler);
    }, [open]);

    const items = [];

    // View Details — always
    items.push({ label: 'View Details', icon: Eye,       cls: 'text-slate-700', action: () => onDetail(scan.id) });

    if (status === 'running') {
        items.push({ label: 'View Live',  icon: Activity,    cls: 'text-blue-600',   action: () => onDetail(scan.id) });
        items.push({ label: 'Pause',      icon: Pause,       cls: 'text-amber-600',  action: () => {} });
        items.push({ label: 'Stop',       icon: StopCircle,  cls: 'text-rose-600',   action: () => onDelete(scan), danger: true });
    }
    if (status === 'queued') {
        items.push({ label: 'Cancel',     icon: X,           cls: 'text-rose-600',   action: () => onDelete(scan), danger: true });
    }
    if (status === 'completed') {
        items.push({ label: 'View Findings', icon: FileText,  cls: 'text-emerald-600', action: () => onDetail(scan.id) });
        items.push({ label: 'View Report',   icon: FileText,  cls: 'text-slate-700',   action: () => onReport(scan.id) });
        items.push({ label: 'Run Again',     icon: RotateCcw, cls: 'text-blue-600',    action: () => onEdit(scan.id) });
    }
    if (status === 'failed') {
        items.push({ label: 'Retry',      icon: RotateCcw,  cls: 'text-violet-600', action: () => onEdit(scan.id) });
        items.push({ label: 'View Logs',  icon: Terminal,   cls: 'text-slate-700',  action: () => onDetail(scan.id) });
    }
    if (!['running','queued','completed','failed'].includes(status)) {
        items.push({ label: 'Edit Config', icon: Edit2,  cls: 'text-slate-700', action: () => onEdit(scan.id) });
        items.push({ label: 'Delete',      icon: Trash2, cls: 'text-rose-600',  action: () => onDelete(scan), danger: true });
    }

    return (
        <div ref={ref} className="relative flex items-center justify-end" onClick={e => e.stopPropagation()}>
            <button
                type="button"
                onClick={() => setOpen(o => !o)}
                aria-label="Actions"
                aria-haspopup="true"
                aria-expanded={open}
                className={`inline-flex items-center gap-1 px-2.5 py-1.5 rounded-md text-xs font-semibold border transition-all ${
                    open
                        ? 'bg-slate-100 border-slate-300 text-slate-800'
                        : 'bg-white border-slate-200 text-slate-500 hover:bg-slate-50 hover:border-slate-300 hover:text-slate-800'
                }`}
            >
                <MoreVertical size={13} strokeWidth={2} />
            </button>

            {open && (
                <div
                    role="menu"
                    className="absolute right-0 top-full mt-1 z-50 w-44 bg-white border border-slate-200 rounded-xl shadow-lg shadow-slate-200/60 py-1 overflow-hidden"
                >
                    {items.map((item, idx) => {
                        const Icon = item.icon;
                        return (
                            <button
                                key={idx}
                                type="button"
                                role="menuitem"
                                onClick={() => { item.action(); setOpen(false); }}
                                className={`w-full flex items-center gap-2.5 px-3 py-2 text-xs font-medium transition-colors ${
                                    item.danger
                                        ? 'text-rose-600 hover:bg-rose-50'
                                        : 'text-slate-700 hover:bg-slate-50'
                                }`}
                            >
                                <Icon size={13} strokeWidth={2} className={item.cls} />
                                {item.label}
                            </button>
                        );
                    })}
                </div>
            )}
        </div>
    );
}

// ─── Empty State ──────────────────────────────────────────────────────────────

function EmptyState({ hasFilters, onClear, onCreate }) {
    return (
        <div className="flex flex-col items-center justify-center py-24 px-6 text-center">
            {/* Illustration */}
            <div className="relative mb-6">
                <div className="w-20 h-20 rounded-2xl bg-gradient-to-br from-slate-100 to-slate-50 border border-slate-200 flex items-center justify-center shadow-inner">
                    <ScanLine size={36} className="text-slate-300" strokeWidth={1.5} />
                </div>
                {!hasFilters && (
                    <div className="absolute -right-1 -bottom-1 w-7 h-7 rounded-lg bg-brand-500/10 border border-brand-200 flex items-center justify-center">
                        <Plus size={13} className="text-brand-500" strokeWidth={2.5} />
                    </div>
                )}
            </div>
            <h3 className="text-base font-bold text-slate-800 mb-1.5">
                {hasFilters ? 'No scans match your filters' : 'No scan runs yet'}
            </h3>
            <p className="text-sm text-slate-500 max-w-sm mb-7 leading-relaxed">
                {hasFilters
                    ? 'Try adjusting or clearing your search and filter criteria to see results.'
                    : 'Configure and trigger your first vulnerability assessment run to start monitoring.'}
            </p>
            {hasFilters ? (
                <button type="button" onClick={onClear}
                    className="inline-flex items-center gap-1.5 px-4 py-2 text-sm font-semibold text-slate-700 bg-white border border-slate-300 rounded-lg hover:bg-slate-50 transition shadow-sm">
                    <X size={14} strokeWidth={2.5} /> Clear Filters
                </button>
            ) : (
                <button type="button" disabled
                    title="Infrastructure & DB scanning is coming soon. Go to Repositories to run a scan."
                    className="inline-flex items-center gap-1.5 px-5 py-2.5 text-sm font-semibold text-slate-400 bg-slate-100 rounded-lg shadow-sm border border-slate-200 cursor-not-allowed">
                    <Lock size={14} strokeWidth={2.5} /> Create First Scan 🔒
                </button>
            )}
        </div>
    );
}

// ─── Error State ──────────────────────────────────────────────────────────────

function ErrorState({ message, onRetry }) {
    return (
        <div className="flex flex-col items-center justify-center py-20 px-6 text-center">
            <div className="w-16 h-16 rounded-2xl bg-rose-50 border border-rose-200 flex items-center justify-center mb-5">
                <AlertTriangle size={28} className="text-rose-400" strokeWidth={1.5} />
            </div>
            <h3 className="text-sm font-bold text-slate-800 mb-1">Failed to load scans</h3>
            <p className="text-xs text-slate-500 max-w-xs mb-6 leading-relaxed">{message}</p>
            <button type="button" onClick={onRetry}
                className="inline-flex items-center gap-1.5 px-5 py-2.5 text-sm font-semibold text-white bg-rose-600 hover:bg-rose-700 active:bg-rose-800 rounded-lg transition shadow-sm">
                <RefreshCw size={13} strokeWidth={2.5} /> Retry
            </button>
        </div>
    );
}

// ─── Column Header ────────────────────────────────────────────────────────────

function Th({ children, className = '', align = 'left' }) {
    return (
        <th className={`px-4 py-3.5 text-[10px] font-bold text-slate-400 uppercase tracking-widest whitespace-nowrap select-none ${
            align === 'right' ? 'text-right' : 'text-left'
        } ${className}`}>
            {children}
        </th>
    );
}

// ─── Progress Cell ────────────────────────────────────────────────────────────

function ProgressCell({ progress = 0, status, duration }) {
    const pct = Math.min(100, Math.max(0, Number(progress) || 0));
    const isRunning   = status === 'running';
    const isCompleted = status === 'completed';

    let barCls = 'bg-slate-300';
    if (isRunning)           barCls = 'bg-blue-500';
    if (isCompleted)         barCls = 'bg-emerald-500';
    if (status === 'failed') barCls = 'bg-rose-500';
    if (status === 'queued') barCls = 'bg-amber-400';

    // Rough estimate: if running and we know % and duration so far, extrapolate remaining
    let eta = null;
    if (isRunning && pct > 2 && duration) {
        const totalEst = (duration / pct) * 100;
        const rem      = Math.max(0, Math.round(totalEst - duration));
        if (rem > 0) eta = rem < 60 ? `~${rem}s left` : `~${Math.round(rem/60)}m left`;
    }

    return (
        <div className="w-full min-w-[140px] space-y-1">
            <div className="flex items-center justify-between gap-2">
                <div className="flex-1 h-2 bg-slate-100 rounded-full overflow-hidden border border-slate-200/80">
                    <div
                        className={`h-full rounded-full transition-all duration-700 ${barCls} ${isRunning ? 'animate-pulse' : ''}`}
                        style={{ width: `${pct}%` }}
                    />
                </div>
                {isCompleted ? (
                    <CheckCircle2 size={13} className="text-emerald-500 shrink-0" strokeWidth={2.5} />
                ) : (
                    <span className="text-[11px] font-bold tabular-nums text-slate-500 shrink-0 min-w-[28px] text-right">{pct}%</span>
                )}
            </div>
            {eta && <div className="text-[10px] text-blue-500 font-medium">{eta}</div>}
        </div>
    );
}

// ─── Schedule Cell ────────────────────────────────────────────────────────────

function ScheduleCell({ schedule }) {
    const label = humanSchedule(schedule);
    if (!label) {
        return <span className="inline-flex items-center gap-1 text-[11px] text-slate-400 font-medium"><Play size={10} strokeWidth={2.5} className="text-slate-300" />Manual</span>;
    }
    return (
        <span className="inline-flex items-center gap-1 text-[11px] font-semibold text-slate-600 bg-slate-50 border border-slate-200 px-2 py-0.5 rounded-md">
            <Clock size={10} strokeWidth={2.5} className="text-slate-400" />
            {label}
        </span>
    );
}

// ─── Main Component ───────────────────────────────────────────────────────────

const SCAN_TYPES    = ['web_application','network_ip','port_discovery','api_vulnerability','container_audit','cloud_infrastructure','internal_network','external_surface', 'repository', 'database'];
const SCAN_ENGINES  = ['nmap','owasp_zap','nuclei','nikto','trivy','nessus','custom', 'database_scanner'];
const SCAN_STATUSES = ['queued','running','scheduled','completed','failed','cancelled'];

const TYPE_LABELS   = { web_application:'Web Application', network_ip:'Network IP / Infrastructure', port_discovery:'Port Discovery', api_vulnerability:'API Vulnerability', container_audit:'Container Audit', cloud_infrastructure:'Cloud Infrastructure', internal_network:'Internal Network', external_surface:'External Surface', repository: 'Repository Scan', database: 'Database Security Scan' };
const ENGINE_LABELS = { nmap:'Nmap', owasp_zap:'OWASP ZAP', nuclei:'Nuclei', nikto:'Nikto', trivy:'Trivy', nessus:'Nessus', custom:'Custom', database_scanner: 'Database Scanner' };
const STATUS_LABELS = { queued:'Queued', running:'Running', scheduled:'Scheduled', completed:'Completed', failed:'Failed', cancelled:'Cancelled' };

export default function ScansPage({ onNavigateToCreate, onNavigateToEdit, onNavigateToDetail, onNavigateToReport }) {
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
    const [perPage,        setPerPage]       = useState(15);
    const [selectedRows,   setSelectedRows]   = useState(new Set());
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
                        onClick={() => window.location.href = '/scans/new'}
                        className="inline-flex items-center gap-1.5 px-4 py-2 text-sm font-semibold text-white bg-brand-600 rounded-lg hover:bg-brand-700 transition shadow-sm"
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
            <div className="bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden">

                {/* ── Filter header strip ─────────────────────────────── */}
                <div className="flex items-center gap-2 px-4 py-2 border-b border-slate-100 bg-slate-50/50">
                    <Filter size={12} className="text-slate-400 shrink-0" strokeWidth={2.5} />
                    <span className="text-[11px] font-bold text-slate-500 uppercase tracking-widest select-none">Filters</span>
                    {hasFilters && (
                        <span className="ml-0.5 inline-flex items-center justify-center min-w-[16px] h-4 px-1 rounded-full bg-brand-500 text-white text-[9px] font-bold leading-none shrink-0">
                            {[search, selectedType, selectedEngine, selectedStatus, dateFrom, dateTo].filter(Boolean).length}
                        </span>
                    )}
                    <div className="ml-auto flex items-center">
                        {hasFilters && (
                            <button
                                type="button"
                                onClick={clearFilters}
                                aria-label="Clear all filters"
                                className="inline-flex items-center gap-1 h-7 px-2.5 text-[11px] font-semibold text-slate-500 hover:text-rose-600 hover:bg-rose-50 rounded-md transition-colors"
                            >
                                <X size={11} strokeWidth={2.5} /> Clear
                            </button>
                        )}
                        <span className="mx-1.5 w-px h-4 bg-slate-200" />
                        <button
                            type="button"
                            onClick={() => fetchScans(true)}
                            disabled={refreshing}
                            aria-label="Refresh scans"
                            className="inline-flex items-center gap-1 h-7 px-2.5 text-[11px] font-semibold text-slate-500 hover:text-slate-800 hover:bg-slate-100 disabled:opacity-40 rounded-md transition-colors"
                        >
                            <RefreshCw size={11} strokeWidth={2.5} className={refreshing ? 'animate-spin' : ''} /> Refresh
                        </button>
                    </div>
                </div>

                {/* ── Controls row ────────────────────────────────────── */}
                <div className="px-3 py-3">
                    <div className="flex flex-col md:flex-row md:flex-wrap xl:flex-nowrap items-stretch gap-2">

                        {/* Search — flex-grows to fill remaining space */}
                        <div className="relative flex-1 min-w-[200px]">
                            <Search className="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none" size={14} strokeWidth={2} />
                            <input
                                ref={searchRef}
                                type="search"
                                placeholder="Search scans, targets, descriptions…"
                                value={search}
                                onChange={e => { setSearch(e.target.value); setPage(1); }}
                                aria-label="Search scans"
                                className="w-full h-[44px] pl-9 pr-3 border border-slate-200 rounded-lg text-sm text-slate-800 placeholder-slate-400 bg-white hover:border-slate-300 focus:outline-none focus:border-brand-400 focus:ring-2 focus:ring-brand-500/10 transition-all"
                            />
                        </div>

                        {/* Type — equal width */}
                        <div className="relative w-full md:w-[156px] shrink-0">
                            <select
                                value={selectedType}
                                onChange={e => { setSelectedType(e.target.value); setPage(1); }}
                                aria-label="Filter by scan type"
                                className="w-full h-[44px] appearance-none border border-slate-200 rounded-lg text-sm text-slate-700 pl-3 pr-8 bg-white hover:border-slate-300 focus:outline-none focus:border-brand-400 focus:ring-2 focus:ring-brand-500/10 transition-all cursor-pointer"
                            >
                                <option value="">All Types</option>
                                {SCAN_TYPES.map(t => <option key={t} value={t}>{TYPE_LABELS[t]}</option>)}
                            </select>
                            <span className="pointer-events-none absolute right-2.5 top-1/2 -translate-y-1/2 text-slate-400">
                                <svg width="10" height="10" viewBox="0 0 10 10" fill="none"><path d="M2 3.5L5 6.5L8 3.5" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round" strokeLinejoin="round"/></svg>
                            </span>
                        </div>

                        {/* Engine — equal width */}
                        <div className="relative w-full md:w-[156px] shrink-0">
                            <select
                                value={selectedEngine}
                                onChange={e => { setSelectedEngine(e.target.value); setPage(1); }}
                                aria-label="Filter by engine"
                                className="w-full h-[44px] appearance-none border border-slate-200 rounded-lg text-sm text-slate-700 pl-3 pr-8 bg-white hover:border-slate-300 focus:outline-none focus:border-brand-400 focus:ring-2 focus:ring-brand-500/10 transition-all cursor-pointer"
                            >
                                <option value="">All Engines</option>
                                {SCAN_ENGINES.map(e => <option key={e} value={e}>{ENGINE_LABELS[e]}</option>)}
                            </select>
                            <span className="pointer-events-none absolute right-2.5 top-1/2 -translate-y-1/2 text-slate-400">
                                <svg width="10" height="10" viewBox="0 0 10 10" fill="none"><path d="M2 3.5L5 6.5L8 3.5" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round" strokeLinejoin="round"/></svg>
                            </span>
                        </div>

                        {/* Status — equal width */}
                        <div className="relative w-full md:w-[156px] shrink-0">
                            <select
                                value={selectedStatus}
                                onChange={e => { setSelectedStatus(e.target.value); setPage(1); }}
                                aria-label="Filter by status"
                                className="w-full h-[44px] appearance-none border border-slate-200 rounded-lg text-sm text-slate-700 pl-3 pr-8 bg-white hover:border-slate-300 focus:outline-none focus:border-brand-400 focus:ring-2 focus:ring-brand-500/10 transition-all cursor-pointer"
                            >
                                <option value="">All Statuses</option>
                                {SCAN_STATUSES.map(s => <option key={s} value={s}>{STATUS_LABELS[s]}</option>)}
                            </select>
                            <span className="pointer-events-none absolute right-2.5 top-1/2 -translate-y-1/2 text-slate-400">
                                <svg width="10" height="10" viewBox="0 0 10 10" fill="none"><path d="M2 3.5L5 6.5L8 3.5" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round" strokeLinejoin="round"/></svg>
                            </span>
                        </div>

                        {/* Date Range — single grouped inline control */}
                        <div className="flex items-center h-[44px] w-full md:w-auto xl:w-[260px] shrink-0 border border-slate-200 rounded-lg bg-white hover:border-slate-300 focus-within:border-brand-400 focus-within:ring-2 focus-within:ring-brand-500/10 transition-all overflow-hidden">
                            <CalendarDays size={13} className="text-slate-400 shrink-0 ml-3 mr-1" strokeWidth={2} />
                            <input
                                type="date"
                                value={dateFrom}
                                onChange={e => { setDateFrom(e.target.value); setPage(1); }}
                                aria-label="From date"
                                className="h-full flex-1 min-w-0 border-none bg-transparent text-[12px] text-slate-600 px-1 focus:outline-none"
                            />
                            <span className="shrink-0 text-[11px] font-bold text-slate-300 select-none px-0.5">→</span>
                            <input
                                type="date"
                                value={dateTo}
                                onChange={e => { setDateTo(e.target.value); setPage(1); }}
                                aria-label="To date"
                                className="h-full flex-1 min-w-0 border-none bg-transparent text-[12px] text-slate-600 px-1 pr-2 focus:outline-none"
                            />
                        </div>

                    </div>
                </div>
            </div>

            {/* ── Table Card ────────────────────────────────────────────── */}
            <div className="bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden">

                {/* ── Table meta bar ─────────────────────────────────── */}
                {!loading && !error && (
                    <div className="flex items-center justify-between px-4 py-2.5 border-b border-slate-100 bg-slate-50/40">
                        <div className="flex items-center gap-3">
                            {selectedRows.size > 0 && (
                                <span className="text-xs font-semibold text-brand-600">
                                    {selectedRows.size} selected
                                </span>
                            )}
                            {scans.length > 0 && (
                                <span className="text-xs text-slate-500">
                                    Showing <span className="font-semibold text-slate-700">{from}–{to}</span> of{' '}
                                    <span className="font-semibold text-slate-700">{totalItems}</span> scans
                                    {hasFilters && <span className="ml-1 text-slate-400">(filtered)</span>}
                                </span>
                            )}
                        </div>
                        <div className="flex items-center gap-2">
                            {refreshing && (
                                <span className="flex items-center gap-1 text-[11px] text-slate-400">
                                    <Loader2 size={10} className="animate-spin" /> Refreshing
                                </span>
                            )}
                            <select
                                value={`${sortBy}:${sortOrder}`}
                                onChange={e => {
                                    const [col, dir] = e.target.value.split(':');
                                    setSortBy(col); setSortOrder(dir); setPage(1);
                                }}
                                aria-label="Sort order"
                                className="text-xs border border-slate-200 rounded-md px-2 py-1 bg-white text-slate-600 focus:outline-none focus:border-brand-400 appearance-none pr-6"
                            >
                                <option value="created_at:desc">Newest first</option>
                                <option value="created_at:asc">Oldest first</option>
                                <option value="name:asc">Name A–Z</option>
                                <option value="name:desc">Name Z–A</option>
                                <option value="status:asc">Status</option>
                                <option value="progress:desc">Progress</option>
                            </select>
                        </div>
                    </div>
                )}

                {/* ── Table ──────────────────────────────────────────── */}
                <div className="overflow-x-auto">
                    <table className="w-full text-sm border-collapse" role="table" aria-label="Scans table">

                        {/* Sticky header */}
                        <thead className="sticky top-0 z-20">
                            <tr className="bg-slate-50 border-b border-slate-200">
                                {/* Checkbox */}
                                <th className="w-10 pl-4 pr-2 py-3.5">
                                    <input
                                        type="checkbox"
                                        aria-label="Select all scans"
                                        checked={scans.length > 0 && selectedRows.size === scans.length}
                                        onChange={e => {
                                            if (e.target.checked) setSelectedRows(new Set(scans.map(s => s.id)));
                                            else setSelectedRows(new Set());
                                        }}
                                        className="w-3.5 h-3.5 rounded border-slate-300 accent-brand-600 cursor-pointer"
                                    />
                                </th>
                                <Th className="pl-2 min-w-[220px]">Scan</Th>
                                <Th className="min-w-[160px]">Target</Th>
                                <Th>Type</Th>
                                <Th>Engine</Th>
                                <Th>Status</Th>
                                <Th className="min-w-[160px]">Progress</Th>
                                <Th>Schedule</Th>
                                <Th>Started</Th>
                                <Th>Duration</Th>
                                <Th align="right" className="pr-4 min-w-[60px]">Actions</Th>
                            </tr>
                        </thead>

                        <tbody className="divide-y divide-slate-100">

                            {/* ── Loading skeletons */}
                            {loading && Array.from({ length: 8 }).map((_, i) => (
                                <tr key={i} className="border-b border-slate-100">
                                    {Array.from({ length: 11 }).map((__, j) => (
                                        <td key={j} className="px-4 py-4">
                                            <div
                                                className="h-3.5 bg-slate-100 rounded animate-pulse"
                                                style={{ width: j === 0 ? '24px' : j === 1 ? '72%' : j === 2 ? '55%' : j === 6 ? '90%' : '45%' }}
                                            />
                                        </td>
                                    ))}
                                </tr>
                            ))}

                            {/* ── Error */}
                            {!loading && error && (
                                <tr>
                                    <td colSpan={11}>
                                        <ErrorState message={error} onRetry={() => fetchScans()} />
                                    </td>
                                </tr>
                            )}

                            {/* ── Empty */}
                            {!loading && !error && scans.length === 0 && (
                                <tr>
                                    <td colSpan={11}>
                                        <EmptyState hasFilters={hasFilters} onClear={clearFilters} onCreate={onNavigateToCreate} />
                                    </td>
                                </tr>
                            )}

                            {/* ── Data rows */}
                            {!loading && !error && scans.map(scan => {
                                const isSelected = selectedRows.has(scan.id);
                                return (
                                    <tr
                                        key={scan.id}
                                        role="row"
                                        aria-selected={isSelected}
                                        onClick={() => {
                                            if (scan.status === 'completed' && onNavigateToReport) {
                                                onNavigateToReport(scan.id);
                                            } else {
                                                onNavigateToDetail(scan.id);
                                            }
                                        }}
                                        className={`group cursor-pointer transition-colors ${
                                            isSelected
                                                ? 'bg-brand-50/60'
                                                : 'hover:bg-slate-50/80'
                                        }`}
                                    >
                                        {/* Checkbox */}
                                        <td className="w-10 pl-4 pr-2 py-4" onClick={e => e.stopPropagation()}>
                                            <input
                                                type="checkbox"
                                                aria-label={`Select ${scan.name}`}
                                                checked={isSelected}
                                                onChange={e => {
                                                    const next = new Set(selectedRows);
                                                    if (e.target.checked) next.add(scan.id);
                                                    else next.delete(scan.id);
                                                    setSelectedRows(next);
                                                }}
                                                className="w-3.5 h-3.5 rounded border-slate-300 accent-brand-600 cursor-pointer"
                                            />
                                        </td>

                                        {/* Scan */}
                                        <td className="pl-2 pr-4 py-3.5 max-w-[240px]">
                                            <div className="font-semibold text-slate-800 group-hover:text-brand-600 transition-colors truncate text-[13px] leading-tight">
                                                {scan.name}
                                            </div>
                                            {scan.description && (
                                                <div className="text-[11px] text-slate-400 truncate mt-0.5 max-w-[220px]">
                                                    {scan.description}
                                                </div>
                                            )}
                                            <div className="flex items-center gap-2 mt-1.5">
                                                {scan.created_by?.name && (
                                                    <span className="inline-flex items-center gap-1 text-[10px] text-slate-400">
                                                        <span className="w-3.5 h-3.5 rounded-full bg-slate-200 flex items-center justify-center text-[8px] font-bold text-slate-500">
                                                            {scan.created_by.name.charAt(0).toUpperCase()}
                                                        </span>
                                                        {scan.created_by.name}
                                                    </span>
                                                )}
                                                {scan.started_at && (
                                                    <span className="text-[10px] text-slate-400" title={fmt(scan.started_at)}>
                                                        {fmtRelative(scan.started_at)}
                                                    </span>
                                                )}
                                            </div>
                                        </td>

                                        {/* Target */}
                                        <td className="px-4 py-3.5 max-w-[180px]">
                                            {scan.target ? (
                                                <span className="font-mono text-[11px] text-slate-600 bg-slate-50 border border-slate-200 px-2 py-1 rounded-md truncate block max-w-[160px] hover:text-brand-600 hover:border-brand-200 hover:bg-brand-50 transition-colors">
                                                    {scan.target}
                                                </span>
                                            ) : (
                                                <span className="text-[11px] text-slate-400 italic">—</span>
                                            )}
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
                                            <div className="flex flex-col items-start gap-1">
                                                <ScanStatusBadge status={scan.status} />
                                                <span className="text-[10px] text-slate-500 font-medium">
                                                    {scan.findings_count === 0 ? '0 findings' : `${scan.findings_count} findings`}
                                                </span>
                                            </div>
                                        </td>

                                        {/* Progress */}
                                        <td className="px-4 py-3.5 w-[170px]">
                                            <ProgressCell progress={scan.progress} status={scan.status} duration={scan.duration} />
                                        </td>

                                        {/* Schedule */}
                                        <td className="px-4 py-3.5 whitespace-nowrap">
                                            <ScheduleCell schedule={scan.schedule} />
                                        </td>

                                        {/* Started */}
                                        <td className="px-4 py-3.5 whitespace-nowrap">
                                            {scan.started_at ? (
                                                <span className="text-[12px] text-slate-500" title={fmt(scan.started_at)}>
                                                    {fmtRelative(scan.started_at)}
                                                </span>
                                            ) : (
                                                <span className="text-[12px] text-slate-300">—</span>
                                            )}
                                        </td>

                                        {/* Duration */}
                                        <td className="px-4 py-3.5 whitespace-nowrap">
                                            <span className="text-[12px] font-mono text-slate-500">{fmtDuration(scan.duration)}</span>
                                        </td>

                                        {/* Actions */}
                                        <td className="pr-4 pl-2 py-3.5" onClick={e => e.stopPropagation()}>
                                            <ScanActionMenu
                                                scan={scan}
                                                onDetail={onNavigateToDetail}
                                                onEdit={onNavigateToEdit}
                                                onDelete={handleDelete}
                                                onReport={onNavigateToReport}
                                            />
                                        </td>
                                    </tr>
                                );
                            })}
                        </tbody>
                    </table>
                </div>

                {/* ── Pagination Footer ────────────────────────────── */}
                {!loading && !error && totalItems > 0 && (
                    <div className="border-t border-slate-100 bg-slate-50/40 px-5 py-3 flex flex-col sm:flex-row items-center justify-between gap-3">

                        {/* Left: rows-per-page + count */}
                        <div className="flex items-center gap-3 text-xs text-slate-500 order-2 sm:order-1">
                            <span className="hidden sm:inline">
                                <span className="font-semibold text-slate-700">{totalItems}</span> total scans
                            </span>
                            <div className="flex items-center gap-1.5">
                                <span className="text-slate-400">Rows per page</span>
                                <select
                                    value={perPage}
                                    onChange={e => { setPerPage(Number(e.target.value)); setPage(1); }}
                                    aria-label="Rows per page"
                                    className="border border-slate-200 rounded-md px-1.5 py-0.5 text-xs bg-white text-slate-600 focus:outline-none focus:border-brand-400"
                                >
                                    {[10,15,25,50].map(n => <option key={n} value={n}>{n}</option>)}
                                </select>
                            </div>
                            <span>
                                Page <span className="font-semibold text-slate-700">{page}</span> of{' '}
                                <span className="font-semibold text-slate-700">{totalPages}</span>
                            </span>
                        </div>

                        {/* Right: navigation */}
                        <div className="flex items-center gap-1 order-1 sm:order-2" role="navigation" aria-label="Pagination">
                            <button type="button" onClick={() => setPage(1)} disabled={page === 1}
                                aria-label="First page"
                                className="h-7 px-2 text-xs border border-slate-200 rounded-md bg-white hover:bg-slate-50 disabled:opacity-40 disabled:cursor-not-allowed transition font-medium text-slate-500"
                            >«</button>
                            <button type="button" onClick={() => setPage(p => Math.max(1, p - 1))} disabled={page === 1}
                                aria-label="Previous page"
                                className="h-7 w-7 flex items-center justify-center border border-slate-200 rounded-md bg-white hover:bg-slate-50 disabled:opacity-40 disabled:cursor-not-allowed transition text-slate-500"
                            ><ChevronLeft size={13} /></button>

                            {/* Page chips */}
                            {Array.from({ length: Math.min(5, totalPages) }, (_, i) => {
                                const rawP = page - 2 + i;
                                return Math.min(Math.max(rawP, 1), totalPages);
                            }).filter((v, i, a) => a.indexOf(v) === i && v >= 1 && v <= totalPages).map(p => (
                                <button
                                    key={p}
                                    type="button"
                                    onClick={() => setPage(p)}
                                    aria-label={`Page ${p}`}
                                    aria-current={p === page ? 'page' : undefined}
                                    className={`h-7 w-7 text-xs font-semibold rounded-md border transition ${
                                        p === page
                                            ? 'bg-brand-600 text-white border-brand-600 shadow-sm'
                                            : 'bg-white text-slate-600 border-slate-200 hover:bg-slate-50'
                                    }`}
                                >{p}</button>
                            ))}

                            <button type="button" onClick={() => setPage(p => Math.min(totalPages, p + 1))} disabled={page === totalPages}
                                aria-label="Next page"
                                className="h-7 w-7 flex items-center justify-center border border-slate-200 rounded-md bg-white hover:bg-slate-50 disabled:opacity-40 disabled:cursor-not-allowed transition text-slate-500"
                            ><ChevronRight size={13} /></button>
                            <button type="button" onClick={() => setPage(totalPages)} disabled={page === totalPages}
                                aria-label="Last page"
                                className="h-7 px-2 text-xs border border-slate-200 rounded-md bg-white hover:bg-slate-50 disabled:opacity-40 disabled:cursor-not-allowed transition font-medium text-slate-500"
                            >»</button>
                        </div>
                    </div>
                )}
            </div>
        </div>
    );
}

