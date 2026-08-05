import React from 'react';
import { mockStats } from '../data/mockData';
import StatCard from '../components/StatCard';
import { SeverityChart, ScanActivityChart } from '../components/Charts';
import ActivityTable from '../components/ActivityTable';
import ScansTable from '../components/ScansTable';
import QuickActions from '../components/QuickActions';

export default function DashboardPage() {
    return (
        <div className="flex flex-col gap-6">

            {/* Section: KPI Cards */}
            <section aria-label="Key metrics">
                <div className="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4">
                    {mockStats.map((stat) => (
                        <StatCard key={stat.id} stat={stat} />
                    ))}
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
