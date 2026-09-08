import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { IpcShell } from '@/layouts/ipc-shell';
import { SettingsTabNav } from '@/layouts/settings/tab-nav';
import { Transition } from '@headlessui/react';
import { Head, useForm } from '@inertiajs/react';
import { FormEventHandler, useRef } from 'react';

export default function Password() {
    const passwordInput = useRef<HTMLInputElement>(null);
    const currentPasswordInput = useRef<HTMLInputElement>(null);

    const { data, setData, errors, put, reset, processing, recentlySuccessful } = useForm({
        current_password: '',
        password: '',
        password_confirmation: '',
    });

    const updatePassword: FormEventHandler = (e) => {
        e.preventDefault();

        put(route('password.update'), {
            preserveScroll: true,
            onSuccess: () => reset(),
            onError: (errors) => {
                if (errors.password) {
                    reset('password', 'password_confirmation');
                    passwordInput.current?.focus();
                }
                if (errors.current_password) {
                    reset('current_password');
                    currentPasswordInput.current?.focus();
                }
            },
        });
    };

    return (
        <IpcShell title="Settings">
            <Head title="Password settings" />
            <SettingsTabNav active="password" />

            <div className="flex-1 overflow-y-auto px-5 pb-8 pt-2 md:px-6">
                <div className="mx-auto max-w-lg space-y-8">
                    <p className="text-[13px] font-semibold uppercase tracking-wider text-muted-foreground">Ubah Password</p>

                    <form onSubmit={updatePassword} className="space-y-4">
                        <div className="grid gap-1.5">
                            <Label htmlFor="current_password">Password saat ini</Label>
                            <Input
                                id="current_password"
                                ref={currentPasswordInput}
                                value={data.current_password}
                                onChange={(e) => setData('current_password', e.target.value)}
                                type="password"
                                className="h-11 rounded-xl"
                                autoComplete="current-password"
                                placeholder="Password saat ini"
                            />
                            <InputError message={errors.current_password} />
                        </div>

                        <div className="grid gap-1.5">
                            <Label htmlFor="password">Password baru</Label>
                            <Input
                                id="password"
                                ref={passwordInput}
                                value={data.password}
                                onChange={(e) => setData('password', e.target.value)}
                                type="password"
                                className="h-11 rounded-xl"
                                autoComplete="new-password"
                                placeholder="Password baru"
                            />
                            <InputError message={errors.password} />
                        </div>

                        <div className="grid gap-1.5">
                            <Label htmlFor="password_confirmation">Konfirmasi password</Label>
                            <Input
                                id="password_confirmation"
                                value={data.password_confirmation}
                                onChange={(e) => setData('password_confirmation', e.target.value)}
                                type="password"
                                className="h-11 rounded-xl"
                                autoComplete="new-password"
                                placeholder="Konfirmasi password baru"
                            />
                            <InputError message={errors.password_confirmation} />
                        </div>

                        <div className="flex items-center gap-3 pt-1">
                            <Button disabled={processing} className="h-11 rounded-xl px-6">
                                Simpan Password
                            </Button>
                            <Transition
                                show={recentlySuccessful}
                                enter="transition ease-in-out"
                                enterFrom="opacity-0"
                                leave="transition ease-in-out"
                                leaveTo="opacity-0"
                            >
                                <p className="text-sm font-medium text-green-600">Tersimpan!</p>
                            </Transition>
                        </div>
                    </form>
                </div>
            </div>
        </IpcShell>
    );
}
