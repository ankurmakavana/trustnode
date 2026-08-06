import React from 'react';

export function ScanStatusBadge({ status }) {
    const s = String(status).toLowerCase();
    switch (s) {
        case 'queued':
            return (
                <span className="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md text-xs font-semibold bg-slate-100 text-slate-700 border border-slate-200">
                    <span className="w-1.5 h-1.5 rounded-full bg-slate-400" />
                    Queued
                </span>
            );
        case 'running':
            return (
                <span className="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md text-xs font-semibold bg-blue-50 text-blue-700 border border-blue-200">
                    <span className="w-1.5 h-1.5 rounded-full bg-blue-500 animate-pulse" />
                    Running
                </span>
            );
        case 'completed':
            return (
                <span className="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md text-xs font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200">
                    <span className="w-1.5 h-1.5 rounded-full bg-emerald-500" />
                    Completed
                </span>
            );
        case 'failed':
            return (
                <span className="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md text-xs font-semibold bg-rose-50 text-rose-700 border border-rose-200">
                    <span className="w-1.5 h-1.5 rounded-full bg-rose-500" />
                    Failed
                </span>
            );
        case 'cancelled':
            return (
                <span className="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md text-xs font-semibold bg-amber-50 text-amber-700 border border-amber-200">
                    <span className="w-1.5 h-1.5 rounded-full bg-amber-500" />
                    Cancelled
                </span>
            );
        default:
            return (
                <span className="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md text-xs font-semibold bg-slate-50 text-slate-600 border border-slate-200">
                    {status}
                </span>
            );
    }
}

export function ScanTypeBadge({ type }) {
    const t = String(type).toLowerCase();
    let label = type;
    let style = 'bg-slate-100 text-slate-800 border-slate-200';

    if (t === 'web_application') {
        label = 'Web App';
        style = 'bg-violet-50 text-violet-700 border-violet-200';
    } else if (t === 'network_ip') {
        label = 'Network IP';
        style = 'bg-sky-50 text-sky-700 border-sky-200';
    } else if (t === 'port_discovery') {
        label = 'Port Discovery';
        style = 'bg-indigo-50 text-indigo-700 border-indigo-200';
    } else if (t === 'api_vulnerability') {
        label = 'API Vuln';
        style = 'bg-pink-50 text-pink-700 border-pink-200';
    } else if (t === 'container_audit') {
        label = 'Container Audit';
        style = 'bg-teal-50 text-teal-700 border-teal-200';
    } else if (t === 'cloud_infrastructure') {
        label = 'Cloud Infra';
        style = 'bg-cyan-50 text-cyan-700 border-cyan-200';
    }

    return (
        <span className={`inline-flex items-center px-2 py-0.5 rounded-md text-xs font-medium border ${style}`}>
            {label}
        </span>
    );
}

export function ScanEngineBadge({ engine }) {
    const e = String(engine).toLowerCase();
    let label = engine;
    let style = 'bg-slate-100 text-slate-800 border-slate-200';

    if (e === 'nmap') {
        label = 'Nmap';
        style = 'bg-purple-50 text-purple-700 border-purple-200';
    } else if (e === 'owasp_zap') {
        label = 'OWASP ZAP';
        style = 'bg-orange-50 text-orange-700 border-orange-200';
    } else if (e === 'nuclei') {
        label = 'Nuclei';
        style = 'bg-blue-50 text-blue-700 border-blue-200';
    } else if (e === 'trivy') {
        label = 'Trivy';
        style = 'bg-teal-50 text-teal-700 border-teal-200';
    } else if (e === 'nessus') {
        label = 'Nessus';
        style = 'bg-emerald-50 text-emerald-700 border-emerald-200';
    }

    return (
        <span className={`inline-flex items-center px-2 py-0.5 rounded-md text-xs font-semibold border ${style}`}>
            {label}
        </span>
    );
}

export function ProgressBar({ progress, status }) {
    let barColor = 'bg-brand-600';
    if (status === 'failed') barColor = 'bg-rose-500';
    if (status === 'cancelled') barColor = 'bg-amber-500';
    if (status === 'completed') barColor = 'bg-emerald-500';

    return (
        <div className="w-full flex items-center gap-3">
            <div className="flex-1 h-2 bg-slate-100 rounded-full overflow-hidden border border-slate-200">
                <div 
                    className={`h-full rounded-full transition-all duration-500 ${barColor}`} 
                    style={{ width: `${Math.min(100, Math.max(0, progress))}%` }}
                />
            </div>
            <span className="text-xs font-semibold text-slate-500 min-w-[28px] text-right">
                {progress}%
            </span>
        </div>
    );
}
