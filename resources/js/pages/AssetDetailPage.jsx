import React, { useState, useEffect } from 'react';
import { 
    ArrowLeft, Calendar, User, Shield, Activity, 
    FileText, Tag, RefreshCw, Server
} from 'lucide-react';
import { Card, CardHeader, MonoChip, Skeleton } from '../components/ui/primitives';
import { RiskBadge, AssetTypeBadge } from '../components/ui/primitives_assets';
import { useAuth } from '../context/AuthContext';

export default function AssetDetailPage({ assetId, onBack, onEdit }) {
    const { checkAuthStatus } = useAuth();
    const [asset, setAsset] = useState(null);
    const [activities, setActivities] = useState([]);
    const [loading, setLoading] = useState(true);
    const [loadingActivity, setLoadingActivity] = useState(true);
    const [error, setError] = useState(null);

    const fetchAssetDetails = async () => {
        setLoading(true);
        try {
            const response = await fetch(`/api/assets/${assetId}`, {
                headers: {
                    'Accept': 'application/json',
                }
            });
            if (response.status === 401) {
                checkAuthStatus();
                return;
            }
            if (!response.ok) {
                throw new Error('Failed to load asset details');
            }
            const data = await response.json();
            setAsset(data.data);
        } catch (err) {
            setError(err.message);
        } finally {
            setLoading(false);
        }
    };

    const fetchActivities = async () => {
        setLoadingActivity(true);
        try {
            const response = await fetch(`/api/assets/${assetId}/activity`, {
                headers: {
                    'Accept': 'application/json',
                }
            });
            if (response.status === 401) {
                checkAuthStatus();
                return;
            }
            if (!response.ok) {
                throw new Error('Failed to load activity logs');
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
        fetchAssetDetails();
        fetchActivities();
    }, [assetId]);

    if (loading) {
        return (
            <div className="space-y-6">
                <Skeleton className="h-6 w-32" />
                <Skeleton className="h-32 w-full" />
                <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <Skeleton className="h-40" />
                    <Skeleton className="h-40 md:col-span-2" />
                </div>
            </div>
        );
    }

    if (error || !asset) {
        return (
            <div className="p-12 text-center">
                <p className="text-sm font-semibold text-red-500">Error loading asset details</p>
                <p className="text-xs text-slate-400 mt-1">{error || 'Asset not found'}</p>
                <button onClick={onBack} className="mt-4 inline-flex items-center gap-1 text-xs text-brand-600 font-semibold hover:underline">
                    <ArrowLeft size={12} /> Go Back
                </button>
            </div>
        );
    }

    return (
        <div className="flex flex-col gap-6">
            {/* Header / Breadcrumb navigation */}
            <div className="flex flex-wrap items-center justify-between gap-4">
                <button
                    onClick={onBack}
                    className="inline-flex items-center gap-1.5 text-xs font-semibold text-slate-600 hover:text-slate-900 transition-colors focus:outline-none"
                >
                    <ArrowLeft size={14} /> Back to Catalog
                </button>
                <button
                    onClick={() => onEdit(asset.id)}
                    className="inline-flex items-center gap-1 px-3 py-2 text-xs font-semibold text-white bg-brand-600 rounded-lg hover:bg-brand-700 transition-colors"
                >
                    Edit Asset
                </button>
            </div>

            {/* Asset Hero Details */}
            <Card className="flex flex-col md:flex-row items-start justify-between gap-6">
                <div className="flex-1 min-w-0">
                    <div className="flex flex-wrap items-center gap-2">
                        <AssetTypeBadge type={asset.type} />
                        <span className={`inline-flex items-center gap-1 px-1.5 py-0.5 rounded text-[10px] font-semibold ${asset.status === 'active' ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-600'}`}>
                            {asset.status.toUpperCase()}
                        </span>
                        {asset.group && (
                            <span className="text-xs font-medium text-slate-400">
                                in <span className="text-slate-600 font-semibold">{asset.group.name}</span>
                            </span>
                        )}
                    </div>
                    <h2 className="text-2xl font-bold text-slate-900 mt-2 truncate">{asset.name}</h2>
                    <div className="mt-2">
                        <MonoChip>{asset.value}</MonoChip>
                    </div>
                    <p className="text-xs text-slate-500 mt-3 leading-relaxed max-w-2xl">
                        {asset.description || 'No description provided for this asset.'}
                    </p>
                </div>

                {/* Sizing box */}
                <div className="flex flex-col gap-3 shrink-0 p-4 bg-slate-50 border border-slate-200/60 rounded-xl min-w-[200px]">
                    <div className="flex items-center justify-between gap-3">
                        <span className="text-xs text-slate-500">Risk Score:</span>
                        <RiskBadge score={asset.risk_score} />
                    </div>
                    <div className="flex items-center justify-between gap-3">
                        <span className="text-xs text-slate-500">Criticality:</span>
                        <span className={`text-xs font-bold uppercase ${asset.criticality === 'critical' || asset.criticality === 'high' ? 'text-red-600' : 'text-slate-600'}`}>
                            {asset.criticality}
                        </span>
                    </div>
                    <div className="flex items-center justify-between gap-3">
                        <span className="text-xs text-slate-500">Owner:</span>
                        <span className="text-xs text-slate-700 font-semibold truncate max-w-[120px]">{asset.owner || '—'}</span>
                    </div>
                </div>
            </Card>

            <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
                {/* Meta details column */}
                <div className="flex flex-col gap-6 lg:col-span-1">
                    {/* Metadata Card */}
                    <Card className="flex flex-col gap-4">
                        <div className="border-b border-slate-100 pb-3">
                            <h3 className="text-sm font-semibold text-slate-900">Metadata Details</h3>
                        </div>
                        <div className="flex flex-col gap-3">
                            <div className="flex items-center gap-2.5 text-xs">
                                <Calendar size={14} className="text-slate-400" />
                                <span className="text-slate-500">Created:</span>
                                <span className="font-medium text-slate-700">{asset.created_at ? new Date(asset.created_at).toLocaleString() : '—'}</span>
                            </div>
                            {asset.created_by && (
                                <div className="flex items-center gap-2.5 text-xs">
                                    <User size={14} className="text-slate-400" />
                                    <span className="text-slate-500">Created By:</span>
                                    <span className="font-medium text-slate-700">{asset.created_by.name}</span>
                                </div>
                            )}
                            <div className="flex items-center gap-2.5 text-xs">
                                <RefreshCw size={14} className="text-slate-400" />
                                <span className="text-slate-500">Last Updated:</span>
                                <span className="font-medium text-slate-700">{asset.updated_at ? new Date(asset.updated_at).toLocaleString() : '—'}</span>
                            </div>
                            {asset.updated_by && (
                                <div className="flex items-center gap-2.5 text-xs">
                                    <User size={14} className="text-slate-400" />
                                    <span className="text-slate-500">Updated By:</span>
                                    <span className="font-medium text-slate-700">{asset.updated_by.name}</span>
                                </div>
                            )}
                        </div>
                    </Card>

                    {/* Tags Card */}
                    <Card className="flex flex-col gap-4">
                        <div className="border-b border-slate-100 pb-3">
                            <h3 className="text-sm font-semibold text-slate-900">Tags</h3>
                        </div>
                        <div className="flex flex-wrap gap-1.5">
                            {asset.tags && asset.tags.length > 0 ? (
                                asset.tags.map(t => (
                                    <span
                                        key={t.id}
                                        style={{ background: t.color, color: '#334155' }}
                                        className="text-xs font-semibold px-2 py-0.5 rounded-md border border-slate-200/80"
                                    >
                                        {t.name}
                                    </span>
                                ))
                            ) : (
                                <span className="text-xs text-slate-400">No tags assigned.</span>
                            )}
                        </div>
                    </Card>
                </div>

                {/* Audit Logs and notes column */}
                <div className="flex flex-col gap-6 lg:col-span-2">
                    {/* Activity Logs */}
                    <Card className="flex flex-col gap-4">
                        <div className="border-b border-slate-100 pb-3 flex items-center justify-between">
                            <h3 className="text-sm font-semibold text-slate-900">Audit Logs</h3>
                            <span className="text-xs text-slate-400">{activities.length} entries</span>
                        </div>
                        {loadingActivity ? (
                            <div className="space-y-2">
                                <Skeleton className="h-6 w-full" />
                                <Skeleton className="h-6 w-full" />
                            </div>
                        ) : activities.length === 0 ? (
                            <p className="text-xs text-slate-400">No logs found.</p>
                        ) : (
                            <div className="flex flex-col divide-y divide-slate-100">
                                {activities.map(log => (
                                    <div key={log.id} className="py-3 flex items-start gap-3 first:pt-0 last:pb-0">
                                        <div className="w-6 h-6 rounded bg-slate-100 flex items-center justify-center shrink-0 mt-0.5">
                                            <Activity size={12} className="text-slate-500" />
                                        </div>
                                        <div className="flex-1 min-w-0">
                                            <p className="text-xs font-semibold text-slate-800">
                                                {log.user ? log.user.name : 'System'} {log.action} this asset.
                                            </p>
                                            {log.properties && log.properties.new && (
                                                <div className="mt-1 bg-slate-50 border border-slate-200/40 rounded p-1.5 font-mono text-[10px] text-slate-600 overflow-x-auto">
                                                    Changes: {JSON.stringify(log.properties.new)}
                                                </div>
                                            )}
                                            <div className="flex items-center gap-2 mt-1 text-[10px] text-slate-400">
                                                <span>IP: {log.ip_address || 'Localhost'}</span>
                                                <span>•</span>
                                                <span>{log.created_at ? new Date(log.created_at).toLocaleString() : ''}</span>
                                            </div>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        )}
                    </Card>

                    {/* Internal Notes */}
                    {asset.notes && (
                        <Card className="flex flex-col gap-4">
                            <div className="border-b border-slate-100 pb-3">
                                <h3 className="text-sm font-semibold text-slate-900">Scoping and Triage Notes</h3>
                            </div>
                            <pre className="text-xs font-mono text-slate-700 whitespace-pre-wrap bg-slate-50 border border-slate-200/50 rounded-xl p-4 leading-relaxed">
                                {asset.notes}
                            </pre>
                        </Card>
                    )}
                </div>
            </div>
        </div>
    );
}
