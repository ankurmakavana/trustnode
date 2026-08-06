import React from 'react';
import {
    LayoutDashboard, Server, Crosshair, ScanLine, ShieldAlert,
    FileText, Sparkles, Users, Settings, ChevronLeft, Shield, CheckSquare,
} from 'lucide-react';
import { mockNavItems, navGroups, mockCurrentUser } from '../data/mockData';
import { Avatar, Badge } from './ui/primitives';
import { useAuth } from '../context/AuthContext';

const iconMap = {
    LayoutDashboard, Server, Crosshair, ScanLine, ShieldAlert,
    FileText, Sparkles, Users, Settings, Shield, CheckSquare,
};

const badgeVariants = {
    red:    'red',
    violet: 'violet',
    brand:  'brand',
};

function NavItem({ item, active, collapsed, onNavigate }) {
    const Icon   = iconMap[item.icon];
    const bColor = badgeVariants[item.badgeColor] || 'slate';

    return (
        <button
            onClick={() => onNavigate(item.id)}
            title={collapsed ? item.label : undefined}
            aria-current={active ? 'page' : undefined}
            className={`
                relative w-full flex items-center gap-2.5
                px-2.5 py-2 rounded-lg text-sm font-medium
                transition-all duration-150 group
                focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500
                ${active
                    ? 'bg-brand-50 text-brand-700'
                    : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900'}
            `}
        >
            {/* Active indicator pill */}
            <span
                className={`
                    absolute left-0 top-1/2 -translate-y-1/2 w-0.5 rounded-full
                    transition-all duration-200
                    ${active ? 'h-5 bg-brand-600' : 'h-0'}
                `}
            />

            {Icon && (
                <Icon
                    size={16}
                    strokeWidth={active ? 2.5 : 2}
                    className={`shrink-0 transition-colors ${active ? 'text-brand-600' : 'text-slate-400 group-hover:text-slate-600'}`}
                />
            )}

            {!collapsed && (
                <>
                    <span className="flex-1 text-left truncate">{item.label}</span>
                    {item.badge && (
                        <Badge variant={bColor}>{item.badge}</Badge>
                    )}
                </>
            )}
        </button>
    );
}

function NavGroup({ group, items, activePage, collapsed, onNavigate }) {
    return (
        <div className="mb-4">
            {!collapsed && (
                <p className="px-2.5 mb-1 text-[10px] font-semibold text-slate-400 uppercase tracking-widest select-none">
                    {group.label}
                </p>
            )}
            <div className="space-y-0.5">
                {items.map(item => (
                    <NavItem
                        key={item.id}
                        item={item}
                        active={activePage === item.id}
                        collapsed={collapsed}
                        onNavigate={onNavigate}
                    />
                ))}
            </div>
        </div>
    );
}

export default function Sidebar({ activePage, onNavigate, collapsed, onToggle }) {
    const { user } = useAuth();
    const [targetsCount, setTargetsCount] = React.useState('0');

    // Fetch targets count dynamically to update sidebar counters
    React.useEffect(() => {
        if (!user) return;
        fetch('/api/targets?per_page=1', {
            headers: { 'Accept': 'application/json' }
        })
        .then(res => res.json())
        .then(data => {
            if (data?.meta?.total !== undefined) {
                setTargetsCount(String(data.meta.total));
            }
        })
        .catch(() => {});
    }, [user, activePage]);

    // Replace the items list with dynamic targets counter
    const dynamicNavItems = mockNavItems.map(item => {
        if (item.id === 'targets') {
            return { ...item, badge: targetsCount };
        }
        return item;
    });

    const groupedItems = navGroups.map(group => ({
        group,
        items: dynamicNavItems.filter(i => i.group === group.id),
    }));

    return (
        <aside
            className={`
                flex flex-col bg-white border-r border-slate-200
                transition-[width] duration-300 ease-in-out
                ${collapsed ? 'w-[60px]' : 'w-[220px]'}
                h-screen sticky top-0 z-30 shrink-0
            `}
            aria-label="Main navigation"
        >
            {/* Logo bar */}
            <div className="flex items-center gap-2.5 px-3.5 h-14 border-b border-slate-200 shrink-0">
                <div className="flex items-center justify-center w-7 h-7 rounded-lg bg-brand-600 shrink-0">
                    <Shield size={14} className="text-white" strokeWidth={2.5} />
                </div>
                {!collapsed && (
                    <div className="flex-1 min-w-0">
                        <span className="font-bold text-sm tracking-tight text-slate-900">
                            TrustNode
                        </span>
                        <span className="ml-1.5 text-[10px] font-medium text-slate-400 uppercase tracking-wider">
                            CE
                        </span>
                    </div>
                )}
                <button
                    onClick={onToggle}
                    className="
                        shrink-0 p-1 rounded-md text-slate-400
                        hover:text-slate-600 hover:bg-slate-100
                        transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500
                    "
                    aria-label={collapsed ? 'Expand sidebar' : 'Collapse sidebar'}
                >
                    <ChevronLeft
                        size={14}
                        className={`transition-transform duration-300 ${collapsed ? 'rotate-180' : ''}`}
                    />
                </button>
            </div>

            {/* Navigation groups */}
            <nav className="flex-1 overflow-y-auto py-4 px-2" aria-label="Primary navigation">
                {groupedItems.map(({ group, items }) =>
                    items.length > 0 && (
                        <NavGroup
                            key={group.id}
                            group={group}
                            items={items}
                            activePage={activePage}
                            collapsed={collapsed}
                            onNavigate={onNavigate}
                        />
                    )
                )}
            </nav>

            {/* User footer */}
            {user && (
                <div className="shrink-0 border-t border-slate-200 p-2">
                    <div className={`
                        flex items-center gap-2.5 p-2 rounded-lg
                        hover:bg-slate-50 cursor-pointer group
                        transition-colors
                        ${collapsed ? 'justify-center' : ''}
                    `}>
                        <Avatar
                            initials={user.initials}
                            size="sm"
                        />
                        {!collapsed && (
                            <div className="flex-1 min-w-0">
                                <p className="text-xs font-semibold text-slate-900 truncate leading-tight">
                                    {user.displayName}
                                </p>
                                <p className="text-[10px] text-slate-400 truncate leading-tight">
                                    {user.role}
                                </p>
                            </div>
                        )}
                    </div>
                </div>
            )}
        </aside>
    );
}
