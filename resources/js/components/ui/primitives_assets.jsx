/**
 * TrustNode UI Primitives Addition
 *
 * Additional primitive components for the Asset management module.
 */

import React from 'react';
import { Badge } from './primitives';

// ── RiskBadge ─────────────────────────────────────────────────────────────────

export function RiskBadge({ score }) {
    const s = parseFloat(score);
    let color = 'slate';
    let label = 'Low';

    if (s >= 9.0) {
        color = 'red';
        label = 'Critical';
    } else if (s >= 7.0) {
        color = 'orange';
        label = 'High';
    } else if (s >= 4.0) {
        color = 'amber';
        label = 'Medium';
    } else {
        color = 'blue';
        label = 'Low';
    }

    return (
        <Badge variant={color} className="gap-1 font-semibold">
            <span className="tabular-nums">{s.toFixed(2)}</span>
            <span className="text-[10px] opacity-75">({label})</span>
        </Badge>
    );
}

// ── AssetTypeBadge ────────────────────────────────────────────────────────────

const typeLabelMap = {
    domain:       { text: 'Domain',       variant: 'indigo'  },
    subdomain:    { text: 'Subdomain',    variant: 'blue'    },
    ipv4:         { text: 'IPv4',         variant: 'emerald' },
    ipv6:         { text: 'IPv6',         variant: 'teal'    },
    cidr:         { text: 'CIDR',         variant: 'violet'  },
    url:          { text: 'URL',          variant: 'fuchsia' },
    api_endpoint: { text: 'API Endpoint', variant: 'rose'    },
    hostname:     { text: 'Hostname',     variant: 'slate'   },
};

export function AssetTypeBadge({ type }) {
    const cfg = typeLabelMap[type?.toLowerCase()] || { text: type, variant: 'slate' };
    return (
        <Badge variant={cfg.variant}>
            {cfg.text}
        </Badge>
    );
}

// ── ConfirmationDialog ────────────────────────────────────────────────────────

export function ConfirmationDialog({ isOpen, title, message, confirmLabel = 'Confirm', cancelLabel = 'Cancel', onConfirm, onCancel, isDanger = false }) {
    if (!isOpen) return null;

    return (
        <div className="fixed inset-0 z-50 overflow-y-auto" role="dialog" aria-modal="true" aria-labelledby="modal-title">
            {/* Backdrop */}
            <div className="fixed inset-0 bg-slate-900/40 backdrop-blur-sm transition-opacity" onClick={onCancel}></div>

            <div className="flex min-h-full items-end justify-center p-4 text-center sm:items-center sm:p-0">
                <div className="relative transform overflow-hidden rounded-xl bg-white text-left shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-lg border border-slate-200">
                    <div className="bg-white px-6 py-5">
                        <h3 className="text-base font-bold text-slate-900" id="modal-title">
                            {title}
                        </h3>
                        <p className="text-sm text-slate-500 mt-2">
                            {message}
                        </p>
                    </div>
                    <div className="bg-slate-50 px-6 py-3 flex flex-row-reverse gap-2 border-t border-slate-100">
                        <button
                            type="button"
                            onClick={onConfirm}
                            className={`
                                inline-flex justify-center rounded-lg px-4 py-2 text-sm font-semibold text-white shadow-sm
                                focus:outline-none focus-visible:ring-2 focus-visible:ring-offset-2
                                ${isDanger
                                    ? 'bg-red-600 hover:bg-red-700 focus-visible:ring-red-500'
                                    : 'bg-brand-600 hover:bg-brand-700 focus-visible:ring-brand-500'}
                            `}
                        >
                            {confirmLabel}
                        </button>
                        <button
                            type="button"
                            onClick={onCancel}
                            className="inline-flex justify-center rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500"
                        >
                            {cancelLabel}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    );
}
