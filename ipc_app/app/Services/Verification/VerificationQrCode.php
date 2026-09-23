<?php

namespace App\Services\Verification;

use App\Models\IpcBatch;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

/**
 * Generates the public verification link + inline QR-code SVG stamped onto a printed IPC
 * report's sign-off box, so anyone holding the physical/PDF document can scan it and land on a
 * read-only page confirming who approved that stage and when. Legacy has no equivalent (its own
 * approval action is a bare flag-flip — see IpcApproval's doc comment) — this is this app's own
 * addition, same design space as IpcApproval/IpcPrintLog themselves.
 */
class VerificationQrCode
{
    public static function url(IpcBatch $batch, string $stage): string
    {
        return route('verify.show', ['batch' => $batch->id, 'stage' => $stage]);
    }

    /**
     * Renders a standalone <svg>...</svg> string (no XML prolog — stripped so it can be embedded
     * directly inline in an HTML document without the declaration surfacing as stray text).
     */
    public static function svg(IpcBatch $batch, string $stage, int $size = 110): string
    {
        $renderer = new ImageRenderer(new RendererStyle($size, 1), new SvgImageBackEnd);
        $svg = (new Writer($renderer))->writeString(self::url($batch, $stage));

        return trim((string) preg_replace('/<\?xml.*?\?>/', '', $svg));
    }
}
