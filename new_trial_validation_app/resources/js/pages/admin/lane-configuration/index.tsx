import { Form, Head } from '@inertiajs/react';
import { useState } from 'react';
import LaneConfigurationController from '@/actions/App/Http/Controllers/Admin/LaneConfigurationController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { MultiSelect } from '@/components/multi-select';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { useTranslation } from '@/hooks/use-translation';
import { index as laneConfigurationIndex } from '@/routes/admin/lane-configuration';

type ReviewTeam = { id: number; name: string; sort_order: number };

type Lane = {
    id: number;
    stage_key: string;
    label: string;
    required_team_id: number | null;
    signer_user_ids: number[];
};

type PageProps = {
    lanes: Lane[];
    reviewTeams: ReviewTeam[];
    users: { id: number; name: string }[];
};

function LaneEditDialog({
    open,
    onOpenChange,
    editingLane,
    reviewTeams,
    users,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    editingLane: Lane | null;
    reviewTeams: ReviewTeam[];
    users: { id: number; name: string }[];
}) {
    const { t } = useTranslation();
    const [signerIds, setSignerIds] = useState<string[]>([]);

    // Re-seed the picked signers each time a different lane is opened.
    const [seededFor, setSeededFor] = useState<number | null>(null);

    if (editingLane && seededFor !== editingLane.id) {
        setSeededFor(editingLane.id);
        setSignerIds(editingLane.signer_user_ids.map(String));
    }

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>{t('admin.lanes.edit_title')}</DialogTitle>
                </DialogHeader>
                {editingLane && (
                    <Form
                        {...LaneConfigurationController.update.form(
                            editingLane.id,
                        )}
                        options={{ preserveScroll: true }}
                        onSuccess={() => onOpenChange(false)}
                        className="grid gap-4"
                    >
                        {({ processing, errors }) => (
                            <>
                                <div className="grid gap-2">
                                    <Label htmlFor="label">
                                        {t('admin.lanes.label')}
                                    </Label>
                                    <Input
                                        id="label"
                                        name="label"
                                        required
                                        maxLength={100}
                                        defaultValue={editingLane.label}
                                    />
                                    <InputError message={errors.label} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="required_team_id">
                                        {t('admin.lanes.required_team')}
                                    </Label>
                                    <Select
                                        name="required_team_id"
                                        defaultValue={
                                            editingLane.required_team_id != null
                                                ? String(
                                                      editingLane.required_team_id,
                                                  )
                                                : '__none'
                                        }
                                    >
                                        <SelectTrigger id="required_team_id">
                                            <SelectValue
                                                placeholder={t(
                                                    'admin.lanes.all_users',
                                                )}
                                            />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="__none">
                                                {t(
                                                    'admin.lanes.all_users_unrestricted',
                                                )}
                                            </SelectItem>
                                            {reviewTeams.map((team) => (
                                                <SelectItem
                                                    key={team.id}
                                                    value={String(team.id)}
                                                >
                                                    {team.name}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    <p className="text-xs text-muted-foreground">
                                        {t('admin.lanes.required_team_hint')}
                                    </p>
                                    <InputError
                                        message={errors.required_team_id}
                                    />
                                </div>

                                <div className="grid gap-2">
                                    <Label>
                                        {t('admin.lanes.signer_users')}
                                    </Label>
                                    <MultiSelect
                                        options={users.map((u) => ({
                                            value: String(u.id),
                                            label: u.name,
                                        }))}
                                        value={signerIds}
                                        onChange={setSignerIds}
                                    />
                                    <input
                                        type="hidden"
                                        name="sync_signers"
                                        value="1"
                                    />
                                    {signerIds.map((id) => (
                                        <input
                                            key={id}
                                            type="hidden"
                                            name="signer_user_ids[]"
                                            value={id}
                                        />
                                    ))}
                                    <p className="text-xs text-muted-foreground">
                                        {t('admin.lanes.signer_users_hint')}
                                    </p>
                                    <InputError
                                        message={errors.signer_user_ids}
                                    />
                                </div>

                                <DialogFooter>
                                    <Button type="submit" disabled={processing}>
                                        {t('admin.actions.save_changes')}
                                    </Button>
                                </DialogFooter>
                            </>
                        )}
                    </Form>
                )}
            </DialogContent>
        </Dialog>
    );
}

export default function AdminLaneConfigurationIndex({
    lanes,
    reviewTeams,
    users,
}: PageProps) {
    const { t } = useTranslation();
    const [editingLane, setEditingLane] = useState<Lane | null>(null);

    const teamName = (teamId: number | null) =>
        teamId === null
            ? t('admin.lanes.all_users')
            : (reviewTeams.find((team) => team.id === teamId)?.name ?? '—');

    return (
        <>
            <Head title={t('admin.lanes.title')} />

            <div className="space-y-6 p-4">
                <Heading
                    title={t('admin.lanes.title')}
                    description={t('admin.lanes.description')}
                />

                <LaneEditDialog
                    open={editingLane !== null}
                    onOpenChange={(open) => {
                        if (!open) {
                            setEditingLane(null);
                        }
                    }}
                    editingLane={editingLane}
                    reviewTeams={reviewTeams}
                    users={users}
                />

                <Card>
                    <CardHeader>
                        <CardTitle>{t('admin.lanes.card_title')}</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>
                                        {t('admin.lanes.stage')}
                                    </TableHead>
                                    <TableHead>
                                        {t('admin.lanes.label_column')}
                                    </TableHead>
                                    <TableHead>
                                        {t('admin.lanes.required_team')}
                                    </TableHead>
                                    <TableHead>
                                        {t('admin.lanes.signers_column')}
                                    </TableHead>
                                    <TableHead>
                                        {t('admin.fields.action')}
                                    </TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {lanes.map((lane) => (
                                    <TableRow key={lane.id}>
                                        <TableCell className="font-mono text-xs">
                                            {lane.stage_key}
                                        </TableCell>
                                        <TableCell>{lane.label}</TableCell>
                                        <TableCell>
                                            {teamName(lane.required_team_id)}
                                        </TableCell>
                                        <TableCell className="max-w-xs text-sm">
                                            {lane.signer_user_ids.length > 0
                                                ? lane.signer_user_ids
                                                      .map(
                                                          (id) =>
                                                              users.find(
                                                                  (u) =>
                                                                      u.id ===
                                                                      id,
                                                              )?.name ?? id,
                                                      )
                                                      .join(', ')
                                                : '—'}
                                        </TableCell>
                                        <TableCell>
                                            <Button
                                                variant="outline"
                                                size="sm"
                                                onClick={() =>
                                                    setEditingLane(lane)
                                                }
                                            >
                                                {t('admin.actions.edit')}
                                            </Button>
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

AdminLaneConfigurationIndex.layout = {
    breadcrumbs: [
        { title: 'Lane Configuration', href: laneConfigurationIndex() },
    ],
};
