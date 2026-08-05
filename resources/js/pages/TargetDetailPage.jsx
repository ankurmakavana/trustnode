import React, { useState, useEffect } from 'react';
import { 
    ArrowLeft, Calendar, User, Shield, Activity, 
    FileText, Tag, RefreshCw, Server
} from 'lucide-react';
import { Card, CardHeader, MonoChip, Skeleton } from '../components/ui/primitives';
import { TargetTypeBadge, TargetEnvironmentBadge, TargetCriticalityBadge, TargetStatusBadge } from '../components/ui/primitives_targets';
import { useAuth } from '../context/AuthContext';

export default function TargetDetailPage({ targetId, onBack, onEdit }) {
    const { checkAuthStatus } = useAuth();
    const [target, setTarget] = useState(null);
    const [activities, setActivities] = useState([]);
    const [loading, setLoading] = useState(true);
    const [loadingActivity, setLoadingActivity] = useState(true);
    const [error, setError] = useState(null);

    const fetchTargetDetails = async () => {
        setLoading(true);
        try {
            const response = await fetch(`/api/targets/${targetId}`, {
                headers: {
                    'Accept': 'application/json',
                }
            });
            if (response.status === 401) {
                checkAuthStatus();
                return;
            }
            if (!response.ok) {
                throw new Error('Failed to load target details');
            }
            const data = await response.json();
            setTarget(data.data);
        } catch (err) {
            setError(err.message);
        } finally {
            setLoading(false);
        }
    };

    const fetchActivities = async () => {
        setLoadingActivity(true);
        try {
            const response = await fetch(`/api/targets/${targetId}/activity`, {
                headers: {
                    'Accept': 'application/json',
                }
            });
            if (response.status === 401) {
                checkAuthStatus();
                return;
            }
            if (!response.ok) {
                throw new Error('Failed to load target activity logs');
            }
            const data = await response.json();
            setActivities(data.data);
        } catch (err) {
            console.error(err);
        } finally {
            setLoadingActivity(false);
        }
    };

    useEffect(() => {
        fetchTargetDetails();
        fetchActivities();
    }, [targetId]);

    if (loading) {
        return (
            <div className="py-12 flex justify-center items-center">
                <span className="text-xs text-slate-500 font-semibold">Loading target details...</span>
            </div>
        );
    }

    if (error || !target) {
        return (
            <div className="p-8 text-center">
                <p className="text-sm font-semibold text-red-600">Error loading details</p>
                <p className="text-xs text-slate-400 mt-1">{error || 'Target not found'}</p>
                <button
                    onClick={onBack}
                    className="mt-4 px-3 py-1.5 text-xs border border-slate-200 rounded-lg hover:bg-slate-50 transition-colors"
                >
                    Back to Targets
                </button>
            </div>
        );
    }

    return (
        <div className="flex flex-col gap-6 max-w-5xl mx-auto">
            {/* Breadcrumb row */}
            <div className="flex items-center justify-between">
                <button
                    onClick={onBack}
                    className="inline-flex items-center gap-1.5 text-xs font-semibold text-slate-500 hover:text-slate-800 transition-colors focus:outline-none"
                >
                    <ArrowLeft size={14} /> Back to Targets
                </button>
                <button
                    onClick={() => onEdit(target.id)}
                    className="px-3 py-1.5 bg-brand-600 hover:bg-brand-700 text-white text-xs font-semibold rounded-lg transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500"
                >
                    Edit Target
                </button>
            </div>

            {/* Target Header Info */}
            <div className="bg-white border border-slate-200 rounded-2xl p-6 flex flex-col md:flex-row md:items-center justify-between gap-6 shadow-sm">
                <div className="min-w-0">
                    <div className="flex flex-wrap items-center gap-2">
                        <h2 className="text-lg font-bold text-slate-900 truncate">{target.name}</h2>
                        <TargetStatusBadge status={target.status} />
                    </div>
                    <p className="text-xs text-slate-500 mt-1">UUID: <span className="font-mono text-slate-400 select-all">{target.uuid}</span></p>
                </div>
                <div className="flex flex-wrap items-center gap-2.5">
                    <TargetTypeBadge type={target.type} />
                    <TargetEnvironmentBadge env={target.environment} />
                    <TargetCriticalityBadge criticality={target.criticality} />
                </div>
            </div>

            <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
                {/* Details Column */}
                <div className="lg:col-span-2 flex flex-col gap-6">
                    {/* Target Specifications */}
                    <Card padding={false}>
                        <CardHeader title="Scope Specifications" description="Registered technical parameters." />
                        <div className="p-6 border-t border-slate-100 flex flex-col gap-5">
                            <div>
                                <span className="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1.5 select-none">Target Value</span>
                                <MonoChip className="text-sm py-1.5 px-3 block w-fit max-w-full truncate select-all">{target.value}</MonoChip>
                            </div>

                            {target.description && (
                                <div>
                                    <span className="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1 select-none">Description</span>
                                    <p className="text-xs text-slate-600 leading-relaxed">{target.description}</p>
                                </div>
                            )}

                            {target.scope_notes && (
                                <div>
                                    <span className="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1 select-none">Scope & Testing Guidelines</span>
                                    <p className="text-xs text-slate-600 bg-slate-50 border border-slate-100 rounded-lg p-3.5 leading-relaxed whitespace-pre-wrap">{target.scope_notes}</p>
                                </div>
                            )}
                        </div>
                    </Card>

                    {/* Target Activity Logs */}
                    <Card padding={false}>
                        <CardHeader title="Audit & Activity Log" description="Log history of changes applied to this target scope." />
                        <div className="border-t border-slate-100 divide-y divide-slate-100">
                            {loadingActivity ? (
                                <div className="p-6 space-y-3">
                                    <Skeleton className="h-4 w-full" />
                                    <Skeleton className="h-4 w-3/4" />
                                </div>
                            ) : activities.length === 0 ? (
                                <div className="p-6 text-center">
                                    <p className="text-xs text-slate-400">No activity logs recorded.</p>
                                </div>
                            ) : (
                                activities.map((log) => (
                                    <div key={log.id} className="p-4 flex gap-3 text-xs">
                                        <div className="w-6 h-6 rounded bg-slate-50 flex items-center justify-center shrink-0 text-slate-400 mt-0.5">
                                            <Activity size={12} />
                                        </div>
                                        <div className="flex-1 min-w-0">
                                            <p className="text-slate-600">
                                                <span className="font-semibold text-slate-900">{log.user?.name || 'System'}</span>{' '}
                                                {log.action === 'created' ? 'registered target scope' : 'updated target details'}
                                            </p>
                                            {log.properties?.new && (
                                                <div className="mt-2 bg-slate-50 border border-slate-100 rounded-md p-2 font-mono text-[10px] text-slate-600 max-h-40 overflow-y-auto">
                                                    {Object.entries(log.properties.new).map(([key, val]) => (
                                                        <div key={key}>
                                                            <span className="text-brand-600 font-semibold">{key}:</span> {typeof val === 'object' ? JSON.stringify(val) : String(val)}
                                                        </div>
                                                    ))}
                                                </div>
                                            )}
                                            <p className="text-[10px] text-slate-400 mt-1">{new Date(log.created_at).toLocaleString()}</p>
                                        </div>
                                    </div>
                                ))
                            )}
                        </div>
                    </Card>
                </div>

                {/* Meta Column */}
                <div className="flex flex-col gap-6">
                    {/* Metadata Card */}
                    <Card padding={false}>
                        <CardHeader title="Information" description="Owner and creation timeline details." />
                        <div className="p-4 border-t border-slate-100 divide-y divide-slate-100">
                            {/* Tags */}
                            <div className="py-2.5 first:pt-0">
                                <span className="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-2 select-none">Tags</span>
                                {target.tags && target.tags.length > 0 ? (
                                    <div className="flex flex-wrap gap-1.5">
                                        {target.tags.map(tag => (
                                            <span 
                                                key={tag.id} 
                                                className="text-[10px] font-semibold px-2 py-0.5 rounded"
                                                style={{ backgroundColor: tag.color || '#f1f5f9', color: '#1e293b' }}
                                            >
                                                {tag.name}
                                            </span>
                                        ))}
                                    </div>
                                ) : (
                                    <span className="text-xs text-slate-400">No tags registered.</span>
                                )}
                            </div>

                            {/* Creator */}
                            <div className="py-2.5">
                                <span className="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1.5 select-none">Created By</span>
                                <div className="flex items-center gap-2">
                                    <User size={13} className="text-slate-400" />
                                    <span className="text-xs text-slate-600 font-medium">{target.created_by?.name || 'Unknown'}</span>
                                </div>
                            </div>

                            {/* Timestamps */}
                            <div className="py-2.5 last:pb-0">
                                <span className="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1.5 select-none">Created At</span>
                                <div className="flex items-center gap-2">
                                    <Calendar size={13} className="text-slate-400" />
                                    <span className="text-xs text-slate-600 font-medium">{new Date(target.created_at).toLocaleDateString()}</span>
                                </div>
                            </div>
                        </div>
                    </Card>
                </div>
            </div>
        </div>
    );
}
