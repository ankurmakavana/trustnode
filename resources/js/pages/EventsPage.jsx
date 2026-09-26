import React, { useState, useEffect } from 'react';
import axios from 'axios';
import { Loader2 } from 'lucide-react';
import { Card, CardHeader, ViewAllLink } from '../components/ui/primitives';

export default function EventsPage() {
    const [events, setEvents] = useState([]);
    const [loading, setLoading] = useState(true);

    useEffect(() => {
        const fetchEvents = async () => {
            try {
                // NOTE: /api/agent/events endpoint does not exist yet
                // This page shows a message until the backend events API is implemented
                setEvents([]);
            } catch (err) {
                console.error('Failed to load agent events', err);
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
                title="Agent Events"
                subtitle="Recent agent activity events"
                action={<ViewAllLink />}
            />
            <div className="px-5 py-4 border-b border-slate-100">
                <p className="text-sm text-slate-500">
                    Agent events API endpoint not yet implemented. This view requires
                    a backend endpoint to expose recent agent events (observations, findings, heartbeats, etc.).
                </p>
            </div>
        </Card>
    );
}