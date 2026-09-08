import AppearanceTabs from '@/components/appearance-tabs';
import { IpcShell } from '@/layouts/ipc-shell';
import { SettingsTabNav } from '@/layouts/settings/tab-nav';
import { Head } from '@inertiajs/react';

export default function Appearance() {
    return (
        <IpcShell title="Settings">
            <Head title="Appearance settings" />
            <SettingsTabNav active="appearance" />

            <div className="flex-1 overflow-y-auto px-5 pb-8 pt-2 md:px-6">
                <div className="mx-auto max-w-lg space-y-8">
                    <p className="text-[13px] font-semibold uppercase tracking-wider text-muted-foreground">Tampilan</p>
                    <AppearanceTabs />
                </div>
            </div>
        </IpcShell>
    );
}
