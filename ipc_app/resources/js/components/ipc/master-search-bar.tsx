import { InputGroup, InputGroupAddon, InputGroupButton, InputGroupInput } from '@/components/ui/input-group';
import { router } from '@inertiajs/react';
import { Search, X } from 'lucide-react';
import { FormEventHandler, useState } from 'react';

export function MasterSearchBar({ baseUrl, initialQ, placeholder }: { baseUrl: string; initialQ: string; placeholder: string }) {
    const [q, setQ] = useState(initialQ);

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        router.get(baseUrl, { q }, { preserveState: true, replace: true });
    };

    const clear = () => {
        setQ('');
        router.get(baseUrl, {}, { preserveState: true, replace: true });
    };

    return (
        <form onSubmit={submit}>
            <InputGroup className="border-border-soft bg-card h-12 rounded-2xl">
                <InputGroupAddon>
                    <Search className="text-muted-foreground/70 size-[18px]" strokeWidth={2} />
                </InputGroupAddon>
                <InputGroupInput value={q} onChange={(e) => setQ(e.target.value)} placeholder={placeholder} className="text-sm" />
                {q && (
                    <InputGroupAddon align="inline-end">
                        <InputGroupButton type="button" size="icon-xs" aria-label="Bersihkan pencarian" onClick={clear}>
                            <X className="size-3.5" />
                        </InputGroupButton>
                    </InputGroupAddon>
                )}
            </InputGroup>
        </form>
    );
}
