import React, { useState, useEffect } from 'react';
import axios from 'axios';
import { Loader2, Shield, LayoutDashboard, ScanLine, FileText, CheckCircle2, X } from 'lucide-react';
import { Card, CardHeader, ViewAllLink, MonoChip } from '../components/ui/primitives';
import { SeverityBadge } from '../components/ui/primitives_findings';

export default function ActivityPage() {
    const [events, setEvents] = useState([]);
    const [loading, setLoading] = useState(true);

    useEffect(() => {
        const fetchEvents = async () => {
            try {
                const response = await axios.get('/api/dashboard/stats', {
                    params: { timeline: 'true' }
                });
                // NOTE: The /api/dashboard/stats endpoint currently does not support timeline mode.
                // This page will show a message until the backend timeline API is implemented.
                setEvents([]);
            } catch (err) {
                console.error('Failed to load timeline events', err);
                setEvents([]);
            } finally {
                setLoading(false);
            }
        };
        fetchEvents();
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
            <CardHeader
                title="Security Timeline"
                subtitle="Chronological view of security events"
                action={<ViewAllLink />}
            />
            <div className="px-5 py-4 border-b border-slate-100">
                <p className="text-sm text-slate-500">
                    Timeline API endpoint not yet implemented. Recent scans and findings activity
                    are shown in the dashboard and respective sections.
                </p>
            </div>
        </Card>
    );
}