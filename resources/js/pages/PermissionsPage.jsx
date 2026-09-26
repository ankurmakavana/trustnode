import React from 'react';
import { Shield, CheckCircle2, X, Loader2 } from 'lucide-react';
import { Card, CardHeader } from '../components/ui/primitives';

export default function PermissionsPage() {
    return (
        <Card padding={false}>
            <CardHeader title="Agent Permissions" subtitle="What the agent is allowed to do" />
            <div className="p-5 space-y-6">
                <section>
                    <h3 className="text-sm font-semibold text-slate-900 mb-3">Allowed</h3>
                    <ul className="space-y-2 text-sm">
                        <li className="flex items-center gap-2">
                            <CheckCircle2 size={14} className="text-emerald-600" />
                            Observe security-relevant environment
                        </li>
                        <li className="flex items-center gap-2">
                            <CheckCircle2 size={14} className="text-emerald-600" />
                            Run TrustNode scanners
                        </li>
                        <li className="flex items-center gap-2">
                            <CheckCircle2 size={14} className="text-emerald-600" />
                            Report findings
                        </li>
                    </ul>
                </section>
                <section>
                    <h3 className="text-sm font-semibold text-slate-900 mb-3">Not Allowed</h3>
                    <ul className="space-y-2 text-sm">
                        <li className="flex items-center gap-2">
                            <X size={14} className="text-red-600" />
                            Write files
                        </li>
                        <li className="flex items-center gap-2">
                            <X size={14} className="text-red-600" />
                            Modify source code
                        </li>
                        <li className="flex items-center gap-2">
                            <X size={14} className="text-red-600" />
                            Delete files
                        </li>
                        <li className="flex items-center gap-2">
                            <X size={14} className="text-red-600" />
                            Install software
                        </li>
                        <li className="flex items-center gap-2">
                            <X size={14} className="text-red-600" />
                            Execute arbitrary commands
                        </li>
                        <li className="flex items-center gap-2">
                            <X size={14} className="text-red-600" />
                            Commit changes
                        </li>
                        <li className="flex items-center gap-2">
                            <X size={14} className="text-red-600" />
                            Push changes
                        </li>
                    </ul>
                </section>
                <div className="pt-4 border-t border-slate-100">
                    <p className="text-xs text-slate-500">
                        This is informational. The agent operates in READ-ONLY mode.
                        Permission management requires backend API implementation.
                    </p>
                </div>
            </div>
        </Card>
    );
}