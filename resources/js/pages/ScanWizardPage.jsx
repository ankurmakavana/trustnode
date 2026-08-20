import React, { useState, useEffect, useCallback } from 'react';
import {
    ArrowLeft, ArrowRight, Check, Loader2, AlertTriangle,
    Target, Layers, Globe, Search, Shield, Zap, Clock,
    Play, RotateCcw, AlertCircle, ScanLine, CheckCircle2,
    Server, Wifi, Package, Cloud, Network, Eye, Lock, GitBranch,
} from 'lucide-react';
import axios from 'axios';

// ─── Constants ────────────────────────────────────────────────────────────────

const SCAN_TYPES = [
    { value: 'repository',          label: 'Repository Scan',      icon: GitBranch, desc: 'SAST, dependency scanning, and secret detection' },
    { value: 'network_ip',          label: 'Infrastructure Scan',  icon: Network, desc: 'Network host discovery and vulnerability scan' },
    { value: 'database',            label: 'Database Security Scan',icon: Server,  desc: 'MySQL/MariaDB security and misconfiguration checks' },
    { value: 'web_application',     label: 'Web Application',      icon: Globe,   desc: 'OWASP Top 10, injection, XSS, auth flaws' },
    { value: 'port_discovery',      label: 'Port Discovery',       icon: Wifi,    desc: 'TCP/UDP port enumeration and service fingerprinting' },
    { value: 'api_vulnerability',   label: 'API Vulnerability',    icon: Zap,     desc: 'REST/GraphQL endpoint fuzzing and schema validation' },
    { value: 'container_audit',     label: 'Container Audit',      icon: Package, desc: 'Docker/OCI image CVE scanning and config review' },
    { value: 'cloud_infrastructure',label: 'Cloud Infrastructure', icon: Cloud,   desc: 'AWS/GCP/Azure misconfiguration and compliance checks' },
    { value: 'internal_network',    label: 'Internal Network',     icon: Server,  desc: 'Internal segment lateral movement and exposure' },
    { value: 'external_surface',    label: 'External Surface',     icon: Eye,     desc: 'Attack surface mapping from external perspective' },
];

const SCAN_ENGINES = [
    { value: 'nmap',             label: 'Nmap',             desc: 'Network discovery & security auditing' },
    { value: 'database_scanner', label: 'Database Scanner', desc: 'TrustNode native database security engine' },
    { value: 'owasp_zap',        label: 'OWASP ZAP',        desc: 'Web app security scanner from OWASP' },
    { value: 'nuclei',           label: 'Nuclei',           desc: 'Fast, template-based vulnerability scanner' },
    { value: 'nikto',            label: 'Nikto',            desc: 'Web server misconfiguration scanner' },
    { value: 'trivy',            label: 'Trivy',            desc: 'Container and IaC vulnerability scanner' },
    { value: 'nessus',           label: 'Nessus',           desc: 'Comprehensive commercial vulnerability scanner' },
    { value: 'custom',           label: 'Custom',           desc: 'Custom engine via API or integration' },
];

const PROFILES = [
    { value: 'quick',        label: 'Quick',         icon: Zap,          duration: '~5 min',   desc: 'Surface-level, fast — ideal for CI/CD pipelines' },
    { value: 'standard',     label: 'Standard',      icon: Shield,       duration: '~30 min',  desc: 'Balanced depth and coverage — recommended for most scans' },
    { value: 'full',         label: 'Full',           icon: Search,       duration: '~2 hrs',   desc: 'Deep scan, all vectors, active exploitation attempts' },
    { value: 'authenticated',label: 'Authenticated',  icon: Lock,         duration: '~45 min',  desc: 'Scan with credentials to uncover post-auth vulnerabilities' },
    { value: 'compliance',   label: 'Compliance',     icon: CheckCircle2, duration: '~1 hr',    desc: 'PCI-DSS, SOC 2, ISO 27001 alignment checks' },
    { value: 'custom',       label: 'Custom',         icon: RotateCcw,   duration: 'Variable', desc: 'Define your own scan parameters and policies' },
];

const SCHEDULE_OPTIONS = [
    { value: 'now',     label: 'Run Now',     cron: null,        desc: 'Queue immediately after creation' },
    { value: 'manual',  label: 'Manual',      cron: null,        desc: 'Start manually from the Scans dashboard' },
    { value: 'daily',   label: 'Daily',       cron: '0 0 * * *', desc: 'Every day at midnight UTC' },
    { value: 'weekly',  label: 'Weekly',      cron: '0 0 * * 0', desc: 'Every Sunday at midnight UTC' },
    { value: 'monthly', label: 'Monthly',     cron: '0 0 1 * *', desc: 'First day of every month' },
    { value: 'custom',  label: 'Custom Cron', cron: null,        desc: 'Enter a custom cron expression' },
];

const SCOPE_OPTIONS = [
    { value: 'single',    label: 'Single Target',    icon: Target, desc: 'Scan one specific host, URL, or IP address' },
    { value: 'group',     label: 'Asset Group',      icon: Layers, desc: 'Scan a defined group of related assets' },
    { value: 'workspace', label: 'Entire Workspace', icon: Globe,  desc: 'Scan all registered targets across your workspace' },
];

const STEPS = [
    { id: 1, short: 'Scope',    label: 'Scan Scope' },
    { id: 2, short: 'Target',   label: 'Target Selection' },
    { id: 3, short: 'Config',   label: 'Configuration' },
    { id: 4, short: 'Schedule', label: 'Schedule' },
    { id: 5, short: 'Review',   label: 'Review & Launch' },
];

// ─── Shared UI Helpers ────────────────────────────────────────────────────────

function FieldError({ msg }) {
    if (!msg) return null;
    const text = Array.isArray(msg) ? msg[0] : msg;
    return (
        <p className="mt-1 text-[11px] font-semibold text-rose-600 flex items-center gap-1">
            <AlertCircle size={10} strokeWidth={2.5} />{text}
        </p>
    );
}

function Label({ children, required }) {
    return (
        <label className="block text-xs font-bold text-slate-700 mb-1.5">
            {children}{required && <span className="ml-0.5 text-rose-500">*</span>}
        </label>
    );
}

function icls(hasErr) {
    return (
        'w-full border rounded-lg text-sm text-slate-800 py-2.5 px-3.5 ' +
        'focus:outline-none focus:ring-2 transition-all ' +
        (hasErr
            ? 'border-rose-400 focus:ring-rose-500/20 focus:border-rose-500 bg-rose-50/30'
            : 'border-slate-200 focus:ring-brand-500/20 focus:border-brand-400 bg-white hover:border-slate-300')
    );
}

// ─── Stepper ─────────────────────────────────────────────────────────────────

function Stepper({ step }) {
    return (
        <div className="flex items-center justify-center">
            {STEPS.map((s, idx) => {
                const done   = step > s.id;
                const active = step === s.id;
                return (
                    <React.Fragment key={s.id}>
                        <div className="flex flex-col items-center gap-1.5">
                            <div className={
                                'w-8 h-8 rounded-full flex items-center justify-center text-xs font-bold border-2 transition-all duration-300 ' +
                                (done   ? 'bg-brand-600 border-brand-600 text-white' :
                                 active ? 'bg-white border-brand-500 text-brand-600 shadow-md ring-4 ring-brand-100' :
                                          'bg-white border-slate-200 text-slate-400')
                            }>
                                {done ? <Check size={13} strokeWidth={3} /> : s.id}
                            </div>
                            <span className={
                                'text-[10px] font-semibold hidden sm:block ' +
                                (active ? 'text-brand-600' : done ? 'text-slate-600' : 'text-slate-400')
                            }>{s.short}</span>
                        </div>
                        {idx < STEPS.length - 1 && (
                            <div className={
                                'flex-1 h-0.5 mx-2 mb-5 sm:mb-6 rounded-full min-w-[20px] transition-all duration-300 ' +
                                (step > s.id ? 'bg-brand-500' : 'bg-slate-200')
                            } />
                        )}
                    </React.Fragment>
                );
            })}
        </div>
    );
}

// ─── Step 1 – Scope ───────────────────────────────────────────────────────────

function StepScope({ scope, setScope, errors }) {
    return (
        <div className="space-y-4">
            <div>
                <h2 className="text-lg font-bold text-slate-900">Scan Scope</h2>
                <p className="text-sm text-slate-500 mt-0.5">Choose what you want to scan.</p>
            </div>
            <FieldError msg={errors.scope} />
            <div className="grid grid-cols-1 sm:grid-cols-3 gap-3">
                {SCOPE_OPTIONS.map(opt => {
                    const Icon = opt.icon;
                    const sel  = scope === opt.value;
                    return (
                        <button
                            key={opt.value}
                            type="button"
                            onClick={() => setScope(opt.value)}
                            className={
                                'relative flex flex-col items-start gap-3 p-4 rounded-xl border-2 text-left transition-all duration-200 ' +
                                (sel
                                    ? 'border-brand-500 bg-brand-50/50 shadow-md ring-4 ring-brand-100'
                                    : 'border-slate-200 bg-white hover:border-slate-300 hover:shadow-sm')
                            }
                        >
                            {sel && (
                                <span className="absolute top-3 right-3 w-5 h-5 rounded-full bg-brand-500 flex items-center justify-center">
                                    <Check size={10} strokeWidth={3} className="text-white" />
                                </span>
                            )}
                            <div className={
                                'w-10 h-10 rounded-xl flex items-center justify-center border ' +
                                (sel
                                    ? 'bg-brand-100 border-brand-200 text-brand-600'
                                    : 'bg-slate-100 border-slate-200 text-slate-500')
                            }>
                                <Icon size={20} strokeWidth={1.5} />
                            </div>
                            <div>
                                <div className={'text-sm font-bold ' + (sel ? 'text-brand-700' : 'text-slate-800')}>{opt.label}</div>
                                <div className="text-[11px] text-slate-500 mt-0.5 leading-relaxed">{opt.desc}</div>
                            </div>
                        </button>
                    );
                })}
            </div>
        </div>
    );
}

// ─── Step 2 – Target ─────────────────────────────────────────────────────────

function StepTarget({ scope, target, setTarget, errors }) {
    const [list,    setList]    = useState([]);
    const [loading, setLoading] = useState(false);
    const [q,       setQ]       = useState('');

    useEffect(() => {
        if (scope !== 'single') return;
        setLoading(true);
        axios.get('/api/targets', { params: { per_page: 100 } })
            .then(r => setList(r.data.data || []))
            .catch(() => {})
            .finally(() => setLoading(false));
    }, [scope]);

    const filtered = list.filter(t =>
        !q ||
        t.name?.toLowerCase().includes(q.toLowerCase()) ||
        t.value?.toLowerCase().includes(q.toLowerCase())
    );

    if (scope === 'workspace') {
        if (target !== '__workspace__') setTarget('__workspace__');
        return (
            <div className="space-y-4">
                <div>
                    <h2 className="text-lg font-bold text-slate-900">Workspace Scope</h2>
                    <p className="text-sm text-slate-500 mt-0.5">This scan will cover all registered targets in your workspace.</p>
                </div>
                <div className="rounded-xl border border-brand-200 bg-brand-50/40 p-5 flex items-start gap-4">
                    <Globe size={24} className="text-brand-500 shrink-0 mt-0.5" strokeWidth={1.5} />
                    <div>
                        <div className="text-sm font-bold text-brand-800">Entire Workspace Selected</div>
                        <div className="text-xs text-brand-600 mt-1 leading-relaxed">
                            All active targets will be included. Ensure you have authorization for every target before proceeding.
                        </div>
                    </div>
                </div>
                <div className="rounded-xl border border-amber-200 bg-amber-50/40 p-4 flex items-start gap-3">
                    <AlertTriangle size={15} className="text-amber-500 shrink-0 mt-0.5" />
                    <p className="text-xs text-amber-800 font-medium">
                        Workspace-wide scans may take significantly longer and consume more resources.
                    </p>
                </div>
            </div>
        );
    }

    if (scope === 'group') {
        return (
            <div className="space-y-4">
                <div>
                    <h2 className="text-lg font-bold text-slate-900">Asset Group</h2>
                    <p className="text-sm text-slate-500 mt-0.5">Enter the name of the asset group to scan.</p>
                </div>
                <FieldError msg={errors.target} />
                <div>
                    <Label required>Group Identifier</Label>
                    <input
                        type="text"
                        placeholder="e.g. production-web, dmz-servers, internal-api"
                        value={target}
                        onChange={e => setTarget(e.target.value)}
                        className={icls(!!errors.target)}
                    />
                    <p className="text-[11px] text-slate-400 mt-1.5">Enter the group name or identifier as defined in your asset inventory.</p>
                </div>
            </div>
        );
    }

    return (
        <div className="space-y-4">
            <div>
                <h2 className="text-lg font-bold text-slate-900">Select Target</h2>
                <p className="text-sm text-slate-500 mt-0.5">Choose a registered target or enter a custom address.</p>
            </div>
            <FieldError msg={errors.target} />
            <div>
                <Label required>Target Address</Label>
                <input
                    type="text"
                    placeholder="e.g. 192.168.1.1, https://app.corp.internal, api.example.com"
                    value={target}
                    onChange={e => setTarget(e.target.value)}
                    className={icls(!!errors.target)}
                />
                <p className="text-[11px] text-slate-400 mt-1.5">IP address, hostname, CIDR block or URL. Or pick a registered target below.</p>
            </div>
            <div className="space-y-2">
                <div className="flex items-center justify-between">
                    <span className="text-xs font-bold text-slate-600">Registered Targets</span>
                    {loading && <Loader2 size={12} className="animate-spin text-slate-400" />}
                </div>
                <div className="relative">
                    <Search size={13} className="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none" />
                    <input
                        type="search"
                        placeholder="Search targets..."
                        value={q}
                        onChange={e => setQ(e.target.value)}
                        className="w-full pl-8 pr-3 py-2 border border-slate-200 rounded-lg text-xs bg-white focus:outline-none focus:border-brand-400 hover:border-slate-300 transition"
                    />
                </div>
                <div className="max-h-52 overflow-y-auto rounded-xl border border-slate-200 divide-y divide-slate-100">
                    {filtered.length === 0 && !loading && (
                        <div className="py-8 text-center text-xs text-slate-400">No registered targets found</div>
                    )}
                    {filtered.map(t => {
                        const active = target === t.value;
                        return (
                            <button
                                key={t.id}
                                type="button"
                                onClick={() => setTarget(t.value)}
                                className={
                                    'w-full flex items-center gap-3 px-4 py-3 text-left transition-colors ' +
                                    (active ? 'bg-brand-50 border-l-2 border-l-brand-500' : 'hover:bg-slate-50')
                                }
                            >
                                <div className="w-7 h-7 rounded-lg bg-slate-100 border border-slate-200 flex items-center justify-center shrink-0">
                                    <Target size={13} className={active ? 'text-brand-500' : 'text-slate-400'} />
                                </div>
                                <div className="flex-1 min-w-0">
                                    <div className={'text-xs font-semibold truncate ' + (active ? 'text-brand-700' : 'text-slate-800')}>{t.name}</div>
                                    <div className="text-[10px] font-mono text-slate-500 truncate">{t.value}</div>
                                </div>
                                <div className="flex flex-col items-end gap-1 shrink-0">
                                    {t.type && <span className="text-[9px] font-bold text-slate-400 uppercase tracking-wider">{t.type}</span>}
                                    {t.environment && (
                                        <span className={
                                            'text-[9px] font-bold px-1.5 py-0.5 rounded-full border ' +
                                            (t.environment === 'production' ? 'bg-rose-50 text-rose-600 border-rose-200' :
                                             t.environment === 'staging'    ? 'bg-amber-50 text-amber-600 border-amber-200' :
                                                                              'bg-slate-50 text-slate-500 border-slate-200')
                                        }>{t.environment}</span>
                                    )}
                                </div>
                                {active && <Check size={14} className="text-brand-500 shrink-0" strokeWidth={2.5} />}
                            </button>
                        );
                    })}
                </div>
            </div>
        </div>
    );
}

// ─── Step 3 – Configuration ───────────────────────────────────────────────────

function StepConfig({ form, setForm, dbCreds, setDbCreds, target, errors }) {
    // If DB type is selected, auto-select DB engine
    useEffect(() => {
        if (form.type === 'database' && form.engine !== 'database_scanner') {
            setForm(f => ({ ...f, engine: 'database_scanner' }));
        }
        if (form.type === 'database' && !dbCreds.host && target) {
            setDbCreds(c => ({ ...c, host: target }));
        }
    }, [form.type, form.engine, setForm, dbCreds.host, target, setDbCreds]);
    return (
        <div className="space-y-5">
            <div>
                <h2 className="text-lg font-bold text-slate-900">Scan Configuration</h2>
                <p className="text-sm text-slate-500 mt-0.5">Set the scan name, type, engine and intensity profile.</p>
            </div>

            <div>
                <Label required>Scan Name</Label>
                <input
                    type="text"
                    placeholder="e.g. Q3 External Surface Assessment, Production API Audit"
                    value={form.name}
                    onChange={e => setForm(f => ({ ...f, name: e.target.value }))}
                    className={icls(!!errors.name)}
                />
                <FieldError msg={errors.name} />
            </div>

            <div>
                <Label>Description</Label>
                <textarea
                    rows={2}
                    placeholder="Assessment context, objectives, scope notes..."
                    value={form.description}
                    onChange={e => setForm(f => ({ ...f, description: e.target.value }))}
                    className="w-full border border-slate-200 rounded-lg text-sm text-slate-800 py-2.5 px-3.5 focus:outline-none focus:ring-2 focus:ring-brand-500/20 focus:border-brand-400 hover:border-slate-300 transition-all resize-none"
                />
            </div>

            <div>
                <Label required>Scan Type</Label>
                <FieldError msg={errors.type} />
                <div className="grid grid-cols-2 sm:grid-cols-4 gap-2 mt-1.5">
                    {SCAN_TYPES.map(t => {
                        const Icon = t.icon;
                        const sel  = form.type === t.value;
                        return (
                            <button
                                key={t.value}
                                type="button"
                                title={t.desc}
                                onClick={() => setForm(f => ({ ...f, type: t.value }))}
                                className={
                                    'flex items-center gap-2 px-3 py-2.5 rounded-lg border-2 text-left text-xs font-semibold transition-all ' +
                                    (sel
                                        ? 'border-brand-500 bg-brand-50 text-brand-700 shadow-sm ring-2 ring-brand-100'
                                        : 'border-slate-200 bg-white text-slate-600 hover:border-slate-300 hover:bg-slate-50')
                                }
                            >
                                <Icon size={13} strokeWidth={1.75} className={sel ? 'text-brand-500 shrink-0' : 'text-slate-400 shrink-0'} />
                                <span className="truncate">{t.label}</span>
                            </button>
                        );
                    })}
                </div>
            </div>

            <div>
                <Label required>Scan Engine</Label>
                <FieldError msg={errors.engine} />
                <div className="grid grid-cols-2 sm:grid-cols-4 gap-2 mt-1.5">
                    {SCAN_ENGINES.map(e => {
                        const sel = form.engine === e.value;
                        return (
                            <button
                                key={e.value}
                                type="button"
                                title={e.desc}
                                onClick={() => setForm(f => ({ ...f, engine: e.value }))}
                                className={
                                    'px-3 py-2.5 rounded-lg border-2 text-left text-xs font-semibold transition-all ' +
                                    (sel
                                        ? 'border-brand-500 bg-brand-50 text-brand-700 shadow-sm ring-2 ring-brand-100'
                                        : 'border-slate-200 bg-white text-slate-600 hover:border-slate-300 hover:bg-slate-50')
                                }
                            >
                                <div>{e.label}</div>
                                <div className={'text-[10px] font-normal mt-0.5 truncate ' + (sel ? 'text-brand-500' : 'text-slate-400')}>
                                    {e.desc.split(',')[0]}
                                </div>
                            </button>
                        );
                    })}
                </div>
            </div>

            <div>
                <Label required>Scan Profile</Label>
                <FieldError msg={errors.profile} />
                <div className="grid grid-cols-2 sm:grid-cols-3 gap-2 mt-1.5">
                    {PROFILES.map(p => {
                        const Icon = p.icon;
                        const sel  = form.profile === p.value;
                        return (
                            <button
                                key={p.value}
                                type="button"
                                onClick={() => setForm(f => ({ ...f, profile: p.value }))}
                                className={
                                    'flex items-start gap-2 p-3 rounded-xl border-2 text-left transition-all ' +
                                    (sel
                                        ? 'border-brand-500 bg-brand-50/60 shadow-sm ring-2 ring-brand-100'
                                        : 'border-slate-200 bg-white hover:border-slate-300')
                                }
                            >
                                <Icon size={13} strokeWidth={1.75} className={'mt-0.5 shrink-0 ' + (sel ? 'text-brand-500' : 'text-slate-400')} />
                                <div>
                                    <div className={'text-xs font-bold ' + (sel ? 'text-brand-700' : 'text-slate-700')}>{p.label}</div>
                                    <div className={'text-[10px] mt-0.5 ' + (sel ? 'text-brand-500' : 'text-slate-400')}>{p.duration}</div>
                                    <div className="text-[10px] text-slate-400 mt-0.5 leading-relaxed">{p.desc}</div>
                                </div>
                            </button>
                        );
                    })}
                </div>
            </div>

            {form.type === 'database' && (
                <div className="space-y-4 p-5 bg-slate-50 border border-slate-200 rounded-xl mt-4">
                    <h3 className="text-sm font-bold text-slate-900 flex items-center gap-2">
                        <Server size={16} className="text-brand-500" />
                        Database Credentials (MySQL / MariaDB)
                    </h3>
                    <p className="text-[11px] text-slate-500">
                        Credentials are encrypted in application memory and safely destroyed after connection. They are never stored permanently.
                    </p>
                    
                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <Label required>Database Type</Label>
                            <input type="text" value="MySQL / MariaDB" disabled className={icls(false) + ' bg-slate-100 text-slate-500 opacity-80 cursor-not-allowed'} />
                        </div>
                        <div className="grid grid-cols-4 gap-2">
                            <div className="col-span-3">
                                <Label required>Host</Label>
                                <input type="text" placeholder="e.g. 192.168.1.100" value={dbCreds.host} onChange={e => setDbCreds({ ...dbCreds, host: e.target.value })} className={icls(!!errors['credentials.host'])} />
                                <FieldError msg={errors['credentials.host']} />
                            </div>
                            <div className="col-span-1">
                                <Label required>Port</Label>
                                <input type="number" placeholder="3306" value={dbCreds.port} onChange={e => setDbCreds({ ...dbCreds, port: e.target.value })} className={icls(!!errors['credentials.port'])} />
                                <FieldError msg={errors['credentials.port']} />
                            </div>
                        </div>
                    </div>

                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <Label>Database Name (Optional)</Label>
                            <input type="text" placeholder="e.g. production_db" value={dbCreds.database} onChange={e => setDbCreds({ ...dbCreds, database: e.target.value })} className={icls(!!errors['credentials.database'])} />
                            <FieldError msg={errors['credentials.database']} />
                        </div>
                        <div>
                            <Label required>Username</Label>
                            <input type="text" placeholder="e.g. db_admin" value={dbCreds.username} onChange={e => setDbCreds({ ...dbCreds, username: e.target.value })} className={icls(!!errors['credentials.username'])} autoComplete="off" />
                            <FieldError msg={errors['credentials.username']} />
                        </div>
                    </div>

                    <div>
                        <Label required>Password</Label>
                        <input type="password" placeholder="••••••••••••" value={dbCreds.password} onChange={e => setDbCreds({ ...dbCreds, password: e.target.value })} className={icls(!!errors['credentials.password'])} autoComplete="new-password" />
                        <FieldError msg={errors['credentials.password']} />
                    </div>
                </div>
            )}
        </div>
    );
}

// ─── Step 4 – Schedule ────────────────────────────────────────────────────────

function StepSchedule({ schedule, setSchedule, customCron, setCustomCron, errors }) {
    return (
        <div className="space-y-5">
            <div>
                <h2 className="text-lg font-bold text-slate-900">Scan Schedule</h2>
                <p className="text-sm text-slate-500 mt-0.5">Choose when this scan should run. You can change this later.</p>
            </div>
            <div className="grid grid-cols-2 sm:grid-cols-3 gap-3">
                {SCHEDULE_OPTIONS.map(opt => {
                    const sel = schedule === opt.value;
                    return (
                        <button
                            key={opt.value}
                            type="button"
                            onClick={() => setSchedule(opt.value)}
                            className={
                                'flex flex-col gap-1.5 p-4 rounded-xl border-2 text-left transition-all ' +
                                (sel
                                    ? 'border-brand-500 bg-brand-50/50 shadow-sm ring-4 ring-brand-100'
                                    : 'border-slate-200 bg-white hover:border-slate-300')
                            }
                        >
                            <div className={'text-xs font-bold flex items-center gap-1.5 ' + (sel ? 'text-brand-700' : 'text-slate-700')}>
                                {opt.label}
                                {sel && <Check size={11} strokeWidth={3} className="text-brand-500" />}
                            </div>
                            <div className="text-[11px] text-slate-400 leading-relaxed">{opt.desc}</div>
                            {opt.cron && (
                                <code className="text-[10px] font-mono text-slate-400 bg-slate-100 px-1.5 py-0.5 rounded">{opt.cron}</code>
                            )}
                        </button>
                    );
                })}
            </div>

            {schedule === 'custom' && (
                <div className="space-y-3 p-4 bg-slate-50 rounded-xl border border-slate-200">
                    <div>
                        <Label required>Custom Cron Expression</Label>
                        <input
                            type="text"
                            placeholder="e.g. 0 3 * * 1-5  (Weekdays at 3 AM)"
                            value={customCron}
                            onChange={e => setCustomCron(e.target.value)}
                            className={icls(!!errors.customCron) + ' font-mono'}
                        />
                        <FieldError msg={errors.customCron} />
                        <p className="text-[11px] text-slate-400 mt-1.5">
                            Standard cron: <code className="bg-slate-200 px-1 rounded text-[10px]">minute hour day month weekday</code>
                        </p>
                    </div>
                    <div className="grid grid-cols-2 sm:grid-cols-4 gap-2">
                        {[
                            { label: 'Every 6 Hours', value: '0 */6 * * *' },
                            { label: 'Weekdays 2 AM', value: '0 2 * * 1-5' },
                            { label: 'Sunday 1 AM',   value: '0 1 * * 0' },
                            { label: 'Every 15 min',  value: '*/15 * * * *' },
                        ].map(ex => (
                            <button
                                key={ex.value}
                                type="button"
                                onClick={() => setCustomCron(ex.value)}
                                className="px-2 py-1.5 text-[10px] border border-slate-200 rounded-lg hover:bg-white hover:border-brand-300 transition bg-white text-left"
                            >
                                <div className="font-bold text-slate-700">{ex.label}</div>
                                <div className="font-mono text-slate-400">{ex.value}</div>
                            </button>
                        ))}
                    </div>
                </div>
            )}

            {schedule === 'now' && (
                <div className="p-4 rounded-xl border border-emerald-200 bg-emerald-50/40 flex items-start gap-3">
                    <Play size={15} className="text-emerald-500 shrink-0 mt-0.5" strokeWidth={2} />
                    <div>
                        <div className="text-xs font-bold text-emerald-800">Immediate Execution</div>
                        <div className="text-[11px] text-emerald-700 mt-0.5">
                            This scan will be queued for immediate processing after creation.
                        </div>
                    </div>
                </div>
            )}
        </div>
    );
}

// ─── Step 5 – Review ─────────────────────────────────────────────────────────

function ReviewRow({ label, value, mono, bold }) {
    return (
        <div className="flex items-start gap-4 px-4 py-3">
            <span className="text-[11px] font-bold text-slate-400 uppercase tracking-wider w-24 shrink-0 mt-0.5">{label}</span>
            <span className={
                'text-sm flex-1 min-w-0 break-all ' +
                (bold ? 'font-bold text-slate-900 ' : 'text-slate-700 ') +
                (mono ? 'font-mono text-xs' : '')
            }>{value}</span>
        </div>
    );
}

function StepReview({ scope, target, form, schedule, customCron }) {
    const scopeOpt    = SCOPE_OPTIONS.find(s => s.value === scope);
    const typeOpt     = SCAN_TYPES.find(t => t.value === form.type);
    const engineOpt   = SCAN_ENGINES.find(e => e.value === form.engine);
    const profileOpt  = PROFILES.find(p => p.value === form.profile);
    const scheduleOpt = SCHEDULE_OPTIONS.find(s => s.value === schedule);

    const cronDisplay =
        schedule === 'custom' ? customCron :
        schedule === 'now'    ? 'Run immediately after creation' :
        schedule === 'manual' ? 'Manual trigger only' :
        (scheduleOpt?.cron || '');

    return (
        <div className="space-y-5">
            <div>
                <h2 className="text-lg font-bold text-slate-900">Review &amp; Launch</h2>
                <p className="text-sm text-slate-500 mt-0.5">Verify your configuration before creating the scan.</p>
            </div>

            <div className="rounded-xl border border-slate-200 overflow-hidden divide-y divide-slate-100">
                <ReviewRow label="Scan Name"  value={form.name || '—'} bold />
                {form.description && <ReviewRow label="Description" value={form.description} />}
                <ReviewRow label="Scope"      value={scopeOpt?.label || scope} />
                <ReviewRow label="Target"     value={target || '—'} mono />
                <ReviewRow label="Type"       value={typeOpt?.label || form.type} />
                <ReviewRow label="Engine"     value={engineOpt?.label || form.engine} />
                <ReviewRow label="Profile"    value={(profileOpt?.label || form.profile) + '  ·  ' + (profileOpt?.duration || '')} />
                <ReviewRow label="Schedule"   value={scheduleOpt?.label || schedule} />
                {cronDisplay && <ReviewRow label="Cron" value={cronDisplay} mono />}
            </div>

            <div className="flex items-center gap-3 p-4 rounded-xl bg-slate-50 border border-slate-200">
                <Clock size={15} className="text-slate-400 shrink-0" />
                <div>
                    <div className="text-xs font-bold text-slate-700">Estimated Duration</div>
                    <div className="text-xs text-slate-500 mt-0.5">{profileOpt?.duration || 'Variable'} · based on selected profile</div>
                </div>
            </div>

            <div className="flex items-start gap-3 p-4 rounded-xl bg-amber-50 border border-amber-200">
                <AlertTriangle size={15} className="text-amber-500 shrink-0 mt-0.5" strokeWidth={2} />
                <div>
                    <div className="text-xs font-bold text-amber-800">Authorization Required</div>
                    <div className="text-[11px] text-amber-700 mt-0.5 leading-relaxed">
                        Ensure you have written authorization to scan the target.
                        Unauthorized scanning is illegal and may violate terms of service.
                    </div>
                </div>
            </div>
        </div>
    );
}

// ─── Main Wizard ──────────────────────────────────────────────────────────────

export default function ScanWizardPage({ onSave, onCancel }) {
    const [step,      setStep]      = useState(1);
    const [errors,    setErrors]    = useState({});
    const [saving,    setSaving]    = useState(false);
    const [submitErr, setSubmitErr] = useState(null);

    const [scope,      setScope]      = useState('single');
    const [target,     setTarget]     = useState('');
    const [form,       setForm]       = useState({ name: '', description: '', type: 'network_ip', engine: 'nmap', profile: 'standard' });
    const [schedule,   setSchedule]   = useState('now');
    const [customCron, setCustomCron] = useState('');
    const [dbCreds,    setDbCreds]    = useState({ driver: 'mysql', host: '', port: '3306', database: '', username: '', password: '' });

    const validate = useCallback((s) => {
        const e = {};
        if (s === 1 && !scope)                                       e.scope      = 'Please select a scope.';
        if (s === 2 && !target?.trim())                              e.target     = 'Target address is required.';
        if (s === 3 && !form.name?.trim())                           e.name       = 'Scan name is required.';
        if (s === 3 && !form.type)                                   e.type       = 'Select a scan type.';
        if (s === 3 && !form.engine)                                 e.engine     = 'Select a scan engine.';
        if (s === 3 && !form.profile)                                e.profile    = 'Select a scan profile.';
        if (s === 3 && form.type === 'database') {
            if (!dbCreds.host?.trim())     e['credentials.host']     = 'Host is required.';
            if (!dbCreds.port)             e['credentials.port']     = 'Port is required.';
            if (!dbCreds.username?.trim()) e['credentials.username'] = 'Username is required.';
            if (!dbCreds.password)         e['credentials.password'] = 'Password is required.';
        }
        if (s === 4 && schedule === 'custom' && !customCron?.trim()) e.customCron = 'Enter a valid cron expression.';
        return e;
    }, [scope, target, form, schedule, customCron, dbCreds]);

    const handleNext = useCallback(() => {
        const e = validate(step);
        if (Object.keys(e).length) { setErrors(e); return; }
        setErrors({});
        setStep(s => s + 1);
    }, [step, validate]);

    const handleBack = useCallback(() => {
        setErrors({});
        setStep(s => s - 1);
    }, []);

    const handleSubmit = useCallback(async () => {
        const e = validate(5);
        if (Object.keys(e).length) { setErrors(e); return; }
        setSaving(true);
        setSubmitErr(null);

        const cronValue =
            schedule === 'custom' ? customCron :
            (SCHEDULE_OPTIONS.find(s => s.value === schedule)?.cron ?? null);

        const payload = {
            name:        form.name.trim(),
            description: form.description?.trim() || null,
            type:        form.type,
            engine:      form.engine,
            target:      form.type === 'database' ? `${dbCreds.host}:${dbCreds.port}` : target.trim(),
            schedule:    cronValue,
            status:      schedule === 'now' ? 'queued' : 'scheduled',
            progress:    0,
        };
        
        if (form.type === 'database') {
            payload.credentials = {
                driver: dbCreds.driver,
                host: dbCreds.host,
                port: Number(dbCreds.port),
                database: dbCreds.database?.trim() || null,
                username: dbCreds.username,
                password: dbCreds.password,
            };
        }

        try {
            await axios.post('/api/scans', payload);
            
            // SECURITY: Immediately clear password state from memory after successful submit
            setDbCreds(prev => ({ ...prev, password: '' }));
            
            onSave();
        } catch (err) {
            if (err.response?.status === 422) {
                const apiErrs = err.response.data.errors || {};
                setErrors(apiErrs);
                setStep(3);
                setSubmitErr('Please fix the highlighted fields.');
            } else {
                setSubmitErr(err.response?.data?.message || 'Failed to create scan. Please try again.');
            }
        } finally {
            setSaving(false);
        }
    }, [validate, schedule, customCron, form, target, onSave]);

    useEffect(() => {
        const handler = (ev) => {
            if (ev.key === 'Escape') { onCancel(); return; }
            if (ev.key === 'Enter' && (ev.ctrlKey || ev.metaKey)) {
                ev.preventDefault();
                if (step < 5) handleNext();
                else handleSubmit();
            }
        };
        window.addEventListener('keydown', handler);
        return () => window.removeEventListener('keydown', handler);
    }, [step, handleNext, handleSubmit, onCancel]);

    return (
        <div className="max-w-3xl mx-auto space-y-6">

            {/* ── Header */}
            <div className="flex items-center gap-3">
                <button
                    type="button"
                    onClick={onCancel}
                    className="p-1.5 border border-slate-200 rounded-lg hover:bg-slate-50 text-slate-500 hover:text-slate-800 transition shrink-0"
                >
                    <ArrowLeft size={16} />
                </button>
                <div>
                    <h1 className="text-xl font-bold text-slate-900 tracking-tight flex items-center gap-2">
                        <ScanLine size={20} className="text-brand-500" strokeWidth={1.75} />
                        New Scan Wizard
                    </h1>
                    <p className="text-xs text-slate-500 mt-0.5">
                        Step {step} of {STEPS.length} — {STEPS[step - 1]?.label}
                    </p>
                </div>
            </div>

            {/* ── Stepper */}
            <div className="bg-white border border-slate-200 rounded-xl px-6 py-4 shadow-sm">
                <Stepper step={step} />
            </div>

            {/* ── Step content */}
            <div className="bg-white border border-slate-200 rounded-xl p-6 shadow-sm min-h-[320px]">
                {submitErr && (
                    <div className="mb-5 flex items-start gap-3 p-4 bg-rose-50 border border-rose-200 rounded-xl text-xs text-rose-800 font-medium">
                        <AlertTriangle size={14} className="text-rose-500 shrink-0 mt-0.5" />
                        {submitErr}
                    </div>
                )}
                {step === 1 && <StepScope    scope={scope}       setScope={setScope}        errors={errors} />}
                {step === 2 && <StepTarget   scope={scope}       target={target}            setTarget={setTarget} errors={errors} />}
                {step === 3 && <StepConfig   form={form}         setForm={setForm}          dbCreds={dbCreds} setDbCreds={setDbCreds} target={target} errors={errors} />}
                {step === 4 && <StepSchedule schedule={schedule} setSchedule={setSchedule} customCron={customCron} setCustomCron={setCustomCron} errors={errors} />}
                {step === 5 && <StepReview   scope={scope}       target={target}            form={form} schedule={schedule} customCron={customCron} />}
            </div>

            {/* ── Navigation footer */}
            <div className="bg-white border border-slate-200 rounded-xl px-6 py-4 shadow-sm flex items-center justify-between gap-3">
                <button
                    type="button"
                    onClick={onCancel}
                    className="px-4 py-2 text-sm font-semibold text-slate-500 hover:text-slate-800 transition"
                >
                    Cancel
                </button>

                <div className="flex items-center gap-3">
                    {step > 1 && (
                        <button
                            type="button"
                            onClick={handleBack}
                            disabled={saving}
                            className="inline-flex items-center gap-1.5 px-4 py-2 text-sm font-semibold text-slate-700 bg-white border border-slate-300 rounded-lg hover:bg-slate-50 disabled:opacity-40 transition shadow-sm"
                        >
                            <ArrowLeft size={14} strokeWidth={2.5} />
                            Previous
                        </button>
                    )}

                    {step < 5 ? (
                        <button
                            type="button"
                            onClick={handleNext}
                            className="inline-flex items-center gap-1.5 px-5 py-2 text-sm font-bold text-white bg-brand-600 hover:bg-brand-700 active:bg-brand-800 rounded-lg shadow-sm border border-brand-700 transition"
                        >
                            Next
                            <ArrowRight size={14} strokeWidth={2.5} />
                        </button>
                    ) : (
                        <button
                            type="button"
                            onClick={handleSubmit}
                            disabled={saving}
                            className="inline-flex items-center gap-2 px-6 py-2 text-sm font-bold text-white bg-brand-600 hover:bg-brand-700 active:bg-brand-800 rounded-lg shadow-sm border border-brand-700 disabled:opacity-50 transition"
                        >
                            {saving
                                ? <><Loader2 size={14} className="animate-spin" /> Creating Scan&hellip;</>
                                : <><CheckCircle2 size={14} strokeWidth={2.5} /> Create Scan</>}
                        </button>
                    )}
                </div>
            </div>
        </div>
    );
}
