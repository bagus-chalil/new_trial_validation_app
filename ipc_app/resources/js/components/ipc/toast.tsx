import { useEffect, useState } from 'react';

export function useToast() {
    const [message, setMessage] = useState<string | null>(null);

    useEffect(() => {
        if (!message) return;
        const t = setTimeout(() => setMessage(null), 3500);
        return () => clearTimeout(t);
    }, [message]);

    return { message, toast: setMessage };
}

export function Toast({ message }: { message: string | null }) {
    if (!message) return null;
    return (
        <div className="pointer-events-none fixed inset-x-0 bottom-24 z-50 flex justify-center px-4">
            <div className="pointer-events-auto flex max-w-sm items-center gap-2.5 rounded-2xl bg-destructive px-4 py-3 text-[13.5px] font-semibold text-white shadow-lg">
                <span>⚠️</span>
                <span>{message}</span>
            </div>
        </div>
    );
}
