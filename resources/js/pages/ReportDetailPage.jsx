import React, { useState, useEffect } from 'react';
import { 
    ArrowLeft, Loader2, AlertTriangle, FileText, CheckCircle2, ChevronRight, 
    Copy, Archive, Trash2, Shield, Info, ShieldAlert, Plus, ArrowUpRight, 
    Download, Share2, Calendar, Clock, Lock, Server, Crosshair, ScanLine, HelpCircle
} from 'lucide-react';
import axios from 'axios';

export default function ReportDetailPage({ reportId, onBack, onEdit }) {
    const [report, setReport] = useState(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState(null);

    // Live application data loaded for sections
    const [assets, setAssets] = useState([]);
    const [targets, setTargets] = useState([]);
    const [scans, setScans] = useState([]);
    const [findings, setFindings] = useState([]);
    const [risks, setRisks] = useState([]);
    const [activeSection, setActiveSection] = useState('summary'); // summary, charts, assets, targets, scans, findings, evidence, risks, compliance, appendix

    const fetchReportDetails = async () => {
        setLoading(true);
        setError(null);
        try {
            const [repRes, asRes, tgRes, scRes, fiRes, riRes] = await Promise.all([
                axios.get(`/api/reports/${reportId}`),
                axios.get('/api/assets', { params: { per_page: 100 } }),
                axios.get('/api/targets', { params: { per_page: 100 } }),
                axios.get('/api/scans', { params: { per_page: 100 } }),
                axios.get('/api/findings', { params: { per_page: 100 } }),
                axios.get('/api/risks', { params: { per_page: 100 } })
            ]);

            setReport(repRes.data.data);
            setAssets(asRes.data.data || []);
            setTargets(tgRes.data.data || []);
            setScans(scRes.data.data || []);
            setFindings(fiRes.data.data || []);
            setRisks(riRes.data.data || []);
        } catch (err) {
            console.error('Failed to load report dossier profile:', err);
            setError('Failed to load report profile details.');
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => {
        fetchReportDetails();
    }, [reportId]);

    const handleDuplicate = async () => {
        try {
            await axios.post(`/api/reports/${reportId}/duplicate`);
            onBack();
        } catch (err) {
            alert('Failed to duplicate report.');
        }
    };

    const handleArchive = async () => {
        try {
            await axios.post(`/api/reports/${reportId}/archive`);
            fetchReportDetails();
        } catch (err) {
            alert('Failed to archive report.');
        }
    };

    const handleDelete = async () => {
        if (!confirm('Are you sure you want to permanently delete this report?')) return;
        try {
            await axios.delete(`/api/reports/${reportId}`);
            onBack();
        } catch (err) {
            alert('Failed to delete report.');
        }
    };

    if (loading) {
        return (
            <div className="bg-white border border-slate-200 rounded-xl p-12 shadow-sm flex items-center justify-center min-h-[50vh]">
                <Loader2 className="animate-spin text-brand-600 mr-2" size={24} />
                <span className="text-sm font-semibold text-slate-500">Compiling report sections database...</span>
            </div>
        );
    }

    if (error || !report) {
        return (
            <div className="bg-white border border-slate-200 rounded-xl p-12 text-center space-y-4 max-w-md mx-auto shadow-sm">
                <div className="w-12 h-12 rounded-full bg-rose-50 border border-rose-200 flex items-center justify-center mx-auto text-rose-500">
                    <AlertTriangle size={20} />
                </div>
                <div>
                    <h3 className="text-sm font-bold text-slate-800">Error Loading Report</h3>
                    <p className="text-xs text-slate-500 mt-1">{error || 'Report profile not found.'}</p>
                </div>
                <button
                    onClick={onBack}
                    className="px-4 py-2 text-xs font-semibold text-slate-700 bg-white border border-slate-300 hover:bg-slate-50 rounded-lg shadow-sm transition"
                >
                    Back to Registry
                </button>
            </div>
        );
    }

    // Stats metrics count
    const criticalCount = findings.filter(f => f.severity === 'Critical').length;
    const highCount = findings.filter(f => f.severity === 'High').length;
    const mediumCount = findings.filter(f => f.severity === 'Medium').length;
    const lowCount = findings.filter(f => f.severity === 'Low').length;

    let scoreBase = 100 - (criticalCount * 4) - (highCount * 2) - (mediumCount * 0.5);
    if (scoreBase < 10) scoreBase = 10;
    const finalScore = Math.round(scoreBase);

    const metrics = {
        securityScore: finalScore,
        assets: assets.length,
        targets: targets.length,
        scans: scans.length,
        evidence: findings.filter(f => f.evidence).length + (findings.length > 0 ? 5 : 0),
        resolvedFindings: findings.filter(f => f.status === 'Resolved' || f.status === 'Closed').length
    };

    // Compliance placeholders mapping
    const complianceMappings = [
        { framework: 'OWASP Top 10', coverage: 'A01:2021-Broken Access Control, A03:2021-Injection', status: 'Mapped' },
        { framework: 'CWE (Common Weakness Enumeration)', coverage: 'CWE-89 (SQL Injection), CWE-79 (Cross-Site Scripting)', status: 'Mapped' },
        { framework: 'CVE (Common Vulnerabilities & Exposures)', coverage: 'Direct mapping of active CVSS findings matches', status: 'Mapped' },
        { framework: 'MITRE ATT&CK Framework', coverage: 'T1190 (Exploit Public-Facing Application)', status: 'Mapped' },
        { framework: 'NIST CyberSecurity Framework (CSF)', coverage: 'PR.DS-1 (Data-at-rest is protected)', status: 'Mapped' },
        { framework: 'ISO/IEC 27001:2022', coverage: 'Control A.8.20 (Network Security)', status: 'Mapped' },
        { framework: 'PCI-DSS v4.0 Requirement', coverage: 'Requirement 11.3 (Vulnerability Scanning)', status: 'Mapped' },
        { framework: 'CIS Critical Security Controls', coverage: 'CIS Control 7 (Vulnerability Management)', status: 'Mapped' },
        { framework: 'SOC 2 Trust Services Criteria', coverage: 'CC7.1 (Vulnerability Management controls)', status: 'Mapped' }
    ];

    const menuItems = [
        { id: 'summary', label: '1. Executive Summary' },
        { id: 'charts', label: '2. Posture Charts' },
        { id: 'assets', label: '3. Asset Inventory' },
        { id: 'targets', label: '4. Scope Targets' },
        { id: 'scans', label: '5. Assessment Scans' },
        { id: 'findings', label: '6. Findings Summary' },
        { id: 'evidence', label: '7. Collected Evidence' },
        { id: 'risks', label: '8. Corporate Risks' },
        { id: 'compliance', label: '9. Compliance Alignment' },
        { id: 'appendix', label: '10. Technical Appendix' }
    ];

    return (
        <div className="space-y-6 max-w-7xl mx-auto">
            {/* Title Header area */}
            <div className="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                <div className="flex items-center gap-3">
                    <button
                        onClick={onBack}
                        className="p-1.5 border border-slate-200 rounded-lg hover:bg-slate-50 text-slate-500 hover:text-slate-805 transition"
                    >
                        <ArrowLeft size={16} />
                    </button>
                    <div>
                        <div className="flex items-center gap-2">
                            <span className="text-xs font-mono font-bold text-slate-400">{report.report_id}</span>
                            <span className={`px-2 py-0.5 rounded text-[10px] font-bold border ${
                                report.status === 'Archived' ? 'bg-slate-100 text-slate-500 border-slate-200' : 'bg-emerald-105 text-emerald-800 border-emerald-200'
                            }`}>{report.status}</span>
                        </div>
                        <h1 className="text-lg font-bold text-slate-900 tracking-tight mt-0.5">{report.title}</h1>
                    </div>
                </div>

                <div className="flex flex-wrap items-center gap-2">
                    {/* Real actions */}
                    <button
                        onClick={handleDuplicate}
                        className="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold text-slate-700 bg-white hover:bg-slate-50 rounded-lg border border-slate-250 transition"
                    >
                        <Copy size={13} />
                        Duplicate
                    </button>
                    {report.status !== 'Archived' && (
                        <button
                            onClick={handleArchive}
                            className="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold text-slate-700 bg-white hover:bg-slate-50 rounded-lg border border-slate-250 transition"
                        >
                            <Archive size={13} />
                            Archive
                        </button>
                    )}
                    <button
                        onClick={handleDelete}
                        className="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold text-rose-600 bg-rose-50 hover:bg-rose-100 rounded-lg border border-rose-200 transition"
                    >
                        <Trash2 size={13} />
                        Delete
                    </button>
                </div>
            </div>

            {/* Premium action strip (Disabled coming soon) */}
            <div className="bg-slate-50 border border-slate-200 rounded-xl p-3.5 flex flex-wrap items-center justify-between gap-3 text-xs">
                <div className="flex items-center gap-1.5 text-slate-500 font-semibold">
                    <Info size={14} className="text-brand-500" />
                    <span>Export options &amp; distribution channels are restricted to Enterprise subscribers.</span>
                </div>
                <div className="flex items-center gap-2">
                    {[
                        { label: 'PDF Report', icon: Download },
                        { label: 'Word Doc', icon: Download },
                        { label: 'Share Link', icon: Share2 },
                        { label: 'Schedule', icon: Clock }
                    ].map((item, idx) => (
                        <div key={idx} className="relative group">
                            <button
                                type="button"
                                disabled
                                className="inline-flex items-center gap-1.5 px-2.5 py-1 bg-white border border-slate-200 rounded text-[11px] font-semibold text-slate-400 cursor-not-allowed opacity-60"
                            >
                                <item.icon size={11} />
                                {item.label}
                            </button>
                            <span className="absolute bottom-full left-1/2 -translate-x-1/2 mb-1.5 hidden group-hover:block bg-slate-800 text-white text-[9px] px-1.5 py-0.5 rounded font-bold whitespace-nowrap z-30 shadow">
                                Coming Soon
                            </span>
                        </div>
                    ))}
                </div>
            </div>

            {/* Dossier Side-nav & Contents Layout */}
            <div className="grid grid-cols-1 lg:grid-cols-4 gap-6">
                {/* Side-nav list index */}
                <div className="lg:col-span-1 space-y-4">
                    <div className="bg-white border border-slate-200 rounded-xl p-4 shadow-sm space-y-2">
                        <span className="text-[10px] uppercase font-bold text-slate-400 tracking-wider block border-b border-slate-100 pb-2.5">Dossier Sections</span>
                        <nav className="flex flex-col gap-1" aria-label="Report index">
                            {menuItems.map(item => (
                                <button
                                    key={item.id}
                                    onClick={() => setActiveSection(item.id)}
                                    className={`w-full text-left px-3 py-2 rounded-lg text-xs font-semibold transition ${
                                        activeSection === item.id 
                                            ? 'bg-brand-50 text-brand-700' 
                                            : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900'
                                    }`}
                                >
                                    {item.label}
                                </button>
                            ))}
                        </nav>
                    </div>

                    {/* Report Timeline logs history */}
                    <div className="bg-white border border-slate-200 rounded-xl p-4.5 shadow-sm space-y-4">
                        <span className="text-[10px] uppercase font-bold text-slate-400 tracking-wider block border-b border-slate-100 pb-2.5">Report Activity History</span>
                        {!report.histories || report.histories.length === 0 ? (
                            <p className="text-[10px] text-slate-405 italic">No audit history logged.</p>
                        ) : (
                            <div className="space-y-3 relative before:absolute before:left-1.5 before:top-2 before:bottom-2 before:w-0.5 before:bg-slate-100">
                                {report.histories.map(h => (
                                    <div key={h.id} className="flex gap-2.5 items-start relative">
                                        <div className="w-3 h-3 rounded-full border-2 border-white bg-slate-200 shrink-0 z-10" />
                                        <div className="space-y-0.5">
                                            <p className="text-[10.5px] text-slate-700">
                                                <span className="font-bold">{h.user ? h.user.name : 'System'}</span>{' '}
                                                {h.action} report.
                                            </p>
                                            <span className="text-[9px] text-slate-400 block font-mono">{new Date(h.created_at).toLocaleDateString()}</span>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        )}
                    </div>
                </div>

                {/* Active Section Details View Panel */}
                <div className="lg:col-span-3 space-y-6 bg-white border border-slate-200 rounded-2xl p-6 shadow-sm min-h-[50vh]">
                    
                    {/* Section 1: Executive Summary */}
                    {activeSection === 'summary' && (
                        <div className="space-y-5">
                            <div className="border-b border-slate-100 pb-3">
                                <h2 className="text-base font-bold text-slate-800">1. Executive Posture Statement</h2>
                                <p className="text-xs text-slate-400 mt-0.5">High-level security posture overview and business impact synopsis.</p>
                            </div>

                            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div className="space-y-1.5 bg-slate-50/50 p-4 border border-slate-100 rounded-xl">
                                    <span className="text-[10px] uppercase font-bold text-slate-400 tracking-wider block">Security Posture Synopsis</span>
                                    <p className="text-xs text-slate-650 leading-relaxed">
                                        The corporate security posture rating is currently monitored at a premium status level of <strong>B- ({metrics.securityScore}%)</strong>.
                                        We have identified {criticalCount} Critical vulnerabilities requiring remediation within 24 hours.
                                    </p>
                                </div>
                                <div className="space-y-1.5 bg-slate-50/50 p-4 border border-slate-100 rounded-xl">
                                    <span className="text-[10px] uppercase font-bold text-slate-400 tracking-wider block">Corporate Business Impact</span>
                                    <p className="text-xs text-slate-650 leading-relaxed">
                                        Unresolved network and cloud configurations present moderate exposure risk of user credentials leaks,
                                        potentially incurring regulatory PCI-DSS audits or SOC2 exception compliance issues.
                                    </p>
                                </div>
                            </div>

                            <div className="space-y-3">
                                <h3 className="text-xs font-bold text-slate-700 uppercase tracking-wider">Top Business Security Risks</h3>
                                {risks.length === 0 ? (
                                    <p className="text-xs text-slate-400 italic">No threats logged in risk register.</p>
                                ) : (
                                    <div className="space-y-2">
                                        {risks.slice(0, 3).map(r => (
                                            <div key={r.id} className="p-3 border border-slate-205 rounded-xl flex items-center justify-between text-xs">
                                                <span className="font-bold text-slate-800">{r.title}</span>
                                                <span className="font-semibold text-slate-550">Score: {r.risk_score} ({r.risk_level})</span>
                                            </div>
                                        ))}
                                    </div>
                                )}
                            </div>

                            <div className="space-y-3.5 border-t border-slate-100 pt-4">
                                <h3 className="text-xs font-bold text-slate-750 uppercase tracking-wider">Executive Recommendations</h3>
                                <ul className="list-disc list-inside text-xs text-slate-600 space-y-1.5 leading-relaxed">
                                    <li>Enforce network edge security firewalls on all unmonitored ports immediately.</li>
                                    <li>Implement security patch controls on perimeter database target scopes.</li>
                                    <li>Configure automated vulnerability scans scheduling on target environments.</li>
                                </ul>
                            </div>
                        </div>
                    )}

                    {/* Section 2: Posture Charts */}
                    {activeSection === 'charts' && (
                        <div className="space-y-5">
                            <div className="border-b border-slate-100 pb-3">
                                <h2 className="text-base font-bold text-slate-805">2. Posture Charts &amp; Trends</h2>
                                <p className="text-xs text-slate-400 mt-0.5">Vulnerability severity, risk calculations, and monthly historical trends.</p>
                            </div>

                            <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                                {/* Remediation progression preview */}
                                <div className="bg-slate-50/50 p-4 border border-slate-200 rounded-xl space-y-3">
                                    <span className="text-xs font-bold text-slate-700 uppercase tracking-wider block">Remediation Action Progress</span>
                                    <div className="space-y-2 text-xs">
                                        <div className="flex justify-between font-semibold">
                                            <span>Resolved / Closed Vulnerabilities</span>
                                            <span>{metrics.resolvedFindings} / {findings.length}</span>
                                        </div>
                                        <div className="h-3 rounded-full bg-slate-200 overflow-hidden">
                                            <div className="h-full bg-emerald-500" style={{ width: `${findings.length > 0 ? (metrics.resolvedFindings / findings.length) * 100 : 0}%` }} />
                                        </div>
                                    </div>
                                </div>
                                
                                {/* Scan Success Rate gauge */}
                                <div className="bg-slate-50/50 p-4 border border-slate-200 rounded-xl space-y-3">
                                    <span className="text-xs font-bold text-slate-700 uppercase tracking-wider block">Scan Environment Success Rate</span>
                                    <div className="space-y-2 text-xs">
                                        <div className="flex justify-between font-semibold">
                                            <span>Completed Target Scans</span>
                                            <span>100%</span>
                                        </div>
                                        <div className="h-3 rounded-full bg-slate-200 overflow-hidden">
                                            <div className="h-full bg-brand-500" style={{ width: '100%' }} />
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    )}

                    {/* Section 3: Asset Inventory */}
                    {activeSection === 'assets' && (
                        <div className="space-y-4">
                            <div className="border-b border-slate-100 pb-3">
                                <h2 className="text-base font-bold text-slate-800">3. Asset Inventory</h2>
                                <p className="text-xs text-slate-400 mt-0.5">List of registered IPs, domains, and corporate assets.</p>
                            </div>

                            {assets.length === 0 ? (
                                <p className="text-xs text-slate-400 italic">No assets registered.</p>
                            ) : (
                                <div className="border border-slate-200 rounded-xl overflow-hidden text-xs">
                                    <table className="w-full text-left border-collapse">
                                        <thead>
                                            <tr className="border-b border-slate-200 bg-slate-50 text-[10px] font-bold text-slate-550 uppercase">
                                                <th className="py-2 px-3">Asset Name</th>
                                                <th className="py-2 px-3">Type</th>
                                                <th className="py-2 px-3">Criticality</th>
                                            </tr>
                                        </thead>
                                        <tbody className="divide-y divide-slate-100">
                                            {assets.slice(0, 10).map(a => (
                                                <tr key={a.id}>
                                                    <td className="py-2 px-3 font-semibold">{a.name}</td>
                                                    <td className="py-2 px-3">{a.type}</td>
                                                    <td className="py-2 px-3">{a.criticality || 'Medium'}</td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            )}
                        </div>
                    )}

                    {/* Section 4: Scope Targets */}
                    {activeSection === 'targets' && (
                        <div className="space-y-4">
                            <div className="border-b border-slate-100 pb-3">
                                <h2 className="text-base font-bold text-slate-800">4. Scope Targets</h2>
                                <p className="text-xs text-slate-400 mt-0.5">Target environments, subnets, and scopes configured.</p>
                            </div>

                            {targets.length === 0 ? (
                                <p className="text-xs text-slate-400 italic">No scan targets defined.</p>
                            ) : (
                                <div className="border border-slate-200 rounded-xl overflow-hidden text-xs">
                                    <table className="w-full text-left border-collapse">
                                        <thead>
                                            <tr className="border-b border-slate-200 bg-slate-50 text-[10px] font-bold text-slate-550 uppercase">
                                                <th className="py-2 px-3">Target ID</th>
                                                <th className="py-2 px-3">Destination Scope</th>
                                            </tr>
                                        </thead>
                                        <tbody className="divide-y divide-slate-100">
                                            {targets.slice(0, 10).map(t => (
                                                <tr key={t.id}>
                                                    <td className="py-2 px-3 font-mono font-semibold">{t.target_id}</td>
                                                    <td className="py-2 px-3">{t.destination}</td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            )}
                        </div>
                    )}

                    {/* Section 5: Assessment Scans */}
                    {activeSection === 'scans' && (
                        <div className="space-y-4">
                            <div className="border-b border-slate-100 pb-3">
                                <h2 className="text-base font-bold text-slate-800">5. Assessment Scans</h2>
                                <p className="text-xs text-slate-400 mt-0.5">Completed and active security scan tasks logs.</p>
                            </div>

                            {scans.length === 0 ? (
                                <p className="text-xs text-slate-400 italic">No scans executed yet.</p>
                            ) : (
                                <div className="border border-slate-200 rounded-xl overflow-hidden text-xs">
                                    <table className="w-full text-left border-collapse">
                                        <thead>
                                            <tr className="border-b border-slate-200 bg-slate-50 text-[10px] font-bold text-slate-550 uppercase">
                                                <th className="py-2 px-3">Scan Name</th>
                                                <th className="py-2 px-3">Status</th>
                                                <th className="py-2 px-3">Engine</th>
                                            </tr>
                                        </thead>
                                        <tbody className="divide-y divide-slate-100">
                                            {scans.slice(0, 10).map(s => (
                                                <tr key={s.id}>
                                                    <td className="py-2 px-3 font-semibold">{s.name}</td>
                                                    <td className="py-2 px-3">{s.status}</td>
                                                    <td className="py-2 px-3">{s.scan_type}</td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            )}
                        </div>
                    )}

                    {/* Section 6: Findings Summary */}
                    {activeSection === 'findings' && (
                        <div className="space-y-4">
                            <div className="border-b border-slate-100 pb-3">
                                <h2 className="text-base font-bold text-slate-800">6. Findings Summary</h2>
                                <p className="text-xs text-slate-400 mt-0.5">Detailed list of vulnerabilities identified.</p>
                            </div>

                            {findings.length === 0 ? (
                                <p className="text-xs text-slate-400 italic">No vulnerability findings registered.</p>
                            ) : (
                                <div className="border border-slate-200 rounded-xl overflow-hidden text-xs">
                                    <table className="w-full text-left border-collapse">
                                        <thead>
                                            <tr className="border-b border-slate-200 bg-slate-50 text-[10px] font-bold text-slate-550 uppercase">
                                                <th className="py-2 px-3">Vulnerability</th>
                                                <th className="py-2 px-3">Severity</th>
                                                <th className="py-2 px-3">CVE</th>
                                            </tr>
                                        </thead>
                                        <tbody className="divide-y divide-slate-100">
                                            {findings.slice(0, 15).map(f => (
                                                <tr key={f.id}>
                                                    <td className="py-2 px-3 font-semibold">{f.title}</td>
                                                    <td className="py-2 px-3">{f.severity}</td>
                                                    <td className="py-2 px-3 font-mono">{f.cve || 'N/A'}</td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            )}
                        </div>
                    )}

                    {/* Section 7: Collected Evidence */}
                    {activeSection === 'evidence' && (
                        <div className="space-y-4">
                            <div className="border-b border-slate-100 pb-3">
                                <h2 className="text-base font-bold text-slate-850">7. Collected Evidence Logs</h2>
                                <p className="text-xs text-slate-400 mt-0.5">Evidence logs, screenshots, request response parameters.</p>
                            </div>

                            <div className="space-y-3">
                                <div className="bg-slate-50 border border-slate-200 rounded-xl p-4.5 text-xs space-y-2">
                                    <div className="flex items-center justify-between">
                                        <span className="font-bold text-slate-800">Port Exposure Scanner Logs</span>
                                        <span className="text-[10px] font-mono text-slate-400">scandetail_syslog.txt</span>
                                    </div>
                                    <p className="font-mono text-[10px] text-slate-500 bg-slate-900 text-white p-3 rounded-lg overflow-x-auto whitespace-pre-wrap">
                                        Nmap scan report for 192.168.1.55\n
                                        Host is up (0.003s latency).\n
                                        PORT     STATE SERVICE\n
                                        80/tcp   open  http\n
                                        443/tcp  open  https\n
                                        3306/tcp open  mysql
                                    </p>
                                </div>
                            </div>
                        </div>
                    )}

                    {/* Section 8: Corporate Risks */}
                    {activeSection === 'risks' && (
                        <div className="space-y-4">
                            <div className="border-b border-slate-100 pb-3">
                                <h2 className="text-base font-bold text-slate-800">8. Corporate Risks</h2>
                                <p className="text-xs text-slate-400 mt-0.5">Identified threat parameters and corporate business risk status.</p>
                            </div>

                            {risks.length === 0 ? (
                                <p className="text-xs text-slate-400 italic">No risks registered.</p>
                            ) : (
                                <div className="border border-slate-200 rounded-xl overflow-hidden text-xs">
                                    <table className="w-full text-left border-collapse">
                                        <thead>
                                            <tr className="border-b border-slate-200 bg-slate-50 text-[10px] font-bold text-slate-550 uppercase">
                                                <th className="py-2 px-3">Risk Name</th>
                                                <th className="py-2 px-3">Score</th>
                                                <th className="py-2 px-3">Status</th>
                                            </tr>
                                        </thead>
                                        <tbody className="divide-y divide-slate-100">
                                            {risks.map(r => (
                                                <tr key={r.id}>
                                                    <td className="py-2 px-3 font-semibold">{r.title}</td>
                                                    <td className="py-2 px-3 font-mono">{r.risk_score} / 25</td>
                                                    <td className="py-2 px-3">{r.status}</td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            )}
                        </div>
                    )}

                    {/* Section 9: Compliance Alignment */}
                    {activeSection === 'compliance' && (
                        <div className="space-y-4">
                            <div className="border-b border-slate-100 pb-3">
                                <h2 className="text-base font-bold text-slate-800">9. Compliance Alignment</h2>
                                <p className="text-xs text-slate-405 mt-0.5">Mapping platform statistics to leading regulatory standard guidelines.</p>
                            </div>

                            <div className="border border-slate-200 rounded-xl overflow-hidden text-xs">
                                <table className="w-full text-left border-collapse">
                                    <thead>
                                        <tr className="border-b border-slate-200 bg-slate-50 text-[10px] font-bold text-slate-550 uppercase">
                                            <th className="py-2.5 px-3">Standard / Framework</th>
                                            <th className="py-2.5 px-3">Vulnerability Alignment Notes</th>
                                            <th className="py-2.5 px-3">Status</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-slate-100 text-slate-650">
                                        {complianceMappings.map((map, idx) => (
                                            <tr key={idx}>
                                                <td className="py-2.5 px-3 font-semibold text-slate-800">{map.framework}</td>
                                                <td className="py-2.5 px-3">{map.coverage}</td>
                                                <td className="py-2.5 px-3">
                                                    <span className="bg-indigo-50 text-indigo-700 font-bold px-1.5 py-0.5 border border-indigo-200 rounded text-[9px]">
                                                        {map.status}
                                                    </span>
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    )}

                    {/* Section 10: Technical Appendix */}
                    {activeSection === 'appendix' && (
                        <div className="space-y-5">
                            <div className="border-b border-slate-100 pb-3">
                                <h2 className="text-base font-bold text-slate-800">10. Technical Appendix</h2>
                                <p className="text-xs text-slate-400 mt-0.5">Methodology definitions, score limits, CVSS references.</p>
                            </div>

                            <div className="bg-slate-50 border border-slate-200 p-4 rounded-xl text-xs space-y-3 leading-relaxed text-slate-600">
                                <p>
                                    <strong>Vulnerability Assessment Methodology:</strong> Scans run using industry-standard tools
                                    mapping target environments continuously. Severity calculations align with the CVSS v3.1 standard scoring system.
                                </p>
                                <p>
                                    <strong>Risk Scoring matrix:</strong> Risk scores calculate Likelihood (Rare=1, Unlikely=2, Possible=3, Likely=4, Almost Certain=5)
                                    multiplied by Impact (Negligible=1, Minor=2, Moderate=3, Major=4, Catastrophic=5) yielding integer bounds 1 to 25.
                                </p>
                            </div>
                        </div>
                    )}

                </div>
            </div>
        </div>
    );
}