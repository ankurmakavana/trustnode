import React, { useState } from 'react';
import { Shield, Lock, Mail, Loader2, AlertCircle } from 'lucide-react';
import { useAuth } from '../context/AuthContext';

export default function LoginPage() {
    const { login } = useAuth();
    const [email, setEmail] = useState('');
    const [password, setPassword] = useState('');
    const [loading, setLoading] = useState(false);
    const [errors, setErrors] = useState({});
    const [generalError, setGeneralError] = useState('');

    const handleSubmit = async (e) => {
        e.preventDefault();
        setLoading(true);
        setErrors({});
        setGeneralError('');

        try {
            await login(email, password);
        } catch (err) {
            if (err.validation) {
                setErrors(err.validation);
            } else {
                setGeneralError(err.message || 'An unexpected authentication error occurred.');
            }
        } finally {
            setLoading(false);
        }
    };

    return (
        <div className="min-h-screen bg-slate-50 flex items-center justify-center p-4">
            <div className="w-full max-w-md bg-white border border-slate-200 rounded-2xl shadow-xl shadow-slate-100/50 overflow-hidden">
                <div className="px-8 pt-8 pb-6 flex flex-col items-center border-b border-slate-100">
                    <div className="w-10 h-10 rounded-xl bg-brand-600 flex items-center justify-center mb-4">
                        <Shield className="text-white" size={20} strokeWidth={2.5} />
                    </div>
                    <h2 className="text-lg font-bold text-slate-900">TrustNode</h2>
                    <p className="text-xs text-slate-500 mt-1.5 font-medium">Enterprise VAPT & Attack Surface Management</p>
                </div>

                <form onSubmit={handleSubmit} className="p-8 flex flex-col gap-4">
                    {generalError && (
                        <div className="flex items-start gap-2.5 p-3 rounded-lg bg-red-50 border border-red-200 text-red-700 text-xs">
                            <AlertCircle size={14} className="shrink-0 mt-0.5" />
                            <span>{generalError}</span>
                        </div>
                    )}

                    {/* Email */}
                    <div>
                        <label className="block text-xs font-semibold text-slate-700 mb-1.5">Corporate Email</label>
                        <div className="relative">
                            <Mail className="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" size={14} />
                            <input
                                type="email"
                                required
                                value={email}
                                onChange={e => setEmail(e.target.value)}
                                placeholder="e.g. analyst@trustnode.internal"
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
                                value={password}
                                onChange={e => setPassword(e.target.value)}
                                placeholder="••••••••"
                                className="w-full bg-slate-50 text-xs text-slate-700 border border-slate-200 rounded-lg pl-9 pr-3 py-2.5 outline-none focus:border-brand-500 focus:bg-white transition-all"
                            />
                        </div>
                        {errors.password && <p className="text-[11px] text-red-500 mt-1 font-semibold">{errors.password[0]}</p>}
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
                                Authenticating...
                            </>
                        ) : (
                            'Sign In'
                        )}
                    </button>
                </form>
            </div>
        </div>
    );
}
