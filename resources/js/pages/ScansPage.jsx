import React, { useState, useEffect } from 'react';
import { 
    Search, Plus, Filter, ArrowUpDown, ChevronLeft, ChevronRight, 
    RefreshCw, MoreHorizontal, Play, Eye, Edit2, Trash2, ShieldAlert
} from 'lucide-react';
import axios from 'axios';
import { ScanStatusBadge, ScanTypeBadge, ScanEngineBadge, ProgressBar } from '../components/ui/primitives_scans';
import { Skeleton } from '../components/ui/primitives';

export default function ScansPage({ 
    onNavigateToCreate, 
    onNavigateToEdit, 
    onNavigateToDetail 
}) {
    const [scans, setScans] = useState([]);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState(null);

    // Filters & Pagination state
    const [search, setSearch] = useState('');
    const [selectedType, setSelectedType] = useState('');
    const [selectedEngine, setSelectedEngine] = useState('');
    const [selectedStatus, setSelectedStatus] = useState('');
    const [sortBy, setSortBy] = useState('created_at');
    const [sortOrder, setSortOrder] = useState('desc');

    const [page, setPage] = useState(1);
    const [perPage] = useState(10);
    const [totalPages, setTotalPages] = useState(1);
    const [totalItems, setTotalItems] = useState(0);

    const [actionDropdownId, setActionDropdownId] = useState(null);

    const fetchScans = async () => {
        setLoading(true);
        setError(null);
        try {
            const params = {
                search,
                type: selectedType || undefined,
                engine: selectedEngine || undefined,
                status: selectedStatus || undefined,
                sort_by: sortBy,
                sort_order: sortOrder,
                page,
                per_page: perPage
            };
            const response = await axios.get('/api/scans', { params });
            setScans(response.data.data || []);
            
            // Handle pagination headers/meta
            if (response.data.meta) {
                setTotalPages(response.data.meta.last_page || 1);
                setTotalItems(response.data.meta.total || 0);
            }
        } catch (err) {
            console.error('Error loading scans:', err);
            setError('Failed to load scans catalog. Please check your connection.');
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => {
        fetchScans();
    }, [search, selectedType, selectedEngine, selectedStatus, sortBy, sortOrder, page]);

    const handleDelete = async (scan) => {
        if (!confirm(`Are you sure you want to delete scan "${scan.name}"?`)) return;
        try {
            await axios.delete(`/api/scans/${scan.id}`);
            fetchScans();
        } catch (err) {
            alert(err.response?.data?.message || 'Failed to delete scan.');
        }
    };

    return (
        <div className="space-y-6">
            {/* Header section */}
            <div className="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                <div>
                    <h1 className="text-2xl font-bold text-slate-900 tracking-tight">Scans Management</h1>
                    <p className="text-sm text-slate-500 mt-1">Configure, trigger, and audit vulnerability assessment runs.</p>
                </div>
                <button
                    onClick={onNavigateToCreate}
                    className="inline-flex items-center justify-center gap-2 px-4 py-2 text-sm font-semibold text-white bg-brand-600 hover:bg-brand-700 active:bg-brand-800 rounded-lg shadow-sm border border-brand-700 transition"
                >
                    <Plus size={16} />
                    New Scan Run
                </button>
            </div>

            {/* Filter grid */}
            <div className="bg-white border border-slate-200 rounded-xl p-4 shadow-sm space-y-4">
                <div className="grid grid-cols-1 md:grid-cols-4 gap-3">
                    {/* Search field */}
                    <div className="relative">
                        <Search className="absolute left-3 top-2.5 text-slate-400" size={16} />
                        <input
                            type="text"
                            placeholder="Search scans, targets..."
                            value={search}
                            onChange={(e) => { setSearch(e.target.value); setPage(1); }}
                            className="w-full pl-9 pr-4 py-2 border border-slate-300 rounded-lg text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-brand-500/20 focus:border-brand-500"
                        />
                    </div>

                    {/* Filter Type */}
                    <select
                        value={selectedType}
                        onChange={(e) => { setSelectedType(e.target.value); setPage(1); }}
                        className="w-full border border-slate-300 rounded-lg text-sm text-slate-700 py-2 px-3 focus:outline-none focus:ring-2 focus:ring-brand-500/20 focus:border-brand-500"
                    >
                        <option value="">All Scan Types</option>
                        <option value="web_application">Web Application</option>
                        <option value="network_ip">Network IP</option>
                        <option value="port_discovery">Port Discovery</option>
                        <option value="api_vulnerability">API Vulnerability</option>
                        <option value="container_audit">Container Audit</option>
                        <option value="cloud_infrastructure">Cloud Infrastructure</option>
                    </select>

                    {/* Filter Engine */}
                    <select
                        value={selectedEngine}
                        onChange={(e) => { setSelectedEngine(e.target.value); setPage(1); }}
                        className="w-full border border-slate-300 rounded-lg text-sm text-slate-700 py-2 px-3 focus:outline-none focus:ring-2 focus:ring-brand-500/20 focus:border-brand-500"
                    >
                        <option value="">All Engines</option>
                        <option value="nmap">Nmap</option>
                        <option value="owasp_zap">OWASP ZAP</option>
                        <option value="nuclei">Nuclei</option>
                        <option value="trivy">Trivy</option>
                        <option value="nessus">Nessus</option>
                    </select>

                    {/* Filter Status */}
                    <select
                        value={selectedStatus}
                        onChange={(e) => { setSelectedStatus(e.target.value); setPage(1); }}
                        className="w-full border border-slate-300 rounded-lg text-sm text-slate-700 py-2 px-3 focus:outline-none focus:ring-2 focus:ring-brand-500/20 focus:border-brand-500"
                    >
                        <option value="">All Statuses</option>
                        <option value="queued">Queued</option>
                        <option value="running">Running</option>
                        <option value="completed">Completed</option>
                        <option value="failed">Failed</option>
                        <option value="cancelled">Cancelled</option>
                    </select>
                </div>
            </div>

            {/* Content Table / Cards */}
            {loading ? (
                <div className="bg-white border border-slate-200 rounded-xl p-5 shadow-sm space-y-4">
                    <Skeleton className="h-6 w-1/4" />
                    <Skeleton className="h-10 w-full" />
                    <Skeleton className="h-10 w-full" />
                    <Skeleton className="h-10 w-full" />
                </div>
            ) : error ? (
                <div className="bg-rose-50 border border-rose-200 rounded-xl p-5 text-center">
                    <ShieldAlert className="mx-auto text-rose-500 mb-3 animate-bounce" size={32} />
                    <p className="text-sm font-semibold text-rose-800">{error}</p>
                    <button
                        onClick={fetchScans}
                        className="mt-3 inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold text-rose-700 bg-white border border-rose-300 rounded-lg hover:bg-rose-100 transition"
                    >
                        <RefreshCw size={12} />
                        Retry Load
                    </button>
                </div>
            ) : scans.length === 0 ? (
                <div className="bg-white border border-slate-200 rounded-xl p-12 text-center shadow-sm">
                    <Search className="mx-auto text-slate-300 mb-4" size={40} />
                    <h3 className="text-base font-bold text-slate-800">No scan executions found</h3>
                    <p className="text-sm text-slate-500 max-w-sm mx-auto mt-1">
                        Try clearing active filters or launch a new scan template audit to begin monitoring target metrics.
                    </p>
                </div>
            ) : (
                <div className="bg-white border border-slate-200 rounded-xl overflow-hidden shadow-sm">
                    <div className="overflow-x-auto">
                        <table className="w-full border-collapse text-left">
                            <thead>
                                <tr className="bg-slate-50 border-b border-slate-200 text-xs font-bold text-slate-500 uppercase tracking-wider">
                                    <th className="px-5 py-3">Scan Name</th>
                                    <th className="px-5 py-3">Target</th>
                                    <th className="px-5 py-3">Type</th>
                                    <th className="px-5 py-3">Engine</th>
                                    <th className="px-5 py-3">Status</th>
                                    <th className="px-5 py-3">Progress</th>
                                    <th className="px-5 py-3">Scheduled</th>
                                    <th className="px-5 py-3 text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-200 text-sm text-slate-700">
                                {scans.map((scan) => (
                                    <tr key={scan.id} className="hover:bg-slate-50/50 transition">
                                        <td className="px-5 py-3.5">
                                            <div className="font-semibold text-slate-800 hover:text-brand-600 cursor-pointer" onClick={() => onNavigateToDetail(scan.id)}>
                                                {scan.name}
                                            </div>
                                            {scan.description && (
                                                <div className="text-xs text-slate-400 mt-0.5 truncate max-w-[240px]">
                                                    {scan.description}
                                                </div>
                                            )}
                                        </td>
                                        <td className="px-5 py-3.5 font-mono text-xs text-slate-600">
                                            {scan.target}
                                        </td>
                                        <td className="px-5 py-3.5">
                                            <ScanTypeBadge type={scan.type} />
                                        </td>
                                        <td className="px-5 py-3.5">
                                            <ScanEngineBadge engine={scan.engine} />
                                        </td>
                                        <td className="px-5 py-3.5">
                                            <ScanStatusBadge status={scan.status} />
                                        </td>
                                        <td className="px-5 py-3.5 max-w-[140px]">
                                            <ProgressBar progress={scan.progress} status={scan.status} />
                                        </td>
                                        <td className="px-5 py-3.5 text-xs text-slate-500">
                                            {scan.schedule ? (
                                                <span className="font-mono text-slate-600 bg-slate-100 px-1.5 py-0.5 rounded border border-slate-200">
                                                    {scan.schedule}
                                                </span>
                                            ) : (
                                                <span className="text-slate-400 italic">Manual</span>
                                            )}
                                        </td>
                                        <td className="px-5 py-3.5 text-right relative">
                                            <div className="inline-flex items-center gap-1.5">
                                                <button
                                                    onClick={() => onNavigateToDetail(scan.id)}
                                                    className="p-1.5 text-slate-400 hover:text-slate-700 hover:bg-slate-100 rounded-md transition"
                                                    title="View Details"
                                                >
                                                    <Eye size={15} />
                                                </button>
                                                <button
                                                    onClick={() => onNavigateToEdit(scan.id)}
                                                    className="p-1.5 text-slate-400 hover:text-slate-700 hover:bg-slate-100 rounded-md transition"
                                                    title="Edit Config"
                                                >
                                                    <Edit2 size={15} />
                                                </button>
                                                <button
                                                    onClick={() => handleDelete(scan)}
                                                    className="p-1.5 text-slate-400 hover:text-rose-600 hover:bg-rose-50 rounded-md transition"
                                                    title="Delete Scan"
                                                >
                                                    <Trash2 size={15} />
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>

                    {/* Pagination Footer */}
                    {totalPages > 1 && (
                        <div className="bg-slate-50 border-t border-slate-200 px-5 py-3 flex items-center justify-between text-xs text-slate-500 font-medium">
                            <div>
                                Showing <span className="font-semibold text-slate-700">{((page - 1) * perPage) + 1}</span> to{' '}
                                <span className="font-semibold text-slate-700">
                                    {Math.min(page * perPage, totalItems)}
                                </span>{' '}
                                of <span className="font-semibold text-slate-700">{totalItems}</span> runs
                            </div>
                            <div className="flex items-center gap-1">
                                <button
                                    onClick={() => setPage(p => Math.max(1, p - 1))}
                                    disabled={page === 1}
                                    className="p-1 border border-slate-300 rounded bg-white hover:bg-slate-50 disabled:opacity-40 disabled:hover:bg-white transition"
                                >
                                    <ChevronLeft size={14} />
                                </button>
                                <button
                                    onClick={() => setPage(p => Math.min(totalPages, p + 1))}
                                    disabled={page === totalPages}
                                    className="p-1 border border-slate-300 rounded bg-white hover:bg-slate-50 disabled:opacity-40 disabled:hover:bg-white transition"
                                >
                                    <ChevronRight size={14} />
                                </button>
                            </div>
                        </div>
                    )}
                </div>
            )}
        </div>
    );
}
