<?php

namespace App\Services\LineStatus;

use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

class QrSvg
{
    /** Inline <svg> markup (no XML prolog) for a QR code pointing at $url. */
    public static function make(string $url, int $size = 220): string
    {
        $renderer = new ImageRenderer(new RendererStyle($size, 1), new SvgImageBackEnd);

        return trim((string) preg_replace('/<\?xml.*?\?>/', '', (new Writer($renderer))->writeString($url)));
    }
}
