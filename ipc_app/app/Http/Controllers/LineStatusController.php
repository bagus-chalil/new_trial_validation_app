<?php

namespace App\Http\Controllers;

use App\Models\IpcSetting;
use App\Models\MasterLine;
use App\Services\LineStatus\LineStatusBoard;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Http\RedirectResponse;
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
        $boardUrl = route('line-monitor.public', IpcSetting::lineMonitorToken());

        return Inertia::render('line-monitor/index', [
            'lines' => fn () => $this->lines($board, withLinks: true),
            'share' => [
                'url' => $boardUrl,
                'qr_svg' => $this->qrSvg($boardUrl, 220),
                'qr_url' => route('line-monitor.qr'),
                'can_regenerate' => (bool) request()->user()?->can('manage-master'),
            ],
        ]);
    }

    /**
     * The same whole-board screen, public (no login) for a TV / wall display or a phone. Protected
     * by one unguessable board token that an admin can regenerate to revoke old links.
     */
    public function board(string $token, LineStatusBoard $board): Response
    {
        abort_unless(hash_equals(IpcSetting::lineMonitorToken(), $token), 404);

        return Inertia::render('line-monitor/index', [
            'lines' => fn () => $this->lines($board, withLinks: false),
            'publicMode' => true,
        ]);
    }

    /** Printable QR poster for the whole board. */
    public function boardQr(): View
    {
        $url = route('line-monitor.public', IpcSetting::lineMonitorToken());

        return view('line-status.qr', [
            'title' => 'Monitor Line',
            'subtitle' => 'Status semua line produksi',
            'hint' => 'Scan untuk melihat status IPC semua line',
            'pageTitle' => 'QR Monitor Line',
            'svg' => $this->qrSvg($url, 320),
            'url' => $url,
        ]);
    }

    public function regenerateBoardToken(): RedirectResponse
    {
        IpcSetting::regenerateLineMonitorToken();

        return back()->with('success', 'Link publik Monitor Line dibuat ulang. Link dan QR lama tidak berlaku lagi.');
    }

    /** Printable QR poster for one line (opened in a new tab from Master Line). */
    public function qr(MasterLine $masterLine): View
    {
        $url = $masterLine->publicUrl();

        return view('line-status.qr', [
            'title' => $masterLine->name,
            'subtitle' => "{$masterLine->code} · {$masterLine->category} · {$masterLine->area}",
            'hint' => 'Scan untuk melihat status IPC di line ini',
            'pageTitle' => "QR Line {$masterLine->code}",
            'svg' => $this->qrSvg($url, 320),
            'url' => $url,
        ]);
    }

    /** @return list<array<string, mixed>> */
    private function lines(LineStatusBoard $board, bool $withLinks): array
    {
        $lines = MasterLine::query()->where('is_active', true)->orderBy('category')->orderBy('area')->orderBy('name')->get();
        $active = $board->forLines($lines);

        return $lines->map(fn (MasterLine $line) => [
            'id' => $line->id,
            'code' => $line->code,
            'name' => $line->name,
            'category' => $line->category,
            'area' => $line->area,
            // Per-line tokens are only handed to logged-in users, never leaked through the public board.
            'public_url' => $withLinks ? $line->publicUrl() : null,
            'qr_url' => $withLinks ? route('master-lines.qr', $line) : null,
            'batches' => $active[$line->id] ?? [],
        ])->values()->all();
    }

    private function qrSvg(string $url, int $size): string
    {
        $renderer = new ImageRenderer(new RendererStyle($size, 1), new SvgImageBackEnd);

        return trim((string) preg_replace('/<\?xml.*?\?>/', '', (new Writer($renderer))->writeString($url)));
    }
}
