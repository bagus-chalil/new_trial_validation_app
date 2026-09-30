import { CameraCaptureDialog } from '@/components/ipc/camera-capture-dialog';
import { ProductSearchSelect, type ProductOption } from '@/components/ipc/product-search-select';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { type VisionDecision } from '@/hooks/use-vision-analyze';
import { IpcShell } from '@/layouts/ipc-shell';
import { Head } from '@inertiajs/react';
import { Camera, ImageUp, Loader2, ScanText } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';

interface TestResult {
    request_id: string;
    field_type: string;
    decision: VisionDecision;
    decision_reason: string;
    ocr_value: string | null;
    raw_ocr_text: string | null;
    expected_value: string | null;
    computed_value: string | null;
    vision_status: 'OK' | 'LOW_CONFIDENCE' | 'ERROR';
    confidence: number | null;
    format_valid: boolean | null;
    error_reason: string | null;
    engine_used: string | null;
    processing_time_ms: number | null;
}

const fieldTypeLabels: Record<string, string> = {
    tube_exp_date: 'EXP date (emboss crimp)',
    tube_mfd_date: 'MFD date (body tube)',
    tube_emboss_default: 'Emboss umum (tanpa pembanding)',
};

const decisionStyle: Record<VisionDecision, string> = {
    PASS: 'border-green-300 bg-green-50 text-green-800 dark:border-green-800 dark:bg-green-950 dark:text-green-200',
    FAIL: 'border-red-300 bg-red-50 text-red-800 dark:border-red-800 dark:bg-red-950 dark:text-red-200',
    REVIEW: 'border-amber-300 bg-amber-50 text-amber-800 dark:border-amber-800 dark:bg-amber-950 dark:text-amber-200',
};

// Same XSRF-cookie approach as use-vision-analyze.ts — this endpoint is JSON, not an Inertia visit.
function xsrfToken(): string {
    const match = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]*)/);
    return match ? decodeURIComponent(match[1]) : '';
}

function ResultRow({ label, value, mono = false }: { label: string; value: string | null | undefined; mono?: boolean }) {
    return (
        <div className="flex items-start justify-between gap-4 py-1.5">
            <span className="text-muted-foreground text-[13px]">{label}</span>
            <span className={`text-right text-[13px] font-semibold break-all ${mono ? 'font-mono' : ''}`}>{value ?? '-'}</span>
        </div>
    );
}

export default function VisionTestIndex({ fieldTypes, rules }: { fieldTypes: string[]; rules: Record<string, string> }) {
    const [product, setProduct] = useState<ProductOption | null>(null);
    const [fieldType, setFieldType] = useState(fieldTypes[0] ?? '');
    const [expDate, setExpDate] = useState('');
    const [photo, setPhoto] = useState<File | null>(null);
    const [previewUrl, setPreviewUrl] = useState<string | null>(null);
    const [cameraOpen, setCameraOpen] = useState(false);
    const [processing, setProcessing] = useState(false);
    const [result, setResult] = useState<TestResult | null>(null);
    const [error, setError] = useState<string | null>(null);
    const fileInputRef = useRef<HTMLInputElement>(null);

    const rule = rules[fieldType];

    useEffect(() => {
        if (!photo) {
            setPreviewUrl(null);
            return;
        }
        const url = URL.createObjectURL(photo);
        setPreviewUrl(url);
        return () => URL.revokeObjectURL(url);
    }, [photo]);

    const choosePhoto = (file: File) => {
        setPhoto(file);
        setResult(null);
        setError(null);
    };

    const analyze = async () => {
        if (!photo) return;
        setProcessing(true);
        setError(null);
        setResult(null);

        const body = new FormData();
        body.append('photo', photo);
        body.append('field_type', fieldType);
        if (product) body.append('master_product_id', String(product.id));
        if (expDate) body.append('exp_date', expDate);

        try {
            const response = await fetch(route('vision-test.analyze'), {
                method: 'POST',
                body,
                credentials: 'same-origin',
                headers: { Accept: 'application/json', 'X-XSRF-TOKEN': xsrfToken() },
            });
            const data = await response.json();
            if (!response.ok) {
                setError(data.message ?? `Gagal (HTTP ${response.status})`);
                return;
            }
            setResult(data as TestResult);
        } catch {
            setError('Tidak bisa menghubungi server. Coba lagi.');
        } finally {
            setProcessing(false);
        }
    };

    return (
        <IpcShell title="OCR Test Product" subtitle="Uji baca kode tanpa batch — hasil tidak disimpan" backHref="/dashboard">
            <Head title="OCR Test Product" />
            <div className="flex flex-1 flex-col gap-4 overflow-y-auto p-5 md:p-6">
                <div className="border-border-soft bg-card flex flex-col gap-4 rounded-[20px] border p-4">
                    <div className="grid gap-2">
                        <Label htmlFor="field_type">Jenis kode</Label>
                        <Select
                            value={fieldType}
                            onValueChange={(v) => {
                                setFieldType(v);
                                setResult(null);
                            }}
                        >
                            <SelectTrigger id="field_type" className="min-h-11">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                {fieldTypes.map((ft) => (
                                    <SelectItem key={ft} value={ft}>
                                        {fieldTypeLabels[ft] ?? ft}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        {!rule && (
                            <p className="text-muted-foreground text-[12px]">
                                Belum ada aturan pembanding untuk jenis ini, jadi hasilnya selalu REVIEW.
                            </p>
                        )}
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="master_product_id">Produk {rule === 'mfd_plus_shelf_life' ? '' : '(opsional)'}</Label>
                        <ProductSearchSelect
                            id="master_product_id"
                            value={product}
                            placeholder="Pilih produk"
                            onChange={(p) => {
                                setProduct(p);
                                setResult(null);
                            }}
                        />
                        {rule === 'mfd_plus_shelf_life' && (
                            <p className="text-muted-foreground text-[12px]">
                                {product
                                    ? product.shelf_life_months
                                        ? `Shelf-life: ${product.shelf_life_months} bulan — EXP dihitung dari MFD + shelf-life.`
                                        : 'Produk ini belum punya shelf-life di master, jadi hasilnya pasti REVIEW.'
                                    : 'Pilih produk supaya EXP bisa dihitung dari MFD + shelf-life.'}
                            </p>
                        )}
                    </div>

                    {rule && (
                        <div className="grid gap-2">
                            <Label htmlFor="exp_date">EXP pembanding</Label>
                            <Input
                                id="exp_date"
                                type="date"
                                className="min-h-11"
                                value={expDate}
                                onChange={(e) => {
                                    setExpDate(e.target.value);
                                    setResult(null);
                                }}
                            />
                            <p className="text-muted-foreground text-[12px]">
                                Pengganti EXP batch. Kalau dikosongkan, hasilnya REVIEW (tidak ada pembanding).
                            </p>
                        </div>
                    )}

                    <div className="grid gap-2">
                        <Label>Foto</Label>
                        {previewUrl && (
                            <img src={previewUrl} alt="Foto yang akan dibaca" className="max-h-72 w-full rounded-xl border object-contain" />
                        )}
                        <div className="flex gap-2">
                            <Button
                                type="button"
                                variant="outline"
                                className="min-h-11 flex-1 gap-1.5"
                                onClick={() => setCameraOpen(true)}
                                disabled={processing}
                            >
                                <Camera className="size-4" />
                                Kamera
                            </Button>
                            <Button
                                type="button"
                                variant="outline"
                                className="min-h-11 flex-1 gap-1.5"
                                onClick={() => fileInputRef.current?.click()}
                                disabled={processing}
                            >
                                <ImageUp className="size-4" />
                                Pilih file
                            </Button>
                            <input
                                ref={fileInputRef}
                                type="file"
                                accept="image/jpeg,image/png,image/webp"
                                className="hidden"
                                onChange={(e) => {
                                    const file = e.target.files?.[0];
                                    if (file) choosePhoto(file);
                                    e.target.value = '';
                                }}
                            />
                        </div>
                    </div>

                    <Button type="button" className="min-h-11 gap-1.5" onClick={analyze} disabled={!photo || processing}>
                        {processing ? <Loader2 className="size-4 animate-spin" /> : <ScanText className="size-4" />}
                        {processing ? 'Sedang membaca...' : 'Baca kode (OCR)'}
                    </Button>
                    {processing && (
                        <p className="text-muted-foreground text-center text-[12px]">
                            Satu scan ±30 detik, dan bisa lebih lama kalau ada stasiun lain yang sedang antre.
                        </p>
                    )}
                    {error && <p className="text-destructive text-[13px]">{error}</p>}
                </div>

                {result && (
                    <div className="border-border-soft bg-card flex flex-col gap-3 rounded-[20px] border p-4">
                        <div className={`rounded-xl border px-4 py-3 ${decisionStyle[result.decision]}`}>
                            <p className="text-[18px] font-bold tracking-tight">{result.decision}</p>
                            <p className="text-[13px]">{result.decision_reason}</p>
                        </div>
                        <div className="divide-border divide-y">
                            <ResultRow label="Terbaca (OCR)" value={result.ocr_value} mono />
                            {result.computed_value && <ResultRow label="EXP dihitung dari MFD" value={result.computed_value} mono />}
                            <ResultRow label="Seharusnya" value={result.expected_value} mono />
                            <div className="flex items-center justify-between gap-4 py-1.5">
                                <span className="text-muted-foreground text-[13px]">Status Vision</span>
                                <Badge variant={result.vision_status === 'OK' ? 'default' : 'secondary'}>{result.vision_status}</Badge>
                            </div>
                            <ResultRow label="Confidence" value={result.confidence !== null ? result.confidence.toFixed(4) : null} />
                            <ResultRow label="Format valid" value={result.format_valid === null ? null : result.format_valid ? 'Ya' : 'Tidak'} />
                            {result.error_reason && <ResultRow label="Error" value={result.error_reason} />}
                            <ResultRow label="Engine" value={result.engine_used} />
                            <ResultRow
                                label="Waktu proses"
                                value={result.processing_time_ms !== null ? `${(result.processing_time_ms / 1000).toFixed(1)} detik` : null}
                            />
                            <ResultRow label="Request ID" value={result.request_id} mono />
                        </div>
                        {result.raw_ocr_text && (
                            <details className="text-[12px]">
                                <summary className="text-muted-foreground cursor-pointer">Teks mentah OCR</summary>
                                <pre className="bg-muted mt-2 rounded-lg p-3 font-mono break-all whitespace-pre-wrap">{result.raw_ocr_text}</pre>
                            </details>
                        )}
                    </div>
                )}
            </div>

            <CameraCaptureDialog open={cameraOpen} onOpenChange={setCameraOpen} onCapture={choosePhoto} title="Foto kode" />
        </IpcShell>
    );
}
