import { useInitials } from '@/hooks/use-initials';
import { IpcShell } from '@/layouts/ipc-shell';
import { stageBadgeStyle, stageLabel } from '@/lib/ipc-stages';
import { type SharedData } from '@/types';
import { Head, Link, usePage } from '@inertiajs/react';
import {
    Activity,
    ArrowRight,
    CalendarDays,
    CheckCircle2,
    ChevronRight,
    ClipboardList,
    Layers,
    Plus,
    Printer,
    Stamp,
    type LucideIcon,
} from 'lucide-react';
import { useState } from 'react';
import cosmaxIndonesiaLogo from '../../images/cosmax-idn-logo.jpg';

interface DashboardStats {
    activeBatches: number;
    needsActionCount: number;
    pendingApprovalBatches: number;
    completedToday: number;
}
interface StageBreakdownItem {
    stage: string;
    count: number;
}
interface DashboardProps {
    stats: DashboardStats;
    stageBreakdown: StageBreakdownItem[];
}

const stageGuides: Record<string, { title: string; description: string }> = {
    startup: {
        title: 'Mulai dari kesiapan produksi',
        description: 'Periksa kesiapan line, dokumen, material, dan mesin sebelum melanjutkan pemeriksaan batch.',
    },
    filling: {
        title: 'Pantau pemeriksaan filling',
        description: 'Buka batch pada tahap Filling Check untuk melengkapi hasil pemeriksaan proses pengisian.',
    },
    packing: {
        title: 'Pastikan kualitas pengemasan',
        description: 'Lanjutkan pemeriksaan kemasan dan catat hasilnya pada Packing Check masing-masing batch.',
    },
    finished: { title: 'Verifikasi produk jadi', description: 'Lengkapi pemeriksaan Finished Good sebelum batch masuk ke proses persetujuan.' },
    approval: {
        title: 'Tinjau hasil pemeriksaan',
        description: 'Batch pada tahap ini menunggu proses persetujuan. Pengguna dengan akses approval dapat meninjau hasil pemeriksaannya.',
    },
    print: {
        title: 'Lanjutkan ke dokumen laporan',
        description: 'Buka batch untuk mengakses dokumen laporan dan melanjutkan proses cetak sesuai akses Anda.',
    },
    completed: {
        title: 'Telusuri batch yang sudah selesai',
        description: 'Lihat kembali detail batch, hasil pemeriksaan, dan dokumen yang telah melalui seluruh proses.',
    },
};
const focusClass = 'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2';
function greeting(): string {
    const hour = new Date().getHours();
    if (hour < 11) return 'Selamat pagi';
    if (hour < 15) return 'Selamat siang';
    if (hour < 19) return 'Selamat sore';
    return 'Selamat malam';
}
function StatCard({
    icon: Icon,
    value,
    label,
    caption,
    href,
    accent = false,
}: {
    icon: LucideIcon;
    value: number;
    label: string;
    caption: string;
    href: string;
    accent?: boolean;
}) {
    return (
        <Link
            href={href}
            className={`${focusClass} group flex min-h-36 flex-col justify-between rounded-2xl border p-4 transition-colors md:p-5 ${accent ? 'border-blue-600 bg-blue-600 text-white hover:bg-blue-700' : 'border-border-soft bg-card hover:border-primary/40'}`}
        >
            <div className="flex items-center justify-between gap-2">
                <span className={`text-sm font-semibold ${accent ? 'text-blue-100' : 'text-muted-foreground'}`}>{label}</span>
                <Icon className={`size-5 shrink-0 ${accent ? 'text-blue-200' : 'text-primary'}`} />
            </div>
            <div className="mt-4 flex items-end justify-between gap-2">
                <div>
                    <p className="text-4xl leading-none font-bold tracking-tight tabular-nums">{value.toLocaleString('id-ID')}</p>
                    <p className={`mt-2 min-h-10 text-xs leading-relaxed ${accent ? 'text-blue-100' : 'text-muted-foreground'}`}>{caption}</p>
                </div>
                <ArrowRight
                    className={`mb-1 size-5 shrink-0 transition-transform group-hover:translate-x-1 ${accent ? 'text-blue-200' : 'text-muted-foreground'}`}
                />
            </div>
        </Link>
    );
}

export default function Dashboard({ stats, stageBreakdown }: DashboardProps) {
    const { props } = usePage<SharedData>();
    const getInitials = useInitials();
    const [selectedStage, setSelectedStage] = useState('startup');
    const total = stageBreakdown.reduce((sum, item) => sum + item.count, 0);
    const largestStage = stageBreakdown.reduce<StageBreakdownItem | null>(
        (largest, item) => (!largest || item.count > largest.count ? item : largest),
        null,
    );
    const selected = stageBreakdown.find((item) => item.stage === selectedStage);
    const guide = stageGuides[selectedStage];
    const printCount = stageBreakdown.find((item) => item.stage === 'print')?.count ?? 0;
    const actionHref = props.canApproveIpc ? '/approvals' : props.canRecordMicroCheck && !props.canCreateBatch ? '/micro-checks' : '/batches';
    const actionLabel = props.canApproveIpc
        ? 'Buka antrian approval'
        : props.canRecordMicroCheck && !props.canCreateBatch
          ? 'Buka Micro Check'
          : 'Buka daftar batch';
    return (
        <IpcShell
            title="Dashboard"
            header={
                <header className="border-border-soft bg-card flex shrink-0 items-center justify-between gap-3 border-b px-5 py-3 md:px-7">
                    <div className="flex min-w-0 flex-col gap-1 sm:flex-row sm:items-center sm:gap-4">
                        <img src={cosmaxIndonesiaLogo} alt="COSMAX Indonesia" className="h-auto w-32 shrink-0 rounded-md bg-white sm:w-40" />
                        <h1 className="sm:border-border text-xs font-bold tracking-tight sm:border-l sm:pl-4 sm:text-lg">In Process Control</h1>
                    </div>
                    <div className="flex items-center gap-3">
                        <span className="text-muted-foreground hidden items-center gap-2 text-sm lg:flex">
                            <CalendarDays className="size-4" />
                            {new Date().toLocaleDateString('id-ID', { day: 'numeric', month: 'long', year: 'numeric' })}
                        </span>
                        <div
                            aria-label={props.auth.user.name}
                            className="bg-primary/10 text-primary flex size-10 shrink-0 items-center justify-center rounded-full text-sm font-bold"
                        >
                            {getInitials(props.auth.user.name)}
                        </div>
                    </div>
                </header>
            }
        >
            <Head title="Dashboard" />
            <div className="dark:bg-background flex-1 overflow-y-auto bg-slate-50/70">
                <div className="mx-auto flex w-full max-w-[1600px] flex-col gap-6 px-5 py-6 md:px-7 md:py-7">
                    <section className="flex flex-wrap items-center justify-between gap-4">
                        <div>
                            <p className="text-muted-foreground mb-1 text-sm">
                                {greeting()}, {props.auth.user.name}
                            </p>
                            <h2 className="text-2xl font-bold tracking-tight md:text-[28px]">Overview produksi & pemeriksaan</h2>
                            <p className="text-muted-foreground mt-2 text-sm leading-relaxed">
                                Lihat kondisi batch, pahami alurnya, dan tentukan langkah berikutnya.
                            </p>
                        </div>
                        {props.canCreateBatch && (
                            <Link
                                href="/batches/create"
                                className={`${focusClass} bg-primary hover:bg-primary/90 flex min-h-12 items-center justify-center gap-2 rounded-xl px-5 text-sm font-semibold text-white`}
                            >
                                <Plus className="size-5" />
                                Batch baru
                            </Link>
                        )}
                    </section>
                    <section aria-label="Ringkasan batch" className="grid grid-cols-2 gap-3 lg:grid-cols-4">
                        <StatCard
                            icon={Layers}
                            value={stats.activeBatches}
                            label="Batch aktif"
                            caption="Belum menyelesaikan seluruh proses"
                            href="/batches"
                            accent
                        />
                        <StatCard
                            icon={ClipboardList}
                            value={stats.needsActionCount}
                            label="Perlu tindakan Anda"
                            caption="Tindak lanjut sesuai peran Anda"
                            href={actionHref}
                        />
                        <StatCard
                            icon={Stamp}
                            value={stats.pendingApprovalBatches}
                            label="Menunggu approval"
                            caption="Batch pada tahap persetujuan"
                            href={props.canApproveIpc ? '/approvals' : '/batches?stage=approval'}
                        />
                        <StatCard
                            icon={CheckCircle2}
                            value={stats.completedToday}
                            label="Selesai hari ini"
                            caption="Penyelesaian batch pada hari ini"
                            href="/batches?stage=completed"
                        />
                    </section>

                    <div className="grid items-start gap-5 lg:grid-cols-[minmax(0,1.6fr)_minmax(280px,1fr)]">
                        <section className="border-border-soft bg-card rounded-2xl border p-5 md:p-6">
                            <div className="flex flex-wrap items-start justify-between gap-3">
                                <div>
                                    <p className="text-primary mb-1 text-[11px] font-bold tracking-[0.14em] uppercase">Gambaran alur</p>
                                    <h2 className="text-xl font-bold">Di mana posisi batch kita?</h2>
                                    <p className="text-muted-foreground mt-1 text-sm leading-relaxed">
                                        Pilih tahapan untuk melihat konteks dan arah tindak lanjut.
                                    </p>
                                </div>
                                <div className="bg-muted rounded-xl px-3 py-2 text-right">
                                    <span className="text-xl font-bold tabular-nums">{total.toLocaleString('id-ID')}</span>
                                    <p className="text-muted-foreground text-[11px]">total batch</p>
                                </div>
                            </div>
                            <div aria-label="Distribusi batch per tahap" className="bg-muted mt-5 flex h-4 gap-1 overflow-hidden rounded-full">
                                {stageBreakdown
                                    .filter((item) => item.count > 0)
                                    .map((item) => (
                                        <button
                                            key={item.stage}
                                            type="button"
                                            onClick={() => setSelectedStage(item.stage)}
                                            aria-label={`${stageLabel(item.stage)}: ${item.count} batch`}
                                            title={`${stageLabel(item.stage)}: ${item.count} batch`}
                                            className={`${focusClass} h-full min-w-1 transition-opacity hover:opacity-70`}
                                            style={{
                                                width: `${total ? (item.count / total) * 100 : 0}%`,
                                                backgroundColor: stageBadgeStyle(item.stage).color,
                                            }}
                                        />
                                    ))}
                            </div>
                            <div className="mt-5 grid grid-cols-2 gap-2 sm:grid-cols-3">
                                {stageBreakdown.map((item, index) => (
                                    <button
                                        key={item.stage}
                                        type="button"
                                        aria-pressed={selectedStage === item.stage}
                                        onClick={() => setSelectedStage(item.stage)}
                                        className={`${focusClass} min-h-24 rounded-xl border p-3 text-left transition-colors ${selectedStage === item.stage ? 'border-primary bg-primary/5' : 'border-border-soft hover:bg-muted'}`}
                                    >
                                        <div className="flex items-center gap-2">
                                            <span
                                                className="flex size-5 shrink-0 items-center justify-center rounded-md text-[10px] font-bold"
                                                style={stageBadgeStyle(item.stage)}
                                            >
                                                {index + 1}
                                            </span>
                                            <span className="text-xs leading-tight font-semibold">{stageLabel(item.stage)}</span>
                                        </div>
                                        <div className="mt-3 flex items-baseline justify-between gap-2">
                                            <span className="text-2xl font-bold tabular-nums">{item.count.toLocaleString('id-ID')}</span>
                                            <span className="text-muted-foreground text-xs tabular-nums">
                                                {total ? Math.round((item.count / total) * 100) : 0}%
                                            </span>
                                        </div>
                                    </button>
                                ))}
                            </div>
                            <div aria-live="polite" className="border-primary/15 bg-primary/5 mt-5 rounded-xl border p-4">
                                <div className="text-primary flex items-center gap-2">
                                    <Activity className="size-4" />
                                    <p className="text-xs font-semibold">
                                        {stageLabel(selectedStage)} · {selected?.count ?? 0} batch
                                    </p>
                                </div>
                                <h3 className="mt-2 text-base font-bold">{guide?.title ?? stageLabel(selectedStage)}</h3>
                                <p className="text-muted-foreground mt-1 text-sm leading-relaxed">{guide?.description}</p>
                                <Link
                                    href={`/batches?stage=${encodeURIComponent(selectedStage)}`}
                                    className={`${focusClass} text-primary mt-3 inline-flex min-h-11 items-center gap-2 rounded-lg text-sm font-semibold`}
                                >
                                    Lihat batch {stageLabel(selectedStage)}
                                    <ArrowRight className="size-4" />
                                </Link>
                            </div>
                        </section>
                        <div className="flex flex-col gap-5">
                            <section className="border-border-soft bg-card rounded-2xl border p-5 md:p-6">
                                <div className="flex items-center gap-2">
                                    <span className={`size-2 rounded-full ${stats.needsActionCount ? 'bg-amber-500' : 'bg-emerald-500'}`} />
                                    <h2 className="text-base font-bold">Fokus Anda</h2>
                                </div>
                                <p className="mt-4 text-4xl font-bold tracking-tight tabular-nums">
                                    {stats.needsActionCount.toLocaleString('id-ID')}
                                    <span className="text-muted-foreground ml-2 text-sm font-medium tracking-normal">batch</span>
                                </p>
                                <p className="text-muted-foreground mt-2 text-sm leading-relaxed">
                                    {stats.needsActionCount
                                        ? 'Membutuhkan tindak lanjut Anda. Buka area kerja untuk melihat detail dan melanjutkan prosesnya.'
                                        : 'Tidak ada tindakan tertunda untuk Anda. Pantau perkembangan batch melalui ringkasan tahapan.'}
                                </p>
                                <Link
                                    href={actionHref}
                                    className={`${focusClass} bg-primary hover:bg-primary/90 mt-5 flex min-h-12 items-center justify-between gap-2 rounded-xl px-4 text-sm font-semibold text-white`}
                                >
                                    {actionLabel}
                                    <ArrowRight className="size-4" />
                                </Link>
                            </section>
                            <section className="border-border-soft bg-card rounded-2xl border p-5 md:p-6">
                                <div className="flex items-center gap-2">
                                    <Printer className="text-primary size-5" />
                                    <h2 className="text-base font-bold">Tahap dokumentasi</h2>
                                </div>
                                <p className="mt-4 text-3xl font-bold tabular-nums">
                                    {printCount.toLocaleString('id-ID')}
                                    <span className="text-muted-foreground ml-2 text-sm font-medium">batch di tahap Print</span>
                                </p>
                                <p className="text-muted-foreground mt-2 text-sm leading-relaxed">
                                    Akses laporan pemeriksaan dan lanjutkan proses cetak dari detail batch.
                                </p>
                                <Link
                                    href="/batches?stage=print"
                                    className={`${focusClass} text-primary mt-3 flex min-h-11 items-center gap-2 rounded-lg text-sm font-semibold`}
                                >
                                    Buka batch tahap Print
                                    <ArrowRight className="size-4" />
                                </Link>
                            </section>
                            <div className="border-border rounded-2xl border border-dashed p-5">
                                <p className="text-muted-foreground text-xs font-bold tracking-[0.12em] uppercase">Sorotan proses</p>
                                <p className="text-muted-foreground mt-2 text-sm leading-relaxed">
                                    {largestStage && largestStage.count > 0 ? (
                                        <>
                                            <span className="text-foreground font-semibold">{stageLabel(largestStage.stage)}</span> memiliki batch
                                            terbanyak ({largestStage.count} batch). Distribusi ini menunjukkan posisi proses, bukan indikator
                                            keterlambatan.
                                        </>
                                    ) : (
                                        'Belum ada data batch. Ringkasan proses akan muncul setelah batch pertama dibuat.'
                                    )}
                                </p>
                            </div>
                        </div>
                    </div>
                    <section className="border-border-soft bg-card rounded-2xl border p-5">
                        <div className="flex flex-wrap items-center justify-between gap-2">
                            <h2 className="text-base font-bold">Mulai dari mana?</h2>
                            <span className="text-muted-foreground text-xs">Pilih area kerja sesuai kebutuhan Anda</span>
                        </div>
                        <div className="mt-4 grid gap-3 md:grid-cols-3">
                            <Link
                                href="/batches"
                                className={`${focusClass} border-border-soft hover:bg-muted flex min-h-20 items-center gap-3 rounded-xl border p-3`}
                            >
                                <Layers className="text-primary size-5 shrink-0" />
                                <div className="flex-1">
                                    <p className="text-sm font-semibold">Pantau batch</p>
                                    <p className="text-muted-foreground mt-1 text-xs leading-relaxed">Cari batch dan lihat progres pemeriksaannya.</p>
                                </div>
                                <ChevronRight className="text-muted-foreground size-4 shrink-0" />
                            </Link>
                            <Link
                                href={actionHref}
                                className={`${focusClass} border-border-soft hover:bg-muted flex min-h-20 items-center gap-3 rounded-xl border p-3`}
                            >
                                <ClipboardList className="text-primary size-5 shrink-0" />
                                <div className="flex-1">
                                    <p className="text-sm font-semibold">
                                        {props.canApproveIpc
                                            ? 'Tinjau persetujuan'
                                            : props.canRecordMicroCheck && !props.canCreateBatch
                                              ? 'Pemeriksaan mikro'
                                              : 'Lanjutkan pemeriksaan'}
                                    </p>
                                    <p className="text-muted-foreground mt-1 text-xs leading-relaxed">Buka pekerjaan sesuai akses dan peran Anda.</p>
                                </div>
                                <ChevronRight className="text-muted-foreground size-4 shrink-0" />
                            </Link>
                            <Link
                                href="/batches?stage=completed"
                                className={`${focusClass} border-border-soft hover:bg-muted flex min-h-20 items-center gap-3 rounded-xl border p-3`}
                            >
                                <CheckCircle2 className="size-5 shrink-0 text-emerald-600" />
                                <div className="flex-1">
                                    <p className="text-sm font-semibold">Telusuri hasil</p>
                                    <p className="text-muted-foreground mt-1 text-xs leading-relaxed">Lihat batch yang telah menyelesaikan proses.</p>
                                </div>
                                <ChevronRight className="text-muted-foreground size-4 shrink-0" />
                            </Link>
                        </div>
                    </section>
                </div>
            </div>
        </IpcShell>
    );
}
