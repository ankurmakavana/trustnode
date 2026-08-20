import React from 'react';

export const SEVERITY_COLORS = {
    critical: { bg: 'bg-rose-600', text: 'text-rose-600', fill: '#e11d48' },
    high:     { bg: 'bg-orange-500', text: 'text-orange-500', fill: '#f97316' },
    medium:   { bg: 'bg-amber-400', text: 'text-amber-500', fill: '#fbbf24' },
    low:      { bg: 'bg-blue-400', text: 'text-blue-500', fill: '#60a5fa' },
    info:     { bg: 'bg-slate-300', text: 'text-slate-400', fill: '#cbd5e1' }
};

export function SeverityBadge({ severity }) {
    const s = (severity || '').toLowerCase();
    const colors = SEVERITY_COLORS[s] || SEVERITY_COLORS.info;
    
    return (
        <span className={`inline-flex items-center px-2 py-0.5 rounded text-[10px] uppercase font-bold tracking-wider ${colors.bg} text-white`}>
            {s}
        </span>
    );
}

export function StatusBadge({ status }) {
    const s = (status || '').toLowerCase();
    let label = s.replace('_', ' ');
    let classes = 'bg-slate-100 text-slate-800 border-slate-200';

    if (s === 'open' || s === 'new') {
        label = 'new';
        classes = 'bg-blue-50 text-blue-700 border-blue-200';
    } else if (s === 'confirmed') {
        classes = 'bg-rose-50 text-rose-700 border-rose-200';
    } else if (s === 'in_progress') {
        label = 'in progress';
        classes = 'bg-amber-50 text-amber-700 border-amber-200';
    } else if (s === 'remediated' || s === 'resolved') {
        label = 'resolved';
        classes = 'bg-emerald-50 text-emerald-700 border-emerald-200';
    } else if (s === 'false_positive' || s === 'false positive') {
        label = 'false positive';
        classes = 'bg-slate-100 text-slate-600 border-slate-200';
    } else if (s === 'risk_accepted' || s === 'accepted_risk' || s === 'accepted risk') {
        label = 'accepted risk';
        classes = 'bg-purple-50 text-purple-700 border-purple-200';
    } else if (s === 'reopened') {
        classes = 'bg-orange-50 text-orange-700 border-orange-200';
    }

    return (
        <span className={`inline-flex items-center px-2 py-0.5 rounded-full text-[10px] uppercase font-bold tracking-wider border ${classes}`}>
            {label}
        </span>
    );
}

export function CvssIndicator({ score }) {
    const num = Number(score || 0);
    let color = 'text-slate-400 bg-slate-50 border-slate-200';

    if (num >= 9.0) {
        color = 'text-rose-700 bg-rose-50 border-rose-200 font-bold';
    } else if (num >= 7.0) {
        color = 'text-orange-700 bg-orange-50 border-orange-200';
    } else if (num >= 4.0) {
        color = 'text-amber-700 bg-amber-50 border-amber-200';
    } else if (num >= 0.1) {
        color = 'text-sky-700 bg-sky-50 border-sky-200';
    }

    return (
        <div className={`inline-flex items-center gap-1 px-1.5 py-0.5 rounded border text-[11px] font-mono font-semibold ${color}`}>
            <span>CVSS</span>
            <span>{num.toFixed(1)}</span>
        </div>
    );
}
