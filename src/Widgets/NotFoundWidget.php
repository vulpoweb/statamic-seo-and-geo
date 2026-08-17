<?php

namespace Vulpo\Seo\Widgets;

use Statamic\Facades\User;
use Statamic\Widgets\VueComponent;
use Statamic\Widgets\Widget;
use Vulpo\Seo\Redirects\NotFoundLog;

/**
 * Dashboard widget listing the URLs that recently 404'd.
 *
 * The HTML is compiled as a Vue template by the control panel's
 * dynamic-html-renderer, so it can use the CP's own UI components.
 */
class NotFoundWidget extends Widget
{
    protected static $handle = 'vulpo_seo_404s';

    protected static $title = 'Recent 404s';

    public function component()
    {
        if (! User::current()?->can('view vulpo seo')) {
            return null;
        }

        $rows = app(NotFoundLog::class)->all()->take((int) $this->config('limit', 5));

        return VueComponent::render('dynamic-html-renderer', [
            'html' => view('vulpo-seo::widgets.not-found', [
                'rows' => $rows,
                'title' => $this->config('title', __('Recent 404s')),
            ])->render(),
        ]);
    }
}
