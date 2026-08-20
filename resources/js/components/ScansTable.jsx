import React from 'react';
import { ExternalLink } from 'lucide-react';
import {
    Card, CardHeader, ViewAllLink,
    StatusBadge, ProgressBar, MonoChip,
} from './ui/primitives';
import { SeverityBadge } from './ui/primitives_findings';

const TABLE_HEADERS = ['Assessment', 'Target', 'Status', 'Progress', 'Findings', 'Duration', ''];

function ScanRow({ scan }) {
    return (
        <tr className="hover:bg-slate-50/70 transition-colors group">
            {/* Name */}
            <td className="px-5 py-3.5">
                <div className="flex flex-col gap-0.5">
                    <span className="text-sm font-medium text-slate-900 leading-snug">
                        {scan.name}
                    </span>
                    {scan.severity && (
                        <SeverityBadge severity={scan.severity} />
                    )}
                </div>
            </td>

            {/* Target */}
            <td className="px-5 py-3.5">
                <MonoChip>{scan.target}</MonoChip>
            </td>

            {/* Status */}
            <td className="px-5 py-3.5">
                <StatusBadge status={scan.status} />
            </td>

            {/* Progress */}
            <td className="px-5 py-3.5 min-w-[120px]">
                <div className="flex items-center gap-2.5">
                    <ProgressBar value={scan.progress} status={scan.status} className="flex-1" />
                    <span className="text-xs text-slate-500 tabular-nums shrink-0 w-8 text-right">
                        {scan.progress}%
                    </span>
                </div>
            </td>

            {/* Findings */}
            <td className="px-5 py-3.5">
                {scan.findings > 0 ? (
                    <span className="text-sm font-bold tabular-nums text-red-600">
                        {scan.findings}
                    </span>
                ) : (
                    <span className="text-sm text-slate-300 tabular-nums">—</span>
                )}
            </td>

            {/* Duration */}
            <td className="px-5 py-3.5">
                <span className="text-sm text-slate-500 tabular-nums">{scan.duration}</span>
            </td>

            {/* Action */}
            <td className="px-4 py-3.5">
                <button
                    aria-label={`View details for ${scan.name}`}
                    className="
                        opacity-0 group-hover:opacity-100 transition-all duration-150
                        p-1.5 rounded-md hover:bg-slate-100
                        focus:outline-none focus-visible:opacity-100 focus-visible:ring-2 focus-visible:ring-brand-500
                    "
                >
                    <ExternalLink size={13} className="text-slate-400" />
                </button>
            </td>
        </tr>
    );
}

export default function ScansTable({ data = [] }) {
    return (
        <Card padding={false}>
            <div className="px-5 py-4 border-b border-slate-100">
                <CardHeader
                    title="Recent Assessments"
                    subtitle="Active and historical security scans"
                    action={<ViewAllLink label="View all scans" />}
                />
            </div>

            <div className="overflow-x-auto">
                <table className="w-full" aria-label="Recent security assessments">
                    <thead>
                        <tr className="border-b border-slate-100 bg-slate-50/60">
                            {TABLE_HEADERS.map((h, i) => (
                                <th
                                    key={h || i}
                                    scope="col"
                                    className="px-5 py-2.5 text-left text-[10px] font-semibold text-slate-500 uppercase tracking-widest whitespace-nowrap"
                                >
                                    {h}
                                </th>
                            ))}
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-slate-50">
                        {data.map((scan) => (
                            <ScanRow key={scan.id || Math.random()} scan={scan} />
                        ))}
                    </tbody>
                </table>
            </div>
        </Card>
    );
}
