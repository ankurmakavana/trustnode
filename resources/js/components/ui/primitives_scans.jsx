import React from 'react';
import {
    Activity, CheckCircle2, XCircle, Clock, Ban, Hourglass,
    Globe, Network, Server, Code2, Cloud, Container, Radar,
    Cpu, Shield, ScanLine, Zap, Terminal, Layers,
} from 'lucide-react';

// ─── Status Badge ─────────────────────────────────────────────────────────────

const STATUS_CONFIG = {
    running:   { label: 'Running',   dot: 'bg-blue-500 animate-pulse',  cls: 'bg-blue-50 text-blue-700 border-blue-200',   Icon: Activity    },
    queued:    { label: 'Queued',    dot: 'bg-amber-400',               cls: 'bg-amber-50 text-amber-700 border-amber-200', Icon: Hourglass   },
    scheduled: { label: 'Scheduled', dot: 'bg-violet-400',              cls: 'bg-violet-50 text-violet-700 border-violet-200', Icon: Clock    },
    completed: { label: 'Completed', dot: 'bg-emerald-500',             cls: 'bg-emerald-50 text-emerald-700 border-emerald-200', Icon: CheckCircle2 },
    failed:    { label: 'Failed',    dot: 'bg-rose-500',                cls: 'bg-rose-50 text-rose-700 border-rose-200',     Icon: XCircle    },
    cancelled: { label: 'Cancelled', dot: 'bg-slate-400',               cls: 'bg-slate-100 text-slate-600 border-slate-200', Icon: Ban        },
};

export function ScanStatusBadge({ status }) {
    const key    = String(status ?? '').toLowerCase();
    const config = STATUS_CONFIG[key] ?? { label: status, dot: 'bg-slate-400', cls: 'bg-slate-100 text-slate-600 border-slate-200' };
    return (
        <span className={`inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md text-xs font-semibold border ${config.cls}`}>
            <span className={`w-1.5 h-1.5 rounded-full shrink-0 ${config.dot}`} />
            {config.label}
        </span>
    );
}

// ─── Type Badge ───────────────────────────────────────────────────────────────

const TYPE_CONFIG = {
    web_application:    { label: 'Web App',        cls: 'bg-violet-50 text-violet-700 border-violet-200',   Icon: Globe       },
    network_ip:         { label: 'Network',        cls: 'bg-sky-50 text-sky-700 border-sky-200',            Icon: Network     },
    port_discovery:     { label: 'Port Discovery', cls: 'bg-indigo-50 text-indigo-700 border-indigo-200',   Icon: Radar       },
    api_vulnerability:  { label: 'API',            cls: 'bg-pink-50 text-pink-700 border-pink-200',         Icon: Code2       },
    cloud_infrastructure:{ label: 'Cloud',         cls: 'bg-cyan-50 text-cyan-700 border-cyan-200',         Icon: Cloud       },
    container_audit:    { label: 'Container',      cls: 'bg-teal-50 text-teal-700 border-teal-200',         Icon: Container   },
    internal_network:   { label: 'Internal',       cls: 'bg-slate-100 text-slate-700 border-slate-200',     Icon: Server      },
    external_surface:   { label: 'External',       cls: 'bg-orange-50 text-orange-700 border-orange-200',   Icon: Layers      },
};

export function ScanTypeBadge({ type }) {
    const key    = String(type ?? '').toLowerCase();
    const config = TYPE_CONFIG[key] ?? { label: type, cls: 'bg-slate-100 text-slate-700 border-slate-200', Icon: ScanLine };
    const { label, cls, Icon } = config;
    return (
        <span className={`inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-[11px] font-semibold border ${cls}`}>
            <Icon size={10} strokeWidth={2.5} />
            {label}
        </span>
    );
}

// ─── Engine Badge ─────────────────────────────────────────────────────────────

const ENGINE_CONFIG = {
    nmap:      { label: 'Nmap',      cls: 'bg-purple-50 text-purple-700 border-purple-200',  Icon: Terminal  },
    owasp_zap: { label: 'OWASP ZAP', cls: 'bg-orange-50 text-orange-700 border-orange-200',  Icon: Shield    },
    nuclei:    { label: 'Nuclei',    cls: 'bg-blue-50 text-blue-700 border-blue-200',         Icon: Zap       },
    nikto:     { label: 'Nikto',     cls: 'bg-rose-50 text-rose-700 border-rose-200',         Icon: ScanLine  },
    trivy:     { label: 'Trivy',     cls: 'bg-teal-50 text-teal-700 border-teal-200',         Icon: Layers    },
    nessus:    { label: 'Nessus',    cls: 'bg-emerald-50 text-emerald-700 border-emerald-200', Icon: Activity },
    custom:    { label: 'Custom',    cls: 'bg-slate-100 text-slate-700 border-slate-200',     Icon: Cpu       },
};

export function ScanEngineBadge({ engine }) {
    const key    = String(engine ?? '').toLowerCase();
    const config = ENGINE_CONFIG[key] ?? { label: engine, cls: 'bg-slate-100 text-slate-700 border-slate-200', Icon: Cpu };
    const { label, cls, Icon } = config;
    return (
        <span className={`inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-[11px] font-semibold border ${cls}`}>
            <Icon size={10} strokeWidth={2.5} />
            {label}
        </span>
    );
}

// ─── Progress Bar ─────────────────────────────────────────────────────────────

export function ProgressBar({ progress = 0, status, compact = false }) {
    const pct = Math.min(100, Math.max(0, Number(progress) || 0));

    let barCls = 'bg-brand-500';
    if (status === 'running')   barCls = 'bg-blue-500';
    if (status === 'completed') barCls = 'bg-emerald-500';
    if (status === 'failed')    barCls = 'bg-rose-500';
    if (status === 'cancelled') barCls = 'bg-slate-400';
    if (status === 'queued')    barCls = 'bg-amber-400';

    const isAnimated = status === 'running';

    if (compact) {
        return (
            <div className="w-full flex items-center gap-2">
                <div className="flex-1 h-1.5 bg-slate-100 rounded-full overflow-hidden border border-slate-200">
                    <div
                        className={`h-full rounded-full transition-all duration-700 ${barCls} ${isAnimated ? 'animate-pulse' : ''}`}
                        style={{ width: `${pct}%` }}
                    />
                </div>
                <span className="text-[10px] font-bold text-slate-500 tabular-nums min-w-[26px] text-right">{pct}%</span>
            </div>
        );
    }

    return (
        <div className="w-full space-y-1.5">
            <div className="flex items-center justify-between">
                <span className="text-[10px] font-semibold text-slate-400 uppercase tracking-wide">Progress</span>
                <span className="text-xs font-bold text-slate-700 tabular-nums">{pct}%</span>
            </div>
            <div className="h-2.5 bg-slate-100 rounded-full overflow-hidden border border-slate-200">
                <div
                    className={`h-full rounded-full transition-all duration-700 ${barCls} ${isAnimated ? 'animate-pulse' : ''}`}
                    style={{ width: `${pct}%` }}
                />
            </div>
        </div>
    );
}

// ─── Skeleton Row ─────────────────────────────────────────────────────────────

export function ScanRowSkeleton() {
    return (
        <tr className="border-b border-slate-100">
            {[1, 2, 3, 4, 5, 6, 7, 8, 9].map(i => (
                <td key={i} className="px-4 py-4">
                    <div className="h-3.5 bg-slate-100 rounded animate-pulse" style={{ width: i === 1 ? '70%' : i === 6 ? '90%' : '50%' }} />
                </td>
            ))}
        </tr>
    );
}
