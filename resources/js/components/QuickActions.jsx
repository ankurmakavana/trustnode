import React from 'react';
import { ScanLine, Server, FileText, ArrowRight, Plus } from 'lucide-react';
import { Card, CardHeader } from './ui/primitives';

const ACTIONS = [
    {
        id:          'scan',
        label:       'Launch Assessment',
        desc:        'Start a new vulnerability or penetration test',
        icon:        ScanLine,
        accent:      'brand',
        border:      'border-brand-200',
        hoverBorder: 'hover:border-brand-400',
        hoverBg:     'hover:bg-brand-50/60',
        iconBg:      'bg-brand-100',
        iconText:    'text-brand-600',
        arrowColor:  'group-hover:text-brand-500',
    },
    {
        id:          'asset',
        label:       'Register Asset',
        desc:        'Add an IP, domain, or network range to scope',
        icon:        Server,
        accent:      'slate',
        border:      'border-slate-200',
        hoverBorder: 'hover:border-slate-300',
        hoverBg:     'hover:bg-slate-50',
        iconBg:      'bg-slate-100',
        iconText:    'text-slate-600',
        arrowColor:  'group-hover:text-slate-500',
    },
    {
        id:          'report',
        label:       'Export Report',
        desc:        'Compile findings into a PDF or CSV report',
        icon:        FileText,
        accent:      'emerald',
        border:      'border-emerald-200',
        hoverBorder: 'hover:border-emerald-400',
        hoverBg:     'hover:bg-emerald-50/60',
        iconBg:      'bg-emerald-100',
        iconText:    'text-emerald-600',
        arrowColor:  'group-hover:text-emerald-500',
    },
];

function ActionCard({ action }) {
    const Icon = action.icon;

    return (
        <button
            aria-label={action.label}
            className={`
                w-full flex items-center gap-3.5 p-3.5 rounded-xl border
                text-left transition-all duration-150 group
                ${action.border} ${action.hoverBorder} ${action.hoverBg}
                focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500
            `}
        >
            <div className={`w-9 h-9 rounded-lg flex items-center justify-center shrink-0 ${action.iconBg}`}>
                <Icon size={16} className={action.iconText} strokeWidth={2} />
            </div>
            <div className="flex-1 min-w-0">
                <p className="text-sm font-semibold text-slate-900 leading-snug">{action.label}</p>
                <p className="text-xs text-slate-500 leading-snug truncate">{action.desc}</p>
            </div>
            <ArrowRight
                size={14}
                className={`text-slate-300 ${action.arrowColor} transition-all duration-150 group-hover:translate-x-0.5 shrink-0`}
            />
        </button>
    );
}

export default function QuickActions() {
    return (
        <Card padding={false} className="p-5 flex flex-col gap-4">
            <CardHeader
                title="Quick Actions"
                subtitle="Common VAPT workflows"
                action={
                    <button className="flex items-center gap-1 text-xs font-medium text-brand-600 hover:text-brand-700 transition-colors">
                        <Plus size={12} /> New
                    </button>
                }
            />
            <div className="flex flex-col gap-2.5">
                {ACTIONS.map((action) => (
                    <ActionCard key={action.id} action={action} />
                ))}
            </div>
        </Card>
    );
}
