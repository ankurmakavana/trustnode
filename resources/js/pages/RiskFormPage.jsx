import React, { useState, useEffect } from 'react';
import { ArrowLeft, Loader2, Save, X, AlertTriangle, Shield } from 'lucide-react';
import axios from 'axios';

export default function RiskFormPage({ riskId, onSave, onCancel }) {
    const [title, setTitle] = useState('');
    const [description, setDescription] = useState('');
    const [businessImpact, setBusinessImpact] = useState('');
    const [technicalImpact, setTechnicalImpact] = useState('');
    const [likelihood, setLikelihood] = useState('Possible');
    const [impact, setImpact] = useState('Moderate');
    const [status, setStatus] = useState('Open');
    const [ownerId, setOwnerId] = useState('');
    const [dueDate, setDueDate] = useState('');
    const [reviewDate, setReviewDate] = useState('');
    const [selectedFindings, setSelectedFindings] = useState([]);

    const [findings, setFindings] = useState([]);
    const [users, setUsers] = useState([]);
    const [loading, setLoading] = useState(false);
    const [saving, setSaving] = useState(false);
    const [errors, setErrors] = useState({});

    const isEdit = !!riskId;

    useEffect(() => {
        const fetchData = async () => {
            setLoading(true);
            try {
                const [fRes, aRes] = await Promise.all([
                    axios.get('/api/findings', { params: { per_page: 100 } }),
                    axios.get('/api/assets') // dummy to verify session
                ]);
                setFindings(fRes.data.data || []);
                setUsers([
                    { id: 1, name: 'TrustNode Admin' },
                    { id: 2, name: 'Security Analyst' }
                ]);

                if (isEdit) {
                    const rRes = await axios.get(`/api/risks/${riskId}`);
                    const r = rRes.data.data;
                    setTitle(r.title || '');
                    setDescription(r.description || '');
                    setBusinessImpact(r.business_impact || '');
                    setTechnicalImpact(r.technical_impact || '');
                    setLikelihood(r.likelihood || 'Possible');
                    setImpact(r.impact || 'Moderate');
                    setStatus(r.status || 'Open');
                    setOwnerId(r.owner_id || '');
                    setDueDate(r.due_date ? r.due_date.split('T')[0] : '');
                    setReviewDate(r.review_date ? r.review_date.split('T')[0] : '');
                    setSelectedFindings(r.findings ? r.findings.map(f => f.id) : []);
                }
            } catch (err) {
                console.error(err);
                alert('Failed to load form config options.');
            } finally {
                setLoading(false);
            }
        };
        fetchData();
    }, [riskId]);

    const handleFindingToggle = (id) => {
        if (selectedFindings.includes(id)) {
            setSelectedFindings(selectedFindings.filter(x => x !== id));
        } else {
            setSelectedFindings([...selectedFindings, id]);
        }
    };

    const handleSubmit = async (e) => {
        e.preventDefault();
        setSaving(true);
        setErrors({});
        try {
            const payload = {
                title,
                description,
                business_impact: businessImpact,
                technical_impact: technicalImpact,
                likelihood,
                impact,
                status,
                owner_id: ownerId || null,
                due_date: dueDate || null,
                review_date: reviewDate || null,
                findings: selectedFindings
            };
            if (isEdit) {
                await axios.put(`/api/risks/${riskId}`, payload);
            } else {
                await axios.post('/api/risks', payload);
            }
            onSave();
        } catch (err) {
            if (err.response?.data?.errors) {
                setErrors(err.response.data.errors);
            } else {
                alert('Failed to save risk registers.');
            }
        } finally {
            setSaving(false);
        }
    };

    // Realtime preview calculator
    const likelihoodMap = { 'Almost Certain': 5, 'Likely': 4, 'Possible': 3, 'Unlikely': 2, 'Rare': 1 };
    const impactMap = { 'Catastrophic': 5, 'Major': 4, 'Moderate': 3, 'Minor': 2, 'Negligible': 1 };
    const computedScore = (likelihoodMap[likelihood] || 3) * (impactMap[impact] || 3);
    const getLevelText = (score) => {
        if (score >= 16) return 'Critical';
        if (score >= 10) return 'High';
        if (score >= 5) return 'Medium';
        return 'Low';
    };
    const levelText = getLevelText(computedScore);

    if (loading) {
        return (
            <div className="bg-white border border-slate-200 rounded-xl p-12 shadow-sm flex items-center justify-center min-h-[40vh]">
                <Loader2 className="animate-spin text-brand-600 mr-2" size={24} />
                <span className="text-sm font-semibold text-slate-500">Loading form schema...</span>
            </div>
        );
    }

    return (
        <div className="max-w-3xl mx-auto space-y-6">
            <div className="flex items-center gap-3">
                <button
                    onClick={onCancel}
                    className="p-1.5 border border-slate-200 rounded-lg hover:bg-slate-50 text-slate-500 hover:text-slate-800 transition"
                >
                    <ArrowLeft size={16} />
                </button>
                <div>
                    <span className="text-xs font-mono font-bold text-slate-400">Risk Register</span>
                    <h1 className="text-lg font-bold text-slate-900 tracking-tight mt-0.5">
                        {isEdit ? 'Modify Corporate Risk' : 'Register Corporate Risk'}
                    </h1>
                </div>
            </div>

            <form onSubmit={handleSubmit} className="bg-white border border-slate-200 rounded-xl p-6 shadow-sm space-y-5">
                
                {/* Title & Status */}
                <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <div className="md:col-span-3 space-y-1.5">
                        <label className="text-xs font-bold text-slate-700 block">Risk Title</label>
                        <input
                            type="text"
                            value={title}
                            onChange={e => setTitle(e.target.value)}
                            placeholder="e.g. Insecure Port Configurations on Perimeter API Server"
                            className="w-full border border-slate-200 rounded-lg text-xs py-2 px-3 focus:outline-none focus:border-brand-400 bg-white"
                            required
                        />
                        {errors.title && <p className="text-[10px] text-rose-600 font-semibold">{errors.title[0]}</p>}
                    </div>
                    <div className="space-y-1.5">
                        <label className="text-xs font-bold text-slate-700 block">Status</label>
                        <select
                            value={status}
                            onChange={e => setStatus(e.target.value)}
                            className="w-full border border-slate-200 rounded-lg text-xs py-2 px-3 focus:outline-none focus:border-brand-400 bg-white"
                        >
                            <option value="Open">Open</option>
                            <option value="Mitigating">Mitigating</option>
                            <option value="Accepted">Accepted</option>
                            <option value="Resolved">Resolved</option>
                            <option value="Closed">Closed</option>
                        </select>
                    </div>
                </div>

                {/* Description */}
                <div className="space-y-1.5">
                    <label className="text-xs font-bold text-slate-700 block">Description</label>
                    <textarea
                        rows={3}
                        value={description}
                        onChange={e => setDescription(e.target.value)}
                        placeholder="Detailed explanation of the risk, vulnerable nodes, threat vectors, etc..."
                        className="w-full border border-slate-200 rounded-lg text-xs py-2 px-3 focus:outline-none focus:border-brand-400 bg-white"
                    />
                </div>

                {/* Impact details */}
                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div className="space-y-1.5">
                        <label className="text-xs font-bold text-slate-700 block">Business Impact</label>
                        <textarea
                            rows={2.5}
                            value={businessImpact}
                            onChange={e => setBusinessImpact(e.target.value)}
                            placeholder="Regulatory fines, privacy compliance breach liability, branding damages..."
                            className="w-full border border-slate-200 rounded-lg text-xs py-2 px-3 focus:outline-none bg-white"
                        />
                    </div>
                    <div className="space-y-1.5">
                        <label className="text-xs font-bold text-slate-700 block">Technical Impact</label>
                        <textarea
                            rows={2.5}
                            value={technicalImpact}
                            onChange={e => setTechnicalImpact(e.target.value)}
                            placeholder="Host server access credentials compromise, database schema leakage..."
                            className="w-full border border-slate-200 rounded-lg text-xs py-2 px-3 focus:outline-none bg-white"
                        />
                    </div>
                </div>

                {/* Likelihood & Impact Matrix parameters */}
                <div className="grid grid-cols-1 md:grid-cols-3 gap-4 border-t border-b border-slate-100 py-4 bg-slate-50/50 p-4 rounded-xl">
                    <div className="space-y-1.5">
                        <label className="text-xs font-bold text-slate-700 block">Likelihood</label>
                        <select
                            value={likelihood}
                            onChange={e => setLikelihood(e.target.value)}
                            className="w-full border border-slate-200 rounded-lg text-xs py-2 px-3 focus:outline-none bg-white"
                        >
                            <option value="Almost Certain">Almost Certain (Expected)</option>
                            <option value="Likely">Likely (High probability)</option>
                            <option value="Possible">Possible (May happen)</option>
                            <option value="Unlikely">Unlikely (Low probability)</option>
                            <option value="Rare">Rare (Highly improbable)</option>
                        </select>
                    </div>
                    <div className="space-y-1.5">
                        <label className="text-xs font-bold text-slate-700 block">Impact</label>
                        <select
                            value={impact}
                            onChange={e => setImpact(e.target.value)}
                            className="w-full border border-slate-200 rounded-lg text-xs py-2 px-3 focus:outline-none bg-white"
                        >
                            <option value="Catastrophic">Catastrophic (Critical loss)</option>
                            <option value="Major">Major (Severe impairment)</option>
                            <option value="Moderate">Moderate (Partial disruption)</option>
                            <option value="Minor">Minor (Low disruption)</option>
                            <option value="Negligible">Negligible (Insignificant)</option>
                        </select>
                    </div>
                    <div className="flex flex-col justify-center border-l border-slate-200 pl-4">
                        <span className="text-[10px] text-slate-400 uppercase font-bold block">Realtime Score Preview</span>
                        <div className="flex items-baseline gap-2 mt-1">
                            <span className="text-xl font-bold text-slate-800">{computedScore} / 25</span>
                            <span className={`px-2 py-0.5 rounded text-[10px] font-bold border ${
                                levelText === 'Critical' ? 'bg-rose-50 border-rose-200 text-rose-800' :
                                levelText === 'High' ? 'bg-orange-50 border-orange-200 text-orange-850' :
                                levelText === 'Medium' ? 'bg-amber-50 border-amber-200 text-amber-800' :
                                                          'bg-emerald-50 border-emerald-200 text-emerald-800'
                            }`}>{levelText}</span>
                        </div>
                    </div>
                </div>

                {/* Owner & Dates */}
                <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div className="space-y-1.5">
                        <label className="text-xs font-bold text-slate-700 block">Risk Owner</label>
                        <select
                            value={ownerId}
                            onChange={e => setOwnerId(e.target.value)}
                            className="w-full border border-slate-200 rounded-lg text-xs py-2 px-3 focus:outline-none bg-white"
                        >
                            <option value="">Unassigned</option>
                            {users.map(u => (
                                <option key={u.id} value={u.id}>{u.name}</option>
                            ))}
                        </select>
                    </div>
                    <div className="space-y-1.5">
                        <label className="text-xs font-bold text-slate-700 block">Due Date</label>
                        <input
                            type="date"
                            value={dueDate}
                            onChange={e => setDueDate(e.target.value)}
                            className="w-full border border-slate-200 rounded-lg text-xs py-1.5 px-3 bg-white focus:outline-none"
                        />
                    </div>
                    <div className="space-y-1.5">
                        <label className="text-xs font-bold text-slate-700 block">Next Review Date</label>
                        <input
                            type="date"
                            value={reviewDate}
                            onChange={e => setReviewDate(e.target.value)}
                            className="w-full border border-slate-200 rounded-lg text-xs py-1.5 px-3 bg-white focus:outline-none"
                        />
                    </div>
                </div>

                {/* Linked findings checklist selector */}
                <div className="space-y-2 border-t border-slate-100 pt-4">
                    <label className="text-xs font-bold text-slate-800 block">Link Scan Findings</label>
                    <p className="text-[10px] text-slate-400 mt-0.5">Select multiple security scan findings to map directly to this business risk definition.</p>
                    {findings.length === 0 ? (
                        <p className="text-xs text-slate-400 italic py-2">No findings available to link.</p>
                    ) : (
                        <div className="border border-slate-200 rounded-xl max-h-[160px] overflow-y-auto divide-y divide-slate-100 p-2 bg-slate-50/20">
                            {findings.map(f => (
                                <label key={f.id} className="flex items-center gap-3 py-2 px-2.5 hover:bg-slate-50 rounded-lg cursor-pointer transition">
                                    <input
                                        type="checkbox"
                                        checked={selectedFindings.includes(f.id)}
                                        onChange={() => handleFindingToggle(f.id)}
                                        className="rounded border-slate-350 text-brand-600 focus:ring-brand-500"
                                    />
                                    <div className="text-xs">
                                        <span className="font-semibold text-slate-700 block">{f.title}</span>
                                        <span className="text-[9px] font-mono text-slate-400">{f.finding_id} · Severity: {f.severity}</span>
                                    </div>
                                </label>
                            ))}
                        </div>
                    )}
                </div>

                {/* Action buttons */}
                <div className="flex justify-end gap-2 border-t border-slate-100 pt-4">
                    <button
                        type="button"
                        onClick={onCancel}
                        className="px-4 py-2 border border-slate-200 rounded-lg text-xs font-semibold hover:bg-slate-50 text-slate-600 transition"
                    >
                        Cancel
                    </button>
                    <button
                        type="submit"
                        disabled={saving}
                        className="px-4 py-2 bg-brand-600 hover:bg-brand-700 disabled:opacity-50 text-white rounded-lg text-xs font-semibold shadow transition flex items-center gap-1.5"
                    >
                        {saving ? <Loader2 className="animate-spin" size={13} /> : <Save size={13} />}
                        {isEdit ? 'Save Changes' : 'Register Risk'}
                    </button>
                </div>

            </form>
        </div>
    );
}