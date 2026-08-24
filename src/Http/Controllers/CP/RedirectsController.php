<?php

namespace Vulpo\Seo\Http\Controllers\CP;

use Illuminate\Http\Request;
use Statamic\CP\PublishForm;
use Statamic\Fields\Blueprint;
use Statamic\Fields\BlueprintRepository;
use Vulpo\Seo\Redirects\Redirect;
use Vulpo\Seo\Redirects\RedirectRepository;

class RedirectsController
{
    public function __construct(private readonly RedirectRepository $redirects) {}

    public function index()
    {
        $blueprint = $this->blueprint();

        $fields = $blueprint->fields()
            ->addValues(['redirects' => $this->redirects->raw()])
            ->preProcess();

        $blueprint->setNamespace('vulpo-seo');

        return PublishForm::make($blueprint)
            ->title(__('Redirects'))
            ->values($fields->values()->toArray())
            ->submittingTo(cp_route('vulpo-seo.redirects.update'), 'POST');
    }

    public function update(Request $request)
    {
        $blueprint = $this->blueprint();

        $fields = $blueprint->fields()->addValues($request->all());
        $fields->validate();

        $values = $fields->process()->values()->toArray();

        $this->redirects->save($values['redirects'] ?? []);

        return true;
    }

    /**
     * Create a redirect from the 404 log.
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'from' => ['required', 'string'],
            'to' => ['required', 'string'],
            'status' => ['nullable', 'in:301,302,410'],
            'site' => ['nullable', 'string'],
        ]);

        $this->redirects->add(new Redirect(
            from: Redirect::normalize($data['from']),
            to: trim($data['to']),
            status: (int) ($data['status'] ?? 301),
            created_at: now()->toDateTimeString(),
            site: ($data['site'] ?? '') !== '' ? $data['site'] : null,
        ));

        return back()->with('success', __('Redirect created'));
    }

    private function blueprint(): Blueprint
    {
        $published = resource_path('blueprints/vendor/vulpo-seo');

        $directory = file_exists("{$published}/redirects.yaml")
            ? $published
            : __DIR__.'/../../../../resources/blueprints';

        return (new BlueprintRepository)->setDirectory($directory)->find('redirects');
    }
}
