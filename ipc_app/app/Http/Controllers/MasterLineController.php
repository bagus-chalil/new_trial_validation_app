<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreMasterLineRequest;
use App\Models\MasterLine;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

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
}
