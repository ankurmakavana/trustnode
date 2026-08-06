import React, { useState, useEffect } from 'react';
import { 
    ArrowLeft, Edit2, Shield, Cpu, Crosshair, User, Clock, 
    Loader2, AlertTriangle, FileText, CheckCircle2, Link as LinkIcon, ExternalLink, Info, ShieldAlert, Plus, ArrowUpRight
} from 'lucide-react';
import axios from 'axios';

export default function RiskDetailPage({ riskId, onBack, onEdit }) {
    const [risk, setRisk] = useState(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState(null);
    const [isManagerOrAdmin, setIsManagerOrAdmin] = useState(false);

    // Treatment Form state
    const [showTreatmentForm, setShowTreatmentForm] = useState(false);
    const [treatmentType, setTreatmentType] = useState('Mitigate');
    const [treatmentDesc, setTreatmentDesc] = useState('');
    const [targetDate, setTargetDate] = useState('');

    const fetchRiskDetails = async () => {
        setLoading(true);
        setError(null);
        try {
            const response = await axios.get(`/api/risks/${riskId}`);
            setRisk(response.data.data);
        } catch (err) {
            console.error('Failed to load risk register profile:', err);
            setError('Failed to load risk details.');
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => {
        fetchRiskDetails();
    }, [riskId]);

    useEffect(() => {
        const checkRole = async () => {
            try {
                await axios.get('/api/dashboard/stats');
                setIsManagerOrAdmin(true);
            } catch (err) {
                setIsManagerOrAdmin(false);
            }
        };
        checkRole();
    }, []);

    const handleAcceptRisk = async () => {
        if (!confirm('Are you sure you want to formally accept this business risk? This action will log your authorization in the security audit history.')) return;
        try {
            await axios.post(`/api/risks/${riskId}/accept`);
            fetchRiskDetails();
        } catch (err) {
            alert('Failed to authorize risk acceptance: ' + (err.response?.data?.message || 'Access denied.'));
        }
    };

    const handleAddTreatment = async (e) => {
        e.preventDefault();
        if (!treatmentDesc) return;
        try {
            await axios.post(`/api/risks/${riskId}/treatment`, {
                treatment_type: treatmentType,
                description: treatmentDesc,
                target_date: targetDate || null
            });
            setShowTreatmentForm(false);
            setTreatmentDesc('');
            setTargetDate('');
            fetchRiskDetails();
        } catch (err) {
            alert('Failed to save treatment plan: ' + (err.response?.data?.message || 'Invalid input.'));
        }
    };

    const getLevelColor = (level) => {
        switch (level) {
            case 'Critical': return 'bg-rose-50 border-rose-200 text-rose-800';
            case 'High': return 'bg-orange-50 border-orange-200 text-orange-850';
            case 'Medium': return 'bg-amber-50 border-amber-200 text-amber-800';
            default: return 'bg-emerald-50 border-emerald-200 text-emerald-800';
        }
    };

    const getStatusBadge = (st) => {
        switch (st) {
            case 'Mitigating': return 'bg-blue-100 text-blue-800 border-blue-200';
            case 'Accepted': return 'bg-purple-100 text-purple-800 border-purple-200';
            case 'Resolved': return 'bg-emerald-100 text-emerald-800 border-emerald-200';
            case 'Closed': return 'bg-slate-100 text-slate-650 border-slate-200';
            default: return 'bg-amber-105 text-amber-800 border-amber-200';
        }
    };

    if (loading) {
        return (
            <div className="bg-white border border-slate-200 rounded-xl p-12 shadow-sm flex items-center justify-center min-h-[40vh]">
                <Loader2 className="animate-spin text-brand-600 mr-2" size={24} />
                <span className="text-sm font-semibold text-slate-500">Retrieving risk details profile...</span>
            </div>
        );
    }

    if (error || !risk) {
        return (
            <div className="bg-white border border-slate-200 rounded-xl p-12 text-center space-y-4 max-w-md mx-auto shadow-sm">
                <div className="w-12 h-12 rounded-full bg-rose-50 border border-rose-200 flex items-center justify-center mx-auto text-rose-500">
                    <AlertTriangle size={20} />
                </div>
                <div>
                    <h3 className="text-sm font-bold text-slate-800">Error Loading Profile</h3>
                    <p className="text-xs text-slate-500 mt-1">{error || 'Risk profile not found.'}</p>
                </div>
                <button
                    onClick={onBack}
                    className="px-4 py-2 text-xs font-semibold text-slate-700 bg-white border border-slate-300 hover:bg-slate-50 rounded-lg shadow-sm transition"
                >
                    Back to Register
                </button>
            </div>
        );
    }

    return (
        <div className="space-y-6 max-w-7xl mx-auto">
            {/* Header & Actions */}
            <div className="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                <div className="flex items-center gap-3">
                    <button
                        onClick={onBack}
                        className="p-1.5 border border-slate-200 rounded-lg hover:bg-slate-50 text-slate-500 hover:text-slate-800 transition"
                    >
                        <ArrowLeft size={16} />
                    </button>
                    <div>
                        <div className="flex items-center gap-2">
                            <span className="text-xs font-mono font-bold text-slate-400">{risk.risk_id}</span>
                            <span className={`px-2 py-0.5 rounded text-[10px] font-bold border ${getStatusBadge(risk.status)}`}>
                                {risk.status}
                            </span>
                        </div>
                        <h1 className="text-lg font-bold text-slate-900 tracking-tight mt-0.5">{risk.title}</h1>
                    </div>
                </div>

                <div className="flex items-center gap-2">
                    {risk.status !== 'Accepted' && isManagerOrAdmin && (
                        <button
                            onClick={handleAcceptRisk}
                            className="inline-flex items-center justify-center gap-1.5 px-3.5 py-2 text-xs font-semibold text-white bg-purple-600 hover:bg-purple-700 active:bg-purple-800 rounded-lg shadow-sm border border-purple-700 transition"
                        >
                            Accept Risk
                        </button>
                    )}
                    {isManagerOrAdmin && (
                        <button
                            onClick={() => onEdit(risk.id)}
                            className="inline-flex items-center justify-center gap-1.5 px-3.5 py-2 text-xs font-semibold text-slate-700 bg-white hover:bg-slate-50 rounded-lg shadow-sm border border-slate-300 transition"
                        >
                            <Edit2 size={14} />
                            Modify Risk
                        </button>
                    )}
                </div>
            </div>

            {/* Formal Acceptance Notice */}
            {risk.accepted && (
                <div className="p-4 rounded-xl border bg-purple-50 border-purple-200 text-purple-900 text-xs flex items-start gap-3 shadow-sm">
                    <Info size={16} className="shrink-0 mt-0.5 text-purple-600" />
                    <div className="space-y-1">
                        <span className="font-bold uppercase tracking-wider text-[10px] text-purple-850">Formal Risk Acceptance Signed Off</span>
                        <p className="leading-relaxed text-[11px]">
                            This security risk has been formally accepted by the corporate workspace administrator. <br/>
                            Authorized By: <strong>{risk.accepter ? risk.accepter.name : 'System Admin'}</strong> on {new Date(risk.accepted_at).toLocaleString()}
                        </p>
                    </div>
                </div>
            )}

            {/* Layout Grid */}
            <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
                {/* Main Content columns */}
                <div className="lg:col-span-2 space-y-6">
                    
                    {/* Overview & Impact Cards */}
                    <div className="bg-white border border-slate-200 rounded-xl p-6 shadow-sm space-y-5">
                        <div>
                            <h2 className="text-xs font-bold text-slate-800 uppercase tracking-wider border-b border-slate-100 pb-2 flex items-center gap-2">
                                <Info size={14} className="text-slate-400" />
                                Risk Overview
                            </h2>
                            <p className="text-xs text-slate-600 mt-3 leading-relaxed whitespace-pre-line">
                                {risk.description || 'No description provided.'}
                            </p>
                        </div>
                        
                        <div className="grid grid-cols-1 md:grid-cols-2 gap-4 border-t border-slate-100 pt-4">
                            <div className="space-y-1.5">
                                <h3 className="text-xs font-bold text-slate-800 uppercase tracking-wider">Business Impact</h3>
                                <p className="text-xs text-slate-600 leading-relaxed">
                                    {risk.business_impact || 'Not specified.'}
                                </p>
                            </div>
                            <div className="space-y-1.5">
                                <h3 className="text-xs font-bold text-slate-800 uppercase tracking-wider">Technical Impact</h3>
                                <p className="text-xs text-slate-600 leading-relaxed">
                                    {risk.technical_impact || 'Not specified.'}
                                </p>
                            </div>
                        </div>
                    </div>

                    {/* Related findings links list */}
                    <div className="bg-white border border-slate-200 rounded-xl p-6 shadow-sm space-y-4">
                        <h2 className="text-xs font-bold text-slate-800 uppercase tracking-wider border-b border-slate-100 pb-2 flex items-center gap-2">
                            <ShieldAlert size={14} className="text-slate-400" />
                            Linked Vulnerabilities
                        </h2>
                        {!risk.findings || risk.findings.length === 0 ? (
                            <p className="text-xs text-slate-400 italic">No scan findings linked to this risk register.</p>
                        ) : (
                            <div className="divide-y divide-slate-100">
                                {risk.findings.map(f => (
                                    <div key={f.id} className="py-3 flex items-center justify-between gap-4">
                                        <div className="space-y-0.5">
                                            <span className="text-xs font-bold text-slate-805 block">{f.title}</span>
                                            <span className="text-[9px] font-mono text-slate-400 block">{f.finding_id} · CVSS {f.cvss_score || 'N/A'}</span>
                                        </div>
                                        <span className={`px-2 py-0.5 rounded text-[9px] font-bold border ${
                                            f.severity === 'Critical' ? 'bg-rose-50 border-rose-200 text-rose-800' :
                                            f.severity === 'High' ? 'bg-orange-50 border-orange-200 text-orange-850' :
                                            f.severity === 'Medium' ? 'bg-amber-50 border-amber-200 text-amber-800' :
                                                                      'bg-emerald-50 border-emerald-200 text-emerald-800'
                                        }`}>
                                            {f.severity}
                                        </span>
                                    </div>
                                ))}
                            </div>
                        )}
                    </div>

                    {/* Treatment Strategies Panel */}
                    <div className="bg-white border border-slate-200 rounded-xl p-6 shadow-sm space-y-4">
                        <div className="flex items-center justify-between border-b border-slate-100 pb-2">
                            <h2 className="text-xs font-bold text-slate-800 uppercase tracking-wider flex items-center gap-2">
                                <CheckCircle2 size={14} className="text-slate-400" />
                                Treatment Strategies &amp; Mitigation Action Plan
                            </h2>
                            {isManagerOrAdmin && (
                                <button
                                    onClick={() => setShowTreatmentForm(!showTreatmentForm)}
                                    className="text-[10px] text-brand-600 font-bold hover:text-brand-700 flex items-center gap-1 transition"
                                >
                                    <Plus size={10} /> {showTreatmentForm ? 'Cancel' : 'Add Strategy'}
                                </button>
                            )}
                        </div>

                        {showTreatmentForm && (
                            <form onSubmit={handleAddTreatment} className="bg-slate-50/50 border border-slate-200 rounded-xl p-4 space-y-3">
                                <div className="grid grid-cols-1 md:grid-cols-2 gap-3">
                                    <div className="space-y-1">
                                        <label className="text-[10px] font-bold text-slate-500 uppercase">Strategy Type</label>
                                        <select
                                            value={treatmentType}
                                            onChange={e => setTreatmentType(e.target.value)}
                                            className="w-full border border-slate-200 rounded-lg text-xs py-1.5 px-2 bg-white focus:outline-none focus:border-brand-400"
                                        >
                                            <option value="Mitigate">Mitigate (Offset risk via controls)</option>
                                            <option value="Transfer">Transfer (Outsource risk to third party)</option>
                                            <option value="Avoid">Avoid (Stop risky process/service)</option>
                                            <option value="Accept">Accept (Authorize exception)</option>
                                        </select>
                                    </div>
                                    <div className="space-y-1">
                                        <label className="text-[10px] font-bold text-slate-500 uppercase">Target Date</label>
                                        <input
                                            type="date"
                                            value={targetDate}
                                            onChange={e => setTargetDate(e.target.value)}
                                            className="w-full border border-slate-200 rounded-lg text-xs py-1.5 px-2 bg-white focus:outline-none"
                                        />
                                    </div>
                                </div>
                                <div className="space-y-1">
                                    <label className="text-[10px] font-bold text-slate-500 uppercase">Description / Action Items</label>
                                    <textarea
                                        rows={2.5}
                                        placeholder="Define treatment actions, mitigation controls to deploy..."
                                        value={treatmentDesc}
                                        onChange={e => setTreatmentDesc(e.target.value)}
                                        className="w-full border border-slate-200 rounded-lg text-xs py-1.5 px-2 bg-white focus:outline-none focus:border-brand-400"
                                    />
                                </div>
                                <button
                                    type="submit"
                                    className="w-full py-1.5 bg-brand-600 text-white rounded-lg text-xs font-bold shadow transition hover:bg-brand-700"
                                >
                                    Save Strategy Plan
                                </button>
                            </form>
                        )}

                        {!risk.treatments || risk.treatments.length === 0 ? (
                            <p className="text-xs text-slate-400 italic">No mitigation strategy registered yet.</p>
                        ) : (
                            <div className="space-y-3">
                                {risk.treatments.map(t => (
                                    <div key={t.id} className="border border-slate-150 rounded-xl p-3.5 bg-slate-50/30 flex justify-between items-start gap-4">
                                        <div className="space-y-1">
                                            <div className="flex items-center gap-2">
                                                <span className="font-bold text-xs text-slate-800">{t.treatment_type}</span>
                                                <span className="text-[9px] bg-blue-50 text-blue-600 border border-blue-100 font-bold px-1.5 py-0.2 rounded uppercase">{t.status}</span>
                                            </div>
                                            <p className="text-xs text-slate-600 leading-relaxed">{t.description}</p>
                                        </div>
                                        {t.target_date && (
                                            <span className="text-[9.5px] font-mono text-slate-400 shrink-0">Target: {new Date(t.target_date).toLocaleDateString()}</span>
                                        )}
                                    </div>
                                ))}
                            </div>
                        )}
                    </div>
                </div>

                {/* Right Sidebar panels */}
                <div className="space-y-6">
                    
                    {/* Core Matrix scores */}
                    <div className="bg-white border border-slate-200 rounded-xl p-5 shadow-sm space-y-4">
                        <h3 className="text-xs font-bold text-slate-800 uppercase tracking-wider border-b border-slate-100 pb-2">Risk Parameters</h3>
                        
                        <div className="space-y-3.5 text-xs">
                            <div className="flex justify-between items-center">
                                <span className="text-slate-400 font-semibold">Likelihood Level</span>
                                <span className="font-bold text-slate-700">{risk.likelihood}</span>
                            </div>
                            <div className="flex justify-between items-center">
                                <span className="text-slate-400 font-semibold">Impact Level</span>
                                <span className="font-bold text-slate-700">{risk.impact}</span>
                            </div>
                            <div className="flex justify-between items-center">
                                <span className="text-slate-400 font-semibold">Risk Score</span>
                                <span className="font-mono font-bold text-slate-800 bg-slate-100 border border-slate-200 px-1.5 py-0.5 rounded">
                                    {risk.risk_score} / 25
                                </span>
                            </div>
                            <div className="flex justify-between items-center">
                                <span className="text-slate-400 font-semibold">Severity Rating</span>
                                <span className={`px-2 py-0.5 rounded text-[10px] font-bold border ${getLevelColor(risk.risk_level)}`}>
                                    {risk.risk_level}
                                </span>
                            </div>
                            <div className="flex justify-between items-center border-t border-slate-100 pt-2.5">
                                <span className="text-slate-400 font-semibold">Owner</span>
                                <span className="font-semibold text-slate-700">{risk.owner ? risk.owner.name : 'Unassigned'}</span>
                            </div>
                            <div className="flex justify-between items-center">
                                <span className="text-slate-400 font-semibold">Due Date</span>
                                <span className="font-mono text-slate-700">{risk.due_date ? new Date(risk.due_date).toLocaleDateString() : 'N/A'}</span>
                            </div>
                            <div className="flex justify-between items-center">
                                <span className="text-slate-400 font-semibold">Review Date</span>
                                <span className="font-mono text-slate-700">{risk.review_date ? new Date(risk.review_date).toLocaleDateString() : 'N/A'}</span>
                            </div>
                        </div>
                    </div>

                    {/* Change logs timeline */}
                    <div className="bg-white border border-slate-200 rounded-xl p-5 shadow-sm space-y-4">
                        <h3 className="text-xs font-bold text-slate-800 uppercase tracking-wider border-b border-slate-100 pb-2">Change History</h3>
                        {!risk.history || risk.history.length === 0 ? (
                            <p className="text-[11px] text-slate-400 italic">No changes logged.</p>
                        ) : (
                            <div className="space-y-3.5 relative before:absolute before:left-2 before:top-2 before:bottom-2 before:w-0.5 before:bg-slate-100">
                                {risk.history.map(log => (
                                    <div key={log.id} className="flex gap-3 items-start relative">
                                        <div className="w-4.5 h-4.5 rounded-full border-2 border-white bg-slate-200 flex items-center justify-center shrink-0 text-slate-550 z-10">
                                            <Clock size={10} />
                                        </div>
                                        <div className="space-y-0.5">
                                            <p className="text-[11px] text-slate-655">
                                                <span className="font-semibold text-slate-800">{log.user ? log.user.name : 'System'}</span>{' '}
                                                {log.action} the risk.
                                            </p>
                                            {log.description && <span className="text-[10px] text-slate-450 block">{log.description}</span>}
                                            <span className="text-[9px] text-slate-400 block font-mono">
                                                {new Date(log.created_at).toLocaleString()}
                                            </span>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        )}
                    </div>

                </div>
            </div>
        </div>
    );
}