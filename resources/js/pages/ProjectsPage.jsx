import React, { useState, useEffect } from 'react';
import { FolderGit, Plus, RefreshCw, Trash2, Search, Play, Activity } from 'lucide-react';
import { Card, CardHeader, MonoChip, Skeleton } from '../components/ui/primitives';
import { ConfirmationDialog } from '../components/ui/primitives_assets';
import { useAuth } from '../context/AuthContext';
import { formatRelativeTime } from '../utils/dateUtils';

export default function ProjectsPage({ onNavigateToCreate, onNavigateToDetail }) {
    const { checkAuthStatus } = useAuth();
    const [projects, setProjects] = useState([]);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState(null);
    const [deleteId, setDeleteId] = useState(null);
    const [scanId, setScanId] = useState(null);

    const fetchProjects = async () => {
        setLoading(true);
        setError(null);
        try {
            const response = await fetch('/api/projects', {
                headers: { 'Accept': 'application/json' }
            });
            if (response.status === 401) {
                checkAuthStatus();
                return;
            }
            if (!response.ok) throw new Error('Failed to load projects');
            const data = await response.json();
            setProjects(data.data || []);
        } catch (err) {
            setError(err.message);
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => {
        fetchProjects();
    }, []);

    const handleDelete = async () => {
        if (!deleteId) return;
        try {
            const response = await fetch(`/api/projects/${deleteId}`, {
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
            if (!response.ok) throw new Error('Failed to delete project');
            setDeleteId(null);
            fetchProjects();
        } catch (err) {
            alert(err.message);
        }
    };

    const handleScan = async (projectId) => {
        try {
            const response = await fetch(`/api/projects/${projectId}/scan`, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                    'Accept': 'application/json',
                }
            });
            if (response.status === 401) {
                checkAuthStatus();
                return;
            }
            if (!response.ok) throw new Error('Failed to dispatch scan');
            alert('Scan request dispatched successfully.');
            fetchProjects();
        } catch (err) {
            alert(err.message);
        }
    };

    return (
        <div className="flex flex-col gap-6">
            <div className="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <h2 className="text-xl font-bold text-slate-900">Codebase Projects</h2>
                    <p className="text-xs text-slate-500 mt-1">Manage workspace directories registered for TrustNode Agent scanning.</p>
                </div>
                <div className="flex items-center gap-2">
                    <button 
                        onClick={fetchProjects}
                        className="p-2 rounded-lg border border-slate-200 hover:bg-slate-50 transition-colors"
                        title="Reload projects"
                    >
                        <RefreshCw size={14} className="text-slate-500" />
                    </button>
                    <button
                        onClick={onNavigateToCreate}
                        className="inline-flex items-center gap-1.5 px-3 py-2 text-xs font-semibold text-white bg-brand-600 rounded-lg hover:bg-brand-700 transition-colors shadow-sm"
                    >
                        <Plus size={14} /> Add Codebase Project
                    </button>
                </div>
            </div>

            <Card padding={false}>
                {loading ? (
                    <div className="p-8 space-y-4">
                        <Skeleton className="h-6 w-full" />
                        <Skeleton className="h-6 w-full" />
                    </div>
                ) : error ? (
                    <div className="p-12 text-center">
                        <p className="text-sm font-semibold text-red-600">Error loading projects</p>
                        <p className="text-xs text-slate-400 mt-1">{error}</p>
                    </div>
                ) : projects.length === 0 ? (
                    <div className="p-16 text-center">
                        <div className="inline-flex items-center justify-center w-12 h-12 rounded-xl bg-slate-100 mb-4">
                            <FolderGit className="text-slate-400" size={20} />
                        </div>
                        <p className="text-sm font-bold text-slate-900">No Codebase Projects</p>
                        <p className="text-xs text-slate-500 max-w-sm mx-auto mt-1.5">
                            TrustNode scans the selected project in read-only mode inside your workspace mount.
                        </p>
                        <button
                            onClick={onNavigateToCreate}
                            className="mt-6 inline-flex items-center gap-1.5 px-4 py-2 text-sm font-semibold text-white bg-brand-600 rounded-lg hover:bg-brand-700 transition-colors shadow-sm"
                        >
                            <Plus size={16} /> Add your first codebase
                        </button>
                    </div>
                ) : (
                    <div className="overflow-x-auto">
                        <table className="w-full text-left border-collapse">
                            <thead>
                                <tr className="border-b border-slate-100 bg-slate-50 text-[10px] font-bold text-slate-400 uppercase tracking-wider select-none">
                                    <th className="px-5 py-3">Project Name</th>
                                    <th className="px-5 py-3">Workspace Path</th>
                                    <th className="px-5 py-3">Status</th>
                                    <th className="px-5 py-3">Continuous Scan</th>
                                    <th className="px-5 py-3">Registered</th>
                                    <th className="px-5 py-3 text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100">
                                {projects.map((p) => (
                                    <tr key={p.id} className="hover:bg-slate-50/50 group transition-colors">
                                        <td className="px-5 py-3.5">
                                            <span className="text-sm font-semibold text-slate-900">{p.name}</span>
                                        </td>
                                        <td className="px-5 py-3.5">
                                            <MonoChip>{p.path}</MonoChip>
                                        </td>
                                        <td className="px-5 py-3.5">
                                            <span className={`inline-flex items-center gap-1 px-2 py-0.5 rounded text-[10px] font-bold uppercase ${
                                                p.status === 'active' ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-600'
                                            }`}>
                                                {p.status}
                                            </span>
                                        </td>
                                        <td className="px-5 py-3.5 text-xs text-slate-600">
                                            {p.continuous_scan_enabled ? 'Enabled' : 'Disabled'}
                                        </td>
                                        <td className="px-5 py-3.5 text-xs text-slate-400 font-mono">
                                            {formatRelativeTime(p.created_at)}
                                        </td>
                                        <td className="px-5 py-3.5 text-right">
                                            <div className="flex items-center justify-end gap-2">
                                                <button
                                                    onClick={() => handleScan(p.id)}
                                                    className="inline-flex items-center gap-1 px-2 py-1 bg-white border border-slate-200 rounded text-xs font-semibold text-slate-700 hover:bg-slate-50 transition-colors"
                                                >
                                                    <Play size={12} className="text-brand-600" /> Scan Now
                                                </button>
                                                <button
                                                    onClick={() => setDeleteId(p.id)}
                                                    className="p-1.5 rounded text-slate-400 hover:bg-red-50 hover:text-red-600 transition-colors"
                                                    title="Revoke project"
                                                >
                                                    <Trash2 size={14} />
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
            </Card>

            <ConfirmationDialog
                isOpen={!!deleteId}
                title="Revoke Codebase Project"
                message="Are you sure you want to revoke scanning access for this project? You can re-authorize it later."
                confirmLabel="Revoke Access"
                cancelLabel="Cancel"
                isDanger={true}
                onConfirm={handleDelete}
                onCancel={() => setDeleteId(null)}
            />
        </div>
    );
}
