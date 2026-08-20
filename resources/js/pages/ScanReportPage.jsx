import React, { useState, useEffect } from 'react';
import {
    ArrowLeft, ChevronRight, FileText, AlertTriangle, CheckCircle2,
    Clock, Download, Shield, BarChart3, List, Info, Loader2,
} from 'lucide-react';
import axios from 'axios';
import { ScanStatusBadge, ScanTypeBadge, ScanEngineBadge } from '../components/ui/primitives_scans';
import { SeverityBadge, SEVERITY_COLORS } from '../components/ui/primitives_findings';

// ─── Severity Row ─────────────────────────────────────────────────────────────

function SeverityBar({ label, color, count = 0, max = 1 }) {
    const pct = max > 0 ? Math.round((count / max) * 100) : 0;
    return (
        <div className="flex items-center gap-3">
            <span className={`text-[11px] font-bold w-20 shrink-0 ${color}`}>{label}</span>
            <div className="flex-1 h-2 bg-slate-100 rounded-full overflow-hidden border border-slate-200">
                <div className={`h-full rounded-full transition-all duration-700 ${
                    SEVERITY_COLORS[label.toLowerCase()]?.bg || 'bg-slate-300'
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
    const [isDownloading, setIsDownloading] = useState(false);
    const [reportStatus, setReportStatus] = useState(null);

    useEffect(() => {
        if (!scanId) return;
        setLoading(true);
        setError(null);
        Promise.all([
            axios.get(`/api/scans/${scanId}`),
            axios.get('/api/findings', { params: { scan_id: scanId, per_page: 500 } }),
            axios.get(`/api/scans/${scanId}/report/status`).catch(() => ({ data: null }))
        ])
        .then(([scanRes, findingsRes, reportRes]) => {
            setScan(scanRes.data.data);
            setFindings(findingsRes.data.data || findingsRes.data || []);
            setReportStatus(reportRes.data);
        })
        .catch(() => setError('Failed to load scan data for this report.'))
        .finally(() => setLoading(false));
    }, [scanId]);

    useEffect(() => {
        let interval;
        if (reportStatus && ['queued', 'generating'].includes(reportStatus.status)) {
            interval = setInterval(() => {
                axios.get(`/api/scans/${scanId}/report/status`)
                    .then(res => setReportStatus(res.data))
                    .catch(() => {});
            }, 3000);
        }
        return () => clearInterval(interval);
    }, [scanId, reportStatus?.status]);

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
    const generatedAt  = reportStatus?.completed_at
        ? new Date(reportStatus.completed_at).toLocaleString()
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
                    <button
                        disabled={isDownloading || (reportStatus && ['queued', 'generating'].includes(reportStatus.status))}
                        onClick={async () => {
                            if (reportStatus?.status === 'completed') {
                                setIsDownloading(true);
                                try {
                                    const response = await axios.get(`/api/scans/${scan.id}/report/download`, {
                                        responseType: 'blob'
                                    });
                                    const url = window.URL.createObjectURL(new Blob([response.data]));
                                    const link = document.createElement('a');
                                    link.href = url;
                                    link.setAttribute('download', `trustnode-${(scan.target || '').replace(/[\/\\_]/g, '-')}-scan.pdf`);
                                    document.body.appendChild(link);
                                    link.click();
                                    link.parentNode.removeChild(link);
                                } catch (err) {
                                    console.error('Failed to download PDF', err);
                                    alert('Failed to download PDF. Please try again.');
                                } finally {
                                    setIsDownloading(false);
                                }
                            } else {
                                try {
                                    await axios.post(`/api/scans/${scan.id}/report`);
                                    const res = await axios.get(`/api/scans/${scan.id}/report/status`);
                                    setReportStatus(res.data);
                                } catch (err) {
                                    alert('Failed to start report generation.');
                                }
                            }
                        }}
                        className={`inline-flex items-center gap-2 px-4 py-2 text-sm font-semibold text-white bg-brand-600 hover:bg-brand-700 rounded-lg shadow-sm transition ${(isDownloading || (reportStatus && ['queued', 'generating'].includes(reportStatus.status))) ? 'opacity-70 cursor-not-allowed' : ''}`}
                    >
                        {(isDownloading || (reportStatus && ['queued', 'generating'].includes(reportStatus.status))) ? <Loader2 size={14} className="animate-spin" /> : (reportStatus?.status === 'completed' ? <Download size={14} strokeWidth={2} /> : <FileText size={14} strokeWidth={2} />)}
                        {(reportStatus && ['queued', 'generating'].includes(reportStatus.status)) ? `Generating (${reportStatus.progress || 0}%)` : (isDownloading ? 'Downloading...' : (reportStatus?.status === 'completed' ? 'Download PDF' : 'Generate Report'))}
                    </button>
                </div>
            </div>

            {/* ── Report Status Banner ─────────────────────────────────────── */}
            <div className={`rounded-xl border px-5 py-4 flex items-start gap-4 ${
                reportStatus?.status === 'completed'
                    ? 'bg-emerald-50 border-emerald-200'
                    : reportStatus?.status === 'failed'
                        ? 'bg-rose-50 border-rose-200'
                        : 'bg-amber-50 border-amber-200'
            }`}>
                {reportStatus?.status === 'completed'
                    ? <CheckCircle2 size={20} className="text-emerald-500 shrink-0 mt-0.5" strokeWidth={1.75} />
                    : reportStatus?.status === 'failed'
                        ? <AlertTriangle size={20} className="text-rose-500 shrink-0 mt-0.5" strokeWidth={1.75} />
                        : <AlertTriangle size={20} className="text-amber-500 shrink-0 mt-0.5" strokeWidth={1.75} />
                }
                <div>
                    <div className={`text-sm font-bold ${reportStatus?.status === 'completed' ? 'text-emerald-800' : reportStatus?.status === 'failed' ? 'text-rose-800' : 'text-amber-800'}`}>
                        {reportStatus?.status === 'completed' ? 'Report Available' : reportStatus?.status === 'failed' ? 'Report Generation Failed' : (reportStatus?.status ? 'Report Generating...' : 'Report Not Generated')}
                    </div>
                    <div className={`text-xs mt-0.5 leading-relaxed ${reportStatus?.status === 'completed' ? 'text-emerald-700' : reportStatus?.status === 'failed' ? 'text-rose-700' : 'text-amber-700'}`}>
                        {reportStatus?.status === 'completed'
                            ? `Static security analysis report generated successfully. ${scan.findings_count ?? findings.length} vulnerabilities included.`
                            : reportStatus?.status === 'failed'
                                ? `Generation failed: ${reportStatus.error_message || 'Unknown error'}`
                                : reportStatus?.status
                                    ? `Report generation in progress (${reportStatus.progress}%). You can leave this page and check back later.`
                                    : `The report has not been generated yet. Click "Generate Report" to start.`
                        }
                    </div>
                    {reportStatus?.status === 'completed' && reportStatus?.completed_at && (
                        <div className="flex items-center gap-1.5 mt-2 text-[11px] text-emerald-600 font-medium">
                            <Clock size={11} strokeWidth={2} />
                            Generated: {new Date(reportStatus.completed_at).toLocaleString()}
                        </div>
                    )}
                </div>
            </div>

            {/* ── Report Metadata ──────────────────────────────────────────── */}
            <SectionCard title="Report Details" icon={Info}>
                <div className="grid grid-cols-2 sm:grid-cols-4 gap-4">
                    {[
                        { label: 'Scan Name',    value: scan.name },
                        { label: 'Report Status',value: reportStatus?.status === 'completed' ? 'Available' : 'Pending' },
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
                    <div className="p-4 rounded-lg bg-slate-50 border border-slate-200 text-xs text-slate-700 leading-relaxed">
                        A total of <strong>{scan.findings_count ?? findings.length}</strong> findings were identified. Review the detailed findings table below to verify vulnerability severity, impacted files, and remediation steps.
                    </div>
                </div>
            </SectionCard>

            {/* ── Findings Section ─────────────────────────────────────── */}
            <SectionCard title="Findings" icon={List}>
                {findings.length === 0 ? (
                    <p className="text-sm text-slate-500 italic text-center py-6">No vulnerabilities detected.</p>
                ) : (
                    <div className="space-y-4">
                        {findings.map((f, idx) => (
                            <div key={f.id || idx} className="p-4 border border-slate-200 rounded-lg bg-slate-50/50 hover:bg-slate-50 transition cursor-pointer" onClick={() => onViewDetail && onViewDetail(f.id)}>
                                <div className="flex items-center justify-between gap-4 mb-2">
                                    <h3 className="text-sm font-bold text-slate-800 hover:text-brand-600 transition">{f.title}</h3>
                                    <SeverityBadge severity={f.severity?.value || f.severity} />
                                </div>
                                <div className="text-xs text-slate-600 space-y-1.5">
                                    <p><strong>Category:</strong> {f.category}</p>
                                    {(f.technical_details || f.url) && <p><strong>Affected:</strong> <code className="bg-white px-1 py-0.5 rounded border border-slate-200 font-mono text-[10px]">{f.technical_details || f.url}</code></p>}
                                    {f.cvss_score && <p><strong>CVSS Score:</strong> {f.cvss_score}</p>}
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
                )}
            </SectionCard>

            {/* ── Risk Summary ──────────────────────────────────── */}
            <SectionCard title="Risk Summary" icon={BarChart3}>
                {(() => {
                    const sc = scan.severity_counts || {};
                    const total = (sc.critical || 0) + (sc.high || 0) + (sc.medium || 0) + (sc.low || 0) + (sc.info || 0) || 1;
                    return (
                        <div className="space-y-3">
                            <p className="text-xs text-slate-500 mb-4">Vulnerability distribution by severity</p>
                            <SeverityBar label="Critical" color={SEVERITY_COLORS.critical.text} count={sc.critical || 0} max={total} />
                            <SeverityBar label="High"     color={SEVERITY_COLORS.high.text}     count={sc.high || 0}     max={total} />
                            <SeverityBar label="Medium"   color={SEVERITY_COLORS.medium.text}   count={sc.medium || 0}   max={total} />
                            <SeverityBar label="Low"      color={SEVERITY_COLORS.low.text}      count={sc.low || 0}      max={total} />
                            <SeverityBar label="Info"     color={SEVERITY_COLORS.info.text}     count={sc.info || 0}     max={total} />
                        </div>
                    );
                })()}
            </SectionCard>

            {/* ── Footer ───────────────────────────────────────────────────── */}
            <div className="flex items-center justify-between text-xs text-slate-400 pt-2 pb-6 border-t border-slate-100">
                <span>TrustNode Platform · Scan Report</span>
                <span>Generated successfully</span>
            </div>
        </div>
    );
}
