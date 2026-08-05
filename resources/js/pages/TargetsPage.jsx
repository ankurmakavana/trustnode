import React, { useState, useEffect } from 'react';
import { 
    Plus, Search, SlidersHorizontal, Trash2, Edit3, Eye, 
    ArrowUpDown, Loader2, RefreshCw 
} from 'lucide-react';
import { Card, CardHeader, ViewAllLink, MonoChip, Skeleton } from '../components/ui/primitives';
import { TargetTypeBadge, TargetEnvironmentBadge, TargetCriticalityBadge, TargetStatusBadge } from '../components/ui/primitives_targets';
import { ConfirmationDialog } from '../components/ui/primitives_assets';
import { useAuth } from '../context/AuthContext';

export default function TargetsPage({ onNavigateToCreate, onNavigateToEdit, onNavigateToDetail }) {
    const { checkAuthStatus } = useAuth();
    const [targets, setTargets] = useState([]);
    const [meta, setMeta] = useState(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState(null);

    // Filter states
    const [search, setSearch] = useState('');
    const [type, setType] = useState('');
    const [environment, setEnvironment] = useState('');
    const [status, setStatus] = useState('');
    const [criticality, setCriticality] = useState('');
    const [sortBy, setSortBy] = useState('created_at');
    const [sortOrder, setSortOrder] = useState('desc');
    const [page, setPage] = useState(1);

    // Bulk action states
    const [selectedIds, setSelectedIds] = useState([]);

    // Delete dialog states
    const [deleteId, setDeleteId] = useState(null);

    const fetchTargets = async () => {
        setLoading(true);
        setError(null);
        try {
            const queryParams = new URLSearchParams({
                search,
                type,
                environment,
                status,
                criticality,
                sort_by: sortBy,
                sort_order: sortOrder,
                page: page.toString(),
            });
            const response = await fetch(`/api/targets?${queryParams.toString()}`, {
                headers: {
                    'Accept': 'application/json',
                }
            });
            if (response.status === 401) {
                checkAuthStatus();
                return;
            }
            if (!response.ok) {
                throw new Error('Failed to load targets index');
            }
            const data = await response.json();
            setTargets(data.data);
            setMeta(data.meta || null);
        } catch (err) {
            setError(err.message);
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => {
        fetchTargets();
    }, [search, type, environment, status, criticality, sortBy, sortOrder, page]);

    const handleDelete = async () => {
        if (!deleteId) return;
        try {
            const response = await fetch(`/api/targets/${deleteId}`, {
                method: 'DELETE',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                    'Accept': 'application/json',
                }
            });
            if (response.status === 401) {
                checkAuthStatus();
                return;
            }
            if (!response.ok) {
                throw new Error('Failed to delete target');
            }
            setDeleteId(null);
            fetchTargets();
        } catch (err) {
            alert(err.message);
        }
    };

    const handleBulkDelete = async () => {
        if (!confirm(`Are you sure you want to delete ${selectedIds.length} selected targets?`)) return;
        try {
            for (const id of selectedIds) {
                await fetch(`/api/targets/${id}`, {
                    method: 'DELETE',
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                    }
                });
            }
            setSelectedIds([]);
            fetchTargets();
        } catch (err) {
            alert(err.message);
        }
    };

    const toggleSort = (field) => {
        if (sortBy === field) {
            setSortOrder(prev => prev === 'asc' ? 'desc' : 'asc');
        } else {
            setSortBy(field);
            setSortOrder('desc');
        }
    };

    return (
        <div className="flex flex-col gap-6">
            {/* Header section */}
            <div className="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <h2 className="text-xl font-bold text-slate-900">Targets Scope</h2>
                    <p className="text-xs text-slate-500 mt-1">Configure, classify, and register targets for penetration testing audits.</p>
                </div>
                <div className="flex items-center gap-2">
                    <button 
                        onClick={fetchTargets}
                        className="p-2 rounded-lg border border-slate-200 hover:bg-slate-50 transition-colors"
                        title="Reload targets"
                    >
                        <RefreshCw size={14} className="text-slate-500" />
                    </button>
                    <button
                        onClick={onNavigateToCreate}
                        className="inline-flex items-center gap-1.5 px-3 py-2 text-xs font-semibold text-white bg-brand-600 rounded-lg hover:bg-brand-700 transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500"
                    >
                        <Plus size={14} /> Add Target
                    </button>
                </div>
            </div>

            {/* Filters panel */}
            <Card padding={true} className="flex flex-col gap-4">
                <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3">
                    {/* Search */}
                    <div className="relative">
                        <Search className="absolute left-2.5 top-1/2 -translate-y-1/2 text-slate-400" size={14} />
                        <input
                            type="text"
                            placeholder="Search name, value..."
                            value={search}
                            onChange={e => setSearch(e.target.value)}
                            className="w-full bg-slate-50 text-xs text-slate-700 placeholder-slate-400 border border-slate-200 rounded-lg pl-8 pr-3 py-2 outline-none focus:border-brand-500 focus:bg-white transition-all"
                        />
                    </div>

                    {/* Type Filter */}
                    <select
                        value={type}
                        onChange={e => setType(e.target.value)}
                        className="w-full bg-slate-50 text-xs text-slate-700 border border-slate-200 rounded-lg px-3 py-2 outline-none focus:border-brand-500 focus:bg-white transition-all"
                    >
                        <option value="">All Types</option>
                        <option value="domain">Domain</option>
                        <option value="ip_address">IP Address</option>
                        <option value="cidr_range">CIDR Range</option>
                        <option value="url">URL</option>
                        <option value="api_endpoint">API Endpoint</option>
                        <option value="mobile_application">Mobile Application</option>
                        <option value="cloud_resource">Cloud Resource</option>
                    </select>

                    {/* Environment Filter */}
                    <select
                        value={environment}
                        onChange={e => setEnvironment(e.target.value)}
                        className="w-full bg-slate-50 text-xs text-slate-700 border border-slate-200 rounded-lg px-3 py-2 outline-none focus:border-brand-500 focus:bg-white transition-all"
                    >
                        <option value="">All Environments</option>
                        <option value="production">Production</option>
                        <option value="staging">Staging</option>
                        <option value="development">Development</option>
                        <option value="internal">Internal</option>
                    </select>

                    {/* Criticality Filter */}
                    <select
                        value={criticality}
                        onChange={e => setCriticality(e.target.value)}
                        className="w-full bg-slate-50 text-xs text-slate-700 border border-slate-200 rounded-lg px-3 py-2 outline-none focus:border-brand-500 focus:bg-white transition-all"
                    >
                        <option value="">All Criticality</option>
                        <option value="critical">Critical</option>
                        <option value="high">High</option>
                        <option value="medium">Medium</option>
                        <option value="low">Low</option>
                    </select>

                    {/* Status Filter */}
                    <select
                        value={status}
                        onChange={e => setStatus(e.target.value)}
                        className="w-full bg-slate-50 text-xs text-slate-700 border border-slate-200 rounded-lg px-3 py-2 outline-none focus:border-brand-500 focus:bg-white transition-all"
                    >
                        <option value="">All Statuses</option>
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                        <option value="under_review">Under Review</option>
                    </select>
                </div>

                {/* Bulk actions strip */}
                {selectedIds.length > 0 && (
                    <div className="flex items-center justify-between px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg animate-fade-in">
                        <span className="text-xs font-medium text-slate-700">
                            {selectedIds.length} targets selected
                        </span>
                        <button
                            onClick={handleBulkDelete}
                            className="inline-flex items-center gap-1 px-2.5 py-1 text-xs font-semibold text-white bg-red-600 rounded-lg hover:bg-red-700 transition-colors"
                        >
                            <Trash2 size={12} /> Bulk Delete
                        </button>
                    </div>
                )}
            </Card>

            {/* Targets Table */}
            <Card padding={false}>
                {loading ? (
                    <div className="p-8 space-y-4">
                        <Skeleton className="h-6 w-full" />
                        <Skeleton className="h-6 w-full" />
                        <Skeleton className="h-6 w-full" />
                        <Skeleton className="h-6 w-full" />
                    </div>
                ) : error ? (
                    <div className="p-12 text-center">
                        <p className="text-sm font-semibold text-red-600">Error loading targets</p>
                        <p className="text-xs text-slate-400 mt-1">{error}</p>
                    </div>
                ) : targets.length === 0 ? (
                    <div className="p-16 text-center">
                        <div className="inline-flex items-center justify-center w-12 h-12 rounded-xl bg-slate-100 mb-4">
                            <Search className="text-slate-400" size={20} />
                        </div>
                        <p className="text-sm font-bold text-slate-900">No targets found</p>
                        <p className="text-xs text-slate-400 max-w-sm mx-auto mt-1.5">No targets match the filter criteria or none have been registered yet.</p>
                    </div>
                ) : (
                    <div className="overflow-x-auto">
                        <table className="w-full text-left border-collapse">
                            <thead>
                                <tr className="border-b border-slate-100 bg-slate-50 text-[10px] font-bold text-slate-400 uppercase tracking-wider select-none">
                                    <th className="px-5 py-3 w-10">
                                        <input
                                            type="checkbox"
                                            checked={selectedIds.length === targets.length}
                                            onChange={(e) => {
                                                if (e.target.checked) {
                                                    setSelectedIds(targets.map(t => t.id));
                                                } else {
                                                    setSelectedIds([]);
                                                }
                                            }}
                                            className="rounded border-slate-300 text-brand-600 focus:ring-brand-500"
                                        />
                                    </th>
                                    <th className="px-5 py-3 cursor-pointer hover:bg-slate-100 transition-colors" onClick={() => toggleSort('name')}>
                                        <div className="flex items-center gap-1.5">
                                            Name <ArrowUpDown size={11} />
                                        </div>
                                    </th>
                                    <th className="px-5 py-3 cursor-pointer hover:bg-slate-100 transition-colors" onClick={() => toggleSort('type')}>
                                        <div className="flex items-center gap-1.5">
                                            Type <ArrowUpDown size={11} />
                                        </div>
                                    </th>
                                    <th className="px-5 py-3">Value</th>
                                    <th className="px-5 py-3 cursor-pointer hover:bg-slate-100 transition-colors" onClick={() => toggleSort('environment')}>
                                        <div className="flex items-center gap-1.5">
                                            Environment <ArrowUpDown size={11} />
                                        </div>
                                    </th>
                                    <th className="px-5 py-3 cursor-pointer hover:bg-slate-100 transition-colors" onClick={() => toggleSort('criticality')}>
                                        <div className="flex items-center gap-1.5">
                                            Criticality <ArrowUpDown size={11} />
                                        </div>
                                    </th>
                                    <th className="px-5 py-3 cursor-pointer hover:bg-slate-100 transition-colors" onClick={() => toggleSort('status')}>
                                        <div className="flex items-center gap-1.5">
                                            Status <ArrowUpDown size={11} />
                                        </div>
                                    </th>
                                    <th className="px-5 py-3 text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100">
                                {targets.map((t) => (
                                    <tr key={t.id} className="hover:bg-slate-50/50 group transition-colors">
                                        <td className="px-5 py-3.5">
                                            <input
                                                type="checkbox"
                                                checked={selectedIds.includes(t.id)}
                                                onChange={(e) => {
                                                    if (e.target.checked) {
                                                        setSelectedIds(prev => [...prev, t.id]);
                                                    } else {
                                                        setSelectedIds(prev => prev.filter(id => id !== t.id));
                                                    }
                                                }}
                                                className="rounded border-slate-300 text-brand-600 focus:ring-brand-500"
                                            />
                                        </td>
                                        <td className="px-5 py-3.5">
                                            <button 
                                                onClick={() => onNavigateToDetail(t.id)}
                                                className="text-xs font-semibold text-slate-900 hover:text-brand-600 transition-colors text-left"
                                            >
                                                {t.name}
                                            </button>
                                        </td>
                                        <td className="px-5 py-3.5">
                                            <TargetTypeBadge type={t.type} />
                                        </td>
                                        <td className="px-5 py-3.5">
                                            <MonoChip className="max-w-[200px] truncate">{t.value}</MonoChip>
                                        </td>
                                        <td className="px-5 py-3.5">
                                            <TargetEnvironmentBadge env={t.environment} />
                                        </td>
                                        <td className="px-5 py-3.5">
                                            <TargetCriticalityBadge criticality={t.criticality} />
                                        </td>
                                        <td className="px-5 py-3.5">
                                            <TargetStatusBadge status={t.status} />
                                        </td>
                                        <td className="px-5 py-3.5 text-right">
                                            <div className="flex items-center justify-end gap-1">
                                                <button
                                                    onClick={() => onNavigateToDetail(t.id)}
                                                    className="p-1 rounded-md text-slate-400 hover:bg-slate-100 hover:text-slate-600 transition-colors"
                                                    title="View target details"
                                                >
                                                    <Eye size={13} />
                                                </button>
                                                <button
                                                    onClick={() => onNavigateToEdit(t.id)}
                                                    className="p-1 rounded-md text-slate-400 hover:bg-slate-100 hover:text-slate-600 transition-colors"
                                                    title="Edit target scope"
                                                >
                                                    <Edit3 size={13} />
                                                </button>
                                                <button
                                                    onClick={() => setDeleteId(t.id)}
                                                    className="p-1 rounded-md text-slate-400 hover:bg-red-50 hover:text-red-600 transition-colors"
                                                    title="Remove target"
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
                )}

                {/* Footer pagination */}
                {meta && meta.last_page > 1 && (
                    <div className="px-5 py-4 border-t border-slate-100 flex items-center justify-between">
                        <span className="text-xs text-slate-500 font-medium select-none">
                            Showing {meta.from} to {meta.to} of {meta.total} targets
                        </span>
                        <div className="flex items-center gap-1.5">
                            <button
                                disabled={page === 1}
                                onClick={() => setPage(p => p - 1)}
                                className="px-3 py-1.5 text-xs font-semibold border border-slate-200 rounded-lg hover:bg-slate-50 disabled:opacity-50 disabled:hover:bg-transparent transition-colors"
                            >
                                Previous
                            </button>
                            <button
                                disabled={page === meta.last_page}
                                onClick={() => setPage(p => p + 1)}
                                className="px-3 py-1.5 text-xs font-semibold border border-slate-200 rounded-lg hover:bg-slate-50 disabled:opacity-50 disabled:hover:bg-transparent transition-colors"
                            >
                                Next
                            </button>
                        </div>
                    </div>
                )}
            </Card>

            {/* Confirmation modal */}
            <ConfirmationDialog
                isOpen={!!deleteId}
                title="Remove Target"
                message="Are you sure you want to delete this target? All associated data and logs will be archived."
                confirmLabel="Delete Target"
                cancelLabel="Cancel"
                isDanger={true}
                onConfirm={handleDelete}
                onCancel={() => setDeleteId(null)}
            />
        </div>
    );
}
