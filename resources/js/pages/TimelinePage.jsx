import React from 'react';
import { Loader2 } from 'lucide-react';

export default function TimelinePage() {
    return (
        <div
            className="flex flex-col items-center justify-center min-h-[64vh] text-center px-4"
            role="main"
            aria-label="Timeline — coming soon"
        >
            <Loader2 className="w-16 h-16 animate-spin text-brand-600 mb-4" />
            <h2 className="text-lg font-bold text-slate-800 mb-2">Timeline</h2>
            <p className="text-sm text-slate-500 max-w-sm leading-relaxed">
                The chronological security timeline is coming in a future release.
                Currently, recent activity events are displayed in the Activity section.
            </p>
        </div>
    );
}