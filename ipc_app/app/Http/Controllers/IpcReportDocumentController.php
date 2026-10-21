<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateIpcReportDocumentRequest;
use App\Models\IpcReportDocument;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Admin screen for the 3 report doc-number/revision/title rows (see
 * BuildsIpcReportPayloads::resolveReportPdf()) — edit-only, since report_key values are fixed to
 * the Blade templates that read them; no create/delete, unlike the other Master admin CRUD
 * screens.
 */
class IpcReportDocumentController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('masters/report-documents/index', [
            'reportDocuments' => IpcReportDocument::query()->orderByRaw(
                "field(report_key, '".implode("','", IpcReportDocument::REPORT_KEYS)."')"
            )->get(),
        ]);
    }

    public function update(UpdateIpcReportDocumentRequest $request, IpcReportDocument $ipcReportDocument): RedirectResponse
    {
        $ipcReportDocument->update($request->validated());

        return back()->with('success', 'Dokumen laporan diperbarui.');
    }
}
