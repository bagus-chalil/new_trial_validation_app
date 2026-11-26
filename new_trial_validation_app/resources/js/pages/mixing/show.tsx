import { Form, Head, Link } from '@inertiajs/react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { Textarea } from '@/components/ui/textarea';
import { useTranslation } from '@/hooks/use-translation';
import { mixingStatusClassName, mixingUrl } from '@/lib/validation-steps';

type PageProps = {
    mixing: {
        id: number;
        mixing_code: string;
        product_name: string;
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
        pending_with: string;
        case_id: number | null;
        approved_by: string | null;
        approved_at: string | null;
        rejected_by: string | null;
        approval_comment: string | null;
        approver_name: string | null;
        final_decision: string | null;
    };
    caseNo: string | null;
    results: {
        parameter_name: string;
        specification: string | null;
        decision: string;
        result_value: string | null;
        remark: string | null;
    }[];
    files: {
        id: number;
        category: string;
        original_name: string;
        caption: string | null;
    }[];
    reviews: {
        department: string;
        review_round: number;
        status: string;
        reviewer_name: string | null;
        comment: string | null;
        reviewed_at: string | null;
    }[];
    myPendingReviews: { id: number; department: string }[];
    canApprove: boolean;
    canEdit: boolean;
};

function Field({ label, value }: { label: string; value: string | null }) {
    return (
        <div>
            <p className="text-sm text-muted-foreground">{label}</p>
            <p className="font-medium">{value || '-'}</p>
        </div>
    );
}

export default function MixingShow({
    mixing,
    caseNo,
    results,
    files,
    reviews,
    myPendingReviews,
    canApprove,
    canEdit,
}: PageProps) {
    const { t } = useTranslation();

    return (
        <>
            <Head title={mixing.mixing_code} />
            <div className="mx-auto max-w-6xl space-y-6 p-4">
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div className="flex items-center gap-3">
                        <Heading
                            title={`${mixing.mixing_code} · ${mixing.product_name}`}
                            description={mixing.product_type}
                        />
                        <Badge
                            className={mixingStatusClassName(
                                mixing.progress_status,
                            )}
                        >
                            {t(`mixing.status.${mixing.progress_status}`)}
                        </Badge>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        <Button variant="outline" asChild>
                            <Link href={mixingUrl.index()}>
                                {t('mixing.show.back')}
                            </Link>
                        </Button>
                        {mixing.case_id && (
                            <Button variant="outline" asChild>
                                <Link href={`/journey/${mixing.case_id}`}>
                                    {t('mixing.show.journey')}
                                </Link>
                            </Button>
                        )}
                        {canEdit && (
                            <Button asChild>
                                <Link href={mixingUrl.edit(mixing.id)}>
                                    {mixing.progress_status === 'Draft'
                                        ? t('mixing.show.continue')
                                        : t('mixing.show.edit')}
                                </Link>
                            </Button>
                        )}
                    </div>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>{t('mixing.show.summary')}</CardTitle>
                    </CardHeader>
                    <CardContent className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        <Field label="Case" value={caseNo} />
                        <Field
                            label={t('mixing.fields.batch_no')}
                            value={mixing.batch_no}
                        />
                        <Field
                            label={t('mixing.fields.batch_size_kg')}
                            value={mixing.batch_size_kg}
                        />
                        <Field
                            label={t('mixing.fields.mixing_date')}
                            value={mixing.mixing_date}
                        />
                        <Field
                            label={t('mixing.fields.mixing_machine')}
                            value={mixing.mixing_machine}
                        />
                        <Field
                            label={t('mixing.fields.batch_record_no')}
                            value={mixing.batch_record_no}
                        />
                        <Field
                            label={t('mixing.fields.batch_record_revision')}
                            value={mixing.batch_record_revision}
                        />
                        <Field
                            label={t('mixing.show.waiting')}
                            value={mixing.pending_with}
                        />
                        <div className="sm:col-span-2 lg:col-span-4">
                            <Field
                                label={t('mixing.fields.objective')}
                                value={mixing.objective}
                            />
                        </div>
                        <div className="sm:col-span-2 lg:col-span-4">
                            <Field
                                label={t('mixing.fields.revision_summary')}
                                value={mixing.revision_summary}
                            />
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>{t('mixing.show.results')}</CardTitle>
                    </CardHeader>
                    <CardContent>
                        {results.length === 0 ? (
                            <p className="text-sm text-muted-foreground">
                                {t('mixing.show.no_results')}
                            </p>
                        ) : (
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>
                                            {t('mixing.validation.parameter')}
                                        </TableHead>
                                        <TableHead>
                                            {t(
                                                'mixing.validation.specification',
                                            )}
                                        </TableHead>
                                        <TableHead>
                                            {t('mixing.validation.decision')}
                                        </TableHead>
                                        <TableHead>
                                            {t('mixing.validation.result')}
                                        </TableHead>
                                        <TableHead>
                                            {t('mixing.validation.remark')}
                                        </TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {results.map((r) => (
                                        <TableRow
                                            key={r.parameter_name}
                                            className={
                                                r.decision === 'NOT OK'
                                                    ? 'bg-destructive/10'
                                                    : ''
                                            }
                                        >
                                            <TableCell className="font-medium">
                                                {r.parameter_name}
                                            </TableCell>
                                            <TableCell className="text-muted-foreground">
                                                {r.specification}
                                            </TableCell>
                                            <TableCell>{r.decision}</TableCell>
                                            <TableCell>
                                                {r.result_value}
                                            </TableCell>
                                            <TableCell>{r.remark}</TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        )}
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>{t('mixing.show.files')}</CardTitle>
                    </CardHeader>
                    <CardContent>
                        {files.length === 0 ? (
                            <p className="text-sm text-muted-foreground">
                                {t('mixing.show.no_files')}
                            </p>
                        ) : (
                            <ul className="divide-y text-sm">
                                {files.map((f) => (
                                    <li key={f.id} className="py-2">
                                        <a
                                            href={mixingUrl.file(
                                                mixing.id,
                                                f.id,
                                            )}
                                            target="_blank"
                                            rel="noreferrer"
                                            className="font-medium underline-offset-4 hover:underline"
                                        >
                                            {f.original_name}
                                        </a>
                                        <span className="ml-2 text-xs text-muted-foreground">
                                            {t(
                                                `mixing.attachments.category_names.${f.category}`,
                                            )}
                                            {f.caption ? ` · ${f.caption}` : ''}
                                        </span>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>{t('mixing.show.reviews')}</CardTitle>
                    </CardHeader>
                    <CardContent>
                        {reviews.length === 0 ? (
                            <p className="text-sm text-muted-foreground">
                                {t('mixing.show.no_reviews')}
                            </p>
                        ) : (
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>
                                            {t('mixing.show.department')}
                                        </TableHead>
                                        <TableHead>
                                            {t('mixing.show.round')}
                                        </TableHead>
                                        <TableHead>
                                            {t('mixing.list.status')}
                                        </TableHead>
                                        <TableHead>
                                            {t('mixing.show.reviewer')}
                                        </TableHead>
                                        <TableHead>
                                            {t('mixing.show.comment')}
                                        </TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {reviews.map((r) => (
                                        <TableRow
                                            key={`${r.review_round}-${r.department}`}
                                        >
                                            <TableCell>
                                                {r.department}
                                            </TableCell>
                                            <TableCell>
                                                {r.review_round}
                                            </TableCell>
                                            <TableCell>{r.status}</TableCell>
                                            <TableCell>
                                                {r.reviewer_name}
                                            </TableCell>
                                            <TableCell>{r.comment}</TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        )}
                    </CardContent>
                </Card>

                {myPendingReviews.map((review) => (
                    <Card key={review.id}>
                        <CardHeader>
                            <CardTitle>
                                {t('mixing.show.your_review', {
                                    department: review.department,
                                })}
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <Form
                                action={`/mixings/reviews/${review.id}`}
                                method="post"
                                className="space-y-3"
                            >
                                {({ errors, processing }) => (
                                    <>
                                        <Textarea
                                            name="comment"
                                            rows={3}
                                            required
                                        />
                                        <InputError message={errors.comment} />
                                        <Button
                                            type="submit"
                                            disabled={processing}
                                        >
                                            {t('mixing.show.submit_review')}
                                        </Button>
                                    </>
                                )}
                            </Form>
                        </CardContent>
                    </Card>
                ))}

                {canApprove && (
                    <Card>
                        <CardHeader>
                            <CardTitle>
                                {t('mixing.show.decide_title')}
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <Form
                                action={`/mixings/${mixing.id}/decision`}
                                method="post"
                                className="space-y-3"
                            >
                                {({ errors, processing }) => (
                                    <>
                                        <div className="grid gap-2">
                                            <Label htmlFor="approval_comment">
                                                {t(
                                                    'mixing.show.approval_comment',
                                                )}
                                            </Label>
                                            <Textarea
                                                id="approval_comment"
                                                name="approval_comment"
                                                rows={3}
                                            />
                                            <InputError
                                                message={
                                                    errors.approval_comment
                                                }
                                            />
                                        </div>
                                        <div className="grid max-w-sm gap-2">
                                            <Label htmlFor="signature_password">
                                                {t('mixing.show.password')}
                                            </Label>
                                            <Input
                                                id="signature_password"
                                                name="signature_password"
                                                type="password"
                                                autoComplete="current-password"
                                            />
                                            <InputError
                                                message={
                                                    errors.signature_password
                                                }
                                            />
                                        </div>
                                        <InputError message={errors.decision} />
                                        <div className="flex flex-wrap gap-2">
                                            <Button
                                                type="submit"
                                                name="decision"
                                                value="Approved"
                                                disabled={processing}
                                            >
                                                {t('mixing.show.approve')}
                                            </Button>
                                            <Button
                                                type="submit"
                                                name="decision"
                                                value="Need Revision"
                                                variant="outline"
                                                disabled={processing}
                                            >
                                                {t('mixing.show.need_revision')}
                                            </Button>
                                            <Button
                                                type="submit"
                                                name="decision"
                                                value="Rejected"
                                                variant="destructive"
                                                disabled={processing}
                                            >
                                                {t('mixing.show.reject')}
                                            </Button>
                                        </div>
                                    </>
                                )}
                            </Form>
                        </CardContent>
                    </Card>
                )}

                {mixing.final_decision && (
                    <Card>
                        <CardHeader>
                            <CardTitle>{t('mixing.show.decision')}</CardTitle>
                        </CardHeader>
                        <CardContent className="grid gap-4 sm:grid-cols-3">
                            <Field
                                label={
                                    mixing.final_decision === 'Approved'
                                        ? t('mixing.show.approved_by')
                                        : t('mixing.show.rejected_by')
                                }
                                value={
                                    mixing.final_decision === 'Approved'
                                        ? mixing.approved_by
                                        : mixing.rejected_by
                                }
                            />
                            <Field
                                label={t('mixing.list.status')}
                                value={t(
                                    `mixing.status.${mixing.progress_status}`,
                                )}
                            />
                            <Field
                                label={t('mixing.show.comment')}
                                value={mixing.approval_comment}
                            />
                        </CardContent>
                    </Card>
                )}
            </div>
        </>
    );
}

MixingShow.layout = {
    breadcrumbs: [
        { title: 'Mixing', href: '/mixings' },
        { title: 'Detail', href: '#' },
    ],
};
