import React, { useState, useEffect, useRef } from 'react';
import axios from 'axios';
import { 
    X, Upload, Shield, Activity, FileText, CheckCircle2, AlertTriangle, 
    Loader2, Play, Trash2, RefreshCw, Server, Globe, Link, Sparkles, Terminal, ChevronRight, HelpCircle
} from 'lucide-react';

export default function ImportWizard({ isOpen, onClose, connectorCode, integrationId, onComplete }) {
    if (!isOpen) return null;

    const [step, setStep] = useState(1);
    const [sourceType, setSourceType] = useState('file'); // file, scanner, api, url
    const [file, setFile] = useState(null);
    const [isDragOver, setIsDragOver] = useState(false);
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState(null);
    const [previewData, setPreviewData] = useState(null);
    const [parsedFindings, setParsedFindings] = useState([]);
    
    // Import progress and logs polling state
    const [job, setJob] = useState(null);
    const [progress, setProgress] = useState(0);
    const [logs, setLogs] = useState([]);
    const pollInterval = useRef(null);

    // History log list tab inside wizard
    const [showHistory, setShowHistory] = useState(false);
    const [historyList, setHistoryList] = useState([]);
    const [selectedHistoryLog, setSelectedHistoryLog] = useState(null);
    const [loadingHistory, setLoadingHistory] = useState(false);

    // Block background page scrolling
    useEffect(() => {
        if (isOpen) {
            document.body.style.overflow = 'hidden';
            setStep(1);
            setFile(null);
            setError(null);
            setPreviewData(null);
            setParsedFindings([]);
            setJob(null);
            setProgress(0);
            setLogs([]);
            fetchHistory();
        }
        return () => {
            document.body.style.overflow = '';
            clearInterval(pollInterval.current);
        };
    }, [isOpen]);

    const fetchHistory = async () => {
        setLoadingHistory(true);
        try {
            const r = await axios.get('/api/imports/history');
            if (r.data?.success) {
                setHistoryList(r.data.data);
            }
        } catch (e) {
            console.error("Failed to fetch import histories.", e);
        } finally {
            setLoadingHistory(false);
        }
    };

    const handleFileChange = (e) => {
        if (e.target.files && e.target.files[0]) {
            setFile(e.target.files[0]);
            setError(null);
        }
    };

    const handleDragOver = (e) => {
        e.preventDefault();
        setIsDragOver(true);
    };

    const handleDragLeave = () => {
        setIsDragOver(false);
    };

    const handleDrop = (e) => {
        e.preventDefault();
        setIsDragOver(false);
        if (e.dataTransfer.files && e.dataTransfer.files[0]) {
            setFile(e.dataTransfer.files[0]);
            setError(null);
        }
    };

    // Step 1 -> Step 2: Validate file content and load findings preview
    const handleValidate = async () => {
        if (!file) {
            setError("Please select or drop a file to import.");
            return;
        }

        setLoading(true);
        setError(null);

        const formData = new FormData();
        formData.append('file', file);
        formData.append('scanner', connectorCode || 'nmap');

        try {
            const r = await axios.post('/api/imports/preview', formData, {
                headers: { 'Content-Type': 'multipart/form-data' }
            });
            if (r.data?.success) {
                setPreviewData(r.data.data);
                
                // Read local parse to populate preview table
                const reader = new FileReader();
                reader.onload = (event) => {
                    try {
                        const content = event.target.result;
                        // Simple parser simulation on frontend side for preview table
                        let list = [];
                        if (connectorCode === 'nmap') {
                            if (content.includes('<nmaprun')) {
                                // Extract open ports
                                const parser = new DOMParser();
                                const xmlDoc = parser.parseFromString(content, "text/xml");
                                const hosts = xmlDoc.getElementsByTagName("host");
                                for (let i = 0; i < hosts.length; i++) {
                                    const ip = hosts[i].getElementsByTagName("address")[0]?.getAttribute("addr") || "127.0.0.1";
                                    const ports = hosts[i].getElementsByTagName("port");
                                    for (let j = 0; j < ports.length; j++) {
                                        const portId = ports[j].getAttribute("portid");
                                        const service = ports[j].getElementsByTagName("service")[0]?.getAttribute("name") || "unknown";
                                        const state = ports[j].getElementsByTagName("state")[0]?.getAttribute("state") || "";
                                        if (state === 'open') {
                                            list.push({
                                                severity: 'low',
                                                title: `Open Port ${portId} (${service})`,
                                                asset: ip,
                                                cve: '—',
                                                cvss: '2.0',
                                                status: 'open'
                                            });
                                        }
                                    }
                                }
                            } else {
                                const parsedJson = JSON.parse(content);
                                list = (parsedJson.findings || []).map(f => ({
                                    severity: f.severity || 'low',
                                    title: f.title,
                                    asset: f.asset_value || '127.0.0.1',
                                    cve: f.cve || '—',
                                    cvss: f.cvss_score || '—',
                                    status: 'open'
                                }));
                            }
                        } else if (connectorCode === 'nessus') {
                            if (content.includes('<NessusClientData_v2')) {
                                const parser = new DOMParser();
                                const xmlDoc = parser.parseFromString(content, "text/xml");
                                const reportHosts = xmlDoc.getElementsByTagName("ReportHost");
                                for (let i = 0; i < reportHosts.length; i++) {
                                    const ip = reportHosts[i].getAttribute("name") || "127.0.0.1";
                                    const reportItems = reportHosts[i].getElementsByTagName("ReportItem");
                                    for (let j = 0; j < reportItems.length; j++) {
                                        const pluginName = reportItems[j].getAttribute("pluginName") || "Nessus Finding";
                                        const severityVal = parseInt(reportItems[j].getAttribute("severity") || "1");
                                        const severityMap = ['informational', 'low', 'medium', 'high', 'critical'];
                                        const severity = severityMap[severityVal] || 'medium';
                                        const cve = reportItems[j].getElementsByTagName("cve")[0]?.textContent || '—';
                                        const cvss = reportItems[j].getElementsByTagName("cvss_base_score")[0]?.textContent || '—';
                                        list.push({
                                            severity,
                                            title: pluginName,
                                            asset: ip,
                                            cve,
                                            cvss,
                                            status: 'open'
                                        });
                                    }
                                }
                            } else {
                                const parsedJson = JSON.parse(content);
                                list = (parsedJson.findings || []).map(f => ({
                                    severity: f.severity || 'low',
                                    title: f.title,
                                    asset: f.asset_value || '127.0.0.1',
                                    cve: f.cve || '—',
                                    cvss: f.cvss_score || '—',
                                    status: 'open'
                                }));
                            }
                        } else {
                            // general json/xml parse
                            try {
                                const parsedJson = JSON.parse(content);
                                list = (parsedJson.findings || []).map(f => ({
                                    severity: f.severity || 'low',
                                    title: f.title,
                                    asset: f.asset_value || '127.0.0.1',
                                    cve: f.cve || '—',
                                    cvss: f.cvss_score || '—',
                                    status: 'open'
                                }));
                            } catch(e) {
                                // Empty list when preview fails
                                list = [];
                                setError('Preview data format is invalid.');
                            }
                        }
                        setParsedFindings(list);
                    } catch(err) {
                        console.error("Local preview parsing failed", err);
                    }
                };
                reader.readAsText(file);
                
                setStep(2); // Proceed to Validate & Preview step
            } else {
                setError(r.data?.message || "File validation failed.");
            }
        } catch (e) {
            setError(e.response?.data?.message || "Failed to validate file structure.");
        } finally {
            setLoading(false);
        }
    };

    // Step 2 -> Step 3: Trigger Background Import
    const handleStartImport = async () => {
        setLoading(true);
        setError(null);

        const formData = new FormData();
        formData.append('file', file);
        formData.append('scanner', connectorCode || 'nmap');
        if (integrationId) {
            formData.append('integration_id', integrationId);
        }

        try {
            const r = await axios.post('/api/imports/upload', formData, {
                headers: { 'Content-Type': 'multipart/form-data' }
            });
            if (r.data?.success) {
                setJob(r.data.data);
                setStep(3); // Go to importing progress step
                startPolling(r.data.data.uuid);
            } else {
                setError(r.data?.message || "Failed to initiate import job.");
            }
        } catch (e) {
            setError(e.response?.data?.message || "Failed to trigger background import.");
        } finally {
            setLoading(false);
        }
    };

    // Poll active import job status and logs
    const startPolling = (jobUuid) => {
        clearInterval(pollInterval.current);
        pollInterval.current = setInterval(async () => {
            try {
                const r = await axios.get(`/api/imports/${jobUuid}`);
                if (r.data?.success) {
                    const currentJob = r.data.data;
                    setJob(currentJob);
                    setProgress(currentJob.progress || 0);
                    setLogs(currentJob.logs || []);

                    if (currentJob.status === 'completed' || currentJob.status === 'failed') {
                        clearInterval(pollInterval.current);
                        if (currentJob.status === 'completed' && onComplete) {
                            onComplete();
                        }
                    }
                }
            } catch (e) {
                console.error("Polling job failed", e);
            }
        }, 1500);
    };

    const handleDeleteJob = async (jobUuid) => {
        if (!window.confirm("Are you sure you want to delete this import record?")) return;
        try {
            await axios.delete(`/api/imports/${jobUuid}`);
            fetchHistory();
        } catch (e) {
            alert("Failed to delete record.");
        }
    };

    return (
        <div style={{ position: 'fixed', inset: 0, zIndex: 1000, display: 'flex', alignItems: 'center', justifyContent: 'center', background: 'rgba(15, 23, 42, 0.4)', backdropFilter: 'blur(8px)' }}>
            
            {/* Inject Custom Style Rules for enterprise scrollbars and responsive layouts */}
            <style dangerouslySetInnerHTML={{__html: `
                .import-wizard-container {
                    width: 920px;
                    max-height: 90vh;
                    border-radius: 20px;
                    background: white;
                    box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
                    overflow: hidden;
                    display: flex;
                    flex-direction: column;
                    transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
                }
                .import-wizard-body {
                    flex: 1;
                    overflow-y: auto;
                    padding: 32px;
                }
                /* Custom Enterprise Scrollbar */
                .import-wizard-body::-webkit-scrollbar, .preview-table-container::-webkit-scrollbar {
                    width: 8px;
                    height: 8px;
                }
                .import-wizard-body::-webkit-scrollbar-track, .preview-table-container::-webkit-scrollbar-track {
                    background: #F3F4F6;
                    border-radius: 4px;
                }
                .import-wizard-body::-webkit-scrollbar-thumb, .preview-table-container::-webkit-scrollbar-thumb {
                    background: #CBD5E1;
                    border-radius: 4px;
                }
                .import-wizard-body::-webkit-scrollbar-thumb:hover, .preview-table-container::-webkit-scrollbar-thumb:hover {
                    background: #94A3B8;
                }
                /* Responsive classes */
                @media (max-width: 1024px) {
                    .import-wizard-container {
                        width: 820px;
                    }
                }
                @media (max-width: 840px) {
                    .import-wizard-container {
                        width: 95vw;
                        max-height: 92vh;
                    }
                }
                @media (max-width: 640px) {
                    .import-wizard-container {
                        width: 100vw;
                        height: 100vh;
                        max-height: 100vh;
                        border-radius: 0px;
                    }
                    .import-wizard-body {
                        padding: 20px;
                    }
                }
            `}} />

            <div className="import-wizard-container">
                
                {/* 1. Header (Fixed, bottom-bordered) */}
                <div style={{ padding: '20px 32px', borderBottom: '1px solid #e2e8f0', display: 'flex', justifyContent: 'space-between', alignItems: 'center', background: '#f8fafc', position: 'sticky', top: 0, zIndex: 10 }}>
                    <div>
                        <div style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
                            <Sparkles size={20} style={{ color: '#4f46e5' }} />
                            <h2 style={{ fontSize: 18, fontWeight: 700, color: '#0f172a', margin: 0 }}>Import Results Wizard</h2>
                        </div>
                        <p style={{ fontSize: 13, color: '#64748b', margin: '2px 0 0' }}>Normalize, validate and sync scanner outputs to the platform</p>
                    </div>
                    <div style={{ display: 'flex', gap: 8, alignItems: 'center' }}>
                        <button 
                            onClick={() => setShowHistory(!showHistory)} 
                            className="btn btn-secondary" 
                            style={{ display: 'flex', alignItems: 'center', gap: 6, padding: '6px 12px', fontSize: 12, borderRadius: 8, height: 32, border: '1px solid #e2e8f0', background: showHistory ? '#f1f5f9' : 'white', fontWeight: 600 }}
                        >
                            <Terminal size={13} />
                            {showHistory ? "Back to Wizard" : "History & Logs"}
                        </button>
                        <button onClick={onClose} style={{ padding: 6, borderRadius: 8, border: 'none', background: 'transparent', cursor: 'pointer', color: '#64748b', display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
                            <X size={20} />
                        </button>
                    </div>
                </div>

                {showHistory ? (
                    /* History / Log view inside body */
                    <div className="import-wizard-body" style={{ display: 'flex', flexDirection: 'column', gap: 16 }}>
                        <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
                            <h3 style={{ fontSize: 15, fontWeight: 700, margin: 0, color: '#0f172a' }}>Import History & System Logs</h3>
                            <button onClick={fetchHistory} style={{ display: 'flex', alignItems: 'center', gap: 4, background: 'none', border: 'none', color: '#4f46e5', fontWeight: 600, fontSize: 12, cursor: 'pointer' }}>
                                <RefreshCw size={12} /> Refresh logs
                            </button>
                        </div>

                        {loadingHistory ? (
                            <div style={{ display: 'flex', justifyContent: 'center', padding: '40px 0' }}>
                                <Loader2 className="spin" size={24} style={{ color: '#4f46e5' }} />
                            </div>
                        ) : historyList.length === 0 ? (
                            <div style={{ textAlign: 'center', padding: '40px 0', color: '#64748b', border: '1px dashed #e2e8f0', borderRadius: 12 }}>
                                <FileText size={32} style={{ margin: '0 auto 12px', opacity: 0.5 }} />
                                <p style={{ margin: 0, fontSize: 13 }}>No previous import logs found.</p>
                            </div>
                        ) : (
                            <div style={{ display: 'flex', flexDirection: 'column', gap: 8 }}>
                                {historyList.map((hist) => (
                                    <div key={hist.uuid} style={{ padding: 16, borderRadius: 12, border: '1px solid #e2e8f0', background: '#f8fafc', display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
                                        <div>
                                            <div style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
                                                <span style={{ fontSize: 13, fontWeight: 700, color: '#0f172a', textTransform: 'uppercase' }}>{hist.scanner}</span>
                                                <span style={{ fontSize: 10, padding: '2px 6px', borderRadius: 6, background: hist.status === 'completed' ? '#dcfce7' : '#fee2e2', color: hist.status === 'completed' ? '#16a34a' : '#ef4444', fontWeight: 600 }}>
                                                    {hist.status}
                                                </span>
                                            </div>
                                            <div style={{ display: 'flex', gap: 12, marginTop: 6, fontSize: 11, color: '#64748b' }}>
                                                <span>Date: {new Date(hist.created_at).toLocaleDateString()}</span>
                                                <span>Assets: {hist.imported_assets_count}</span>
                                                <span>Findings: {hist.imported_findings_count}</span>
                                            </div>
                                        </div>
                                        <div style={{ display: 'flex', gap: 8 }}>
                                            <button 
                                                onClick={async () => {
                                                    setLoadingHistory(true);
                                                    try {
                                                        const r = await axios.get(`/api/imports/${hist.import_job?.uuid}`);
                                                        setSelectedHistoryLog(r.data?.data?.logs ?? []);
                                                    } catch (e) {
                                                        alert("Failed to load logs.");
                                                    } finally {
                                                        setLoadingHistory(false);
                                                    }
                                                }}
                                                style={{ border: '1px solid #e2e8f0', background: 'white', borderRadius: 8, padding: '4px 8px', fontSize: 11, fontWeight: 600, display: 'flex', alignItems: 'center', gap: 4, cursor: 'pointer' }}
                                            >
                                                <Terminal size={12} /> View Log
                                            </button>
                                            <button 
                                                onClick={() => handleDeleteJob(hist.import_job?.uuid)}
                                                style={{ border: '1px solid #fee2e2', background: 'white', borderRadius: 8, padding: '4px 8px', color: '#ef4444', cursor: 'pointer' }}
                                            >
                                                <Trash2 size={12} />
                                            </button>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        )}

                        {selectedHistoryLog && (
                            <div style={{ marginTop: 16, background: '#0f172a', borderRadius: 12, padding: 16, color: '#38bdf8', fontFamily: 'monospace', fontSize: 12, maxHeight: 200, overflowY: 'auto' }}>
                                <div style={{ display: 'flex', justifyContent: 'space-between', color: '#94a3b8', borderBottom: '1px solid #334155', paddingBottom: 8, marginBottom: 8 }}>
                                    <span>Console Logs</span>
                                    <button onClick={() => setSelectedHistoryLog(null)} style={{ background: 'none', border: 'none', color: '#ef4444', cursor: 'pointer', fontSize: 11 }}>Close</button>
                                </div>
                                {selectedHistoryLog.map((log, idx) => (
                                    <div key={idx} style={{ margin: '4px 0' }}>
                                        <span style={{ color: log.level === 'error' ? '#ef4444' : log.level === 'warning' ? '#f59e0b' : '#10b981' }}>[{log.level.toUpperCase()}]</span> {log.message}
                                    </div>
                                ))}
                            </div>
                        )}
                    </div>
                ) : (
                    /* Step Wizard View */
                    <>
                        {/* 2. Stepper (Sticky below header) */}
                        <div style={{ display: 'flex', padding: '16px 32px', background: '#f1f5f9', gap: 12, borderBottom: '1px solid #e2e8f0', position: 'sticky', top: 0, zIndex: 9 }}>
                            {[
                                { num: 1, label: 'Select Source' },
                                { num: 2, label: 'Validate & Preview' },
                                { num: 3, label: 'Import Progress' }
                            ].map((s) => (
                                <div key={s.num} style={{ display: 'flex', alignItems: 'center', gap: 6, opacity: step === s.num ? 1 : 0.5 }}>
                                    <span style={{ display: 'inline-flex', alignItems: 'center', justifyContent: 'center', width: 22, height: 22, borderRadius: '50%', background: step >= s.num ? '#4f46e5' : '#64748b', color: 'white', fontSize: 11, fontWeight: 700 }}>
                                        {step > s.num ? <CheckCircle2 size={12} /> : s.num}
                                    </span>
                                    <span style={{ fontSize: 12, fontWeight: 600, color: '#334155' }}>{s.label}</span>
                                    {s.num < 3 && <ChevronRight size={14} style={{ color: '#94a3b8' }} />}
                                </div>
                            ))}
                        </div>

                        {/* 3. Scrollable Body */}
                        <div className="import-wizard-body">
                            {error && (
                                <div style={{ marginBottom: 20, padding: 12, background: '#fee2e2', border: '1px solid #fca5a5', borderRadius: 10, color: '#991b1b', display: 'flex', alignItems: 'center', gap: 8, fontSize: 13 }}>
                                    <AlertTriangle size={16} />
                                    <span>{error}</span>
                                </div>
                            )}

                            {step === 1 && (
                                <div style={{ display: 'flex', flexDirection: 'column', gap: 24 }}>
                                    <div>
                                        <label style={{ fontSize: 13, fontWeight: 600, color: '#334155', display: 'block', marginBottom: 10 }}>Choose Import Method</label>
                                        <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 12 }}>
                                            {[
                                                { type: 'file', label: 'Upload File', desc: 'Drag-and-drop XML, JSON, CSV or SARIF reports', icon: Upload },
                                                { type: 'scanner', label: 'Connected Scanner', desc: 'Pull directly from active connection', icon: Server, disabled: !integrationId },
                                                { type: 'api', label: 'API Push Target', desc: 'Stream scanner payload via API endpoint', icon: Globe, disabled: true },
                                                { type: 'url', label: 'Remote Source URL', desc: 'Fetch report from web hook bucket URL', icon: Link, disabled: true }
                                            ].map((opt) => {
                                                const OptIcon = opt.icon;
                                                return (
                                                    <div 
                                                        key={opt.type}
                                                        onClick={() => !opt.disabled && setSourceType(opt.type)}
                                                        style={{ 
                                                            padding: 16, 
                                                            borderRadius: 12, 
                                                            border: sourceType === opt.type ? '2px solid #4f46e5' : '1px solid #e2e8f0', 
                                                            background: opt.disabled ? '#f8fafc' : 'white', 
                                                            cursor: opt.disabled ? 'not-allowed' : 'pointer',
                                                            opacity: opt.disabled ? 0.6 : 1,
                                                            position: 'relative',
                                                            transition: 'all 0.2s ease'
                                                        }}
                                                    >
                                                        <div style={{ display: 'flex', alignItems: 'center', gap: 10 }}>
                                                            <OptIcon size={18} style={{ color: sourceType === opt.type ? '#4f46e5' : '#64748b' }} />
                                                            <span style={{ fontSize: 14, fontWeight: 700, color: '#0f172a' }}>{opt.label}</span>
                                                        </div>
                                                        <p style={{ fontSize: 11, color: '#64748b', margin: '4px 0 0' }}>{opt.desc}</p>
                                                        {opt.disabled && (
                                                            <span style={{ position: 'absolute', top: 8, right: 8, fontSize: 8, background: '#e2e8f0', color: '#64748b', padding: '2px 4px', borderRadius: 4, fontWeight: 600 }}>
                                                                Coming Soon
                                                            </span>
                                                        )}
                                                    </div>
                                                );
                                            })}
                                        </div>
                                    </div>

                                    {sourceType === 'file' && (
                                        <div>
                                            <label style={{ fontSize: 13, fontWeight: 600, color: '#334155', display: 'block', marginBottom: 8 }}>Supported File Types</label>
                                            <div style={{ display: 'flex', flexWrap: 'wrap', gap: 8, marginBottom: 12 }}>
                                                {['Nmap XML', 'Nessus XML', 'Burp XML', 'OpenVAS/Greenbone XML', 'Qualys XML', 'Rapid7 JSON', 'SARIF Schema'].map((typeLabel, idx) => (
                                                    <span key={idx} style={{ fontSize: 11, color: '#475569', background: '#f1f5f9', padding: '3px 8px', borderRadius: 6, fontWeight: 500 }}>
                                                        {typeLabel}
                                                    </span>
                                                ))}
                                            </div>

                                            {/* Drag & Drop Upload Zone */}
                                            <div 
                                                onDragOver={handleDragOver}
                                                onDragLeave={handleDragLeave}
                                                onDrop={handleDrop}
                                                onClick={() => document.getElementById('file-upload-input').click()}
                                                style={{ 
                                                    minHeight: '260px',
                                                    border: isDragOver ? '2px dashed #4f46e5' : '2px dashed #cbd5e1', 
                                                    borderRadius: 12, 
                                                    padding: '32px 16px', 
                                                    textAlign: 'center', 
                                                    background: isDragOver ? '#eef2ff' : '#f8fafc', 
                                                    cursor: 'pointer',
                                                    display: 'flex',
                                                    flexDirection: 'column',
                                                    alignItems: 'center',
                                                    justifyContent: 'center',
                                                    gap: 12,
                                                    transition: 'all 0.2s ease-in-out'
                                                }}
                                            >
                                                <input id="file-upload-input" type="file" onChange={handleFileChange} style={{ display: 'none' }} />
                                                <Upload size={40} style={{ color: isDragOver ? '#4f46e5' : '#94a3b8' }} />
                                                <div>
                                                    <p style={{ fontSize: 15, fontWeight: 700, color: '#0f172a', margin: 0 }}>
                                                        {file ? file.name : "Click to upload or drag & drop"}
                                                    </p>
                                                    <p style={{ fontSize: 12, color: '#64748b', margin: '4px 0 0' }}>Supports XML, JSON, CSV or SARIF up to 10MB</p>
                                                </div>
                                                {file && (
                                                    <span style={{ fontSize: 11, background: '#e0f2fe', color: '#0369a1', padding: '2px 8px', borderRadius: 10, fontWeight: 600 }}>
                                                        Size: {Math.round(file.size / 1024)} KB
                                                    </span>
                                                )}
                                            </div>
                                        </div>
                                    )}

                                    {sourceType === 'scanner' && (
                                        <div style={{ padding: 16, border: '1px solid #dcfce7', background: '#f0fdf4', borderRadius: 12, display: 'flex', alignItems: 'center', gap: 12 }}>
                                            <Server size={24} style={{ color: '#16a34a' }} />
                                            <div>
                                                <p style={{ fontSize: 13, fontWeight: 700, color: '#16a34a', margin: 0 }}>Scanner connection detected</p>
                                                <p style={{ fontSize: 12, color: '#14532d', margin: '2px 0 0' }}>Will pull latest scanner reports automatically in the background.</p>
                                            </div>
                                        </div>
                                    )}
                                </div>
                            )}

                            {step === 2 && previewData && (
                                <div style={{ display: 'flex', flexDirection: 'column', gap: 24 }}>
                                    
                                    {/* 9. Validation Screen Banner */}
                                    <div style={{ padding: 16, background: '#f0fdf4', border: '1px solid #bbf7d0', borderRadius: 12, display: 'flex', alignItems: 'center', gap: 10 }}>
                                        <CheckCircle2 size={20} style={{ color: '#16a34a' }} />
                                        <div>
                                            <span style={{ fontSize: 14, fontWeight: 700, color: '#14532d' }}>Parsed Successfully</span>
                                            <p style={{ fontSize: 12, color: '#14532d', margin: 0 }}>Structure contains valid scanner metadata signatures.</p>
                                        </div>
                                    </div>

                                    {/* Stats grid */}
                                    <div style={{ display: 'grid', gridTemplateColumns: 'repeat(4, 1fr)', gap: 12 }}>
                                        <div style={{ padding: 12, border: '1px solid #e2e8f0', borderRadius: 10, background: '#f8fafc' }}>
                                            <span style={{ fontSize: 11, color: '#64748b', display: 'block' }}>Scanner Engine</span>
                                            <span style={{ fontSize: 15, fontWeight: 700, color: '#0f172a', textTransform: 'uppercase' }}>{connectorCode}</span>
                                        </div>
                                        <div style={{ padding: 12, border: '1px solid #e2e8f0', borderRadius: 10, background: '#f8fafc' }}>
                                            <span style={{ fontSize: 11, color: '#64748b', display: 'block' }}>Extracted Assets</span>
                                            <span style={{ fontSize: 16, fontWeight: 700, color: '#0f172a' }}>{previewData.assets_count}</span>
                                        </div>
                                        <div style={{ padding: 12, border: '1px solid #e2e8f0', borderRadius: 10, background: '#f8fafc' }}>
                                            <span style={{ fontSize: 11, color: '#64748b', display: 'block' }}>Extracted Findings</span>
                                            <span style={{ fontSize: 16, fontWeight: 700, color: '#0f172a' }}>{previewData.findings_count}</span>
                                        </div>
                                        <div style={{ padding: 12, border: '1px solid #e2e8f0', borderRadius: 10, background: '#f8fafc' }}>
                                            <span style={{ fontSize: 11, color: '#64748b', display: 'block' }}>Estimated Duplicates</span>
                                            <span style={{ fontSize: 16, fontWeight: 700, color: '#ef4444' }}>{previewData.duplicates}</span>
                                        </div>
                                    </div>

                                    {/* Severity breakdown */}
                                    <div>
                                        <label style={{ fontSize: 13, fontWeight: 600, color: '#334155', display: 'block', marginBottom: 8 }}>Vulnerability Breakdown</label>
                                        <div style={{ display: 'flex', gap: 8 }}>
                                            {[
                                                { label: 'Critical', count: previewData.severities.critical, bg: '#fee2e2', text: '#991b1b' },
                                                { label: 'High', count: previewData.severities.high, bg: '#ffedd5', text: '#9a3412' },
                                                { label: 'Medium', count: previewData.severities.medium, bg: '#fef9c3', text: '#854d0e' },
                                                { label: 'Low', count: previewData.severities.low, bg: '#e0f2fe', text: '#0369a1' }
                                            ].map((sev, idx) => (
                                                <div key={idx} style={{ flex: 1, padding: 8, background: sev.bg, color: sev.text, borderRadius: 8, textAlign: 'center' }}>
                                                    <span style={{ fontSize: 11, fontWeight: 600, display: 'block' }}>{sev.label}</span>
                                                    <span style={{ fontSize: 16, fontWeight: 700 }}>{sev.count}</span>
                                                </div>
                                            ))}
                                        </div>
                                    </div>

                                    {/* 10. Enterprise Preview Table (with sticky header and overflow container) */}
                                    <div>
                                        <label style={{ fontSize: 13, fontWeight: 600, color: '#334155', display: 'block', marginBottom: 8 }}>Parsed Findings Preview</label>
                                        <div className="preview-table-container" style={{ maxHeight: 220, overflowY: 'auto', border: '1px solid #e2e8f0', borderRadius: 10 }}>
                                            <table style={{ width: '100%', borderCollapse: 'collapse', fontSize: 12, textAlign: 'left' }}>
                                                <thead style={{ position: 'sticky', top: 0, background: '#f8fafc', borderBottom: '1px solid #e2e8f0', zIndex: 1 }}>
                                                    <tr>
                                                        <th style={{ padding: '10px 12px', fontWeight: 600, color: '#475569' }}>Severity</th>
                                                        <th style={{ padding: '10px 12px', fontWeight: 600, color: '#475569' }}>Title</th>
                                                        <th style={{ padding: '10px 12px', fontWeight: 600, color: '#475569' }}>Asset</th>
                                                        <th style={{ padding: '10px 12px', fontWeight: 600, color: '#475569' }}>CVE</th>
                                                        <th style={{ padding: '10px 12px', fontWeight: 600, color: '#475569' }}>CVSS</th>
                                                        <th style={{ padding: '10px 12px', fontWeight: 600, color: '#475569' }}>Status</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    {parsedFindings.length === 0 ? (
                                                        <tr>
                                                            <td colSpan={6} style={{ padding: '24px', textAlign: 'center', color: '#64748b' }}>No preview findings available.</td>
                                                        </tr>
                                                    ) : (
                                                        parsedFindings.map((f, idx) => (
                                                            <tr key={idx} style={{ borderBottom: '1px solid #f1f5f9', background: idx % 2 === 0 ? 'white' : '#f8fafc' }}>
                                                                <td style={{ padding: '10px 12px' }}>
                                                                    <span style={{ 
                                                                        fontSize: 10, 
                                                                        fontWeight: 600, 
                                                                        padding: '2px 6px', 
                                                                        borderRadius: 4, 
                                                                        background: f.severity === 'critical' ? '#fee2e2' : f.severity === 'high' ? '#ffedd5' : f.severity === 'medium' ? '#fef9c3' : '#e0f2fe',
                                                                        color: f.severity === 'critical' ? '#ef4444' : f.severity === 'high' ? '#f97316' : f.severity === 'medium' ? '#ca8a04' : '#0284c7',
                                                                        textTransform: 'uppercase'
                                                                    }}>
                                                                        {f.severity}
                                                                    </span>
                                                                </td>
                                                                <td style={{ padding: '10px 12px', fontWeight: 600, color: '#0f172a', maxWidth: 220, overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }} title={f.title}>{f.title}</td>
                                                                <td style={{ padding: '10px 12px', color: '#475569' }}>{f.asset}</td>
                                                                <td style={{ padding: '10px 12px', color: '#64748b', fontFamily: 'monospace' }}>{f.cve}</td>
                                                                <td style={{ padding: '10px 12px', fontWeight: 600 }}>{f.cvss}</td>
                                                                <td style={{ padding: '10px 12px' }}>
                                                                    <span style={{ fontSize: 10, color: '#16a34a', background: '#dcfce7', padding: '2px 6px', borderRadius: 4, fontWeight: 600 }}>
                                                                        {f.status}
                                                                    </span>
                                                                </td>
                                                            </tr>
                                                        ))
                                                    )}
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>

                                    {/* Impacts */}
                                    <div style={{ padding: 16, border: '1px solid #e2e8f0', borderRadius: 12, background: '#f8fafc', display: 'flex', flexDirection: 'column', gap: 8 }}>
                                        <div style={{ display: 'flex', justifyContent: 'space-between', fontSize: 12 }}>
                                            <span style={{ color: '#64748b' }}>Compliance Impact Estimate:</span>
                                            <span style={{ fontWeight: 700, color: '#ef4444' }}>{previewData.compliance_impact}</span>
                                        </div>
                                        <div style={{ display: 'flex', justifyContent: 'space-between', fontSize: 12 }}>
                                            <span style={{ color: '#64748b' }}>Overall Risk Factor Increase:</span>
                                            <span style={{ fontWeight: 700, color: '#eab308' }}>{previewData.risk_impact}</span>
                                        </div>
                                    </div>
                                </div>
                            )}

                            {step === 3 && job && (
                                <div style={{ display: 'flex', flexDirection: 'column', gap: 24 }}>
                                    <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
                                        <div>
                                            <span style={{ fontSize: 14, fontWeight: 700, color: '#0f172a' }}>
                                                {job.status === 'completed' ? 'Import Complete!' : job.status === 'failed' ? 'Import Failed' : 'Queue Job Running'}
                                            </span>
                                            <p style={{ fontSize: 12, color: '#64748b', margin: '2px 0 0' }}>Job progress updates live from backend workers.</p>
                                        </div>
                                        {job.status === 'completed' && <CheckCircle2 size={32} style={{ color: '#16a34a' }} />}
                                        {job.status === 'failed' && <AlertTriangle size={32} style={{ color: '#ef4444' }} />}
                                        {job.status !== 'completed' && job.status !== 'failed' && <Loader2 size={32} className="spin" style={{ color: '#4f46e5' }} />}
                                    </div>

                                    {/* 11. Import Progress Bar */}
                                    <div>
                                        <div style={{ display: 'flex', justifyContent: 'space-between', fontSize: 12, fontWeight: 600, color: '#475569', marginBottom: 6 }}>
                                            <span>Import Pipeline Progress</span>
                                            <span>{progress}%</span>
                                        </div>
                                        <div style={{ width: '100%', height: 8, background: '#e2e8f0', borderRadius: 4, overflow: 'hidden' }}>
                                            <div style={{ width: `${progress}%`, height: '100%', background: '#4f46e5', transition: 'width 0.4s ease' }} />
                                        </div>
                                    </div>

                                    {/* Step status details */}
                                    <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 12 }}>
                                        {[
                                            { label: 'Reading File', done: progress >= 10 },
                                            { label: 'Parsing XML / JSON', done: progress >= 25 },
                                            { label: 'Normalizing Fields', done: progress >= 45 },
                                            { label: 'Creating Assets', done: progress >= 60 },
                                            { label: 'Creating Findings', done: progress >= 75 },
                                            { label: 'Deduplicating Records', done: progress >= 85 },
                                            { label: 'Linking CVE Compliance', done: progress >= 90 },
                                            { label: 'Completed Pipeline', done: progress >= 100 }
                                        ].map((pStep, idx) => (
                                            <div key={idx} style={{ display: 'flex', alignItems: 'center', gap: 8, fontSize: 12 }}>
                                                <span style={{ 
                                                    width: 14, 
                                                    height: 14, 
                                                    borderRadius: '50%', 
                                                    background: pStep.done ? '#22c55e' : '#cbd5e1', 
                                                    display: 'inline-flex',
                                                    alignItems: 'center',
                                                    justifyContent: 'center',
                                                    color: 'white',
                                                    fontSize: 8
                                                }}>
                                                    ✓
                                                </span>
                                                <span style={{ color: pStep.done ? '#0f172a' : '#64748b', fontWeight: pStep.done ? 600 : 400 }}>{pStep.label}</span>
                                            </div>
                                        ))}
                                    </div>

                                    {/* Live console logs */}
                                    <div>
                                        <label style={{ fontSize: 13, fontWeight: 600, color: '#334155', display: 'block', marginBottom: 8 }}>Live Execution Logs</label>
                                        <div style={{ background: '#0f172a', borderRadius: 12, padding: 16, color: '#38bdf8', fontFamily: 'monospace', fontSize: 12, minHeight: 180, maxHeight: 220, overflowY: 'auto' }}>
                                            {logs.length === 0 ? (
                                                <div style={{ color: '#64748b' }}>Waiting for logs...</div>
                                            ) : (
                                                logs.map((log, idx) => (
                                                    <div key={idx} style={{ margin: '4px 0' }}>
                                                        <span style={{ color: log.level === 'error' ? '#ef4444' : log.level === 'warning' ? '#f59e0b' : '#10b981' }}>[{log.level.toUpperCase()}]</span> {log.message}
                                                    </div>
                                                ))
                                            )}
                                        </div>
                                    </div>
                                </div>
                            )}

                        </div>

                        {/* 4. Footer (Sticky, top-bordered) */}
                        <div style={{ padding: '20px 32px', borderTop: '1px solid #e2e8f0', display: 'flex', justifyContent: 'flex-end', gap: 12, background: '#f8fafc', position: 'sticky', bottom: 0, zIndex: 10 }}>
                            {step === 1 && (
                                <>
                                    <button className="btn btn-secondary" onClick={onClose} style={{ padding: '8px 16px', borderRadius: 10, border: '1px solid #e2e8f0', background: 'white', fontWeight: 600, fontSize: 13 }}>
                                        Cancel
                                    </button>
                                    {sourceType === 'file' ? (
                                        <button className="btn btn-primary" onClick={handleValidate} disabled={loading} style={{ padding: '8px 16px', borderRadius: 10, background: '#4f46e5', color: 'white', border: 'none', fontWeight: 600, fontSize: 13, display: 'flex', alignItems: 'center', gap: 8 }}>
                                            {loading ? <Loader2 size={16} className="spin" /> : <Play size={16} />} Continue
                                        </button>
                                    ) : (
                                        <button 
                                            className="btn btn-primary" 
                                            onClick={async () => {
                                                setLoading(true);
                                                try {
                                                    const r = await axios.post(`/api/integrations/${integrationId}/import`);
                                                    if (r.data?.success) {
                                                        setJob(r.data.data);
                                                        setStep(3);
                                                        startPolling(r.data.data.uuid);
                                                    }
                                                } catch (e) {
                                                    setError("Failed to trigger automated scanner import.");
                                                } finally {
                                                    setLoading(false);
                                                }
                                            }}
                                            disabled={loading} 
                                            style={{ padding: '8px 16px', borderRadius: 10, background: '#4f46e5', color: 'white', border: 'none', fontWeight: 600, fontSize: 13, display: 'flex', alignItems: 'center', gap: 8 }}
                                        >
                                            {loading ? <Loader2 size={16} className="spin" /> : <Play size={16} />} Sync Connection
                                        </button>
                                    )}
                                </>
                            )}

                            {step === 2 && (
                                <>
                                    <button className="btn btn-secondary" onClick={() => setStep(1)} style={{ padding: '8px 16px', borderRadius: 10, border: '1px solid #e2e8f0', background: 'white', fontWeight: 600, fontSize: 13 }}>
                                        Previous
                                    </button>
                                    <button className="btn btn-primary" onClick={handleStartImport} disabled={loading} style={{ padding: '8px 16px', borderRadius: 10, background: '#16a34a', color: 'white', border: 'none', fontWeight: 600, fontSize: 13, display: 'flex', alignItems: 'center', gap: 8 }}>
                                        {loading ? <Loader2 size={16} className="spin" /> : <CheckCircle2 size={16} />} Import Results
                                    </button>
                                </>
                            )}

                            {step === 3 && (
                                <button 
                                    className="btn btn-primary" 
                                    onClick={onClose} 
                                    disabled={job && job.status !== 'completed' && job.status !== 'failed'} 
                                    style={{ padding: '8px 16px', borderRadius: 10, background: '#4f46e5', color: 'white', border: 'none', fontWeight: 600, fontSize: 13 }}
                                >
                                    Finish
                                </button>
                            )}
                        </div>
                    </>
                )}

            </div>
        </div>
    );
}
