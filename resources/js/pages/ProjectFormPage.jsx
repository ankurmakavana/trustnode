import React, { useState, useEffect } from 'react';
import { ChevronLeft, FolderGit, CheckCircle2, ShieldCheck, Save, Loader2 } from 'lucide-react';
import { Card, CardHeader } from '../components/ui/primitives';
import { useAuth } from '../context/AuthContext';

export default function ProjectFormPage({ onSave, onCancel }) {
    const { checkAuthStatus } = useAuth();
    const [loading, setLoading] = useState(false);
    const [fetchingDirs, setFetchingDirs] = useState(true);
    const [directories, setDirectories] = useState([]);
    
    const [formData, setFormData] = useState({
        name: '',
        path: '',
        scan_profile: 'standard',
        continuous_scan_enabled: true
    });
    
    const [errors, setErrors] = useState({});

    useEffect(() => {
        const fetchDirs = async () => {
            try {
                const response = await fetch('/api/projects/workspace-directories', {
                    headers: { 'Accept': 'application/json' }
                });
                if (response.status === 401) {
                    checkAuthStatus();
                    return;
                }
                const data = await response.json();
                setDirectories(data.data || []);
            } catch (err) {
                console.error("Failed to fetch workspace directories", err);
            } finally {
                setFetchingDirs(false);
            }
        };
        fetchDirs();
    }, [checkAuthStatus]);

    const handleChange = (e) => {
        const { name, value, type, checked } = e.target;
        setFormData(prev => ({
            ...prev,
            [name]: type === 'checkbox' ? checked : value
        }));
        if (errors[name]) {
            setErrors(prev => ({ ...prev, [name]: null }));
        }
    };

    const handleSubmit = async (e) => {
        e.preventDefault();
        setLoading(true);
        setErrors({});

        try {
            const response = await fetch('/api/projects', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
                },
                body: JSON.stringify(formData)
            });

            if (response.status === 401) {
                checkAuthStatus();
                return;
            }

            if (!response.ok) {
                const data = await response.json();
                if (response.status === 422) {
                    setErrors(data.errors || {});
                } else {
                    throw new Error(data.message || 'Failed to authorize project');
                }
                return;
            }
            onSave();
        } catch (err) {
            setErrors({ _global: err.message });
        } finally {
            setLoading(false);
        }
    };

    return (
        <div className="max-w-2xl mx-auto flex flex-col gap-6 animate-fade-in">
            <div className="flex items-center gap-3">
                <button
                    onClick={onCancel}
                    className="p-1.5 rounded-lg border border-slate-200 text-slate-500 hover:bg-slate-50 hover:text-slate-900 transition-colors"
                >
                    <ChevronLeft size={18} />
                </button>
                <div>
                    <h2 className="text-xl font-bold text-slate-900 flex items-center gap-2">
                        Add Codebase Project
                    </h2>
                    <p className="text-xs text-slate-500 mt-1">Authorize a workspace directory for TrustNode read-only scanning.</p>
                </div>
            </div>

            {errors._global && (
                <div className="p-4 bg-red-50 border border-red-200 rounded-xl flex items-start gap-3 text-red-800 text-sm">
                    <ShieldCheck className="shrink-0 mt-0.5 text-red-600" size={16} />
                    <span>{errors._global}</span>
                </div>
            )}

            <form onSubmit={handleSubmit}>
                <Card padding={false} className="overflow-hidden mb-6">
                    <div className="p-6 space-y-6">
                        {/* Name */}
                        <div>
                            <label className="block text-sm font-semibold text-slate-900 mb-1.5">Project Name</label>
                            <input
                                type="text"
                                name="name"
                                required
                                placeholder="e.g. My SaaS Backend"
                                value={formData.name}
                                onChange={handleChange}
                                className={`w-full bg-slate-50 text-sm text-slate-900 border rounded-lg px-3 py-2.5 outline-none focus:bg-white transition-all ${
                                    errors.name ? 'border-red-300 focus:border-red-500' : 'border-slate-200 focus:border-brand-500'
                                }`}
                            />
                            {errors.name && <p className="text-xs text-red-600 mt-1.5 font-medium">{errors.name[0]}</p>}
                        </div>

                        {/* Path / Directory */}
                        <div>
                            <label className="block text-sm font-semibold text-slate-900 mb-1.5">Workspace Directory</label>
                            <p className="text-xs text-slate-500 mb-2">Select a directory mapped into the TrustNode workspace volume.</p>
                            
                            {fetchingDirs ? (
                                <div className="flex items-center gap-2 text-sm text-slate-500 py-2">
                                    <Loader2 className="animate-spin" size={16} /> Discovering directories...
                                </div>
                            ) : directories.length === 0 ? (
                                <div className="p-3 bg-amber-50 border border-amber-200 rounded-lg text-amber-800 text-xs">
                                    No readable directories found in the workspace mount. Make sure your volumes are configured correctly.
                                </div>
                            ) : (
                                <select
                                    name="path"
                                    required
                                    value={formData.path}
                                    onChange={handleChange}
                                    className={`w-full bg-slate-50 text-sm text-slate-900 border rounded-lg px-3 py-2.5 outline-none focus:bg-white transition-all ${
                                        errors.path ? 'border-red-300 focus:border-red-500' : 'border-slate-200 focus:border-brand-500'
                                    }`}
                                >
                                    <option value="" disabled>Select a directory...</option>
                                    {directories.map(dir => (
                                        <option key={dir} value={dir}>{dir}</option>
                                    ))}
                                </select>
                            )}
                            {errors.path && <p className="text-xs text-red-600 mt-1.5 font-medium">{errors.path[0]}</p>}
                        </div>

                        {/* Profile */}
                        <div>
                            <label className="block text-sm font-semibold text-slate-900 mb-1.5">Scan Profile</label>
                            <select
                                name="scan_profile"
                                required
                                value={formData.scan_profile}
                                onChange={handleChange}
                                className="w-full bg-slate-50 text-sm text-slate-900 border border-slate-200 rounded-lg px-3 py-2.5 outline-none focus:bg-white focus:border-brand-500 transition-all"
                            >
                                <option value="standard">Standard Security Scan</option>
                                <option value="deep">Deep Secrets & Vulnerability Scan</option>
                                <option value="fast">Fast Baseline Scan</option>
                            </select>
                        </div>

                        {/* Continuous */}
                        <div className="flex items-start gap-3 p-4 border border-slate-200 rounded-xl bg-slate-50">
                            <input
                                type="checkbox"
                                id="continuous_scan_enabled"
                                name="continuous_scan_enabled"
                                checked={formData.continuous_scan_enabled}
                                onChange={handleChange}
                                className="mt-1 w-4 h-4 rounded border-slate-300 text-brand-600 focus:ring-brand-500"
                            />
                            <div>
                                <label htmlFor="continuous_scan_enabled" className="text-sm font-bold text-slate-900 cursor-pointer block">
                                    Enable Continuous Scanning
                                </label>
                                <p className="text-xs text-slate-500 mt-0.5">
                                    TrustNode Agent will automatically scan this project when changes are detected or on a schedule.
                                </p>
                            </div>
                        </div>
                    </div>
                </Card>

                <div className="flex items-center justify-end gap-3">
                    <button
                        type="button"
                        onClick={onCancel}
                        disabled={loading}
                        className="px-4 py-2 text-sm font-semibold text-slate-600 bg-white border border-slate-200 rounded-lg hover:bg-slate-50 transition-colors disabled:opacity-50"
                    >
                        Cancel
                    </button>
                    <button
                        type="submit"
                        disabled={loading || fetchingDirs || formData.path === ''}
                        className="inline-flex items-center gap-2 px-5 py-2 text-sm font-semibold text-white bg-brand-600 rounded-lg hover:bg-brand-700 transition-colors disabled:opacity-50 shadow-sm"
                    >
                        {loading ? <Loader2 size={16} className="animate-spin" /> : <Save size={16} />}
                        Authorize Project
                    </button>
                </div>
            </form>
        </div>
    );
}
