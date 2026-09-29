import React from 'react';
import { ShieldCheck, CheckCircle2, XCircle, Lock, ShieldAlert, Cpu } from 'lucide-react';
import { Card, CardHeader } from '../components/ui/primitives';

export default function PermissionsPage() {
    const allowedCapabilities = [
        { title: 'Observe Security State', description: 'Monitor configured environment files and system parameters for unauthorized modifications.' },
        { title: 'Execute TrustNode Scanners', description: 'Run internal security verification scanners (e.g. SecretScanner) against local targets.' },
        { title: 'Report Security Findings', description: 'Persist detected vulnerabilities and telemetry to the central TrustNode platform.' },
    ];

    const restrictedCapabilities = [
        { title: 'Write Files', description: 'Agent cannot modify or write files on disk.' },
        { title: 'Modify Source Code', description: 'Agent cannot rewrite codebase files.' },
        { title: 'Delete Files', description: 'Agent has zero file deletion permissions.' },
        { title: 'Install Software', description: 'Agent cannot install packages or run package managers.' },
        { title: 'Execute Arbitrary Commands', description: 'Agent cannot run shell commands outside registered handlers.' },
        { title: 'Commit Changes', description: 'Agent cannot perform git commit operations.' },
        { title: 'Push Changes', description: 'Agent cannot push commits to remote repositories.' },
    ];

    return (
        <div className="space-y-6">
            <div className="bg-white border border-slate-200 rounded-xl p-5 shadow-sm flex flex-col md:flex-row items-start md:items-center justify-between gap-4">
                <div className="flex items-center gap-3">
                    <div className="w-10 h-10 rounded-full bg-emerald-50 border border-emerald-200 flex items-center justify-center text-emerald-600 shrink-0">
                        <ShieldCheck size={20} />
                    </div>
                    <div>
                        <h1 className="text-base font-bold text-slate-900">Agent Capability Policy</h1>
                        <p className="text-xs text-slate-500 mt-0.5">Defines the read-only operational boundary enforced by the TrustNode Agent Security Controller.</p>
                    </div>
                </div>
                <span className="inline-flex items-center gap-1.5 px-3 py-1 bg-slate-100 text-slate-700 font-mono text-xs font-semibold rounded-lg border border-slate-200">
                    <Lock size={12} className="text-slate-500" />
                    Read-Only Runtime Mode
                </span>
            </div>

            <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                {/* Allowed Capabilities */}
                <Card padding={false}>
                    <CardHeader
                        title="Allowed Capabilities"
                        subtitle="Authorized operational tasks"
                        icon={<CheckCircle2 className="w-5 h-5 text-emerald-600" />}
                    />
                    <div className="p-5 divide-y divide-slate-100">
                        {allowedCapabilities.map((cap, i) => (
                            <div key={i} className="py-3.5 first:pt-0 last:pb-0 flex items-start gap-3">
                                <CheckCircle2 size={16} className="text-emerald-600 shrink-0 mt-0.5" />
                                <div>
                                    <h4 className="text-xs font-bold text-slate-800">{cap.title}</h4>
                                    <p className="text-[11px] text-slate-500 mt-0.5 leading-relaxed">{cap.description}</p>
                                </div>
                            </div>
                        ))}
                    </div>
                </Card>

                {/* Restricted Capabilities */}
                <Card padding={false}>
                    <CardHeader
                        title="Restricted Capabilities"
                        subtitle="Enforced security boundaries"
                        icon={<XCircle className="w-5 h-5 text-rose-500" />}
                    />
                    <div className="p-5 divide-y divide-slate-100">
                        {restrictedCapabilities.map((cap, i) => (
                            <div key={i} className="py-2.5 first:pt-0 last:pb-0 flex items-start gap-3">
                                <XCircle size={15} className="text-rose-500 shrink-0 mt-0.5" />
                                <div>
                                    <h4 className="text-xs font-bold text-slate-800">{cap.title}</h4>
                                    <p className="text-[11px] text-slate-500 mt-0.5 leading-relaxed">{cap.description}</p>
                                </div>
                            </div>
                        ))}
                    </div>
                </Card>
            </div>

            <div className="bg-slate-50 border border-slate-200 rounded-xl p-4 text-xs text-slate-600 flex items-start gap-3">
                <ShieldAlert size={16} className="text-slate-400 shrink-0 mt-0.5" />
                <div>
                    <span className="font-semibold text-slate-800 block">Security Control Visibility</span>
                    <p className="mt-0.5 leading-relaxed">
                        These capabilities are enforced by the Agent execution policy. Policy visibility only — enforcement is managed directly by the Agent runtime security controller.
                    </p>
                </div>
            </div>
        </div>
    );
}
