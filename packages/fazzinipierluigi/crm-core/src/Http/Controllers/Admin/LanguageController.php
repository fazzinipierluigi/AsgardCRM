<?php

namespace Fazzinipierluigi\CrmCore\Http\Controllers\Admin;

use Fazzinipierluigi\CrmCore\Http\Controllers\Controller;
use Fazzinipierluigi\CrmCore\Http\Requests\Admin\StoreLanguageRequest;
use Fazzinipierluigi\CrmCore\Models\Language;
use Fazzinipierluigi\CrmCore\Models\Translation;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class LanguageController extends Controller
{
    /**
     * List all languages, with an inline form to add a new one.
     */
    public function index(): View
    {
        return view('crm::admin.languages.index', ['languages' => Language::orderBy('name')->get()]);
    }

    /**
     * Add a new language.
     */
    public function store(StoreLanguageRequest $request): RedirectResponse
    {
        Language::create($request->only('code', 'name'));

        return redirect()->route('admin.languages.index')->with('status', 'language-created');
    }

    /**
     * Remove a language. Refused if any translation still uses it, to
     * avoid silently orphaning data.
     */
    public function destroy(Language $language): RedirectResponse
    {
        if (Translation::where('language', $language->code)->exists()) {
            return back()->with('error', 'Non è possibile eliminare una lingua che ha ancora traduzioni associate.');
        }

        $language->delete();

        return redirect()->route('admin.languages.index')->with('status', 'language-deleted');
    }
}
