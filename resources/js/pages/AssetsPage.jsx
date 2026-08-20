import React, { useState, useEffect } from 'react';
import { 
    Plus, Search, SlidersHorizontal, Trash2, Edit3, Eye, 
    ArrowUpDown, Loader2, RefreshCw, Lock
} from 'lucide-react';
import { Card, CardHeader, ViewAllLink, MonoChip, Skeleton } from '../components/ui/primitives';
import { RiskBadge, AssetTypeBadge, ConfirmationDialog } from '../components/ui/primitives_assets';
import { useAuth } from '../context/AuthContext';

export default function AssetsPage({ onNavigateToCreate, onNavigateToEdit, onNavigateToDetail }) {
    const { checkAuthStatus } = useAuth();
    const [assets, setAssets] = useState([]);
    const [meta, setMeta] = useState(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState(null);

    // Filter states
    const [search, setSearch] = useState('');
    const [type, setType] = useState('');
    const [status, setStatus] = useState('');
    const [criticality, setCriticality] = useState('');
    const [sortBy, setSortBy] = useState('created_at');
    const [sortOrder, setSortOrder] = useState('desc');
    const [page, setPage] = useState(1);

    // Bulk action states
    const [selectedIds, setSelectedIds] = useState([]);

    // Delete dialog states
    const [deleteId, setDeleteId] = useState(null);

    const fetchAssets = async () => {
        setLoading(true);
        setError(null);
        try {
            const queryParams = new URLSearchParams({
                search,
                type,
                status,
                criticality,
                sort_by: sortBy,
                sort_order: sortOrder,
                page: page.toString(),
            });
            const response = await fetch(`/api/assets?${queryParams.toString()}`, {
                headers: {
                    'Accept': 'application/json',
                }
            });
            if (response.status === 401) {
                checkAuthStatus();
                return;
            }
            if (!response.ok) {
                throw new Error('Failed to load asset index');
            }
            const data = await response.json();
            setAssets(data.data);
            setMeta(data.meta || null);
        } catch (err) {
            setError(err.message);
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => {
        const delayDebounceFn = setTimeout(() => {
            fetchAssets();
        }, 300);

        return () => clearTimeout(delayDebounceFn);
    }, [search, type, status, criticality, sortBy, sortOrder, page]);

    const handleSelectAll = (e) => {
        if (e.target.checked) {
            setSelectedIds(assets.map(a => a.id));
        } else {
            setSelectedIds([]);
        }
    };

    const handleSelectRow = (id) => {
        setSelectedIds(prev => 
            prev.includes(id) ? prev.filter(x => x !== id) : [...prev, id]
        );
    };

    const handleDeleteClick = (id, e) => {
        e.stopPropagation();
        setDeleteId(id);
    };

    const handleConfirmDelete = async () => {
        if (!deleteId) return;
        try {
            const response = await fetch(`/api/assets/${deleteId}`, {
                method: 'DELETE',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                    'Accept': 'application/json',
                }
            });
            if (!response.ok) {
                throw new Error('Failed to delete asset');
            }
            setDeleteId(null);
            fetchAssets();
        } catch (err) {
            alert(err.message);
        }
    };

    const handleBulkDelete = async () => {
        if (!confirm(`Are you sure you want to delete ${selectedIds.length} selected assets?`)) return;
        try {
            for (const id of selectedIds) {
                await fetch(`/api/assets/${id}`, {
                    method: 'DELETE',
                    headers: {
                        'Accept': 'application/json',
                    }
                });
            }
            setSelectedIds([]);
            fetchAssets();
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
                    <h2 className="text-xl font-bold text-slate-900">Asset Catalog</h2>
                    <p className="text-xs text-slate-500 mt-1">Monitor, categorize, and track VAPT target scopes.</p>
                </div>
                <div className="flex items-center gap-2">
                    <button 
                        onClick={fetchAssets}
                        className="p-2 rounded-lg border border-slate-200 hover:bg-slate-50 transition-colors"
                        title="Reload assets"
                    >
                        <RefreshCw size={14} className="text-slate-500" />
                    </button>
                    <button
                        disabled
                        title="Asset management is coming soon."
                        className="inline-flex items-center gap-1.5 px-3 py-2 text-xs font-semibold text-slate-400 bg-slate-100 rounded-lg shadow-sm border border-slate-200 cursor-not-allowed"
                    >
                        <Lock size={14} /> Add Asset 🔒
                    </button>
                </div>
            </div>

            {/* Filters panel */}
            <Card padding={true} className="flex flex-col gap-4">
                <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
                    {/* Search */}
                    <div className="relative">
                        <Search className="absolute left-2.5 top-1/2 -translate-y-1/2 text-slate-400" size={14} />
                        <input
                            type="text"
                            placeholder="Search name, value, owner..."
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
                        <option value="">All Asset Types</option>
                        <option value="domain">Domain</option>
                        <option value="subdomain">Subdomain</option>
                        <option value="ipv4">IPv4</option>
                        <option value="ipv6">IPv6</option>
                        <option value="cidr">CIDR</option>
                        <option value="url">URL</option>
                        <option value="api_endpoint">API Endpoint</option>
                        <option value="hostname">Hostname</option>
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
                        <option value="archived">Archived</option>
                    </select>
                </div>

                {/* Bulk actions strip */}
                {selectedIds.length > 0 && (
                    <div className="flex items-center justify-between px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg animate-fade-in">
                        <span className="text-xs font-medium text-slate-700">
                            {selectedIds.length} assets selected
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

            {/* Assets Table */}
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
                        <p className="text-sm font-semibold text-red-500">Error loading catalog</p>
                        <p className="text-xs text-slate-400 mt-1">{error}</p>
                    </div>
                ) : assets.length === 0 ? (
                    <div className="p-16 text-center">
                        <p className="text-sm font-semibold text-slate-800">No assets found</p>
                        <p className="text-xs text-slate-400 mt-1">Try relaxing filters or create a new asset scope.</p>
                    </div>
                ) : (
                    <div className="overflow-x-auto">
                        <table className="w-full text-left" aria-label="Asset catalog table">
                            <thead>
                                <tr className="border-b border-slate-100 bg-slate-50/50">
                                    <th className="px-5 py-3 w-8">
                                        <input
                                            type="checkbox"
                                            checked={selectedIds.length === assets.length}
                                            onChange={handleSelectAll}
                                            className="rounded border-slate-300 text-brand-600 focus:ring-brand-500"
                                        />
                                    </th>
                                    <th className="px-5 py-3 text-[10px] font-semibold text-slate-500 uppercase tracking-widest cursor-pointer select-none" onClick={() => toggleSort('name')}>
                                        <div className="flex items-center gap-1">Name <ArrowUpDown size={10} /></div>
                                    </th>
                                    <th className="px-5 py-3 text-[10px] font-semibold text-slate-500 uppercase tracking-widest cursor-pointer select-none" onClick={() => toggleSort('type')}>
                                        <div className="flex items-center gap-1">Type <ArrowUpDown size={10} /></div>
                                    </th>
                                    <th className="px-5 py-3 text-[10px] font-semibold text-slate-500 uppercase tracking-widest">Value</th>
                                    <th className="px-5 py-3 text-[10px] font-semibold text-slate-500 uppercase tracking-widest cursor-pointer select-none" onClick={() => toggleSort('risk_score')}>
                                        <div className="flex items-center gap-1">Risk Score <ArrowUpDown size={10} /></div>
                                    </th>
                                    <th className="px-5 py-3 text-[10px] font-semibold text-slate-500 uppercase tracking-widest cursor-pointer select-none" onClick={() => toggleSort('status')}>
                                        <div className="flex items-center gap-1">Status <ArrowUpDown size={10} /></div>
                                    </th>
                                    <th className="px-5 py-3 text-[10px] font-semibold text-slate-500 uppercase tracking-widest">Owner</th>
                                    <th className="px-5 py-3 text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-50">
                                {assets.map(asset => (
                                    <tr key={asset.id} className="hover:bg-slate-50/40 transition-colors group">
                                        <td className="px-5 py-3">
                                            <input
                                                type="checkbox"
                                                checked={selectedIds.includes(asset.id)}
                                                onChange={() => handleSelectRow(asset.id)}
                                                className="rounded border-slate-300 text-brand-600 focus:ring-brand-500"
                                            />
                                        </td>
                                        <td className="px-5 py-3 text-sm font-semibold text-slate-900">{asset.name}</td>
                                        <td className="px-5 py-3"><AssetTypeBadge type={asset.type} /></td>
                                        <td className="px-5 py-3"><MonoChip>{asset.value}</MonoChip></td>
                                        <td className="px-5 py-3"><RiskBadge score={asset.risk_score} /></td>
                                        <td className="px-5 py-3">
                                            <span className={`inline-flex items-center gap-1 px-1.5 py-0.5 rounded text-[10px] font-semibold ${asset.status === 'active' ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-600'}`}>
                                                {asset.status.toUpperCase()}
                                            </span>
                                        </td>
                                        <td className="px-5 py-3 text-xs text-slate-500">{asset.owner || '—'}</td>
                                        <td className="px-5 py-3 text-right">
                                            <div className="flex items-center justify-end gap-1.5 opacity-0 group-hover:opacity-100 transition-opacity">
                                                <button
                                                    onClick={() => onNavigateToDetail(asset.id)}
                                                    className="p-1 rounded hover:bg-slate-100 text-slate-500"
                                                    title="View details"
                                                >
                                                    <Eye size={13} />
                                                </button>
                                                <button
                                                    onClick={() => onNavigateToEdit(asset.id)}
                                                    className="p-1 rounded hover:bg-slate-100 text-slate-500"
                                                    title="Edit asset"
                                                >
                                                    <Edit3 size={13} />
                                                </button>
                                                <button
                                                    onClick={(e) => handleDeleteClick(asset.id, e)}
                                                    className="p-1 rounded hover:bg-red-50 text-red-500"
                                                    title="Delete asset"
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

                {/* Pagination */}
                {meta && meta.last_page > 1 && (
                    <div className="flex items-center justify-between px-5 py-3 border-t border-slate-100 bg-slate-50/20">
                        <span className="text-xs text-slate-500">
                            Showing page {meta.current_page} of {meta.last_page}
                        </span>
                        <div className="flex items-center gap-1.5">
                            <button
                                disabled={meta.current_page === 1}
                                onClick={() => setPage(prev => Math.max(1, prev - 1))}
                                className="px-2.5 py-1 text-[11px] font-semibold text-slate-600 border border-slate-200 rounded-md bg-white hover:bg-slate-50 disabled:opacity-50 disabled:pointer-events-none transition-colors"
                            >
                                Previous
                            </button>
                            <button
                                disabled={meta.current_page === meta.last_page}
                                onClick={() => setPage(prev => Math.min(meta.last_page, prev + 1))}
                                className="px-2.5 py-1 text-[11px] font-semibold text-slate-600 border border-slate-200 rounded-md bg-white hover:bg-slate-50 disabled:opacity-50 disabled:pointer-events-none transition-colors"
                            >
                                Next
                            </button>
                        </div>
                    </div>
                )}
            </Card>

            {/* Confirm Delete modal */}
            <ConfirmationDialog
                isOpen={!!deleteId}
                title="Delete Asset"
                message="Are you sure you want to delete this asset? This action will perform a soft-delete and can be restored later."
                confirmLabel="Delete"
                isDanger={true}
                onConfirm={handleConfirmDelete}
                onCancel={() => setDeleteId(null)}
            />
        </div>
    );
}
