import React, { useState, useEffect } from 'react';
import { ArrowLeft, Shield, AlertTriangle, Save, Loader2 } from 'lucide-react';
import axios from 'axios';

export default function ScanFormPage({ scanId, onSave, onCancel }) {
    const isEdit = !!scanId;
    const [loading, setLoading] = useState(false);
    const [saving, setSaving] = useState(false);
    const [error, setError] = useState(null);
    const [validationErrors, setValidationErrors] = useState({});

    // Form inputs state
    const [name, setName] = useState('');
    const [description, setDescription] = useState('');
    const [type, setType] = useState('web_application');
    const [engine, setEngine] = useState('owasp_zap');
    const [target, setTarget] = useState('');
    const [schedule, setSchedule] = useState('');
    const [status, setStatus] = useState('queued');
    const [progress, setProgress] = useState(0);

    const [targetsList, setTargetsList] = useState([]);

    useEffect(() => {
        // Fetch target scope list to select target value easily
        const fetchTargets = async () => {
            try {
                const res = await axios.get('/api/targets');
                setTargetsList(res.data.data || []);
            } catch (err) {
                console.error('Failed to load targets list:', err);
            }
        };
        fetchTargets();

        if (isEdit) {
            const fetchScanData = async () => {
                setLoading(true);
                setError(null);
                try {
                    const response = await axios.get(`/api/scans/${scanId}`);
                    const s = response.data.data;
                    setName(s.name);
                    setDescription(s.description || '');
                    setType(s.type);
                    setEngine(s.engine);
                    setTarget(s.target);
                    setSchedule(s.schedule || '');
                    setStatus(s.status);
                    setProgress(s.progress || 0);
                } catch (err) {
                    console.error('Failed to load scan data:', err);
                    setError('Failed to load scan profile properties.');
                } finally {
                    setLoading(false);
                }
            };
            fetchScanData();
        }
    }, [scanId]);

    const handleSubmit = async (e) => {
        e.preventDefault();
        setSaving(true);
        setError(null);
        setValidationErrors({});

        const payload = {
            name,
            description,
            type,
            engine,
            target,
            schedule: schedule || null,
            status,
            progress: Number(progress)
        };

        try {
            if (isEdit) {
                await axios.put(`/api/scans/${scanId}`, payload);
            } else {
                await axios.post('/api/scans', payload);
            }
            onSave();
        } catch (err) {
            console.error('Failed to save scan profile:', err);
            if (err.response?.status === 422) {
                setValidationErrors(err.response.data.errors || {});
            } else {
                setError(err.response?.data?.message || 'Failed to save scan configuration.');
            }
        } finally {
            setSaving(false);
        }
    };

    if (loading) {
        return (
            <div className="bg-white border border-slate-200 rounded-xl p-6 shadow-sm flex items-center justify-center min-h-[40vh]">
                <Loader2 className="animate-spin text-brand-600 mr-2" size={24} />
                <span className="text-sm font-semibold text-slate-500">Loading scan profile...</span>
            </div>
        );
    }

    return (
        <div className="max-w-2xl mx-auto space-y-6">
            {/* Header section */}
            <div className="flex items-center gap-3">
                <button
                    onClick={onCancel}
                    className="p-1.5 border border-slate-200 rounded-lg hover:bg-slate-50 text-slate-500 hover:text-slate-800 transition"
                >
                    <ArrowLeft size={16} />
                </button>
                <div>
                    <h1 className="text-xl font-bold text-slate-900 tracking-tight">
                        {isEdit ? 'Modify Scan Configuration' : 'Launch New Scan Run'}
                    </h1>
                    <p className="text-xs text-slate-500 mt-0.5">
                        Configure target credentials and engine options for vulnerability scanning.
                    </p>
                </div>
            </div>

            {/* Error notifications */}
            {error && (
                <div className="bg-rose-50 border border-rose-200 rounded-xl p-4 flex gap-3 text-xs text-rose-800 font-medium">
                    <AlertTriangle size={16} className="text-rose-500 shrink-0" />
                    <span>{error}</span>
                </div>
            )}

            {/* Form card */}
            <form onSubmit={handleSubmit} className="bg-white border border-slate-200 rounded-xl p-5 shadow-sm space-y-5">
                {/* Scan Name */}
                <div className="space-y-1">
                    <label htmlFor="scan-name" className="text-xs font-bold text-slate-700 block">Scan Name</label>
                    <input
                        id="scan-name"
                        type="text"
                        placeholder="e.g. Edge Firewall Port Scan Audit"
                        value={name}
                        onChange={(e) => setName(e.target.value)}
                        className={`w-full border rounded-lg text-sm text-slate-800 py-2 px-3 focus:outline-none focus:ring-2 ${
                            validationErrors.name 
                                ? 'border-rose-400 focus:ring-rose-500/20 focus:border-rose-500' 
                                : 'border-slate-300 focus:ring-brand-500/20 focus:border-brand-500'
                        }`}
                    />
                    {validationErrors.name && (
                        <p className="text-rose-600 text-[10px] font-semibold mt-0.5">{validationErrors.name[0]}</p>
                    )}
                </div>

                {/* Grid Inputs - Type and Engine */}
                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                    {/* Scan Type */}
                    <div className="space-y-1">
                        <label htmlFor="scan-type" className="text-xs font-bold text-slate-700 block">Scan Type</label>
                        <select
                            id="scan-type"
                            value={type}
                            onChange={(e) => setType(e.target.value)}
                            className="w-full border border-slate-300 rounded-lg text-sm text-slate-700 py-2 px-3 focus:outline-none focus:ring-2 focus:ring-brand-500/20 focus:border-brand-500"
                        >
                            <option value="web_application">Web Application</option>
                            <option value="network_ip">Network IP</option>
                            <option value="port_discovery">Port Discovery</option>
                            <option value="api_vulnerability">API Vulnerability</option>
                            <option value="container_audit">Container Audit</option>
                            <option value="cloud_infrastructure">Cloud Infrastructure</option>
                        </select>
                    </div>

                    {/* Scan Engine */}
                    <div className="space-y-1">
                        <label htmlFor="scan-engine" className="text-xs font-bold text-slate-700 block">Scanning Engine</label>
                        <select
                            id="scan-engine"
                            value={engine}
                            onChange={(e) => setEngine(e.target.value)}
                            className="w-full border border-slate-300 rounded-lg text-sm text-slate-700 py-2 px-3 focus:outline-none focus:ring-2 focus:ring-brand-500/20 focus:border-brand-500"
                        >
                            <option value="owasp_zap">OWASP ZAP</option>
                            <option value="nmap">Nmap</option>
                            <option value="nuclei">Nuclei</option>
                            <option value="trivy">Trivy</option>
                            <option value="nessus">Nessus</option>
                        </select>
                    </div>
                </div>

                {/* Target details */}
                <div className="space-y-1">
                    <label htmlFor="scan-target" className="text-xs font-bold text-slate-700 block">Target Scope Address / Address Link</label>
                    <input
                        id="scan-target"
                        type="text"
                        placeholder="e.g. 10.10.1.254 or corp.internal"
                        value={target}
                        onChange={(e) => setTarget(e.target.value)}
                        className={`w-full border rounded-lg text-sm text-slate-800 py-2 px-3 focus:outline-none focus:ring-2 ${
                            validationErrors.target 
                                ? 'border-rose-400 focus:ring-rose-500/20 focus:border-rose-500' 
                                : 'border-slate-300 focus:ring-brand-500/20 focus:border-brand-500'
                        }`}
                    />
                    {validationErrors.target && (
                        <p className="text-rose-600 text-[10px] font-semibold mt-0.5">{validationErrors.target[0]}</p>
                    )}
                    {targetsList.length > 0 && (
                        <div className="mt-1.5 flex flex-wrap gap-1.5 items-center">
                            <span className="text-[10px] text-slate-400 font-medium">Quick link target:</span>
                            {targetsList.slice(0, 3).map((t) => (
                                <button
                                    key={t.id}
                                    type="button"
                                    onClick={() => setTarget(t.value)}
                                    className="text-[10px] px-2 py-0.5 border border-slate-200 rounded hover:bg-slate-100 hover:border-slate-300 text-slate-600 font-mono transition"
                                >
                                    {t.value}
                                </button>
                            ))}
                        </div>
                    )}
                </div>

                {/* Cron Schedule */}
                <div className="space-y-1">
                    <label htmlFor="scan-schedule" className="text-xs font-bold text-slate-700 block">Cron Schedule (Optional)</label>
                    <input
                        id="scan-schedule"
                        type="text"
                        placeholder="e.g. 0 0 * * 0 (Leave blank for manual run)"
                        value={schedule}
                        onChange={(e) => setSchedule(e.target.value)}
                        className="w-full border border-slate-300 rounded-lg text-sm text-slate-800 py-2 px-3 focus:outline-none focus:ring-2 focus:ring-brand-500/20 focus:border-brand-500"
                    />
                    <p className="text-[10px] text-slate-400 italic">Standard cron formatting (Minute Hour Day-of-Month Month Day-of-Week).</p>
                </div>

                {/* Edit-only parameters: Status & Progress */}
                {isEdit && (
                    <div className="grid grid-cols-1 md:grid-cols-2 gap-4 border-t border-slate-100 pt-4">
                        {/* Status */}
                        <div className="space-y-1">
                            <label htmlFor="scan-status" className="text-xs font-bold text-slate-700 block">Scan Status</label>
                            <select
                                id="scan-status"
                                value={status}
                                onChange={(e) => setStatus(e.target.value)}
                                className="w-full border border-slate-300 rounded-lg text-sm text-slate-700 py-2 px-3 focus:outline-none focus:ring-2 focus:ring-brand-500/20 focus:border-brand-500"
                            >
                                <option value="queued">Queued</option>
                                <option value="running">Running</option>
                                <option value="completed">Completed</option>
                                <option value="failed">Failed</option>
                                <option value="cancelled">Cancelled</option>
                            </select>
                        </div>

                        {/* Progress */}
                        <div className="space-y-1">
                            <label htmlFor="scan-progress" className="text-xs font-bold text-slate-700 block">Progress Percentage (0 - 100)</label>
                            <input
                                id="scan-progress"
                                type="number"
                                min="0"
                                max="100"
                                value={progress}
                                onChange={(e) => setProgress(e.target.value)}
                                className="w-full border border-slate-300 rounded-lg text-sm text-slate-800 py-2 px-3 focus:outline-none focus:ring-2 focus:ring-brand-500/20 focus:border-brand-500"
                            />
                        </div>
                    </div>
                )}

                {/* Description */}
                <div className="space-y-1">
                    <label htmlFor="scan-desc" className="text-xs font-bold text-slate-700 block">Assessment Notes / Description</label>
                    <textarea
                        id="scan-desc"
                        rows="3"
                        placeholder="VAPT test descriptions and config notes..."
                        value={description}
                        onChange={(e) => setDescription(e.target.value)}
                        className="w-full border border-slate-300 rounded-lg text-sm text-slate-800 py-2 px-3 focus:outline-none focus:ring-2 focus:ring-brand-500/20 focus:border-brand-500"
                    />
                </div>

                {/* Form Buttons */}
                <div className="flex items-center justify-end gap-3 border-t border-slate-100 pt-4">
                    <button
                        type="button"
                        onClick={onCancel}
                        disabled={saving}
                        className="px-4 py-2 text-sm font-semibold text-slate-700 bg-white border border-slate-300 hover:bg-slate-50 rounded-lg shadow-sm disabled:opacity-40 transition"
                    >
                        Cancel
                    </button>
                    <button
                        type="submit"
                        disabled={saving}
                        className="inline-flex items-center justify-center gap-1.5 px-4 py-2 text-sm font-semibold text-white bg-brand-600 hover:bg-brand-700 active:bg-brand-800 rounded-lg shadow-sm border border-brand-700 disabled:opacity-40 transition"
                    >
                        {saving ? (
                            <>
                                <Loader2 className="animate-spin" size={16} />
                                Saving Run...
                            </>
                        ) : (
                            <>
                                <Save size={16} />
                                {isEdit ? 'Update Scan Config' : 'Initialize Scan Execution'}
                            </>
                        )}
                    </button>
                </div>
            </form>
        </div>
    );
}
