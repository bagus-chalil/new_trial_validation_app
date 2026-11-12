/* global process */
/**
 * Old-Chrome fixes for Tailwind v4 output (the IPC tablets run Android 12 + Chrome 106).
 * Build-only (Vite `generateBundle`), so `vite dev` serves Tailwind's output untouched.
 *
 * A) Real alpha fallbacks for opacity modifiers on var()-based theme colors.
 *    Tailwind v4.1 compiles `bg-primary/10` to
 *      .x{background-color:var(--color-primary)}
 *      @supports (color:color-mix(in lab,red,red)){.x{background-color:color-mix(in oklab,var(--color-primary)10%,transparent)}}
 *    so without color-mix() (Chrome < 111) a 4-10% tint becomes the solid accent color
 *    and the text on it vanishes. This step:
 *     1. reads `--color-NAME:var(--SRC)` from the theme to learn which colors are runtime vars,
 *     2. next to every `--SRC:<hex|rgb|hsl>` declaration adds `--rgb-SRC:R G B`
 *        (and `--rgb-SRC:var(--rgb-OTHER)` when `--SRC:var(--OTHER)`, e.g. the per-flow accent),
 *     3. inserts `.x{background-color:rgb(var(--rgb-SRC) / 10%)}` right before each
 *        color-mix @supports block.
 *
 * B) Empty-fallback var() on registered properties (see unregisterEmptyFallbackProps).
 *
 * Modern browsers render exactly as before: verified by comparing computed styles of
 * every utility class with and without this plugin.
 */

const SUPPORTS_BLOCK = /@supports \(color:\s?color-mix\(in lab,\s?red,\s?red\)\)\{([^{}@]+)\{([^{}]*)\}\}/g;
const THEME_MIX = /color-mix\(in oklab,\s?var\(--color-([\w-]+)\)\s?([\d.]+%),\s?transparent\)/g;
// shadow-x/20 nests the tint inside a second mix with --tw-shadow-alpha (100% unless a
// shadow opacity utility is used): keep the inner tint.
const NESTED_ALPHA = /color-mix\(in oklab,\s?(rgb\(var\(--rgb-[\w-]+\) \/ [\d.]+%\))\s?var\(--tw-[\w-]+-alpha\),\s?transparent\)/g;

function toChannels(value) {
    const v = value.trim().toLowerCase();
    let m = v.match(/^#([0-9a-f]{3,8})$/);
    if (m) {
        let h = m[1];
        if (h.length <= 4) h = [...h].map((c) => c + c).join('');
        return [0, 2, 4].map((i) => Number.parseInt(h.slice(i, i + 2), 16)).join(' ');
    }
    m = v.match(/^rgba?\(\s*([\d.]+)[\s,]+([\d.]+)[\s,]+([\d.]+)/);
    if (m) return `${m[1]} ${m[2]} ${m[3]}`;
    m = v.match(/^hsla?\(\s*([\d.]+)(?:deg)?[\s,]+([\d.]+)%[\s,]+([\d.]+)%/);
    if (m) {
        const h = +m[1] / 360;
        const s = +m[2] / 100;
        const l = +m[3] / 100;
        const f = (n) => {
            const k = (n + h * 12) % 12;
            return Math.round(255 * (l - s * Math.min(l, 1 - l) * Math.max(-1, Math.min(k - 3, 9 - k, 1))));
        };
        return `${f(0)} ${f(8)} ${f(4)}`;
    }
    return null;
}

/**
 * Chrome < ~110 bug: `var(--x,)` (empty fallback) is invalid at computed-value time
 * whenever `--x` is registered via `@property` — even when `--x` has a value. Tailwind
 * uses exactly that for rings (`var(--tw-ring-inset,)`), transforms, filters,
 * backdrop-filters and font-variant-numeric, so on Chrome 106 all of those silently
 * computed to `none`. Unregistered properties don't hit the bug, so: drop the
 * registration of every such property that has no initial-value (syntax "*", not
 * animatable anyway) and reset it to `initial` on every element in the lowest layer —
 * the same thing Tailwind itself does for browsers without @property, which also
 * stops a parent's value from leaking into children now that it would inherit.
 */
export function unregisterEmptyFallbackProps(css) {
    const names = new Set([...css.matchAll(/var\(--([\w-]+),\)/g)].map((m) => m[1]));
    const reset = [];
    css = css.replace(/@property --([\w-]+)\{([^}]*)\}/g, (rule, name, body) => {
        if (!names.has(name) || body.includes('initial-value')) return rule;
        reset.push(`--${name}:initial`);
        return '';
    });
    if (!reset.length) return css;
    return `${css}@layer properties{*,:before,:after,::backdrop{${reset.join(';')}}}`;
}

export function transformCss(css) {
    css = unregisterEmptyFallbackProps(css);

    // A1. tailwind color name -> source var name (first definition = the @theme one;
    // later overrides such as `.ipc-flow{--color-primary:var(--flow-accent)}` are
    // covered by step A2 giving `--rgb-primary` the flow value there).
    const sources = new Map();
    for (const m of css.matchAll(/--color-([\w-]+):\s*var\(--([\w-]+)\)/g)) {
        if (!m[2].startsWith('color-') && !sources.has(m[1])) sources.set(m[1], m[2]);
    }
    if (!sources.size) return css;
    const srcNames = new Set(sources.values());

    // A2. channel variables next to each source definition
    css = css.replace(/(^|[;{])\s*--([\w-]+):\s*([^;{}]+?)\s*(?=[;}])/g, (whole, lead, name, value) => {
        if (!srcNames.has(name)) return whole;
        const ref = value.match(/^var\(--([\w-]+)\)$/);
        const channels = ref ? `var(--rgb-${ref[1]})` : toChannels(value);
        return channels ? `${whole};--rgb-${name}:${channels}` : whole;
    });

    // A3. Insert an alpha fallback rule right before every color-mix @supports block.
    // The opaque fallback is deliberately *not* edited in place: the minifier merges
    // identical fallbacks of different classes into one rule (`.bg-primary,.bg-primary\/5{…}`),
    // so editing it would also tint the solid class. The inserted rule has the same
    // selector and comes later, so it wins on old browsers; the @supports rule after it
    // still wins on modern ones.
    return css.replace(SUPPORTS_BLOCK, (whole, selector, modern) => {
        const rewritten = [];
        for (const decl of modern.split(';').filter(Boolean)) {
            const out = decl
                .replace(THEME_MIX, (m, name, pct) => {
                    const src = sources.get(name);
                    return src ? `rgb(var(--rgb-${src}) / ${pct})` : m;
                })
                .replace(NESTED_ALPHA, '$1');
            if (out !== decl && !out.includes('color-mix(')) rewritten.push(out);
        }
        return rewritten.length ? `${selector}{${rewritten.join(';')}}${whole}` : whole;
    });
}

export default function colorMixFallback() {
    return {
        name: 'ipc:color-mix-fallback',
        apply: 'build',
        enforce: 'post',
        generateBundle(_options, bundle) {
            // Escape hatch for comparing against Tailwind's untouched output.
            if (process.env.IPC_SKIP_CSS_FALLBACK === '1') return;
            for (const asset of Object.values(bundle)) {
                if (asset.type === 'asset' && asset.fileName.endsWith('.css')) {
                    asset.source = transformCss(String(asset.source));
                }
            }
        },
    };
}
