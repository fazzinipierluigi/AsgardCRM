<?php

namespace Fazzinipierluigi\CrmCore\Http\Controllers\Admin;

use Fazzinipierluigi\CrmCore\Http\Controllers\Controller;
use Fazzinipierluigi\CrmCore\Http\Requests\Admin\StoreTranslationRequest;
use Fazzinipierluigi\CrmCore\Http\Requests\Admin\UpdateTranslationRequest;
use Fazzinipierluigi\CrmCore\Models\Language;
use Fazzinipierluigi\CrmCore\Models\Translation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TranslationController extends Controller
{
    /**
     * Display the translations listing page (one row per key).
     */
    public function index(): View
    {
        return view('crm::admin.translations.index', ['languages' => Language::orderBy('name')->get()]);
    }

    /**
     * Serve the server-side datatable request for the translations listing,
     * pivoted so each row is a key with one column per language.
     *
     * Laraccoon's EloquentSource works on one-row-per-record datasets and
     * has no concept of pivoting, so it isn't used here — the response is
     * built manually in the {data, total} shape Raccoon Tables expects.
     */
    public function data(Request $request): JsonResponse
    {
        $search = $request->string('globalSearch')->toString();
        $start = max(0, (int) $request->input('start', 0));
        $limit = max(1, (int) $request->input('limit', 25));

        $keysQuery = Translation::query()->select('key')->distinct()->orderBy('key');

        if ($search !== '') {
            $keysQuery->where('key', 'like', "%{$search}%");
        }

        $this->applyKeyFilter($keysQuery, $request);

        $total = (clone $keysQuery)->count();
        $keys = $keysQuery->skip($start)->take($limit)->pluck('key');

        $rowsByKey = Translation::whereIn('key', $keys)->get()->groupBy('key');
        $languageCodes = Language::pluck('code');

        $data = $keys->map(function (string $key) use ($rowsByKey, $languageCodes) {
            $translationsForKey = $rowsByKey->get($key, collect());
            $row = [
                'id' => $translationsForKey->first()?->id,
                'key' => $key,
            ];

            foreach ($languageCodes as $code) {
                $row[$code] = $translationsForKey->firstWhere('language', $code)?->value;
            }

            return $row;
        })->values();

        return response()->json(['data' => $data, 'total' => $total]);
    }

    /**
     * Apply the Raccoon Tables filter bar's "key" filter, if present.
     * This grid doesn't use EloquentSource (see the class docblock), so
     * filters aren't handled generically — only the one real, filterable
     * column ("key") needs this by hand.
     */
    private function applyKeyFilter($keysQuery, Request $request): void
    {
        $raw = $request->input('filters');
        $filters = is_string($raw) ? json_decode($raw, true) : $raw;

        if (! is_array($filters)) {
            return;
        }

        foreach ($filters as $filter) {
            if (($filter['index'] ?? null) !== 'key') {
                continue;
            }

            $value = (string) ($filter['value'] ?? '');

            match ($filter['sign'] ?? '=') {
                '==' => $keysQuery->where('key', '=', $value),
                '!==' => $keysQuery->where('key', '!=', $value),
                '!=' => $keysQuery->where('key', 'not like', "%{$value}%"),
                'a_' => $keysQuery->where('key', 'like', "{$value}%"),
                '_a' => $keysQuery->where('key', 'like', "%{$value}"),
                default => $keysQuery->where('key', 'like', "%{$value}%"),
            };
        }
    }

    /**
     * Show the form to create a new translation key.
     */
    public function create(): View
    {
        return view('crm::admin.translations.create', ['languages' => Language::orderBy('name')->get()]);
    }

    /**
     * Persist a new translation key, with a value for any language filled in.
     */
    public function store(StoreTranslationRequest $request): RedirectResponse
    {
        $key = $request->string('key')->toString();

        foreach ($request->input('values', []) as $language => $value) {
            if (filled($value)) {
                Translation::create(['key' => $key, 'language' => $language, 'value' => $value]);
            }
        }

        return redirect()->route('admin.translations.index')->with('status', 'translation-created');
    }

    /**
     * Show the form to edit every language's value for a translation key.
     */
    public function edit(Translation $translation): View
    {
        $values = Translation::where('key', $translation->key)->pluck('value', 'language');

        return view('crm::admin.translations.edit', [
            'translation' => $translation,
            'languages' => Language::orderBy('name')->get(),
            'values' => $values,
        ]);
    }

    /**
     * Update a translation key's value for each language. An emptied value
     * deletes that language's row; a filled value creates/updates it.
     */
    public function update(UpdateTranslationRequest $request, Translation $translation): RedirectResponse
    {
        foreach ($request->input('values', []) as $language => $value) {
            if (filled($value)) {
                Translation::updateOrCreate(
                    ['key' => $translation->key, 'language' => $language],
                    ['value' => $value]
                );
            } else {
                Translation::where('key', $translation->key)->where('language', $language)->delete();
            }
        }

        return redirect()->route('admin.translations.index')->with('status', 'translation-updated');
    }

    /**
     * Delete a translation key entirely, across every language.
     */
    public function destroy(Translation $translation): RedirectResponse
    {
        Translation::where('key', $translation->key)->delete();

        return redirect()->route('admin.translations.index')->with('status', 'translation-deleted');
    }
}
