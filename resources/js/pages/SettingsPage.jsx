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
        </div>
    );
}
