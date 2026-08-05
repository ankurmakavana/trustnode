/**
 * TrustNode Target Primitives
 *
 * Primitive components for formatting target metadata badges.
 */

import React from 'react';
import { Badge } from './primitives';

// ── TargetTypeBadge ───────────────────────────────────────────────────────────

const targetTypeLabelMap = {
    domain:             { text: 'Domain',             variant: 'indigo'  },
    ip_address:         { text: 'IP Address',         variant: 'emerald' },
    cidr_range:         { text: 'CIDR Range',         variant: 'violet'  },
    url:                { text: 'URL',                variant: 'fuchsia' },
    api_endpoint:       { text: 'API Endpoint',       variant: 'rose'    },
    mobile_application: { text: 'Mobile Application', variant: 'amber'   },
    cloud_resource:     { text: 'Cloud Resource',     variant: 'cyan'    },
};

export function TargetTypeBadge({ type }) {
    const cfg = targetTypeLabelMap[type?.toLowerCase()] || { text: type, variant: 'slate' };
    return (
        <Badge variant={cfg.variant}>
            {cfg.text}
        </Badge>
    );
}

// ── TargetEnvironmentBadge ────────────────────────────────────────────────────

const targetEnvLabelMap = {
    production:  { text: 'Production',  variant: 'red'     },
    staging:     { text: 'Staging',     variant: 'orange'  },
    development: { text: 'Development', variant: 'blue'    },
    internal:    { text: 'Internal',    variant: 'slate'   },
};

export function TargetEnvironmentBadge({ env }) {
    const cfg = targetEnvLabelMap[env?.toLowerCase()] || { text: env, variant: 'slate' };
    return (
        <Badge variant={cfg.variant}>
            {cfg.text}
        </Badge>
    );
}

// ── TargetCriticalityBadge ────────────────────────────────────────────────────

const targetCritLabelMap = {
    critical: { text: 'Critical', variant: 'red'     },
    high:     { text: 'High',     variant: 'orange'  },
    medium:   { text: 'Medium',   variant: 'amber'   },
    low:      { text: 'Low',      variant: 'blue'    },
};

export function TargetCriticalityBadge({ criticality }) {
    const cfg = targetCritLabelMap[criticality?.toLowerCase()] || { text: criticality, variant: 'slate' };
    return (
        <Badge variant={cfg.variant} className="font-semibold">
            {cfg.text}
        </Badge>
    );
}

// ── TargetStatusBadge ─────────────────────────────────────────────────────────

const targetStatusLabelMap = {
    active:       { text: 'Active',       variant: 'emerald' },
    inactive:     { text: 'Inactive',     variant: 'slate'   },
    under_review: { text: 'Under Review', variant: 'violet'  },
};

export function TargetStatusBadge({ status }) {
    const cfg = targetStatusLabelMap[status?.toLowerCase()] || { text: status, variant: 'slate' };
    return (
        <Badge variant={cfg.variant}>
            {cfg.text}
        </Badge>
    );
}
