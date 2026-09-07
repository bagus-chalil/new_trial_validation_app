<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreMasterTestTypeRequest;
use App\Models\MasterTestType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MasterTestTypeController extends Controller
{
    public function index(Request $request): Response
    {
        $testTypes = MasterTestType::query()
            ->when($request->string('q')->toString(), function ($query, $q) {
                $query->where(function ($query) use ($q) {
                    $query->where('name', 'like', "%{$q}%")
                        ->orWhere('category', 'like', "%{$q}%");
                });
            })
            ->orderBy('category')
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('masters/test-types/index', [
            'testTypes' => $testTypes,
            'filters' => $request->only('q'),
            'categories' => MasterTestType::CATEGORIES,
        ]);
    }

    public function store(StoreMasterTestTypeRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $testType = MasterTestType::findOrNew($data['id'] ?? null);
        $testType->fill($data);
        $testType->save();

        return back()->with('success', $data['id'] ?? null ? 'Test type diperbarui.' : 'Test type ditambahkan.');
    }

    public function destroy(MasterTestType $masterTestType, Request $request): RedirectResponse
    {
        $masterTestType->deleted_by = $request->user()->id;
        $masterTestType->save();
        $masterTestType->delete();

        return back()->with('success', 'Test type dihapus.');
    }
}
