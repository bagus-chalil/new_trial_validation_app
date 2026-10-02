// A batch's line is picked on Startup Check, so it can still be empty on any page.
export type BatchLine = { name: string; code: string } | null | undefined;

export function lineLabel(line: BatchLine): string {
    return line ? `${line.name} (${line.code})` : '—';
}
