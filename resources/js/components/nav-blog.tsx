import { SidebarGroup, SidebarGroupLabel, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import { type NavItem } from '@/types';
import { Link } from '@inertiajs/react';
import { ChevronDown, ChevronRight } from "lucide-react";
import { useState } from "react";

export function NavBlog({ items = [] }: { items: NavItem[] }) {
    return (
        <SidebarGroup className="px-2 py-0">
            <SidebarGroupLabel>Settings</SidebarGroupLabel>
            <SidebarMenu>
                {items.map((item, index) => (
                  <SidebarMenuItem key={index}>
                    {item.children ? (
                      <SubMenu item={item} />
                    ) : (
                      <SidebarMenuButton asChild>
                        <Link href={item.href!} className="flex items-center gap-2">
                          {item.icon && <item.icon className="h-4 w-4" />}
                          {item.title}
                        </Link>
                      </SidebarMenuButton>
                    )}
                  </SidebarMenuItem>
                ))}
            </SidebarMenu>
        </SidebarGroup>
    );
}

function SubMenu({ item }: { item: NavItem }) {
  const [open, setOpen] = useState(false);

  return (
    <div>
      <SidebarMenuButton
        onClick={() => setOpen(!open)}
        className="flex items-center justify-between w-full"
      >
        <div className="flex items-center gap-2">
          {item.icon && <item.icon className="h-4 w-4" />}
          {item.title}
        </div>
        {open ? <ChevronDown className="h-4 w-4" /> : <ChevronRight className="h-4 w-4" />}
      </SidebarMenuButton>

      {open && (
        <div className="pl-6 pt-1 space-y-2">
          {item.children?.map((subItem, idx) => (
            <Link
              key={idx}
              href={subItem.href!}
              className="text-sm hover:text-gray-400 block"
            ><div className="flex items-center gap-2">
                {subItem.icon && <subItem.icon className="h-4 w-4" />}
                {subItem.title}
            </div>
            </Link>
          ))}
        </div>
      )}
    </div>
  );
}
