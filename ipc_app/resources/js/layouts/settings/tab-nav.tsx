import { cn } from '@/lib/utils';
import { Link } from '@inertiajs/react';
import { Lock, Paintbrush, User } from 'lucide-react';

const tabs = [
    { key: 'profile', label: 'Profil', href: '/settings/profile', icon: User },
    { key: 'password', label: 'Password', href: '/settings/password', icon: Lock },
    { key: 'appearance', label: 'Tampilan', href: '/settings/appearance', icon: Paintbrush },
] as const;

type TabKey = (typeof tabs)[number]['key'];

export function SettingsTabNav({ active }: { active: TabKey }) {
    return (
        <div className="flex shrink-0 gap-1 border-b border-border px-5 md:px-6">
            {tabs.map(({ key, label, href, icon: Icon }) => (
                <Link
                    key={key}
                    href={href}
                    className={cn(
                        'flex items-center gap-1.5 border-b-2 pb-3 pt-1 text-[13.5px] font-semibold transition-colors',
                        key === active
                            ? 'border-primary text-primary'
                            : 'border-transparent text-muted-foreground hover:text-foreground',
                    )}
                >
                    <Icon className="size-[14px]" strokeWidth={2.2} />
                    {label}
                </Link>
            ))}
        </div>
    );
}
