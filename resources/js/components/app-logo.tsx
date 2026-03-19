export default function AppLogo() {
    return (
        <>
            <div className="text-sidebar-primary-foreground flex aspect-square size-8 items-center justify-center rounded-md">
                <img src="/logo.png" alt="" />
            </div>
            <div className="ml-1 grid flex-1 text-left text-lg">
                <span className="mb-0.5 truncate leading-none text-green-600 font-semibold">Refund Portal</span>
            </div>
        </>
    );
}
