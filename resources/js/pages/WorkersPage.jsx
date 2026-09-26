import React, { useState, useEffect } from 'react';
import axios from 'axios';
import { Loader2 } from 'lucide-react';
import { Card, CardHeader, StatusBadge } from '../components/ui/primitives';

export default function WorkersPage() {
    const [stats, setStats] = useState(null);
    const [loading, setLoading] = useState(true);

    useEffect(() => {
        const fetchStats = async () => {
            try {
                // NOTE: /api/agent/workers endpoint does not exist yet
                // This page shows a message until the backend worker status API is implemented
                const response = await axios.get('/api/agent/health');
                // We can't get worker stats from health endpoint, so just show placeholder
                setStats({
                    queueDepth: 0,
                    processing: 0,
                    completed: 0,
                    failed: 0
                });
            } catch (err) {
                console.error('Failed to load worker stats', err);
                setStats(null);
            } finally {
                setLoading(false);
            }
        };
        fetchStats();
    }, []);

    if (loading) {
        return (
            <div className="flex items-center justify-center min-h-[64vh]">
                <Loader2 className="animate-spin text-brand-600" size={32} /> <span>Loading...</span>
            </div>
        );
    }

    return (
        <Card padding={false}>
            <CardHeader title="Agent Workers" subtitle="Queue and worker status" />
            <div className="px-5 py-4 border-b border-slate-100">
                <p className="text-sm text-slate-500">
                    Worker status API endpoint not yet implemented. This view requires
                    a backend endpoint to expose queue depth, processing, completed, and failed counts.
                </p>
            </div>
            <div className="p-5 grid grid-cols-2 sm:grid-cols-4 gap-4">
                <div className="text-center p-3 bg-slate-50 rounded-lg">
                    <p className="text-2xl font-bold text-slate-900">{stats?.queueDepth || '—'}</p>
                    <p className="text-xs text-slate-500">Queue Depth</p>
                </div>
                <div className="text-center p-3 bg-slate-50 rounded-lg">
                    <p className="text-2xl font-bold text-brand-600">{stats?.processing || '—'}</p>
                    <p className="text-xs text-slate-500">Processing</p>
                </div>
                <div className="text-center p-3 bg-slate-50 rounded-lg">
                    <p className="text-2xl font-bold text-emerald-600">{stats?.completed || '—'}</p>
                    <p className="text-xs text-slate-500">Completed</p>
                </div>
                <div className="text-center p-3 bg-slate-50 rounded-lg">
                    <p className="text-2xl font-bold text-red-600">{stats?.failed || '—'}</p>
                    <p className="text-xs text-slate-500">Failed</p>
                </div>
            </div>
        </Card>
    );
}