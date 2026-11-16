<?php

namespace App\Http\Controllers;

use App\Exports\MasterLinesTemplateExport;
use App\Http\Requests\StoreMasterLineRequest;
use App\Models\MasterLine;
use App\Services\LineStatus\QrSvg;
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

        // Share info (public status link + QR) only for active lines: an inactive line's public page is a 404.
        $lines->getCollection()->transform(function (MasterLine $line) {
            if ($line->is_active) {
                $url = $line->publicUrl();
                $line->setAttribute('share', [
                    'url' => $url,
                    'qr_svg' => QrSvg::make($url, 220),
                    'poster_url' => route('master-lines.qr', $line),
                ]);
            }

            return $line;
        });

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
}
