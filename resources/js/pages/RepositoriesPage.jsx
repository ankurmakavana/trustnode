import React, { useState, useEffect } from 'react';
import { 
    GitBranch, Plus, Trash2, Play, CheckCircle2, AlertTriangle, Loader2, Lock, Unlock, ExternalLink, RefreshCw, Eye
} from 'lucide-react';
import axios from 'axios';

export default function RepositoriesPage() {
    const [repositories, setRepositories] = useState([]);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState(null);

    // Modal Form State
    const [showModal, setShowModal] = useState(false);
    const [repoUrl, setRepoUrl] = useState('');
    const [visibility, setVisibility] = useState('public');
    const [token, setToken] = useState('');
    
    const [validating, setValidating] = useState(false);
    const [validationStatus, setValidationStatus] = useState(null); // 'valid' | 'invalid'
    const [submitting, setSubmitting] = useState(false);

    // Active Scans Status Tracking (for UI updates)
    const [scans, setScans] = useState({});

    const fetchRepositories = async (silent = false) => {
        if (!silent) setLoading(true);
        if (!silent) setError(null);
        try {
            const response = await axios.get('/api/repositories');
            const data = response.data;
            setRepositories(data);

            setScans(prev => {
                const newScans = { ...prev };
                data.forEach(repo => {
                    const scan = repo.latest_scan || repo.latestScan;
                    if (scan) {
                        newScans[repo.id] = {
                            status: scan.status,
                            progress: scan.progress,
                            scanId: scan.id,
                            started_at: scan.started_at,
                            completed_at: scan.completed_at,
                            findings_count: scan.findings_count
                        };
                    } else {
                        delete newScans[repo.id];
                    }
                });
                return newScans;
            });
        } catch (err) {
            if (!silent) setError('Failed to load connected repositories.');
        } finally {
            if (!silent) setLoading(false);
        }
    };

    useEffect(() => {
        fetchRepositories();
    }, []);

    useEffect(() => {
        let isPolling = false;
        const pollInterval = setInterval(async () => {
            const activeRepos = repositories.filter(repo => {
                const s = scans[repo.id];
                return s && (s.status === 'queued' || s.status === 'running');
            });
            if (activeRepos.length === 0) return;
            if (isPolling) return;
            isPolling = true;
            try {
                await fetchRepositories(true);
            } finally {
                isPolling = false;
            }
        }, 3000);
        return () => clearInterval(pollInterval);
    }, [repositories, scans]);

    const handleValidateAccess = async () => {
        if (!repoUrl) return;
        setValidating(true);
        setValidationStatus(null);
        try {
            const response = await axios.post('/api/repositories/validate-access', {
                repository_url: repoUrl,
                token: visibility === 'private' ? token : null
            });
            setValidationStatus(response.data.valid ? 'valid' : 'invalid');
        } catch (err) {
            setValidationStatus('invalid');
        } finally {
            setValidating(false);
        }
    };

    const handleConnect = async (e) => {
        e.preventDefault();
        setSubmitting(true);
        try {
            await axios.post('/api/repositories', {
                repository_url: repoUrl,
                visibility,
                token: visibility === 'private' ? token : null
            });
            setShowModal(false);
            // Reset form
            setRepoUrl('');
            setVisibility('public');
            setToken('');
            setValidationStatus(null);
            fetchRepositories();
        } catch (err) {
            alert(err.response?.data?.message || 'Failed to connect repository.');
        } finally {
            setSubmitting(false);
        }
    };

    const handleDisconnect = async (id) => {
        if (!confirm('Are you sure you want to disconnect this repository?')) return;
        try {
            await axios.delete(`/api/repositories/${id}`);
            fetchRepositories();
        } catch (err) {
            alert('Failed to disconnect repository.');
        }
    };

    const handleScanNow = async (repo) => {
        setScans(prev => ({ ...prev, [repo.id]: { status: 'queued', progress: 0 } }));
        try {
            const response = await axios.post(`/api/repositories/${repo.id}/scan`);
            const scan = response.data;
            setScans(prev => ({
                ...prev,
                [repo.id]: { 
                    status: scan.status, 
                    progress: scan.progress,
                    scanId: scan.id
                }
            }));
            
            // We no longer need local polling interval here because the central effect handles it

        } catch (err) {
            alert(err.response?.data?.message || 'Failed to trigger scan.');
            setScans(prev => {
                const copy = { ...prev };
                delete copy[repo.id];
                return copy;
            });
        }
    };

    return (
        <div className="space-y-6">
            <div className="flex items-center justify-between">
                <div>
                    <h1 className="text-xl font-bold text-slate-900 tracking-tight flex items-center gap-2">
                        <GitBranch className="text-brand-500" size={22} />
                        Connected Repositories
                    </h1>
                    <p className="text-xs text-slate-500 mt-0.5">Manage and run security scans on public and private repositories.</p>
                </div>

                <button
                    onClick={() => setShowModal(true)}
                    className="inline-flex items-center gap-1.5 px-3.5 py-2 text-xs font-semibold text-white bg-brand-600 hover:bg-brand-700 rounded-lg shadow-sm transition"
                >
                    <Plus size={14} />
                    Connect Repository
                </button>
            </div>

            {loading ? (
                <div className="bg-white border border-slate-200 rounded-xl p-12 text-center flex items-center justify-center min-h-[40vh]">
                    <Loader2 className="animate-spin text-brand-600 mr-2" size={24} />
                    <span className="text-sm font-semibold text-slate-500">Loading repositories...</span>
                </div>
            ) : error ? (
                <div className="bg-white border border-slate-200 rounded-xl p-12 text-center text-rose-600 min-h-[40vh] flex flex-col justify-center items-center gap-3">
                    <AlertTriangle size={32} />
                    <span className="text-sm font-semibold">{error}</span>
                </div>
            ) : repositories.length === 0 ? (
                <div className="bg-white border border-slate-200 rounded-xl p-16 text-center shadow-sm flex flex-col items-center justify-center min-h-[40vh]">
                    <div className="w-14 h-14 rounded-2xl bg-slate-100 border border-slate-200 flex items-center justify-center mb-4">
                        <GitBranch size={26} className="text-slate-400" />
                    </div>
                    <h3 className="text-sm font-bold text-slate-700 mb-1">No Connected Repositories</h3>
                    <p className="text-xs text-slate-400 max-w-sm leading-relaxed mb-4">
                        Connect a public or private GitHub repository to perform automated vulnerability scanning, detect secrets, SQL injections, and command injection risks.
                    </p>
                    <button
                        onClick={() => setShowModal(true)}
                        className="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold text-slate-700 bg-white border border-slate-300 rounded-lg hover:bg-slate-50 shadow-sm transition"
                    >
                        <Plus size={14} />
                        Get Started
                    </button>
                </div>
            ) : (
                <div className="bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden">
                    <table className="w-full text-left border-collapse">
                        <thead>
                            <tr className="bg-slate-50 border-b border-slate-100 text-[10px] font-bold text-slate-500 uppercase tracking-wider">
                                <th className="px-5 py-3">Repository</th>
                                <th className="px-5 py-3">Visibility</th>
                                <th className="px-5 py-3">Default Branch</th>
                                <th className="px-5 py-3">Scan Status</th>
                                <th className="px-5 py-3 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-100">
                            {repositories.map(repo => {
                                const activeScan = scans[repo.id];
                                return (
                                    <tr key={repo.id} className="hover:bg-slate-50/50 transition">
                                        <td className="px-5 py-4">
                                            <div className="flex items-center gap-2">
                                                <span className="font-semibold text-slate-800 text-sm">{repo.name}</span>
                                                <a href={repo.url} target="_blank" rel="noreferrer" className="text-slate-400 hover:text-slate-600 transition">
                                                    <ExternalLink size={12} />
                                                </a>
                                            </div>
                                        </td>
                                        <td className="px-5 py-4">
                                            <div className="flex items-center gap-1.5 text-xs text-slate-600">
                                                {repo.visibility === 'private' ? (
                                                    <>
                                                        <Lock size={12} className="text-amber-500" />
                                                        <span className="capitalize font-medium">Private</span>
                                                    </>
                                                ) : (
                                                    <>
                                                        <Unlock size={12} className="text-emerald-500" />
                                                        <span className="capitalize font-medium">Public</span>
                                                    </>
                                                )}
                                            </div>
                                        </td>
                                        <td className="px-5 py-4">
                                            <span className="font-mono text-xs text-slate-500">{repo.default_branch}</span>
                                        </td>
                                        <td className="px-5 py-4">
                                            {activeScan ? (
                                                <div className="flex flex-col gap-0.5">
                                                    {activeScan.status === 'completed' ? (
                                                        <>
                                                            <span className="inline-flex items-center gap-1 text-xs font-semibold text-emerald-600">
                                                                <CheckCircle2 size={13} /> Completed
                                                            </span>
                                                            {activeScan.completed_at && (
                                                                <span className="text-[10px] text-slate-400">
                                                                    Last scan: {new Date(activeScan.completed_at).toLocaleString()}
                                                                </span>
                                                            )}
                                                            {activeScan.findings_count !== undefined && activeScan.findings_count > 0 && (
                                                                <span className="text-[10px] text-brand-600 font-medium">
                                                                    Findings: {activeScan.findings_count}
                                                                </span>
                                                            )}
                                                        </>
                                                    ) : activeScan.status === 'failed' ? (
                                                        <>
                                                            <span className="inline-flex items-center gap-1 text-xs font-semibold text-rose-600">
                                                                <AlertTriangle size={13} /> Failed
                                                            </span>
                                                            {activeScan.completed_at && (
                                                                <span className="text-[10px] text-slate-400">
                                                                    Failed on: {new Date(activeScan.completed_at).toLocaleString()}
                                                                </span>
                                                            )}
                                                        </>
                                                    ) : (
                                                        <>
                                                            <span className="inline-flex items-center gap-1.5 text-xs text-brand-600 font-medium">
                                                                <Loader2 size={13} className="animate-spin" />
                                                                {activeScan.status === 'queued' ? 'Queued' : 'Running'}
                                                            </span>
                                                            {activeScan.started_at && (
                                                                <span className="text-[10px] text-slate-400">
                                                                    Started: {new Date(activeScan.started_at).toLocaleTimeString()}
                                                                </span>
                                                            )}
                                                        </>
                                                    )}
                                                </div>
                                            ) : (
                                                <span className="text-xs text-slate-400 font-medium">Never scanned</span>
                                            )}
                                        </td>
                                        <td className="px-5 py-4 text-right">
                                            <div className="flex items-center justify-end gap-2">
                                                {activeScan?.status === 'completed' && (
                                                    <a
                                                        href={`/scans/${activeScan.scanId}/report`}
                                                        className="inline-flex items-center gap-1 px-2.5 py-1.5 text-xs font-semibold text-slate-700 bg-white border border-slate-250 hover:bg-slate-50 rounded-lg transition"
                                                    >
                                                        <Eye size={12} />
                                                        View Report
                                                    </a>
                                                )}
                                                <button
                                                    onClick={() => handleScanNow(repo)}
                                                    disabled={activeScan && activeScan.status !== 'completed' && activeScan.status !== 'failed'}
                                                    className="inline-flex items-center gap-1 px-2.5 py-1.5 text-xs font-semibold text-emerald-700 bg-emerald-50 hover:bg-emerald-100 disabled:opacity-50 disabled:cursor-not-allowed rounded-lg transition"
                                                >
                                                    <Play size={12} />
                                                    Scan Now
                                                </button>
                                                <button
                                                    onClick={() => handleDisconnect(repo.id)}
                                                    className="inline-flex items-center gap-1 px-2.5 py-1.5 text-xs font-semibold text-rose-600 hover:text-rose-700 hover:bg-rose-50 rounded-lg transition"
                                                >
                                                    <Trash2 size={12} />
                                                    Disconnect
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                );
                            })}
                        </tbody>
                    </table>
                </div>
            )}

            {/* Connect Modal */}
            {showModal && (
                <div className="fixed inset-0 bg-slate-900/40 backdrop-blur-sm z-50 flex items-center justify-center p-4">
                    <div className="bg-white border border-slate-200 rounded-xl shadow-xl max-w-md w-full overflow-hidden animate-in fade-in zoom-in duration-200">
                        <div className="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
                            <h3 className="text-sm font-bold text-slate-950">Connect GitHub Repository</h3>
                            <button
                                onClick={() => setShowModal(false)}
                                className="text-slate-400 hover:text-slate-600 transition"
                            >
                                ✕
                            </button>
                        </div>
                        <form onSubmit={handleConnect} className="p-6 space-y-4">
                            <div className="space-y-1">
                                <label className="text-xs font-semibold text-slate-700">Repository URL</label>
                                <input
                                    type="url"
                                    required
                                    placeholder="https://github.com/org/repo"
                                    value={repoUrl}
                                    onChange={(e) => {
                                        setRepoUrl(e.target.value);
                                        setValidationStatus(null);
                                    }}
                                    className="w-full text-xs border border-slate-300 rounded-lg px-3 py-2 focus:ring-1 focus:ring-brand-500 focus:outline-none"
                                />
                            </div>

                            <div className="space-y-1">
                                <label className="text-xs font-semibold text-slate-700">Visibility</label>
                                <div className="grid grid-cols-2 gap-2">
                                    <button
                                        type="button"
                                        onClick={() => {
                                            setVisibility('public');
                                            setValidationStatus(null);
                                        }}
                                        className={`px-3 py-2 text-xs font-semibold border rounded-lg transition ${
                                            visibility === 'public'
                                                ? 'bg-brand-50 border-brand-500 text-brand-700'
                                                : 'bg-white border-slate-300 text-slate-600 hover:bg-slate-50'
                                        }`}
                                    >
                                        Public
                                    </button>
                                    <button
                                        type="button"
                                        onClick={() => {
                                            setVisibility('private');
                                            setValidationStatus(null);
                                        }}
                                        className={`px-3 py-2 text-xs font-semibold border rounded-lg transition ${
                                            visibility === 'private'
                                                ? 'bg-brand-50 border-brand-500 text-brand-700'
                                                : 'bg-white border-slate-300 text-slate-600 hover:bg-slate-50'
                                        }`}
                                    >
                                        Private
                                    </button>
                                </div>
                            </div>

                            {visibility === 'private' && (
                                <div className="space-y-1 animate-in slide-in-from-top-2 duration-200">
                                    <label className="text-xs font-semibold text-slate-700">GitHub Personal Access Token (PAT)</label>
                                    <input
                                        type="password"
                                        required
                                        placeholder="ghp_xxxxxxxxxxxxxxxxxxxx"
                                        value={token}
                                        onChange={(e) => {
                                            setToken(e.target.value);
                                            setValidationStatus(null);
                                        }}
                                        className="w-full text-xs border border-slate-300 rounded-lg px-3 py-2 focus:ring-1 focus:ring-brand-500 focus:outline-none"
                                    />
                                    <p className="text-[10px] text-slate-400">Tokens are stored securely using AES-256 encryption.</p>
                                </div>
                            )}

                            {/* Validation Row */}
                            <div className="flex items-center justify-between pt-2">
                                <button
                                    type="button"
                                    onClick={handleValidateAccess}
                                    disabled={validating || !repoUrl}
                                    className="inline-flex items-center gap-1 px-3 py-1.5 bg-slate-100 hover:bg-slate-200 disabled:opacity-50 text-slate-700 text-xs font-semibold rounded-lg transition"
                                >
                                    {validating && <RefreshCw size={12} className="animate-spin" />}
                                    Validate Access
                                </button>

                                {validationStatus === 'valid' && (
                                    <span className="text-xs font-semibold text-emerald-600 flex items-center gap-1">
                                        <CheckCircle2 size={14} /> Valid Access
                                    </span>
                                )}
                                {validationStatus === 'invalid' && (
                                    <span className="text-xs font-semibold text-rose-600 flex items-center gap-1">
                                        <AlertTriangle size={14} /> Invalid Access
                                    </span>
                                )}
                            </div>

                            <div className="flex items-center justify-end gap-2 pt-4 border-t border-slate-100">
                                <button
                                    type="button"
                                    onClick={() => setShowModal(false)}
                                    className="px-4 py-2 border border-slate-300 hover:bg-slate-50 text-slate-700 text-xs font-semibold rounded-lg transition"
                                >
                                    Cancel
                                </button>
                                <button
                                    type="submit"
                                    disabled={submitting}
                                    className="px-4 py-2 bg-brand-600 hover:bg-brand-700 disabled:opacity-50 text-white text-xs font-semibold rounded-lg transition shadow"
                                >
                                    {submitting ? 'Connecting...' : 'Connect'}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}
        </div>
    );
}
