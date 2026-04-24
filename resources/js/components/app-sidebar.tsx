import { NavFooter } from '@/components/nav-footer';
import { NavMain } from '@/components/nav-main';
import { NavReund } from '@/components/nav-refund';
import { NavUser } from '@/components/nav-user';
import { NavBlog } from '@/components/nav-blog';
import { Sidebar, SidebarContent, SidebarFooter, SidebarHeader, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import { type NavItem } from '@/types';
import { Link, usePage } from "@inertiajs/react";
import { ActivityIcon, AlarmCheckIcon, BookOpen, CalendarCheck, Database, FileInputIcon, FilesIcon, FileUser, HammerIcon, LayoutGrid, MonitorIcon, PieChartIcon, ScanSearchIcon, UploadIcon, UserCircle } from 'lucide-react';
import AppLogo from './app-logo';

const mainNavItems: NavItem[] = [
    {
        title: 'Dashboard',
        href: '/dashboard',
        permission: "",
        icon: LayoutGrid,
    },
    {
        title: 'Refund Report',
        href: '/reporting',
        permission: "refund-reports",
        icon: CalendarCheck,
    },
    {
        title: 'Finance Report',
        href: '/finance-report',
        permission: "finance-reports",
        icon: CalendarCheck,
    },
    {
        title: 'Exported Files',
        href: '/exported-files',
        permission: "exported-files",
        icon: FileInputIcon,
    },
];

const refundNavItems: NavItem[] = [
    {
        title: 'Refunds Dashboard',
        href: '/refunds',
        permission: "",
        icon: MonitorIcon,
    },
    {
        title: 'Upload File',
        href: '/refunds/upload',
        permission: "upload-file",
        icon: UploadIcon,
    },
    {
        title: 'Uploaded Files',
        href: '/refunds/uploaded-files',
        permission: "uploaded-files",
        icon: FilesIcon,
    },
    {
        title: 'Uploaded Data',
        href: '/refunds/uploaded-data',
        permission: "uploaded-data",
        icon: Database,
    },
    
];

const BlogNavItems: NavItem[] = [
    {
        title: 'Users',
        icon: UserCircle,
        permission: "users",
        children: [
            { 
                title: 'Add User', 
                href: '/users/create', 
                permission: "create-user", 
                icon: BookOpen 
            },
            { 
                title: 'All Users', 
                href: '/users', 
                permission: "all-users", 
                icon: BookOpen 
            },
        ],
    },
    {
        title: 'Analytics Accounts',
        icon: FileUser,
        permission: "analytics", 
        children: [
            { 
                title: 'Add Account', 
                href: '/analytics-accounts/create', 
                permission: "create-analytics", 
                icon: BookOpen 
            },
            { 
                title: 'All Account', 
                href: '/analytics-accounts', 
                permission: "all-analytics", 
                icon: BookOpen 
            },
        ],
    },
    {
        title: 'Jobs',
        href: '/jobs',
        permission: "jobs",
        icon: HammerIcon,
    },
    {
        title: 'Schedulers',
        href: '/schedulers',
        permission: "schedulers",
        icon: AlarmCheckIcon,
    },
    {
        title: 'DB Monitoring',
        href: '/db-monitoring',
        permission: "db-monitoring",
        icon: ActivityIcon,
    },
    {
        title: 'Table Partitions',
        href: '/partitions',
        permission: "partitions",
        icon: PieChartIcon,
    },
];

//Users & Roles
//Settings

const footerNavItems: NavItem[] = [
    {
        title: 'Logs',
        href: '/logs',
        permission: "logs",
        icon: ScanSearchIcon,
    },
    {
        title: 'Developer Notes',
        href: '/notes',
        permission: "notes",
        icon: BookOpen,
    },
];

// Hook to check permissions
const usePermission = () => {
  const { auth } = usePage().props as any;
  const permissions: string[] = auth?.permissions || [];
console.log(auth.permissions);
  const can = (permission?: string) => {
    if (!permission) return true; // No permission required
    return permissions.includes(permission);
  };

  return { can };
  
};

// Recursive menu filter based on permission
const filterMenu = (items: NavItem[], can: (perm?: string) => boolean): NavItem[] => {
  return items
    .map((item) => {
      if (item.children) {
        const filteredChildren = filterMenu(item.children, can);
        if (filteredChildren.length > 0) return { ...item, children: filteredChildren };
        return null;
      }

      if (!can(item.permission)) return null;
      return item;
    })
    .filter(Boolean) as NavItem[];
};

export function AppSidebar() {
    const { can } = usePermission();

  const filteredMain = filterMenu(mainNavItems, can);
  const filteredRefund = filterMenu(refundNavItems, can);
  const filteredAdmin = filterMenu(BlogNavItems, can);
  const filtereFooter = filterMenu(footerNavItems, can);

    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href="/dashboard" prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                <NavMain items={filteredMain} />
                <NavReund items={filteredRefund} />
                <NavBlog items={filteredAdmin} />
            </SidebarContent>

            <SidebarFooter>
                <NavFooter items={filtereFooter} className="mt-auto" />
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
