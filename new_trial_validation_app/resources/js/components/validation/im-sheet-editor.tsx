import { router } from '@inertiajs/react';
import { FileText, ImagePlus, Plus, Trash2, X } from 'lucide-react';
import { useRef, useState } from 'react';
import type { ReactNode } from 'react';
import ValidationImRevisionController from '@/actions/App/Http/Controllers/ValidationImRevisionController';
import { ConfirmDialog } from '@/components/confirm-dialog';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { useTranslation } from '@/hooks/use-translation';
import { cn } from '@/lib/utils';

export type ImParam = {
    section: string;
    code: string | null;
    name: string;
    category: string | null;
    description: string | null;
    specification: string | null;
    frequency: string | null;
    value_min: string | null;
    value_max: string | null;
    unit: string | null;
};

export type ImSheetFile = {
    id: number;
    category: string;
    caption: string;
    original_name: string;
    mime: string;
};

export type ImSheetHeader = {
    description: string;
    appearance: string;
    mfg_category: string;
    customer: string;
    customer_ref: string;
    line_label: string;
    revision_reason: string;
    legacy_reference: string;
    issue_date: string;
    effective_from: string;
};

type Props = {
    page: 'p1' | 'p2';
    revision: {
        id: number;
        im_number: string;
        fg_code: string;
        replaces_number: string | null;
    };
    editable: boolean;
    header: ImSheetHeader;
    onHeader: (h: ImSheetHeader) => void;
    bulk: string;
    onBulk: (v: string) => void;
    content: Record<string, string>;
    onContent: (c: Record<string, string>) => void;
    params: ImParam[];
    onParams: (p: ImParam[]) => void;
    files: ImSheetFile[];
    errors: Record<string, string>;
};

const CELL =
    'w-full min-w-0 rounded-sm border border-transparent bg-transparent px-1.5 py-1 text-sm outline-none transition placeholder:text-muted-foreground/50 hover:border-border focus:border-brand focus:bg-background disabled:cursor-default disabled:opacity-100 disabled:hover:border-transparent';
const AREA = `${CELL} field-sizing-content min-h-7 resize-none`;

const BAR = {
    red: 'bg-red-500 text-white dark:bg-red-900',
    green: 'bg-emerald-400 text-emerald-950 dark:bg-emerald-900 dark:text-emerald-50',
    blue: 'bg-sky-100 text-sky-950 dark:bg-sky-950 dark:text-sky-100',
    orange: 'bg-orange-100 text-orange-950 dark:bg-orange-950 dark:text-orange-100',
};

function emptyParam(section: string): ImParam {
    return {
        section,
        code: '',
        name: '',
        category: '',
        description: '',
        specification: '',
        frequency: '',
        value_min: '',
        value_max: '',
        unit: section === 'weight' ? 'Gram' : '',
    };
}

function Bar({
    tone,
    children,
}: {
    tone: keyof typeof BAR;
    children: ReactNode;
}) {
    return (
        <div
            className={cn(
                'px-2 py-1 text-xs font-bold tracking-wide',
                BAR[tone],
            )}
        >
            {children}
        </div>
    );
}

/** Red-bordered note box of the paper IM ("Point Filling", "Point Appearance"...). */
function PointBox({ title, children }: { title: string; children: ReactNode }) {
    return (
        <div className="rounded-sm border border-red-400/70 p-2">
            <h5 className="mb-1 text-xs font-bold text-red-600 underline dark:text-red-400">
                {title}
            </h5>
            {children}
        </div>
    );
}

export function ImSheetEditor({
    page,
    revision,
    editable,
    header,
    onHeader,
    bulk,
    onBulk,
    content,
    onContent,
    params,
    onParams,
    files,
    errors,
}: Props) {
    const { t } = useTranslation();

    const rows = (section: string) =>
        params
            .map((p, i) => ({ p, i }))
            .filter(({ p }) => p.section === section);

    const patch = (i: number, change: Partial<ImParam>) =>
        onParams(params.map((p, idx) => (idx === i ? { ...p, ...change } : p)));
    const add = (section: string) => onParams([...params, emptyParam(section)]);
    const remove = (i: number) =>
        onParams(params.filter((_, idx) => idx !== i));

    const head = (key: keyof ImSheetHeader, className?: string) => (
        <input
            className={cn(CELL, className)}
            value={header[key]}
            disabled={!editable}
            onChange={(e) => onHeader({ ...header, [key]: e.target.value })}
        />
    );

    const text = (key: string, rowsN = 1, placeholder?: string) => (
        <textarea
            className={AREA}
            rows={rowsN}
            value={content[key] ?? ''}
            placeholder={placeholder}
            disabled={!editable}
            onChange={(e) => onContent({ ...content, [key]: e.target.value })}
        />
    );

    const addBtn = (section: string) =>
        editable && (
            <Button
                type="button"
                size="sm"
                variant="ghost"
                className="mt-1 h-7 gap-1 text-xs text-muted-foreground"
                onClick={() => add(section)}
            >
                <Plus className="size-3" />
                {t('im_master.add_row')}
            </Button>
        );

    const delBtn = (i: number) =>
        editable && (
            <button
                type="button"
                aria-label={t('im_master.remove_row')}
                onClick={() => remove(i)}
                className="shrink-0 rounded p-1 text-muted-foreground opacity-0 transition group-hover:opacity-100 hover:text-destructive focus:opacity-100"
            >
                <Trash2 className="size-3.5" />
            </button>
        );

    /** Numbered list rows used by the point boxes. */
    const pointList = (section: string, withDetail = true) => (
        <>
            <ol className="space-y-0.5">
                {rows(section).map(({ p, i }, n) => (
                    <li key={i} className="group flex items-start gap-1">
                        <span className="w-4 shrink-0 pt-1 text-right text-xs text-muted-foreground">
                            {n + 1}.
                        </span>
                        <div className="min-w-0 flex-1">
                            <textarea
                                rows={1}
                                className={AREA}
                                value={p.name}
                                disabled={!editable}
                                onChange={(e) =>
                                    patch(i, { name: e.target.value })
                                }
                            />
                            {withDetail && (p.description || editable) && (
                                <textarea
                                    rows={1}
                                    className={cn(
                                        AREA,
                                        'text-xs text-muted-foreground',
                                    )}
                                    placeholder={t('im_master.cols.detail')}
                                    value={p.description ?? ''}
                                    disabled={!editable}
                                    onChange={(e) =>
                                        patch(i, {
                                            description: e.target.value,
                                        })
                                    }
                                />
                            )}
                        </div>
                        {delBtn(i)}
                    </li>
                ))}
            </ol>
            {addBtn(section)}
        </>
    );

    /** Surveillance table (B), with the same four columns as the paper. */
    const surveillance = (section: string, titleKey: string) => (
        <div className="overflow-x-auto">
            <table className="w-full border-collapse text-sm">
                <thead>
                    <tr
                        className={cn(
                            'text-center text-xs font-bold',
                            BAR.orange,
                        )}
                    >
                        <th className="w-[30%] border px-1 py-1">
                            {t(titleKey)}
                        </th>
                        <th className="border px-1 py-1">
                            {t('im_master.pdf.work_description')}
                        </th>
                        <th className="w-[14%] border px-1 py-1">
                            {t('im_master.pdf.specification')}
                        </th>
                        <th className="w-[20%] border px-1 py-1">
                            {t('im_master.pdf.frequency')}
                        </th>
                        {editable && <th className="w-6 border" />}
                    </tr>
                </thead>
                <tbody>
                    {rows(section).map(({ p, i }, n) => (
                        <tr key={i} className="group align-top">
                            <td className="border p-0.5">
                                <textarea
                                    rows={1}
                                    className={AREA}
                                    value={p.name}
                                    placeholder={`${n + 1}.`}
                                    disabled={!editable}
                                    onChange={(e) =>
                                        patch(i, { name: e.target.value })
                                    }
                                />
                            </td>
                            <td className="border p-0.5">
                                <textarea
                                    rows={1}
                                    className={AREA}
                                    value={p.description ?? ''}
                                    disabled={!editable}
                                    onChange={(e) =>
                                        patch(i, {
                                            description: e.target.value,
                                        })
                                    }
                                />
                            </td>
                            <td className="border p-0.5">
                                <textarea
                                    rows={1}
                                    className={AREA}
                                    value={p.specification ?? ''}
                                    disabled={!editable}
                                    onChange={(e) =>
                                        patch(i, {
                                            specification: e.target.value,
                                        })
                                    }
                                />
                            </td>
                            <td className="border p-0.5">
                                <textarea
                                    rows={1}
                                    className={AREA}
                                    value={p.frequency ?? ''}
                                    disabled={!editable}
                                    onChange={(e) =>
                                        patch(i, { frequency: e.target.value })
                                    }
                                />
                            </td>
                            {editable && (
                                <td className="border">{delBtn(i)}</td>
                            )}
                        </tr>
                    ))}
                </tbody>
            </table>
            {addBtn(section)}
        </div>
    );

    const slot = (category: string, label: string) => (
        <ImageSlot
            revisionId={revision.id}
            category={category}
            label={label}
            files={files.filter((f) => f.category === category)}
            editable={editable}
        />
    );

    const headerBlock = (
        <div className="grid border md:grid-cols-[1.4fr_1fr_1fr]">
            <div className="border-b p-1 md:border-r">
                <div className="flex items-center gap-2 px-1 text-xs font-bold">
                    <span className="rounded bg-red-500 px-1.5 py-0.5 text-white">
                        COSMAX
                    </span>
                    PT COSMAX INDONESIA
                </div>
            </div>
            <div
                className={cn(
                    'border-b p-1 text-center text-sm font-bold md:border-r',
                    BAR.blue,
                )}
            >
                INSTRUKSI MUTU
            </div>
            <div className="border-b p-0.5 md:col-span-1">
                <div className="flex items-center bg-amber-200/70 px-1 dark:bg-amber-900/40">
                    {head('line_label', 'text-center font-bold')}
                </div>
            </div>

            <div className="space-y-0.5 p-1 md:border-r">
                <Labeled label={t('im_master.pdf.description')}>
                    {head('description')}
                </Labeled>
                <Labeled label={t('im_master.pdf.fg_code')}>
                    <span className="px-1.5 text-sm">{revision.fg_code}</span>
                    <input
                        className={CELL}
                        value={bulk}
                        placeholder={t('im_master.fields.bulk_wip_refs')}
                        disabled={!editable}
                        onChange={(e) => onBulk(e.target.value)}
                    />
                </Labeled>
                <Labeled label="Mfg.category">{head('mfg_category')}</Labeled>
                <Labeled label={t('im_master.pdf.appearance')}>
                    {head('appearance')}
                </Labeled>
                <Labeled label="Customer">{head('customer')}</Labeled>
                <Labeled label={t('im_master.pdf.customer_ref')}>
                    {head('customer_ref')}
                </Labeled>
            </div>
            <div className="space-y-0.5 p-1 md:border-r">
                <Labeled label={t('im_master.pdf.issue_date')}>
                    <input
                        type="date"
                        className={CELL}
                        value={header.issue_date}
                        disabled={!editable}
                        onChange={(e) =>
                            onHeader({ ...header, issue_date: e.target.value })
                        }
                    />
                </Labeled>
                <Labeled label={t('im_master.fields.effective_from')}>
                    <input
                        type="date"
                        className={CELL}
                        value={header.effective_from}
                        disabled={!editable}
                        onChange={(e) =>
                            onHeader({
                                ...header,
                                effective_from: e.target.value,
                            })
                        }
                    />
                </Labeled>
                <Labeled label={t('im_master.pdf.revision_reason')}>
                    {head('revision_reason')}
                </Labeled>
                <Labeled label={t('im_master.fields.legacy_reference')}>
                    {head('legacy_reference')}
                </Labeled>
                <Labeled label={t('im_master.pdf.replaces')}>
                    <span className="px-1.5 text-sm">
                        {revision.replaces_number ?? '—'}
                    </span>
                </Labeled>
            </div>
            <div className="flex flex-col items-center justify-center bg-yellow-200/80 p-2 text-center dark:bg-yellow-900/40">
                <span className="text-xs">{t('im_master.pdf.doc_no')}</span>
                <span className="text-sm font-bold break-all">
                    {revision.im_number}
                </span>
            </div>
        </div>
    );

    if (page === 'p2') {
        return (
            <div className="space-y-3">
                {headerBlock}
                <Bar tone="red">A. {t('im_master.pdf.fg_spec')}</Bar>
                <Bar tone="red">
                    A.4 {t('im_master.pdf.appearance_packing')}
                </Bar>

                <h4 className="text-sm font-bold">
                    {t('im_master.files.categories.secondary_packaging')}
                </h4>
                {slot(
                    'secondary_packaging',
                    t('im_master.files.categories.secondary_packaging'),
                )}

                <PointBox title={t('im_master.pdf.packing_process')}>
                    {pointList('packing_point')}
                </PointBox>

                <h4 className="text-sm font-bold">
                    {t('im_master.files.categories.tertiary_packaging')}
                </h4>
                {slot(
                    'tertiary_packaging',
                    t('im_master.files.categories.tertiary_packaging'),
                )}

                <div className="grid gap-2 md:grid-cols-2">
                    <PointBox title={t('im_master.pdf.master_box')}>
                        {text('master_box_qty', 1, '48 pcs')}
                    </PointBox>
                    <PointBox title={t('im_master.pdf.pallet')}>
                        {text(
                            'pallet_layout',
                            1,
                            '1 Pallet 5 Layer / 1 Layer 10 MB',
                        )}
                    </PointBox>
                    <PointBox title={t('im_master.pdf.shipper')}>
                        {text('shipper_format', 4)}
                    </PointBox>
                    <PointBox title={t('im_master.pdf.notes')}>
                        {text('notes', 4)}
                    </PointBox>
                </div>
            </div>
        );
    }

    return (
        <div className="space-y-3">
            {headerBlock}

            <div className="grid gap-3 lg:grid-cols-2">
                {/* Left column: A.1, points, A.3 */}
                <div className="space-y-2">
                    <Bar tone="red">A. {t('im_master.pdf.fg_spec')}</Bar>
                    <Bar tone="red">
                        A.1 {t('im_master.pdf.appearance_filling')}
                    </Bar>
                    <div className="overflow-x-auto">
                        <table className="w-full border-collapse text-sm">
                            <thead>
                                <tr
                                    className={cn(
                                        'text-center text-xs font-bold',
                                        BAR.blue,
                                    )}
                                >
                                    <th className="w-[22%] border px-1 py-1">
                                        {t('im_master.pdf.item_no')}
                                    </th>
                                    <th className="border px-1 py-1">
                                        {t('im_master.pdf.packaging_material')}
                                    </th>
                                    <th className="w-[22%] border px-1 py-1">
                                        {t('im_master.pdf.category')}
                                    </th>
                                    {editable && <th className="w-6 border" />}
                                </tr>
                            </thead>
                            <tbody>
                                {rows('bom').map(({ p, i }) => (
                                    <tr key={i} className="group align-top">
                                        <td className="border p-0.5">
                                            <input
                                                className={CELL}
                                                value={p.code ?? ''}
                                                disabled={!editable}
                                                onChange={(e) =>
                                                    patch(i, {
                                                        code: e.target.value,
                                                    })
                                                }
                                            />
                                        </td>
                                        <td className="border p-0.5">
                                            <textarea
                                                rows={1}
                                                className={AREA}
                                                value={p.name}
                                                disabled={!editable}
                                                onChange={(e) =>
                                                    patch(i, {
                                                        name: e.target.value,
                                                    })
                                                }
                                            />
                                        </td>
                                        <td className="border p-0.5">
                                            <input
                                                className={CELL}
                                                list="im-bom-category"
                                                value={p.category ?? ''}
                                                disabled={!editable}
                                                onChange={(e) =>
                                                    patch(i, {
                                                        category:
                                                            e.target.value,
                                                    })
                                                }
                                            />
                                        </td>
                                        {editable && (
                                            <td className="border">
                                                {delBtn(i)}
                                            </td>
                                        )}
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                        <datalist id="im-bom-category">
                            <option value="Primary Packaging" />
                            <option value="Secondary Packaging" />
                            <option value="Tersier Packaging" />
                        </datalist>
                        {addBtn('bom')}
                    </div>

                    <PointBox title={t('im_master.pdf.point_filling')}>
                        {pointList('filling_point', false)}
                    </PointBox>
                    <PointBox title={t('im_master.pdf.point_appearance')}>
                        {pointList('appearance_point', false)}
                    </PointBox>
                    {slot(
                        'appearance_filling',
                        t('im_master.files.categories.appearance_filling'),
                    )}

                    <Bar tone="red">
                        A.3 {t('im_master.pdf.weight_filling')}
                    </Bar>
                    <div className="flex items-center gap-1 text-sm font-bold">
                        {text(
                            'legal_note',
                            1,
                            t('im_master.content.legal_note'),
                        )}
                    </div>
                    <div className="overflow-x-auto">
                        <table className="w-full border-collapse text-sm">
                            <thead>
                                <tr
                                    className={cn(
                                        'text-center text-xs font-bold',
                                        BAR.blue,
                                    )}
                                >
                                    <th className="border px-1 py-1">
                                        {t('im_master.pdf.product_name')}
                                    </th>
                                    <th className="w-36 border px-1 py-1">
                                        {t('im_master.pdf.std_weight')}
                                    </th>
                                    <th className="border px-1 py-1">
                                        {t('im_master.pdf.bulk_code')}
                                    </th>
                                    <th className="border px-1 py-1">
                                        {t('im_master.pdf.fg_wip_code')}
                                    </th>
                                    {editable && <th className="w-6 border" />}
                                </tr>
                            </thead>
                            <tbody>
                                {rows('weight').map(({ p, i }) => (
                                    <tr key={i} className="group align-top">
                                        <td className="border p-0.5">
                                            <textarea
                                                rows={1}
                                                className={AREA}
                                                value={p.name}
                                                disabled={!editable}
                                                onChange={(e) =>
                                                    patch(i, {
                                                        name: e.target.value,
                                                    })
                                                }
                                            />
                                        </td>
                                        <td className="border p-0.5">
                                            <div className="flex items-center gap-0.5">
                                                <input
                                                    inputMode="decimal"
                                                    className={cn(
                                                        CELL,
                                                        'text-center font-bold',
                                                    )}
                                                    placeholder="min"
                                                    value={p.value_min ?? ''}
                                                    disabled={!editable}
                                                    onChange={(e) =>
                                                        patch(i, {
                                                            value_min:
                                                                e.target.value,
                                                        })
                                                    }
                                                />
                                                <span>-</span>
                                                <input
                                                    inputMode="decimal"
                                                    className={cn(
                                                        CELL,
                                                        'text-center font-bold',
                                                    )}
                                                    placeholder="max"
                                                    value={p.value_max ?? ''}
                                                    disabled={!editable}
                                                    onChange={(e) =>
                                                        patch(i, {
                                                            value_max:
                                                                e.target.value,
                                                        })
                                                    }
                                                />
                                            </div>
                                            <input
                                                className={cn(
                                                    CELL,
                                                    'text-center text-xs',
                                                )}
                                                value={p.unit ?? ''}
                                                disabled={!editable}
                                                onChange={(e) =>
                                                    patch(i, {
                                                        unit: e.target.value,
                                                    })
                                                }
                                            />
                                        </td>
                                        <td className="border p-0.5">
                                            <input
                                                className={cn(
                                                    CELL,
                                                    'text-center',
                                                )}
                                                value={p.code ?? ''}
                                                disabled={!editable}
                                                onChange={(e) =>
                                                    patch(i, {
                                                        code: e.target.value,
                                                    })
                                                }
                                            />
                                        </td>
                                        <td className="border p-0.5">
                                            <input
                                                className={cn(
                                                    CELL,
                                                    'text-center',
                                                )}
                                                value={p.category ?? ''}
                                                disabled={!editable}
                                                onChange={(e) =>
                                                    patch(i, {
                                                        category:
                                                            e.target.value,
                                                    })
                                                }
                                            />
                                        </td>
                                        {editable && (
                                            <td className="border">
                                                {delBtn(i)}
                                            </td>
                                        )}
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                        {addBtn('weight')}
                    </div>
                </div>

                {/* Right column: A.2 and B */}
                <div className="space-y-2">
                    <Bar tone="red">
                        A.2 {t('im_master.pdf.appearance_packing')}
                    </Bar>
                    {slot(
                        'primary_packaging',
                        t('im_master.files.categories.primary_packaging'),
                    )}

                    <PointBox title={t('im_master.pdf.point_batch')}>
                        <ul className="space-y-0.5">
                            {rows('coding').map(({ p, i }) => (
                                <li
                                    key={i}
                                    className="group flex items-start gap-1"
                                >
                                    <div className="min-w-0 flex-1 space-y-0.5">
                                        <input
                                            className={cn(CELL, 'font-bold')}
                                            value={p.name}
                                            disabled={!editable}
                                            onChange={(e) =>
                                                patch(i, {
                                                    name: e.target.value,
                                                })
                                            }
                                        />
                                        <textarea
                                            rows={1}
                                            className={cn(AREA, 'text-xs')}
                                            placeholder={t(
                                                'im_master.cols.detail',
                                            )}
                                            value={p.description ?? ''}
                                            disabled={!editable}
                                            onChange={(e) =>
                                                patch(i, {
                                                    description: e.target.value,
                                                })
                                            }
                                        />
                                        <input
                                            className={cn(CELL, 'text-xs')}
                                            placeholder={t(
                                                'im_master.cols.specification',
                                            )}
                                            value={p.specification ?? ''}
                                            disabled={!editable}
                                            onChange={(e) =>
                                                patch(i, {
                                                    specification:
                                                        e.target.value,
                                                })
                                            }
                                        />
                                    </div>
                                    {delBtn(i)}
                                </li>
                            ))}
                        </ul>
                        {addBtn('coding')}

                        <div className="mt-2 space-y-1 border-t pt-2">
                            {(
                                [
                                    ['batch_format_tube', 'Tube'],
                                    ['batch_format_unit_box', 'Unit Box'],
                                    ['batch_format_master_box', 'Master Box'],
                                ] as const
                            ).map(([key, label]) => (
                                <div key={key}>
                                    <span className="px-1.5 text-xs font-bold">
                                        {label}
                                    </span>
                                    {text(key, 1)}
                                </div>
                            ))}
                        </div>
                    </PointBox>

                    <Bar tone="green">B. {t('im_master.pdf.surveillance')}</Bar>
                    {surveillance(
                        'surveillance_initial',
                        'im_master.pdf.check_initial',
                    )}
                    {surveillance(
                        'surveillance_periodic',
                        'im_master.pdf.check_periodic',
                    )}
                </div>
            </div>

            {Object.keys(errors).length > 0 && (
                <InputError message={Object.values(errors)[0]} />
            )}
        </div>
    );
}

function Labeled({ label, children }: { label: string; children: ReactNode }) {
    return (
        <div className="flex items-center gap-1">
            <span className="w-24 shrink-0 text-xs font-semibold text-sky-700 dark:text-sky-300">
                {label}
            </span>
            <span className="text-xs text-muted-foreground">:</span>
            <div className="flex min-w-0 flex-1 items-center">{children}</div>
        </div>
    );
}

/**
 * One picture area of the paper IM. Pictures upload immediately (drop or
 * choose), each with a blue caption box like the paper annotations.
 */
export function ImageSlot({
    revisionId,
    category,
    label,
    files,
    editable,
}: {
    revisionId: number;
    category: string;
    label: string;
    files: ImSheetFile[];
    editable: boolean;
}) {
    const { t } = useTranslation();
    const input = useRef<HTMLInputElement>(null);
    const [caption, setCaption] = useState('');
    const [busy, setBusy] = useState(false);
    const [over, setOver] = useState(false);

    function upload(list: FileList | File[]) {
        const picked = Array.from(list);

        if (picked.length === 0) {
            return;
        }

        setBusy(true);
        router.post(
            ValidationImRevisionController.fileStore.url(revisionId),
            { category, caption, files: picked },
            {
                forceFormData: true,
                preserveScroll: true,
                preserveState: true,
                onSuccess: () => setCaption(''),
                onFinish: () => setBusy(false),
            },
        );
    }

    return (
        <div
            onDragOver={(e) => {
                if (editable) {
                    e.preventDefault();
                    setOver(true);
                }
            }}
            onDragLeave={() => setOver(false)}
            onDrop={(e) => {
                if (!editable) {
                    return;
                }

                e.preventDefault();
                setOver(false);
                upload(e.dataTransfer.files);
            }}
            className={cn(
                'rounded-md border border-dashed p-2 transition',
                over && 'border-brand bg-brand/5',
            )}
        >
            <div className="flex flex-wrap gap-3">
                {files.map((f) => {
                    const url = ValidationImRevisionController.fileShow.url({
                        revision: revisionId,
                        file: f.id,
                    });

                    return (
                        <figure
                            key={f.id}
                            className="group relative w-40 text-center"
                        >
                            {f.mime.startsWith('image/') ? (
                                <a href={url} target="_blank" rel="noreferrer">
                                    <img
                                        src={url}
                                        alt={f.caption || f.original_name}
                                        className="mx-auto max-h-40 object-contain"
                                    />
                                </a>
                            ) : (
                                <a
                                    href={url}
                                    target="_blank"
                                    rel="noreferrer"
                                    className="flex h-24 flex-col items-center justify-center gap-1 rounded border text-xs"
                                >
                                    <FileText className="size-6" />
                                    {f.original_name}
                                </a>
                            )}
                            {f.caption && (
                                <figcaption className="mt-1 rounded border border-sky-400 bg-sky-50 px-1.5 py-1 text-xs whitespace-pre-line dark:bg-sky-950/40">
                                    {f.caption}
                                </figcaption>
                            )}
                            {editable && (
                                <ConfirmDialog
                                    trigger={
                                        <button
                                            type="button"
                                            aria-label={t(
                                                'im_master.files.delete',
                                            )}
                                            className="absolute -top-1 -right-1 rounded-full border bg-background p-0.5 opacity-0 shadow transition group-hover:opacity-100 focus:opacity-100"
                                        >
                                            <X className="size-3.5" />
                                        </button>
                                    }
                                    title={t('im_master.files.delete')}
                                    description={f.original_name}
                                    formProps={ValidationImRevisionController.fileDestroy.form(
                                        { revision: revisionId, file: f.id },
                                    )}
                                />
                            )}
                        </figure>
                    );
                })}

                {files.length === 0 && (
                    <p className="flex min-h-16 flex-1 items-center justify-center text-xs text-muted-foreground">
                        {editable ? t('im_master.sheet.drop_hint') : '—'}
                    </p>
                )}
            </div>

            {editable && (
                <div className="mt-2 flex flex-wrap items-center gap-2">
                    <input
                        className={cn(CELL, 'max-w-72 border-border text-xs')}
                        placeholder={t('im_master.sheet.caption_ph')}
                        value={caption}
                        onChange={(e) => setCaption(e.target.value)}
                    />
                    <input
                        ref={input}
                        type="file"
                        multiple
                        hidden
                        accept="image/jpeg,image/png,image/webp,application/pdf"
                        onChange={(e) => {
                            if (e.target.files) {
                                upload(e.target.files);
                            }

                            e.target.value = '';
                        }}
                    />
                    <Button
                        type="button"
                        size="sm"
                        variant="outline"
                        className="gap-1"
                        disabled={busy}
                        onClick={() => input.current?.click()}
                    >
                        <ImagePlus className="size-3.5" />
                        {t('im_master.sheet.add_image')}
                    </Button>
                    <span className="text-xs text-muted-foreground">
                        {label}
                    </span>
                </div>
            )}
        </div>
    );
}
