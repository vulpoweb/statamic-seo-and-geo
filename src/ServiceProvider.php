<?php

namespace Vulpo\Seo;

use Illuminate\Support\Facades\Event;
use Statamic\Events\AddonSettingsSaved;
use Statamic\Events\EntryBlueprintFound;
use Statamic\Events\EntryDeleted;
use Statamic\Events\EntrySaved;
use Statamic\Events\TermBlueprintFound;
use Statamic\Events\TermDeleted;
use Statamic\Events\TermSaved;
use Statamic\Facades\CP\Nav;
use Statamic\Facades\Permission;
use Statamic\Providers\AddonServiceProvider;
use Vulpo\Seo\AiCrawlers\CrawlerLog;
use Vulpo\Seo\Console\IndexUrisCommand;
use Vulpo\Seo\Console\MigrateCommand;
use Vulpo\Seo\Fieldtypes\SeoPreview;
use Vulpo\Seo\Http\Middleware\HandleNotFound;
use Vulpo\Seo\Http\Middleware\LogAiCrawlers;
use Vulpo\Seo\Listeners\FlushCaches;
use Vulpo\Seo\Listeners\InjectFields;
use Vulpo\Seo\Listeners\TrackUriChanges;
use Vulpo\Seo\Redirects\NotFoundLog;
use Vulpo\Seo\Redirects\RedirectRepository;
use Vulpo\Seo\Redirects\UriLedger;
use Vulpo\Seo\Tags\SeoTags;
use Vulpo\Seo\Widgets\AiCrawlersWidget;
use Vulpo\Seo\Widgets\NotFoundWidget;

class ServiceProvider extends AddonServiceProvider
{
    protected $tags = [
        SeoTags::class,
    ];

    protected $fieldtypes = [
        SeoPreview::class,
    ];

    protected $widgets = [
        NotFoundWidget::class,
        AiCrawlersWidget::class,
    ];

    /**
     * A buildless control panel script: it registers the preview fieldtype
     * through the globals Statamic exposes (window.Statamic, window.Vue,
     * window.__STATAMIC__), so there is no bundle to recompile per release.
     */
    protected $scripts = [
        __DIR__.'/../resources/js/cp.js',
    ];

    protected $commands = [
        MigrateCommand::class,
        IndexUrisCommand::class,
    ];

    protected $routes = [
        'cp' => __DIR__.'/../routes/cp.php',
        'web' => __DIR__.'/../routes/web.php',
    ];

    protected $middlewareGroups = [
        'web' => [
            HandleNotFound::class,
            LogAiCrawlers::class,
        ],
    ];

    protected $viewNamespace = 'vulpo-seo';

    protected string $navIcon = '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/><path d="M8 11h6M11 8v6"/></svg>';

    public function register(): void
    {
        parent::register();

        $this->app->singleton(RedirectRepository::class);
        $this->app->singleton(NotFoundLog::class);
        $this->app->singleton(UriLedger::class);
        $this->app->singleton(CrawlerLog::class);
    }

    public function bootAddon(): void
    {
        $this->publishes([
            __DIR__.'/../resources/blueprints' => resource_path('blueprints/vendor/vulpo-seo'),
        ], 'vulpo-seo-blueprints');

        $this->registerListeners();
        $this->registerPermissions();
        $this->registerNav();
    }

    private function registerListeners(): void
    {
        Event::listen(EntryBlueprintFound::class, [InjectFields::class, 'handleEntryBlueprint']);
        Event::listen(TermBlueprintFound::class, [InjectFields::class, 'handleTermBlueprint']);

        Event::listen(EntrySaved::class, [TrackUriChanges::class, 'handleSaved']);
        Event::listen(EntryDeleted::class, [TrackUriChanges::class, 'handleDeleted']);

        foreach ([EntrySaved::class, EntryDeleted::class, TermSaved::class, TermDeleted::class] as $event) {
            Event::listen($event, [FlushCaches::class, 'handleContentSaved']);
        }

        Event::listen(AddonSettingsSaved::class, [FlushCaches::class, 'handleSettingsSaved']);
    }

    private function registerPermissions(): void
    {
        Permission::register('view vulpo seo')
            ->label(__('View & edit SEO settings, redirects and logs'));
    }

    private function registerNav(): void
    {
        Nav::extend(function ($nav) {
            $nav->create(__('SEO'))
                ->section('Tools')
                ->can('view vulpo seo')
                ->icon($this->navIcon)
                ->route('addons.settings.edit', 'seo')
                ->children([
                    $nav->item(__('Settings'))->route('addons.settings.edit', 'seo'),
                    $nav->item(__('Redirects'))->route('vulpo-seo.redirects.index'),
                    $nav->item(__('404 log'))->route('vulpo-seo.not-found.index'),
                    $nav->item(__('AI crawlers'))->route('vulpo-seo.ai-crawlers.index'),
                ]);
        });
    }
}
