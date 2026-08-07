import React from 'react';
import { ShieldAlert, RefreshCw } from 'lucide-react';

export default class ErrorBoundary extends React.Component {
    constructor(props) {
        super(props);
        this.state = { hasError: false, error: null };
    }

    static getDerivedStateFromError(error) {
        return { hasError: true, error };
    }

    componentDidCatch(error, errorInfo) {
        console.error("ErrorBoundary caught an exception:", error, errorInfo);
    }

    handleReset = () => {
        this.setState({ hasError: false, error: null });
        window.location.reload();
    };

    render() {
        if (this.state.hasError) {
            return (
                <div className="min-h-screen bg-slate-50 flex items-center justify-center p-6">
                    <div className="max-w-md w-full bg-white rounded-2xl border border-slate-200 p-8 shadow-sm flex flex-col items-center text-center">
                        <div className="w-16 h-16 rounded-2xl bg-rose-50 flex items-center justify-center text-rose-500 mb-6">
                            <ShieldAlert size={36} />
                        </div>
                        <h2 className="text-xl font-bold text-slate-900 mb-2">Something went wrong</h2>
                        <p className="text-sm text-slate-500 mb-6 leading-relaxed">
                            An unexpected runtime error occurred while loading this page. Please try reloading or contact system support.
                        </p>
                        {this.state.error && (
                            <div className="w-full text-left bg-slate-900 text-slate-300 font-mono text-xs rounded-xl p-4 mb-6 overflow-x-auto max-h-48 border border-slate-800">
                                {this.state.error.toString()}
                            </div>
                        )}
                        <button
                            onClick={this.handleReset}
                            className="w-full h-11 bg-indigo-600 hover:bg-indigo-700 active:bg-indigo-800 text-white rounded-xl font-medium transition-colors flex items-center justify-center gap-2"
                        >
                            <RefreshCw size={16} />
                            Reload Platform
                        </button>
                    </div>
                </div>
            );
        }

        return this.props.children;
    }
}
