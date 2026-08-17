<?php

namespace Vulpo\Seo\Http\Controllers\CP;

use Illuminate\Http\Request;
use Illuminate\View\View;
use Vulpo\Seo\Redirects\NotFoundLog;
use Vulpo\Seo\Redirects\RedirectRepository;

class NotFoundLogController
{
    public function __construct(
        private readonly NotFoundLog $log,
        private readonly RedirectRepository $redirects,
    ) {}

    public function index(): View
    {
        return view('vulpo-seo::cp.not-found-log', [
            'title' => __('404 log'),
            'rows' => $this->log->all(),
            // Only active rules count: an inactive one is why the URL 404s.
            'redirects' => $this->redirects->all()->filter->active->keyBy->from,
        ]);
    }

    public function destroy(Request $request)
    {
        $this->log->forget((string) $request->input('path'));

        return back();
    }

    public function clear()
    {
        $this->log->clear();

        return back();
    }
}
