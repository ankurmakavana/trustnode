import React, { useState, useEffect } from 'react';
import {
    ArrowLeft, ChevronRight, FileText, AlertTriangle, CheckCircle2,
    Clock, Download, Shield, BarChart3, List, Info, Loader2,
} from 'lucide-react';
import axios from 'axios';
import { ScanStatusBadge, ScanTypeBadge, ScanEngineBadge } from '../components/ui/primitives_scans';

// ─── Severity Row ─────────────────────────────────────────────────────────────

function SeverityBar({ label, color, count = 0, max = 1 }) {
    const pct = max > 0 ? Math.round((count / max) * 100) : 0;
    return (
        <div className="flex items-center gap-3">
            <span className={`text-[11px] font-bold w-20 shrink-0 ${color}`}>{label}</span>
            <div className="flex-1 h-2 bg-slate-100 rounded-full overflow-hidden border border-slate-200">
                <div className={`h-full rounded-full transition-all duration-700 ${
                    label === 'Critical' ? 'bg-rose-600' :
                    label === 'High'     ? 'bg-orange-500' :
                    label === 'Medium'   ? 'bg-amber-400' :
                    label === 'Low'      ? 'bg-blue-400' :
                                          'bg-slate-300'
                }`} style={{ width: `${pct}%` }} />
            </div>
            <span className="text-[11px] font-bold text-slate-600 w-6 text-right shrink-0">{count}</span>
        </div>
    );
}

// ─── Section Card ─────────────────────────────────────────────────────────────

function SectionCard({ title, icon: Icon, children, className = '' }) {
    return (
        <div className={`bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden ${className}`}>
            <div className="flex items-center gap-2.5 px-5 py-3.5 border-b border-slate-100 bg-slate-50/50">
                <Icon size={15} className="text-slate-400" strokeWidth={2} />
                <h2 className="text-sm font-bold text-slate-800">{title}</h2>
            </div>
            <div className="p-5">{children}</div>
        </div>
    );
}

// ─── Main Component ───────────────────────────────────────────────────────────

export default function ScanReportPage({ scanId, onBack, onViewDetail }) {
    const [scan,    setScan]    = useState(null);
    const [findings, setFindings] = useState([]);
    const [loading, setLoading] = useState(true);
    const [error,   setError]   = useState(null);

    useEffect(() => {
        if (!scanId) return;
        setLoading(true);
        setError(null);
        Promise.all([
            axios.get(`/api/scans/${scanId}`),
            axios.get('/api/findings', { params: { scan_id: scanId, per_page: 100 } })
        ])
        .then(([scanRes, findingsRes]) => {
            setScan(scanRes.data.data);
            setFindings(findingsRes.data.data || findingsRes.data || []);
        })
        .catch(() => setError('Failed to load scan data for this report.'))
        .finally(() => setLoading(false));
    }, [scanId]);

    // ── Loading ───────────────────────────────────────────────────────────────
    if (loading) {
        return (
            <div className="flex items-center justify-center min-h-[50vh]">
                <Loader2 size={28} className="animate-spin text-brand-500" />
            </div>
        );
    }

    // ── Error ─────────────────────────────────────────────────────────────────
    if (error || !scan) {
        return (
            <div className="bg-rose-50 border border-rose-200 rounded-xl p-6 text-center">
                <p className="text-sm font-semibold text-rose-800 mb-3">{error || 'Scan not found.'}</p>
                <button
                    type="button"
                    onClick={onBack}
                    className="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold text-slate-700 bg-white border border-slate-300 rounded-lg hover:bg-slate-50 transition"
                >
                    <ArrowLeft size={12} /> Back to Scans
                </button>
            </div>
        );
    }

    const isCompleted  = scan.status === 'completed';
    const generatedAt  = scan.completed_at
        ? new Date(scan.completed_at).toLocaleString()
        : null;

    return (
        <div className="space-y-6 max-w-4xl mx-auto">

            {/* ── Breadcrumb + Header ──────────────────────────────────────── */}
            <div className="flex flex-col gap-2">
                {/* Breadcrumb */}
                <div className="flex items-center gap-2 text-xs text-slate-500 font-medium">
                    <span className="cursor-pointer hover:text-slate-800 transition" onClick={onBack}>Scans</span>
                    <ChevronRight size={12} />
                    <span
                        className="cursor-pointer hover:text-slate-800 transition"
                        onClick={() => onViewDetail && onViewDetail(scan.id)}
                    >{scan.name}</span>
                    <ChevronRight size={12} />
                    <span className="text-slate-700 font-semibold">Report</span>
                </div>

                {/* Title row */}
                <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                    <div className="flex items-center gap-3">
                        <button
                            type="button"
                            onClick={onBack}
                            className="p-1.5 border border-slate-200 rounded-lg hover:bg-slate-50 text-slate-500 hover:text-slate-800 transition shrink-0"
                        >
                            <ArrowLeft size={16} />
                        </button>
                        <div>
                            <h1 className="text-xl font-bold text-slate-900 tracking-tight flex items-center gap-2">
                                <FileText size={20} className="text-brand-500" strokeWidth={1.75} />
                                Scan Report
                            </h1>
                            <p className="text-xs text-slate-500 mt-0.5 truncate max-w-xs">{scan.name}</p>
                        </div>
                    </div>

                    {/* Export button */}
                    {scan.type === 'repository' || scan.type === 'repository' ? (
                        <a
                            href={`/api/scans/${scan.id}/report/download`}
                            target="_blank"
                            rel="noopener noreferrer"
                            className="inline-flex items-center gap-2 px-4 py-2 text-sm font-semibold text-white bg-brand-600 hover:bg-brand-700 rounded-lg shadow-sm transition"
                        >
                            <Download size={14} strokeWidth={2} />
                            Download Report
                        </a>
                    ) : (
                        <button
                            type="button"
                            disabled
                            title="PDF export — coming soon"
                            className="inline-flex items-center gap-2 px-4 py-2 text-sm font-semibold text-slate-400 bg-slate-100 border border-slate-200 rounded-lg cursor-not-allowed"
                        >
                            <Download size={14} strokeWidth={2} />
                            Export PDF
                            <span className="text-[10px] font-bold text-slate-400 bg-slate-200 px-1.5 py-0.5 rounded">Soon</span>
                        </button>
                    )}
                </div>
            </div>

            {/* ── Report Status Banner ─────────────────────────────────────── */}
            <div className={`rounded-xl border px-5 py-4 flex items-start gap-4 ${
                isCompleted
                    ? 'bg-emerald-50 border-emerald-200'
                    : 'bg-amber-50 border-amber-200'
            }`}>
                {isCompleted
                    ? <CheckCircle2 size={20} className="text-emerald-500 shrink-0 mt-0.5" strokeWidth={1.75} />
                    : <AlertTriangle size={20} className="text-amber-500 shrink-0 mt-0.5" strokeWidth={1.75} />
                }
                <div>
                    <div className={`text-sm font-bold ${isCompleted ? 'text-emerald-800' : 'text-amber-800'}`}>
                        {isCompleted ? 'Report Available' : 'Report Pending'}
                    </div>
                    <div className={`text-xs mt-0.5 leading-relaxed ${isCompleted ? 'text-emerald-700' : 'text-amber-700'}`}>
                        {isCompleted
                            ? `Static security analysis has finished successfully. ${findings.length} vulnerabilities detected.`
                            : `This scan has not completed yet (status: ${scan.status}). The report will be generated upon completion.`
                        }
                    </div>
                    {generatedAt && (
                        <div className="flex items-center gap-1.5 mt-2 text-[11px] text-emerald-600 font-medium">
                            <Clock size={11} strokeWidth={2} />
                            Generated: {generatedAt}
                        </div>
                    )}
                </div>
            </div>

            {/* ── Report Metadata ──────────────────────────────────────────── */}
            <SectionCard title="Report Details" icon={Info}>
                <div className="grid grid-cols-2 sm:grid-cols-4 gap-4">
                    {[
                        { label: 'Scan Name',    value: scan.name },
                        { label: 'Report Status',value: isCompleted ? 'Available' : 'Pending' },
                        { label: 'Generated',    value: generatedAt || 'Not yet generated' },
                        { label: 'Target',       value: scan.target, mono: true },
                    ].map(({ label, value, mono }) => (
                        <div key={label}>
                            <span className="text-[10px] font-bold text-slate-400 uppercase tracking-wider block mb-1">{label}</span>
                            <span className={`text-sm text-slate-800 break-all ${mono ? 'font-mono text-xs' : 'font-semibold'}`}>{value}</span>
                        </div>
                    ))}
                </div>
                <div className="grid grid-cols-2 sm:grid-cols-3 gap-4 mt-4 pt-4 border-t border-slate-100">
                    <div>
                        <span className="text-[10px] font-bold text-slate-400 uppercase tracking-wider block mb-1.5">Scan Type</span>
                        <ScanTypeBadge type={scan.type} />
                    </div>
                    <div>
                        <span className="text-[10px] font-bold text-slate-400 uppercase tracking-wider block mb-1.5">Engine</span>
                        <ScanEngineBadge engine={scan.engine} />
                    </div>
                    <div>
                        <span className="text-[10px] font-bold text-slate-400 uppercase tracking-wider block mb-1.5">Status</span>
                        <ScanStatusBadge status={scan.status} />
                    </div>
                </div>
            </SectionCard>

            {/* ── Executive Summary ────────────────────────────────────────── */}
            <SectionCard title="Executive Summary" icon={Shield}>
                <div className="space-y-3">
                    <p className="text-sm text-slate-600 leading-relaxed">
                        This report summarises the findings of the <strong className="text-slate-800">{scan.name}</strong> vulnerability
                        assessment against target <code className="bg-slate-100 px-1.5 py-0.5 rounded text-xs font-mono">{scan.target}</code>.
                        The assessment was conducted using the <strong className="text-slate-800">{scan.engine?.replace('_', ' ').toUpperCase()}</strong> engine.
                    </p>
                    {scan.type === 'repository' ? (
                        <div className="p-4 rounded-lg bg-slate-50 border border-slate-200 text-xs text-slate-700 leading-relaxed">
                            A total of <strong>{findings.length}</strong> findings were identified. Review the detailed findings table below to verify vulnerability severity, impacted files, and remediation steps.
                        </div>
                    ) : (
                        <div className="flex items-start gap-3 p-4 rounded-lg bg-brand-50 border border-brand-200">
                            <Info size={15} className="text-brand-400 shrink-0 mt-0.5" strokeWidth={2} />
                            <p className="text-xs text-brand-700 leading-relaxed">
                                <strong>Report generation is coming soon.</strong> The Findings module is currently under development.
                                Once deployed, this section will include a full executive narrative, risk posture assessment,
                                and remediation prioritisation.
                            </p>
                        </div>
                    )}
                </div>
            </SectionCard>

            {/* ── Findings Section ─────────────────────────────────────── */}
            <SectionCard title="Findings" icon={List}>
                {scan.type === 'repository' ? (
                    findings.length === 0 ? (
                        <p className="text-sm text-slate-500 italic text-center py-6">No vulnerabilities detected.</p>
                    ) : (
                        <div className="space-y-4">
                            {findings.map((f, idx) => (
                                <div key={f.id || idx} className="p-4 border border-slate-200 rounded-lg bg-slate-50/50 hover:bg-slate-50 transition">
                                    <div className="flex items-center justify-between gap-4 mb-2">
                                        <h3 className="text-sm font-bold text-slate-800">{f.title}</h3>
                                        <span className={`text-[10px] font-bold uppercase px-2 py-0.5 rounded ${
                                            (f.severity?.value || f.severity) === 'Critical' ? 'bg-rose-100 text-rose-800' :
                                            (f.severity?.value || f.severity) === 'High' ? 'bg-orange-100 text-orange-850' :
                                            (f.severity?.value || f.severity) === 'Medium' ? 'bg-amber-100 text-amber-800' :
                                            'bg-slate-100 text-slate-700'
                                        }`}>
                                            {f.severity?.value || f.severity}
                                        </span>
                                    </div>
                                    <div className="text-xs text-slate-600 space-y-1.5">
                                        <p><strong>Category:</strong> {f.category}</p>
                                        <p><strong>File:</strong> <code className="bg-white px-1 py-0.5 rounded border border-slate-200 font-mono text-[10px]">{f.technical_details || f.url}</code></p>
                                        <p className="mt-2 text-slate-700 leading-relaxed">{f.description}</p>
                                        {f.evidence && (
                                            <pre className="bg-slate-900 text-slate-100 p-2.5 rounded font-mono text-[11px] overflow-x-auto mt-2 border border-slate-950">
                                                {f.evidence}
                                            </pre>
                                        )}
                                        {f.remediation && (
                                            <p className="mt-2 text-emerald-800 bg-emerald-50/50 p-2.5 rounded border border-emerald-100">
                                                <strong>Remediation:</strong> {f.remediation}
                                            </p>
                                        )}
                                    </div>
                                </div>
                            ))}
                        </div>
                    )
                ) : (
                    <div className="flex flex-col items-center justify-center py-12 text-center">
                        <div className="w-14 h-14 rounded-2xl bg-slate-100 border border-slate-200 flex items-center justify-center mb-4">
                            <List size={24} className="text-slate-300" strokeWidth={1.5} />
                        </div>
                        <h3 className="text-sm font-bold text-slate-700 mb-1">Findings Not Available</h3>
                        <p className="text-xs text-slate-500 max-w-sm leading-relaxed">
                            Individual vulnerability findings will be listed here once the Findings module is deployed and the scan engine has completed its assessment.
                        </p>
                    </div>
                )}
            </SectionCard>

            {/* ── Risk Summary ──────────────────────────────────── */}
            <SectionCard title="Risk Summary" icon={BarChart3}>
                {scan.type === 'repository' ? (
                    <div className="space-y-3">
                        <p className="text-xs text-slate-500 mb-4">Vulnerability distribution by severity</p>
                        <SeverityBar label="Critical" color="text-rose-600"   count={findings.filter(f => (f.severity === 'Critical' || f.severity?.value === 'Critical')).length} max={findings.length || 1} />
                        <SeverityBar label="High"     color="text-orange-500" count={findings.filter(f => (f.severity === 'High' || f.severity?.value === 'High')).length} max={findings.length || 1} />
                        <SeverityBar label="Medium"   color="text-amber-500"  count={findings.filter(f => (f.severity === 'Medium' || f.severity?.value === 'Medium')).length} max={findings.length || 1} />
                        <SeverityBar label="Low"      color="text-blue-500"   count={findings.filter(f => (f.severity === 'Low' || f.severity?.value === 'Low')).length} max={findings.length || 1} />
                        <SeverityBar label="Info"     color="text-slate-400"  count={findings.filter(f => (f.severity === 'Info' || f.severity?.value === 'Info')).length} max={findings.length || 1} />
                    </div>
                ) : (
                    <div className="space-y-3">
                        <p className="text-xs text-slate-500 mb-4">Vulnerability distribution by severity (placeholder — no findings data yet)</p>
                        <SeverityBar label="Critical" color="text-rose-600"   count={0} max={1} />
                        <SeverityBar label="High"     color="text-orange-500" count={0} max={1} />
                        <SeverityBar label="Medium"   color="text-amber-500"  count={0} max={1} />
                        <SeverityBar label="Low"      color="text-blue-500"   count={0} max={1} />
                        <SeverityBar label="Info"     color="text-slate-400"  count={0} max={1} />
                        <div className="flex items-center gap-2 mt-4 pt-4 border-t border-slate-100">
                            <Info size={12} className="text-slate-400 shrink-0" />
                            <p className="text-[11px] text-slate-400 leading-relaxed">
                                Risk distribution will populate automatically after the Findings module is deployed.
                            </p>
                        </div>
                    </div>
                )}
            </SectionCard>

            {/* ── Footer ───────────────────────────────────────────────────── */}
            <div className="flex items-center justify-between text-xs text-slate-400 pt-2 pb-6 border-t border-slate-100">
                <span>TrustNode Platform · Scan Report</span>
                <span>{scan.type === 'repository' ? 'Generated successfully' : 'Report generation coming soon'}</span>
            </div>
        </div>
    );
}
