import React, { useState } from 'react';
import { Shield, Lock, Mail, User, Loader2, AlertCircle } from 'lucide-react';
import { useAuth } from '../context/AuthContext';

export default function SetupPage() {
    const { login, refreshSetupStatus } = useAuth();
    const [name, setName] = useState('');
    const [email, setEmail] = useState('');
    const [password, setPassword] = useState('');
    const [passwordConfirmation, setPasswordConfirmation] = useState('');
    const [loading, setLoading] = useState(false);
    const [errors, setErrors] = useState({});
    const [generalError, setGeneralError] = useState('');

    const getCsrfToken = () => {
        return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
    };

    const handleSubmit = async (e) => {
        e.preventDefault();
        setLoading(true);
        setErrors({});
        setGeneralError('');

        try {
            const res = await fetch('/api/setup', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': getCsrfToken(),
                },
                body: JSON.stringify({
                    name,
                    email,
                    password,
                    password_confirmation: passwordConfirmation
                }),
            });

            if (!res.ok) {
                if (res.status === 403) {
                    // Setup already completed by another process/user
                    await refreshSetupStatus();
                    return;
                }
                if (res.status === 422) {
                    const data = await res.json();
                    throw { validation: data.errors };
                }
                const data = await res.json().catch(() => ({}));
                throw new Error(data.message || 'Setup failed. Please try again.');
            }

            // After successful setup, automatically log in
            await login(email, password);
            // Refresh setup status so app updates state if needed
            await refreshSetupStatus();
        } catch (err) {
            if (err.validation) {
                setErrors(err.validation);
            } else {
                setGeneralError(err.message || 'An unexpected error occurred.');
            }
        } finally {
            setLoading(false);
        }
    };

    return (
        <div className="min-h-screen bg-slate-50 flex items-center justify-center p-4">
            <div className="w-full max-w-md bg-white border border-slate-200 rounded-2xl shadow-xl shadow-slate-100/50 overflow-hidden my-8">
                <div className="px-8 pt-8 pb-6 flex flex-col items-center border-b border-slate-100 text-center">
                    <div className="w-10 h-10 rounded-xl bg-brand-600 flex items-center justify-center mb-4 shadow-sm">
                        <Shield className="text-white" size={20} strokeWidth={2.5} />
                    </div>
                    <h2 className="text-lg font-bold text-slate-900">TrustNode</h2>
                    <p className="text-xs text-slate-500 mt-1.5 font-medium">Security is built from the smallest entities up.</p>
                </div>

                <div className="px-8 pt-6 text-center">
                    <h3 className="text-base font-bold text-slate-800">Initial Setup</h3>
                    <p className="text-xs text-slate-500 mt-1">Create your TrustNode developer account.</p>
                </div>

                <form onSubmit={handleSubmit} className="px-8 pb-8 pt-6 flex flex-col gap-5">
                    {generalError && (
                        <div className="flex items-start gap-2.5 p-3 rounded-lg bg-red-50 border border-red-200 text-red-700 text-xs">
                            <AlertCircle size={14} className="shrink-0 mt-0.5" />
                            <span>{generalError}</span>
                        </div>
                    )}

                    {/* Name */}
                    <div>
                        <label className="block text-xs font-semibold text-slate-700 mb-1.5">Full Name</label>
                        <div className="relative">
                            <User className="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" size={14} />
                            <input
                                type="text"
                                required
                                autoComplete="name"
                                value={name}
                                onChange={e => setName(e.target.value)}
                                placeholder="e.g. John Doe"
                                className="w-full bg-slate-50 text-xs text-slate-700 border border-slate-200 rounded-lg pl-9 pr-3 py-2.5 outline-none focus:border-brand-500 focus:bg-white transition-all"
                            />
                        </div>
                        {errors.name && <p className="text-[11px] text-red-500 mt-1 font-semibold">{errors.name[0]}</p>}
                    </div>

                    {/* Email */}
                    <div>
                        <label className="block text-xs font-semibold text-slate-700 mb-1.5">Email</label>
                        <div className="relative">
                            <Mail className="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" size={14} />
                            <input
                                type="email"
                                required
                                autoComplete="email"
                                value={email}
                                onChange={e => setEmail(e.target.value)}
                                placeholder="e.g. admin@trustnode.internal"
                                className="w-full bg-slate-50 text-xs text-slate-700 border border-slate-200 rounded-lg pl-9 pr-3 py-2.5 outline-none focus:border-brand-500 focus:bg-white transition-all"
                            />
                        </div>
                        {errors.email && <p className="text-[11px] text-red-500 mt-1 font-semibold">{errors.email[0]}</p>}
                    </div>

                    {/* Password */}
                    <div>
                        <label className="block text-xs font-semibold text-slate-700 mb-1.5">Password</label>
                        <div className="relative">
                            <Lock className="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" size={14} />
                            <input
                                type="password"
                                required
                                autoComplete="new-password"
                                value={password}
                                onChange={e => setPassword(e.target.value)}
                                placeholder="••••••••"
                                className="w-full bg-slate-50 text-xs text-slate-700 border border-slate-200 rounded-lg pl-9 pr-3 py-2.5 outline-none focus:border-brand-500 focus:bg-white transition-all"
                            />
                        </div>
                        {errors.password && <p className="text-[11px] text-red-500 mt-1 font-semibold">{errors.password[0]}</p>}
                    </div>

                    {/* Confirm Password */}
                    <div>
                        <label className="block text-xs font-semibold text-slate-700 mb-1.5">Confirm Password</label>
                        <div className="relative">
                            <Lock className="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" size={14} />
                            <input
                                type="password"
                                required
                                autoComplete="new-password"
                                value={passwordConfirmation}
                                onChange={e => setPasswordConfirmation(e.target.value)}
                                placeholder="••••••••"
                                className="w-full bg-slate-50 text-xs text-slate-700 border border-slate-200 rounded-lg pl-9 pr-3 py-2.5 outline-none focus:border-brand-500 focus:bg-white transition-all"
                            />
                        </div>
                    </div>

                    {/* Submit */}
                    <button
                        type="submit"
                        disabled={loading}
                        className="w-full inline-flex items-center justify-center gap-1.5 px-4 py-2.5 rounded-lg text-xs font-semibold text-white bg-brand-600 hover:bg-brand-700 disabled:opacity-50 transition-all focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500 mt-2"
                    >
                        {loading ? (
                            <>
                                <Loader2 size={13} className="animate-spin" />
                                Creating Account...
                            </>
                        ) : (
                            'Create Account'
                        )}
                    </button>
                    
                    <p className="text-[11px] text-center text-slate-400 mt-2 font-medium">
                        This account will be the local administrator for this TrustNode instance.
                    </p>
                </form>
            </div>
            
            <div className="fixed bottom-6 left-0 right-0 text-center text-[11px] text-slate-400 font-medium hidden sm:block">
                TrustNode <br /> Developer Security Console
            </div>
        </div>
    );
}

