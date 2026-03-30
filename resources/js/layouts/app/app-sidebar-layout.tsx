import { AppContent } from '@/components/app-content';
import { AppShell } from '@/components/app-shell';
import { AppSidebar } from '@/components/app-sidebar';
import { AppSidebarHeader } from '@/components/app-sidebar-header';
import { type BreadcrumbItem } from '@/types';
import { type PropsWithChildren } from 'react';

export default function AppSidebarLayout({ children, breadcrumbs = [] }: PropsWithChildren<{ breadcrumbs?: BreadcrumbItem[] }>) {
    return (
        <AppShell variant="sidebar">
            <AppSidebar />
            <AppContent variant="sidebar">
                <div className="flex items-center justify-between">
                    {/* LEFT */}
                    <AppSidebarHeader breadcrumbs={breadcrumbs} />

                    {/* RIGHT */}
                    <div className="relative me-4">
                        <input
                        type="text"
                        placeholder="Search waybill no ..."
                        className="w-full rounded-lg border border-muted pl-8 pr-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-green-500"
                        />
                        
                        {/* Icon */}
                        <span className="absolute left-3 top-1/2 -translate-y-1/2 text-muted-foreground">
                        <svg
                            xmlns="http://www.w3.org/2000/svg"
                            className="h-4 w-4"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke="currentColor"
                        >
                            <path
                            strokeLinecap="round"
                            strokeLinejoin="round"
                            strokeWidth={2}
                            d="m21 21-4.35-4.35M10 18a8 8 0 1 1 0-16 8 8 0 0 1 0 16z"
                            />
                        </svg>
                        </span>
                    </div>
                    </div>
                {children}
            </AppContent>
        </AppShell>
    );
}
