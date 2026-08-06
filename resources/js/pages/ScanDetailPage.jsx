import React, { useState, useEffect } from 'react';
import { 
    ArrowLeft, Calendar, Shield, Activity, Clock, Play, AlertTriangle, 
    CheckCircle, XCircle, Ban, History, FileText, User, ChevronRight, Terminal
} from 'lucide-react';
import axios from 'axios';
import { ScanStatusBadge, ScanTypeBadge, ScanEngineBadge, ProgressBar } from '../components/ui/primitives_scans';
import { Skeleton } from '../components/ui/primitives';

export default function ScanDetailPage({ scanId, onBack, onEdit }) {
    const [scan, setScan] = useState(null);
    const [logs, setLogs] = useState([]);
    const [loading, setLoading] = useState(true);
    const [loadingLogs, setLoadingLogs] = useState(false);
    const [error, setError] = useState(null);

    const fetchScanDetail = async () => {
        setLoading(true);
        setError(null);
        try {
            const response = await axios.get(`/api/scans/${scanId}`);
            setScan(response.data.data);
            
            // Fetch associated activity logs history
            setLoadingLogs(true);
            const logsResponse = await axios.get(`/api/scans/${scanId}/activity`);
            setLogs(logsResponse.data.data || []);
        } catch (err) {
            console.error('Error loading scan details:', err);
            setError('Failed to load scan profile properties.');
        } finally {
            setLoading(false);
            setLoadingLogs(false);
        }
    };

    useEffect(() => {
        if (scanId) {
            fetchScanDetail();
        }
    }, [scanId]);

    if (loading) {
        return (
            <div className="bg-white border border-slate-200 rounded-xl p-6 shadow-sm space-y-4">
                <Skeleton className="h-6 w-1/3" />
                <Skeleton className="h-20 w-full" />
                <Skeleton className="h-20 w-full" />
            </div>
        );
    }

    if (error || !scan) {
        return (
            <div className="bg-rose-50 border border-rose-200 rounded-xl p-5 text-center">
                <p className="text-sm font-semibold text-rose-800">{error || 'Scan not found.'}</p>
                <button
                    onClick={onBack}
                    className="mt-3 inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold text-slate-700 bg-white border border-slate-300 rounded-lg hover:bg-slate-100 transition"
                >
                    <ArrowLeft size={12} />
                    Back to Catalog
                </button>
            </div>
        );
    }

    return (
        <div className="space-y-6">
            {/* Breadcrumbs & Title */}
            <div className="flex flex-col gap-2">
                <div className="flex items-center gap-2 text-xs text-slate-500 font-medium">
                    <span className="cursor-pointer hover:text-slate-800" onClick={onBack}>Scans</span>
                    <ChevronRight size={12} />
                    <span className="text-slate-700 font-semibold truncate max-w-[200px]">{scan.name}</span>
                </div>
                <div className="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                    <div className="flex items-center gap-3">
                        <button
                            onClick={onBack}
                            className="p-1.5 border border-slate-200 rounded-lg hover:bg-slate-50 text-slate-500 hover:text-slate-800 transition"
                        >
                            <ArrowLeft size={16} />
                        </button>
                        <div>
                            <h1 className="text-xl font-bold text-slate-900 tracking-tight">{scan.name}</h1>
                            <p className="text-xs font-mono text-slate-400 mt-0.5">UUID: {scan.uuid}</p>
                        </div>
                    </div>
                    <div className="flex items-center gap-2">
                        <button
                            onClick={() => onEdit(scan.id)}
                            className="px-3.5 py-1.5 text-xs font-semibold text-slate-700 bg-white border border-slate-300 hover:bg-slate-50 rounded-lg shadow-sm transition"
                        >
                            Edit Configuration
                        </button>
                    </div>
                </div>
            </div>

            {/* Content layout */}
            <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
                {/* Left column - main specs */}
                <div className="lg:col-span-2 space-y-6">
                    {/* Status progress overview */}
                    <div className="bg-white border border-slate-200 rounded-xl p-5 shadow-sm space-y-4">
                        <h2 className="text-sm font-bold text-slate-900 border-b border-slate-100 pb-2.5">Execution Status</h2>
                        <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div>
                                <span className="text-xs text-slate-400 font-semibold block mb-1">State</span>
                                <ScanStatusBadge status={scan.status} />
                            </div>
                            <div className="md:col-span-2">
                                <span className="text-xs text-slate-400 font-semibold block mb-1">Progress</span>
                                <ProgressBar progress={scan.progress} status={scan.status} />
                            </div>
                        </div>
                    </div>

                    {/* Technical details grid */}
                    <div className="bg-white border border-slate-200 rounded-xl p-5 shadow-sm space-y-4">
                        <h2 className="text-sm font-bold text-slate-900 border-b border-slate-100 pb-2.5">Technical Profile</h2>
                        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <span className="text-xs text-slate-400 font-semibold block">Scan Type</span>
                                <div className="mt-1">
                                    <ScanTypeBadge type={scan.type} />
                                </div>
                            </div>
                            <div>
                                <span className="text-xs text-slate-400 font-semibold block">Vulnerability Engine</span>
                                <div className="mt-1">
                                    <ScanEngineBadge engine={scan.engine} />
                                </div>
                            </div>
                            <div className="md:col-span-2">
                                <span className="text-xs text-slate-400 font-semibold block">Target Address / FQDN</span>
                                <span className="text-sm font-mono text-slate-800 bg-slate-50 px-2.5 py-1.5 rounded-lg border border-slate-200 block mt-1">
                                    {scan.target}
                                </span>
                            </div>
                            <div className="md:col-span-2">
                                <span className="text-xs text-slate-400 font-semibold block">Description</span>
                                <p className="text-sm text-slate-600 mt-1 leading-relaxed">
                                    {scan.description || <span className="italic text-slate-400">No description provided.</span>}
                                </p>
                            </div>
                        </div>
                    </div>

                    {/* Timeline Activity history logs */}
                    <div className="bg-white border border-slate-200 rounded-xl p-5 shadow-sm space-y-4">
                        <h2 className="text-sm font-bold text-slate-900 border-b border-slate-100 pb-2.5">Scan Run Audit History</h2>
                        {loadingLogs ? (
                            <div className="space-y-3">
                                <Skeleton className="h-4 w-full" />
                                <Skeleton className="h-4 w-3/4" />
                            </div>
                        ) : logs.length === 0 ? (
                            <p className="text-xs text-slate-400 italic">No activity log entries recorded for this scan profile.</p>
                        ) : (
                            <div className="relative border-l-2 border-slate-200 pl-4 space-y-4 ml-2">
                                {logs.map((log) => (
                                    <div key={log.id} className="relative text-xs text-slate-600 space-y-1">
                                        <div className="absolute -left-[21px] top-0.5 w-2 h-2 rounded-full bg-slate-300 ring-4 ring-white" />
                                        <div className="flex items-center gap-1.5 font-semibold text-slate-800">
                                            <span>Scan {log.action}</span>
                                            {log.user && (
                                                <span className="text-slate-400 text-[10px] font-normal">
                                                    by {log.user.name}
                                                </span>
                                            )}
                                        </div>
                                        <div className="text-[10px] text-slate-400 flex items-center gap-2">
                                            <span>{new Date(log.created_at).toLocaleString()}</span>
                                            {log.ip_address && <span>IP: {log.ip_address}</span>}
                                        </div>
                                    </div>
                                ))}
                            </div>
                        )}
                    </div>
                </div>

                {/* Right column - duration statistics, creators info */}
                <div className="space-y-6">
                    {/* Execution times metrics card */}
                    <div className="bg-white border border-slate-200 rounded-xl p-5 shadow-sm space-y-4">
                        <h2 className="text-sm font-bold text-slate-900 border-b border-slate-100 pb-2.5">Execution Details</h2>
                        <div className="space-y-3">
                            <div className="flex justify-between items-center text-xs">
                                <span className="text-slate-400 font-semibold">Cron Schedule</span>
                                <span className="font-mono text-slate-700 bg-slate-100 px-1.5 py-0.5 rounded border border-slate-200">
                                    {scan.schedule || 'Manual'}
                                </span>
                            </div>
                            <div className="flex justify-between items-center text-xs">
                                <span className="text-slate-400 font-semibold">Started At</span>
                                <span className="text-slate-700 font-mono">
                                    {scan.started_at ? new Date(scan.started_at).toLocaleString() : 'N/A'}
                                </span>
                            </div>
                            <div className="flex justify-between items-center text-xs">
                                <span className="text-slate-400 font-semibold">Completed At</span>
                                <span className="text-slate-700 font-mono">
                                    {scan.completed_at ? new Date(scan.completed_at).toLocaleString() : 'N/A'}
                                </span>
                            </div>
                            <div className="flex justify-between items-center text-xs border-t border-slate-100 pt-2.5">
                                <span className="text-slate-400 font-semibold">Duration</span>
                                <span className="text-slate-700 font-mono font-semibold">
                                    {scan.duration ? `${Math.floor(scan.duration / 60)}m ${scan.duration % 60}s` : 'N/A'}
                                </span>
                            </div>
                        </div>
                    </div>

                    {/* Metadata ownership card */}
                    <div className="bg-white border border-slate-200 rounded-xl p-5 shadow-sm space-y-3.5 text-xs text-slate-500">
                        <div className="flex items-center gap-2">
                            <User size={14} className="text-slate-400" />
                            <span>Created by <strong className="text-slate-700">{scan.created_by?.name || 'System Admin'}</strong></span>
                        </div>
                        <div className="flex items-center gap-2">
                            <Clock size={14} className="text-slate-400" />
                            <span>Configured on <strong>{new Date(scan.created_at).toLocaleDateString()}</strong></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    );
}
