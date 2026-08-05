import React from 'react';
import {
    Server, ScanLine, ShieldAlert, FileText,
    TrendingUp, TrendingDown, Minus,
} from 'lucide-react';
import { Card } from './ui/primitives';

const colorConfig = {
    blue:    { icon: 'bg-blue-100',    text: 'text-blue-600'    },
    violet:  { icon: 'bg-violet-100',  text: 'text-violet-600'  },
    red:     { icon: 'bg-red-100',     text: 'text-red-600'     },
    emerald: { icon: 'bg-emerald-100', text: 'text-emerald-600' },
};

const iconMap = { Server, ScanLine, ShieldAlert, FileText };

const trendConfig = {
    up:      { Icon: TrendingUp,   color: 'text-emerald-600' },
    down:    { Icon: TrendingDown, color: 'text-red-500'     },
    neutral: { Icon: Minus,        color: 'text-slate-400'   },
};

export default function StatCard({ stat }) {
    const c     = colorConfig[stat.color] || colorConfig.blue;
    const trend = trendConfig[stat.trend] || trendConfig.neutral;
    const Icon  = iconMap[stat.icon] || Server;

    return (
        <Card hover className="flex flex-col gap-4">
            {/* Top row: label + icon */}
            <div className="flex items-start justify-between gap-3">
                <div>
                    <p className="text-xs font-medium text-slate-500 uppercase tracking-widest leading-none">
                        {stat.label}
                    </p>
                    {stat.description && (
                        <p className="text-[10px] text-slate-400 mt-1 leading-tight">{stat.description}</p>
                    )}
                </div>
                <div className={`w-9 h-9 rounded-lg ${c.icon} flex items-center justify-center shrink-0`}>
                    <Icon size={16} className={c.text} strokeWidth={2} />
                </div>
            </div>

            {/* Value */}
            <p className="text-[2rem] font-bold text-slate-900 tabular-nums leading-none">
                {stat.value}
            </p>

            {/* Delta */}
            <div className="flex items-center gap-1.5 pt-1 border-t border-slate-100">
                <trend.Icon size={13} className={trend.color} strokeWidth={2.5} />
                <span className={`text-xs font-semibold tabular-nums ${trend.color}`}>
                    {stat.delta}
                </span>
                {stat.deltaLabel && (
                    <span className="text-xs text-slate-400">{stat.deltaLabel}</span>
                )}
            </div>
        </Card>
    );
}
