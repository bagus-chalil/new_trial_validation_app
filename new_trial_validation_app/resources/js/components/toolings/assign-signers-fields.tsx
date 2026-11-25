import InputError from '@/components/input-error';
import { StepField } from '@/components/toolings/step-fields';
import { useTranslation } from '@/hooks/use-translation';

export type SignerCandidates = Record<
    string,
    { id: number; name: string; department: string }[]
>;

/** One native select per signing box: empty = anyone in the department group. */
export function AssignSignersFields({
    roles,
    candidates,
    assigned,
    signed,
    errors,
}: {
    readonly roles: readonly string[];
    readonly candidates: SignerCandidates;
    readonly assigned: Record<string, number | null>;
    readonly signed: Record<string, boolean>;
    readonly errors: Record<string, string>;
}) {
    const { t } = useTranslation();

    return (
        <div className="mt-4 grid gap-4">
            <p className="text-sm text-muted-foreground">
                {t('toolings.assign.description')}
            </p>
            {roles.map((role) => (
                <StepField key={role} label={t(`toolings.roles.${role}`)}>
                    <select
                        name={`assignees[${role}]`}
                        disabled={signed[role]}
                        defaultValue={assigned[role] ?? ''}
                        className="h-9 w-full rounded-md border border-input bg-transparent px-3 text-sm shadow-xs disabled:opacity-60"
                    >
                        <option value="">
                            {t('toolings.assign.anyone', {
                                role: t(`toolings.roles_short.${role}`),
                            })}
                        </option>
                        {(candidates[role] ?? []).map((u) => (
                            <option key={u.id} value={u.id}>
                                {u.name}
                                {u.department ? ` (${u.department})` : ''}
                            </option>
                        ))}
                    </select>
                    {signed[role] && (
                        <p className="text-xs text-muted-foreground">
                            {t('toolings.assign.signed_locked')}
                        </p>
                    )}
                </StepField>
            ))}
            <InputError message={errors.assignees} />
        </div>
    );
}
