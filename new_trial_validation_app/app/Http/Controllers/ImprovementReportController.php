<?php

namespace App\Http\Controllers;

use App\Models\Improvement;
use App\Models\ImprovementFile;
use App\Models\Project;
use App\Services\Pdf\PdfService;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\Gate;

/**
 * Printable Improvement form (FR.IMP.001.00): PDF and Word, same approach as
 * the Check Tooling form (Word opens an HTML document saved as .doc).
 */
class ImprovementReportController extends Controller
{
    public function pdf(int $improvement, PdfService $pdf): HttpResponse
    {
        $model = $this->find($improvement);
        Gate::authorize('view', $model);

        return $pdf->fromView('pdf.improvement', $this->formData($model), $this->filename($model, 'pdf'));
    }

    public function word(int $improvement): HttpResponse
    {
        $model = $this->find($improvement);
        Gate::authorize('view', $model);

        return response(view('pdf.improvement', $this->formData($model))->render(), 200, [
            'Content-Type' => 'application/msword; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$this->filename($model, 'doc').'"',
        ]);
    }

    private function find(int $id): Improvement
    {
        return Improvement::whereNull('deleted_at')->findOrFail($id);
    }

    private function filename(Improvement $i, string $ext): string
    {
        $base = 'FR-IMP-001-00-'.($i->title !== '' ? mb_substr($i->title, 0, 60) : $i->id);

        return trim((string) preg_replace('/[^A-Za-z0-9._-]+/', '-', $base), '-').'.'.$ext;
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(Improvement $i): array
    {
        $files = ImprovementFile::where('improvement_id', $i->id)->orderBy('id')->get();
        $project = $i->project_id ? Project::whereNull('deleted_at')->find($i->project_id) : null;

        return [
            'title' => 'FR.IMP.001.00 '.$i->title,
            'imp' => $i,
            'project' => $project,
            'files' => $files->where('phase', '!=', 'after')->values(),
            'afterFiles' => $files->where('phase', 'after')->values(),
        ];
    }
}
