import { SidebarInset } from '@/components/ui/sidebar';
import * as React from 'react';
import { Toaster } from "sonner";

interface AppContentProps extends React.ComponentProps<'main'> {
    variant?: 'header' | 'sidebar';
}

export function AppContent({ variant = 'header', children, ...props }: AppContentProps) {
    if (variant === 'sidebar') {
        return (
            <>
                <SidebarInset
                    className="flex-1 min-w-0 overflow-hidden"
                    {...props}
                >
                    {children}
                </SidebarInset>

                <Toaster richColors position="top-right" />
            </>
        );
    }

    return (
        <>
            <main
                className="mx-auto flex h-full w-full max-w-7xl flex-1 flex-col gap-4 rounded-xl"
                {...props}
            >
                {children}
            </main>

            <Toaster richColors position="top-right" />
        </>
    );
}