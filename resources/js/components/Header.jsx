import React, { useState, useRef, useEffect, useCallback } from 'react';
import {
    Search, Bell, Sun, Moon, ChevronDown,
    LogOut, User, Settings, ShieldAlert, ScanLine, FileText, Users,
} from 'lucide-react';
import { Avatar, IconButton } from './ui/primitives';
import { useAuth } from '../context/AuthContext';

const notifications = [];

/* ── useClickOutside hook ─────────────────────────────────── */
function useClickOutside(ref, handler) {
    useEffect(() => {
        const listener = (e) => {
            if (ref.current && !ref.current.contains(e.target)) handler(e);
        };
        document.addEventListener('mousedown', listener);
        document.addEventListener('touchstart', listener);
        return () => {
            document.removeEventListener('mousedown', listener);
            document.removeEventListener('touchstart', listener);
        };
    }, [ref, handler]);
}

/* ── Notification icon map ───────────────────────────────── */
const notifIcons = {
    finding: ShieldAlert,
    scan: ScanLine,
    report: FileText,
    user: Users,
};

const notifColors = {
    finding: 'text-red-600 bg-red-50',
    scan: 'text-brand-600 bg-brand-50',
    report: 'text-emerald-600 bg-emerald-50',
    user: 'text-slate-600 bg-slate-100',
};

/* ── NotificationPanel ───────────────────────────────────── */
function NotificationPanel({ onClose }) {
    const ref = useRef(null);
    const unreadCount = notifications.filter(n => n.unread).length;
    useClickOutside(ref, onClose);

    return (
        <div
            ref={ref}
            role="dialog"
            aria-label="Notifications"
            className="absolute right-0 top-full mt-2 w-84 bg-white rounded-xl border border-slate-200 shadow-2xl shadow-slate-200/80 z-50"
        >
            {/* Header */}
            <div className="flex items-center justify-between px-4 py-3 border-b border-slate-100">
                <div className="flex items-center gap-2">
                    <span className="text-sm font-semibold text-slate-900">Notifications</span>
                    {unreadCount > 0 && (
                        <span className="text-xs font-medium text-white bg-brand-600 px-1.5 py-0.5 rounded-full leading-none">
                            {unreadCount}
                        </span>
                    )}
                </div>
                <button className="text-xs text-brand-600 font-medium hover:text-brand-700 transition-colors focus:outline-none focus-visible:underline">
                    Mark all read
                </button>
            </div>

            {/* List */}
            <div className="divide-y divide-slate-50 max-h-80 overflow-y-auto">
                {notifications.map((n) => {
                    const Icon = notifIcons[n.type] || Bell;
                    const color = notifColors[n.type] || notifColors.user;

                    return (
                        <div
                            key={n.id}
                            className={`flex gap-3 px-4 py-3 hover:bg-slate-50 cursor-pointer transition-colors ${n.unread ? '' : 'opacity-70'}`}
                        >
                            <div className={`w-8 h-8 rounded-lg flex items-center justify-center shrink-0 mt-0.5 ${color}`}>
                                <Icon size={13} strokeWidth={2} />
                            </div>
                            <div className="flex-1 min-w-0">
                                <div className="flex items-start justify-between gap-2">
                                    <p className={`text-xs font-semibold text-slate-900 leading-snug ${n.unread ? '' : 'font-medium'}`}>
                                        {n.title}
                                    </p>
                                    {n.unread && <span className="w-1.5 h-1.5 rounded-full bg-brand-500 shrink-0 mt-1" />}
                                </div>
                                <p className="text-xs text-slate-500 truncate mt-0.5">{n.desc}</p>
                                <p className="text-[10px] text-slate-400 mt-1">{n.time} ago</p>
                            </div>
                        </div>
                    );
                })}
            </div>

            {/* Footer */}
            <div className="px-4 py-2.5 border-t border-slate-100">
                <button className="w-full text-xs text-center text-brand-600 font-medium hover:text-brand-700 transition-colors focus:outline-none focus-visible:underline">
                    View all notifications
                </button>
            </div>
        </div>
    );
}

/* ── ProfileMenu ─────────────────────────────────────────── */
function ProfileMenu({ onClose, user, onLogout }) {
    const ref = useRef(null);
    useClickOutside(ref, onClose);

    const menuItems = [
        { icon: User, label: 'My Profile' },
        { icon: Settings, label: 'Preferences' },
    ];

    return (
        <div
            ref={ref}
            role="menu"
            aria-label="User menu"
            className="absolute right-0 top-full mt-2 w-56 bg-white rounded-xl border border-slate-200 shadow-2xl shadow-slate-200/80 z-50"
        >
            {/* User info */}
            <div className="flex items-center gap-3 px-4 py-3.5 border-b border-slate-100">
                <Avatar initials={user.initials} size="md" />
                <div className="min-w-0">
                    <p className="text-sm font-semibold text-slate-900 truncate">{user.displayName}</p>
                    <p className="text-xs text-slate-400 truncate">{user.email}</p>
                </div>
            </div>

            {/* Menu items */}
            <div className="py-1">
                {menuItems.map(({ icon: Icon, label }) => (
                    <button
                        key={label}
                        role="menuitem"
                        className="w-full flex items-center gap-2.5 px-4 py-2.5 text-sm text-slate-700 hover:bg-slate-50 transition-colors focus:outline-none focus-visible:bg-slate-50"
                    >
                        <Icon size={14} className="text-slate-400 shrink-0" />
                        {label}
                    </button>
                ))}
            </div>

            {/* Divider + Sign out */}
            <div className="border-t border-slate-100 py-1">
                <button
                    onClick={onLogout}
                    role="menuitem"
                    className="w-full flex items-center gap-2.5 px-4 py-2.5 text-sm text-red-600 hover:bg-red-50 transition-colors focus:outline-none focus-visible:bg-red-50"
                >
                    <LogOut size={14} className="shrink-0" />
                    Sign out
                </button>
            </div>
        </div>
    );
}

/* ── Header ──────────────────────────────────────────────── */
export default function Header({ darkMode, onToggleDark, pageTitle }) {
    const { user, logout } = useAuth();
    const [showNotifications, setShowNotifications] = useState(false);
    const [showProfile, setShowProfile] = useState(false);
    const [searchValue, setSearchValue] = useState('');
    const [searchFocus, setSearchFocus] = useState(false);

    const unreadCount = notifications.filter(n => n.unread).length;

    const closeAll = useCallback(() => {
        setShowNotifications(false);
        setShowProfile(false);
    }, []);

    const toggleNotifications = useCallback(() => {
        setShowNotifications(v => !v);
        setShowProfile(false);
    }, []);

    const toggleProfile = useCallback(() => {
        setShowProfile(v => !v);
        setShowNotifications(false);
    }, []);

    /* Keyboard shortcut: Cmd/Ctrl+K focuses search */
    useEffect(() => {
        const handler = (e) => {
            if ((e.metaKey || e.ctrlKey) && e.key === 'k') {
                e.preventDefault();
                document.getElementById('global-search')?.focus();
            }
        };
        document.addEventListener('keydown', handler);
        return () => document.removeEventListener('keydown', handler);
    }, []);

    if (!user) return null;

    return (
        <header
            role="banner"
            className="h-14 bg-white/95 backdrop-blur-sm border-b border-slate-200 flex items-center gap-3 px-5 sticky top-0 z-20"
        >
            {/* Breadcrumb / page title */}
            <div className="flex-1 min-w-0">
                <h1 className="text-sm font-semibold text-slate-900 truncate">{pageTitle}</h1>
            </div>

            {/* Global search */}
            <div
                className={`
                    hidden sm:flex items-center gap-2 px-3 py-2 rounded-lg border
                    transition-all duration-200
                    ${searchFocus
                        ? 'border-brand-400 bg-white ring-3 ring-brand-100 w-72'
                        : 'border-slate-200 bg-slate-50 w-60'}
                `}
            >
                <Search size={13} className="text-slate-400 shrink-0" aria-hidden="true" />
                <input
                    id="global-search"
                    type="search"
                    placeholder="Search assets, findings, scans…"
                    value={searchValue}
                    onChange={e => setSearchValue(e.target.value)}
                    onFocus={() => setSearchFocus(true)}
                    onBlur={() => setSearchFocus(false)}
                    aria-label="Global search"
                    className="flex-1 bg-transparent text-sm text-slate-700 placeholder-slate-400 outline-none min-w-0"
                />
                {!searchFocus && (
                    <kbd className="hidden lg:inline-flex items-center gap-0.5 text-[10px] text-slate-400 border border-slate-200 rounded px-1 py-0.5 font-mono shrink-0">
                        ⌘K
                    </kbd>
                )}
                {searchFocus && searchValue && (
                    <button
                        onClick={() => setSearchValue('')}
                        className="text-slate-400 hover:text-slate-600 text-xs shrink-0"
                        aria-label="Clear search"
                    >
                        ✕
                    </button>
                )}
            </div>

            {/* Actions */}
            <div className="flex items-center gap-0.5">
                {/* Theme toggle */}
                <IconButton
                    icon={darkMode ? Sun : Moon}
                    label={darkMode ? 'Switch to light mode' : 'Switch to dark mode'}
                    onClick={onToggleDark}
                />

                {/* Notifications */}
                <div className="relative">
                    <button
                        onClick={toggleNotifications}
                        aria-label={`Notifications${unreadCount > 0 ? `, ${unreadCount} unread` : ''}`}
                        aria-expanded={showNotifications}
                        className="relative p-2 rounded-lg text-slate-500 hover:bg-slate-100 hover:text-slate-700 transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500"
                    >
                        <Bell size={16} strokeWidth={1.75} />
                        {unreadCount > 0 && (
                            <span className="absolute top-1.5 right-1.5 w-2 h-2 bg-red-500 rounded-full ring-2 ring-white" />
                        )}
                    </button>
                    {showNotifications && (
                        <NotificationPanel onClose={() => setShowNotifications(false)} />
                    )}
                </div>

                {/* Separator */}
                <div className="w-px h-5 bg-slate-200 mx-1.5" aria-hidden="true" />

                {/* Profile */}
                <div className="relative">
                    <button
                        onClick={toggleProfile}
                        aria-label="User menu"
                        aria-expanded={showProfile}
                        className="flex items-center gap-2 px-2 py-1.5 rounded-lg hover:bg-slate-50 transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500"
                    >
                        <Avatar initials={user.initials} size="sm" />
                        <span className="hidden lg:block text-sm font-medium text-slate-700 max-w-[120px] truncate">
                            {user.displayName}
                        </span>
                        <ChevronDown
                            size={13}
                            className={`text-slate-400 transition-transform duration-200 ${showProfile ? 'rotate-180' : ''}`}
                        />
                    </button>
                    {showProfile && (
                        <ProfileMenu onClose={() => setShowProfile(false)} user={user} onLogout={logout} />
                    )}
                </div>
            </div>
        </header>
    );
}
