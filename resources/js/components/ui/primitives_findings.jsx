import React from 'react';

export function SeverityBadge({ severity }) {
    const s = (severity || '').toLowerCase();
    let classes = 'bg-slate-50 text-slate-700 border-slate-200';
    
    if (s === 'critical') {
        classes = 'bg-rose-50 text-rose-800 border-rose-200 font-bold';
    } else if (s === 'high') {
        classes = 'bg-orange-50 text-orange-850 border-orange-200 font-semibold';
    } else if (s === 'medium') {
        classes = 'bg-amber-55 text-amber-850 border-amber-200';
    } else if (s === 'low') {
        classes = 'bg-sky-50 text-sky-850 border-sky-200';
    } else if (s === 'info') {
        classes = 'bg-blue-50 text-blue-750 border-blue-200';
    }

    return (
        <span className={`inline-flex items-center px-2 py-0.5 rounded text-[10px] uppercase font-mono tracking-wider border ${classes}`}>
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
