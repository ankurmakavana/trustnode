import React, { useState, useEffect } from 'react';
import { Mail, Bell, Save, CheckCircle2, AlertTriangle, Send } from 'lucide-react';
import axios from 'axios';

export default function SettingsPage() {
    const [activeTab, setActiveTab] = useState('email');
    const [loading, setLoading] = useState(true);
    const [saving, setSaving] = useState(false);
    const [message, setMessage] = useState(null);

    // Mail settings state
    const [mailSettings, setMailSettings] = useState({
        mail_mailer: 'smtp',
        mail_host: '',
        mail_port: '',
        mail_username: '',
        mail_password: '',
        mail_encryption: 'tls',
        mail_from_address: '',
        mail_from_name: ''
    });

    // Preferences state
    const [preferences, setPreferences] = useState({
        in_app: true,
        email: true
    });

    // Test Email state
    const [testEmail, setTestEmail] = useState('');
    const [testingMail, setTestingMail] = useState(false);
    const [testMessage, setTestMessage] = useState(null);

    // Danger Zone / Reset state
    const [showResetModal, setShowResetModal] = useState(false);
    const [resetConfirmation, setResetConfirmation] = useState('');
    const [createBackup, setCreateBackup] = useState(true);
    const [resetting, setResetting] = useState(false);
    const [resetError, setResetError] = useState(null);

    useEffect(() => {
        const fetchData = async () => {
            setLoading(true);
            try {
                const [mailRes, prefRes] = await Promise.all([
                    axios.get('/api/settings/mail').catch(() => ({ data: { data: {} } })),
                    axios.get('/api/users/preferences').catch(() => ({ data: { data: { notifications: {} } } }))
                ]);
                
                if (mailRes.data?.data) {
                    setMailSettings(prev => ({ ...prev, ...mailRes.data.data }));
                }
                
                if (prefRes.data?.data?.notifications) {
                    setPreferences({
                        in_app: prefRes.data.data.notifications.in_app ?? true,
                        email: prefRes.data.data.notifications.email ?? true
                    });
                }
            } catch (error) {
                console.error("Failed to load settings:", error);
            } finally {
                setLoading(false);
            }
        };
        fetchData();
    }, []);

    const handleSaveMail = async (e) => {
        e.preventDefault();
        setSaving(true);
        setMessage(null);
        try {
            await axios.put('/api/settings/mail', mailSettings);
            setMessage({ type: 'success', text: 'Mail settings updated successfully.' });
            
            // Clear password field after save
            setMailSettings(prev => ({ ...prev, mail_password: '' }));
        } catch (error) {
            setMessage({ type: 'error', text: error.response?.data?.message || 'Failed to update mail settings.' });
        } finally {
            setSaving(false);
        }
    };

    const handleSavePreferences = async (e) => {
        e.preventDefault();
        setSaving(true);
        setMessage(null);
        try {
            await axios.put('/api/users/preferences', { notifications: preferences });
            setMessage({ type: 'success', text: 'Preferences updated successfully.' });
        } catch (error) {
            setMessage({ type: 'error', text: 'Failed to update preferences.' });
        } finally {
            setSaving(false);
        }
    };

    const handleTestEmail = async (e) => {
        e.preventDefault();
        if (!testEmail) return;
        
        setTestingMail(true);
        setTestMessage(null);
        try {
            await axios.post('/api/settings/test-email', { to_email: testEmail });
            setTestMessage({ type: 'success', text: 'Test email dispatched successfully.' });
        } catch (error) {
            setTestMessage({ type: 'error', text: error.response?.data?.message || 'Failed to dispatch test email.' });
        } finally {
            setTestingMail(false);
        }
    };

    const handlePlatformReset = async () => {
        setResetting(true);
        setResetError(null);

        try {
            const res = await axios.post('/api/settings/reset', {
                confirmation: resetConfirmation,
                create_backup: createBackup
            });

            if (res.data?.success) {
                // Force full reload to setup wizard
                window.location.href = '/';
            } else {
                setResetError(res.data?.message || 'Failed to reset platform.');
            }
        } catch (error) {
            setResetError(error.response?.data?.message || 'A server error occurred during platform reset.');
        } finally {
            setResetting(false);
        }
    };

    if (loading) {
        return (
            <div className="flex items-center justify-center min-h-[40vh]">
                <div className="text-slate-500 font-medium">Loading settings...</div>
            </div>
        );
    }

    return (
        <div className="max-w-4xl mx-auto space-y-6">
            <div>
                <h1 className="text-xl font-bold text-slate-900 tracking-tight">Platform Settings</h1>
                <p className="text-sm text-slate-500 mt-1">Manage system configurations and personal preferences.</p>
            </div>

            {message && (
                <div className={`p-4 rounded-lg flex items-start gap-3 ${message.type === 'success' ? 'bg-emerald-50 text-emerald-800' : 'bg-rose-50 text-rose-800'}`}>
                    {message.type === 'success' ? <CheckCircle2 className="mt-0.5 text-emerald-600" size={18} /> : <AlertTriangle className="mt-0.5 text-rose-600" size={18} />}
                    <span className="text-sm font-medium">{message.text}</span>
                </div>
            )}

            <div className="flex gap-4 border-b border-slate-200">
                <button 
                    className={`pb-3 px-1 text-sm font-medium border-b-2 transition-colors ${activeTab === 'email' ? 'border-brand-600 text-brand-700' : 'border-transparent text-slate-500 hover:text-slate-700'}`}
                    onClick={() => setActiveTab('email')}
                >
                    <div className="flex items-center gap-2"><Mail size={16}/> SMTP Configuration</div>
                </button>
                <button 
                    className={`pb-3 px-1 text-sm font-medium border-b-2 transition-colors ${activeTab === 'notifications' ? 'border-brand-600 text-brand-700' : 'border-transparent text-slate-500 hover:text-slate-700'}`}
                    onClick={() => setActiveTab('notifications')}
                >
                    <div className="flex items-center gap-2"><Bell size={16}/> My Preferences</div>
                </button>
                <button
                    className={`pb-3 px-1 text-sm font-medium border-b-2 transition-colors ${activeTab === 'danger' ? 'border-rose-600 text-rose-700' : 'border-transparent text-slate-500 hover:text-rose-600'}`}
                    onClick={() => setActiveTab('danger')}
                >
                    <div className="flex items-center gap-2"><AlertTriangle size={16}/> Danger Zone</div>
                </button>
            </div>

            {activeTab === 'email' && (
                <div className="space-y-8">
                    <form onSubmit={handleSaveMail} className="bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden">
                        <div className="p-6 space-y-6">
                            <h2 className="text-base font-semibold text-slate-900">Mail Server (SMTP)</h2>
                            
                            <div className="grid grid-cols-2 gap-6">
                                <div className="space-y-1.5 col-span-2 sm:col-span-1">
                                    <label className="text-sm font-medium text-slate-700">Host</label>
                                    <input type="text" value={mailSettings.mail_host} onChange={e => setMailSettings({...mailSettings, mail_host: e.target.value})} className="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-sm focus:bg-white focus:ring-2 focus:ring-brand-500/20 focus:border-brand-500 transition-all" placeholder="smtp.example.com" required />
                                </div>
                                <div className="space-y-1.5 col-span-2 sm:col-span-1">
                                    <label className="text-sm font-medium text-slate-700">Port</label>
                                    <input type="number" value={mailSettings.mail_port} onChange={e => setMailSettings({...mailSettings, mail_port: e.target.value})} className="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-sm focus:bg-white focus:ring-2 focus:ring-brand-500/20 focus:border-brand-500 transition-all" placeholder="587" required />
                                </div>
                                <div className="space-y-1.5 col-span-2 sm:col-span-1">
                                    <label className="text-sm font-medium text-slate-700">Username</label>
                                    <input type="text" value={mailSettings.mail_username} onChange={e => setMailSettings({...mailSettings, mail_username: e.target.value})} className="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-sm focus:bg-white focus:ring-2 focus:ring-brand-500/20 focus:border-brand-500 transition-all" placeholder="user@example.com" />
                                </div>
                                <div className="space-y-1.5 col-span-2 sm:col-span-1">
                                    <label className="text-sm font-medium text-slate-700">Password</label>
                                    <input type="password" value={mailSettings.mail_password} onChange={e => setMailSettings({...mailSettings, mail_password: e.target.value})} className="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-sm focus:bg-white focus:ring-2 focus:ring-brand-500/20 focus:border-brand-500 transition-all" placeholder="Leave blank to keep unchanged" autoComplete="new-password" />
                                </div>
                                <div className="space-y-1.5 col-span-2 sm:col-span-1">
                                    <label className="text-sm font-medium text-slate-700">Encryption</label>
                                    <select value={mailSettings.mail_encryption} onChange={e => setMailSettings({...mailSettings, mail_encryption: e.target.value})} className="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-sm focus:bg-white focus:ring-2 focus:ring-brand-500/20 focus:border-brand-500 transition-all">
                                        <option value="tls">TLS</option>
                                        <option value="ssl">SSL</option>
                                        <option value="">None</option>
                                    </select>
                                </div>
                            </div>

                            <hr className="border-slate-100" />
                            <h2 className="text-base font-semibold text-slate-900">Sender Details</h2>
                            
                            <div className="grid grid-cols-2 gap-6">
                                <div className="space-y-1.5 col-span-2 sm:col-span-1">
                                    <label className="text-sm font-medium text-slate-700">From Address</label>
                                    <input type="email" value={mailSettings.mail_from_address} onChange={e => setMailSettings({...mailSettings, mail_from_address: e.target.value})} className="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-sm focus:bg-white focus:ring-2 focus:ring-brand-500/20 focus:border-brand-500 transition-all" placeholder="security@company.com" required />
                                </div>
                                <div className="space-y-1.5 col-span-2 sm:col-span-1">
                                    <label className="text-sm font-medium text-slate-700">From Name</label>
                                    <input type="text" value={mailSettings.mail_from_name} onChange={e => setMailSettings({...mailSettings, mail_from_name: e.target.value})} className="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-sm focus:bg-white focus:ring-2 focus:ring-brand-500/20 focus:border-brand-500 transition-all" placeholder="TrustNode Alerts" required />
                                </div>
                            </div>
                        </div>
                        <div className="px-6 py-4 bg-slate-50 border-t border-slate-200 flex justify-end">
                            <button type="submit" disabled={saving} className="px-4 py-2 bg-brand-600 text-white text-sm font-medium rounded-lg hover:bg-brand-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-brand-500 disabled:opacity-50 flex items-center gap-2">
                                <Save size={16} /> Save Configuration
                            </button>
                        </div>
                    </form>

                    <form onSubmit={handleTestEmail} className="bg-white border border-slate-200 rounded-xl shadow-sm p-6">
                        <h2 className="text-base font-semibold text-slate-900 mb-4">Test Configuration</h2>
                        {testMessage && (
                            <div className={`p-3 rounded-md mb-4 text-sm ${testMessage.type === 'success' ? 'bg-emerald-50 text-emerald-700' : 'bg-rose-50 text-rose-700'}`}>
                                {testMessage.text}
                            </div>
                        )}
                        <div className="flex gap-3">
                            <input type="email" value={testEmail} onChange={e => setTestEmail(e.target.value)} placeholder="Recipient email address" className="flex-1 px-3 py-2 border border-slate-200 rounded-lg text-sm" required />
                            <button type="submit" disabled={testingMail || !testEmail} className="px-4 py-2 bg-slate-800 text-white text-sm font-medium rounded-lg hover:bg-slate-900 disabled:opacity-50 flex items-center gap-2">
                                <Send size={16} /> Send Test Email
                            </button>
                        </div>
                    </form>
                </div>
            )}

            {activeTab === 'notifications' && (
                <form onSubmit={handleSavePreferences} className="bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden">
                    <div className="p-6 space-y-6">
                        <h2 className="text-base font-semibold text-slate-900">Alert Channels</h2>
                        <p className="text-sm text-slate-500">Choose how you want to be notified about scans and reports.</p>
                        
                        <div className="space-y-4">
                            <label className="flex items-start gap-3 cursor-pointer p-4 rounded-lg border border-slate-200 hover:bg-slate-50 transition-colors">
                                <div className="mt-0.5">
                                    <input type="checkbox" checked={preferences.in_app} onChange={e => setPreferences({...preferences, in_app: e.target.checked})} className="w-4 h-4 text-brand-600 rounded border-slate-300 focus:ring-brand-500" />
                                </div>
                                <div>
                                    <div className="text-sm font-medium text-slate-900">In-App Notifications</div>
                                    <div className="text-xs text-slate-500 mt-1">Receive alerts within the TrustNode dashboard.</div>
                                </div>
                            </label>

                            <label className="flex items-start gap-3 cursor-pointer p-4 rounded-lg border border-slate-200 hover:bg-slate-50 transition-colors">
                                <div className="mt-0.5">
                                    <input type="checkbox" checked={preferences.email} onChange={e => setPreferences({...preferences, email: e.target.checked})} className="w-4 h-4 text-brand-600 rounded border-slate-300 focus:ring-brand-500" />
                                </div>
                                <div>
                                    <div className="text-sm font-medium text-slate-900">Email Alerts</div>
                                    <div className="text-xs text-slate-500 mt-1">Receive alerts directly to your registered email address.</div>
                                </div>
                            </label>
                        </div>
                    </div>
                    <div className="px-6 py-4 bg-slate-50 border-t border-slate-200 flex justify-end">
                        <button type="submit" disabled={saving} className="px-4 py-2 bg-brand-600 text-white text-sm font-medium rounded-lg hover:bg-brand-700 focus:outline-none flex items-center gap-2">
                            <Save size={16} /> Save Preferences
                        </button>
                    </div>
                </form>
            )}

            {activeTab === 'danger' && (
                <div className="space-y-6">
                    <div className="bg-white border border-rose-200 rounded-xl shadow-sm overflow-hidden">
                        <div className="p-6 border-b border-rose-100 bg-rose-50/40">
                            <div className="flex items-center gap-2 text-rose-700 font-semibold text-base mb-1">
                                <AlertTriangle size={18} /> Danger Zone — Application Reset
                            </div>
                            <p className="text-xs text-rose-600">
                                Irreversible actions that modify or purge platform operational data.
                            </p>
                        </div>

                        <div className="p-6 space-y-6">
                            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div className="p-4 rounded-lg border border-slate-200 bg-slate-50">
                                    <div className="text-sm font-semibold text-slate-800 mb-1">Safe Application Restart</div>
                                    <p className="text-xs text-slate-500 mb-3">
                                        Restarts the container services or host processes. All projects, findings, and configurations remain completely intact.
                                    </p>
                                    <span className="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-medium bg-emerald-100 text-emerald-800">
                                        Non-destructive · Safe
                                    </span>
                                </div>

                                <div className="p-4 rounded-lg border border-rose-200 bg-rose-50/50">
                                    <div className="text-sm font-semibold text-rose-900 mb-1">Full Platform Data Reset</div>
                                    <p className="text-xs text-rose-700 mb-3">
                                        Purges all registered projects, scan history, findings, and administrator accounts to return TrustNode to its initial setup wizard.
                                    </p>
                                    <span className="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-medium bg-rose-100 text-rose-800">
                                        Destructive · Purges App Data
                                    </span>
                                </div>
                            </div>

                            <div className="border-t border-slate-200 pt-6">
                                <h3 className="text-sm font-semibold text-slate-900 mb-2">Scope of Data Purge</h3>
                                <div className="text-xs text-slate-600 space-y-1.5 list-disc pl-4 mb-6">
                                    <div>Purges: Codebase projects, scans, findings, activity logs, notifications, and administrator accounts.</div>
                                    <div>Invalidates: Active user sessions, remember tokens, and connected agent authorizations.</div>
                                    <div>Preserves: Host source code files, developer folders, Docker configurations, .env files, and baseline database schemas.</div>
                                    <div>Protects: A verified automated database backup is generated before the reset commences.</div>
                                </div>

                                <button
                                    type="button"
                                    onClick={() => setShowResetModal(true)}
                                    className="px-4 py-2 bg-rose-600 text-white text-xs font-semibold rounded-lg hover:bg-rose-700 focus:outline-none flex items-center gap-2 shadow-sm"
                                >
                                    <AlertTriangle size={14} /> Reset TrustNode Platform...
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            )}

            {showResetModal && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 backdrop-blur-sm p-4">
                    <div className="bg-white rounded-2xl max-w-lg w-full shadow-2xl border border-rose-200 overflow-hidden">
                        <div className="p-6 bg-rose-50 border-b border-rose-100 flex items-start gap-3">
                            <div className="w-10 h-10 rounded-xl bg-rose-100 flex items-center justify-center text-rose-600 shrink-0">
                                <AlertTriangle size={20} />
                            </div>
                            <div>
                                <h3 className="text-base font-bold text-rose-900">Confirm Platform Reset</h3>
                                <p className="text-xs text-rose-700 mt-1">
                                    This operation will clear all platform data and return the system to the initial Setup Wizard.
                                </p>
                            </div>
                        </div>

                        <div className="p-6 space-y-4">
                            <p className="text-xs text-slate-600 leading-relaxed">
                                To confirm and prevent accidental execution, please type <span className="font-mono font-bold text-rose-600 select-all">RESET TRUSTNODE</span> in the box below:
                            </p>

                            <input
                                type="text"
                                value={resetConfirmation}
                                onChange={e => setResetConfirmation(e.target.value)}
                                placeholder="RESET TRUSTNODE"
                                className="w-full px-3 py-2 bg-slate-50 border border-slate-300 rounded-lg text-xs font-mono text-slate-800 focus:bg-white focus:border-rose-500 focus:ring-2 focus:ring-rose-500/20 outline-none"
                            />

                            <label className="flex items-center gap-2 text-xs text-slate-600 cursor-pointer pt-2">
                                <input
                                    type="checkbox"
                                    checked={createBackup}
                                    onChange={e => setCreateBackup(e.target.checked)}
                                    className="rounded border-slate-300 text-brand-600 focus:ring-brand-500"
                                />
                                <span>Create verified pre-reset database backup (strongly recommended)</span>
                            </label>

                            {resetError && (
                                <div className="p-3 rounded-lg bg-rose-50 border border-rose-200 text-rose-700 text-xs">
                                    {resetError}
                                </div>
                            )}
                        </div>

                        <div className="p-4 bg-slate-50 border-t border-slate-100 flex justify-end gap-3">
                            <button
                                type="button"
                                disabled={resetting}
                                onClick={() => {
                                    setShowResetModal(false);
                                    setResetConfirmation('');
                                    setResetError(null);
                                }}
                                className="px-4 py-2 text-xs font-semibold text-slate-600 hover:text-slate-800"
                            >
                                Cancel
                            </button>
                            <button
                                type="button"
                                disabled={resetting || resetConfirmation.trim() !== 'RESET TRUSTNODE'}
                                onClick={handlePlatformReset}
                                className="px-4 py-2 bg-rose-600 text-white text-xs font-semibold rounded-lg hover:bg-rose-700 disabled:opacity-40 transition-all flex items-center gap-2"
                            >
                                {resetting ? 'Resetting Platform...' : 'Execute Reset'}
                            </button>
                        </div>
                    </div>
                </div>
            )}
        </div>
    );
}
