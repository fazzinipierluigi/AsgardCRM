<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreTranslationRequest;
use App\Http\Requests\Admin\UpdateTranslationRequest;
use App\Models\Translation;
use Fazzinipierluigi\LaraccoonDatasource\EloquentSource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TranslationController extends Controller
{
    /**
     * Display the translations listing page.
     */
    public function index(): View
    {
        return view('admin.translations.index');
    }

    /**
     * Serve the server-side datatable request for the translations listing.
     */
    public function data(Request $request): JsonResponse
    {
        $translations = Translation::select('id', 'key', 'language', 'value', 'created_at');

        $source = new EloquentSource;
        $source->apply($translations, $request, null, ['key', 'language', 'value']);

        return $source->getResponse(function (Translation $translation) {
            return [
                'id' => $translation->id,
                'key' => $translation->key,
                'language' => $translation->language,
                'value' => $translation->value,
                'created_at' => $translation->created_at->format('d/m/Y H:i'),
            ];
        });
    }

    /**
     * Show the form to create a new translation.
     */
    public function create(): View
    {
        return view('admin.translations.create');
    }

    /**
     * Persist a new translation.
     */
    public function store(StoreTranslationRequest $request): RedirectResponse
    {
        Translation::create($request->only('key', 'language', 'value'));

        return redirect()->route('admin.translations.index')->with('status', 'translation-created');
    }

    /**
     * Show the form to edit an existing translation.
     */
    public function edit(Translation $translation): View
    {
        return view('admin.translations.edit', ['translation' => $translation]);
    }

    /**
     * Update an existing translation.
     */
    public function update(UpdateTranslationRequest $request, Translation $translation): RedirectResponse
    {
        $translation->update($request->only('key', 'language', 'value'));

        return redirect()->route('admin.translations.index')->with('status', 'translation-updated');
    }

    /**
     * Delete a translation.
     */
    public function destroy(Translation $translation): RedirectResponse
    {
        $translation->delete();

        return redirect()->route('admin.translations.index')->with('status', 'translation-deleted');
    }
}
