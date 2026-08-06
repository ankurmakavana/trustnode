import React, { useState, useEffect } from 'react';
import { 
    Shield, AlertTriangle, CheckCircle2, ChevronRight, SlidersHorizontal, Loader2, 
    Activity, Server, Crosshair, BarChart3, PieChart, Info, HelpCircle
} from 'lucide-react';
import axios from 'axios';

export default function ComplianceDashboardPage({ onNavigateToDetail }) {
    const [frameworks, setFrameworks] = useState([]);
    const [overallCompliance, setOverallCompliance] = useState(100.00);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState(null);

    const fetchComplianceData = async () => {
        setLoading(true);
        setError(null);
        try {
            const response = await axios.get('/api/compliance/stats');
            setOverallCompliance(response.data.overall_compliance);
            setFrameworks(response.data.frameworks || []);
        } catch (err) {
            console.error('Failed to load compliance matrix:', err);
            setError('Failed to fetch corporate compliance statistics.');
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => {
        fetchComplianceData();
    }, []);

    const getCoverageColor = (cov) => {
        if (cov >= 90) return 'text-emerald-600 bg-emerald-50 border-emerald-200';
        if (cov >= 70) return 'text-amber-600 bg-amber-50 border-amber-200';
        return 'text-rose-600 bg-rose-50 border-rose-200';
    };

    // Get specific framework coverage helper for KPI cards
    const getFwCoverage = (code) => {
        const fw = frameworks.find(f => f.code === code);
        return fw ? `${fw.coverage}%` : '100%';
    };

    if (loading) {
        return (
            <div className="bg-white border border-slate-200 rounded-xl p-12 shadow-sm flex flex-col items-center justify-center gap-3 min-h-[40vh]">
                <Loader2 className="animate-spin text-brand-600" size={24} />
                <span className="text-xs font-semibold text-slate-500">Querying corporate compliance dashboard metrics...</span>
            </div>
        );
    }

    return (
        <div className="space-y-6">
            {/* Header */}
            <div>
                <h1 className="text-xl font-bold text-slate-900 tracking-tight flex items-center gap-2">
                    <Shield className="text-brand-600" size={22} />
                    Regulatory Compliance Management
                </h1>
                <p className="text-xs text-slate-500 mt-0.5">
                    Map active platform vulnerability assessments to leading security standards and criteria.
                </p>
            </div>

            {/* KPI Cards row */}
            <div className="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-7 gap-3.5">
                {/* Overall Compliance score gauge */}
                <div className="bg-white border border-slate-200 rounded-xl p-4 shadow-sm flex flex-col justify-between col-span-2 sm:col-span-2">
                    <span className="text-[10px] uppercase font-bold text-slate-400 tracking-wider">Overall Compliance Score</span>
                    <div className="flex items-baseline gap-1.5 mt-2">
                        <span className="text-3xl font-extrabold text-slate-800">{overallCompliance}%</span>
                        <span className="text-xs font-bold text-emerald-600 bg-emerald-50 px-1.5 py-0.5 rounded border border-emerald-200">Active</span>
                    </div>
                </div>

                {[
                    { label: 'OWASP Top 10', value: getFwCoverage('OWASP'), border: 'border-l-indigo-500 text-indigo-700 bg-indigo-50/10' },
                    { label: 'ISO 27001', value: getFwCoverage('ISO'), border: 'border-l-sky-500 text-sky-700 bg-sky-50/10' },
                    { label: 'PCI DSS v4.0', value: getFwCoverage('PCI'), border: 'border-l-emerald-500 text-emerald-700 bg-emerald-50/10' },
                    { label: 'NIST CSF v2.0', value: getFwCoverage('NIST'), border: 'border-l-amber-500 text-amber-800 bg-amber-50/10' },
                    { label: 'SOC 2 Coverage', value: getFwCoverage('SOC2'), border: 'border-l-purple-500 text-purple-700 bg-purple-50/10' },
                ].map((card, i) => (
                    <div key={i} className={`bg-white border border-slate-200 border-l-4 ${card.border} rounded-xl p-4.5 shadow-sm flex flex-col justify-between`}>
                        <span className="text-[9.5px] uppercase font-bold text-slate-450 tracking-wider leading-tight">{card.label}</span>
                        <span className="text-lg font-bold mt-1.5">{card.value}</span>
                    </div>
                ))}
            </div>

            {/* Compliance matrix grid framework list cards */}
            {error && (
                <div className="bg-rose-50 border border-rose-200 text-rose-800 text-xs p-4 rounded-xl flex items-center gap-2 font-medium">
                    <AlertTriangle size={15} className="text-rose-505" />
                    <span>{error}</span>
                </div>
            )}

            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                {frameworks.map((fw) => (
                    <div 
                        key={fw.code} 
                        onClick={() => onNavigateToDetail(fw.code)}
                        className="bg-white border border-slate-200 hover:border-brand-300 rounded-2xl p-5.5 shadow-sm hover:shadow-md cursor-pointer transition-all flex flex-col justify-between gap-4 group"
                    >
                        <div className="space-y-2">
                            <div className="flex items-start justify-between gap-3">
                                <div className="space-y-0.5">
                                    <span className="text-[10px] font-bold text-slate-400 font-mono tracking-widest uppercase">{fw.code}</span>
                                    <h3 className="text-sm font-bold text-slate-800 group-hover:text-brand-650 group-hover:text-brand-600 transition-colors">{fw.name}</h3>
                                </div>
                                <span className={`px-2 py-0.5 rounded text-[10px] font-bold border ${getCoverageColor(fw.coverage)}`}>
                                    {fw.coverage}%
                                </span>
                            </div>
                            <p className="text-xs text-slate-500 leading-relaxed line-clamp-2">
                                {fw.description || 'No description configured.'}
                            </p>
                        </div>

                        {/* Controls mapping statistics breakdown */}
                        <div className="grid grid-cols-3 gap-2 border-t border-slate-100 pt-3.5 text-center text-[10.5px]">
                            <div className="space-y-0.5">
                                <span className="text-slate-400 font-bold block">Passed</span>
                                <span className="font-bold text-emerald-600">{fw.passed}</span>
                            </div>
                            <div className="space-y-0.5">
                                <span className="text-slate-400 font-bold block">Failed</span>
                                <span className="font-bold text-rose-600">{fw.failed}</span>
                            </div>
                            <div className="space-y-0.5">
                                <span className="text-slate-400 font-bold block">Controls</span>
                                <span className="font-semibold text-slate-700">{fw.controls_count}</span>
                            </div>
                        </div>
                    </div>
                ))}
            </div>
        </div>
    );
}