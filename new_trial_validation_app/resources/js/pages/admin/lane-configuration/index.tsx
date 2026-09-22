import { Form, Head } from '@inertiajs/react';
import { useState } from 'react';
import LaneConfigurationController from '@/actions/App/Http/Controllers/Admin/LaneConfigurationController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
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
import { index as laneConfigurationIndex } from '@/routes/admin/lane-configuration';

type ReviewTeam = { id: number; name: string; sort_order: number };

type Lane = {
    id: number;
    stage_key: string;
    label: string;
    required_team_id: number | null;
};

type PageProps = {
    lanes: Lane[];
    reviewTeams: ReviewTeam[];
};

function LaneEditDialog({
    open,
    onOpenChange,
    editingLane,
    reviewTeams,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    editingLane: Lane | null;
    reviewTeams: ReviewTeam[];
}) {
    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Edit Lane</DialogTitle>
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
                                        Label Tampilan
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
                                        Tim yang Wajib
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
                                            <SelectValue placeholder="Semua user" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="__none">
                                                Semua user (tidak dibatasi)
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
                                        User yang boleh ditugaskan/menyetujui di
                                        tahap ini harus tergabung di tim ini
                                        (Access Rights &rarr; Review Team).
                                        Pilih &quot;Semua user&quot; untuk tidak
                                        membatasi.
                                    </p>
                                    <InputError
                                        message={errors.required_team_id}
                                    />
                                </div>

                                <DialogFooter>
                                    <Button type="submit" disabled={processing}>
                                        Save Changes
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
}: PageProps) {
    const [editingLane, setEditingLane] = useState<Lane | null>(null);

    const teamName = (teamId: number | null) =>
        teamId === null
            ? 'Semua user'
            : (reviewTeams.find((t) => t.id === teamId)?.name ?? '—');

    return (
        <>
            <Head title="Lane Configuration" />

            <div className="space-y-6 p-4">
                <Heading
                    title="Lane Configuration"
                    description="Super Admin only: atur label dan tim yang wajib untuk setiap tahap sign-off pada Line Configuration Report."
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
                />

                <Card>
                    <CardHeader>
                        <CardTitle>Tahap Sign-off</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Tahap</TableHead>
                                    <TableHead>Label</TableHead>
                                    <TableHead>Tim Wajib</TableHead>
                                    <TableHead>Action</TableHead>
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
                                        <TableCell>
                                            <Button
                                                variant="outline"
                                                size="sm"
                                                onClick={() =>
                                                    setEditingLane(lane)
                                                }
                                            >
                                                Edit
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
