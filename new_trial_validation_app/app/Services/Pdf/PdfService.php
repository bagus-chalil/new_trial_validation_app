<?php

namespace App\Services\Pdf;

use Illuminate\Http\Response;

class PdfService
{
    public function __construct(private readonly PdfRenderer $renderer) {}

    /**
     * @param  view-string  $view
     * @param  array<string, mixed>  $data
     */
    public function fromView(string $view, array $data, string $filename): Response
    {
        $html = view($view, $data)->render();

        $pdf = $this->renderer->render($html);

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$filename.'"',
        ]);
    }
}
