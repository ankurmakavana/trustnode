import React from 'react';
import {
    BarChart, Bar, XAxis, YAxis, Tooltip, ResponsiveContainer,
    PieChart, Pie, Cell, CartesianGrid,
} from 'recharts';
import { mockSeverityData, mockScanActivity } from '../data/mockData';
import { Card, CardHeader } from './ui/primitives';

/* ── Shared custom tooltip ───────────────────────────────── */
function ChartTooltip({ active, payload, label }) {
    if (!active || !payload?.length) return null;
    return (
        <div className="bg-white rounded-lg border border-slate-200 shadow-lg px-3 py-2.5 text-xs min-w-[120px]">
            {label && <p className="font-semibold text-slate-600 mb-1.5">{label}</p>}
            {payload.map((p) => (
                <div key={p.dataKey || p.name} className="flex items-center justify-between gap-3">
                    <span className="flex items-center gap-1.5 text-slate-500">
                        <span className="w-2 h-2 rounded-full shrink-0" style={{ background: p.color || p.fill }} />
                        {p.name || p.dataKey}
                    </span>
                    <span className="font-semibold text-slate-900 tabular-nums">{p.value}</span>
                </div>
            ))}
        </div>
    );
}

/* ── Severity Donut ──────────────────────────────────────── */
export function SeverityChart() {
    const total = mockSeverityData.reduce((s, d) => s + d.count, 0);

    return (
        <Card padding={false} className="p-5 flex flex-col gap-4">
            <CardHeader
                title="Vulnerability Severity"
                subtitle={`${total.toLocaleString()} total findings`}
            />

            <div className="flex items-center gap-5">
                {/* Donut */}
                <div className="shrink-0">
                    <ResponsiveContainer width={148} height={148}>
                        <PieChart>
                            <Pie
                                data={mockSeverityData}
                                cx="50%" cy="50%"
                                innerRadius={46} outerRadius={68}
                                dataKey="count"
                                strokeWidth={2}
                                stroke="#ffffff"
                                paddingAngle={2}
                            >
                                {mockSeverityData.map((entry) => (
                                    <Cell key={entry.severity} fill={entry.fill} />
                                ))}
                            </Pie>
                            <Tooltip content={<ChartTooltip />} />
                        </PieChart>
                    </ResponsiveContainer>
                </div>

                {/* Legend */}
                <div className="flex flex-col gap-2 flex-1 min-w-0">
                    {mockSeverityData.map((d) => {
                        const pct = Math.round((d.count / total) * 100);
                        return (
                            <div key={d.severity} className="grid grid-cols-[auto_1fr_auto_auto] items-center gap-2">
                                <span
                                    className="w-2 h-2 rounded-full shrink-0"
                                    style={{ background: d.fill }}
                                />
                                <span className="text-xs text-slate-600 truncate">{d.severity}</span>
                                <span className="text-xs font-semibold text-slate-900 tabular-nums text-right">{d.count}</span>
                                <span className="text-[10px] text-slate-400 tabular-nums w-7 text-right">{pct}%</span>
                            </div>
                        );
                    })}
                </div>
            </div>

            {/* Total bar */}
            <div className="h-2 rounded-full overflow-hidden flex">
                {mockSeverityData.map((d) => (
                    <div
                        key={d.severity}
                        title={`${d.severity}: ${Math.round((d.count / total) * 100)}%`}
                        className="h-full transition-all duration-500"
                        style={{
                            width:      `${(d.count / total) * 100}%`,
                            background: d.fill,
                        }}
                    />
                ))}
            </div>
        </Card>
    );
}

/* ── Scan Activity ───────────────────────────────────────── */
export function ScanActivityChart() {
    const totalScans = mockScanActivity.reduce((s, d) => s + d.completed + d.failed, 0);
    const totalFailed = mockScanActivity.reduce((s, d) => s + d.failed, 0);

    return (
        <Card padding={false} className="p-5 flex flex-col gap-4">
            <CardHeader
                title="Scan Activity"
                subtitle="Last 7 days"
                action={
                    <div className="flex items-center gap-3 text-xs text-slate-500">
                        <span>
                            <span className="font-semibold text-slate-900 tabular-nums">{totalScans}</span> total
                        </span>
                        <span>
                            <span className="font-semibold text-red-600 tabular-nums">{totalFailed}</span> failed
                        </span>
                    </div>
                }
            />

            <ResponsiveContainer width="100%" height={176}>
                <BarChart
                    data={mockScanActivity}
                    barGap={2}
                    barCategoryGap="40%"
                    margin={{ top: 0, right: 0, left: -20, bottom: 0 }}
                >
                    <CartesianGrid vertical={false} stroke="#f1f5f9" />
                    <XAxis
                        dataKey="day"
                        axisLine={false}
                        tickLine={false}
                        tick={{ fontSize: 11, fill: '#94a3b8' }}
                    />
                    <YAxis
                        axisLine={false}
                        tickLine={false}
                        tick={{ fontSize: 11, fill: '#94a3b8' }}
                        width={32}
                    />
                    <Tooltip content={<ChartTooltip />} cursor={{ fill: '#f8fafc', radius: 4 }} />
                    <Bar dataKey="completed" name="Completed" fill="#4e6ef7" radius={[3, 3, 0, 0]} />
                    <Bar dataKey="failed"    name="Failed"    fill="#fca5a5" radius={[3, 3, 0, 0]} />
                </BarChart>
            </ResponsiveContainer>

            {/* Inline legend */}
            <div className="flex items-center gap-4 text-xs text-slate-500">
                <span className="flex items-center gap-1.5">
                    <span className="w-2.5 h-2.5 rounded-sm bg-brand-500" />
                    Completed
                </span>
                <span className="flex items-center gap-1.5">
                    <span className="w-2.5 h-2.5 rounded-sm bg-red-300" />
                    Failed
                </span>
            </div>
        </Card>
    );
}
