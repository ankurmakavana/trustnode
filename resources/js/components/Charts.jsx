import React from 'react';
import {
    BarChart, Bar, XAxis, YAxis, Tooltip, ResponsiveContainer,
    PieChart, Pie, Cell, CartesianGrid, LineChart, Line, Legend
} from 'recharts';
import { Card, CardHeader } from './ui/primitives';
import { TrendingDown, TrendingUp, Minus, ShieldCheck, AlertCircle, RefreshCw, CheckCircle2, AlertTriangle } from 'lucide-react';

/* ── Shared custom tooltip ───────────────────────────────── */
function ChartTooltip({ active, payload, label }) {
    if (!active || !payload?.length) return null;
    return (
        <div className="bg-white rounded-lg border border-slate-200 shadow-lg px-3 py-2.5 text-xs min-w-[140px] z-50">
            {label && <p className="font-semibold text-slate-700 mb-1.5 border-b border-slate-100 pb-1">{label}</p>}
            {payload.map((p) => (
                <div key={p.dataKey || p.name} className="flex items-center justify-between gap-3 my-0.5">
                    <span className="flex items-center gap-1.5 text-slate-500">
                        <span className="w-2 h-2 rounded-full shrink-0" style={{ background: p.color || p.fill || p.stroke }} />
                        {p.name || p.dataKey}
                    </span>
                    <span className="font-semibold text-slate-900 tabular-nums">{p.value}</span>
                </div>
            ))}
        </div>
    );
}

/* ── Severity Donut ──────────────────────────────────────── */
export function SeverityChart({ data = [] }) {
    const total = data.reduce((s, d) => s + d.count, 0);

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
                                data={data}
                                cx="50%" cy="50%"
                                innerRadius={46} outerRadius={68}
                                dataKey="count"
                                strokeWidth={2}
                                stroke="#ffffff"
                                paddingAngle={2}
                            >
                                {data.map((entry) => (
                                    <Cell key={entry.severity} fill={entry.fill} />
                                ))}
                            </Pie>
                            <Tooltip content={<ChartTooltip />} />
                        </PieChart>
                    </ResponsiveContainer>
                </div>

                {/* Legend */}
                <div className="flex flex-col gap-2 flex-1 min-w-0">
                    {data.map((d) => {
                        const pct = total > 0 ? Math.round((d.count / total) * 100) : 0;
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
            <div className="h-2 rounded-full overflow-hidden flex bg-slate-100">
                {data.map((d) => (
                    <div
                        key={d.severity}
                        title={`${d.severity}: ${total > 0 ? Math.round((d.count / total) * 100) : 0}%`}
                        className="h-full transition-all duration-500"
                        style={{
                            width:      `${total > 0 ? (d.count / total) * 100 : 0}%`,
                            background: d.fill,
                        }}
                    />
                ))}
            </div>
        </Card>
    );
}

/* ── Scan Activity ───────────────────────────────────────── */
export function ScanActivityChart({ data = [] }) {
    const totalScans = data.reduce((s, d) => s + (d.completed || 0) + (d.failed || 0), 0);
    const totalFailed = data.reduce((s, d) => s + (d.failed || 0), 0);

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
                    data={data}
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

/* ── Security Posture Trend Chart ────────────────────────── */
export function PostureTrendChart({ data = [], assessment = 'initial_baseline' }) {
    const formattedData = data.map((d, index) => {
        const label = d.completed_at
            ? new Date(d.completed_at).toLocaleDateString(undefined, { month: 'short', day: 'numeric' })
            : `#${d.scan_id}`;
        return {
            ...d,
            displayLabel: label,
        };
    });

    const getAssessmentBadge = () => {
        switch (assessment) {
            case 'improving':
                return (
                    <span className="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200">
                        <TrendingDown size={12} /> Improving
                    </span>
                );
            case 'worsening':
                return (
                    <span className="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-semibold bg-rose-50 text-rose-700 border border-rose-200">
                        <TrendingUp size={12} /> Worsening
                    </span>
                );
            case 'unchanged':
                return (
                    <span className="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-semibold bg-slate-50 text-slate-700 border border-slate-200">
                        <Minus size={12} /> Unchanged
                    </span>
                );
            case 'initial_baseline':
            default:
                return (
                    <span className="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-semibold bg-blue-50 text-blue-700 border border-blue-200">
                        <ShieldCheck size={12} /> Baseline
                    </span>
                );
        }
    };

    return (
        <Card padding={false} className="p-5 flex flex-col gap-4">
            <CardHeader
                title="Security Posture Trend"
                subtitle="Historical findings across completed scans"
                action={getAssessmentBadge()}
            />

            {formattedData.length === 0 ? (
                <div className="flex flex-col items-center justify-center h-44 text-slate-400 text-xs gap-1">
                    <ShieldCheck size={24} className="text-slate-300 mb-1" />
                    <span>No historical completed scans available.</span>
                </div>
            ) : (
                <>
                    <ResponsiveContainer width="100%" height={176}>
                        <LineChart
                            data={formattedData}
                            margin={{ top: 10, right: 10, left: -20, bottom: 0 }}
                        >
                            <CartesianGrid vertical={false} stroke="#f1f5f9" />
                            <XAxis
                                dataKey="displayLabel"
                                axisLine={false}
                                tickLine={false}
                                tick={{ fontSize: 11, fill: '#94a3b8' }}
                            />
                            <YAxis
                                axisLine={false}
                                tickLine={false}
                                tick={{ fontSize: 11, fill: '#94a3b8' }}
                                width={32}
                                allowDecimals={false}
                            />
                            <Tooltip content={<ChartTooltip />} />
                            <Line
                                type="monotone"
                                dataKey="total_open"
                                name="Total Open"
                                stroke="#4e6ef7"
                                strokeWidth={2}
                                dot={{ r: 3, fill: '#4e6ef7' }}
                                activeDot={{ r: 5 }}
                            />
                            <Line
                                type="monotone"
                                dataKey="critical"
                                name="Critical"
                                stroke="#e11d48"
                                strokeWidth={1.5}
                                strokeDasharray="3 3"
                                dot={{ r: 2.5, fill: '#e11d48' }}
                            />
                            <Line
                                type="monotone"
                                dataKey="high"
                                name="High"
                                stroke="#f97316"
                                strokeWidth={1.5}
                                strokeDasharray="3 3"
                                dot={{ r: 2.5, fill: '#f97316' }}
                            />
                        </LineChart>
                    </ResponsiveContainer>

                    {/* Legend */}
                    <div className="flex items-center justify-between text-xs text-slate-500 border-t border-slate-100 pt-2.5">
                        <div className="flex items-center gap-4">
                            <span className="flex items-center gap-1.5">
                                <span className="w-2.5 h-1 rounded-sm bg-brand-500" />
                                Total Open
                            </span>
                            <span className="flex items-center gap-1.5">
                                <span className="w-2.5 h-1 rounded-sm bg-rose-600" />
                                Critical
                            </span>
                            <span className="flex items-center gap-1.5">
                                <span className="w-2.5 h-1 rounded-sm bg-orange-500" />
                                High
                            </span>
                        </div>
                        <span className="text-[11px] text-slate-400">
                            {formattedData.length} {formattedData.length === 1 ? 'scan' : 'scans'} evaluated
                        </span>
                    </div>
                </>
            )}
        </Card>
    );
}

/* ── Lifecycle Summary Widget ────────────────────────────── */
export function LifecycleSummaryWidget({ summary = { new: 0, recurring: 0, resolved: 0, regression: 0 } }) {
    return (
        <Card padding={false} className="p-5 flex flex-col gap-3.5">
            <CardHeader
                title="Finding Lifecycle Summary"
                subtitle="Persistent finding states across target inventory"
            />

            <div className="grid grid-cols-2 sm:grid-cols-4 gap-3">
                {/* NEW */}
                <div className="bg-blue-50/60 border border-blue-100 rounded-xl p-3 flex flex-col justify-between">
                    <div className="flex items-center justify-between">
                        <span className="text-[11px] font-bold text-blue-700 uppercase tracking-wider">New</span>
                        <AlertCircle size={14} className="text-blue-500" />
                    </div>
                    <div className="mt-2">
                        <span className="text-xl font-bold text-blue-950 tabular-nums">
                            {summary.new || 0}
                        </span>
                        <span className="block text-[10px] text-blue-600/80 mt-0.5">First occurrence</span>
                    </div>
                </div>

                {/* RECURRING */}
                <div className="bg-amber-50/60 border border-amber-100 rounded-xl p-3 flex flex-col justify-between">
                    <div className="flex items-center justify-between">
                        <span className="text-[11px] font-bold text-amber-700 uppercase tracking-wider">Recurring</span>
                        <RefreshCw size={14} className="text-amber-500" />
                    </div>
                    <div className="mt-2">
                        <span className="text-xl font-bold text-amber-950 tabular-nums">
                            {summary.recurring || 0}
                        </span>
                        <span className="block text-[10px] text-amber-600/80 mt-0.5">Unresolved across scans</span>
                    </div>
                </div>

                {/* RESOLVED */}
                <div className="bg-emerald-50/60 border border-emerald-100 rounded-xl p-3 flex flex-col justify-between">
                    <div className="flex items-center justify-between">
                        <span className="text-[11px] font-bold text-emerald-700 uppercase tracking-wider">Resolved</span>
                        <CheckCircle2 size={14} className="text-emerald-500" />
                    </div>
                    <div className="mt-2">
                        <span className="text-xl font-bold text-emerald-950 tabular-nums">
                            {summary.resolved || 0}
                        </span>
                        <span className="block text-[10px] text-emerald-600/80 mt-0.5">Remediated findings</span>
                    </div>
                </div>

                {/* REGRESSION */}
                <div className="bg-rose-50/60 border border-rose-100 rounded-xl p-3 flex flex-col justify-between">
                    <div className="flex items-center justify-between">
                        <span className="text-[11px] font-bold text-rose-700 uppercase tracking-wider">Regression</span>
                        <AlertTriangle size={14} className="text-rose-500" />
                    </div>
                    <div className="mt-2">
                        <span className="text-xl font-bold text-rose-950 tabular-nums">
                            {summary.regression || 0}
                        </span>
                        <span className="block text-[10px] text-rose-600/80 mt-0.5">Reopened after fix</span>
                    </div>
                </div>
            </div>
        </Card>
    );
}
