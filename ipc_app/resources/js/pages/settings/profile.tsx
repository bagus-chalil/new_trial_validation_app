import { type SharedData } from '@/types';
import { Transition } from '@headlessui/react';
import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { FormEventHandler } from 'react';

import DeleteUser from '@/components/delete-user';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { IpcShell } from '@/layouts/ipc-shell';
import { SettingsTabNav } from '@/layouts/settings/tab-nav';

export default function Profile({ mustVerifyEmail, status }: { mustVerifyEmail: boolean; status?: string }) {
    const { auth } = usePage<SharedData>().props;

    const { data, setData, patch, errors, processing, recentlySuccessful } = useForm({
        name: auth.user.name,
        email: auth.user.email,
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        patch(route('profile.update'));
    };

    return (
        <IpcShell title="Settings">
            <Head title="Profile settings" />
            <SettingsTabNav active="profile" />

            <div className="flex-1 overflow-y-auto px-5 pb-8 pt-2 md:px-6">
                <div className="mx-auto max-w-lg space-y-8">
                    <div className="space-y-5">
                        <p className="text-[13px] font-semibold uppercase tracking-wider text-muted-foreground">Informasi Profil</p>

                        <form onSubmit={submit} className="space-y-4">
                            <div className="grid gap-1.5">
                                <Label htmlFor="name">Nama</Label>
                                <Input
                                    id="name"
                                    value={data.name}
                                    onChange={(e) => setData('name', e.target.value)}
                                    required
                                    autoComplete="name"
                                    placeholder="Nama lengkap"
                                    className="h-11 rounded-xl"
                                />
                                <InputError message={errors.name} />
                            </div>

                            <div className="grid gap-1.5">
                                <Label htmlFor="email">Email</Label>
                                <Input
                                    id="email"
                                    type="email"
                                    value={data.email}
                                    onChange={(e) => setData('email', e.target.value)}
                                    required
                                    autoComplete="username"
                                    placeholder="Alamat email"
                                    className="h-11 rounded-xl"
                                />
                                <InputError message={errors.email} />
                            </div>

                            {mustVerifyEmail && auth.user.email_verified_at === null && (
                                <div className="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800 dark:border-amber-200/10 dark:bg-amber-700/10 dark:text-amber-300">
                                    Email belum terverifikasi.{' '}
                                    <Link
                                        href={route('verification.send')}
                                        method="post"
                                        as="button"
                                        className="font-semibold underline"
                                    >
                                        Kirim ulang verifikasi.
                                    </Link>
                                    {status === 'verification-link-sent' && (
                                        <p className="mt-1 font-medium text-green-700">Link verifikasi telah dikirim.</p>
                                    )}
                                </div>
                            )}

                            <div className="flex items-center gap-3 pt-1">
                                <Button disabled={processing} className="h-11 rounded-xl px-6">
                                    Simpan
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

                    <div className="border-t border-border pt-6">
                        <DeleteUser />
                    </div>
                </div>
            </div>
        </IpcShell>
    );
}
