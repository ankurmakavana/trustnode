import React, { useState, useEffect, useCallback } from 'react';
import axios from 'axios';
import {
    Loader2, Shield, ShieldCheck, ShieldAlert, Activity, Eye, Search, FileCode2,
    Clock, CheckCircle2, AlertTriangle, XCircle, Cpu, RefreshCw, Radio, ArrowRight,
    CheckSquare, Database, ListFilter, Server, Zap
} from 'lucide-react';
import { Card, CardHeader, MonoChip } from '../components/ui/primitives';
import { SeverityBadge } from '../components/ui/primitives_findings';
import { Link } from 'react-router-dom';
import { formatRelativeTime, formatHumanDateTime } from '../utils/dateUtils';

export default function AgentPage() {
    const [telemetry, setTelemetry] = useState(null);
    const [findings, setFindings] = useState([]);
    const [loading, setLoading] = useState(true);
    const [lastRefreshed, setLastRefreshed] = useState(null);

    const fetchConsoleData = useCallback(async () => {
        try {
            const [consoleRes, findingsRes] = await Promise.all([
                axios.get('/api/agent/console'),
                axios.get('/api/findings', { params: { per_page: 5 } })
            ]);
            setTelemetry(consoleRes.data);
            setFindings(findingsRes.data.data || []);
            setLastRefreshed(new Date());
        } catch (err) {
            console.error('Failed to load agent console telemetry', err);
        } finally {
            setLoading(false);
        }
    }, []);

    useEffect(() => {
        fetchConsoleData();
        const interval = setInterval(fetchConsoleData, 15000); // 15s polling
        return () => clearInterval(interval);
    }, [fetchConsoleData]);

    if (loading && !telemetry) {
        return (
            <div className="flex flex-col items-center justify-center min-h-[60vh] gap-3">
                <Loader2 className="animate-spin text-brand-600" size={32} />
                <span className="text-xs font-semibold text-slate-600">Retrieving Live Agent Telemetry...</span>
            </div>
        );
    }

    const overallState = telemetry?.overall_state || 'UNKNOWN';
    const agent = telemetry?.agent || {};
    const process = telemetry?.process || {};
    const worker = telemetry?.worker || {};
    const obs = telemetry?.observation || {};
    const scan = telemetry?.scan || {};
    const queue = telemetry?.queue || {};
    const metrics = telemetry?.metrics || {};
    const activeTask = telemetry?.active_task || null;
    const recentTasks = telemetry?.recent_tasks || [];
    const timeline = telemetry?.activity_timeline || [];

    // Derive top level badge and banner configuration
    let stateConfig = {
        label: 'HEALTHY IDLE',
        bg: 'bg-emerald-50 text-emerald-800 border-emerald-200',
        dot: 'bg-emerald-500',
        bannerBg: 'bg-emerald-50/70 border-emerald-200',
        title: '● HEALTHY — IDLE OBSERVATION LOOP',
        description: 'Agent process is running, worker is active, and environment observation loop is performing scheduled monitoring.'
    };

    if (overallState === 'OBSERVING') {
        stateConfig = {
            label: 'OBSERVING',
            bg: 'bg-brand-50 text-brand-800 border-brand-200',
            dot: 'bg-brand-500 animate-ping',
            bannerBg: 'bg-brand-50/80 border-brand-200',
            title: '● OBSERVING SECURITY STATE',
            description: `Agent is currently inspecting target file: ${obs.target ?? 'N/A'}.`
        };
    } else if (overallState === 'REPORTING' || scan.status === 'scanning') {
        stateConfig = {
            label: 'SCANNING / REPORTING',
            bg: 'bg-purple-50 text-purple-800 border-purple-200',
            dot: 'bg-purple-500 animate-ping',
            bannerBg: 'bg-purple-50/80 border-purple-200',
            title: '● EXECUTING SECRET SCANNER & REPORTING FINDINGS',
            description: 'Environment modification detected. SecretScanner is actively running and persisting finding identity record.'
        };
    } else if (overallState === 'STALE_OBSERVATION') {
        stateConfig = {
            label: 'STALE OBSERVATION',
            bg: 'bg-amber-50 text-amber-800 border-amber-200',
            dot: 'bg-amber-500 animate-pulse',
            bannerBg: 'bg-amber-50/80 border-amber-200',
            title: '⚠️ STALE OBSERVATION WARNING',
            description: `Agent process is alive, but the observation loop has not completed an observation recently (${formatRelativeTime(obs.last_completed_at)}).`
        };
    } else if (overallState === 'STALE_HEARTBEAT') {
        stateConfig = {
            label: 'STALE HEARTBEAT',
            bg: 'bg-amber-50 text-amber-800 border-amber-200',
            dot: 'bg-amber-500 animate-pulse',
            bannerBg: 'bg-amber-50/80 border-amber-200',
            title: '⚠️ PROCESS HEARTBEAT STALE',
            description: `Agent process heartbeat has exceeded the 60s timeout limit (last heartbeat: ${formatRelativeTime(process.last_heartbeat_at)}).`
        };
    } else if (overallState === 'STOPPED') {
        stateConfig = {
            label: 'STOPPED',
            bg: 'bg-slate-100 text-slate-700 border-slate-200',
            dot: 'bg-slate-400',
            bannerBg: 'bg-slate-100/80 border-slate-300',
            title: '● AGENT RUNTIME STOPPED',
            description: 'TrustNode Agent daemon is currently offline.'
        };
    }

    return (
        <div className="space-y-6">
            {/* 1. AGENT IDENTITY & TOP BADGE HEADER */}
            <div className="bg-white border border-slate-200 rounded-xl p-5 shadow-sm flex flex-col md:flex-row items-start md:items-center justify-between gap-4">
                <div className="flex items-center gap-4">
                    <div className="w-12 h-12 rounded-xl bg-brand-50 border border-brand-200 flex items-center justify-center text-brand-600 shrink-0">
                        <Shield size={24} />
                    </div>
                    <div>
                        <div className="flex items-center gap-3">
                            <h1 className="text-lg font-bold text-slate-900 tracking-tight">TrustNode Agent</h1>
                            <span className={`inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-mono font-bold border ${stateConfig.bg}`}>
                                <span className={`w-2 h-2 rounded-full ${stateConfig.dot}`} />
                                {stateConfig.label}
                            </span>
                        </div>
                        <div className="flex items-center gap-4 text-xs text-slate-500 mt-1 font-mono">
                            <span>ID: <strong className="text-slate-700">{agent.id}</strong></span>
                            <span>•</span>
                            <span>Version: <strong className="text-slate-700">{agent.version}</strong></span>
                            <span>•</span>
                            <span>Environment: <strong className="text-slate-700">{agent.environment}</strong></span>
                        </div>
                    </div>
                </div>

                <div className="flex items-center gap-2 self-end md:self-auto">
                    {lastRefreshed && (
                        <span className="text-[11px] text-slate-400 font-mono">
                            Auto-refreshed {lastRefreshed.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', second: '2-digit' })}
                        </span>
                    )}
                    <button
                        onClick={fetchConsoleData}
                        className="p-1.5 text-slate-500 hover:text-slate-700 border border-slate-200 hover:bg-slate-50 rounded-lg transition"
                        title="Force refresh operational state"
                    >
                        <RefreshCw size={14} />
                    </button>
                </div>
            </div>

            {/* 2. OPERATIONAL STATE BANNER */}
            <div className={`p-4 rounded-xl border ${stateConfig.bannerBg} flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3 shadow-sm`}>
                <div>
                    <h3 className="text-xs font-bold font-mono tracking-wider uppercase text-slate-900">{stateConfig.title}</h3>
                    <p className="text-xs text-slate-700 mt-0.5 leading-relaxed">{stateConfig.description}</p>
                </div>
                {obs.last_completed_at && (
                    <div className="text-right text-xs shrink-0 font-mono bg-white/70 px-3 py-1.5 rounded-lg border border-slate-200">
                        <span className="text-slate-500 block text-[10px] uppercase">Last Observation</span>
                        <span className="font-bold text-slate-800">{formatRelativeTime(obs.last_completed_at)}</span>
                    </div>
                )}
            </div>

            {/* 3. OPERATIONAL HEALTH (IS IT REALLY WORKING?) */}
            <Card padding={false}>
                <CardHeader
                    title="Operational Health Matrix"
                    subtitle="Real-time multi-layered runtime state verification"
                    icon={<Activity className="w-4 h-4 text-brand-500" />}
                />
                <div className="p-5 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 divide-y sm:divide-y-0 sm:divide-x divide-slate-100 text-xs">
                    {/* Component 1: Agent Process */}
                    <div className="pt-2 sm:pt-0 sm:pr-3 space-y-2">
                        <div className="flex items-center justify-between">
                            <span className="font-bold text-slate-700 flex items-center gap-1.5">
                                <Server size={14} className="text-slate-400" /> Process
                            </span>
                            <span className={`px-2 py-0.5 rounded font-mono font-bold text-[10px] uppercase ${
                                process.status === 'running' ? 'bg-emerald-100 text-emerald-800' : 'bg-rose-100 text-rose-800'
                            }`}>
                                ● {process.status}
                            </span>
                        </div>
                        <div className="text-slate-500 text-[11px] space-y-1 font-mono">
                            <div>Heartbeat: <span className="text-slate-800 font-semibold">{formatRelativeTime(process.last_heartbeat_at)}</span></div>
                            <div>Interval: <span className="text-slate-700">Every 30s</span></div>
                        </div>
                    </div>

                    {/* Component 2: Worker */}
                    <div className="pt-3 sm:pt-0 sm:px-3 space-y-2">
                        <div className="flex items-center justify-between">
                            <span className="font-bold text-slate-700 flex items-center gap-1.5">
                                <Zap size={14} className="text-slate-400" /> Queue Worker
                            </span>
                            <span className={`px-2 py-0.5 rounded font-mono font-bold text-[10px] uppercase ${
                                worker.status === 'processing' ? 'bg-brand-100 text-brand-800 animate-pulse' :
                                worker.status === 'idle' ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-slate-100 text-slate-700'
                            }`}>
                                ● {worker.status}
                            </span>
                        </div>
                        <div className="text-slate-500 text-[11px] space-y-1 font-mono">
                            <div>Active Task: <span className="text-slate-800 font-semibold">{worker.current_task_type ?? 'None (Idle)'}</span></div>
                            <div>Queue State: <span className="text-slate-700">{queue.pending || 0} pending / {queue.processing || 0} active</span></div>
                        </div>
                    </div>

                    {/* Component 3: Observation Loop */}
                    <div className="pt-3 sm:pt-0 sm:px-3 space-y-2">
                        <div className="flex items-center justify-between">
                            <span className="font-bold text-slate-700 flex items-center gap-1.5">
                                <Eye size={14} className="text-slate-400" /> Observation Loop
                            </span>
                            <span className={`px-2 py-0.5 rounded font-mono font-bold text-[10px] uppercase ${
                                obs.stale ? 'bg-amber-100 text-amber-800' : 'bg-emerald-100 text-emerald-800'
                            }`}>
                                ● {obs.stale ? 'Stale' : 'Active'}
                            </span>
                        </div>
                        <div className="text-slate-500 text-[11px] space-y-1 font-mono">
                            <div>Last Check: <span className="text-slate-800 font-semibold">{formatRelativeTime(obs.last_completed_at)}</span></div>
                            <div>Schedule: <span className="text-slate-700">Every minute</span></div>
                        </div>
                    </div>

                    {/* Component 4: Scanner */}
                    <div className="pt-3 sm:pt-0 sm:pl-3 space-y-2">
                        <div className="flex items-center justify-between">
                            <span className="font-bold text-slate-700 flex items-center gap-1.5">
                                <Search size={14} className="text-slate-400" /> Scanner Engine
                            </span>
                            <span className={`px-2 py-0.5 rounded font-mono font-bold text-[10px] uppercase ${
                                scan.engine_status === 'RUNNING' ? 'bg-purple-100 text-purple-800 animate-pulse' :
                                scan.engine_status === 'IDLE' ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-slate-100 text-slate-700'
                            }`}>
                                ● {scan.engine_status ?? 'UNKNOWN'}
                            </span>
                        </div>
                        <div className="text-slate-500 text-[11px] space-y-1 font-mono">
                            <div>Scanner: <span className="text-slate-800 font-semibold">{scan.scanner ?? 'N/A'}</span></div>
                            <div>Scanners Registered: <span className="text-slate-700">{scan.scanners_available ?? 0} Available</span></div>
                        </div>
                    </div>
                </div>
            </Card>

            {/* 4. CURRENT ACTIVITY & TARGET DETAILS (2 Column Grid) */}
            <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
                {/* CURRENT ACTIVITY */}
                <Card padding={false} className="lg:col-span-2">
                    <CardHeader
                        title="Current Execution Activity"
                        subtitle="Live execution state & active tasks"
                        icon={<Radio className="w-4 h-4 text-brand-500 animate-pulse" />}
                    />
                    <div className="p-5">
                        {activeTask ? (
                            <div className="bg-brand-50/60 border border-brand-200/80 rounded-xl p-4 space-y-3">
                                <div className="flex items-center justify-between">
                                    <div className="flex items-center gap-2">
                                        <span className="w-2.5 h-2.5 rounded-full bg-brand-500 animate-ping" />
                                        <span className="text-xs font-bold text-brand-900 uppercase tracking-wider font-mono">
                                            {activeTask.type === 'agent.observe_env' ? 'OBSERVING ENVIRONMENT' : activeTask.type}
                                        </span>
                                    </div>
                                    <span className="text-[10px] font-mono bg-brand-100 text-brand-800 font-bold px-2 py-0.5 rounded">
                                        Processing
                                    </span>
                                </div>
                                <p className="text-xs text-brand-700 font-mono">
                                    Target: {activeTask.target ?? 'N/A'}
                                </p>
                                <div className="grid grid-cols-3 gap-3 pt-2 text-[11px] border-t border-brand-200/60 text-brand-800 font-mono">
                                    <div><span className="text-brand-600 block">Task ID:</span> {activeTask.id.slice(0, 8)}</div>
                                    <div><span className="text-brand-600 block">Started:</span> {formatRelativeTime(activeTask.created_at)}</div>
                                    <div><span className="text-brand-600 block">Duration:</span> {worker.duration_seconds !== null ? `${worker.duration_seconds}s` : 'Just started'}</div>
                                </div>
                            </div>
                        ) : (
                            <div className="bg-slate-50 border border-slate-200 rounded-xl p-6 text-center space-y-2">
                                <div className="w-8 h-8 rounded-full bg-slate-200/70 flex items-center justify-center mx-auto text-slate-500">
                                    <Clock size={16} />
                                </div>
                                <h4 className="text-xs font-bold text-slate-800 uppercase tracking-wider font-mono">IDLE — WAITING FOR NEXT OBSERVATION</h4>
                                <p className="text-[11px] text-slate-500 max-w-md mx-auto">
                                    The Agent worker is active and idle. The scheduler enqueues environment observation tasks every minute.
                                </p>
                                {obs.last_completed_at && (
                                    <div className="pt-2 text-[11px] font-mono text-slate-600">
                                        Last completed: <strong className="text-slate-800">{formatRelativeTime(obs.last_completed_at)}</strong>
                                    </div>
                                )}
                            </div>
                        )}
                    </div>
                </Card>

                {/* OBSERVATION TARGET CARD */}
                <Card padding={false} className="lg:col-span-1">
                    <CardHeader
                        title="Observation Target"
                        subtitle="Environment security monitoring target"
                        icon={<FileCode2 className="w-4 h-4 text-brand-500" />}
                    />
                    <div className="p-5 space-y-3.5 text-xs">
                        <div className="bg-slate-50 border border-slate-200 rounded-lg p-3 font-mono">
                            <span className="text-[10px] font-bold text-slate-400 uppercase block">Active Target File</span>
                            <span className="font-bold text-slate-800 text-sm block mt-0.5">{obs.target ?? 'N/A'}</span>
                        </div>

                        <div className="space-y-2 font-mono text-[11px]">
                            <div className="flex items-center justify-between border-b border-slate-100 pb-2">
                                <span className="text-slate-500">Status</span>
                                <span className={`font-bold uppercase ${obs.stale ? 'text-amber-600' : 'text-emerald-600'}`}>
                                    ● {obs.stale ? 'Monitoring Stale' : 'Monitoring Active'}
                                </span>
                            </div>
                            <div className="flex items-center justify-between border-b border-slate-100 pb-2">
                                <span className="text-slate-500">File State</span>
                                <span className="font-medium text-slate-700">{obs.exists ? 'Exists / Readable' : 'Missing'}</span>
                            </div>
                            <div className="flex items-center justify-between border-b border-slate-100 pb-2">
                                <span className="text-slate-500">Last Observation</span>
                                <span className="font-bold text-slate-800">{formatRelativeTime(obs.last_completed_at)}</span>
                            </div>
                            <div className="flex items-center justify-between border-b border-slate-100 pb-2">
                                <span className="text-slate-500">Last Modified</span>
                                <span className="font-medium text-slate-700">{formatRelativeTime(obs.last_modified)}</span>
                            </div>
                            <div className="flex items-center justify-between">
                                <span className="text-slate-500">SHA-256 Hash</span>
                                <span className="font-mono text-[10px] text-slate-700 bg-slate-100 px-1.5 py-0.5 rounded">
                                    {obs.last_hash || 'Tracking...'}
                                </span>
                            </div>
                        </div>
                    </div>
                </Card>
            </div>

            {/* 5. METRICS CARDS */}
            <div className="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3">
                {[
                    { label: 'Observations', value: metrics.observations ?? 0, icon: Eye, color: 'text-brand-600' },
                    { label: 'Scans', value: metrics.scans ?? 0, icon: Search, color: 'text-blue-600' },
                    { label: 'Findings', value: metrics.findings ?? 0, icon: ShieldAlert, color: 'text-purple-600' },
                    { label: 'Tasks Processed', value: metrics.tasks_processed ?? 0, icon: CheckSquare, color: 'text-emerald-600' },
                    { label: 'Open Findings', value: metrics.open_findings ?? 0, icon: AlertTriangle, color: 'text-amber-600' },
                    { label: 'Regressions', value: metrics.regressions ?? 0, icon: XCircle, color: 'text-rose-600' },
                ].map((kpi, idx) => {
                    const Icon = kpi.icon;
                    return (
                        <div key={idx} className="bg-white border border-slate-200 rounded-xl p-3.5 shadow-sm">
                            <div className="flex items-center justify-between text-slate-400 mb-1.5">
                                <span className="text-[10px] font-bold uppercase tracking-wider text-slate-500">{kpi.label}</span>
                                <Icon size={14} className={kpi.color} />
                            </div>
                            <span className="text-xl font-extrabold text-slate-900 tracking-tight font-mono">{kpi.value}</span>
                        </div>
                    );
                })}
            </div>

            {/* 6. RECENT SCANS SECTION */}
            <Card padding={false}>
                <CardHeader
                    title="Recent Scan Executions"
                    subtitle="Security scans executed by TrustNode Agent and TrustNode Scanners"
                    icon={<Search className="w-4 h-4 text-brand-500" />}
                />
                <div className="overflow-x-auto">
                    {(!telemetry?.recent_scans || telemetry.recent_scans.length === 0) ? (
                        <div className="p-8 text-center text-xs text-slate-400">No security scan executions recorded yet.</div>
                    ) : (
                        <table className="w-full text-left text-xs">
                            <thead className="bg-slate-50 border-b border-slate-200 text-slate-500 font-semibold uppercase text-[10px]">
                                <tr>
                                    <th className="px-4 py-2.5">Scan Name</th>
                                    <th className="px-4 py-2.5">Engine / Scanner</th>
                                    <th className="px-4 py-2.5">Target</th>
                                    <th className="px-4 py-2.5">Status</th>
                                    <th className="px-4 py-2.5">Findings</th>
                                    <th className="px-4 py-2.5">Executed</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100 font-mono">
                                {telemetry.recent_scans.map((s) => (
                                    <tr key={s.id} className="hover:bg-slate-50/80 transition-colors">
                                        <td className="px-4 py-2.5 font-sans font-semibold text-slate-900">
                                            {s.name}
                                        </td>
                                        <td className="px-4 py-2.5 text-slate-600">
                                            {s.engine || 'N/A'}
                                        </td>
                                        <td className="px-4 py-2.5 text-slate-700 font-bold">
                                            {s.target || 'N/A'}
                                        </td>
                                        <td className="px-4 py-2.5">
                                            <span className={`inline-flex items-center gap-1 px-2 py-0.5 rounded text-[10px] font-bold uppercase ${
                                                s.status === 'completed' ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' :
                                                s.status === 'running' ? 'bg-brand-50 text-brand-700 border border-brand-200 animate-pulse' :
                                                s.status === 'failed' ? 'bg-rose-50 text-rose-700 border border-rose-200' : 'bg-slate-100 text-slate-600'
                                            }`}>
                                                {s.status}
                                            </span>
                                        </td>
                                        <td className="px-4 py-2.5 font-bold">
                                            {s.findings_count > 0 ? (
                                                <span className="text-rose-600">{s.findings_count} findings</span>
                                            ) : (
                                                <span className="text-slate-400">No findings</span>
                                            )}
                                        </td>
                                        <td className="px-4 py-2.5 text-slate-400 font-sans">
                                            {formatRelativeTime(s.completed_at || s.started_at)}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    )}
                </div>
            </Card>

            {/* 7. REAL ACTIVITY TIMELINE & RECENT TASKS */}
            <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
                {/* REAL ACTIVITY TIMELINE */}
                <Card padding={false} className="lg:col-span-1">
                    <CardHeader
                        title="Agent Activity Timeline"
                        subtitle="Real-time security events"
                        icon={<Activity className="w-4 h-4 text-brand-500" />}
                    />
                    <div className="p-5">
                        {timeline.length === 0 ? (
                            <div className="text-center py-6 space-y-1">
                                <p className="text-xs text-slate-600 font-medium">No activity events recorded yet.</p>
                                <p className="text-[11px] text-slate-400">Activity timeline records task, scan, and finding lifecycle events.</p>
                            </div>
                        ) : (
                            <div className="space-y-4 relative before:absolute before:left-2 before:top-2 before:bottom-2 before:w-0.5 before:bg-slate-100">
                                {timeline.map((act) => (
                                    <div key={act.id} className="flex items-start gap-3 relative pl-1">
                                        <div className="w-3.5 h-3.5 rounded-full border-2 border-white bg-brand-500 shrink-0 mt-0.5 z-10" />
                                        <div className="space-y-0.5">
                                            <p className="text-xs font-medium text-slate-800">
                                                {act.event}
                                            </p>
                                            <p className="text-[11px] text-slate-500 truncate max-w-[200px]">
                                                {act.details}
                                            </p>
                                            <span className="text-[10px] text-slate-400 font-mono block">
                                                {formatRelativeTime(act.timestamp || act.created_at)}
                                            </span>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        )}
                    </div>
                </Card>

                {/* RECENT TASKS */}
                <Card padding={false} className="lg:col-span-2">
                    <CardHeader
                        title="Recent Task Execution Log"
                        subtitle="Agent execution queue history"
                        icon={<ListFilter className="w-4 h-4 text-brand-500" />}
                    />
                    <div className="overflow-x-auto">
                        {recentTasks.length === 0 ? (
                            <div className="p-8 text-center text-xs text-slate-400">No tasks processed yet.</div>
                        ) : (
                            <table className="w-full text-left text-xs">
                                <thead className="bg-slate-50 border-b border-slate-200 text-slate-500 font-semibold uppercase text-[10px]">
                                    <tr>
                                        <th className="px-4 py-2.5">Task Type</th>
                                        <th className="px-4 py-2.5">Target</th>
                                        <th className="px-4 py-2.5">Status</th>
                                        <th className="px-4 py-2.5">Processed</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-slate-100 font-mono">
                                    {recentTasks.map((t) => (
                                        <tr key={t.id} className="hover:bg-slate-50/80 transition-colors">
                                            <td className="px-4 py-2.5 font-sans font-medium text-slate-800">
                                                {t.type}
                                            </td>
                                            <td className="px-4 py-2.5 text-slate-600">
                                                {t.target ?? 'N/A'}
                                            </td>
                                            <td className="px-4 py-2.5">
                                                <span className={`inline-flex items-center gap-1 px-2 py-0.5 rounded text-[10px] font-bold uppercase ${
                                                    t.status === 'completed' ? 'bg-emerald-50 text-emerald-700' :
                                                    t.status === 'processing' ? 'bg-brand-50 text-brand-700' :
                                                    t.status === 'failed' ? 'bg-rose-50 text-rose-700' : 'bg-slate-100 text-slate-600'
                                                }`}>
                                                    {t.status}
                                                </span>
                                            </td>
                                            <td className="px-4 py-2.5 text-slate-400 font-sans">
                                                {formatRelativeTime(t.updated_at || t.created_at)}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        )}
                    </div>
                </Card>
            </div>

            {/* 7. RECENT FINDINGS */}
            <Card padding={false}>
                <CardHeader
                    title="Recent Agent Findings"
                    subtitle="Security vulnerabilities identified by TrustNode Agent"
                    icon={<ShieldAlert className="w-4 h-4 text-brand-500" />}
                />

                {findings.length === 0 ? (
                    <div className="p-8 text-center border-b border-slate-100">
                        <div className="w-10 h-10 rounded-full bg-slate-50 text-slate-400 flex items-center justify-center mx-auto mb-2.5">
                            <ListFilter className="w-5 h-5" />
                        </div>
                        <p className="text-xs font-bold text-slate-800">No findings recorded</p>
                        <p className="text-[11px] text-slate-500 mt-0.5">
                            Findings will appear here after the Agent executes a scanner.
                        </p>
                    </div>
                ) : (
                    <div className="divide-y divide-slate-100">
                        {findings.map((finding) => (
                            <Link
                                key={finding.id}
                                to={`/findings/${finding.id}`}
                                className="flex items-center justify-between p-4 hover:bg-slate-50 transition-colors gap-4"
                            >
                                <div className="flex items-center gap-3.5 min-w-0">
                                    <SeverityBadge severity={finding.severity} />
                                    <div className="min-w-0">
                                        <div className="flex items-center gap-2">
                                            <span className="text-[11px] font-mono font-bold text-slate-400">{finding.finding_id}</span>
                                            <span className="text-xs font-bold text-slate-900 truncate">{finding.title}</span>
                                        </div>
                                        <div className="flex items-center gap-3 mt-0.5 text-[11px] text-slate-500 font-mono">
                                            <span>Scanner: {finding.scanner || 'N/A'}</span>
                                            <span>•</span>
                                            <span>Detected {formatRelativeTime(finding.created_at)}</span>
                                        </div>
                                    </div>
                                </div>
                                <div className="flex items-center gap-2 shrink-0">
                                    <MonoChip text={finding.lifecycle_status || finding.status} />
                                    <ArrowRight size={14} className="text-slate-400" />
                                </div>
                            </Link>
                        ))}
                    </div>
                )}
            </Card>
        </div>
    );
}
