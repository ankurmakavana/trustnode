import React, { useState, useEffect } from 'react';
import { 
    ArrowLeft, Loader2, AlertTriangle, Shield, CheckCircle2, XCircle, Search, SlidersHorizontal, Info,
    Server, Crosshair, HelpCircle, Activity, Clock, ShieldAlert, ArrowUpRight, Copy
} from 'lucide-react';
import axios from 'axios';

export default function ComplianceDetailPage({ frameworkCode, onBack }) {
    const [framework, setFramework] = useState(null);
    const [controls, setControls] = useState([]);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState(null);
    const [activeControl, setActiveControl] = useState(null);

    // Filters
    const [search, setSearch] = useState('');
    const [statusFilter, setStatusFilter] = useState('');

    const fetchFrameworkDetails = async () => {
        setLoading(true);
        setError(null);
        try {
            const response = await axios.get(`/api/compliance/${frameworkCode}`);
            setFramework(response.data.framework);
            const ctrls = response.data.controls || [];
            setControls(ctrls);
            if (ctrls.length > 0) {
                setActiveControl(ctrls[0]);
            }
        } catch (err) {
            console.error('Failed to load framework details:', err);
            setError('Failed to fetch framework controls profile.');
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => {
        fetchFrameworkDetails();
    }, [frameworkCode]);

    const filteredControls = controls.filter(c => {
        const matchSearch = (c.code || '').toLowerCase().includes(search.toLowerCase()) ||
                            (c.title || '').toLowerCase().includes(search.toLowerCase()) ||
                            (c.description || '').toLowerCase().includes(search.toLowerCase());
        const matchStatus = statusFilter === '' || c.status === statusFilter;
        return matchSearch && matchStatus;
    });

    if (loading) {
        return (
            <div className="bg-white border border-slate-200 rounded-xl p-12 shadow-sm flex items-center justify-center min-h-[50vh]">
                <Loader2 className="animate-spin text-brand-600 mr-2" size={24} />
                <span className="text-sm font-semibold text-slate-500">Retrieving framework controls alignment...</span>
            </div>
        );
    }

    if (error || !framework) {
        return (
            <div className="bg-white border border-slate-200 rounded-xl p-12 text-center space-y-4 max-w-md mx-auto shadow-sm">
                <div className="w-12 h-12 rounded-full bg-rose-50 border border-rose-200 flex items-center justify-center mx-auto text-rose-500">
                    <AlertTriangle size={20} />
                </div>
                <div>
                    <h3 className="text-sm font-bold text-slate-805">Error Loading Framework</h3>
                    <p className="text-xs text-slate-500 mt-1">{error || 'Framework profile not found.'}</p>
                </div>
                <button
                    onClick={onBack}
                    className="px-4 py-2 text-xs font-semibold text-slate-700 bg-white border border-slate-300 hover:bg-slate-50 rounded-lg shadow-sm transition"
                >
                    Back to Matrix
                </button>
            </div>
        );
    }

    return (
        <div className="space-y-6 max-w-7xl mx-auto">
            {/* Header */}
            <div className="flex items-center gap-3">
                <button
                    onClick={onBack}
                    className="p-1.5 border border-slate-200 rounded-lg hover:bg-slate-50 text-slate-500 hover:text-slate-805 transition"
                >
                    <ArrowLeft size={16} />
                </button>
                <div>
                    <span className="text-xs font-mono font-bold text-slate-400">Compliance framework</span>
                    <h1 className="text-lg font-bold text-slate-900 tracking-tight mt-0.5">{framework.name} ({framework.code})</h1>
                </div>
            </div>

            {/* Layout Grid */}
            <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
                
                {/* Left Side: Controls list with search & filter */}
                <div className="lg:col-span-2 space-y-4">
                    <div className="bg-white border border-slate-200 rounded-xl p-4 shadow-sm space-y-4">
                        {/* Search & filters */}
                        <div className="grid grid-cols-1 md:grid-cols-3 gap-3">
                            <div className="md:col-span-2 relative">
                                <Search className="absolute left-3 top-2.5 text-slate-400" size={14} />
                                <input
                                    type="text"
                                    placeholder="Search controls by code, title..."
                                    value={search}
                                    onChange={(e) => setSearch(e.target.value)}
                                    className="w-full pl-8 pr-3 py-2 border border-slate-205 rounded-lg text-xs bg-white focus:outline-none focus:border-brand-400"
                                />
                            </div>
                            <select
                                value={statusFilter}
                                onChange={(e) => setStatusFilter(e.target.value)}
                                className="border border-slate-205 rounded-lg text-xs py-2 px-3 focus:outline-none focus:border-brand-400 bg-white text-slate-700"
                            >
                                <option value="">All Statuses</option>
                                <option value="Passed">Passed (Compliant)</option>
                                <option value="Failed">Failed (Non-compliant)</option>
                            </select>
                        </div>

                        {/* Controls mapping table */}
                        {filteredControls.length === 0 ? (
                            <p className="text-xs text-slate-400 italic py-6 text-center">No compliance controls match the criteria.</p>
                        ) : (
                            <div className="divide-y divide-slate-100">
                                {filteredControls.map(c => (
                                    <div 
                                        key={c.id} 
                                        onClick={() => setActiveControl(c)}
                                        className={`py-3.5 px-3 rounded-xl flex items-center justify-between gap-4 cursor-pointer transition ${
                                            activeControl?.id === c.id ? 'bg-slate-50' : 'hover:bg-slate-50/50'
                                        }`}
                                    >
                                        <div className="space-y-0.5 max-w-[70%]">
                                            <div className="flex items-center gap-2">
                                                <span className="font-bold font-mono text-[10.5px] text-slate-500">{c.code}</span>
                                                <span className="font-bold text-xs text-slate-800 truncate">{c.title}</span>
                                            </div>
                                            <p className="text-[11px] text-slate-500 line-clamp-1">{c.description}</p>
                                        </div>
                                        <span className={`px-2 py-0.5 rounded text-[9.5px] font-bold border ${
                                            c.status === 'Passed' ? 'bg-emerald-50 border-emerald-200 text-emerald-800' : 'bg-rose-50 border-rose-200 text-rose-800'
                                        }`}>{c.status}</span>
                                    </div>
                                ))}
                            </div>
                        )}
                    </div>
                </div>

                {/* Right Side: Compliance control mapping details panel */}
                <div className="lg:col-span-1 space-y-6">
                    {activeControl ? (
                        <div className="bg-white border border-slate-200 rounded-2xl p-5.5 shadow-sm space-y-5.5">
                            {/* Control Overview header */}
                            <div className="space-y-2 pb-3.5 border-b border-slate-100">
                                <div className="flex items-center justify-between">
                                    <span className="text-[10px] font-bold font-mono text-slate-450 tracking-wider">{activeControl.code} CONTROL</span>
                                    <span className={`px-2 py-0.5 rounded text-[9px] font-bold border ${
                                        activeControl.status === 'Passed' ? 'bg-emerald-50 border-emerald-200 text-emerald-800' : 'bg-rose-50 border-rose-200 text-rose-800'
                                    }`}>{activeControl.status}</span>
                                </div>
                                <h3 className="font-bold text-sm text-slate-850">{activeControl.title}</h3>
                                <p className="text-[11.5px] text-slate-500 leading-relaxed">{activeControl.description}</p>
                            </div>

                            {/* Mapped active findings */}
                            <div className="space-y-3">
                                <span className="text-[10px] uppercase font-bold text-slate-400 tracking-wider flex items-center gap-1.5">
                                    <ShieldAlert size={13} className="text-slate-400" />
                                    Mapped Security Findings ({activeControl.findings?.length || 0})
                                </span>
                                {!activeControl.findings || activeControl.findings.length === 0 ? (
                                    <div className="p-3 bg-emerald-50 border border-emerald-100 rounded-xl text-emerald-800 text-[11px] flex items-center gap-2">
                                        <CheckCircle2 size={14} className="text-emerald-500" />
                                        <span>No active vulnerability mappings. Control passed.</span>
                                    </div>
                                ) : (
                                    <div className="space-y-2">
                                        {activeControl.findings.map(f => (
                                            <div key={f.id} className="p-3 border border-slate-200 bg-slate-55 bg-slate-50/50 rounded-xl space-y-1.5">
                                                <div className="flex items-center justify-between text-xs">
                                                    <span className="font-bold text-slate-800">{f.title}</span>
                                                    <span className={`px-1.5 py-0.2 rounded text-[9px] font-bold border ${
                                                        f.severity === 'Critical' ? 'bg-rose-50 border-rose-200 text-rose-800' :
                                                        f.severity === 'High' ? 'bg-orange-50 border-orange-200 text-orange-850' :
                                                                                'bg-amber-50 border-amber-200 text-amber-800'
                                                    }`}>{f.severity}</span>
                                                </div>
                                                <div className="flex justify-between items-center text-[10px] text-slate-450">
                                                    <span className="font-mono">{f.finding_id}</span>
                                                    <span>CVSS: {f.cvss_score || 'N/A'}</span>
                                                </div>

                                                {/* Affected Asset & Target scopes */}
                                                {(f.asset || f.target) && (
                                                    <div className="border-t border-slate-100 pt-2 flex flex-col gap-1 text-[10px] text-slate-500">
                                                        {f.asset && (
                                                            <span className="flex items-center gap-1.5">
                                                                <Server size={11} className="text-slate-400" />
                                                                Asset: {f.asset.name}
                                                            </span>
                                                        )}
                                                        {f.target && (
                                                            <span className="flex items-center gap-1.5">
                                                                <Crosshair size={11} className="text-slate-400" />
                                                                Target Scope: {f.target.destination}
                                                            </span>
                                                        )}
                                                    </div>
                                                )}

                                                {/* Remediation guidance */}
                                                {f.remediation && (
                                                    <div className="bg-slate-100 p-2.5 rounded-lg border border-slate-150 text-[10px] text-slate-600 mt-2 space-y-0.5">
                                                        <span className="font-bold block uppercase text-[8px] text-slate-450">Remediation Recommendation</span>
                                                        <p className="leading-relaxed">{f.remediation}</p>
                                                    </div>
                                                )}
                                            </div>
                                        ))}
                                    </div>
                                )}
                            </div>
                        </div>
                    ) : (
                        <div className="bg-white border border-slate-200 rounded-2xl p-6 text-center shadow-sm">
                            <HelpCircle size={24} className="text-slate-300 mx-auto mb-2" />
                            <p className="text-xs text-slate-400">Select a control from the list to display its mapping profile dossier details.</p>
                        </div>
                    )}
                </div>

            </div>
        </div>
    );
}