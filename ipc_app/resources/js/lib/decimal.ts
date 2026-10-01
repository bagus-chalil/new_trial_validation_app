/** Truncates a typed number to at most `digits` places after the decimal point. */
export function limitDecimals(value: string, digits = 2): string {
    const [whole, fraction] = value.split(/(?=[.,])/);
    return fraction === undefined ? value : whole + fraction.slice(0, digits + 1);
}
