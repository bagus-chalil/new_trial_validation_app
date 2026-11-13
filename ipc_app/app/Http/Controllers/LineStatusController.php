<?php

namespace App\Http\Controllers;

use App\Models\MasterLine;
use App\Services\LineStatus\LineStatusBoard;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\View\View;
use Inertia\Inertia;
use Inertia\Response;

class LineStatusController extends Controller
{
    /**
     * Public (no login) read-only status of one line, reached by scanning the QR code posted at
     * the line. The URL carries an unguessable per-line token; the page is internal-network only
     * and shows product, batch, FG code and PIC by explicit decision (2026-11-13).
     */
    public function show(string $token, LineStatusBoard $board): View
    {
        $line = MasterLine::query()->where('public_token', $token)->where('is_active', true)->firstOrFail();

        return view('line-status.show', [
            'line' => $line,
            'batches' => $board->forLines(collect([$line]))[$line->id] ?? [],
        ]);
    }

    /** Internal dashboard (login required): every active line, what is running, and who the PIC is. */
    public function monitor(LineStatusBoard $board): Response
    {
        $lines = MasterLine::query()->where('is_active', true)->orderBy('category')->orderBy('area')->orderBy('name')->get();
        $active = $board->forLines($lines);

        return Inertia::render('line-monitor/index', [
            'lines' => $lines->map(fn (MasterLine $line) => [
                'id' => $line->id,
                'code' => $line->code,
                'name' => $line->name,
                'category' => $line->category,
                'area' => $line->area,
                'batches' => $active[$line->id] ?? [],
            ])->values(),
        ]);
    }

    /** Printable QR poster for one line (opened in a new tab from Master Line). */
    public function qr(MasterLine $masterLine): View
    {
        $renderer = new ImageRenderer(new RendererStyle(320, 1), new SvgImageBackEnd);
        $svg = trim((string) preg_replace('/<\?xml.*?\?>/', '', (new Writer($renderer))->writeString($masterLine->publicUrl())));

        return view('line-status.qr', ['line' => $masterLine, 'svg' => $svg, 'url' => $masterLine->publicUrl()]);
    }
}
