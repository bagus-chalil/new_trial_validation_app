import { router } from '@inertiajs/react';
import type { ComponentProps, ReactNode } from 'react';
import { useState } from 'react';
import { ConfirmDialog } from '@/components/confirm-dialog';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { useTranslation } from '@/hooks/use-translation';

type Confirm = Omit<
    ComponentProps<typeof ConfirmDialog>,
    'trigger' | 'open' | 'onOpenChange'
>;

export type RowAction = {
    key: string;
    label: ReactNode;
    /** Navigates (Inertia visit) when chosen. */
    href?: string;
    /** Runs when chosen. */
    onSelect?: () => void;
    /** Opens a confirmation dialog when chosen. */
    confirm?: Confirm;
    destructive?: boolean;
    /** Draws a divider above this item. */
    separator?: boolean;
};

/**
 * One "Actions" button that opens a dropdown of row actions. Confirmation
 * dialogs live outside the menu (controlled), so they survive the menu closing.
 */
export function RowActionsMenu({ actions }: { actions: RowAction[] }) {
    const { t } = useTranslation();
    const [activeKey, setActiveKey] = useState<string | null>(null);
    const active = actions.find((a) => a.key === activeKey && a.confirm);

    if (actions.length === 0) {
        return <span className="text-muted-foreground">-</span>;
    }

    return (
        <>
            <DropdownMenu modal={false}>
                <DropdownMenuTrigger asChild>
                    <Button variant="outline" size="sm">
                        {t('common.actions.row_actions')}
                    </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="end" className="min-w-40">
                    {actions.map((action) => (
                        <div key={action.key}>
                            {action.separator && <DropdownMenuSeparator />}
                            <DropdownMenuItem
                                className={
                                    action.destructive
                                        ? 'text-destructive focus:text-destructive'
                                        : undefined
                                }
                                onSelect={() => {
                                    if (action.confirm) {
                                        setActiveKey(action.key);
                                    } else if (action.href) {
                                        router.visit(action.href);
                                    } else {
                                        action.onSelect?.();
                                    }
                                }}
                            >
                                {action.label}
                            </DropdownMenuItem>
                        </div>
                    ))}
                </DropdownMenuContent>
            </DropdownMenu>
            {active?.confirm && (
                <ConfirmDialog
                    {...active.confirm}
                    open
                    onOpenChange={(open) => !open && setActiveKey(null)}
                />
            )}
        </>
    );
}
