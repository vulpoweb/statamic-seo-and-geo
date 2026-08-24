<?php

namespace Vulpo\Seo\Http\Controllers\CP;

use Illuminate\Http\Request;
use Illuminate\View\View;
use Statamic\Facades\Site;
use Statamic\Facades\User;
use Vulpo\Seo\Redirects\NotFoundLog;
use Vulpo\Seo\Redirects\RedirectRepository;

class NotFoundLogController
{
    private const PER_PAGE = 25;

    public function __construct(
        private readonly NotFoundLog $log,
        private readonly RedirectRepository $redirects,
    ) {}

    public function index(Request $request): View
    {
        $search = trim((string) $request->query('search', ''));

        $rows = $this->log->all()
            ->when($search !== '', fn ($rows) => $rows->filter(
                fn (array $row) => str_contains(strtolower($row['path']), strtolower($search))
                    || str_contains(strtolower((string) $row['referer']), strtolower($search)),
            ))
            ->values();

        $page = max(1, (int) $request->query('page', 1));
        $pages = max(1, (int) ceil($rows->count() / self::PER_PAGE));
        $page = min($page, $pages);

        // Never pass a `page` variable to a control panel view: statamic::layout
        // renders `$page` into the root element's data-page as Inertia's page
        // object, so shadowing it stops the whole control panel app from booting.
        return view('vulpo-seo::cp.not-found-log', [
            'title' => __('404 log'),
            'rows' => $rows->forPage($page, self::PER_PAGE),
            'total' => $rows->count(),
            'currentPage' => $page,
            'totalPages' => $pages,
            'search' => $search,
            'multisite' => Site::all()->count() > 1,
            'canEdit' => (bool) User::current()?->can('edit vulpo seo'),
            // Only active rules count: an inactive one is why the URL 404s.
            'redirects' => $this->redirects->all()->filter->active,
        ]);
    }

    public function destroy(Request $request)
    {
        $this->log->forget((string) $request->input('path'), $request->input('site'));

        return back();
    }

    public function clear()
    {
        $this->log->clear();

        return back();
    }
}
