import { cn } from '@/lib/utils';
import { AlertCircle, ArrowDown, ArrowRight, BookOpen, Check, ClipboardCheck, LockKeyhole } from 'lucide-react';
import { createContext, type FormHTMLAttributes, useCallback, useContext, useEffect, useRef, useState } from 'react';

interface Section {
    id: string;
    title: string;
    complete: boolean;
    progress?: string;
}
const SectionsContext = createContext<((section: Section | string) => void) | null>(null);

const TERMS = [
    ['Conform / Not Conform', 'Sesuai / tidak sesuai dengan standar atau dokumen acuan.'],
    ['N/A (Not Applicable)', 'Tidak berlaku untuk produk atau pemeriksaan ini. Bukan pengganti data yang belum diketahui.'],
    ['FG Code / Bulk Code', 'Kode produk jadi / kode bahan ruahan. Cocokkan dengan dokumen produksi.'],
    ['Density', 'Massa jenis produk. Gunakan nilai dan satuan pada dokumen acuan.'],
    ['Tare / Empty Bottle Weight', 'Berat kemasan kosong sebelum diisi produk.'],
    ['TH Progress', 'Jumlah putaran pemeriksaan yang sudah disimpan dibandingkan jumlah yang ditentukan.'],
    ['Result / Std', 'Hasil uji aktual / standar pembanding. Gunakan satuan yang tercantum pada kolom.'],
];

export function useWorkflowSection(section: Section) {
    const register = useContext(SectionsContext);
    const { id, title, complete, progress } = section;
    useEffect(() => {
        register?.({ id, title, complete, progress });
    }, [register, id, title, complete, progress]);
    useEffect(() => () => register?.(id), [register, id]);
}

export function WorkflowIntro({ title, description, readOnly = false }: { title: string; description: string; readOnly?: boolean }) {
    return (
        <div className="workflow-intro border-primary/15 bg-card relative overflow-hidden rounded-3xl border p-5 md:p-7">
            <div className="relative flex items-start gap-4">
                <div className="bg-primary/10 text-primary flex size-12 shrink-0 items-center justify-center rounded-2xl">
                    {readOnly ? <LockKeyhole className="size-6" /> : <ClipboardCheck className="size-6" />}
                </div>
                <div className="min-w-0">
                    <p className="text-primary mb-1 text-[10px] font-bold tracking-[0.18em] uppercase">IPC · Quality workspace</p>
                    <h2 className="text-xl font-bold tracking-tight md:text-2xl">{title}</h2>
                    <p className="text-muted-foreground mt-2 max-w-2xl text-sm leading-relaxed">{description}</p>
                    {readOnly && (
                        <span className="bg-muted mt-3 inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-xs font-semibold">
                            <LockKeyhole className="size-3" />
                            Mode lihat · data terkunci
                        </span>
                    )}
                </div>
            </div>
        </div>
    );
}

export function WorkflowForm({
    title,
    description,
    readOnly,
    children,
    className,
    ...props
}: FormHTMLAttributes<HTMLFormElement> & { title: string; description: string; readOnly?: boolean }) {
    const [sections, setSections] = useState<Section[]>([]);
    const formRef = useRef<HTMLFormElement>(null);
    const [active, setActive] = useState('');
    const [fieldErrors, setFieldErrors] = useState<{ target: HTMLElement; label: string; message: string }[]>([]);
    const shouldFocusError = useRef(false);
    const jumpToError = useCallback((target: HTMLElement) => {
        const section = target.closest('.workflow-section');
        const trigger = section?.querySelector<HTMLButtonElement>('[data-section-trigger]');
        if (trigger?.getAttribute('aria-expanded') === 'false') trigger.click();
        requestAnimationFrame(() => {
            target.scrollIntoView({
                behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'instant' : 'smooth',
                block: 'center',
            });
            const control = target.matches('input, textarea, button, [role="combobox"]')
                ? target
                : target.querySelector<HTMLElement>('input:not(:disabled), textarea:not(:disabled), [role="combobox"]:not(:disabled)') ||
                  target.querySelector<HTMLElement>('[role="radio"]:not(:disabled), button:not(:disabled)');
            control?.focus({ preventScroll: true });
        });
    }, []);
    useEffect(() => {
        const form = formRef.current;
        if (!form) return;
        const collect = () => {
            const targets = Array.from(
                form.querySelectorAll<HTMLElement>(
                    '[data-field-error], [data-native-invalid], .outline-destructive, input.border-destructive, textarea.border-destructive, [role="combobox"].border-destructive, [aria-invalid="true"]',
                ),
            );
            const seen = new Set<HTMLElement>();
            const next = targets.flatMap((node) => {
                let field: HTMLElement = node;
                const accessibleLabel = node.getAttribute('aria-label');
                // Find the closest field group with a human-readable label.
                while (
                    field.parentElement &&
                    !accessibleLabel &&
                    !field.querySelector('label') &&
                    !field.classList.contains('workflow-fields') &&
                    field.parentElement !== form
                )
                    field = field.parentElement;
                if (seen.has(field)) return [];
                seen.add(field);
                const label = accessibleLabel || field.querySelector('label')?.textContent?.trim() || 'Periksa isian';
                const message =
                    field.querySelector('[data-field-error]')?.textContent?.trim() ||
                    (node instanceof HTMLInputElement ? node.validationMessage : '') ||
                    'Isian ini belum lengkap atau perlu diperiksa.';
                return [{ target: field, label, message }];
            });
            setFieldErrors((previous) =>
                previous.length === next.length && previous.every((e, i) => e.target === next[i].target && e.message === next[i].message)
                    ? previous
                    : next,
            );
            if (next.length && shouldFocusError.current) {
                shouldFocusError.current = false;
                jumpToError(next[0].target);
            }
        };
        const observer = new MutationObserver(collect);
        observer.observe(form, {
            childList: true,
            subtree: true,
            attributes: true,
            attributeFilter: ['class', 'aria-invalid', 'data-native-invalid'],
        });
        collect();
        return () => observer.disconnect();
    }, [jumpToError]);
    const register = useCallback((entry: Section | string) => {
        setSections((current) => {
            if (typeof entry === 'string') return current.filter((s) => s.id !== entry);
            const index = current.findIndex((s) => s.id === entry.id);
            if (index < 0) return [...current, entry];
            return current.map((s) => (s.id === entry.id ? entry : s));
        });
    }, []);
    const navigate = (id: string) => {
        const section = document.getElementById(id);
        const trigger = section?.querySelector<HTMLButtonElement>('[data-section-trigger]');
        if (trigger?.getAttribute('aria-expanded') === 'false') trigger.click();
        setActive(id);
        requestAnimationFrame(() =>
            section?.scrollIntoView({
                behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'instant' : 'smooth',
                block: 'start',
            }),
        );
    };
    return (
        <SectionsContext.Provider value={register}>
            <form
                {...props}
                onInvalidCapture={(event) => {
                    shouldFocusError.current = true;
                    (event.target as HTMLElement).setAttribute('data-native-invalid', 'true');
                    props.onInvalidCapture?.(event);
                }}
                onInputCapture={(event) => {
                    const control = event.target;
                    if (control instanceof HTMLInputElement && control.validity.valid) control.removeAttribute('data-native-invalid');
                    props.onInputCapture?.(event);
                }}
                onSubmitCapture={(event) => {
                    shouldFocusError.current = true;
                    formRef.current
                        ?.querySelectorAll<HTMLButtonElement>('[data-section-trigger][aria-expanded="false"]')
                        .forEach((button) => button.click());
                    props.onSubmitCapture?.(event);
                    if (fieldErrors.length) {
                        shouldFocusError.current = false;
                        jumpToError(fieldErrors[0].target);
                    }
                }}
                ref={formRef}
                className={cn('workflow-form flex min-h-full flex-1 flex-col', className)}
            >
                <div className="px-5 pt-5 pb-4 md:px-8 md:pt-7">
                    <WorkflowIntro title={title} description={description} readOnly={readOnly} />
                    {!readOnly && (
                        <details className="border-border bg-card mt-3 rounded-2xl border px-4 py-3 text-xs">
                            <summary className="text-primary cursor-pointer font-semibold">
                                <BookOpen className="mr-2 inline size-4" />
                                Baru menggunakan form ini? Buka panduan istilah
                            </summary>
                            <dl className="mt-4 grid gap-4 md:grid-cols-2">
                                {TERMS.map(([term, definition]) => (
                                    <div key={term}>
                                        <dt className="font-bold">{term}</dt>
                                        <dd className="text-muted-foreground mt-1 leading-relaxed">{definition}</dd>
                                    </div>
                                ))}
                            </dl>
                            <p className="border-border text-muted-foreground mt-4 border-t pt-3 leading-relaxed">
                                Isi berdasarkan pemeriksaan aktual. Pilihan negatif menandai temuan yang perlu ditinjau. Data disimpan ketika Anda
                                menekan tombol simpan di bawah.
                            </p>
                        </details>
                    )}
                    {fieldErrors.length > 0 && (
                        <div
                            aria-label="Ringkasan kesalahan"
                            className="mt-4 rounded-2xl border border-red-200 bg-red-50 p-4 text-red-900 dark:border-red-900 dark:bg-red-950/30 dark:text-red-200"
                        >
                            <p className="flex items-center gap-2 text-sm font-bold">
                                <AlertCircle className="size-4" />
                                Ada {fieldErrors.length} isian yang perlu diperiksa
                            </p>
                            <p className="mt-1 text-xs">Klik isian berikut untuk langsung menuju letak kesalahannya.</p>
                            <ul className="mt-3 grid gap-2 md:grid-cols-2">
                                {fieldErrors.map((error, index) => (
                                    <li key={index}>
                                        <button
                                            type="button"
                                            onClick={() => jumpToError(error.target)}
                                            className="bg-card/70 hover:bg-card flex w-full items-start justify-between gap-3 rounded-xl p-3 text-left text-xs focus-visible:outline-2 focus-visible:outline-red-500"
                                        >
                                            <span>
                                                <strong>{error.label}</strong>
                                                <span className="mt-1 block">{error.message}</span>
                                            </span>
                                            <ArrowRight className="size-4 shrink-0" />
                                        </button>
                                    </li>
                                ))}
                            </ul>
                        </div>
                    )}
                </div>
                {sections.length > 0 && (
                    <nav
                        aria-label="Navigasi bagian form"
                        className="workflow-section-nav border-border bg-card/95 sticky top-0 z-20 mx-5 mb-4 flex items-center gap-2 overflow-x-auto rounded-2xl border p-2 shadow-sm backdrop-blur-xl md:mx-8"
                    >
                        {sections.map((section, index) => (
                            <button
                                key={section.id}
                                type="button"
                                onClick={() => navigate(section.id)}
                                aria-current={active === section.id ? 'location' : undefined}
                                className={cn(
                                    'hover:bg-muted focus-visible:outline-primary flex shrink-0 items-center gap-2 rounded-xl px-3 py-2 text-xs font-semibold transition-colors focus-visible:outline-2',
                                    active === section.id ? 'bg-primary/10 text-primary' : 'text-muted-foreground',
                                )}
                            >
                                <span
                                    className={cn(
                                        'flex size-5 items-center justify-center rounded-full text-[10px]',
                                        section.complete ? 'bg-emerald-100 text-emerald-700' : 'bg-muted text-muted-foreground',
                                    )}
                                >
                                    {section.complete ? <Check className="size-3" /> : index + 1}
                                </span>
                                {section.title}
                                {section.progress && <span className="sr-only">{section.progress}</span>}
                            </button>
                        ))}
                        <span className="text-muted-foreground ml-auto hidden shrink-0 px-2 xl:block">
                            <ArrowDown className="size-4" />
                        </span>
                    </nav>
                )}
                {children}
            </form>
        </SectionsContext.Provider>
    );
}
