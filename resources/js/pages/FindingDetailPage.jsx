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

    // AI Remediation States
    const [aiStatus, setAiStatus] = useState('not_generated'); 
    const [generatingProgress, setGeneratingProgress] = useState(0);

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

                // Seed realistic VAPT evidence database mock based on category/title
                const isWeb = f.category === 'web_application' || f.title.toLowerCase().includes('xss') || f.title.toLowerCase().includes('sql');
                const mockEvs = [
                    {
                        uuid: 'ev-001',
                        type: 'http_request',
                        title: 'Captured Attack Vector HTTP Request',
                        description: 'Raw ingress HTTP request headers containing payload parameters.',
                        content: `POST /api/auth/login HTTP/1.1\nHost: trustnode.local\nContent-Type: application/json\nUser-Agent: Mozilla/5.0\n\n{\n  "username": "admin\' OR \'1\'=\'1",\n  "password": "tempPass123!"\n}`,
                        filename: 'attack_request.http',
                        mime_type: 'text/plain',
                        size: '240 B',
                        hash: '4f28c50d32152631aef71a2588fbd103',
                        created_at: new Date(Date.now() - 3600000).toISOString(),
                        created_by: 'Automated Scan Engine'
                    },
                    {
                        uuid: 'ev-002',
                        type: 'http_response',
                        title: 'Target Server Leakage HTTP Response',
                        description: 'HTTP response body containing SQL system error messages.',
                        content: `HTTP/1.1 500 Internal Server Error\nContent-Type: text/html; charset=UTF-8\n\n<html>\n  <body>\n    <h3>SQL syntax error in query template:</h3>\n    <p>SELECT * FROM accounts WHERE user = 'admin' OR '1'='1' AND pass = 'tempPass123!'</p>\n  </body>\n</html>`,
                        filename: 'leakage_response.http',
                        mime_type: 'text/html',
                        size: '310 B',
                        hash: 'a8b9015cdef31930b8d5a15159efd142',
                        created_at: new Date(Date.now() - 3500000).toISOString(),
                        created_by: 'Automated Scan Engine'
                    },
                    {
                        uuid: 'ev-003',
                        type: 'screenshot',
                        title: 'Database Schema Leak Verification',
                        description: 'Mock capture showing database table structures exposed via logs.',
                        content: '[Mock Screenshot: Database Admin Portal bypass state showing tables: users, sessions, settings]',
                        filename: 'db_schema_leak.png',
                        mime_type: 'image/png',
                        size: '142 KB',
                        hash: '7e8f9b01519d30b8ef4a1599a14def90',
                        created_at: new Date(Date.now() - 3400000).toISOString(),
                        created_by: 'Security Analyst'
                    },
                    {
                        uuid: 'ev-004',
                        type: 'terminal_output',
                        title: 'Nmap TCP Syn Port Scan Trace',
                        description: 'Diagnostic output verifying open ports configuration status.',
                        content: `Starting Nmap 7.92 ( https://nmap.org ) at 2026-08-06 20:12\nNmap scan report for 10.10.1.254\nHost is up (0.0021s latency).\nNot shown: 997 closed tcp ports (reset)\nPORT     STATE SERVICE\n80/tcp   open  http\n443/tcp  open  https\n8081/tcp open  blackice-icecap\n\nNmap done: 1 IP address scanned in 1.45 seconds`,
                        filename: 'nmap_trace.log',
                        mime_type: 'text/plain',
                        size: '380 B',
                        hash: '3e4f5a6b7c8d9e0f1a2b3c4d5e6f7a8b',
                        created_at: new Date(Date.now() - 3300000).toISOString(),
                        created_by: 'Scan Worker node-4'
                    },
                    {
                        uuid: 'ev-005',
                        type: 'text_note',
                        title: 'Penetration Testing Summary Notes',
                        description: 'Triage annotation added during manual analysis validation.',
                        content: `Validation Notes:\nVerified that the login portal executes queries without binding input variables. Attacker payload ' OR '1'='1 bypasses authentication logic. High likelihood of total DB access.`,
                        filename: 'analyst_notes.txt',
                        mime_type: 'text/plain',
                        size: '185 B',
                        hash: '9f8e7d6c5b4a3f2e1d0c9b8a7f6e5d4c',
                        created_at: new Date(Date.now() - 3200000).toISOString(),
                        created_by: 'Security Analyst'
                    }
                ];
                setEvidences(mockEvs);
                setActiveEvidence(mockEvs[0]);
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

    const handleGenerateAI = () => {
        if (aiStatus !== 'not_generated') return;
        setAiStatus('generating');
        setGeneratingProgress(10);
        
        const interval = setInterval(() => {
            setGeneratingProgress(prev => {
                if (prev >= 90) {
                    clearInterval(interval);
                    return 90;
                }
                return prev + 20;
            });
        }, 305);

        setTimeout(() => {
            clearInterval(interval);
            setGeneratingProgress(100);
            setAiStatus('ready');
        }, 1500);
    };

    const handleApprove = () => {
        if (aiStatus !== 'ready') return;
        setAiStatus('approved');
    };

    const handleReject = () => {
        if (aiStatus !== 'ready') return;
        setAiStatus('rejected');
    };

    const handleCopy = () => {
        const text = `AI REMEDIATION ASSESSMENT PLAN - ${finding?.finding_id}\n` +
                     `Title: ${finding?.title}\n` +
                     `CVSS Score: ${finding?.cvss_score || 'N/A'}\n` +
                     `Severity: ${finding?.severity || 'Medium'}\n\n` +
                     `EXECUTIVE SUMMARY\nThe VAPT assessment identified input handling issues. Corrective validation is required.\n\n` +
                     `ROOT CAUSE ANALYSIS\nLack of parameterization templates during query mapping.\n\n` +
                     `RECOMMENDED FIX\nImplement secure parameterized query bindings.`;
        navigator.clipboard.writeText(text);
        alert('AI Remediation plan copied to clipboard!');
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

                    {/* AI Remediation Assistant V1 Section */}
                    <div className="bg-white border border-slate-200 rounded-xl p-5 shadow-sm space-y-5">
                        <div className="flex items-center justify-between border-b border-slate-100 pb-3">
                            <h3 className="text-sm font-bold text-slate-900 flex items-center gap-2">
                                <Shield className="text-brand-600" size={16} />
                                AI Remediation Assistant
                            </h3>
                            <span className="text-[10px] bg-brand-50 text-brand-700 font-bold uppercase tracking-wider px-2 py-0.5 rounded border border-brand-100">
                                AI Engineer V1
                            </span>
                        </div>

                        {/* Top Status Banner */}
                        <div className={`p-4 rounded-xl border flex items-start gap-3 text-xs ${
                            aiStatus === 'not_generated' ? 'bg-slate-50 border-slate-200 text-slate-600' :
                            aiStatus === 'generating'    ? 'bg-blue-50/50 border-blue-200 text-blue-700 font-medium animate-pulse' :
                            aiStatus === 'ready'         ? 'bg-amber-50/50 border-amber-250 text-amber-808' :
                            aiStatus === 'approved'      ? 'bg-emerald-50 border-emerald-200 text-emerald-850' :
                                                           'bg-rose-50 border-rose-200 text-rose-850'
                        }`}>
                            <Info size={16} className="shrink-0 mt-0.5" />
                            <div className="space-y-1">
                                <span className="font-bold block uppercase tracking-wide text-[10px]">
                                    AI Remediation Status: {
                                        aiStatus === 'not_generated' ? 'Not Generated' :
                                        aiStatus === 'generating'    ? 'Generating Analysis Plan...' :
                                        aiStatus === 'ready'         ? 'Ready for Triage' :
                                        aiStatus === 'approved'      ? 'Approved' :
                                                                       'Rejected'
                                    }
                                </span>
                                <p className="text-[11px] leading-relaxed">
                                    {aiStatus === 'not_generated' && 'No remediation plan has been computed yet. Request an assessment below.'}
                                    {aiStatus === 'generating' && `Building complete vector assessment... (${generatingProgress}%)`}
                                    {aiStatus === 'ready' && 'AI Security Agent has generated the remediation blueprint. Review parameters and confirm execution authorization.'}
                                    {aiStatus === 'approved' && 'Remediation approved. Ready for future AI Fix Agent.'}
                                    {aiStatus === 'rejected' && 'Remediation rejected.'}
                                </p>
                            </div>
                        </div>

                        {/* AI Actions toolbar */}
                        <div className="flex flex-wrap items-center gap-2.5">
                            <button
                                type="button"
                                onClick={handleGenerateAI}
                                disabled={aiStatus !== 'not_generated'}
                                className="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold text-white bg-brand-600 hover:bg-brand-700 disabled:opacity-40 disabled:cursor-not-allowed rounded-lg shadow-sm transition"
                            >
                                <Play size={12} />
                                Generate AI Analysis
                            </button>
                            <button
                                type="button"
                                onClick={handleApprove}
                                disabled={aiStatus !== 'ready'}
                                className="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold text-white bg-emerald-600 hover:bg-emerald-700 disabled:opacity-40 disabled:cursor-not-allowed rounded-lg shadow-sm transition"
                            >
                                <CheckCircle2 size={12} />
                                Approve Remediation
                            </button>
                            <button
                                type="button"
                                onClick={handleReject}
                                disabled={aiStatus !== 'ready'}
                                className="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold text-white bg-rose-600 hover:bg-rose-700 disabled:opacity-40 disabled:cursor-not-allowed rounded-lg shadow-sm transition"
                            >
                                <XCircle size={12} />
                                Reject Remediation
                            </button>
                            <button
                                type="button"
                                onClick={handleCopy}
                                disabled={!['ready', 'approved', 'rejected'].includes(aiStatus)}
                                className="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold text-slate-700 bg-white border border-slate-200 hover:bg-slate-50 disabled:opacity-40 disabled:cursor-not-allowed rounded-lg shadow-sm transition ml-auto"
                            >
                                <Copy size={12} />
                                Copy Plan
                            </button>
                        </div>

                        {/* AI Analysis Plan content */}
                        {(aiStatus === 'ready' || aiStatus === 'approved' || aiStatus === 'rejected') && (
                            <div className="border border-slate-150 rounded-xl p-5 bg-slate-50/50 space-y-6 text-xs text-slate-700">
                                
                                {/* Metrics Block */}
                                <div className="grid grid-cols-3 gap-4 border-b border-slate-200 pb-4">
                                    <div>
                                        <span className="text-[10px] text-slate-400 uppercase font-bold block">Confidence Score</span>
                                        <span className="text-sm font-bold text-slate-800 mt-0.5 block">95%</span>
                                    </div>
                                    <div>
                                        <span className="text-[10px] text-slate-400 uppercase font-bold block">Estimated Fix Time</span>
                                        <span className="text-sm font-bold text-slate-800 mt-0.5 block">15 mins</span>
                                    </div>
                                    <div>
                                        <span className="text-[10px] text-slate-400 uppercase font-bold block">Potential Breaking Risk</span>
                                        <span className="text-xs font-bold text-amber-600 mt-1 block uppercase">Low Risk</span>
                                    </div>
                                </div>

                                {/* Executive Narrative */}
                                <div className="space-y-1.5">
                                    <h4 className="font-bold text-slate-850 uppercase text-[10px] tracking-wider">Executive Summary</h4>
                                    <p className="leading-relaxed text-slate-600">
                                        The security scan identified a vulnerability corresponding to inadequate sanitization vectors inside the authentication middleware routing paths. An attacker targeting this vector could manipulate session authentication payloads to bypass validation limits or extract credentials parameters.
                                    </p>
                                </div>

                                {/* Root Cause */}
                                <div className="space-y-1.5">
                                    <h4 className="font-bold text-slate-850 uppercase text-[10px] tracking-wider">Root Cause Analysis</h4>
                                    <p className="leading-relaxed text-slate-600">
                                        Lack of parameterized statement mapping within raw database query builders allows unfiltered input characters (such as quotes or comments) to alter the SQL syntax structure.
                                    </p>
                                </div>

                                {/* Threat vector risks */}
                                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div className="space-y-1">
                                        <h4 className="font-bold text-slate-850 uppercase text-[10px] tracking-wider">Technical Risk</h4>
                                        <p className="leading-relaxed text-slate-600">
                                            Arbitrary data leakage, database server host compromise, and complete extraction of sensitive database records.
                                        </p>
                                    </div>
                                    <div className="space-y-1">
                                        <h4 className="font-bold text-slate-850 uppercase text-[10px] tracking-wider">Business Risk</h4>
                                        <p className="leading-relaxed text-slate-600">
                                            Violations of regulatory standards (PCI-DSS, GDPR) and potential data breach reputational impacts.
                                        </p>
                                    </div>
                                </div>

                                {/* Attack Scenario simulation */}
                                <div className="space-y-1.5">
                                    <h4 className="font-bold text-slate-850 uppercase text-[10px] tracking-wider">Attack Scenario</h4>
                                    <p className="leading-relaxed text-slate-600">
                                        1. Attacker sends a POST login request with payload containing injection characters. <br/>
                                        2. System parses payload and concatenates string inputs directly into SQL string builders. <br/>
                                        3. SQL parser executes the statement, returning full administrative access credentials.
                                    </p>
                                </div>

                                {/* Fix Recommendation */}
                                <div className="space-y-2 border-t border-slate-200 pt-4">
                                    <h4 className="font-bold text-emerald-850 uppercase text-[10px] tracking-wider">Recommended Fix Plan</h4>
                                    <p className="leading-relaxed text-slate-600">
                                        Migrate the raw concatenation query syntax to standard parameterized bindings using the database framework driver constructs.
                                    </p>
                                </div>

                                {/* Step by step */}
                                <div className="space-y-2">
                                    <h4 className="font-bold text-slate-850 uppercase text-[10px] tracking-wider">Step-by-Step Remediation</h4>
                                    <ol className="list-decimal pl-4 space-y-1 text-slate-600">
                                        <li>Identify the file reference matching the query definition.</li>
                                        <li>Replace raw inline string injection variables with structured parameterized bindings (`?` or named parameters).</li>
                                        <li>Bind request input variables cleanly into the execution parameters arrays.</li>
                                    </ol>
                                </div>

                                {/* Configuration Changes */}
                                <div className="space-y-1.5">
                                    <h4 className="font-bold text-slate-850 uppercase text-[10px] tracking-wider">Configuration Changes</h4>
                                    <pre className="bg-slate-100 border border-slate-200 rounded p-2.5 text-[10px] font-mono text-slate-700">
                                        DB_STRICT_MODE=true
                                    </pre>
                                </div>

                                {/* Code Level recommendations */}
                                <div className="space-y-1.5">
                                    <h4 className="font-bold text-slate-850 uppercase text-[10px] tracking-wider">Code-Level Recommendations</h4>
                                    <pre className="bg-slate-900 border border-slate-950 rounded-lg p-3 text-[10.5px] font-mono text-emerald-400 overflow-x-auto whitespace-pre-wrap">
                                        {`// Vulnerable Implementation\n` +
                                         `$user = DB::select(\"SELECT * FROM users WHERE email = '\" . $request->input('email') . "'\");\n\n` +
                                         `// Remediation Implementation\n` +
                                         `$user = DB::select(\"SELECT * FROM users WHERE email = :email\", [\n` +
                                         `    'email' => $request->input('email')\n` +
                                         `]);`}
                                    </pre>
                                </div>

                                {/* Validation steps */}
                                <div className="space-y-2 border-t border-slate-200 pt-4">
                                    <h4 className="font-bold text-slate-850 uppercase text-[10px] tracking-wider">Validation Steps</h4>
                                    <p className="leading-relaxed text-slate-600">
                                        Execute the identical login POST payload and verify the server outputs a strict SQL parsing exception instead of credentials records logs.
                                    </p>
                                </div>

                                {/* Regression & Rollback */}
                                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div className="space-y-1">
                                        <h4 className="font-bold text-slate-850 uppercase text-[10px] tracking-wider">Regression Testing Checklist</h4>
                                        <ul className="list-disc pl-4 space-y-0.5 text-slate-600">
                                            <li>Verify general user login functions.</li>
                                            <li>Check session session token persistence.</li>
                                            <li>Verify password recovery processes.</li>
                                        </ul>
                                    </div>
                                    <div className="space-y-1">
                                        <h4 className="font-bold text-slate-850 uppercase text-[10px] tracking-wider">Rollback Strategy</h4>
                                        <p className="leading-relaxed text-slate-600">
                                            Restore previous code configuration commits from VCS repository history tracking branches.
                                        </p>
                                    </div>
                                </div>

                                {/* References */}
                                <div className="space-y-2 border-t border-slate-200 pt-4">
                                    <h4 className="font-bold text-slate-850 uppercase text-[10px] tracking-wider">Security Taxonomy References</h4>
                                    <div className="flex flex-wrap gap-2 text-[10px]">
                                        <span className="bg-slate-100 text-slate-700 px-2 py-0.5 rounded font-mono font-bold">OWASP: A03:2021-Injection</span>
                                        <span className="bg-slate-100 text-slate-700 px-2 py-0.5 rounded font-mono font-bold">CWE-89: SQL Injection</span>
                                        {finding?.cve && <span className="bg-slate-100 text-slate-700 px-2 py-0.5 rounded font-mono font-bold">CVE: {finding.cve}</span>}
                                    </div>
                                </div>

                            </div>
                        )}

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
                                        <button
                                            key={i}
                                            type="button"
                                            disabled
                                            className="flex items-center justify-center gap-1.5 py-2 px-2.5 border border-dashed border-slate-200 rounded-lg text-slate-350 cursor-not-allowed bg-slate-50/20 transition"
                                        >
                                            <Icon size={12} />
                                            {item.label}
                                        </button>
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
                                    <span className="text-xs font-semibold text-slate-700 block mt-0.5">
                                        {finding.target ? finding.target.name : 'Unknown Target'}
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