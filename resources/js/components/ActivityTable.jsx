import React from 'react';
import { ScanLine, Server, FileText, ShieldAlert } from 'lucide-react';
import { Card, CardHeader, ViewAllLink, MonoChip } from './ui/primitives';

const typeConfig = {
    scan:    { Icon: ScanLine,    color: 'bg-brand-100   text-brand-600'   },
    report:  { Icon: FileText,    color: 'bg-emerald-100 text-emerald-600' },
    finding: { Icon: ShieldAlert, color: 'bg-red-100     text-red-600'     },
    asset:   { Icon: Server,      color: 'bg-slate-100   text-slate-600'   },
};

function ActivityRow({ item }) {
    const cfg = typeConfig[item.type] || typeConfig.asset;
    const { Icon, color } = cfg;

    return (
        <div className="flex items-start gap-3 px-5 py-3.5 hover:bg-slate-50/70 transition-colors group">
            {/* Type icon */}
            <div className={`w-8 h-8 rounded-lg flex items-center justify-center shrink-0 mt-0.5 ${color}`}>
                <Icon size={14} strokeWidth={2} />
            </div>

            {/* Content */}
            <div className="flex-1 min-w-0">
                <div className="flex flex-wrap items-baseline gap-x-1.5">
                    <span className="text-xs font-semibold text-slate-700">{item.actor}</span>
                    <span className="text-xs text-slate-500 leading-snug">{item.action}</span>
                </div>
                <MonoChip>{item.target}</MonoChip>
            </div>

            {/* Time */}
            <time
                dateTime={item.time}
                className="text-[10px] text-slate-400 shrink-0 tabular-nums mt-0.5 whitespace-nowrap"
            >
                {item.time}
            </time>
        </div>
    );
}

export default function ActivityTable({ data = [] }) {
    return (
        <Card padding={false}>
            <div className="px-5 py-4 border-b border-slate-100">
                <CardHeader
                    title="Recent Activity"
                    subtitle="Latest platform events"
                    action={<ViewAllLink />}
                />
            </div>
            <div
                className="divide-y divide-slate-50"
                role="list"
                aria-label="Recent activity"
            >
                {data.map((item) => (
                    <div key={item.id || Math.random()} role="listitem">
                        <ActivityRow item={item} />
                    </div>
                ))}
            </div>
        </Card>
    );
}
