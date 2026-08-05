import React from 'react';
import { Wrench } from 'lucide-react';

export default function PlaceholderPage({ title }) {
    return (
        <div
            className="flex flex-col items-center justify-center min-h-[64vh] text-center px-4"
            role="main"
            aria-label={`${title} — coming soon`}
        >
            {/* Icon */}
            <div className="w-16 h-16 rounded-2xl bg-slate-100 border border-slate-200 flex items-center justify-center mb-5">
                <Wrench size={24} className="text-slate-400" strokeWidth={1.5} />
            </div>

            {/* Text */}
            <h2 className="text-lg font-bold text-slate-800 mb-2">{title}</h2>
            <p className="text-sm text-slate-500 max-w-sm leading-relaxed">
                This module is on the roadmap. The backend foundation—models, migrations,
                policies, and services—are already implemented and tested.
                The UI will follow in the next sprint.
            </p>

            {/* Status pills */}
            <div className="mt-6 flex flex-wrap items-center justify-center gap-2">
                <span className="inline-flex items-center gap-1.5 text-xs px-3 py-1.5 rounded-full bg-emerald-50 text-emerald-700 font-medium ring-1 ring-emerald-200">
                    <span className="w-1.5 h-1.5 rounded-full bg-emerald-500" />
                    Backend: Complete
                </span>
                <span className="inline-flex items-center gap-1.5 text-xs px-3 py-1.5 rounded-full bg-amber-50 text-amber-700 font-medium ring-1 ring-amber-200">
                    <span className="w-1.5 h-1.5 rounded-full bg-amber-400" />
                    UI: Scheduled
                </span>
            </div>
        </div>
    );
}
