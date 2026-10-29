const dateFormatter = new Intl.DateTimeFormat('id-ID', {
    day: '2-digit',
    month: 'short',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
    timeZone: 'Asia/Jakarta',
});

export function BatchCreatedAt({ value }: { value: string | null }) {
    const date = value ? new Date(value) : null;
    const valid = date !== null && !Number.isNaN(date.getTime());

    return (
        <p className="text-muted-foreground mt-auto text-right text-[11.5px] leading-relaxed">
            Dibuat: {valid ? <time dateTime={value!}>{dateFormatter.format(date)} WIB</time> : '—'}
        </p>
    );
}
