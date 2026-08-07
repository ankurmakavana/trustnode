import React, { useState, useEffect } from 'react';
import { 
    ArrowLeft, Loader2, AlertTriangle, Blocks, CheckCircle2, RefreshCw, 
    Unplug, Trash2, Clock, Calendar, CheckSquare, Shield, HelpCircle
} from 'lucide-react';
import axios from 'axios';

export default function IntegrationDetailPage({ integrationId, onBack }) {
    const [integration, setIntegration] = useState(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState(null);
    const [validating, setValidating] = useState(false);

    const fetchIntegrationDetails = async () => {
        setLoading(true);
        setError(null);
        try {
            const response = await axios.get(`/api/integrations/${integrationId}`);
            setIntegration(response.data.data);
        } catch (err) {
            console.error('Failed to load integration detail:', err);
            setError('Failed to retrieve integration logs profile.');
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => {
        fetchIntegrationDetails();
    }, [integrationId]);

    const handleValidate = async () => {
        setValidating(true);
        try {
            const response = await axios.post(`/api/integrations/${integrationId}/validate`);
            alert(`Validation check: ${response.data.status}`);
            fetchIntegrationDetails();
        } catch (err) {
            alert('Failed to validate connection health status.');
        } finally {
            setValidating(false);
        }
    };

    const handleDisconnect = async () => {
        if (!confirm('Are you sure you want to disconnect this scanner?')) return;
        try {
            await axios.post(`/api/integrations/${integrationId}/disconnect`);
            fetchIntegrationDetails();
        } catch (err) {
            alert('Failed to disconnect integration.');
        }
    };

    const handleDelete = async () => {
        if (!confirm('Are you sure you want to permanently delete this integration profile?')) return;
        try {
            await axios.delete(`/api/integrations/${integrationId}`);
            onBack();
        } catch (err) {
            alert('Failed to delete integration.');
        }
    };

    if (loading) {
        return (
            <div className="bg-white border border-slate-200 rounded-xl p-12 shadow-sm flex items-center justify-center min-h-[50vh]">
                <Loader2 className="animate-spin text-brand-600 mr-2" size={24} />
                <span className="text-sm font-semibold text-slate-500">Retrieving connector job logs history database...</span>
            </div>
        );
    }

    if (error || !integration) {
        return (
            <div className="bg-white border border-slate-200 rounded-xl p-12 text-center space-y-4 max-w-md mx-auto shadow-sm">
                <div className="w-12 h-12 rounded-full bg-rose-50 border border-rose-200 flex items-center justify-center mx-auto text-rose-500">
                    <AlertTriangle size={20} />
                </div>
                <div>
                    <h3 className="text-sm font-bold text-slate-805">Error Loading Integration</h3>
                    <p className="text-xs text-slate-500 mt-1">{error || 'Integration dossier profile not found.'}</p>
                </div>
                <button
                    onClick={onBack}
                    className="px-4 py-2 text-xs font-semibold text-slate-700 bg-white border border-slate-300 hover:bg-slate-50 rounded-lg shadow-sm transition"
                >
                    Back to Registry
                </button>
            </div>
        );
    }

    return (
        <div className="space-y-6 max-w-7xl mx-auto text-xs">
            {/* Header */}
            <div className="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                <div className="flex items-center gap-3">
                    <button
                        onClick={onBack}
                        className="p-1.5 border border-slate-205 rounded-lg hover:bg-slate-50 text-slate-500 hover:text-slate-805 transition"
                    >
                        <ArrowLeft size={16} />
                    </button>
                    <div>
                        <span className="text-[10px] font-mono font-bold text-slate-400">{integration.code.toUpperCase()} CONNECTOR</span>
                        <h1 className="text-lg font-bold text-slate-900 tracking-tight mt-0.5">{integration.name}</h1>
                    </div>
                </div>

                <div className="flex items-center gap-2">
                    <button
                        disabled={validating}
                        onClick={handleValidate}
                        className="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold text-slate-700 bg-white hover:bg-slate-50 border border-slate-205 rounded-lg transition"
                    >
                        {validating ? <Loader2 className="animate-spin" size={12} /> : <RefreshCw size={12} />}
                        Validate Health
                    </button>
                    {integration.status === 'Connected' && (
                        <button
                            onClick={handleDisconnect}
                            className="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold text-slate-700 bg-white hover:bg-slate-55 hover:bg-slate-50 border border-slate-205 rounded-lg transition"
                        >
                            <Unplug size={12} />
                            Disconnect
                        </button>
                    )}
                    <button
                        onClick={handleDelete}
                        className="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold text-rose-650 bg-rose-50 hover:bg-rose-100 border border-rose-205 rounded-lg transition"
                    >
                        <Trash2 size={12} />
                        Delete Connector
                    </button>
                </div>
            </div>

            {/* Details & Logs split layout */}
            <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
                
                {/* Left Side: properties timeline & status overview */}
                <div className="lg:col-span-1 space-y-6">
                    <div className="bg-white border border-slate-200 rounded-xl p-4.5 shadow-sm space-y-4">
                        <span className="text-[10px] uppercase font-bold text-slate-400 tracking-wider block border-b border-slate-100 pb-2.5">Connection Credentials</span>
                        <div className="space-y-3">
                            <div className="flex justify-between">
                                <span className="text-slate-450 font-medium">Status</span>
                                <span className={`font-bold px-1.5 py-0.2 rounded border ${
                                    integration.status === 'Connected' ? 'bg-emerald-50 text-emerald-800 border-emerald-200' : 'bg-slate-100 text-slate-500 border-slate-200'
                                }`}>{integration.status}</span>
                            </div>
                            <div className="flex justify-between">
                                <span className="text-slate-450 font-medium">API Host</span>
                                <span className="font-semibold font-mono text-slate-800">{integration.host || 'N/A'}</span>
                            </div>
                            <div className="flex justify-between">
                                <span className="text-slate-450 font-medium">Port</span>
                                <span className="font-semibold font-mono text-slate-800">{integration.port || 'N/A'}</span>
                            </div>
                            <div className="flex justify-between">
                                <span className="text-slate-450 font-medium">Username</span>
                                <span className="font-semibold text-slate-800">{integration.username || 'Anonymous'}</span>
                            </div>
                            <div className="flex justify-between">
                                <span className="text-slate-450 font-medium">TLS Enforced</span>
                                <span className="font-semibold text-slate-800">{integration.tls ? 'Yes' : 'No'}</span>
                            </div>
                            <div className="flex justify-between">
                                <span className="text-slate-450 font-medium">Health Status</span>
                                <span className={`font-bold px-1.5 py-0.2 rounded border ${
                                    integration.health_status === 'Healthy' ? 'bg-emerald-50 text-emerald-800 border-emerald-200' : 'bg-rose-50 text-rose-800 border-rose-200'
                                }`}>{integration.health_status}</span>
                            </div>
                        </div>
                    </div>

                    {/* Recent audit activity history logs */}
                    <div className="bg-white border border-slate-200 rounded-xl p-4.5 shadow-sm space-y-4">
                        <span className="text-[10px] uppercase font-bold text-slate-400 tracking-wider block border-b border-slate-100 pb-2.5">Recent Integration Events</span>
                        {!integration.histories || integration.histories.length === 0 ? (
                            <p className="italic text-slate-400">No activity logs recorded.</p>
                        ) : (
                            <div className="space-y-3 relative before:absolute before:left-1.5 before:top-2 before:bottom-2 before:w-0.5 before:bg-slate-100">
                                {integration.histories.map(h => (
                                    <div key={h.id} className="flex gap-2.5 items-start relative">
                                        <div className={`w-3.5 h-3.5 rounded-full border-2 border-white shrink-0 z-10 ${
                                            h.status === 'Success' ? 'bg-emerald-500' : 'bg-rose-500'
                                        }`} />
                                        <div className="space-y-0.5">
                                            <p className="font-bold text-slate-800 text-[10.5px]">{h.action}</p>
                                            <p className="text-slate-500 text-[10px]">{h.description}</p>
                                            <span className="text-[9px] text-slate-400 block font-mono">{new Date(h.created_at).toLocaleDateString()}</span>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        )}
                    </div>
                </div>

                {/* Right Side: Jobs execution log queue lists */}
                <div className="lg:col-span-2 space-y-6">
                    <div className="bg-white border border-slate-200 rounded-2xl p-5 shadow-sm space-y-4">
                        <span className="text-[10px] uppercase font-bold text-slate-400 tracking-wider block border-b border-slate-100 pb-2.5">Job History logs</span>
                        {!integration.jobs || integration.jobs.length === 0 ? (
                            <p className="italic text-slate-400 py-6 text-center">No connector jobs executed yet.</p>
                        ) : (
                            <div className="border border-slate-200 rounded-xl overflow-hidden">
                                <table className="w-full text-left border-collapse">
                                    <thead>
                                        <tr className="bg-slate-50 border-b border-slate-200 text-[10px] font-bold text-slate-500 uppercase">
                                            <th className="p-2.5">Job ID / UUID</th>
                                            <th className="p-2.5">Status</th>
                                            <th className="p-2.5">Duration</th>
                                            <th className="p-2.5">Imported Records</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-slate-100">
                                        {integration.jobs.map(job => (
                                            <tr key={job.id}>
                                                <td className="p-2.5 font-mono font-bold text-slate-650">{job.uuid.slice(0, 8)}</td>
                                                <td className="p-2.5">
                                                    <span className={`px-2 py-0.2 rounded text-[9.5px] font-bold border ${
                                                        job.status === 'Completed' ? 'bg-emerald-50 text-emerald-800 border-emerald-250' :
                                                        job.status === 'Running' ? 'bg-brand-50 text-brand-850 border-brand-200 animate-pulse' :
                                                                                   'bg-rose-50 text-rose-800 border-rose-250'
                                                    }`}>{job.status}</span>
                                                </td>
                                                <td className="p-2.5 text-slate-650 font-mono">{job.duration}s</td>
                                                <td className="p-2.5 text-slate-700 font-bold">{job.imported_records} items</td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </div>
                </div>

            </div>
        </div>
    );
}