<?php

namespace App\Http\Controllers;

use App\Exports\MasterLinesTemplateExport;
use App\Http\Requests\ImportMasterLinesRequest;
use App\Http\Requests\StoreMasterLineRequest;
use App\Imports\MasterLinesImport;
use App\Models\MasterLine;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class MasterLineController extends Controller
{
    public function index(Request $request): Response
    {
        $lines = MasterLine::query()
            ->when($request->string('q')->toString(), function ($query, $q) {
                $query->where(function ($query) use ($q) {
                    $query->where('name', 'like', "%{$q}%")
                        ->orWhere('code', 'like', "%{$q}%")
                        ->orWhere('category', 'like', "%{$q}%")
                        ->orWhere('area', 'like', "%{$q}%");
                });
            })
            ->orderBy('category')
            ->orderBy('area')
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('masters/lines/index', [
            'lines' => $lines,
            'filters' => $request->only('q'),
        ]);
    }

    public function store(StoreMasterLineRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['is_active'] = $request->boolean('is_active');
        $line = MasterLine::findOrNew($data['id'] ?? null);
        $line->fill($data);
        $line->save();

        return back()->with('success', $data['id'] ?? null ? 'Line diperbarui.' : 'Line ditambahkan.');
    }

    public function destroy(MasterLine $masterLine, Request $request): RedirectResponse
    {
        $masterLine->deleted_by = $request->user()->id;
        $masterLine->save();
        $masterLine->delete();

        return back()->with('success', 'Line dihapus.');
    }

    public function template(): BinaryFileResponse
    {
        return Excel::download(new MasterLinesTemplateExport, 'template-master-line.xlsx');
    }

    public function import(ImportMasterLinesRequest $request): RedirectResponse
    {
        $import = new MasterLinesImport;
        Excel::import($import, $request->file('file'));

        if ($import->rowErrors === []) {
            return back()->with('success', "Import selesai. {$import->summary()}");
        }

        $errorList = implode(' | ', array_slice($import->rowErrors, 0, 10));
        if (count($import->rowErrors) > 10) {
            $errorList .= ' | dan '.(count($import->rowErrors) - 10).' baris lainnya';
        }

        return back()
            ->with('success', $import->hasChanges() ? "Sebagian baris berhasil diimpor. {$import->summary()}" : null)
            ->with('error', "Baris berikut dilewati karena tidak valid: {$errorList}");
    }
}
