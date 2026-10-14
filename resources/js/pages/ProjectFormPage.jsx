import React, { useState, useEffect } from 'react';
import {
    ChevronLeft, CheckCircle2, ShieldCheck, Save, Loader2,
    Folder, Radio, RefreshCw, Copy, Check, ExternalLink, AlertTriangle
} from 'lucide-react';
import { Card } from '../components/ui/primitives';
import { useAuth } from '../context/AuthContext';

export default function ProjectFormPage({ onSave, onCancel }) {
    const { checkAuthStatus } = useAuth();

    // Form state: ONLY Project Name, Absolute Path, Scan Profile, Continuous Monitoring
    const [formData, setFormData] = useState({
        name: '',
        path: '',
        scan_profile: 'standard',
        continuous_scan_enabled: true
    });

    const [availableAgents, setAvailableAgents] = useState([]);
    const [fetchingAgents, setFetchingAgents] = useState(true);
    const [submitting, setSubmitting] = useState(false);
    const [successData, setSuccessData] = useState(null);
    const [errors, setErrors] = useState({});
    const [copiedCmd, setCopiedCmd] = useState(false);

    // Load active agents from server
    const loadAgents = async () => {
        setFetchingAgents(true);
        try {
            const res = await fetch('/api/agent/list', {
                headers: { 'Accept': 'application/json' }
            });
            if (res.status === 401) {
                checkAuthStatus();
                return;
            }
            const data = await res.json();
            setAvailableAgents(data.agents || []);
        } catch (err) {
            console.error("Failed to fetch agents", err);
        } finally {
            setFetchingAgents(false);
        }
    };

    useEffect(() => {
        loadAgents();
        const interval = setInterval(loadAgents, 5000);
        return () => clearInterval(interval);
    }, [checkAuthStatus]);

    // Active host desktop agent status
    const onlineAgents = availableAgents.filter(a => a.status === 'online');
    const onlineDesktopAgents = onlineAgents.filter(a => a.desktop_picker_supported || a.type === 'desktop' || a.agent_id !== 'trustnode-local-agent-1');
    const hasOnlineDesktopAgent = onlineDesktopAgents.length > 0;
    const activeDesktopAgent = onlineDesktopAgents[0] || null;

    // Handle form submission: Authorize Project
    const handleSubmit = async (e) => {
        e.preventDefault();
        setSubmitting(true);
        setErrors({});
        setSuccessData(null);

        try {
            const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
            const payload = {
                name: formData.name.trim(),
                path: formData.path.trim(),
                source_type: 'agent',
                agent_id: activeDesktopAgent?.agent_id || undefined,
                scan_profile: formData.scan_profile,
                continuous_scan_enabled: formData.continuous_scan_enabled
            };

            const response = await fetch('/api/projects', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': token
                },
                body: JSON.stringify(payload)
            });

            if (response.status === 401) {
                checkAuthStatus();
                return;
            }

            const data = await response.json();
            if (!response.ok) {
                if (response.status === 422) {
                    setErrors(data.errors || { _global: data.message });
                } else {
                    throw new Error(data.message || 'Failed to authorize project');
                }
                return;
            }

            const projectRecord = data.data || data;
            setSuccessData(projectRecord);
        } catch (err) {
            setErrors({ _global: err.message || 'An error occurred while authorizing project.' });
        } finally {
            setSubmitting(false);
        }
    };

    const copyAgentCommand = (customCmd) => {
        const cmd = customCmd || 'php cli/bin/trustnode agent:service install';
        navigator.clipboard?.writeText(cmd);
        setCopiedCmd(true);
        setTimeout(() => setCopiedCmd(false), 2500);
    };

    return (
        <div className="max-w-2xl mx-auto flex flex-col gap-6 animate-fade-in pb-12">
            {/* Header */}
            <div className="flex items-center gap-3">
                <button
                    onClick={onCancel}
                    className="p-1.5 rounded-lg border border-slate-200 text-slate-500 hover:bg-slate-50 hover:text-slate-900 transition-colors"
                    title="Back to Projects"
                >
                    <ChevronLeft size={18} />
                </button>
                <div>
                    <h2 className="text-xl font-bold text-slate-900 flex items-center gap-2">
                        Add Codebase Project
                    </h2>
                    <p className="text-xs text-slate-500 mt-0.5">
                        Register a local folder path on your computer for continuous security monitoring by the TrustNode Local Agent.
                    </p>
                </div>
            </div>

            {/* Global Errors */}
            {errors._global && (
                <div className="p-4 bg-red-50 border border-red-200 rounded-xl flex items-start gap-3 text-red-800 text-sm">
                    <ShieldCheck className="shrink-0 mt-0.5 text-red-600" size={16} />
                    <span>{errors._global}</span>
                </div>
            )}

            {/* Success State */}
            {successData ? (
                <Card padding={false} className="overflow-hidden p-6 space-y-5">
                    <div className="p-4 bg-emerald-50 border border-emerald-200 rounded-xl flex items-start gap-3 text-emerald-900 text-sm">
                        <CheckCircle2 className="shrink-0 mt-0.5 text-emerald-600" size={20} />
                        <div>
                            <p className="font-bold text-emerald-950">Project Authorized & Linked to Local Agent</p>
                            <p className="text-xs text-emerald-800 mt-1">
                                <strong>{successData.name}</strong> (#{successData.id}) is registered at <code className="bg-emerald-100/70 px-1 py-0.5 rounded font-mono text-[11px]">{successData.path}</code>.
                                The initial baseline scan has been dispatched, and your persistent host Agent will continuously monitor this directory for changes.
                            </p>
                        </div>
                    </div>

                    <div className="bg-slate-900 text-slate-100 p-4 rounded-xl font-mono text-xs space-y-2">
                        <div className="flex justify-between items-center text-slate-400">
                            <span>Background Persistent Agent Service:</span>
                            <button
                                type="button"
                                onClick={() => copyAgentCommand('php cli/bin/trustnode agent:service status')}
                                className="inline-flex items-center gap-1 text-emerald-400 hover:text-emerald-300"
                            >
                                {copiedCmd ? <Check size={13} /> : <Copy size={13} />}
                                {copiedCmd ? 'Copied' : 'Copy Status Command'}
                            </button>
                        </div>
                        <p className="text-emerald-400 select-all">
                            php cli/bin/trustnode agent:service status
                        </p>
                        <p className="text-[11px] text-slate-400 font-sans mt-1">
                            A single persistent background agent monitors all authorized projects on your computer.
                        </p>
                    </div>

                    <div className="flex items-center justify-between pt-2">
                        <button
                            type="button"
                            onClick={() => {
                                setSuccessData(null);
                                setFormData({ name: '', path: '', scan_profile: 'standard', continuous_scan_enabled: true });
                            }}
                            className="px-4 py-2 text-xs font-semibold text-slate-600 bg-white border border-slate-200 rounded-lg hover:bg-slate-50 transition"
                        >
                            Add Another Project Folder
                        </button>
                        <button
                            type="button"
                            onClick={onSave}
                            className="px-5 py-2 text-xs font-semibold text-white bg-brand-600 rounded-lg hover:bg-brand-700 transition shadow-sm flex items-center gap-1.5"
                        >
                            <span>Go to Projects Dashboard</span>
                            <ExternalLink size={13} />
                        </button>
                    </div>
                </Card>
            ) : (
                <form onSubmit={handleSubmit} className="space-y-6">
                    <Card padding={false} className="overflow-hidden">
                        <div className="p-6 space-y-6">

                            {/* 1. Host Agent Status Banner */}
                            <div className={`p-4 border rounded-xl flex items-center justify-between ${
                                hasOnlineDesktopAgent
                                    ? 'bg-emerald-50/60 border-emerald-200'
                                    : 'bg-amber-50/80 border-amber-200'
                            }`}>
                                <div className="flex items-center gap-3">
                                    <div className="relative">
                                        <Radio size={20} className={hasOnlineDesktopAgent ? "text-emerald-600 animate-pulse" : "text-amber-500"} />
                                    </div>
                                    <div>
                                        <div className="flex items-center gap-2">
                                            <span className="text-sm font-bold text-slate-900">
                                                {hasOnlineDesktopAgent
                                                    ? 'Host Local Agent Connected'
                                                    : 'Host Local Agent Not Connected'}
                                            </span>
                                            <span className={`text-[10px] px-2 py-0.5 rounded-full font-bold uppercase tracking-wider ${
                                                hasOnlineDesktopAgent
                                                    ? 'bg-emerald-100 text-emerald-800'
                                                    : 'bg-amber-100 text-amber-800'
                                            }`}>
                                                {hasOnlineDesktopAgent ? 'Online & Persistent' : 'Action Required'}
                                            </span>
                                        </div>
                                        <p className="text-xs text-slate-600 mt-0.5">
                                            {hasOnlineDesktopAgent
                                                ? `Agent ID: ${activeDesktopAgent.agent_id} (${activeDesktopAgent.os}). Monitoring authorized repositories continuously in the background.`
                                                : 'TrustNode Local Agent runs on your machine to monitor local files. It installs automatically during setup or can be downloaded below.'}
                                        </p>
                                        {!hasOnlineDesktopAgent && (
                                            <div className="mt-2.5 flex flex-wrap items-center gap-2">
                                                <a
                                                    href="/api/agent/installer?os=windows"
                                                    download="install-agent.ps1"
                                                    className="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold text-white bg-slate-900 rounded-lg hover:bg-slate-800 transition shadow-sm"
                                                >
                                                    <ExternalLink size={12} />
                                                    <span>Download Agent Installer (Windows)</span>
                                                </a>
                                                <a
                                                    href="/api/agent/installer?os=linux"
                                                    download="install-agent.sh"
                                                    className="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold text-slate-700 bg-white border border-slate-200 rounded-lg hover:bg-slate-50 transition"
                                                >
                                                    <ExternalLink size={12} />
                                                    <span>macOS / Linux Installer</span>
                                                </a>
                                            </div>
                                        )}
                                    </div>
                                </div>

                                <button
                                    type="button"
                                    onClick={loadAgents}
                                    disabled={fetchingAgents}
                                    className="p-1.5 text-slate-500 hover:text-slate-800 hover:bg-slate-200/60 rounded-lg transition"
                                    title="Refresh Agent Connection"
                                >
                                    <RefreshCw size={15} className={fetchingAgents ? "animate-spin" : ""} />
                                </button>
                            </div>

                            {/* 2. Absolute Folder Path */}
                            <div>
                                <label className="block text-sm font-semibold text-slate-900 mb-1.5">
                                    Absolute Folder Path
                                </label>
                                <input
                                    type="text"
                                    required
                                    placeholder="e.g. C:\Users\developer\projects\my-app or /home/developer/src/my-app"
                                    value={formData.path}
                                    onChange={(e) => {
                                        const p = e.target.value;
                                        setFormData(prev => ({
                                            ...prev,
                                            path: p,
                                            name: prev.name || p.split(/[\/\\]/).filter(Boolean).pop() || ''
                                        }));
                                    }}
                                    className={`w-full bg-slate-50 text-xs font-mono text-slate-900 border rounded-lg px-3 py-2.5 outline-none focus:bg-white transition-all ${
                                        errors.path ? 'border-red-300 focus:border-red-500' : 'border-slate-200 focus:border-brand-500'
                                    }`}
                                />
                                <p className="text-[11px] text-slate-400 mt-1">
                                    Specify the absolute path on your host machine. The Local Agent monitors and scans files locally without full-code upload.
                                </p>
                                {errors.path && <p className="text-xs text-red-600 mt-1 font-medium">{errors.path[0]}</p>}
                            </div>

                            {/* 3. Project Name */}
                            <div>
                                <label className="block text-sm font-semibold text-slate-900 mb-1.5">
                                    Project Name
                                </label>
                                <input
                                    type="text"
                                    required
                                    placeholder="e.g. Acme Web App"
                                    value={formData.name}
                                    onChange={(e) => setFormData(prev => ({ ...prev, name: e.target.value }))}
                                    className={`w-full bg-slate-50 text-sm text-slate-900 border rounded-lg px-3 py-2.5 outline-none focus:bg-white transition-all ${
                                        errors.name ? 'border-red-300 focus:border-red-500' : 'border-slate-200 focus:border-brand-500'
                                    }`}
                                />
                                {errors.name && <p className="text-xs text-red-600 mt-1 font-medium">{errors.name[0]}</p>}
                            </div>

                            {/* 4. Scan Profile */}
                            <div>
                                <label className="block text-sm font-semibold text-slate-900 mb-1.5">
                                    Scan Profile
                                </label>
                                <select
                                    value={formData.scan_profile}
                                    onChange={(e) => setFormData(prev => ({ ...prev, scan_profile: e.target.value }))}
                                    className="w-full bg-slate-50 text-sm text-slate-900 border border-slate-200 rounded-lg px-3 py-2.5 outline-none focus:bg-white focus:border-brand-500 transition-all"
                                >
                                    <option value="standard">Standard Security Scan (Balanced Vulnerabilities & Secrets)</option>
                                    <option value="full">Full Scan (Full AST, Secrets, and Comprehensive SAST)</option>
                                </select>
                            </div>

                            {/* 5. Continuous Monitoring Checkbox */}
                            <div className="flex items-start gap-3 p-4 border border-slate-200 rounded-xl bg-slate-50">
                                <input
                                    type="checkbox"
                                    id="continuous_scan_enabled"
                                    checked={formData.continuous_scan_enabled}
                                    onChange={(e) => setFormData(prev => ({ ...prev, continuous_scan_enabled: e.target.checked }))}
                                    className="mt-1 w-4 h-4 rounded border-slate-300 text-brand-600 focus:ring-brand-500"
                                />
                                <div>
                                    <label htmlFor="continuous_scan_enabled" className="text-sm font-bold text-slate-900 cursor-pointer block">
                                        Enable Continuous Monitoring & Change Detection
                                    </label>
                                    <p className="text-xs text-slate-500 mt-0.5">
                                        The Local Agent will continuously monitor this folder for file modifications, debouncing rapid changes before triggering an automatic incremental scan. Source files remain on your computer; only findings are transmitted.
                                    </p>
                                </div>
                            </div>

                        </div>
                    </Card>

                    {/* Action Buttons */}
                    <div className="flex items-center justify-end gap-3">
                        <button
                            type="button"
                            onClick={onCancel}
                            disabled={submitting}
                            className="px-4 py-2 text-sm font-semibold text-slate-600 bg-white border border-slate-200 rounded-lg hover:bg-slate-50 transition"
                        >
                            Cancel
                        </button>
                        <button
                            type="submit"
                            disabled={submitting || !formData.path.trim() || !formData.name.trim()}
                            className="inline-flex items-center gap-2 px-6 py-2.5 text-sm font-semibold text-white bg-brand-600 rounded-lg hover:bg-brand-700 transition disabled:opacity-50 disabled:cursor-not-allowed shadow-sm"
                        >
                            {submitting ? <Loader2 size={16} className="animate-spin" /> : <Save size={16} />}
                            <span>Authorize Project</span>
                        </button>
                    </div>
                </form>
            )}
        </div>
    );
}
