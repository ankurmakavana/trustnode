import React, { useState, useEffect } from 'react';
import { Card, CardHeader } from '../components/ui/primitives';
import { useAuth } from '../context/AuthContext';

export default function AssetFormPage({ assetId = null, onSave, onCancel }) {
    const { checkAuthStatus } = useAuth();
    const [loading, setLoading] = useState(false);
    const [saving, setSaving] = useState(false);
    const [errors, setErrors] = useState({});

    // Form inputs
    const [name, setName] = useState('');
    const [type, setType] = useState('subdomain');
    const [value, setValue] = useState('');
    const [description, setDescription] = useState('');
    const [criticality, setCriticality] = useState('medium');
    const [status, setStatus] = useState('active');
    const [riskScore, setRiskScore] = useState(0.0);
    const [owner, setOwner] = useState('');
    const [notes, setNotes] = useState('');
    const [tagsText, setTagsText] = useState('');

    useEffect(() => {
        if (!assetId) return;

        const fetchAsset = async () => {
            setLoading(true);
            try {
                const response = await fetch(`/api/assets/${assetId}`, {
                    headers: {
                        'Accept': 'application/json',
                    }
                });
                if (response.status === 401) {
                    checkAuthStatus();
                    return;
                }
                if (!response.ok) {
                    throw new Error('Failed to load asset');
                }
                const data = (await response.json()).data;
                setName(data.name);
                setType(data.type);
                setValue(data.value);
                setDescription(data.description || '');
                setCriticality(data.criticality);
                setStatus(data.status);
                setRiskScore(data.risk_score);
                setOwner(data.owner || '');
                setNotes(data.notes || '');
                setTagsText((data.tags || []).map(t => t.name).join(', '));
            } catch (err) {
                alert(err.message);
            } finally {
                setLoading(false);
            }
        };

        fetchAsset();
    }, [assetId]);

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
            description,
            criticality,
            status,
            risk_score: parseFloat(riskScore) || 0.00,
            owner,
            notes,
            tags,
        };

        try {
            const url = assetId ? `/api/assets/${assetId}` : '/api/assets';
            const method = assetId ? 'PUT' : 'POST';

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
                throw new Error('Failed to save asset details');
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
            <div className="flex items-center justify-center min-h-[40vh]">
                <p className="text-sm text-slate-500">Loading asset form...</p>
            </div>
        );
    }

    return (
        <div className="max-w-3xl mx-auto flex flex-col gap-6">
            <div>
                <h2 className="text-xl font-bold text-slate-900">
                    {assetId ? 'Edit Asset Profile' : 'Register New Asset'}
                </h2>
                <p className="text-xs text-slate-500 mt-1">
                    {assetId ? 'Modify registered target specifications.' : 'Include targets into current scoping checks.'}
                </p>
            </div>

            <form onSubmit={handleSubmit} className="flex flex-col gap-4">
                <Card>
                    <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                        {/* Name */}
                        <div className="md:col-span-2">
                            <label className="block text-xs font-semibold text-slate-700 mb-1.5">Asset Label Name *</label>
                            <input
                                type="text"
                                required
                                value={name}
                                onChange={e => setName(e.target.value)}
                                placeholder="e.g. Primary Client Gateway Router"
                                className="w-full bg-slate-50 text-xs text-slate-700 border border-slate-200 rounded-lg px-3 py-2 outline-none focus:border-brand-500 focus:bg-white transition-all"
                            />
                            {errors.name && <p className="text-xs text-red-500 mt-1">{errors.name[0]}</p>}
                        </div>

                        {/* Type */}
                        <div>
                            <label className="block text-xs font-semibold text-slate-700 mb-1.5">Asset Type *</label>
                            <select
                                value={type}
                                onChange={e => setType(e.target.value)}
                                className="w-full bg-slate-50 text-xs text-slate-700 border border-slate-200 rounded-lg px-3 py-2 outline-none focus:border-brand-500 focus:bg-white transition-all"
                            >
                                <option value="domain">Domain</option>
                                <option value="subdomain">Subdomain</option>
                                <option value="ipv4">IPv4</option>
                                <option value="ipv6">IPv6</option>
                                <option value="cidr">CIDR</option>
                                <option value="url">URL</option>
                                <option value="api_endpoint">API Endpoint</option>
                                <option value="hostname">Hostname</option>
                            </select>
                            {errors.type && <p className="text-xs text-red-500 mt-1">{errors.type[0]}</p>}
                        </div>

                        {/* Value */}
                        <div>
                            <label className="block text-xs font-semibold text-slate-700 mb-1.5">Target Value *</label>
                            <input
                                type="text"
                                required
                                value={value}
                                onChange={e => setValue(e.target.value)}
                                placeholder={
                                    type === 'ipv4' ? '10.10.1.1' :
                                    type === 'cidr' ? '10.10.0.0/16' :
                                    type === 'url' ? 'https://portal.internal' : 'auth.internal'
                                }
                                className="w-full bg-slate-50 text-xs text-slate-700 border border-slate-200 rounded-lg px-3 py-2 outline-none focus:border-brand-500 focus:bg-white transition-all font-mono"
                            />
                            {errors.value && <p className="text-xs text-red-500 mt-1">{errors.value[0]}</p>}
                        </div>

                        {/* Criticality */}
                        <div>
                            <label className="block text-xs font-semibold text-slate-700 mb-1.5">Criticality Level</label>
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
                                <option value="archived">Archived</option>
                            </select>
                        </div>

                        {/* Risk Score */}
                        <div>
                            <label className="block text-xs font-semibold text-slate-700 mb-1.5">Risk Score (0.0 - 10.0)</label>
                            <input
                                type="number"
                                step="0.01"
                                min="0.00"
                                max="10.00"
                                value={riskScore}
                                onChange={e => setRiskScore(e.target.value)}
                                className="w-full bg-slate-50 text-xs text-slate-700 border border-slate-200 rounded-lg px-3 py-2 outline-none focus:border-brand-500 focus:bg-white transition-all"
                            />
                            {errors.risk_score && <p className="text-xs text-red-500 mt-1">{errors.risk_score[0]}</p>}
                        </div>

                        {/* Owner */}
                        <div>
                            <label className="block text-xs font-semibold text-slate-700 mb-1.5">Asset Owner</label>
                            <input
                                type="text"
                                value={owner}
                                onChange={e => setOwner(e.target.value)}
                                placeholder="e.g. Platform API Team"
                                className="w-full bg-slate-50 text-xs text-slate-700 border border-slate-200 rounded-lg px-3 py-2 outline-none focus:border-brand-500 focus:bg-white transition-all"
                            />
                        </div>

                        {/* Tags */}
                        <div className="md:col-span-2">
                            <label className="block text-xs font-semibold text-slate-700 mb-1.5">Tags (comma-separated)</label>
                            <input
                                type="text"
                                value={tagsText}
                                onChange={e => setTagsText(e.target.value)}
                                placeholder="e.g. Production, Internal, PCI-DSS"
                                className="w-full bg-slate-50 text-xs text-slate-700 border border-slate-200 rounded-lg px-3 py-2 outline-none focus:border-brand-500 focus:bg-white transition-all"
                            />
                        </div>

                        {/* Description */}
                        <div className="md:col-span-2">
                            <label className="block text-xs font-semibold text-slate-700 mb-1.5">Description</label>
                            <textarea
                                value={description}
                                onChange={e => setDescription(e.target.value)}
                                rows={3}
                                placeholder="Short context detailing the asset scope..."
                                className="w-full bg-slate-50 text-xs text-slate-700 border border-slate-200 rounded-lg px-3 py-2 outline-none focus:border-brand-500 focus:bg-white transition-all resize-none"
                            />
                        </div>

                        {/* Notes */}
                        <div className="md:col-span-2">
                            <label className="block text-xs font-semibold text-slate-700 mb-1.5">Notes (Internal markdown)</label>
                            <textarea
                                value={notes}
                                onChange={e => setNotes(e.target.value)}
                                rows={4}
                                placeholder="Vulnerability details, network routing or triage details..."
                                className="w-full bg-slate-50 text-xs text-slate-700 border border-slate-200 rounded-lg px-3 py-2 outline-none focus:border-brand-500 focus:bg-white transition-all resize-none font-mono"
                            />
                        </div>
                    </div>
                </Card>

                {/* Form buttons */}
                <div className="flex items-center justify-end gap-2">
                    <button
                        type="button"
                        onClick={onCancel}
                        className="px-4 py-2 rounded-lg border border-slate-200 text-xs font-semibold text-slate-700 bg-white hover:bg-slate-50 transition-all focus:outline-none"
                    >
                        Cancel
                    </button>
                    <button
                        type="submit"
                        disabled={saving}
                        className="inline-flex items-center justify-center px-4 py-2 rounded-lg text-xs font-semibold text-white bg-brand-600 hover:bg-brand-700 disabled:opacity-50 transition-all focus:outline-none"
                    >
                        {saving ? 'Saving...' : 'Save Asset'}
                    </button>
                </div>
            </form>
        </div>
    );
}
