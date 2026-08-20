/**
 * TrustNode UI Primitives
 *
 * Reusable, accessible atomic components.
 * Import from here instead of duplicating inline.
 */

import React from 'react';

// ── Badge ─────────────────────────────────────────────────────────────────────

const badgeVariants = {
    red:     'bg-red-100    text-red-700    ring-red-200',
    orange:  'bg-orange-100 text-orange-700 ring-orange-200',
    amber:   'bg-amber-100  text-amber-700  ring-amber-200',
    emerald: 'bg-emerald-100 text-emerald-700 ring-emerald-200',
    blue:    'bg-blue-100   text-blue-700   ring-blue-200',
    violet:  'bg-violet-100 text-violet-700 ring-violet-200',
    brand:   'bg-brand-100  text-brand-700  ring-brand-200',
    slate:   'bg-slate-100  text-slate-600  ring-slate-200',
};

export function Badge({ children, variant = 'slate', className = '' }) {
    const styles = badgeVariants[variant] || badgeVariants.slate;
    return (
        <span
            className={`
                inline-flex items-center px-1.5 py-0.5 rounded-md text-xs font-medium
                ring-1 ring-inset ${styles} ${className}
            `}
        >
            {children}
        </span>
    );
}

// ── StatusBadge ───────────────────────────────────────────────────────────────

const statusVariants = {
    running:   { label: 'Running',   dot: 'bg-brand-500 animate-pulse', variant: 'brand'   },
    queued:    { label: 'Queued',    dot: 'bg-amber-400',               variant: 'amber'   },
    completed: { label: 'Completed', dot: 'bg-emerald-500',             variant: 'emerald' },
    failed:    { label: 'Failed',    dot: 'bg-red-500',                 variant: 'red'     },
    paused:    { label: 'Paused',    dot: 'bg-slate-400',               variant: 'slate'   },
};

export function StatusBadge({ status }) {
    const cfg = statusVariants[status] || statusVariants.queued;
    return (
        <Badge variant={cfg.variant} className="gap-1.5">
            <span className={`w-1.5 h-1.5 rounded-full ${cfg.dot} shrink-0`} />
            {cfg.label}
        </Badge>
    );
}

// SeverityBadge has been consolidated to primitives_findings.jsx

// ── ProgressBar ───────────────────────────────────────────────────────────────

const progressColors = {
    running:   'bg-brand-500',
    queued:    'bg-amber-400',
    completed: 'bg-emerald-500',
    failed:    'bg-red-400',
};

export function ProgressBar({ value, status = 'completed', className = '' }) {
    const color = progressColors[status] || progressColors.completed;
    const clamped = Math.min(100, Math.max(0, value));

    return (
        <div
            className={`w-full h-1.5 bg-slate-100 rounded-full overflow-hidden ${className}`}
            role="progressbar"
            aria-valuenow={clamped}
            aria-valuemin={0}
            aria-valuemax={100}
            aria-label={`${clamped}% complete`}
        >
            <div
                className={`h-full rounded-full transition-all duration-700 ${color}`}
                style={{ width: `${clamped}%` }}
            />
        </div>
    );
}

// ── Avatar ────────────────────────────────────────────────────────────────────

const avatarSizes = {
    xs: 'w-5 h-5 text-[10px]',
    sm: 'w-6 h-6 text-xs',
    md: 'w-8 h-8 text-sm',
    lg: 'w-10 h-10 text-base',
};

export function Avatar({ initials, size = 'md', className = '' }) {
    const sizeStyles = avatarSizes[size] || avatarSizes.md;
    return (
        <div
            aria-hidden="true"
            className={`
                ${sizeStyles} rounded-full shrink-0
                bg-gradient-to-br from-brand-500 to-brand-700
                flex items-center justify-center font-semibold text-white
                ${className}
            `}
        >
            {initials}
        </div>
    );
}

// ── Card ──────────────────────────────────────────────────────────────────────

export function Card({ children, className = '', hover = false, padding = true }) {
    return (
        <div
            className={`
                bg-white rounded-xl border border-slate-200
                ${hover ? 'hover:shadow-md hover:shadow-slate-100 hover:-translate-y-0.5 transition-all duration-200' : ''}
                ${padding ? 'p-5' : 'overflow-hidden'}
                ${className}
            `}
        >
            {children}
        </div>
    );
}

// ── CardHeader ────────────────────────────────────────────────────────────────

export function CardHeader({ title, subtitle, action, className = '' }) {
    return (
        <div className={`flex items-start justify-between gap-3 ${className}`}>
            <div className="min-w-0">
                <h3 className="text-sm font-semibold text-slate-900 leading-tight">{title}</h3>
                {subtitle && (
                    <p className="text-xs text-slate-500 mt-0.5">{subtitle}</p>
                )}
            </div>
            {action && (
                <div className="shrink-0">{action}</div>
            )}
        </div>
    );
}

// ── ViewAllLink ───────────────────────────────────────────────────────────────

export function ViewAllLink({ label = 'View all', onClick }) {
    return (
        <button
            onClick={onClick}
            className="text-xs font-medium text-brand-600 hover:text-brand-700 transition-colors focus:outline-none focus-visible:underline"
        >
            {label} →
        </button>
    );
}

// ── Skeleton ──────────────────────────────────────────────────────────────────

export function Skeleton({ className = '' }) {
    return (
        <div className={`animate-pulse bg-slate-200 rounded ${className}`} />
    );
}

export function SkeletonCard() {
    return (
        <Card>
            <Skeleton className="h-3 w-24 mb-3" />
            <Skeleton className="h-8 w-16 mb-4" />
            <Skeleton className="h-3 w-32" />
        </Card>
    );
}

// ── MonospaceChip ─────────────────────────────────────────────────────────────

export function MonoChip({ children }) {
    return (
        <span className="text-xs font-mono text-slate-500 bg-slate-100 px-1.5 py-0.5 rounded">
            {children}
        </span>
    );
}

// ── IconButton ────────────────────────────────────────────────────────────────

export function IconButton({ icon: Icon, label, onClick, active = false, className = '' }) {
    return (
        <button
            onClick={onClick}
            aria-label={label}
            className={`
                p-2 rounded-lg transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500
                ${active
                    ? 'bg-slate-100 text-slate-700'
                    : 'text-slate-500 hover:bg-slate-100 hover:text-slate-700'}
                ${className}
            `}
        >
            <Icon size={16} strokeWidth={1.75} />
        </button>
    );
}
