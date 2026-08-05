import React, { useState, useEffect } from 'react';
import StatCard from '../components/StatCard';
import { SeverityChart, ScanActivityChart } from '../components/Charts';
import ActivityTable from '../components/ActivityTable';
import ScansTable from '../components/ScansTable';
import QuickActions from '../components/QuickActions';
import { SkeletonCard } from '../components/ui/primitives';

export default function DashboardPage() {
    const [stats, setStats] = useState([]);
    const [loading, setLoading] = useState(true);

    useEffect(() => {
        const fetchDashboardStats = async () => {
            try {
                const response = await fetch('/api/dashboard/stats', {
                    headers: {
                        'Accept': 'application/json',
                    }
                });
                if (!response.ok) {
                    throw new Error('Failed to load dashboard stats');
                }
                const data = await response.json();
                setStats(data.stats);
            } catch (err) {
                console.error(err);
            } finally {
                setLoading(false);
            }
        };

        fetchDashboardStats();
    }, []);

    return (
        <div className="flex flex-col gap-6">

            {/* Section: KPI Cards */}
            <section aria-label="Key metrics">
                <div className="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4">
                    {loading ? (
                        <>
                            <SkeletonCard />
                            <SkeletonCard />
                            <SkeletonCard />
                            <SkeletonCard />
                        </>
                    ) : (
                        stats.map((stat) => (
                            <StatCard key={stat.id} stat={stat} />
                        ))
                    )}
                </div>
            </section>

            {/* Section: Charts + Quick Actions */}
            <section aria-label="Charts and quick actions">
                <div className="grid grid-cols-1 lg:grid-cols-3 gap-4">
                    <SeverityChart />
                    <ScanActivityChart />
                    <QuickActions />
                </div>
            </section>

            {/* Section: Activity + Scans */}
            <section aria-label="Recent activity and assessments">
                <div className="grid grid-cols-1 xl:grid-cols-2 gap-4">
                    <ActivityTable />
                    <ScansTable />
                </div>
            </section>

        </div>
    );
}
