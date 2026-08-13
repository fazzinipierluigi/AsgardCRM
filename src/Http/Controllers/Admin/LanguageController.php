<?php

namespace Fazzinipierluigi\AsgardCRM\Http\Controllers\Admin;

use Fazzinipierluigi\AsgardCRM\Http\Controllers\Controller;
use Fazzinipierluigi\AsgardCRM\Http\Requests\Admin\StoreLanguageRequest;
use Fazzinipierluigi\AsgardCRM\Models\Language;
use Fazzinipierluigi\AsgardCRM\Models\Translation;
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
