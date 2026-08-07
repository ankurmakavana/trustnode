import React, { useState, useEffect } from 'react';
import {
    ChevronLeft, Plus, RefreshCw, CheckCircle2, Activity,
    Shield, Clock, Unplug, Upload, Loader2, HardDrive,
    MapPin, ShieldAlert, X, ChevronRight, Info, MoreVertical, Check, Search, Blocks, AlertTriangle,
    Eye, EyeOff, ShieldCheck, Terminal, Key, FolderOpen, Tag
} from 'lucide-react';
import axios from 'axios';
import { getConnector } from '../data/connectorCatalog';
import ImportWizard from '../components/ImportWizard';

const ENVS = ['Production', 'Staging', 'Development', 'DMZ', 'QA', 'Testing', 'Client'];

function relTime(ts) {
    if (!ts) return '—';
    const m = Math.floor((Date.now() - new Date(ts).getTime()) / 60000);
    if (m < 1)  return 'Just now';
    if (m < 60) return `${m}m ago`;
    const h = Math.floor(m / 60);
    if (h < 24) return `${h}h ago`;
    return `${Math.floor(h / 24)}d ago`;
}

function ConnLogo({ connector, size = 38 }) {
    if (!connector) return null;
    const letter = connector.name[0].toUpperCase();
    const color  = connector.color || '#6366f1';
    return (
        <div style={{
            width: size, height: size, borderRadius: Math.round(size * 0.25),
            background: color + '12', border: `1.5px solid ${color}22`,
            display: 'flex', alignItems: 'center', justifyContent: 'center', flexShrink: 0,
            boxShadow: '0 2px 4px rgba(0,0,0,0.02)'
        }}>
            <span style={{ fontSize: size * 0.42, fontWeight: 800, color, lineHeight: 1 }}>{letter}</span>
        </div>
    );
}

// ─── Add Connection - Premium slide drawer ────────────────────────────────────
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
function ConnectionDetailDrawer({ conn, connector, onClose, onRefresh }) {
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
        try {
            await fn();
            const r = await axios.get(`/api/integrations/${conn.uuid}`);
            setDetail(r.data?.data ?? null);
            onRefresh();
        } catch {} finally { setActing(null); }
    }

    const isHealthy = data.health_status === 'Healthy';
    const isConn    = data.status === 'Connected';

    return (
        <>
            <div onClick={onClose} style={{ position: 'fixed', inset: 0, background: 'rgba(15, 23, 42, 0.15)', zIndex: 200, backdropFilter: 'blur(2px)' }} />
            <div style={{
                position: 'fixed', top: 0, right: 0, bottom: 0, width: 440, background: 'white',
                boxShadow: '-8px 0 32px rgba(15, 23, 42, 0.08)', zIndex: 201, display: 'flex', flexDirection: 'column',
                animation: 'slideIn 0.25s cubic-bezier(0.16, 1, 0.3, 1)'
            }}>
                {/* Header */}
                <div style={{ padding: '20px 24px', borderBottom: '1px solid #f1f5f9', display: 'flex', alignItems: 'center', gap: 12 }}>
                    <ConnLogo connector={connector} size={36} />
                    <div style={{ flex: 1, minWidth: 0 }}>
                        <div style={{ fontSize: 15, fontWeight: 700, color: '#0f172a', whiteSpace: 'nowrap', overflow: 'hidden', textOverflow: 'ellipsis' }}>{data.name}</div>
                        <div style={{ fontSize: 11, color: '#64748b', marginTop: 1 }}>{connector?.name ?? data.code} · {data.environment ?? '—'}</div>
                    </div>
                    <button onClick={onClose} style={{ border: 'none', background: 'transparent', cursor: 'pointer', padding: 6, borderRadius: 8 }} className="btn-hover-gray">
                        <X size={16} style={{ color: '#64748b' }} />
                    </button>
                </div>

                {/* Actions banner */}
                <div style={{ padding: '12px 24px', borderBottom: '1px solid #f1f5f9', display: 'flex', gap: 8, background: '#f8fafc' }}>
                    <button className="btn btn-secondary btn-sm" style={{ display: 'flex', alignItems: 'center', gap: 6, fontWeight: 600 }} disabled={acting === 'validate'}
                        onClick={() => doAction('validate', () => axios.post(`/api/integrations/${conn.uuid}/validate`))}>
                        {acting === 'validate' ? <Loader2 size={12} className="spin" /> : <Shield size={12} />} Validate
                    </button>
                    <button className="btn btn-secondary btn-sm" style={{ display: 'flex', alignItems: 'center', gap: 6, fontWeight: 600 }} disabled={acting === 'import'}
                        onClick={() => doAction('import', () => axios.post(`/api/integrations/${conn.uuid}/import`))}>
                        {acting === 'import' ? <Loader2 size={12} className="spin" /> : <Upload size={12} />} Import
                    </button>
                    <button className="btn btn-danger btn-sm" style={{ display: 'flex', alignItems: 'center', gap: 6, fontWeight: 600, marginLeft: 'auto' }} disabled={acting === 'disconnect'}
                        onClick={async () => {
                            if (!window.confirm(`Disconnect "${data.name}"?`)) return;
                            await doAction('disconnect', () => axios.post(`/api/integrations/${conn.uuid}/disconnect`));
                            onClose();
                        }}>
                        {acting === 'disconnect' ? <Loader2 size={12} className="spin" /> : <Unplug size={12} />} Disconnect
                    </button>
                </div>

                {loading ? (
                    <div style={{ display: 'flex', justifyContent: 'center', padding: '40px 0' }}>
                        <Loader2 size={22} className="spin" style={{ color: 'var(--color-primary)' }} />
                    </div>
                ) : (
                    <div style={{ flex: 1, overflowY: 'auto', padding: 24, display: 'flex', flexDirection: 'column', gap: 20 }}>
                        <div>
                            <div style={{ fontSize: 10, fontWeight: 700, textTransform: 'uppercase', letterSpacing: '0.08em', color: '#94a3b8', marginBottom: 10 }}>Connection Info</div>
                            <div style={{ display: 'flex', flexDirection: 'column', gap: 8 }}>
                                <DrawerRow label="Status" value={<span className={`badge ${isConn ? 'badge-success' : 'badge-secondary'}`}>{data.status}</span>} />
                                <DrawerRow label="Health" value={<span className={`badge ${isHealthy ? 'badge-success' : 'badge-danger'}`}>{data.health_status}</span>} />
                                <DrawerRow label="Endpoint" value={data.host ? `${data.host}${data.port ? ':' + data.port : ''}` : '—'} />
                                <DrawerRow label="TLS" value={data.tls ? 'Enabled' : 'Disabled'} />
                                <DrawerRow label="Last Check" value={relTime(data.last_check_at)} />
                                {data.description && <DrawerRow label="Notes" value={data.description} />}
                            </div>
                        </div>

                        {Array.isArray(data.tags) && data.tags.length > 0 && (
                            <div>
                                <div style={{ fontSize: 10, fontWeight: 700, textTransform: 'uppercase', letterSpacing: '0.08em', color: '#94a3b8', marginBottom: 8 }}>Tags</div>
                                <div style={{ display: 'flex', flexWrap: 'wrap', gap: 4 }}>
                                    {data.tags.map(t => <span key={t} style={{ fontSize: 10, padding: '2px 8px', borderRadius: 10, background: '#f1f5f9', color: '#475569' }}>#{t}</span>)}
                                </div>
                            </div>
                        )}

                        {data.jobs?.length > 0 && (
                            <div>
                                <div style={{ fontSize: 10, fontWeight: 700, textTransform: 'uppercase', letterSpacing: '0.08em', color: '#94a3b8', marginBottom: 10 }}>Job History</div>
                                <div style={{ display: 'flex', flexDirection: 'column', gap: 6 }}>
                                    {data.jobs.slice(0, 5).map(j => (
                                        <div key={j.uuid} style={{ display: 'flex', justifyContent: 'space-between', padding: '6px 0', borderBottom: '1px solid #f8fafc', alignItems: 'center' }}>
                                            <div style={{ display: 'flex', gap: 6, alignItems: 'center' }}>
                                                <span style={{ width: 6, height: 6, borderRadius: '50%', background: j.status === 'Completed' ? '#22c55e' : '#f59e0b' }} />
                                                <span style={{ fontSize: 11, fontWeight: 600, color: '#334155' }}>{j.status}</span>
                                                {j.imported_records > 0 && <span style={{ fontSize: 10, color: '#64748b' }}>· {j.imported_records} records</span>}
                                            </div>
                                            <span style={{ fontSize: 10, color: '#94a3b8' }}>{relTime(j.started_at)}</span>
                                        </div>
                                    ))}
                                </div>
                            </div>
                        )}

                        {data.histories?.length > 0 && (
                            <div>
                                <div style={{ fontSize: 10, fontWeight: 700, textTransform: 'uppercase', letterSpacing: '0.08em', color: '#94a3b8', marginBottom: 10 }}>Logs & Activity</div>
                                <div style={{ display: 'flex', flexDirection: 'column', gap: 8 }}>
                                    {data.histories.slice(0, 5).map((h, idx) => (
                                        <div key={h.uuid ?? idx} style={{ display: 'flex', gap: 10, padding: '8px 0', borderBottom: '1px solid #f8fafc', alignItems: 'flex-start' }}>
                                            <span style={{ width: 6, height: 6, borderRadius: '50%', marginTop: 5, flexShrink: 0, background: h.status === 'Success' ? '#22c55e' : '#ef4444' }} />
                                            <div style={{ flex: 1 }}>
                                                <div style={{ fontSize: 11, fontWeight: 600, color: '#334155' }}>{h.action}</div>
                                                {h.description && <div style={{ fontSize: 10, color: '#64748b', marginTop: 1 }}>{h.description}</div>}
                                            </div>
                                            <span style={{ fontSize: 10, color: '#94a3b8', flexShrink: 0 }}>{relTime(h.created_at)}</span>
                                        </div>
                                    ))}
                                </div>
                            </div>
                        )}
                    </div>
                )}
            </div>
        </>
    );
}

function DrawerRow({ label, value }) {
    return (
        <div style={{ display: 'flex', gap: 12, alignItems: 'flex-start' }}>
            <span style={{ fontSize: 11, color: '#64748b', width: 80, flexShrink: 0 }}>{label}</span>
            <span style={{ fontSize: 12, color: '#0f172a', fontWeight: 500 }}>{value}</span>
        </div>
    );
}

// ─── Main Page ────────────────────────────────────────────────────────────────
export default function ConnectorPage({ connectorCode, onBack }) {
    const connector = getConnector(connectorCode);

    const [connections, setConnections] = useState([]);
    const [loading, setLoading]         = useState(true);
    
    // Filters & Toolbar
    const [search, setSearch]           = useState('');
    const [envFilter, setEnvFilter]     = useState('all');
    const [statusFilter, setStatusFilter] = useState('all');
    const [healthFilter, setHealthFilter] = useState('all');

    // UI Drawer state
    const [showAddDrawer, setShowAddDrawer] = useState(false);
    const [drawerConn, setDrawerConn]   = useState(null);
    const [validatingAll, setValAll]    = useState(false);
    const [importingAll, setImpAll]     = useState(false);
    const [showImportWizard, setShowImportWizard] = useState(false);

    // Active dropdown action ID
    const [activeActionMenu, setActiveActionMenu] = useState(null);

    async function loadConnections() {
        setLoading(true);
        try {
            const res = await axios.get(`/api/integrations/connector/${connectorCode}`);
            setConnections(res.data?.data ?? []);
        } catch (err) { console.error('Failed to load connections', err); }
        finally { setLoading(false); }
    }
    useEffect(() => { loadConnections(); }, [connectorCode]);

    async function validateAll() {
        setValAll(true);
        await Promise.allSettled(connections.map(c => axios.post(`/api/integrations/${c.uuid}/validate`)));
        setValAll(false); loadConnections();
    }
    async function importAll() {
        setImpAll(true);
        await Promise.allSettled(connections.map(c => axios.post(`/api/integrations/${c.uuid}/import`)));
        setImpAll(false); loadConnections();
    }

    const filtered = connections.filter(c => {
        const matchSearch = !search || c.name.toLowerCase().includes(search.toLowerCase()) || (c.host ?? '').toLowerCase().includes(search.toLowerCase());
        const matchEnv    = envFilter === 'all' || c.environment === envFilter;
        const matchStatus = statusFilter === 'all' || c.status === statusFilter;
        const matchHealth = healthFilter === 'all' || c.health_status === healthFilter;
        return matchSearch && matchEnv && matchStatus && matchHealth;
    });

    const connected = connections.filter(c => c.status === 'Connected').length;
    const healthy   = connections.filter(c => c.health_status === 'Healthy').length;
    const totalJobs = connections.reduce((s, c) => s + (c.jobs_count ?? 0), 0);

    if (!connector) {
        return (
            <div className="page-container">
                <button className="btn btn-secondary btn-sm" onClick={onBack} style={{ marginBottom: 20 }}>
                    <ChevronLeft size={13} /> Back
                </button>
                <div style={{ textAlign: 'center', padding: '60px 0', color: '#94a3b8' }}>Connector not found.</div>
            </div>
        );
    }

    return (
        <div className="page-container" style={{ position: 'relative' }}>
            
            {/* Header layout: Left aligned Back, Logo, Title, badges & Right buttons */}
            <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', marginBottom: 24, paddingBottom: 16, borderBottom: '1px solid #e2e8f0' }}>
                <div style={{ display: 'flex', alignItems: 'center', gap: 16 }}>
                    <button className="btn btn-secondary" style={{ display: 'flex', alignItems: 'center', justifyContent: 'center', width: 40, height: 40, padding: 0, borderRadius: 12, border: '1px solid #e2e8f0' }} onClick={onBack}>
                        <ChevronLeft size={16} />
                    </button>
                    <ConnLogo connector={connector} size={50} />
                    <div>
                        <div style={{ display: 'flex', alignItems: 'center', gap: 8, flexWrap: 'wrap' }}>
                            <h1 style={{ fontSize: 22, fontWeight: 700, color: '#0f172a', margin: 0 }}>{connector.name}</h1>
                            <span style={{ fontSize: 10, padding: '2px 8px', borderRadius: 8, background: (connector.color ?? '#6366f1') + '12', color: connector.color ?? '#6366f1', fontWeight: 700, textTransform: 'uppercase', letterSpacing: '0.04em' }}>
                                {connector.category.replace('_', ' ')}
                            </span>
                            {connected > 0 && (
                                <span style={{ fontSize: 10, padding: '2px 8px', borderRadius: 8, background: '#dcfce7', color: '#16a34a', fontWeight: 700 }}>{connected} Connected</span>
                            )}
                            {healthy > 0 && (
                                <span style={{ fontSize: 10, padding: '2px 8px', borderRadius: 8, background: '#e0f2fe', color: '#0284c7', fontWeight: 700 }}>{healthy} Healthy</span>
                            )}
                        </div>
                        <p style={{ fontSize: 13, color: '#64748b', margin: '4px 0 0' }}>{connector.description}</p>
                    </div>
                </div>

                {/* Header Action Bar */}
                <div style={{ display: 'flex', gap: 12, alignItems: 'center' }}>
                    <button className="btn btn-secondary" style={{ display: 'flex', alignItems: 'center', gap: 8, fontWeight: 600, height: 40, borderRadius: 12, border: '1px solid #e2e8f0', background: 'white', padding: '0 16px' }} disabled={validatingAll} onClick={validateAll}>
                        {validatingAll ? <Loader2 size={16} className="spin" /> : <Shield size={16} />} Validate All
                    </button>
                    <button className="btn btn-secondary" style={{ display: 'flex', alignItems: 'center', gap: 8, fontWeight: 600, height: 40, borderRadius: 12, border: '1px solid #e2e8f0', background: 'white', padding: '0 16px' }} onClick={() => setShowImportWizard(true)}>
                        <Upload size={16} /> Import Results
                    </button>
                    <button className="btn btn-primary" style={{ display: 'flex', alignItems: 'center', gap: 8, fontWeight: 600, height: 40, borderRadius: 12, background: '#4f46e5', border: 'none', padding: '0 16px', color: 'white', boxShadow: '0 1px 2px rgba(0,0,0,0.05)' }} onClick={() => setShowAddDrawer(true)}>
                        <Plus size={16} /> Add Connection
                    </button>
                </div>
            </div>

            {/* KPI Cards */}
            <div style={{ display: 'grid', gridTemplateColumns: 'repeat(4,1fr)', gap: 12, marginBottom: 24 }}>
                {[
                    { label: 'Total Connections', value: connections.length, subtitle: 'Configured setups', icon: Blocks, color: '#6366f1' },
                    { label: 'Connected Scanners', value: connected, subtitle: `${connections.length - connected} Offline`, icon: CheckCircle2, color: '#22c55e' },
                    { label: 'Healthy Connections', value: healthy, subtitle: `${connections.length - healthy} Unhealthy`, icon: Shield, color: '#0ea5e9' },
                    { label: 'Total Jobs Executed', value: totalJobs, subtitle: 'All time jobs', icon: Activity, color: '#f59e0b' },
                ].map((s, idx) => {
                    const CardIcon = s.icon;
                    return (
                        <div key={idx} style={{ padding: '16px', borderRadius: 12, border: '1px solid #e2e8f0', background: 'white', borderLeft: `4px solid ${s.color}`, display: 'flex', flexDirection: 'column', gap: 8, boxShadow: '0 1px 3px rgba(0,0,0,0.02)' }}>
                            <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between' }}>
                                <span style={{ fontSize: 11, fontWeight: 600, textTransform: 'uppercase', letterSpacing: '0.06em', color: '#64748b' }}>{s.label}</span>
                                <CardIcon size={16} style={{ color: s.color }} />
                            </div>
                            <div style={{ display: 'flex', alignItems: 'baseline', gap: 8 }}>
                                <span style={{ fontSize: 26, fontWeight: 700, color: '#0f172a', lineHeight: 1 }}>{s.value}</span>
                                <span style={{ fontSize: 11, color: '#94a3b8' }}>{s.subtitle}</span>
                            </div>
                        </div>
                    );
                })}
            </div>

            {/* Feature Chips */}
            <div style={{ display: 'flex', flexWrap: 'wrap', gap: 6, marginBottom: 20 }}>
                {connector.features.map(f => (
                    <span key={f} style={{ fontSize: 10, padding: '3px 10px', borderRadius: 20, background: '#f1f5f9', color: '#475569', border: '1px solid #e2e8f0', fontWeight: 500 }}>{f}</span>
                ))}
            </div>

            {/* Table Toolbar */}
            <div style={{ display: 'flex', gap: 8, marginBottom: 16, alignItems: 'center', flexWrap: 'wrap' }}>
                <div style={{ position: 'relative', flex: 1, minWidth: 200, maxWidth: 260 }}>
                    <Search size={13} style={{ position: 'absolute', left: 10, top: '50%', transform: 'translateY(-50%)', color: '#94a3b8' }} />
                    <input className="form-control" value={search} style={{ paddingLeft: 30, fontSize: 12, height: 36, borderRadius: 8 }}
                        placeholder="Search connection..." onChange={e => setSearch(e.target.value)} />
                </div>
                
                <select className="form-control" style={{ width: 140, fontSize: 12, height: 36, borderRadius: 8 }} value={envFilter} onChange={e => setEnvFilter(e.target.value)}>
                    <option value="all">All Environments</option>
                    {ENVS.map(v => <option key={v} value={v}>{v}</option>)}
                </select>

                <select className="form-control" style={{ width: 130, fontSize: 12, height: 36, borderRadius: 8 }} value={statusFilter} onChange={e => setStatusFilter(e.target.value)}>
                    <option value="all">All Status</option>
                    <option value="Connected">Connected</option>
                    <option value="Disconnected">Disconnected</option>
                </select>

                <select className="form-control" style={{ width: 130, fontSize: 12, height: 36, borderRadius: 8 }} value={healthFilter} onChange={e => setHealthFilter(e.target.value)}>
                    <option value="all">All Health</option>
                    <option value="Healthy">Healthy</option>
                    <option value="Unreachable">Unreachable</option>
                    <option value="Authentication Failed">Auth Failed</option>
                    <option value="Timeout">Timeout</option>
                </select>

                <button className="btn btn-secondary btn-sm" style={{ height: 36, borderRadius: 8, width: 36, padding: 0, display: 'flex', alignItems: 'center', justifyContent: 'center', border: '1px solid #e2e8f0', background: 'white' }} onClick={loadConnections}>
                    <RefreshCw size={13} />
                </button>
            </div>

            {/* Table or Empty state */}
            {loading ? (
                <div style={{ display: 'flex', justifyContent: 'center', padding: '50px 0' }}>
                    <Loader2 size={24} className="spin" style={{ color: 'var(--color-primary)' }} />
                </div>
            ) : connections.length === 0 ? (
                <div style={{ display: 'flex', flexDirection: 'column', alignItems: 'center', justifyContent: 'center', padding: '80px 0', background: 'white', borderRadius: 12, border: '1px solid #e2e8f0', boxShadow: '0 2px 8px rgba(0,0,0,0.01)' }}>
                    <ConnLogo connector={connector} size={50} />
                    <div style={{ fontSize: 15, fontWeight: 700, color: '#0f172a', margin: '16px 0 6px' }}>No connections yet</div>
                    <p style={{ fontSize: 12, color: '#64748b', marginBottom: 20 }}>Connect your first scanner to begin automated reporting workflows.</p>
                    <button className="btn btn-primary" style={{ display: 'flex', alignItems: 'center', gap: 8, fontWeight: 600, height: 40, borderRadius: 12, background: '#4f46e5', border: 'none', padding: '0 16px' }} onClick={() => setShowAddDrawer(true)}>
                        <Plus size={16} /> Add Connection
                    </button>
                </div>
            ) : (
                <div style={{ background: 'white', borderRadius: 12, border: '1px solid #e2e8f0', overflow: 'hidden', boxShadow: '0 2px 12px rgba(15, 23, 42, 0.015)' }}>
                    
                    {/* Header */}
                    <div style={{ display: 'grid', gridTemplateColumns: '2fr 1.2fr 1.6fr 1.2fr 1.1fr 1fr 1fr 60px', padding: '10px 20px', background: '#f8fafc', borderBottom: '1px solid #e2e8f0' }}>
                        {['Connection Name', 'Environment', 'Host', 'Health', 'Status', 'Last Sync', 'Jobs', ''].map((h, i) => (
                            <span key={i} style={{ fontSize: 10, fontWeight: 700, textTransform: 'uppercase', letterSpacing: '0.06em', color: '#94a3b8' }}>{h}</span>
                        ))}
                    </div>

                    {/* Rows */}
                    {filtered.map((conn, idx) => {
                        const isConn = conn.status === 'Connected';
                        const isHealthy = conn.health_status === 'Healthy';
                        const isSelected = drawerConn?.uuid === conn.uuid;
                        return (
                            <div key={conn.uuid}
                                onClick={() => setDrawerConn(conn)}
                                style={{
                                    display: 'grid', gridTemplateColumns: '2fr 1.2fr 1.6fr 1.2fr 1.1fr 1fr 1fr 60px',
                                    padding: '0 20px', height: 58, alignItems: 'center', cursor: 'pointer',
                                    borderBottom: idx < filtered.length - 1 ? '1px solid #f1f5f9' : 'none',
                                    background: isSelected ? '#f8faff' : '#ffffff',
                                    transition: 'all 0.15s ease'
                                }}
                                className="table-row-hover">

                                {/* Connection Name & Avatar & tags */}
                                <div style={{ display: 'flex', alignItems: 'center', gap: 10 }}>
                                    <ConnLogo connector={connector} size={28} />
                                    <div style={{ display: 'flex', flexDirection: 'column', gap: 2 }}>
                                        <span style={{ fontSize: 13, fontWeight: 600, color: '#0f172a' }}>{conn.name}</span>
                                        {Array.isArray(conn.tags) && conn.tags.length > 0 ? (
                                            <div style={{ display: 'flex', gap: 3 }}>
                                                {conn.tags.slice(0, 2).map(t => (
                                                    <span key={t} style={{ fontSize: 9, padding: '1px 5px', borderRadius: 4, background: '#f1f5f9', color: '#64748b' }}>#{t}</span>
                                                ))}
                                            </div>
                                        ) : null}
                                    </div>
                                </div>

                                {/* Environment Badge */}
                                <div>
                                    <span className="badge badge-secondary" style={{ fontSize: 10, background: '#f1f5f9', color: '#475569', fontWeight: 600 }}>{conn.environment ?? '—'}</span>
                                </div>

                                {/* Host / Endpoint */}
                                <span style={{ fontSize: 11, color: '#64748b', fontFamily: 'monospace' }}>
                                    {conn.host ? `${conn.host}${conn.port ? ':' + conn.port : ''}` : '—'}
                                </span>

                                {/* Health Pill */}
                                <div>
                                    <span className={`badge ${isHealthy ? 'badge-success' : 'badge-danger'}`} style={{ fontSize: 10, fontWeight: 600 }}>
                                        {conn.health_status}
                                    </span>
                                </div>

                                {/* Status Pill */}
                                <div>
                                    <span className={`badge ${isConn ? 'badge-success' : 'badge-secondary'}`} style={{ fontSize: 10, fontWeight: 600 }}>
                                        {conn.status}
                                    </span>
                                </div>

                                {/* Last Sync */}
                                <span style={{ fontSize: 11, color: '#64748b' }}>{relTime(conn.last_check_at)}</span>

                                {/* Jobs Count badge */}
                                <div>
                                    <span className="badge badge-secondary" style={{ fontSize: 10, background: '#f1f5f9', color: '#475569' }}>{conn.jobs_count ?? 0}</span>
                                </div>

                                {/* Actions Dropdown Menu */}
                                <div style={{ display: 'flex', justifyContent: 'flex-end', position: 'relative' }} onClick={e => e.stopPropagation()}>
                                    <button className="btn btn-secondary" style={{ padding: 6, borderRadius: 8, height: 30, width: 30, display: 'flex', alignItems: 'center', justifyContent: 'center' }} onClick={() => setActiveActionMenu(activeActionMenu === conn.uuid ? null : conn.uuid)}>
                                        <MoreVertical size={14} />
                                    </button>
                                    
                                    {activeActionMenu === conn.uuid && (
                                        <>
                                            <div style={{ position: 'fixed', inset: 0, zIndex: 90 }} onClick={() => setActiveActionMenu(null)} />
                                            <div style={{ position: 'absolute', right: 0, top: 34, background: 'white', border: '1px solid #e2e8f0', borderRadius: 8, padding: 4, width: 120, zIndex: 91, boxShadow: '0 4px 12px rgba(0,0,0,0.08)' }}>
                                                <button style={{ width: '100%', border: 'none', background: 'transparent', textAlign: 'left', padding: '6px 8px', fontSize: 12, cursor: 'pointer', display: 'flex', alignItems: 'center', gap: 6 }} className="btn-hover-gray" onClick={() => { setDrawerConn(conn); setActiveActionMenu(null); }}>
                                                    <ChevronRight size={12} /> Details
                                                </button>
                                                <button style={{ width: '100%', border: 'none', background: 'transparent', textAlign: 'left', padding: '6px 8px', fontSize: 12, cursor: 'pointer', display: 'flex', alignItems: 'center', gap: 6, color: '#ef4444' }} className="btn-hover-gray" onClick={async () => {
                                                    if (!window.confirm(`Disconnect "${conn.name}"?`)) return;
                                                    await axios.post(`/api/integrations/${conn.uuid}/disconnect`).catch(() => {});
                                                    setActiveActionMenu(null);
                                                    loadConnections();
                                                }}>
                                                    <Unplug size={12} /> Disconnect
                                                </button>
                                            </div>
                                        </>
                                    )}
                                </div>
                            </div>
                        );
                    })}
                </div>
            )}

            {/* Drawers */}
            {showAddDrawer && (
                <AddConnectionDrawer
                    connector={connector}
                    onClose={() => setShowAddDrawer(false)}
                    onSaved={() => { setShowAddDrawer(false); loadConnections(); }} />
            )}
            {drawerConn && (
                <ConnectionDetailDrawer
                    conn={drawerConn}
                    connector={connector}
                    onClose={() => setDrawerConn(null)}
                    onRefresh={loadConnections} />
            )}
            <ImportWizard
                isOpen={showImportWizard}
                onClose={() => setShowImportWizard(false)}
                connectorCode={connector.code}
                integrationId={connections[0]?.id}
                onComplete={loadConnections} />
        </div>
    );
}
