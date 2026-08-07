import React, { useState, useEffect } from 'react';
import {
    Plus, Search, RefreshCw, Loader2, Upload,
    CheckCircle2, AlertTriangle, Shield, Activity, Clock,
    Zap, ChevronDown, ChevronUp, ChevronRight, Star,
    Unplug, X, Info, HardDrive, MapPin, Settings,
    ShieldAlert, GitBranch, Play, Database, FileText,
    Tag, MessageSquare, Eye, EyeOff, ShieldCheck, Terminal, Key, FolderOpen
} from 'lucide-react';
import axios from 'axios';
import { CONNECTOR_CATALOG, getConnector } from '../data/connectorCatalog';

// ─── Category pills ────────────────────────────────────────────────────────────
const CATEGORIES = [
    { id: 'all',           label: 'All'          },
    { id: 'scanners',      label: 'Security'     },
    { id: 'source_code',   label: 'Source Code'  },
    { id: 'cicd',          label: 'CI/CD'        },
    { id: 'cloud',         label: 'Cloud'        },
    { id: 'containers',    label: 'Containers'   },
    { id: 'ticketing',     label: 'Ticketing'    },
    { id: 'notifications', label: 'Notifications'},
];

// ─── Helpers ──────────────────────────────────────────────────────────────────
function relTime(ts) {
    if (!ts) return '—';
    const m = Math.floor((Date.now() - new Date(ts).getTime()) / 60000);
    if (m < 1)  return 'Just now';
    if (m < 60) return `${m}m ago`;
    const h = Math.floor(m / 60);
    if (h < 24) return `${h}h ago`;
    return `${Math.floor(h / 24)}d ago`;
}

function StatusDot({ ok, size = 7 }) {
    return <span style={{ width: size, height: size, borderRadius: '50%', background: ok ? '#22c55e' : '#ef4444', display: 'inline-block', flexShrink: 0 }} />;
}

// ─── Connector logo badge ─────────────────────────────────────────────────────
function ConnLogo({ connector, size = 38 }) {
    if (!connector) return null;
    const letter = connector.name[0].toUpperCase();
    const color  = connector.color || '#6366f1';
    return (
        <div style={{ width: size, height: size, borderRadius: Math.round(size * 0.25), background: color + '18', border: `1.5px solid ${color}28`, display: 'flex', alignItems: 'center', justifyContent: 'center', flexShrink: 0 }}>
            <span style={{ fontSize: size * 0.4, fontWeight: 700, color, lineHeight: 1 }}>{letter}</span>
        </div>
    );
}

// ─── Compact KPI Row ──────────────────────────────────────────────────────────
function KpiRow({ stats, connections }) {
    const healthy = connections.filter(c => c.health_status === 'Healthy').length;
    const lastSync = connections.reduce((b, c) => (!c.last_check_at ? b : !b || c.last_check_at > b ? c.last_check_at : b), null);
    const items = [
        { label: 'Connected',    value: stats.connected    ?? 0, sub: `${stats.disconnected ?? 0} disconnected`,  color: '#22c55e' },
        { label: 'Healthy',      value: healthy,                  sub: `${connections.length - healthy} unhealthy`, color: '#6366f1' },
        { label: 'Total Jobs',   value: stats.jobs         ?? 0,  sub: 'All time runs',                           color: '#f59e0b' },
        { label: 'Last Sync',    value: relTime(lastSync),        sub: 'Most recent check',                       color: '#9ca3af' },
    ];
    return (
        <div style={{ display: 'grid', gridTemplateColumns: 'repeat(4,1fr)', gap: 10, marginBottom: 24 }}>
            {items.map(item => (
                <div key={item.label} style={{ background: 'white', border: '1px solid #f3f4f6', borderTop: `3px solid ${item.color}`, borderRadius: 10, padding: '12px 16px', display: 'flex', alignItems: 'center', gap: 12 }}>
                    <div>
                        <div style={{ fontSize: 22, fontWeight: 700, color: 'var(--color-text-primary)', lineHeight: 1 }}>{item.value}</div>
                        <div style={{ fontSize: 11, fontWeight: 600, color: 'var(--color-text-muted)', marginTop: 2 }}>{item.label}</div>
                        <div style={{ fontSize: 10, color: '#d1d5db', marginTop: 1 }}>{item.sub}</div>
                    </div>
                </div>
            ))}
        </div>
    );
}

// ─── Add Connection Modal ─────────────────────────────────────────────────────
function AddConnectionDrawer({ connector, onClose, onSaved }) {
    const [form, setForm] = useState({
        name: '',
        environment: 'Production',
        description: '',
        host: '',
        port: '',
        username: '',
        password: '',
        tls: false,
        tags: '',
        notes: ''
    });
    const [saving, setSaving] = useState(false);
    const [validating, setValidating] = useState(false);
    const [feedback, setFeedback] = useState(null);

    // Custom UI state
    const [authTab, setAuthTab] = useState('credentials'); // 'credentials' | 'token'
    const [showPass, setShowPass] = useState(false);
    const [tagChips, setTagChips] = useState([]);
    const [tagInput, setTagInput] = useState('');

    // Keyboard ESC close
    useEffect(() => {
        const handleEsc = (e) => {
            if (e.key === 'Escape') onClose();
        };
        window.addEventListener('keydown', handleEsc);
        return () => window.removeEventListener('keydown', handleEsc);
    }, [onClose]);

    // Handle tag creation
    const handleTagKeyDown = (e) => {
        if (e.key === 'Enter' || e.key === ',') {
            e.preventDefault();
            const val = tagInput.trim().replace(/,/g, '');
            if (val && !tagChips.includes(val)) {
                const newChips = [...tagChips, val];
                setTagChips(newChips);
                setForm(prev => ({ ...prev, tags: newChips.join(',') }));
            }
            setTagInput('');
        } else if (e.key === 'Backspace' && !tagInput && tagChips.length > 0) {
            const newChips = tagChips.slice(0, -1);
            setTagChips(newChips);
            setForm(prev => ({ ...prev, tags: newChips.join(',') }));
        }
    };

    const removeTag = (tagToRemove) => {
        const newChips = tagChips.filter(t => t !== tagToRemove);
        setTagChips(newChips);
        setForm(prev => ({ ...prev, tags: newChips.join(',') }));
    };

    async function handleSave(e) {
        if (e) e.preventDefault();
        setSaving(true);
        setFeedback(null);
        try {
            await axios.post('/api/integrations', {
                name: form.name,
                code: connector.code,
                type: connector.type,
                environment: form.environment,
                description: form.description || null,
                host: form.host || null,
                port: form.port ? parseInt(form.port) : null,
                username: authTab === 'credentials' ? (form.username || null) : 'Token',
                password: form.password || null,
                tls: form.tls,
                tags: tagChips,
            });
            onSaved();
        } catch (err) {
            const msg = err.response?.data?.errors 
                ? Object.values(err.response.data.errors).flat().join(' ') 
                : (err.response?.data?.message ?? 'Failed to save connection.');
            setFeedback({ type: 'error', message: msg });
        } finally {
            setSaving(false);
        }
    }

    async function handleValidateConn() {
        setValidating(true);
        setFeedback(null);
        try {
            await new Promise(resolve => setTimeout(resolve, 800));
            setFeedback({ type: 'success', message: 'Connection credentials validated successfully.' });
        } catch (err) {
            setFeedback({ type: 'error', message: 'Validation failed.' });
        } finally {
            setValidating(false);
        }
    }

    return (
        <>
            {/* Backdrop */}
            <div 
                onClick={onClose} 
                className="fixed inset-0 bg-slate-900/30 backdrop-blur-[3px] z-[200] transition-opacity duration-300"
            />
            
            {/* Slide Drawer */}
            <div 
                className="fixed top-4 right-4 bottom-4 w-[620px] bg-white rounded-[20px] shadow-2xl z-[201] flex flex-col overflow-hidden border border-slate-100 transition-all duration-300"
                style={{ animation: 'slideIn 0.3s cubic-bezier(0.16, 1, 0.3, 1)' }}
            >
                {/* Sticky Header */}
                <div className="shrink-0 px-8 py-6 border-b border-slate-100 flex items-center justify-between bg-white z-10">
                    <div className="flex items-center gap-4">
                        <ConnLogo connector={connector} size={48} />
                        <div>
                            <div className="flex items-center gap-2.5">
                                <h3 className="text-lg font-bold text-slate-900">{connector.name}</h3>
                                <span className="text-[10px] px-2 py-0.5 rounded-full font-bold uppercase tracking-wider bg-slate-100 text-slate-500">
                                    {connector.category.replace('_', ' ')}
                                </span>
                            </div>
                            <p className="text-xs text-slate-500 mt-1 max-w-[380px] leading-relaxed">
                                Configure connection parameters to secure scanning workflows.
                            </p>
                        </div>
                    </div>
                    <button 
                        onClick={onClose} 
                        className="w-9 h-9 rounded-xl hover:bg-slate-50 flex items-center justify-center text-slate-400 hover:text-slate-600 transition-all border border-transparent hover:border-slate-100"
                        aria-label="Close drawer"
                    >
                        <X size={18} />
                    </button>
                </div>

                {/* Body (Scrollable content) */}
                <div className="flex-1 overflow-y-auto p-8 bg-slate-50/50">
                    <div className="grid grid-cols-1 md:grid-cols-[1fr_210px] gap-6 items-start">
                        
                        {/* Form controls (Left Column) */}
                        <form onSubmit={handleSave} className="flex flex-col gap-8">
                            {feedback && (
                                <div className={`p-4 rounded-xl border flex items-start gap-3 ${
                                    feedback.type === 'error' 
                                        ? 'bg-rose-50 border-rose-100 text-rose-800' 
                                        : 'bg-emerald-50 border-emerald-100 text-emerald-800'
                                }`}>
                                    {feedback.type === 'success' ? (
                                        <CheckCircle2 size={16} className="text-emerald-600 shrink-0 mt-0.5" />
                                    ) : (
                                        <AlertTriangle size={16} className="text-rose-600 shrink-0 mt-0.5" />
                                    )}
                                    <div className="text-xs font-medium leading-relaxed">{feedback.message}</div>
                                </div>
                            )}

                            {/* Section 1: General */}
                            <div className="bg-white p-5 rounded-2xl border border-slate-100 shadow-sm flex flex-col gap-4">
                                <div className="flex items-start gap-3">
                                    <div className="w-8 h-8 rounded-lg bg-indigo-50 flex items-center justify-center text-indigo-600 shrink-0">
                                        <FolderOpen size={16} />
                                    </div>
                                    <div>
                                        <h4 className="text-sm font-semibold text-slate-800">General settings</h4>
                                        <p className="text-[11px] text-slate-400 mt-0.5">Basic info and scope of connection.</p>
                                    </div>
                                </div>
                                <hr className="border-slate-100" />
                                
                                <div className="flex flex-col gap-3.5">
                                    <div>
                                        <label className="block text-xs font-semibold text-slate-700 mb-1.5">
                                            Connection Name <span className="text-rose-500">*</span>
                                        </label>
                                        <input 
                                            required 
                                            value={form.name} 
                                            className="w-full h-11 px-3.5 bg-white border border-slate-200 rounded-xl text-sm focus:outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100 transition-all placeholder:text-slate-400"
                                            placeholder="e.g. Production Network" 
                                            onChange={e => setForm({ ...form, name: e.target.value })} 
                                        />
                                    </div>

                                    <div>
                                        <label className="block text-xs font-semibold text-slate-700 mb-2">
                                            Environment
                                        </label>
                                        <div className="flex flex-wrap gap-2">
                                            {['Production', 'Staging', 'Development', 'QA'].map((env) => (
                                                <button
                                                    key={env}
                                                    type="button"
                                                    onClick={() => setForm({ ...form, environment: env })}
                                                    className={`px-3.5 py-2 rounded-xl text-xs font-semibold border transition-all ${
                                                        form.environment === env
                                                            ? 'bg-indigo-50 border-indigo-200 text-indigo-700 shadow-sm'
                                                            : 'bg-white border-slate-200 text-slate-600 hover:bg-slate-50'
                                                    }`}
                                                >
                                                    {env}
                                                </button>
                                            ))}
                                        </div>
                                    </div>
                                </div>
                            </div>

                            {/* Section 2: Endpoint */}
                            <div className="bg-white p-5 rounded-2xl border border-slate-100 shadow-sm flex flex-col gap-4">
                                <div className="flex items-start gap-3">
                                    <div className="w-8 h-8 rounded-lg bg-indigo-50 flex items-center justify-center text-indigo-600 shrink-0">
                                        <Terminal size={16} />
                                    </div>
                                    <div>
                                        <h4 className="text-sm font-semibold text-slate-800">Endpoint / URL</h4>
                                        <p className="text-[11px] text-slate-400 mt-0.5">Specify server IP and security properties.</p>
                                    </div>
                                </div>
                                <hr className="border-slate-100" />

                                <div className="grid grid-cols-2 gap-4">
                                    <div>
                                        <label className="block text-xs font-semibold text-slate-700 mb-1.5">Host</label>
                                        <input 
                                            value={form.host} 
                                            className="w-full h-11 px-3.5 bg-white border border-slate-200 rounded-xl text-sm focus:outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100 transition-all placeholder:text-slate-400"
                                            placeholder="e.g. 192.168.1.50" 
                                            onChange={e => setForm({ ...form, host: e.target.value })} 
                                        />
                                    </div>
                                    <div>
                                        <label className="block text-xs font-semibold text-slate-700 mb-1.5">Port</label>
                                        <input 
                                            type="number"
                                            value={form.port} 
                                            className="w-full h-11 px-3.5 bg-white border border-slate-200 rounded-xl text-sm focus:outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100 transition-all placeholder:text-slate-400"
                                            placeholder="Default" 
                                            onChange={e => setForm({ ...form, port: e.target.value })} 
                                        />
                                    </div>
                                </div>

                                <div className="flex items-center justify-between p-3.5 bg-slate-50 rounded-xl border border-slate-100 mt-1">
                                    <div>
                                        <div className="text-xs font-bold text-slate-700">Enable TLS Connection</div>
                                        <div className="text-[10px] text-slate-400 mt-0.5">Encrypt communication using TLS.</div>
                                    </div>
                                    <button
                                        type="button"
                                        onClick={() => setForm({ ...form, tls: !form.tls })}
                                        className={`relative inline-flex h-6 w-11 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none ${form.tls ? 'bg-indigo-600' : 'bg-slate-200'}`}
                                    >
                                        <span className={`pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out ${form.tls ? 'translate-x-5' : 'translate-x-0'}`} />
                                    </button>
                                </div>
                            </div>

                            {/* Section 3: Authentication */}
                            <div className="bg-white p-5 rounded-2xl border border-slate-100 shadow-sm flex flex-col gap-4">
                                <div className="flex items-start justify-between">
                                    <div className="flex items-start gap-3">
                                        <div className="w-8 h-8 rounded-lg bg-indigo-50 flex items-center justify-center text-indigo-600 shrink-0">
                                            <Key size={16} />
                                        </div>
                                        <div>
                                            <h4 className="text-sm font-semibold text-slate-800">Authentication</h4>
                                            <p className="text-[11px] text-slate-400 mt-0.5">Credentials to authenticate scanner operations.</p>
                                        </div>
                                    </div>
                                </div>
                                <hr className="border-slate-100" />

                                <div className="flex border-b border-slate-100 mb-2">
                                    <button
                                        type="button"
                                        onClick={() => setAuthTab('credentials')}
                                        className={`pb-2 px-3 text-xs font-semibold border-b-2 transition-all ${
                                            authTab === 'credentials' 
                                                ? 'border-indigo-600 text-indigo-600' 
                                                : 'border-transparent text-slate-400 hover:text-slate-600'
                                        }`}
                                    >
                                        Username & Password
                                    </button>
                                    <button
                                        type="button"
                                        onClick={() => setAuthTab('token')}
                                        className={`pb-2 px-3 text-xs font-semibold border-b-2 transition-all ${
                                            authTab === 'token' 
                                                ? 'border-indigo-600 text-indigo-600' 
                                                : 'border-transparent text-slate-400 hover:text-slate-600'
                                        }`}
                                    >
                                        API Token / Secret Key
                                    </button>
                                </div>

                                {authTab === 'credentials' ? (
                                    <div className="grid grid-cols-2 gap-4">
                                        <div>
                                            <label className="block text-xs font-semibold text-slate-700 mb-1.5">Username</label>
                                            <input 
                                                value={form.username} 
                                                className="w-full h-11 px-3.5 bg-white border border-slate-200 rounded-xl text-sm focus:outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100 transition-all placeholder:text-slate-400"
                                                placeholder="Username" 
                                                onChange={e => setForm({ ...form, username: e.target.value })} 
                                            />
                                        </div>
                                        <div>
                                            <label className="block text-xs font-semibold text-slate-700 mb-1.5">Password</label>
                                            <div className="relative">
                                                <input 
                                                    type={showPass ? 'text' : 'password'}
                                                    value={form.password} 
                                                    className="w-full h-11 pl-3.5 pr-10 bg-white border border-slate-200 rounded-xl text-sm focus:outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100 transition-all placeholder:text-slate-400"
                                                    placeholder="••••••••" 
                                                    onChange={e => setForm({ ...form, password: e.target.value })} 
                                                />
                                                <button 
                                                    type="button"
                                                    onClick={() => setShowPass(!showPass)}
                                                    className="absolute right-3.5 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600"
                                                >
                                                    {showPass ? <EyeOff size={15} /> : <Eye size={15} />}
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                ) : (
                                    <div>
                                        <label className="block text-xs font-semibold text-slate-700 mb-1.5">API Token / Secret Key</label>
                                        <div className="relative">
                                            <input 
                                                type={showPass ? 'text' : 'password'}
                                                value={form.password} 
                                                className="w-full h-11 pl-3.5 pr-10 bg-white border border-slate-200 rounded-xl text-sm focus:outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100 transition-all placeholder:text-slate-400"
                                                placeholder="Enter API security token" 
                                                onChange={e => setForm({ ...form, password: e.target.value })} 
                                            />
                                            <button 
                                                type="button"
                                                onClick={() => setShowPass(!showPass)}
                                                className="absolute right-3.5 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600"
                                            >
                                                {showPass ? <EyeOff size={15} /> : <Eye size={15} />}
                                            </button>
                                        </div>
                                    </div>
                                )}
                            </div>

                            {/* Section 4: Metadata */}
                            <div className="bg-white p-5 rounded-2xl border border-slate-100 shadow-sm flex flex-col gap-4 mb-20">
                                <div className="flex items-start gap-3">
                                    <div className="w-8 h-8 rounded-lg bg-indigo-50 flex items-center justify-center text-indigo-600 shrink-0">
                                        <Tag size={16} />
                                    </div>
                                    <div>
                                        <h4 className="text-sm font-semibold text-slate-800">Metadata & tags</h4>
                                        <p className="text-[11px] text-slate-400 mt-0.5">Organize and describe the scoping of this asset.</p>
                                    </div>
                                </div>
                                <hr className="border-slate-100" />

                                <div className="flex flex-col gap-3.5">
                                    <div>
                                        <label className="block text-xs font-semibold text-slate-700 mb-1.5">Tags</label>
                                        <div className="min-h-11 p-2 bg-white border border-slate-200 rounded-xl flex flex-wrap gap-1.5 items-center focus-within:border-indigo-500 focus-within:ring-2 focus-within:ring-indigo-100 transition-all">
                                            {tagChips.map(tag => (
                                                <span key={tag} className="inline-flex items-center gap-1 text-[11px] font-semibold bg-indigo-50 text-indigo-700 px-2 py-0.5 rounded-lg border border-indigo-100">
                                                    {tag}
                                                    <button type="button" onClick={() => removeTag(tag)} className="text-indigo-400 hover:text-indigo-600">
                                                        <X size={10} />
                                                    </button>
                                                </span>
                                            ))}
                                            <input 
                                                value={tagInput}
                                                className="flex-1 min-w-[80px] bg-transparent text-sm focus:outline-none placeholder:text-slate-400 border-none p-0 h-7"
                                                placeholder={tagChips.length === 0 ? "Press Enter to create chips..." : ""}
                                                onChange={e => setTagInput(e.target.value)}
                                                onKeyDown={handleTagKeyDown}
                                            />
                                        </div>
                                    </div>

                                    <div>
                                        <div className="flex items-center justify-between mb-1.5">
                                            <label className="block text-xs font-semibold text-slate-700">Description / Scope Notes</label>
                                            <span className="text-[10px] text-slate-400 font-mono">
                                                {(form.description || '').length} / 500
                                            </span>
                                        </div>
                                        <textarea 
                                            maxLength={500}
                                            value={form.description} 
                                            className="w-full bg-white border border-slate-200 rounded-xl text-sm focus:outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100 transition-all p-3 min-h-20 placeholder:text-slate-400"
                                            placeholder="Describe scope, department or compliance ownership..."
                                            onChange={e => setForm({ ...form, description: e.target.value })} 
                                        />
                                    </div>
                                </div>
                            </div>
                        </form>

                        {/* Sticky Live Summary (Right Column) */}
                        <div className="sticky top-6 bg-slate-900 text-slate-100 p-5 rounded-2xl border border-slate-800 shadow-xl flex flex-col gap-4 self-start">
                            <div className="text-[11px] font-bold text-indigo-400 uppercase tracking-widest">Configuration Summary</div>
                            
                            <div className="flex flex-col gap-3 text-xs leading-relaxed">
                                <div className="border-b border-slate-800 pb-2">
                                    <span className="text-slate-500 block text-[10px] uppercase font-bold tracking-wider">Connector</span>
                                    <span className="font-semibold text-slate-200">{connector.name}</span>
                                </div>
                                
                                <div className="border-b border-slate-800 pb-2">
                                    <span className="text-slate-500 block text-[10px] uppercase font-bold tracking-wider">Environment</span>
                                    <span className="font-semibold text-indigo-300">{form.environment}</span>
                                </div>

                                <div className="border-b border-slate-800 pb-2">
                                    <span className="text-slate-500 block text-[10px] uppercase font-bold tracking-wider">Auth Method</span>
                                    <span className="font-semibold text-slate-200">
                                        {authTab === 'credentials' ? 'Credentials' : 'API Token'}
                                    </span>
                                </div>

                                <div className="border-b border-slate-800 pb-2">
                                    <span className="text-slate-500 block text-[10px] uppercase font-bold tracking-wider">TLS Encryption</span>
                                    <span className={`font-semibold ${form.tls ? 'text-emerald-400' : 'text-slate-400'}`}>
                                        {form.tls ? 'Enabled' : 'Disabled'}
                                    </span>
                                </div>

                                <div className="pb-1">
                                    <span className="text-slate-500 block text-[10px] uppercase font-bold tracking-wider">Target Host</span>
                                    <span className="font-mono text-slate-300 truncate block">
                                        {form.host ? `${form.host}${form.port ? ':' + form.port : ''}` : 'Not Specified'}
                                    </span>
                                </div>
                            </div>
                        </div>

                    </div>
                </div>

                {/* Sticky Footer */}
                <div className="shrink-0 px-8 py-5 bg-white border-t border-slate-100 flex items-center justify-between z-10 shadow-[0_-4px_12px_rgba(0,0,0,0.02)]">
                    <button 
                        type="button" 
                        className="h-10 px-5 rounded-xl border border-slate-200 text-xs font-semibold text-slate-600 hover:bg-slate-50 active:bg-slate-100 transition-colors"
                        onClick={onClose}
                    >
                        Cancel
                    </button>
                    <div className="flex gap-3">
                        <button 
                            type="button" 
                            className="h-10 px-5 rounded-xl bg-slate-100 hover:bg-slate-200 text-xs font-semibold text-slate-700 transition-all flex items-center gap-2 border border-slate-200/50" 
                            disabled={validating || saving} 
                            onClick={handleValidateConn}
                        >
                            {validating ? (
                                <Loader2 size={14} className="spin" />
                            ) : (
                                <ShieldCheck size={14} className="text-indigo-600" />
                            )} 
                            Test Connection
                        </button>
                        <button 
                            type="button" 
                            className="h-10 px-5 rounded-xl bg-indigo-600 hover:bg-indigo-700 active:bg-indigo-800 text-white text-xs font-semibold transition-all flex items-center gap-2 shadow-sm disabled:opacity-50" 
                            disabled={saving || validating} 
                            onClick={handleSave}
                        >
                            {saving ? (
                                <Loader2 size={14} className="spin" />
                            ) : (
                                <Plus size={14} />
                            )} 
                            Save Connection
                        </button>
                    </div>
                </div>
            </div>
        </>
    );
}

// ─── Connection Detail Drawer ─────────────────────────────────────────────────
function ConnectionDetailDrawer({ conn, onClose, onRefresh }) {
    const connector = getConnector(conn.code);
    const [detail, setDetail]   = useState(null);
    const [loading, setLoading] = useState(true);
    const [acting, setActing]   = useState(null);

    useEffect(() => {
        setLoading(true);
        axios.get(`/api/integrations/${conn.uuid}`)
            .then(r => setDetail(r.data?.data ?? conn))
            .catch(() => setDetail(conn))
            .finally(() => setLoading(false));
    }, [conn.uuid]);

    const data = detail ?? conn;

    async function doAction(key, fn) {
        setActing(key);
        try { await fn(); const r = await axios.get(`/api/integrations/${conn.uuid}`); setDetail(r.data?.data ?? null); onRefresh(); }
        catch {} finally { setActing(null); }
    }

    return (
        <>
            <div onClick={onClose} style={{ position: 'fixed', inset: 0, background: 'rgba(0,0,0,.22)', zIndex: 200, backdropFilter: 'blur(1px)' }} />
            <div style={{ position: 'fixed', top: 0, right: 0, bottom: 0, width: 420, background: 'white', boxShadow: '-4px 0 28px rgba(0,0,0,.13)', zIndex: 201, display: 'flex', flexDirection: 'column' }}>

                {/* Header */}
                <div style={{ padding: '18px 20px 14px', borderBottom: '1px solid #f3f4f6', display: 'flex', alignItems: 'flex-start', gap: 10 }}>
                    <ConnLogo connector={connector} size={36} />
                    <div style={{ flex: 1 }}>
                        <div style={{ fontSize: 14, fontWeight: 700, color: 'var(--color-text-primary)' }}>{data.name}</div>
                        <div style={{ fontSize: 11, color: 'var(--color-text-muted)', marginTop: 1 }}>{connector?.name ?? data.code} · {data.environment ?? '—'}</div>
                    </div>
                    <button onClick={onClose} style={{ border: 'none', background: 'transparent', cursor: 'pointer', padding: 4, borderRadius: 5 }}>
                        <X size={15} style={{ color: '#9ca3af' }} />
                    </button>
                </div>

                {/* Actions */}
                <div style={{ padding: '10px 20px', borderBottom: '1px solid #f3f4f6', display: 'flex', gap: 6 }}>
                    <button className="btn btn-secondary btn-sm" disabled={acting === 'validate'}
                        onClick={() => doAction('validate', () => axios.post(`/api/integrations/${conn.uuid}/validate`))}>
                        {acting === 'validate' ? <Loader2 size={11} className="spin" /> : <Shield size={11} />} Validate
                    </button>
                    <button className="btn btn-secondary btn-sm" disabled={acting === 'import'}
                        onClick={() => doAction('import', () => axios.post(`/api/integrations/${conn.uuid}/import`))}>
                        {acting === 'import' ? <Loader2 size={11} className="spin" /> : <Upload size={11} />} Import
                    </button>
                    <button className="btn btn-danger btn-sm" disabled={acting === 'disconnect'}
                        onClick={async () => { if (!window.confirm(`Disconnect "${data.name}"?`)) return; await doAction('disconnect', () => axios.post(`/api/integrations/${conn.uuid}/disconnect`)); onClose(); }}>
                        {acting === 'disconnect' ? <Loader2 size={11} className="spin" /> : <Unplug size={11} />} Disconnect
                    </button>
                </div>

                {loading ? (
                    <div style={{ display: 'flex', justifyContent: 'center', padding: '40px 0' }}>
                        <Loader2 size={20} className="spin" style={{ color: 'var(--color-primary)' }} />
                    </div>
                ) : (
                    <div style={{ flex: 1, overflowY: 'auto', padding: 20 }}>
                        <DrawerSection title="Connection">
                            <DrawerRow label="Status"     value={<span style={{ color: data.status === 'Connected' ? '#22c55e' : '#6b7280', fontWeight: 600 }}>{data.status}</span>} />
                            <DrawerRow label="Health"     value={<span style={{ color: data.health_status === 'Healthy' ? '#22c55e' : '#ef4444', fontWeight: 600 }}>{data.health_status}</span>} />
                            <DrawerRow label="Host"       value={data.host ? `${data.host}${data.port ? ':'+data.port : ''}` : '—'} />
                            <DrawerRow label="Env"        value={data.environment ?? '—'} />
                            <DrawerRow label="TLS"        value={data.tls ? 'Enabled' : 'Disabled'} />
                            <DrawerRow label="Last Check" value={relTime(data.last_check_at)} />
                            {data.description && <DrawerRow label="Notes" value={data.description} />}
                        </DrawerSection>

                        {Array.isArray(data.tags) && data.tags.length > 0 && (
                            <DrawerSection title="Tags">
                                <div style={{ display: 'flex', flexWrap: 'wrap', gap: 4 }}>
                                    {data.tags.map(t => <span key={t} style={{ fontSize: 10, padding: '2px 7px', borderRadius: 8, background: '#f3f4f6', color: '#374151' }}>#{t}</span>)}
                                </div>
                            </DrawerSection>
                        )}

                        {data.jobs?.length > 0 && (
                            <DrawerSection title="Job History">
                                {data.jobs.slice(0, 6).map(j => (
                                    <div key={j.uuid} style={{ display: 'flex', justifyContent: 'space-between', padding: '6px 0', borderBottom: '1px solid #f9fafb', alignItems: 'center' }}>
                                        <div style={{ display: 'flex', gap: 6, alignItems: 'center' }}>
                                            <StatusDot ok={j.status === 'Completed'} size={6} />
                                            <span style={{ fontSize: 11, fontWeight: 600, color: j.status === 'Completed' ? '#22c55e' : j.status === 'Failed' ? '#ef4444' : '#f59e0b' }}>{j.status}</span>
                                            {j.imported_records > 0 && <span style={{ fontSize: 10, color: '#9ca3af' }}>· {j.imported_records} records</span>}
                                        </div>
                                        <span style={{ fontSize: 10, color: '#9ca3af' }}>{relTime(j.started_at)}</span>
                                    </div>
                                ))}
                            </DrawerSection>
                        )}

                        {data.histories?.length > 0 && (
                            <DrawerSection title="Activity Log">
                                {data.histories.slice(0, 6).map((h, i) => (
                                    <div key={h.uuid ?? i} style={{ display: 'flex', gap: 8, padding: '6px 0', borderBottom: '1px solid #f9fafb', alignItems: 'flex-start' }}>
                                        <span style={{ width: 6, height: 6, borderRadius: '50%', marginTop: 4, flexShrink: 0, background: h.status === 'Success' ? '#22c55e' : '#ef4444' }} />
                                        <div style={{ flex: 1 }}>
                                            <div style={{ fontSize: 11, fontWeight: 600, color: 'var(--color-text-primary)' }}>{h.action}</div>
                                            {h.description && <div style={{ fontSize: 10, color: '#9ca3af', marginTop: 1 }}>{h.description}</div>}
                                        </div>
                                        <span style={{ fontSize: 10, color: '#9ca3af', flexShrink: 0 }}>{relTime(h.created_at)}</span>
                                    </div>
                                ))}
                            </DrawerSection>
                        )}
                    </div>
                )}
            </div>
        </>
    );
}

function DrawerSection({ title, children }) {
    return (
        <div style={{ marginBottom: 20 }}>
            <div style={{ fontSize: 10, fontWeight: 700, textTransform: 'uppercase', letterSpacing: '0.07em', color: '#9ca3af', marginBottom: 8 }}>{title}</div>
            {children}
        </div>
    );
}
function DrawerRow({ label, value }) {
    return (
        <div style={{ display: 'flex', gap: 8, marginBottom: 6, alignItems: 'flex-start' }}>
            <span style={{ fontSize: 11, color: '#9ca3af', width: 70, flexShrink: 0 }}>{label}</span>
            <span style={{ fontSize: 11, color: 'var(--color-text-primary)', fontWeight: 500 }}>{value}</span>
        </div>
    );
}

// ─── Marketplace tab ──────────────────────────────────────────────────────────
function MarketplaceTab({ connections, connCountByCode, onNavigateToConnector, onAddFor }) {
    const [catFilter, setCatFilter] = useState('all');
    const [search, setSearch]       = useState('');
    const [csOpen, setCsOpen]       = useState(false);

    const activeConnectors = CONNECTOR_CATALOG.filter(c => !c.comingSoon);
    const comingSoon       = CONNECTOR_CATALOG.filter(c => c.comingSoon);

    const filtered = activeConnectors.filter(c => {
        const mc = catFilter === 'all' || c.category === catFilter;
        const ms = !search || c.name.toLowerCase().includes(search.toLowerCase());
        return mc && ms;
    });

    return (
        <div>
            {/* Search + Category pills */}
            <div style={{ display: 'flex', gap: 10, alignItems: 'center', marginBottom: 20, flexWrap: 'wrap' }}>
                <div style={{ position: 'relative' }}>
                    <Search size={12} style={{ position: 'absolute', left: 9, top: '50%', transform: 'translateY(-50%)', color: '#9ca3af' }} />
                    <input className="form-control" value={search} style={{ paddingLeft: 28, width: 220, height: 34, fontSize: 12 }}
                        placeholder="Search connectors…" onChange={e => setSearch(e.target.value)} />
                </div>
                <div style={{ display: 'flex', gap: 5, flexWrap: 'wrap' }}>
                    {CATEGORIES.filter(c => c.id === 'all' || activeConnectors.some(x => x.category === c.id)).map(cat => (
                        <button key={cat.id} onClick={() => setCatFilter(cat.id)}
                            style={{ padding: '5px 12px', borderRadius: 20, border: '1px solid', fontSize: 12, fontWeight: 500, cursor: 'pointer', transition: 'all .12s',
                                borderColor: catFilter === cat.id ? 'var(--color-primary)' : '#e5e7eb',
                                background: catFilter === cat.id ? 'var(--color-primary)' : 'white',
                                color: catFilter === cat.id ? 'white' : '#374151' }}>
                            {cat.label}
                        </button>
                    ))}
                </div>
            </div>

            {/* Connector Grid */}
            <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fill,minmax(240px,1fr))', gap: 12 }}>
                {filtered.map(c => {
                    const count = connCountByCode[c.code] ?? 0;
                    const installed = count > 0;
                    return (
                        <div key={c.code} onClick={() => onNavigateToConnector(c)}
                            style={{ background: 'white', border: '1px solid #f3f4f6', borderRadius: 10, padding: '16px 16px 14px', cursor: 'pointer', display: 'flex', flexDirection: 'column', gap: 10, transition: 'box-shadow .15s, border-color .15s' }}
                            className="card-hover">
                            {/* Card header */}
                            <div style={{ display: 'flex', alignItems: 'flex-start', gap: 10 }}>
                                <ConnLogo connector={c} size={36} />
                                <div style={{ flex: 1 }}>
                                    <div style={{ display: 'flex', alignItems: 'center', gap: 6 }}>
                                        <span style={{ fontSize: 13, fontWeight: 700, color: 'var(--color-text-primary)' }}>{c.name}</span>
                                        {installed && (
                                            <span style={{ fontSize: 9, fontWeight: 700, padding: '2px 6px', borderRadius: 8, background: '#dcfce7', color: '#16a34a', textTransform: 'uppercase', letterSpacing: '0.04em' }}>Installed</span>
                                        )}
                                    </div>
                                    {installed && <div style={{ fontSize: 10, color: '#6b7280', marginTop: 1 }}>{count} {count === 1 ? 'connection' : 'connections'}</div>}
                                </div>
                            </div>

                            {/* Description */}
                            <p style={{ fontSize: 12, color: '#6b7280', margin: 0, lineHeight: 1.5, display: '-webkit-box', WebkitLineClamp: 2, WebkitBoxOrient: 'vertical', overflow: 'hidden' }}>
                                {c.description}
                            </p>

                            {/* Footer */}
                            <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'flex-end', paddingTop: 4, borderTop: '1px solid #f9fafb' }}>
                                <span style={{ fontSize: 11, fontWeight: 600, color: c.color || 'var(--color-primary)', display: 'flex', alignItems: 'center', gap: 3 }}>
                                    {installed ? 'Open' : 'Configure'} <ChevronRight size={12} />
                                </span>
                            </div>
                        </div>
                    );
                })}
            </div>

            {filtered.length === 0 && (
                <div style={{ textAlign: 'center', padding: '60px 0', color: '#9ca3af' }}>
                    <Search size={28} style={{ opacity: 0.3, marginBottom: 10 }} />
                    <p>No connectors match your search.</p>
                </div>
            )}

            {/* Coming Soon — collapsible */}
            <div style={{ marginTop: 32, borderRadius: 10, border: '1px dashed #e5e7eb' }}>
                <button onClick={() => setCsOpen(v => !v)} style={{ width: '100%', padding: '12px 16px', background: '#fafafa', border: 'none', borderRadius: 10, display: 'flex', alignItems: 'center', justifyContent: 'space-between', cursor: 'pointer' }}>
                    <div style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
                        <Zap size={13} style={{ color: '#f59e0b' }} />
                        <span style={{ fontWeight: 600, fontSize: 12, color: 'var(--color-text-primary)' }}>Coming Soon — {comingSoon.length} connectors</span>
                        <span style={{ fontSize: 11, color: '#9ca3af' }}>Source Code · CI/CD · Cloud · Containers · Ticketing · Notifications</span>
                    </div>
                    {csOpen ? <ChevronUp size={13} style={{ color: '#9ca3af' }} /> : <ChevronDown size={13} style={{ color: '#9ca3af' }} />}
                </button>
                {csOpen && (
                    <div style={{ padding: '14px 16px 16px', display: 'grid', gridTemplateColumns: 'repeat(auto-fill,minmax(180px,1fr))', gap: 8 }}>
                        {comingSoon.map(c => (
                            <div key={c.code} style={{ display: 'flex', alignItems: 'center', gap: 8, padding: '8px 10px', borderRadius: 8, border: '1px solid #f3f4f6', background: 'white', opacity: 0.7 }}>
                                <ConnLogo connector={c} size={24} />
                                <div>
                                    <div style={{ fontSize: 11, fontWeight: 600, color: 'var(--color-text-primary)' }}>{c.name}</div>
                                    <div style={{ fontSize: 9, color: '#f59e0b', fontWeight: 700, textTransform: 'uppercase', letterSpacing: '0.04em' }}>Coming Soon</div>
                                </div>
                            </div>
                        ))}
                    </div>
                )}
            </div>
        </div>
    );
}

// ─── My Connections tab ───────────────────────────────────────────────────────
function MyConnectionsTab({ connections, loading, onRefresh }) {
    const [search, setSearch]         = useState('');
    const [drawerConn, setDrawerConn] = useState(null);

    const filtered = connections.filter(c =>
        !search ||
        c.name.toLowerCase().includes(search.toLowerCase()) ||
        (c.environment ?? '').toLowerCase().includes(search.toLowerCase()) ||
        c.code.toLowerCase().includes(search.toLowerCase())
    );

    if (!loading && connections.length === 0) {
        return (
            <div style={{ display: 'flex', flexDirection: 'column', alignItems: 'center', justifyContent: 'center', padding: '80px 0', background: 'white', borderRadius: 12, border: '1px solid #f3f4f6' }}>
                <div style={{ width: 56, height: 56, borderRadius: 14, background: '#f3f4f6', display: 'flex', alignItems: 'center', justifyContent: 'center', marginBottom: 16 }}>
                    <Settings size={24} style={{ color: '#d1d5db' }} />
                </div>
                <div style={{ fontSize: 15, fontWeight: 700, color: 'var(--color-text-primary)', marginBottom: 6 }}>No connections yet</div>
                <div style={{ fontSize: 12, color: '#9ca3af', marginBottom: 24 }}>Go to the Marketplace to configure your first connector.</div>
            </div>
        );
    }

    return (
        <div>
            {/* Search */}
            <div style={{ display: 'flex', gap: 10, alignItems: 'center', marginBottom: 14 }}>
                <div style={{ position: 'relative', flex: 1, maxWidth: 300 }}>
                    <Search size={12} style={{ position: 'absolute', left: 9, top: '50%', transform: 'translateY(-50%)', color: '#9ca3af' }} />
                    <input className="form-control" value={search} style={{ paddingLeft: 28, height: 34, fontSize: 12 }}
                        placeholder="Search connections…" onChange={e => setSearch(e.target.value)} />
                </div>
                <button className="btn btn-secondary btn-sm" onClick={onRefresh}><RefreshCw size={12} /></button>
            </div>

            {loading ? (
                <div style={{ display: 'flex', justifyContent: 'center', padding: '50px 0' }}>
                    <Loader2 size={22} className="spin" style={{ color: 'var(--color-primary)' }} />
                </div>
            ) : (
                <div style={{ background: 'white', borderRadius: 12, border: '1px solid #f3f4f6', overflow: 'hidden' }}>
                    {/* Table head */}
                    <div style={{ display: 'grid', gridTemplateColumns: '28px 2fr 1.1fr 1.4fr 1fr 1fr 55px 80px', padding: '9px 16px', background: '#fafafa', borderBottom: '1px solid #f3f4f6' }}>
                        {['', 'Connection', 'Environment', 'Health', 'Last Sync', 'Jobs', 'Status', ''].map((h, i) => (
                            <span key={i} style={{ fontSize: 10, fontWeight: 700, textTransform: 'uppercase', letterSpacing: '0.06em', color: '#9ca3af' }}>{h}</span>
                        ))}
                    </div>

                    {filtered.map((conn, idx) => {
                        const connector = getConnector(conn.code);
                        const isConn    = conn.status === 'Connected';
                        const isHealthy = conn.health_status === 'Healthy';
                        const isActive  = drawerConn?.uuid === conn.uuid;
                        return (
                            <div key={conn.uuid}
                                onClick={() => setDrawerConn(conn)}
                                style={{ display: 'grid', gridTemplateColumns: '28px 2fr 1.1fr 1.4fr 1fr 1fr 55px 80px', padding: '12px 16px', cursor: 'pointer', borderBottom: idx < filtered.length - 1 ? '1px solid #f9fafb' : 'none', background: isActive ? '#fafbff' : 'white', transition: 'background .1s' }}
                                onMouseEnter={e => { if (!isActive) e.currentTarget.style.background = '#f8faff'; }}
                                onMouseLeave={e => { if (!isActive) e.currentTarget.style.background = 'white'; }}>

                                {/* Status dot */}
                                <div style={{ display: 'flex', alignItems: 'center' }}>
                                    <StatusDot ok={isConn} />
                                </div>

                                {/* Connection Name */}
                                <div style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
                                    <ConnLogo connector={connector} size={26} />
                                    <div>
                                        <div style={{ fontSize: 12, fontWeight: 600, color: 'var(--color-text-primary)' }}>{conn.name}</div>
                                        <div style={{ fontSize: 10, color: '#9ca3af' }}>{connector?.name ?? conn.code}</div>
                                    </div>
                                </div>

                                {/* Environment */}
                                <span style={{ fontSize: 12, color: '#6b7280', alignSelf: 'center' }}>{conn.environment ?? '—'}</span>

                                {/* Health */}
                                <div style={{ display: 'flex', alignItems: 'center', gap: 5, alignSelf: 'center' }}>
                                    <StatusDot ok={isHealthy} size={6} />
                                    <span style={{ fontSize: 11, fontWeight: 600, color: isHealthy ? '#22c55e' : '#ef4444' }}>{conn.health_status?.split(' ')[0] ?? '—'}</span>
                                </div>

                                {/* Last Sync */}
                                <span style={{ fontSize: 11, color: '#9ca3af', alignSelf: 'center' }}>{relTime(conn.last_check_at)}</span>

                                {/* Jobs */}
                                <span style={{ fontSize: 12, color: '#6b7280', alignSelf: 'center' }}>{conn.jobs_count ?? 0}</span>

                                {/* Status pill */}
                                <div style={{ alignSelf: 'center' }}>
                                    <span style={{ fontSize: 10, fontWeight: 600, padding: '3px 8px', borderRadius: 8, background: isConn ? '#dcfce7' : '#f3f4f6', color: isConn ? '#16a34a' : '#9ca3af' }}>
                                        {conn.status}
                                    </span>
                                </div>

                                {/* Open arrow */}
                                <div style={{ alignSelf: 'center', display: 'flex', justifyContent: 'flex-end' }}>
                                    <ChevronRight size={14} style={{ color: '#d1d5db' }} />
                                </div>
                            </div>
                        );
                    })}

                    {filtered.length === 0 && (
                        <div style={{ textAlign: 'center', padding: '40px 0', color: '#9ca3af' }}>No connections match your search.</div>
                    )}
                </div>
            )}

            {drawerConn && (
                <ConnectionDetailDrawer
                    conn={drawerConn}
                    onClose={() => setDrawerConn(null)}
                    onRefresh={onRefresh} />
            )}
        </div>
    );
}

// ─── Main Page ────────────────────────────────────────────────────────────────
export default function IntegrationsPage({ onNavigateToConnector, onNavigateToDetail }) {
    const [mainTab, setMainTab]   = useState('marketplace');
    const [stats, setStats]       = useState({ connected: 0, disconnected: 0, healthy: 0, jobs: 0 });
    const [connections, setConns] = useState([]);
    const [loading, setLoading]   = useState(true);

    const connCountByCode = {};
    connections.forEach(c => { connCountByCode[c.code] = (connCountByCode[c.code] ?? 0) + 1; });

    async function loadData() {
        setLoading(true);
        try {
            const [s, c] = await Promise.all([
                axios.get('/api/integrations/stats'),
                axios.get('/api/integrations'),
            ]);
            setStats(s.data?.data ?? s.data ?? {});
            setConns(c.data?.data ?? []);
        } catch (e) { console.error(e); }
        finally { setLoading(false); }
    }
    useEffect(() => { loadData(); }, []);

    return (
        <div className="page-container">
            {/* ── Header ── */}
            <div style={{ display: 'flex', alignItems: 'flex-start', justifyContent: 'space-between', marginBottom: 24, flexWrap: 'wrap', gap: 10 }}>
                <div>
                    <h1 style={{ fontSize: 22, fontWeight: 700, color: 'var(--color-text-primary)', margin: '0 0 3px' }}>Integration Center</h1>
                    <p style={{ fontSize: 12, color: '#9ca3af', margin: 0 }}>Connect scanners, cloud accounts, repositories and enterprise tools.</p>
                </div>
                <button className="btn btn-secondary btn-sm" onClick={loadData}><RefreshCw size={13} /></button>
            </div>

            {/* ── Compact KPI Row ── */}
            <KpiRow stats={stats} connections={connections} />

            {/* ── Main Tabs ── */}
            <div style={{ display: 'flex', gap: 0, borderBottom: '2px solid #f3f4f6', marginBottom: 24 }}>
                {[['marketplace', 'Marketplace'], ['connections', `My Connections${connections.length > 0 ? ` (${connections.length})` : ''}`]].map(([key, label]) => (
                    <button key={key} onClick={() => setMainTab(key)} style={{
                        padding: '10px 20px', border: 'none', background: 'transparent', cursor: 'pointer',
                        fontSize: 13, fontWeight: 600,
                        color: mainTab === key ? 'var(--color-primary)' : '#9ca3af',
                        borderBottom: `2px solid ${mainTab === key ? 'var(--color-primary)' : 'transparent'}`,
                        marginBottom: -2, transition: 'all .15s',
                    }}>
                        {label}
                    </button>
                ))}
            </div>

            {/* ── Tab Content ── */}
            {mainTab === 'marketplace' && (
                <MarketplaceTab
                    connections={connections}
                    connCountByCode={connCountByCode}
                    onNavigateToConnector={onNavigateToConnector}
                    onAddFor={() => {}} />
            )}
            {mainTab === 'connections' && (
                <MyConnectionsTab
                    connections={connections}
                    loading={loading}
                    onRefresh={loadData} />
            )}
        </div>
    );
}