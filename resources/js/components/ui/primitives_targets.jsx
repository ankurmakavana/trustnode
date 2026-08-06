import React, { useState, useRef, useEffect } from 'react';
import { 
    ChevronDown, ShieldAlert, ArrowLeft, Calendar, 
    User, Activity, FileText, Server, MoreHorizontal 
} from 'lucide-react';
import { Badge } from './primitives';

// ── TargetTypeBadge ───────────────────────────────────────────────────────────

const targetTypeLabelMap = {
    domain:             { text: 'Domain',             variant: 'indigo'  },
    ip_address:         { text: 'IP Address',         variant: 'emerald' },
    cidr_range:         { text: 'CIDR Range',         variant: 'violet'  },
    url:                { text: 'URL',                variant: 'fuchsia' },
    api_endpoint:       { text: 'API Endpoint',       variant: 'rose'    },
    mobile_application: { text: 'Mobile Application', variant: 'amber'   },
    cloud_resource:     { text: 'Cloud Resource',     variant: 'cyan'    },
};

export function TargetTypeBadge({ type }) {
    const cfg = targetTypeLabelMap[type?.toLowerCase()] || { text: type, variant: 'slate' };
    return (
        <Badge variant={cfg.variant}>
            {cfg.text}
        </Badge>
    );
}

// ── TargetEnvironmentBadge ────────────────────────────────────────────────────

const targetEnvLabelMap = {
    production:  { text: 'Production',  variant: 'red'     },
    staging:     { text: 'Staging',     variant: 'orange'  },
    development: { text: 'Development', variant: 'blue'    },
    internal:    { text: 'Internal',    variant: 'slate'   },
};

export function TargetEnvironmentBadge({ env }) {
    const cfg = targetEnvLabelMap[env?.toLowerCase()] || { text: env, variant: 'slate' };
    return (
        <Badge variant={cfg.variant}>
            {cfg.text}
        </Badge>
    );
}

// ── TargetCriticalityBadge ────────────────────────────────────────────────────

const targetCritLabelMap = {
    critical: { text: 'Critical', variant: 'red'     },
    high:     { text: 'High',     variant: 'orange'  },
    medium:   { text: 'Medium',   variant: 'amber'   },
    low:      { text: 'Low',      variant: 'blue'    },
};

export function TargetCriticalityBadge({ criticality }) {
    const cfg = targetCritLabelMap[criticality?.toLowerCase()] || { text: criticality, variant: 'slate' };
    return (
        <Badge variant={cfg.variant} className="font-semibold">
            {cfg.text}
        </Badge>
    );
}

// ── TargetStatusBadge ─────────────────────────────────────────────────────────

const targetStatusLabelMap = {
    active:       { text: 'Active',       variant: 'emerald' },
    inactive:     { text: 'Inactive',     variant: 'slate'   },
    under_review: { text: 'Under Review', variant: 'violet'  },
};

export function TargetStatusBadge({ status }) {
    const cfg = targetStatusLabelMap[status?.toLowerCase()] || { text: status, variant: 'slate' };
    return (
        <Badge variant={cfg.variant}>
            {cfg.text}
        </Badge>
    );
}

// ── TargetDetailLayout ──────────────────────────────────────────────────────────

export function TargetDetailLayout({ children, breadcrumbs }) {
    return (
        <div className="flex flex-col gap-6 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
            {/* Breadcrumb / Navigation bar */}
            <nav className="flex items-center gap-2 text-xs font-semibold text-slate-500" aria-label="Breadcrumb">
                {breadcrumbs.map((crumb, idx) => (
                    <React.Fragment key={idx}>
                        {idx > 0 && <span className="text-slate-300">/</span>}
                        {crumb.href ? (
                            <button
                                onClick={crumb.onClick}
                                className="hover:text-slate-800 transition-colors focus:outline-none focus-visible:underline"
                            >
                                {crumb.label}
                            </button>
                        ) : (
                            <span className="text-slate-800 font-bold">{crumb.label}</span>
                        )}
                    </React.Fragment>
                ))}
            </nav>
            {children}
        </div>
    );
}

// ── DetailHero ─────────────────────────────────────────────────────────────────

export function DetailHero({ title, subtitle, badges, actions, kpiCards }) {
    return (
        <div className="flex flex-col gap-6 bg-white border border-slate-200/80 rounded-xl shadow-sm p-6">
            <div className="flex flex-col lg:flex-row lg:items-center justify-between gap-6">
                {/* Title & Badges */}
                <div className="min-w-0 flex-1">
                    <div className="flex flex-wrap items-center gap-3">
                        <h1 className="text-xl font-bold text-slate-900 tracking-tight sm:text-2xl truncate">{title}</h1>
                        <div className="flex flex-wrap gap-1.5">
                            {badges.map((b, idx) => (
                                <span key={idx}>{b}</span>
                            ))}
                        </div>
                    </div>
                    {subtitle && (
                        <p className="text-xs text-slate-500 mt-2 font-medium">
                            UUID: <span className="font-mono text-slate-400 select-all">{subtitle}</span>
                        </p>
                    )}
                </div>

                {/* Top Right Actions */}
                <div className="flex items-center gap-2.5 shrink-0">
                    {actions}
                </div>
            </div>

            {/* KPI Metrics Strip */}
            {kpiCards && kpiCards.length > 0 && (
                <div className="grid grid-cols-2 md:grid-cols-4 gap-4 border-t border-slate-100 pt-6">
                    {kpiCards.map((card, idx) => (
                        <MetricCard
                            key={idx}
                            label={card.label}
                            value={card.value}
                            subtext={card.subtext}
                            trend={card.trend}
                            color={card.color}
                            icon={card.icon}
                        />
                    ))}
                </div>
            )}
        </div>
    );
}

// ── DetailCard ─────────────────────────────────────────────────────────────────

export function DetailCard({ title, subtitle, children, actions, className = '' }) {
    return (
        <div className={`bg-white border border-slate-200/80 rounded-xl shadow-sm overflow-hidden ${className}`}>
            {(title || subtitle || actions) && (
                <div className="px-5 py-4 border-b border-slate-100 flex items-center justify-between gap-4">
                    <div>
                        {title && <h3 className="text-sm font-bold text-slate-900 tracking-tight">{title}</h3>}
                        {subtitle && <p className="text-[11px] text-slate-400 mt-0.5">{subtitle}</p>}
                    </div>
                    {actions && <div className="shrink-0">{actions}</div>}
                </div>
            )}
            <div className="p-5">{children}</div>
        </div>
    );
}

// ── InfoGrid ───────────────────────────────────────────────────────────────────

export function InfoGrid({ items, columns = 2 }) {
    const gridCols = columns === 3 ? 'grid-cols-1 sm:grid-cols-3' : 'grid-cols-1 sm:grid-cols-2';
    return (
        <div className={`grid ${gridCols} gap-y-4 gap-x-6`}>
            {items.map((item, idx) => (
                <div key={idx} className="min-w-0">
                    <span className="block text-[10px] font-bold text-slate-400 uppercase tracking-wider select-none">
                        {item.label}
                    </span>
                    <div className="mt-1.5 text-xs text-slate-700 font-medium">
                        {item.value}
                    </div>
                </div>
            ))}
        </div>
    );
}

// ── MetricCard ─────────────────────────────────────────────────────────────────

export function MetricCard({ label, value, subtext, trend, color = 'slate', icon: Icon }) {
    const colorMap = {
        red: 'text-red-600 bg-red-50',
        blue: 'text-blue-600 bg-blue-50',
        emerald: 'text-emerald-600 bg-emerald-50',
        amber: 'text-amber-600 bg-amber-50',
        violet: 'text-violet-600 bg-violet-50',
        slate: 'text-slate-500 bg-slate-50',
    };
    const cClass = colorMap[color] || colorMap.slate;

    return (
        <div className="flex items-center gap-3 p-3 bg-slate-50/50 border border-slate-100 rounded-lg">
            {Icon && (
                <div className={`w-8 h-8 rounded-lg flex items-center justify-center shrink-0 ${cClass}`}>
                    <Icon size={14} strokeWidth={2.5} />
                </div>
            )}
            <div className="min-w-0">
                <span className="block text-[10px] font-bold text-slate-400 uppercase tracking-widest leading-none select-none">
                    {label}
                </span>
                <span className="block text-sm font-bold text-slate-900 mt-1 leading-none">
                    {value}
                </span>
                {subtext && (
                    <span className="block text-[10px] text-slate-400 mt-1 leading-none">
                        {subtext}
                    </span>
                )}
            </div>
        </div>
    );
}

// ── Timeline ───────────────────────────────────────────────────────────────────

export function Timeline({ events }) {
    return (
        <div className="relative pl-4 border-l border-slate-100 space-y-5">
            {events.map((evt, idx) => {
                const Icon = evt.icon || Activity;
                return (
                    <div key={idx} className="relative group">
                        {/* Timeline dot */}
                        <span className="absolute -left-[21px] top-0.5 w-2.5 h-2.5 rounded-full border border-white bg-slate-300 ring-4 ring-white group-hover:bg-brand-500 transition-colors" />
                        
                        <div className="flex items-start gap-2.5 text-xs">
                            <div className="flex-1 min-w-0">
                                <p className="text-slate-600">
                                    <span className="font-semibold text-slate-900">{evt.title}</span>{' '}
                                    {evt.description}
                                </p>
                                {evt.meta && (
                                    <span className="block text-[10px] text-slate-400 mt-1">{evt.meta}</span>
                                )}
                            </div>
                        </div>
                    </div>
                );
            })}
        </div>
    );
}

// ── RelationshipCard ───────────────────────────────────────────────────────────

export function RelationshipCard({ title, icon: Icon, items, emptyText = 'No linked items' }) {
    return (
        <div className="flex flex-col gap-2 p-3.5 bg-slate-50/50 border border-slate-100 rounded-lg">
            <div className="flex items-center gap-2 text-slate-500">
                {Icon && <Icon size={13} />}
                <span className="text-[11px] font-bold uppercase tracking-wider select-none">{title}</span>
            </div>
            {items && items.length > 0 ? (
                <div className="flex flex-wrap gap-1.5 mt-1">
                    {items.map((item, idx) => (
                        <button
                            key={idx}
                            onClick={item.onClick}
                            className="inline-flex items-center gap-1.5 px-2 py-1 bg-white border border-slate-200/80 rounded-md text-[11px] font-semibold text-slate-700 hover:border-brand-500 hover:text-brand-700 transition-colors focus:outline-none"
                        >
                            {item.label}
                        </button>
                    ))}
                </div>
            ) : (
                <span className="text-xs text-slate-400 italic mt-0.5">{emptyText}</span>
            )}
        </div>
    );
}

// ── TabNavigation ──────────────────────────────────────────────────────────────

export function TabNavigation({ tabs, activeTab, onChange }) {
    return (
        <div className="border-b border-slate-200 flex gap-6 overflow-x-auto select-none" role="tablist">
            {tabs.map((tab) => {
                const isActive = tab.id === activeTab;
                return (
                    <button
                        key={tab.id}
                        role="tab"
                        aria-selected={isActive}
                        onClick={() => onChange(tab.id)}
                        className={`
                            pb-3 text-xs font-semibold border-b-2 transition-all focus:outline-none relative whitespace-nowrap
                            ${isActive 
                                ? 'border-brand-600 text-brand-600' 
                                : 'border-transparent text-slate-500 hover:text-slate-800'}
                        `}
                    >
                        {tab.label}
                        {tab.badge && (
                            <span className={`ml-1.5 px-1.5 py-0.5 rounded-full text-[9px] font-bold ${isActive ? 'bg-brand-50 text-brand-700' : 'bg-slate-100 text-slate-500'}`}>
                                {tab.badge}
                            </span>
                        )}
                    </button>
                );
            })}
        </div>
    );
}

// ── ActionDropdown ─────────────────────────────────────────────────────────────

export function ActionDropdown({ label = 'Actions', items }) {
    const [open, setOpen] = useState(false);
    const containerRef = useRef(null);

    useEffect(() => {
        const handleClick = (e) => {
            if (containerRef.current && !containerRef.current.contains(e.target)) {
                setOpen(false);
            }
        };
        document.addEventListener('mousedown', handleClick);
        return () => document.removeEventListener('mousedown', handleClick);
    }, []);

    return (
        <div className="relative inline-block text-left" ref={containerRef}>
            <button
                type="button"
                onClick={() => setOpen(!open)}
                className="inline-flex items-center gap-1.5 px-3 py-2 bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 text-xs font-semibold rounded-lg shadow-sm transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500"
            >
                {label} <ChevronDown size={12} className={`transition-transform duration-200 ${open ? 'rotate-180' : ''}`} />
            </button>

            {open && (
                <div 
                    className="absolute right-0 top-full mt-1.5 w-48 bg-white rounded-lg border border-slate-200 shadow-lg shadow-slate-100 z-50 py-1.5 animate-fade-in"
                    role="menu"
                >
                    {items.map((item, idx) => (
                        <button
                            key={idx}
                            role="menuitem"
                            onClick={() => {
                                setOpen(false);
                                if (item.onClick) item.onClick();
                            }}
                            className={`w-full flex items-center gap-2 px-3 py-2 text-left text-xs font-medium transition-colors focus:outline-none ${item.isDanger ? 'text-red-600 hover:bg-red-50' : 'text-slate-700 hover:bg-slate-50'}`}
                        >
                            {item.icon && <item.icon size={13} className={item.isDanger ? 'text-red-500' : 'text-slate-400'} />}
                            {item.label}
                        </button>
                    ))}
                </div>
            )}
        </div>
    );
}

// ── EmptyState ─────────────────────────────────────────────────────────────────

export function EmptyState({ title, description, icon: Icon }) {
    return (
        <div className="py-12 px-4 text-center border border-dashed border-slate-200 rounded-xl bg-slate-50/20 select-none animate-fade-in">
            {Icon && (
                <div className="inline-flex items-center justify-center w-10 h-10 rounded-lg bg-slate-50 text-slate-400 border border-slate-100 mb-3.5">
                    <Icon size={16} />
                </div>
            )}
            <h4 className="text-xs font-bold text-slate-800">{title}</h4>
            <p className="text-[11px] text-slate-400 mt-1 max-w-xs mx-auto leading-relaxed">{description}</p>
        </div>
    );
}
