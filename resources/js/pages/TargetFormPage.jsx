import React, { useState, useEffect } from 'react';
import { Card, CardHeader } from '../components/ui/primitives';
import { useAuth } from '../context/AuthContext';

export default function TargetFormPage({ targetId = null, onSave, onCancel }) {
    const { checkAuthStatus } = useAuth();
    const [loading, setLoading] = useState(false);
    const [saving, setSaving] = useState(false);
    const [errors, setErrors] = useState({});

    // Form inputs
    const [name, setName] = useState('');
    const [type, setType] = useState('domain');
    const [value, setValue] = useState('');
    const [environment, setEnvironment] = useState('production');
    const [description, setDescription] = useState('');
    const [criticality, setCriticality] = useState('medium');
    const [status, setStatus] = useState('active');
    const [scopeNotes, setScopeNotes] = useState('');
    const [tagsText, setTagsText] = useState('');

    useEffect(() => {
        if (!targetId) return;

        const fetchTarget = async () => {
            setLoading(true);
            try {
                const response = await fetch(`/api/targets/${targetId}`, {
                    headers: {
                        'Accept': 'application/json',
                    }
                });
                if (response.status === 401) {
                    checkAuthStatus();
                    return;
                }
                if (!response.ok) {
                    throw new Error('Failed to load target details');
                }
                const data = (await response.json()).data;
                setName(data.name);
                setType(data.type);
                setValue(data.value);
                setEnvironment(data.environment);
                setDescription(data.description || '');
                setCriticality(data.criticality);
                setStatus(data.status);
                setScopeNotes(data.scope_notes || '');
                setTagsText((data.tags || []).map(t => t.name).join(', '));
            } catch (err) {
                alert(err.message);
            } finally {
                setLoading(false);
            }
        };

        fetchTarget();
    }, [targetId]);

    const handleSubmit = async (e) => {
        e.preventDefault();
        setSaving(true);
        setErrors({});

        // Parse tags
        const tags = tagsText
            .split(',')
            .map(t => t.trim())
            .filter(t => t.length > 0);

        const payload = {
            name,
            type,
            value,
            environment,
            description,
            criticality,
            status,
            scope_notes: scopeNotes,
            tags,
        };

        try {
            const url = targetId ? `/api/targets/${targetId}` : '/api/targets';
            const method = targetId ? 'PUT' : 'POST';

            const response = await fetch(url, {
                method,
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                },
                body: JSON.stringify(payload),
            });

            if (response.status === 401) {
                checkAuthStatus();
                return;
            }

            if (response.status === 422) {
                const data = await response.json();
                setErrors(data.errors || {});
                return;
            }

            if (!response.ok) {
                throw new Error('Failed to save target details');
            }

            onSave();
        } catch (err) {
            alert(err.message);
        } finally {
            setSaving(false);
        }
    };

    if (loading) {
        return (
            <div className="py-12 flex justify-center items-center">
                <span className="text-xs text-slate-500 font-semibold">Loading target details...</span>
            </div>
        );
    }

    return (
        <div className="max-w-3xl mx-auto">
            <Card padding={false}>
                <CardHeader 
                    title={targetId ? 'Modify Target Scope' : 'Register Target Scope'}
                    description="Configure parameters for external/internal scanning targets."
                />

                <form onSubmit={handleSubmit} className="p-6 flex flex-col gap-5 border-t border-slate-100">
                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        {/* Name */}
                        <div>
                            <label className="block text-xs font-semibold text-slate-700 mb-1.5">Friendly Name</label>
                            <input
                                type="text"
                                required
                                value={name}
                                onChange={e => setName(e.target.value)}
                                placeholder="e.g. Primary Edge Firewall"
                                className="w-full bg-slate-50 text-xs text-slate-700 border border-slate-200 rounded-lg px-3 py-2 outline-none focus:border-brand-500 focus:bg-white transition-all"
                            />
                            {errors.name && <p className="text-[11px] text-red-500 mt-1">{errors.name[0]}</p>}
                        </div>

                        {/* Target Type */}
                        <div>
                            <label className="block text-xs font-semibold text-slate-700 mb-1.5">Target Type</label>
                            <select
                                value={type}
                                onChange={e => setType(e.target.value)}
                                className="w-full bg-slate-50 text-xs text-slate-700 border border-slate-200 rounded-lg px-3 py-2 outline-none focus:border-brand-500 focus:bg-white transition-all"
                            >
                                <option value="domain">Domain</option>
                                <option value="ip_address">IP Address</option>
                                <option value="cidr_range">CIDR Range</option>
                                <option value="url">URL</option>
                                <option value="api_endpoint">API Endpoint</option>
                                <option value="mobile_application">Mobile Application</option>
                                <option value="cloud_resource">Cloud Resource</option>
                            </select>
                            {errors.type && <p className="text-[11px] text-red-500 mt-1">{errors.type[0]}</p>}
                        </div>
                    </div>

                    {/* Value */}
                    <div>
                        <label className="block text-xs font-semibold text-slate-700 mb-1.5">Target Value</label>
                        <input
                            type="text"
                            required
                            value={value}
                            onChange={e => setValue(e.target.value)}
                            placeholder="e.g. gateway.internal or 10.10.1.1"
                            className="w-full bg-slate-50 text-xs font-mono text-slate-700 border border-slate-200 rounded-lg px-3 py-2 outline-none focus:border-brand-500 focus:bg-white transition-all"
                        />
                        {errors.value && <p className="text-[11px] text-red-500 mt-1">{errors.value[0]}</p>}
                    </div>

                    <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        {/* Environment */}
                        <div>
                            <label className="block text-xs font-semibold text-slate-700 mb-1.5">Environment</label>
                            <select
                                value={environment}
                                onChange={e => setEnvironment(e.target.value)}
                                className="w-full bg-slate-50 text-xs text-slate-700 border border-slate-200 rounded-lg px-3 py-2 outline-none focus:border-brand-500 focus:bg-white transition-all"
                            >
                                <option value="production">Production</option>
                                <option value="staging">Staging</option>
                                <option value="development">Development</option>
                                <option value="internal">Internal</option>
                            </select>
                            {errors.environment && <p className="text-[11px] text-red-500 mt-1">{errors.environment[0]}</p>}
                        </div>

                        {/* Criticality */}
                        <div>
                            <label className="block text-xs font-semibold text-slate-700 mb-1.5">Criticality</label>
                            <select
                                value={criticality}
                                onChange={e => setCriticality(e.target.value)}
                                className="w-full bg-slate-50 text-xs text-slate-700 border border-slate-200 rounded-lg px-3 py-2 outline-none focus:border-brand-500 focus:bg-white transition-all"
                            >
                                <option value="critical">Critical</option>
                                <option value="high">High</option>
                                <option value="medium">Medium</option>
                                <option value="low">Low</option>
                            </select>
                            {errors.criticality && <p className="text-[11px] text-red-500 mt-1">{errors.criticality[0]}</p>}
                        </div>

                        {/* Status */}
                        <div>
                            <label className="block text-xs font-semibold text-slate-700 mb-1.5">Status</label>
                            <select
                                value={status}
                                onChange={e => setStatus(e.target.value)}
                                className="w-full bg-slate-50 text-xs text-slate-700 border border-slate-200 rounded-lg px-3 py-2 outline-none focus:border-brand-500 focus:bg-white transition-all"
                            >
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                                <option value="under_review">Under Review</option>
                            </select>
                            {errors.status && <p className="text-[11px] text-red-500 mt-1">{errors.status[0]}</p>}
                        </div>
                    </div>

                    {/* Description */}
                    <div>
                        <label className="block text-xs font-semibold text-slate-700 mb-1.5">Description</label>
                        <textarea
                            value={description}
                            onChange={e => setDescription(e.target.value)}
                            placeholder="Primary entry point, hosting central API routes."
                            rows={3}
                            className="w-full bg-slate-50 text-xs text-slate-700 border border-slate-200 rounded-lg px-3 py-2 outline-none focus:border-brand-500 focus:bg-white transition-all resize-none"
                        />
                        {errors.description && <p className="text-[11px] text-red-500 mt-1">{errors.description[0]}</p>}
                    </div>

                    {/* Tags */}
                    <div>
                        <label className="block text-xs font-semibold text-slate-700 mb-1.5">Tags (comma-separated)</label>
                        <input
                            type="text"
                            value={tagsText}
                            onChange={e => setTagsText(e.target.value)}
                            placeholder="Production, External, DMZ"
                            className="w-full bg-slate-50 text-xs text-slate-700 border border-slate-200 rounded-lg px-3 py-2 outline-none focus:border-brand-500 focus:bg-white transition-all"
                        />
                        {errors.tags && <p className="text-[11px] text-red-500 mt-1">{errors.tags[0]}</p>}
                    </div>

                    {/* Scope Notes */}
                    <div>
                        <label className="block text-xs font-semibold text-slate-700 mb-1.5">Scope Notes</label>
                        <textarea
                            value={scopeNotes}
                            onChange={e => setScopeNotes(e.target.value)}
                            placeholder="Exempt from heavy load testing during active working hours."
                            rows={4}
                            className="w-full bg-slate-50 text-xs text-slate-700 border border-slate-200 rounded-lg px-3 py-2 outline-none focus:border-brand-500 focus:bg-white transition-all resize-none"
                        />
                        {errors.scope_notes && <p className="text-[11px] text-red-500 mt-1">{errors.scope_notes[0]}</p>}
                    </div>

                    {/* Footer Actions */}
                    <div className="flex justify-end gap-2.5 pt-3 border-t border-slate-100">
                        <button
                            type="button"
                            onClick={onCancel}
                            className="px-4 py-2 border border-slate-200 bg-white hover:bg-slate-50 text-slate-700 text-xs font-semibold rounded-lg transition-colors focus:outline-none"
                        >
                            Cancel
                        </button>
                        <button
                            type="submit"
                            disabled={saving}
                            className="px-4 py-2 bg-brand-600 hover:bg-brand-700 disabled:opacity-50 text-white text-xs font-semibold rounded-lg transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500"
                        >
                            {saving ? 'Saving...' : 'Save Target'}
                        </button>
                    </div>
                </form>
            </Card>
        </div>
    );
}
