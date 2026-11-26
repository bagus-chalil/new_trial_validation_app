import { Form, Head, Link } from '@inertiajs/react';
import { useState } from 'react';
import { Combobox } from '@/components/combobox';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { MixingWizardSteps } from '@/components/mixing-wizard-steps';
import { StickyActionBar } from '@/components/sticky-action-bar';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { useTranslation } from '@/hooks/use-translation';
import { mixingUrl } from '@/lib/validation-steps';

type Mixing = {
    id: number;
    product_id: number | null;
    product_type: string;
    batch_no: string;
    batch_size_kg: string | null;
    mixing_machine: string;
    mixing_date: string | null;
    objective: string | null;
    batch_record_no: string;
    batch_record_revision: string;
    revision_summary: string | null;
    progress_status: string;
    current_step: string | null;
    files_count: number;
};

type PageProps = {
    mixing: Mixing | null;
    caseId: number | null;
    caseNo: string | null;
    defaultProductId: number | null;
    canEdit?: boolean;
    products: { value: string; label: string }[];
    productTypes: string[];
};

export default function MixingForm({
    mixing,
    caseId,
    caseNo,
    defaultProductId,
    canEdit = true,
    products,
    productTypes,
}: PageProps) {
    const { t } = useTranslation();
    const [productId, setProductId] = useState(
        String(mixing?.product_id ?? defaultProductId ?? ''),
    );
    const [productType, setProductType] = useState(mixing?.product_type ?? '');

    return (
        <>
            <Head title={mixing ? t('mixing.edit_title') : t('mixing.new')} />
            <div className="mx-auto max-w-6xl space-y-6 p-4">
                <Heading
                    title={mixing ? t('mixing.edit_title') : t('mixing.new')}
                    description={
                        caseNo ? `${t('mixing.form.case')}: ${caseNo}` : ''
                    }
                />
                <MixingWizardSteps currentStep={1} mixing={mixing} />

                <Form
                    action={
                        mixing ? mixingUrl.show(mixing.id) : mixingUrl.index()
                    }
                    method={mixing ? 'put' : 'post'}
                    className="space-y-6"
                >
                    {({ errors, processing }) => (
                        <>
                            {caseId !== null && !mixing && (
                                <input
                                    type="hidden"
                                    name="case_id"
                                    value={caseId}
                                />
                            )}
                            <input
                                type="hidden"
                                name="product_id"
                                value={productId}
                            />
                            <input
                                type="hidden"
                                name="product_type"
                                value={productType}
                            />

                            <Card>
                                <CardHeader>
                                    <CardTitle>
                                        {t('mixing.form.section_info')}
                                    </CardTitle>
                                </CardHeader>
                                <CardContent className="grid gap-4 sm:grid-cols-2">
                                    <div className="grid min-w-0 gap-2">
                                        <Label>
                                            {t('mixing.fields.product')}
                                        </Label>
                                        <Combobox
                                            options={products}
                                            value={productId}
                                            onChange={setProductId}
                                            placeholder={t(
                                                'mixing.form.select_product',
                                            )}
                                            disabled={!canEdit}
                                        />
                                        <InputError
                                            message={errors.product_id}
                                        />
                                    </div>
                                    <div className="grid min-w-0 gap-2">
                                        <Label>
                                            {t('mixing.fields.product_type')}
                                        </Label>
                                        <Select
                                            value={productType}
                                            onValueChange={setProductType}
                                            disabled={!canEdit}
                                        >
                                            <SelectTrigger>
                                                <SelectValue
                                                    placeholder={t(
                                                        'mixing.form.select_type',
                                                    )}
                                                />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {productTypes.map((type) => (
                                                    <SelectItem
                                                        key={type}
                                                        value={type}
                                                    >
                                                        {type}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                        <InputError
                                            message={errors.product_type}
                                        />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label htmlFor="batch_no">
                                            {t('mixing.fields.batch_no')}
                                        </Label>
                                        <Input
                                            id="batch_no"
                                            name="batch_no"
                                            defaultValue={mixing?.batch_no}
                                            disabled={!canEdit}
                                        />
                                        <InputError message={errors.batch_no} />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label htmlFor="batch_size_kg">
                                            {t('mixing.fields.batch_size_kg')}
                                        </Label>
                                        <Input
                                            id="batch_size_kg"
                                            name="batch_size_kg"
                                            type="number"
                                            step="0.01"
                                            defaultValue={
                                                mixing?.batch_size_kg ?? ''
                                            }
                                            disabled={!canEdit}
                                        />
                                        <InputError
                                            message={errors.batch_size_kg}
                                        />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label htmlFor="mixing_machine">
                                            {t('mixing.fields.mixing_machine')}
                                        </Label>
                                        <Input
                                            id="mixing_machine"
                                            name="mixing_machine"
                                            defaultValue={
                                                mixing?.mixing_machine
                                            }
                                            disabled={!canEdit}
                                        />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label htmlFor="mixing_date">
                                            {t('mixing.fields.mixing_date')}
                                        </Label>
                                        <Input
                                            id="mixing_date"
                                            name="mixing_date"
                                            type="date"
                                            defaultValue={
                                                mixing?.mixing_date ?? ''
                                            }
                                            disabled={!canEdit}
                                        />
                                        <InputError
                                            message={errors.mixing_date}
                                        />
                                    </div>
                                    <div className="grid gap-2 sm:col-span-2">
                                        <Label htmlFor="objective">
                                            {t('mixing.fields.objective')}
                                        </Label>
                                        <Textarea
                                            id="objective"
                                            name="objective"
                                            rows={3}
                                            defaultValue={
                                                mixing?.objective ?? ''
                                            }
                                            disabled={!canEdit}
                                        />
                                    </div>
                                </CardContent>
                            </Card>

                            <Card>
                                <CardHeader>
                                    <CardTitle>
                                        {t('mixing.form.section_batch_record')}
                                    </CardTitle>
                                    <p className="text-sm text-muted-foreground">
                                        {t('mixing.form.batch_record_hint')}
                                    </p>
                                </CardHeader>
                                <CardContent className="grid gap-4 sm:grid-cols-2">
                                    <div className="grid gap-2">
                                        <Label htmlFor="batch_record_no">
                                            {t('mixing.fields.batch_record_no')}
                                        </Label>
                                        <Input
                                            id="batch_record_no"
                                            name="batch_record_no"
                                            defaultValue={
                                                mixing?.batch_record_no
                                            }
                                            disabled={!canEdit}
                                        />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label htmlFor="batch_record_revision">
                                            {t(
                                                'mixing.fields.batch_record_revision',
                                            )}
                                        </Label>
                                        <Input
                                            id="batch_record_revision"
                                            name="batch_record_revision"
                                            defaultValue={
                                                mixing?.batch_record_revision
                                            }
                                            disabled={!canEdit}
                                        />
                                    </div>
                                    <div className="grid gap-2 sm:col-span-2">
                                        <Label htmlFor="revision_summary">
                                            {t(
                                                'mixing.fields.revision_summary',
                                            )}
                                        </Label>
                                        <Textarea
                                            id="revision_summary"
                                            name="revision_summary"
                                            rows={3}
                                            defaultValue={
                                                mixing?.revision_summary ?? ''
                                            }
                                            disabled={!canEdit}
                                        />
                                    </div>
                                </CardContent>
                            </Card>

                            <StickyActionBar>
                                <Button variant="outline" asChild>
                                    <Link
                                        href={
                                            mixing
                                                ? mixingUrl.show(mixing.id)
                                                : mixingUrl.index()
                                        }
                                    >
                                        {t('mixing.form.cancel')}
                                    </Link>
                                </Button>
                                {canEdit && (
                                    <Button type="submit" disabled={processing}>
                                        {t('mixing.form.save_continue')}
                                    </Button>
                                )}
                            </StickyActionBar>
                        </>
                    )}
                </Form>
            </div>
        </>
    );
}

MixingForm.layout = {
    breadcrumbs: [
        { title: 'Mixing', href: '/mixings' },
        { title: 'Form', href: '#' },
    ],
};
