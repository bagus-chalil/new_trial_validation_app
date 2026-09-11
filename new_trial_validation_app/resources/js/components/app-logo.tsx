import { usePage } from '@inertiajs/react';

import cosmaxLogo from '@/assets/cosmax-idn-logo.jpg';

export default function AppLogo() {
    const { name } = usePage().props;

    return (
        <>
            <div className="flex aspect-square size-8 items-center justify-center overflow-hidden rounded-md bg-white ring-1 ring-sidebar-border">
                <img
                    src={cosmaxLogo}
                    alt="Cosmax"
                    className="h-full w-full object-cover object-right"
                />
            </div>
            <div className="ml-1 grid flex-1 text-left text-sm">
                <span className="mb-0.5 truncate leading-tight font-semibold">
                    {name}
                </span>
            </div>
        </>
    );
}
