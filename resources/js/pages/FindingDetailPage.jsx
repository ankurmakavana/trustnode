import React, { useState, useEffect } from 'react';
import { 
    ArrowLeft, Edit2, ShieldAlert, Cpu, Crosshair, Scan, User, Clock, 
    Loader2, AlertTriangle, FileText, CheckCircle2, Link as LinkIcon, ExternalLink, Info, Shield, Play, Copy, XCircle, Settings, GitBranch, FileCode, CheckSquare, RefreshCw,
    FileCode2, Terminal, Image, Eye, Download, Search, ChevronRight, ChevronDown, ListFilter
} from 'lucide-react';
import { SeverityBadge, StatusBadge, CvssIndicator } from '../components/ui/primitives_findings';
import axios from 'axios';

export default function FindingDetailPage({ findingId, onBack, onEdit }) {
    const [finding, setFinding] = useState(null);
    const [logs, setLogs] = useState([]);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState(null);
    const [isManagerOrAdmin, setIsManagerOrAdmin] = useState(false);


    // Evidence States
    const [evidences, setEvidences] = useState([]);
    const [activeEvidence, setActiveEvidence] = useState(null);
    const [evSearch, setEvSearch] = useState('');
    const [evType, setEvType] = useState('');
    const [evSort, setEvSort] = useState('newest');

    useEffect(() => {
        const fetchFindingData = async () => {
            setLoading(true);
            setError(null);
            try {
                const [findingRes, logsRes] = await Promise.all([
                    axios.get(`/api/findings/${findingId}`),
                    axios.get(`/api/findings/${findingId}/activity`)
                ]);
                const f = findingRes.data.data;
                setFinding(f);
                setLogs(logsRes.data.data || []);

                // Use actual evidence from finding or fallback to empty array
                const evs = f.evidence_records || (f.evidence ? [{
                    uuid: 'ev-legacy',
                    type: 'text_note',
                    title: 'Legacy Evidence Record',
                    description: 'Raw evidence extracted from legacy finding structure.',
                    content: f.evidence,
                    filename: 'evidence.txt',
                    mime_type: 'text/plain',
                    size: 'Unknown',
                    hash: '-',
                    created_at: f.created_at || new Date().toISOString(),
                    created_by: 'System'
                }] : []);

                setEvidences(evs);
                setActiveEvidence(evs.length > 0 ? evs[0] : null);
            } catch (err) {
                console.error('Failed to load finding details:', err);
                setError('Failed to load finding details from database.');
            } finally {
                setLoading(false);
            }
        };
        fetchFindingData();
    }, [findingId]);

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


    const handleCopy = () => {
        if (!finding) return;
        const text = `REMEDIATION PLAN - ${finding?.finding_id || finding?.id}\n` +
                     `Title: ${finding?.title}\n` +
                     `Severity: ${finding?.severity || 'Unknown'}\n\n` +
                     `REMEDIATION\n${finding?.remediation || 'No remediation provided.'}`;
        navigator.clipboard.writeText(text);
        alert('Remediation plan copied to clipboard!');
    };

    const getEvidenceIcon = (type) => {
        switch (type) {
            case 'screenshot': return <Image size={15} className="text-blue-500" />;
            case 'http_request':
            case 'http_response': return <FileCode2 size={15} className="text-purple-500" />;
            case 'terminal_output': return <Terminal size={15} className="text-emerald-500" />;
            default: return <FileText size={15} className="text-slate-500" />;
        }
    };

    const handleCopyEvidence = (content) => {
        navigator.clipboard.writeText(content);
        alert('Evidence content copied to clipboard!');
    };

    // Filter and Sort evidences
    const filteredEvidences = evidences
        .filter(ev => {
            const matchesSearch = ev.title.toLowerCase().includes(evSearch.toLowerCase()) || 
                                  ev.description.toLowerCase().includes(evSearch.toLowerCase());
            const matchesType = evType ? ev.type === evType : true;
            return matchesSearch && matchesType;
        })
        .sort((a, b) => {
            if (evSort === 'newest') return new Date(b.created_at) - new Date(a.created_at);
            return new Date(a.created_at) - new Date(b.created_at);
        });

    if (loading) {
        return (
            <div className="bg-white border border-slate-200 rounded-xl p-12 shadow-sm flex items-center justify-center min-h-[40vh]">
                <Loader2 className="animate-spin text-brand-600 mr-2" size={24} />
                <span className="text-sm font-semibold text-slate-500">Retrieving vulnerability details...</span>
            </div>
        );
    }

    if (error || !finding) {
        return (
            <div className="bg-white border border-slate-200 rounded-xl p-12 text-center space-y-4 max-w-md mx-auto">
                <div className="w-12 h-12 rounded-full bg-rose-50 border border-rose-200 flex items-center justify-center mx-auto text-rose-500">
                    <AlertTriangle size={20} />
                </div>
                <div>
                    <h3 className="text-sm font-bold text-slate-800">Error Loading Profile</h3>
                    <p className="text-xs text-slate-500 mt-1">{error || 'Finding profile not found.'}</p>
                </div>
                <button
                    onClick={onBack}
                    className="px-4 py-2 text-xs font-semibold text-slate-700 bg-white border border-slate-300 hover:bg-slate-50 rounded-lg shadow-sm transition"
                >
                    Back to Findings
                </button>
            </div>
        );
    }

    return (
        <div className="space-y-6">
            {/* Header / Actions */}
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
                            <span className="text-xs font-mono font-bold text-slate-400">{finding.finding_id}</span>
                            <StatusBadge status={finding.status} />
                        </div>
                        <h1 className="text-lg font-bold text-slate-900 tracking-tight mt-0.5">{finding.title}</h1>
                    </div>
                </div>

                {isManagerOrAdmin && (
                    <button
                        onClick={() => onEdit(finding.id)}
                        className="inline-flex items-center justify-center gap-1.5 px-3.5 py-2 text-xs font-semibold text-slate-700 bg-white hover:bg-slate-50 rounded-lg shadow-sm border border-slate-300 transition"
                    >
                        <Edit2 size={14} />
                        Modify Finding
                    </button>
                )}
            </div>

            {/* Layout Grid */}
            <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
                {/* Main Vulnerability Metadata */}
                <div className="lg:col-span-2 space-y-6">
                    {/* Description & Impact */}
                    <div className="bg-white border border-slate-200 rounded-xl p-5 shadow-sm space-y-4">
                        <div>
                            <h3 className="text-xs font-bold text-slate-800 uppercase tracking-wider">Vulnerability Overview</h3>
                            <p className="text-xs text-slate-600 mt-2 leading-relaxed">
                                {finding.description || 'No description provided.'}
                            </p>
                        </div>
                        {finding.technical_details && (
                            <div className="border-t border-slate-100 pt-4">
                                <h3 className="text-xs font-bold text-slate-800 uppercase tracking-wider">Technical Details</h3>
                                <pre className="bg-slate-50 border border-slate-100 rounded-lg p-3 text-[11px] font-mono text-slate-700 mt-2 overflow-x-auto whitespace-pre-wrap">
                                    {finding.technical_details}
                                </pre>
                            </div>
                        )}
                        {finding.business_impact && (
                            <div className="border-t border-slate-100 pt-4">
                                <h3 className="text-xs font-bold text-slate-800 uppercase tracking-wider">Business Impact</h3>
                                <p className="text-xs text-slate-600 mt-2 leading-relaxed">
                                    {finding.business_impact}
                                </p>
                            </div>
                        )}
                    </div>

                    {/* Remediation Guidelines */}
                    <div className="bg-white border border-slate-200 rounded-xl p-5 shadow-sm space-y-4">
                        <div>
                            <h3 className="text-xs font-bold text-emerald-805 uppercase tracking-wider">Remediation Guidelines</h3>
                            <p className="text-xs text-slate-600 mt-2 leading-relaxed">
                                {finding.remediation || 'No remediation instructions registered.'}
                            </p>
                        </div>
                    </div>

                    {/* Professional Evidence Management V1 Section */}
                    <div className="bg-white border border-slate-200 rounded-xl p-5 shadow-sm space-y-4">
                        <div className="flex items-center justify-between border-b border-slate-100 pb-3">
                            <h3 className="text-sm font-bold text-slate-900 flex items-center gap-2">
                                <FileText size={16} className="text-brand-600" />
                                Evidence Records
                            </h3>
                            <span className="text-[10px] bg-slate-100 text-slate-500 font-bold uppercase px-2 py-0.5 rounded border border-slate-200">
                                {filteredEvidences.length} items
                            </span>
                        </div>

                        {/* Evidence Search and Filters */}
                        <div className="flex flex-col sm:flex-row gap-2.5 items-center">
                            <div className="relative flex-1 w-full">
                                <Search className="absolute left-2.5 top-2 text-slate-400" size={13} />
                                <input
                                    type="text"
                                    placeholder="Search evidence title or description..."
                                    value={evSearch}
                                    onChange={e => setEvSearch(e.target.value)}
                                    className="w-full pl-7 pr-3 py-1.5 border border-slate-200 rounded-lg text-xs text-slate-800 focus:outline-none focus:border-brand-400 transition bg-white"
                                />
                            </div>
                            <div className="flex gap-2 w-full sm:w-auto">
                                <select
                                    value={evType}
                                    onChange={e => setEvType(e.target.value)}
                                    className="border border-slate-200 rounded-lg text-xs text-slate-700 py-1.5 px-2 bg-white focus:outline-none"
                                >
                                    <option value="">All Types</option>
                                    <option value="http_request">HTTP Request</option>
                                    <option value="http_response">HTTP Response</option>
                                    <option value="screenshot">Screenshot</option>
                                    <option value="terminal_output">Terminal Output</option>
                                    <option value="text_note">Text Note</option>
                                </select>
                                <select
                                    value={evSort}
                                    onChange={e => setEvSort(e.target.value)}
                                    className="border border-slate-200 rounded-lg text-xs text-slate-700 py-1.5 px-2 bg-white focus:outline-none"
                                >
                                    <option value="newest">Newest</option>
                                    <option value="oldest">Oldest</option>
                                </select>
                            </div>
                        </div>

                        {/* Layout: Timeline / Cards vs Preview Panel */}
                        <div className="grid grid-cols-1 md:grid-cols-5 gap-4 pt-2">
                            {/* Left Side: Cards list */}
                            <div className="md:col-span-2 space-y-2.5 max-h-[360px] overflow-y-auto pr-1.5">
                                {filteredEvidences.length === 0 ? (
                                    <p className="text-xs text-slate-400 italic py-4 text-center">No matching evidence records.</p>
                                ) : (
                                    filteredEvidences.map(ev => (
                                        <div 
                                            key={ev.uuid} 
                                            onClick={() => setActiveEvidence(ev)}
                                            className={`border rounded-xl p-3 cursor-pointer transition-all ${
                                                activeEvidence?.uuid === ev.uuid 
                                                    ? 'border-brand-500 bg-brand-50/10 shadow-sm' 
                                                    : 'border-slate-200 hover:border-slate-350 bg-white'
                                            }`}
                                        >
                                            <div className="flex items-center gap-2">
                                                {getEvidenceIcon(ev.type)}
                                                <span className="font-bold text-[11px] text-slate-800 truncate flex-1">{ev.title}</span>
                                            </div>
                                            <p className="text-[10px] text-slate-400 truncate mt-1">{ev.description}</p>
                                            <div className="flex items-center justify-between mt-2.5 text-[9px] text-slate-400 font-mono">
                                                <span>{ev.size}</span>
                                                <span>{new Date(ev.created_at).toLocaleDateString()}</span>
                                            </div>
                                        </div>
                                    ))
                                )}
                            </div>

                            {/* Right Side: Preview & Metadata Panel */}
                            <div className="md:col-span-3 border border-slate-200 rounded-xl p-4 bg-slate-50/50 flex flex-col justify-between min-h-[300px] overflow-hidden">
                                {activeEvidence ? (
                                    <div className="space-y-3 flex-1 flex flex-col justify-between">
                                        <div>
                                            <div className="flex items-center justify-between border-b border-slate-200 pb-2">
                                                <div className="flex items-center gap-1.5">
                                                    {getEvidenceIcon(activeEvidence.type)}
                                                    <span className="font-bold text-[11.5px] text-slate-800">{activeEvidence.title}</span>
                                                </div>
                                                <div className="flex items-center gap-1">
                                                    <button
                                                        type="button"
                                                        onClick={() => handleCopyEvidence(activeEvidence.content)}
                                                        className="p-1 border border-slate-200 bg-white rounded hover:bg-slate-50 text-slate-550 transition"
                                                        title="Copy evidence content"
                                                    >
                                                        <Copy size={11} />
                                                    </button>
                                                    <button
                                                        type="button"
                                                        disabled
                                                        className="p-1 border border-slate-200 bg-slate-100 rounded text-slate-350 cursor-not-allowed"
                                                        title="Download - disabled for simulation"
                                                    >
                                                        <Download size={11} />
                                                    </button>
                                                </div>
                                            </div>

                                            {/* Preview canvas */}
                                            <div className="mt-3">
                                                {activeEvidence.type === 'screenshot' ? (
                                                    <div className="border border-slate-250 bg-white rounded-lg p-8 flex flex-col items-center justify-center text-center shadow-inner">
                                                        <Image size={24} className="text-slate-350 mb-2" />
                                                        <span className="text-xs font-semibold text-slate-400">Visual Verification Image</span>
                                                        <span className="text-[10px] text-slate-400 font-mono mt-1">{activeEvidence.filename}</span>
                                                    </div>
                                                ) : (
                                                    <pre className="bg-slate-900 border border-slate-950 rounded-lg p-3 text-[10.5px] font-mono text-emerald-450 overflow-x-auto whitespace-pre-wrap max-h-[180px]">
                                                        {activeEvidence.content}
                                                    </pre>
                                                )}
                                            </div>
                                        </div>

                                        {/* Metadata Footer panel */}
                                        <div className="border-t border-slate-200 pt-3 mt-3 text-[9.5px] text-slate-400 font-mono space-y-1.5 bg-slate-100/50 p-2.5 rounded-lg">
                                            <div className="flex justify-between">
                                                <span>Filename:</span>
                                                <span className="text-slate-650 font-bold">{activeEvidence.filename}</span>
                                            </div>
                                            <div className="flex justify-between">
                                                <span>Mime Type:</span>
                                                <span className="text-slate-650">{activeEvidence.mime_type}</span>
                                            </div>
                                            <div className="flex justify-between">
                                                <span>Hash (MD5):</span>
                                                <span className="text-slate-600 truncate max-w-[120px]" title={activeEvidence.hash}>{activeEvidence.hash}</span>
                                            </div>
                                            <div className="flex justify-between">
                                                <span>Created By:</span>
                                                <span className="text-slate-650">{activeEvidence.created_by}</span>
                                            </div>
                                        </div>
                                    </div>
                                ) : (
                                    <div className="flex flex-col items-center justify-center text-center flex-1">
                                        <Eye size={20} className="text-slate-300 mb-1.5" />
                                        <span className="text-xs text-slate-400 font-semibold">Select an evidence card to preview</span>
                                    </div>
                                )}
                            </div>
                        </div>
                    </div>

                    {/* Remediation Section */}
                    <div className="bg-white border border-slate-200 rounded-xl p-5 shadow-sm space-y-5">
                        <div className="flex items-center justify-between border-b border-slate-100 pb-3">
                            <h3 className="text-sm font-bold text-slate-900 flex items-center gap-2">
                                <Shield className="text-brand-600" size={16} />
                                Remediation Plan
                            </h3>
                            <button
                                type="button"
                                onClick={handleCopy}
                                className="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold text-slate-700 bg-white border border-slate-200 hover:bg-slate-50 rounded-lg shadow-sm transition"
                            >
                                <Copy size={12} />
                                Copy Plan
                            </button>
                        </div>
                        
                        <div className="text-sm text-slate-700 whitespace-pre-wrap leading-relaxed">
                            {finding.remediation ? (
                                finding.remediation
                            ) : (
                                <div className="flex flex-col items-center justify-center py-6 text-slate-400">
                                    <ShieldAlert size={24} className="mb-2 opacity-50" />
                                    <p>No remediation provided by scanner.</p>
                                </div>
                            )}
                        </div>

                        {/* Future Automation section (disabled placeholder) */}
                        <div className="border-t border-slate-100 pt-4 space-y-3">
                            <div className="flex items-center justify-between">
                                <h4 className="text-xs font-bold text-slate-400 uppercase tracking-wider flex items-center gap-1.5">
                                    <Settings size={13} />
                                    Future Automation
                                </h4>
                                <span className="text-[9px] bg-slate-100 text-slate-500 font-bold uppercase px-1.5 py-0.5 rounded">
                                    Coming Soon
                                </span>
                            </div>
                            <p className="text-[11px] text-slate-400 leading-relaxed">
                                Automated patch creation and patch merging triggers will be integrated here in future agent upgrades.
                            </p>
                            <div className="grid grid-cols-2 md:grid-cols-4 gap-3 text-[10.5px]">
                                {[
                                    { label: 'Create Git Branch', icon: GitBranch },
                                    { label: 'Generate Patch',   icon: FileCode },
                                    { label: 'Run Safe Tests',   icon: CheckSquare },
                                    { label: 'Submit Pull Request', icon: RefreshCw }
                                ].map((item, i) => {
                                    const Icon = item.icon;
                                    return (
                                        <div key={i} className="flex flex-col items-center justify-center p-3 rounded-lg border border-slate-100 bg-slate-50 text-slate-400 opacity-60 cursor-not-allowed">
                                            <Icon size={14} className="mb-1.5" />
                                            <span className="font-semibold text-center">{item.label}</span>
                                        </div>
                                    );
                                })}
                            </div>
                        </div>
                    </div>
                </div>

                {/* Sidebar Context Panel */}
                <div className="space-y-6">
                    {/* Related Assets/Targets */}
                    <div className="bg-white border border-slate-200 rounded-xl p-5 shadow-sm space-y-4">
                        <h3 className="text-xs font-bold text-slate-800 uppercase tracking-wider">Contextual Mappings</h3>
                        
                        <div className="space-y-3">
                            {/* Target link card */}
                            <div className="flex gap-3 items-start border border-slate-100 rounded-lg p-3 bg-slate-50/50">
                                <Crosshair className="text-slate-400 shrink-0 mt-0.5" size={16} />
                                <div>
                                    <span className="text-[10px] text-slate-400 font-bold block uppercase">Target Environment</span>
                                    <span className="text-xs font-semibold text-slate-700 block mt-0.5 break-all">
                                        {finding.target ? finding.target.name : (finding.url || 'Unknown Target')}
                                    </span>
                                    {finding.target && (
                                        <span className="text-[10px] text-slate-400 font-mono block mt-0.5">{finding.target.value}</span>
                                    )}
                                </div>
                            </div>

                            {/* Asset link card */}
                            <div className="flex gap-3 items-start border border-slate-100 rounded-lg p-3 bg-slate-50/50">
                                <Cpu className="text-slate-400 shrink-0 mt-0.5" size={16} />
                                <div>
                                    <span className="text-[10px] text-slate-400 font-bold block uppercase">Assigned Asset</span>
                                    <span className="text-xs font-semibold text-slate-700 block mt-0.5">
                                        {finding.asset ? finding.asset.name : 'Unmapped'}
                                    </span>
                                    {finding.asset && (
                                        <span className="text-[10px] text-slate-400 font-mono block mt-0.5">{finding.asset.value}</span>
                                    )}
                                </div>
                            </div>

                            {/* Scan execution context */}
                            <div className="flex gap-3 items-start border border-slate-100 rounded-lg p-3 bg-slate-55/50">
                                <Scan className="text-slate-400 shrink-0 mt-0.5" size={16} />
                                <div>
                                    <span className="text-[10px] text-slate-400 font-bold block uppercase">Scan Origin</span>
                                    <span className="text-xs font-semibold text-slate-700 block mt-0.5">
                                        {finding.scan ? finding.scan.name : 'Manual assessment'}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>

                    {/* Change Logs Activity Timeline */}
                    <div className="bg-white border border-slate-200 rounded-xl p-5 shadow-sm space-y-4">
                        <h3 className="text-xs font-bold text-slate-800 uppercase tracking-wider">Assessment Log Timeline</h3>
                        
                        {logs.length === 0 ? (
                            <p className="text-[11px] text-slate-400 italic">No activity logs recorded.</p>
                        ) : (
                            <div className="space-y-3.5 relative before:absolute before:left-2 before:top-2 before:bottom-2 before:w-0.5 before:bg-slate-100">
                                {logs.map((log) => (
                                    <div key={log.id} className="flex gap-3 items-start relative">
                                        <div className="w-4.5 h-4.5 rounded-full border-2 border-white bg-slate-200 flex items-center justify-center shrink-0 text-slate-550 z-10">
                                            <Clock size={10} />
                                        </div>
                                        <div className="space-y-0.5">
                                            <p className="text-[11px] text-slate-650">
                                                <span className="font-semibold text-slate-800">{log.user?.name || 'System'}</span>{' '}
                                                {log.action} the finding.
                                            </p>
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