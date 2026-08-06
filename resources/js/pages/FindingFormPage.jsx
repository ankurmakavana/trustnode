import React, { useState, useEffect } from 'react';
import { ArrowLeft, Save, AlertTriangle, Loader2 } from 'lucide-react';
import axios from 'axios';

export default function FindingFormPage({ findingId, onSave, onCancel }) {
    const isEdit = !!findingId;
    const [loading, setLoading] = useState(false);
    const [saving, setSaving] = useState(false);
    const [error, setError] = useState(null);
    const [validationErrors, setValidationErrors] = useState({});

    // Form attributes
    const [title, setTitle] = useState('');
    const [cve, setCve] = useState('');
    const [cvssScore, setCvssScore] = useState('');
    const [severity, setSeverity] = useState('medium');
    const [status, setStatus] = useState('open');
    const [category, setCategory] = useState('web_application');
    const [cwe, setCwe] = useState('');
    const [description, setDescription] = useState('');
    const [technicalDetails, setTechnicalDetails] = useState('');
    const [businessImpact, setBusinessImpact] = useState('');
    const [remediation, setRemediation] = useState('');
    const [evidence, setEvidence] = useState('');

    const [assetId, setAssetId] = useState('');
    const [targetId, setTargetId] = useState('');
    const [scanId, setScanId] = useState('');
    const [assignedAnalyst, setAssignedAnalyst] = useState('');

    const [assets, setAssets] = useState([]);
    const [targets, setTargets] = useState([]);
    const [scans, setScans] = useState([]);
    const [usersList, setUsersList] = useState([]);

    useEffect(() => {
        const fetchDropdowns = async () => {
            try {
                const [assetsRes, targetsRes, scansRes, usersRes] = await Promise.all([
                    axios.get('/api/assets'),
                    axios.get('/api/targets'),
                    axios.get('/api/scans'),
                    axios.get('/api/dashboard/stats') // Stats or list to fetch user profiles
                ]);
                setAssets(assetsRes.data.data || []);
                setTargets(targetsRes.data.data || []);
                setScans(scansRes.data.data || []);
            } catch (err) {
                console.error('Failed to load related models list:', err);
            }
        };
        fetchDropdowns();

        if (isEdit) {
            const fetchFinding = async () => {
                setLoading(true);
                setError(null);
                try {
                    const response = await axios.get(`/api/findings/${findingId}`);
                    const f = response.data.data;
                    setTitle(f.title);
                    setCve(f.cve || '');
                    setCvssScore(f.cvss_score !== null ? f.cvss_score : '');
                    setSeverity(f.severity);
                    setStatus(f.status);
                    setCategory(f.category);
                    setCwe(f.cwe || '');
                    setDescription(f.description || '');
                    setTechnicalDetails(f.technical_details || '');
                    setBusinessImpact(f.business_impact || '');
                    setRemediation(f.remediation || '');
                    setEvidence(f.evidence || '');
                    setAssetId(f.asset ? f.asset.id : '');
                    setTargetId(f.target ? f.target.id : '');
                    setScanId(f.scan ? f.scan.id : '');
                    setAssignedAnalyst(f.analyst ? f.analyst.id : '');
                } catch (err) {
                    console.error('Failed to load finding profile details:', err);
                    setError('Failed to load finding profile.');
                } finally {
                    setLoading(false);
                }
            };
            fetchFinding();
        }
    }, [findingId]);

    const handleSubmit = async (e) => {
        e.preventDefault();
        setSaving(true);
        setError(null);
        setValidationErrors({});

        const payload = {
            title,
            cve: cve || null,
            cvss_score: cvssScore !== '' ? Number(cvssScore) : null,
            severity,
            status,
            category,
            cwe: cwe || null,
            description,
            technical_details: technicalDetails,
            business_impact: businessImpact,
            remediation,
            evidence,
            asset_id: assetId ? Number(assetId) : null,
            target_id: targetId ? Number(targetId) : null,
            scan_id: scanId ? Number(scanId) : null,
            assigned_analyst: assignedAnalyst ? Number(assignedAnalyst) : null
        };

        try {
            if (isEdit) {
                await axios.put(`/api/findings/${findingId}`, payload);
            } else {
                await axios.post('/api/findings', payload);
            }
            onSave();
        } catch (err) {
            console.error('Failed to save manual finding:', err);
            if (err.response?.status === 422) {
                setValidationErrors(err.response.data.errors || {});
            } else {
                setError(err.response?.data?.message || 'Failed to save finding configuration.');
            }
        } finally {
            setSaving(false);
        }
    };

    if (loading) {
        return (
            <div className="bg-white border border-slate-200 rounded-xl p-6 shadow-sm flex items-center justify-center min-h-[40vh]">
                <Loader2 className="animate-spin text-brand-600 mr-2" size={24} />
                <span className="text-sm font-semibold text-slate-500">Loading finding configurations...</span>
            </div>
        );
    }

    return (
        <div className="max-w-3xl mx-auto space-y-6">
            {/* Header section */}
            <div className="flex items-center gap-3">
                <button
                    onClick={onCancel}
                    className="p-1.5 border border-slate-200 rounded-lg hover:bg-slate-50 text-slate-500 hover:text-slate-800 transition"
                >
                    <ArrowLeft size={16} />
                </button>
                <div>
                    <h1 className="text-xl font-bold text-slate-900 tracking-tight">
                        {isEdit ? 'Modify Security Finding' : 'Log Manual Finding'}
                    </h1>
                    <p className="text-xs text-slate-500 mt-0.5">
                        Define vulnerability details, risk classification parameters, and evidence files.
                    </p>
                </div>
            </div>

            {/* Error notifications */}
            {error && (
                <div className="bg-rose-50 border border-rose-200 rounded-xl p-4 flex gap-3 text-xs text-rose-800 font-medium">
                    <AlertTriangle size={16} className="text-rose-500 shrink-0" />
                    <span>{error}</span>
                </div>
            )}

            {/* Form configuration card */}
            <form onSubmit={handleSubmit} className="bg-white border border-slate-200 rounded-xl p-5 shadow-sm space-y-5">
                {/* Title */}
                <div className="space-y-1">
                    <label htmlFor="finding-title" className="text-xs font-bold text-slate-700 block">Finding Title</label>
                    <input
                        id="finding-title"
                        type="text"
                        placeholder="e.g. Cross-Site Scripting (XSS) in Login query page"
                        value={title}
                        onChange={(e) => setTitle(e.target.value)}
                        className={`w-full border rounded-lg text-sm text-slate-800 py-2 px-3 focus:outline-none focus:ring-2 ${
                            validationErrors.title 
                                ? 'border-rose-400 focus:ring-rose-500/20 focus:border-rose-500' 
                                : 'border-slate-300 focus:ring-brand-500/20 focus:border-brand-500'
                        }`}
                    />
                    {validationErrors.title && (
                        <p className="text-rose-600 text-[10px] font-semibold mt-0.5">{validationErrors.title[0]}</p>
                    )}
                </div>

                {/* Severity, CVSS Score, Status */}
                <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div className="space-y-1">
                        <label htmlFor="finding-sev" className="text-xs font-bold text-slate-700 block">Risk Severity</label>
                        <select
                            id="finding-sev"
                            value={severity}
                            onChange={(e) => setSeverity(e.target.value)}
                            className="w-full border border-slate-300 rounded-lg text-sm text-slate-700 py-2 px-3 focus:outline-none focus:ring-2 focus:ring-brand-500/20 focus:border-brand-500"
                        >
                            <option value="critical">Critical</option>
                            <option value="high">High</option>
                            <option value="medium">Medium</option>
                            <option value="low">Low</option>
                            <option value="info">Info</option>
                        </select>
                    </div>

                    <div className="space-y-1">
                        <label htmlFor="finding-cvss" className="text-xs font-bold text-slate-700 block">CVSS Score (0.0 - 10.0)</label>
                        <input
                            id="finding-cvss"
                            type="number"
                            step="0.1"
                            min="0"
                            max="10"
                            placeholder="e.g. 8.5"
                            value={cvssScore}
                            onChange={(e) => setCvssScore(e.target.value)}
                            className={`w-full border rounded-lg text-sm text-slate-800 py-2 px-3 focus:outline-none focus:ring-2 ${
                                validationErrors.cvss_score 
                                    ? 'border-rose-400 focus:ring-rose-500/20 focus:border-rose-500' 
                                    : 'border-slate-300 focus:ring-brand-500/20 focus:border-brand-500'
                            }`}
                        />
                        {validationErrors.cvss_score && (
                            <p className="text-rose-600 text-[10px] font-semibold mt-0.5">{validationErrors.cvss_score[0]}</p>
                        )}
                    </div>

                    <div className="space-y-1">
                        <label htmlFor="finding-status" className="text-xs font-bold text-slate-700 block">Workflow Status</label>
                        <select
                            id="finding-status"
                            value={status}
                            onChange={(e) => setStatus(e.target.value)}
                            className="w-full border border-slate-300 rounded-lg text-sm text-slate-700 py-2 px-3 focus:outline-none focus:ring-2 focus:ring-brand-500/20 focus:border-brand-500"
                        >
                            <option value="open">Open</option>
                            <option value="in_progress">In Progress</option>
                            <option value="remediated">Remediated</option>
                            <option value="false_positive">False Positive</option>
                            <option value="risk_accepted">Risk Accepted</option>
                        </select>
                    </div>
                </div>

                {/* CVE, CWE, Category */}
                <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div className="space-y-1">
                        <label htmlFor="finding-cve" className="text-xs font-bold text-slate-700 block">CVE Reference (Optional)</label>
                        <input
                            id="finding-cve"
                            type="text"
                            placeholder="e.g. CVE-2024-12345"
                            value={cve}
                            onChange={(e) => setCve(e.target.value)}
                            className={`w-full border rounded-lg text-sm text-slate-800 py-2 px-3 focus:outline-none focus:ring-2 ${
                                validationErrors.cve 
                                    ? 'border-rose-400 focus:ring-rose-500/20 focus:border-rose-500' 
                                    : 'border-slate-300 focus:ring-brand-500/20 focus:border-brand-500'
                            }`}
                        />
                        {validationErrors.cve && (
                            <p className="text-rose-600 text-[10px] font-semibold mt-0.5">{validationErrors.cve[0]}</p>
                        )}
                    </div>

                    <div className="space-y-1">
                        <label htmlFor="finding-cwe" className="text-xs font-bold text-slate-700 block">CWE Reference (Optional)</label>
                        <input
                            id="finding-cwe"
                            type="text"
                            placeholder="e.g. CWE-79"
                            value={cwe}
                            onChange={(e) => setCwe(e.target.value)}
                            className={`w-full border rounded-lg text-sm text-slate-800 py-2 px-3 focus:outline-none focus:ring-2 ${
                                validationErrors.cwe 
                                    ? 'border-rose-400 focus:ring-rose-500/20 focus:border-rose-500' 
                                    : 'border-slate-300 focus:ring-brand-500/20 focus:border-brand-500'
                            }`}
                        />
                        {validationErrors.cwe && (
                            <p className="text-rose-600 text-[10px] font-semibold mt-0.5">{validationErrors.cwe[0]}</p>
                        )}
                    </div>

                    <div className="space-y-1">
                        <label htmlFor="finding-cat" className="text-xs font-bold text-slate-700 block">Finding Category</label>
                        <select
                            id="finding-cat"
                            value={category}
                            onChange={(e) => setCategory(e.target.value)}
                            className="w-full border border-slate-300 rounded-lg text-sm text-slate-700 py-2 px-3 focus:outline-none focus:ring-2 focus:ring-brand-500/20 focus:border-brand-500"
                        >
                            <option value="web_application">Web Application</option>
                            <option value="network_ip">Network IP</option>
                            <option value="port_discovery">Port Discovery</option>
                            <option value="api_vulnerability">API Vulnerability</option>
                            <option value="container_audit">Container Audit</option>
                            <option value="cloud_infrastructure">Cloud Infrastructure</option>
                        </select>
                    </div>
                </div>

                {/* Context Mappings: Asset, Target, Scan */}
                <div className="grid grid-cols-1 md:grid-cols-3 gap-4 border-t border-slate-100 pt-4">
                    {/* Target */}
                    <div className="space-y-1">
                        <label htmlFor="finding-target" className="text-xs font-bold text-slate-700 block">Link Target Scope</label>
                        <select
                            id="finding-target"
                            value={targetId}
                            onChange={(e) => setTargetId(e.target.value)}
                            className="w-full border border-slate-300 rounded-lg text-sm text-slate-700 py-2 px-3 focus:outline-none focus:ring-2 focus:ring-brand-500/20 focus:border-brand-500"
                        >
                            <option value="">Unlinked</option>
                            {targets.map((t) => (
                                <option key={t.id} value={t.id}>{t.name} ({t.value})</option>
                            ))}
                        </select>
                    </div>

                    {/* Asset */}
                    <div className="space-y-1">
                        <label htmlFor="finding-asset" className="text-xs font-bold text-slate-700 block">Link Asset</label>
                        <select
                            id="finding-asset"
                            value={assetId}
                            onChange={(e) => setAssetId(e.target.value)}
                            className="w-full border border-slate-300 rounded-lg text-sm text-slate-700 py-2 px-3 focus:outline-none focus:ring-2 focus:ring-brand-500/20 focus:border-brand-500"
                        >
                            <option value="">Unlinked</option>
                            {assets.map((a) => (
                                <option key={a.id} value={a.id}>{a.name}</option>
                            ))}
                        </select>
                    </div>

                    {/* Scan */}
                    <div className="space-y-1">
                        <label htmlFor="finding-scan" className="text-xs font-bold text-slate-700 block">Link Scan Run</label>
                        <select
                            id="finding-scan"
                            value={scanId}
                            onChange={(e) => setScanId(e.target.value)}
                            className="w-full border border-slate-300 rounded-lg text-sm text-slate-700 py-2 px-3 focus:outline-none focus:ring-2 focus:ring-brand-500/20 focus:border-brand-500"
                        >
                            <option value="">Manual assessment</option>
                            {scans.map((s) => (
                                <option key={s.id} value={s.id}>{s.name}</option>
                            ))}
                        </select>
                    </div>
                </div>

                {/* Description & Technical Details */}
                <div className="space-y-4 border-t border-slate-100 pt-4">
                    <div className="space-y-1">
                        <label htmlFor="finding-desc" className="text-xs font-bold text-slate-700 block">Vulnerability Overview / Description</label>
                        <textarea
                            id="finding-desc"
                            rows="3"
                            placeholder="Detailed description of the vulnerability..."
                            value={description}
                            onChange={(e) => setDescription(e.target.value)}
                            className="w-full border border-slate-300 rounded-lg text-sm text-slate-800 py-2 px-3 focus:outline-none focus:ring-2 focus:ring-brand-500/20 focus:border-brand-500"
                        />
                    </div>

                    <div className="space-y-1">
                        <label htmlFor="finding-tech" className="text-xs font-bold text-slate-700 block">Technical details / Attack vector / URL paths</label>
                        <textarea
                            id="finding-tech"
                            rows="3"
                            placeholder="e.g. Host validation parameters or payload models..."
                            value={technicalDetails}
                            onChange={(e) => setTechnicalDetails(e.target.value)}
                            className="w-full border border-slate-300 rounded-lg text-sm text-slate-800 py-2 px-3 focus:outline-none focus:ring-2 focus:ring-brand-500/20 focus:border-brand-500"
                        />
                    </div>
                </div>

                {/* Impact, Remediation, PoC */}
                <div className="space-y-4 border-t border-slate-100 pt-4">
                    <div className="space-y-1">
                        <label htmlFor="finding-impact" className="text-xs font-bold text-slate-700 block">Business Impact Analysis</label>
                        <textarea
                            id="finding-impact"
                            rows="2"
                            placeholder="e.g. Arbitrary database queries execution or data exfiltration..."
                            value={businessImpact}
                            onChange={(e) => setBusinessImpact(e.target.value)}
                            className="w-full border border-slate-300 rounded-lg text-sm text-slate-800 py-2 px-3 focus:outline-none focus:ring-2 focus:ring-brand-500/20 focus:border-brand-500"
                        />
                    </div>

                    <div className="space-y-1">
                        <label htmlFor="finding-rem" className="text-xs font-bold text-slate-700 block">Remediation Guidelines</label>
                        <textarea
                            id="finding-rem"
                            rows="3"
                            placeholder="Remediation steps, configuration patches..."
                            value={remediation}
                            onChange={(e) => setRemediation(e.target.value)}
                            className="w-full border border-slate-300 rounded-lg text-sm text-slate-800 py-2 px-3 focus:outline-none focus:ring-2 focus:ring-brand-500/20 focus:border-brand-500"
                        />
                    </div>

                    <div className="space-y-1">
                        <label htmlFor="finding-ev" className="text-xs font-bold text-slate-700 block">Proof of Concept / Evidence (Console Logs / Payloads)</label>
                        <textarea
                            id="finding-ev"
                            rows="4"
                            placeholder="Paste raw log outputs, network requests, stack traces..."
                            value={evidence}
                            onChange={(e) => setEvidence(e.target.value)}
                            className="w-full border border-slate-300 rounded-lg text-sm font-mono text-slate-800 py-2 px-3 focus:outline-none focus:ring-2 focus:ring-brand-500/20 focus:border-brand-500"
                        />
                    </div>
                </div>

                {/* Form Buttons */}
                <div className="flex items-center justify-end gap-3 border-t border-slate-100 pt-4">
                    <button
                        type="button"
                        onClick={onCancel}
                        disabled={saving}
                        className="px-4 py-2 text-sm font-semibold text-slate-700 bg-white border border-slate-300 hover:bg-slate-50 rounded-lg shadow-sm disabled:opacity-40 transition"
                    >
                        Cancel
                    </button>
                    <button
                        type="submit"
                        disabled={saving}
                        className="inline-flex items-center justify-center gap-1.5 px-4 py-2 text-sm font-semibold text-white bg-brand-600 hover:bg-brand-700 active:bg-brand-800 rounded-lg shadow-sm border border-brand-700 disabled:opacity-40 transition"
                    >
                        {saving ? (
                            <>
                                <Loader2 className="animate-spin" size={16} />
                                Saving Finding...
                            </>
                        ) : (
                            <>
                                <Save size={16} />
                                {isEdit ? 'Update Finding' : 'Log Vulnerability'}
                            </>
                        )}
                    </button>
                </div>
            </form>
        </div>
    );
}
