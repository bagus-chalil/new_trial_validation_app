import { Label } from '@/components/ui/label';
import { CircleHelp } from 'lucide-react';
import { type ComponentProps, useId, useState } from 'react';

const FIELD_HELP: [RegExp, string][] = [
    [/filling range min|min volume/i, 'Batas bawah pengisian. Salin nilai dari standar atau dokumen produksi yang berlaku.'],
    [/filling range max|max volume/i, 'Batas atas pengisian. Periksa kembali angka dan satuannya pada dokumen acuan.'],
    [/density/i, 'Massa jenis produk. Gunakan hasil atau dokumen acuan; pilih N/A hanya jika parameter ini tidak berlaku.'],
    [/empty bottle|tare/i, 'Berat kemasan kosong, sebelum diisi produk. Gunakan hasil penimbangan sesuai prosedur.'],
    [/heating|temperature/i, 'Pengaturan pemanasan atau suhu mesin. Cocokkan nilai aktual dengan instruksi kerja yang berlaku.'],
    [/leakage/i, 'Pemeriksaan kebocoran kemasan. Pilih status berdasarkan hasil uji aktual.'],
    [/bulk.*odor|appearance/i, 'Periksa tampilan atau bau sampel dan bandingkan dengan standar produk.'],
    [/coding|im number|wi number/i, 'Cocokkan identitas atau cetakan dengan dokumen acuan. Jika diminta foto, pastikan tulisan terbaca jelas.'],
    [/masterbox|master box/i, 'Kemasan luar berisi produk. Perhatikan apakah kolom meminta nomor box, jumlah, atau berat.'],
    [/quantity wi/i, 'Jumlah produksi yang tercantum pada instruksi kerja (WI). Masukkan angka sesuai dokumen.'],
    [/result.*cfu/i, 'Hasil uji mikrobiologi aktual. Ikuti format pada form, misalnya <10 atau (-), dan satuan cfu/gr.'],
    [/std.*cfu/i, 'Standar pembanding hasil uji. Cocokkan dengan standar produk yang berlaku.'],
    [/mixing date|tanggal mixing/i, 'Tanggal proses mixing sesuai dokumen produksi.'],
    [/exp.*date|tanggal.*kedaluwarsa/i, 'Tanggal kedaluwarsa. Cocokkan dengan informasi produk dan dokumen produksi.'],
    [/pm.*bom/i, 'Cocokkan material kemasan (PM) dengan daftar material (BOM) produk ini.'],
    [/product standard/i, 'Standar acuan untuk membandingkan hasil pemeriksaan produk. Periksa apakah acuannya tersedia.'],
];

/** Contextual help stays beside the field, while the original label keeps its input association. */
export function GuidedLabel({ children, ...props }: ComponentProps<typeof Label>) {
    const [open, setOpen] = useState(false);
    const id = useId();
    const text = typeof children === 'string' ? children.trim() : '';
    const help = FIELD_HELP.find(([pattern]) => pattern.test(text))?.[1];
    if (!help) return <Label {...props}>{children}</Label>;
    return (
        <div className="grid gap-1.5">
            <div className="flex items-start justify-between gap-2">
                <Label {...props}>{children}</Label>
                <button
                    type="button"
                    aria-label={`Bantuan ${text}`}
                    aria-expanded={open}
                    aria-controls={id}
                    onClick={() => setOpen(!open)}
                    className="text-primary/70 hover:bg-primary/10 focus-visible:outline-primary -mt-1 flex size-7 shrink-0 items-center justify-center rounded-full focus-visible:outline-2"
                >
                    <CircleHelp className="size-4" />
                </button>
            </div>
            {open && (
                <p id={id} className="bg-primary/5 text-muted-foreground rounded-lg px-3 py-2 text-xs leading-relaxed">
                    {help}
                </p>
            )}
        </div>
    );
}
