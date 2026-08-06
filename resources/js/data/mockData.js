/**
 * TrustNode — Mock Data
 *
 * All data uses realistic cybersecurity / VAPT domain terminology.
 * No generic placeholder names (Acme, Jane Doe, etc.) allowed.
 */

// ── Stat Cards ───────────────────────────────────────────────────────────────

export const mockStats = [
    {
        id:         'assets',
        label:      'Monitored Assets',
        value:      '247',
        delta:      '+12',
        deltaLabel: 'added this month',
        trend:      'up',
        color:      'blue',
        icon:       'Server',
        description:'Registered IPs, domains & network ranges',
    },
    {
        id:         'scans',
        label:      'Active Scans',
        value:      '8',
        delta:      '3 queued',
        deltaLabel: '',
        trend:      'neutral',
        color:      'violet',
        icon:       'ScanLine',
        description:'Running + pending assessments',
    },
    {
        id:         'findings',
        label:      'Critical Findings',
        value:      '34',
        delta:      '−7',
        deltaLabel: 'vs last week',
        trend:      'down',
        color:      'red',
        icon:       'ShieldAlert',
        description:'Unresolved critical-severity vulnerabilities',
    },
    {
        id:         'reports',
        label:      'Reports Exported',
        value:      '128',
        delta:      '+4',
        deltaLabel: 'this week',
        trend:      'up',
        color:      'emerald',
        icon:       'FileText',
        description:'Compiled assessment reports',
    },
];

// ── Severity Distribution ────────────────────────────────────────────────────

export const mockSeverityData = [
    { severity: 'Critical', count:  34, fill: '#dc2626' },
    { severity: 'High',     count:  87, fill: '#ea580c' },
    { severity: 'Medium',   count: 152, fill: '#ca8a04' },
    { severity: 'Low',      count: 230, fill: '#2563eb' },
    { severity: 'Info',     count: 411, fill: '#7c3aed' },
];

// ── Scan Activity (last 7 days) ───────────────────────────────────────────────

export const mockScanActivity = [
    { day: 'Mon', completed: 12, failed: 1 },
    { day: 'Tue', completed: 19, failed: 2 },
    { day: 'Wed', completed:  8, failed: 0 },
    { day: 'Thu', completed: 24, failed: 3 },
    { day: 'Fri', completed: 17, failed: 1 },
    { day: 'Sat', completed:  6, failed: 0 },
    { day: 'Sun', completed:  9, failed: 0 },
];

// ── Recent Activity ───────────────────────────────────────────────────────────

export const mockRecentActivity = [
    {
        id:     1,
        actor:  'Security Manager',
        action: 'Initiated External Attack Surface Assessment',
        target: 'portal.internal',
        time:   '2 min ago',
        type:   'scan',
    },
    {
        id:     2,
        actor:  'DevSecOps Engineer',
        action: 'Exported Infrastructure Security Report (PDF)',
        target: 'Infrastructure Audit Q3-2026',
        time:   '18 min ago',
        type:   'report',
    },
    {
        id:     3,
        actor:  'System',
        action: 'Critical finding detected — Broken Access Control',
        target: 'auth.internal',
        time:   '34 min ago',
        type:   'finding',
    },
    {
        id:     4,
        actor:  'Security Analyst',
        action: 'Registered new asset scope',
        target: '10.10.0.0/16',
        time:   '1 hr ago',
        type:   'asset',
    },
    {
        id:     5,
        actor:  'System',
        action: 'API Security Assessment completed — 28 findings',
        target: 'api.internal',
        time:   '2 hr ago',
        type:   'scan',
    },
    {
        id:     6,
        actor:  'SOC Operator',
        action: 'Triaged finding as accepted risk',
        target: 'Weak TLS Configuration — mail.internal',
        time:   '3 hr ago',
        type:   'finding',
    },
];

// ── Recent Scans ─────────────────────────────────────────────────────────────

export const mockRecentScans = [
    {
        id:       1,
        name:     'External Attack Surface Assessment',
        target:   'corp.internal',
        status:   'running',
        progress: 67,
        duration: '43 min',
        findings: 12,
        severity: 'critical',
    },
    {
        id:       2,
        name:     'API Security Assessment',
        target:   'api.internal',
        status:   'running',
        progress: 31,
        duration: '18 min',
        findings: 5,
        severity: 'high',
    },
    {
        id:       3,
        name:     'Internal Network Assessment',
        target:   '10.10.0.0/16',
        status:   'queued',
        progress: 0,
        duration: '—',
        findings: 0,
        severity: null,
    },
    {
        id:       4,
        name:     'Authentication Review',
        target:   'auth.internal',
        status:   'completed',
        progress: 100,
        duration: '1h 12m',
        findings: 28,
        severity: 'critical',
    },
    {
        id:       5,
        name:     'Container Security Assessment',
        target:   'vpn.internal',
        status:   'failed',
        progress: 43,
        duration: '21 min',
        findings: 0,
        severity: null,
    },
];

// ── Navigation ────────────────────────────────────────────────────────────────

export const mockNavItems = [
    { id: 'dashboard', label: 'Dashboard',    icon: 'LayoutDashboard', group: 'main'   },
    { id: 'assets',    label: 'Assets',       icon: 'Server',          group: 'main',   badge: '247'                      },
    { id: 'targets',   label: 'Targets',      icon: 'Crosshair',       group: 'main'                                      },
    { id: 'scans',     label: 'Scans',        icon: 'ScanLine',        group: 'main',   badge: '8',   badgeColor: 'violet' },
    { id: 'findings',  label: 'Findings',     icon: 'ShieldAlert',     group: 'main',   badge: '34',  badgeColor: 'red'    },
    { id: 'risks',     label: 'Risk Register', icon: 'Shield',         group: 'main'                                      },
    { id: 'reports',   label: 'Reports',      icon: 'FileText',        group: 'main'                                      },
    { id: 'compliance', label: 'Compliance',  icon: 'CheckSquare',     group: 'main'                                      },
    { id: 'ai',        label: 'AI Assistant', icon: 'Sparkles',        group: 'tools',  badge: 'NEW', badgeColor: 'brand'  },
    { id: 'users',     label: 'Users',        icon: 'Users',           group: 'admin'                                     },
    { id: 'settings',  label: 'Settings',     icon: 'Settings',        group: 'admin'                                     },
];

export const navGroups = [
    { id: 'main',  label: 'Workspace' },
    { id: 'tools', label: 'Tools'     },
    { id: 'admin', label: 'Admin'     },
];

// ── Notifications ─────────────────────────────────────────────────────────────

export const mockNotifications = [
    {
        id:     1,
        title:  'Critical finding detected',
        desc:   'Broken Access Control on auth.internal',
        time:   '2m',
        unread: true,
        type:   'finding',
    },
    {
        id:     2,
        title:  'Assessment completed',
        desc:   'API Security Assessment — 28 findings',
        time:   '1h',
        unread: true,
        type:   'scan',
    },
    {
        id:     3,
        title:  'Report ready for download',
        desc:   'Infrastructure Audit Q3-2026 finalized',
        time:   '3h',
        unread: false,
        type:   'report',
    },
    {
        id:     4,
        title:  'New analyst joined',
        desc:   'SOC Operator invitation accepted',
        time:   '1d',
        unread: false,
        type:   'user',
    },
];

// ── Current User (mock session) ───────────────────────────────────────────────

export const mockCurrentUser = {
    displayName: 'Administrator',
    role:        'Administrator',
    initials:    'AD',
    email:       'admin@trustnode.internal',
};
