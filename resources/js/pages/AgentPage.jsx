import React, { useState, useEffect } from 'react';
import axios from 'axios';
import { Loader2, Shield, CheckCircle2, X, LayoutDashboard, ScanLine, FileText, Calendar, Search } from 'lucide-react';
import { Card, CardHeader, ViewAllLink, MonoChip } from '../components/ui/primitives';
import { SeverityBadge } from '../components/ui/primitives_findings';
import { Link } from 'react-router-dom';

export default function AgentPage() {
    const [health, setHealth] = useState(null);
    const [findings, setFindings] = useState([]);
    const [loading, setLoading] = useState(true);
    const [loadingFindings, setLoadingFindings] = useState(false);

    useEffect(() => {
        const fetchHealth = async () => {
            try {
                const response = await axios.get('/api/agent/health');
                setHealth(response.data);
                if (response.data?.agent_id) {
                    fetchFindings(response.data.agent_id);
                }
            } catch (err) {
                console.error('Failed to load agent health', err);
                setHealth(null);
            } finally {
                setLoading(false);
            }
        };

        const fetchFindings = async (agentId) => {
            setLoadingFindings(true);
            try {
                const response = await axios.get('/api/findings', {
                    params: { agent_id: agentId, per_page: 5 }
                });
                setFindings(response.data.data || []);
            } catch (err) {
                console.error('Failed to load agent findings', err);
            } finally {
                setLoadingFindings(false);
            }
        };

        fetchHealth();
    }, []);

    if (loading) {
        return (
            <div className="flex items-center justify-center min-h-[64vh]">
                <Loader2 className="animate-spin text-brand-600" size={32} /> <span>Loading...</span>
            </div>
        );
    }

    if (!health) {
        return (
            <Card padding={false}>
                <CardHeader title="Agent Status" subtitle="Agent health status" />
                <div className="px-5 py-4 border-b border-slate-100">
                    <p className="text-sm text-slate-500">Agent health data unavailable</p>
                </div>
            </Card>
        );
    }

    const statusColor = health.status === 'running' ? 'bg-brand-500 text-white' :
                       health.status === 'stopped' ? 'bg-slate-600 text-white' :
                       health.status === 'starting' ? 'bg-amber-500 text-white' :
                       health.status === 'stopping' ? 'bg-slate-600 text-white' :
                       health.status === 'unhealthy' ? 'bg-red-500 text-white' : 'bg-slate-500 text-white';

    return (
        <>
        <Card padding={false}>
            <CardHeader title="Agent Status" subtitle="Agent health status" />
            <div className="px-5 py-4 border-b border-slate-100">
                <div className="flex items-center gap-3">
                    <div className={`w-8 h-8 rounded-full ${statusColor} flex items-center justify-center text-xs font-medium`}>
                        {health.status}
                    </div>
                    <div>
                        <p className="font-medium text-slate-900">Agent Status</p>
                        <p className="text-xs text-slate-500">{health.state || 'unknown'}</p>
                    </div>
                </div>
            </div>
            <div className="px-5 py-3">
                <div className="grid grid-cols-2 gap-3 text-sm">
                    <div>
                        <span className="text-slate-500">Agent ID:</span>
                        <span className="font-mono break-all text-slate-600">{health.agent_id || 'N/A'}</span>
                    </div>
                    <div>
                        <span className="text-slate-500">Version:</span>
                        <span className="font-mono break-all text-slate-600">{health.version || 'N/A'}</span>
                    </div>
                    <div>
                        <span className="text-slate-500">Last Heartbeat:</span>
                        <span className="font-mono break-all text-slate-600">{health.last_heartbeat_at || 'N/A'}</span>
                    </div>
                    <div>
                        <span className="text-slate-500">Started:</span>
                        <span className="font-mono break-all text-slate-600">{health.started_at || 'N/A'}</span>
                    </div>
                </div>
            </div>
        </Card>

        <Card padding={false} className="mt-6">
            <CardHeader 
                title="Recent Findings" 
                subtitle="Findings discovered by this Agent" 
                icon={<Shield className="w-5 h-5 text-brand-500" />}
            />
            
            {loadingFindings ? (
                <div className="flex items-center justify-center p-8">
                    <Loader2 className="animate-spin text-brand-600" size={24} />
                </div>
            ) : findings.length === 0 ? (
                <div className="p-12 text-center border-b border-slate-100">
                    <div className="w-12 h-12 rounded-full bg-slate-50 flex items-center justify-center mx-auto mb-3">
                        <Search className="w-6 h-6 text-slate-400" />
                    </div>
                    <p className="text-sm font-medium text-slate-900">No findings discovered by this Agent yet.</p>
                    <p className="text-xs text-slate-500 mt-1">When this Agent identifies security issues, they will appear here.</p>
                </div>
            ) : (
                <div className="divide-y divide-slate-100">
                    {findings.map((finding) => (
                        <Link 
                            key={finding.id} 
                            to={`/findings/${finding.id}`}
                            className="flex items-start gap-4 p-4 hover:bg-slate-50 transition-colors"
                        >
                            <SeverityBadge severity={finding.severity} />
                            
                            <div className="flex-1 min-w-0">
                                <p className="text-sm font-medium text-slate-900 truncate">
                                    {finding.title}
                                </p>
                                <div className="flex items-center gap-3 mt-1 text-xs text-slate-500">
                                    <span className="flex items-center gap-1">
                                        <Calendar className="w-3.5 h-3.5" />
                                        {new Date(finding.created_at).toLocaleDateString()}
                                    </span>
                                    {finding.target && (
                                        <span className="flex items-center gap-1">
                                            <LayoutDashboard className="w-3.5 h-3.5" />
                                            {finding.target.name}
                                        </span>
                                    )}
                                    {finding.asset && (
                                        <span className="flex items-center gap-1">
                                            <FileText className="w-3.5 h-3.5" />
                                            {finding.asset.name}
                                        </span>
                                    )}
                                    <MonoChip text={finding.lifecycle_status} />
                                </div>
                            </div>
                        </Link>
                    ))}
                </div>
            )}
        </Card>
        </>
    );
}