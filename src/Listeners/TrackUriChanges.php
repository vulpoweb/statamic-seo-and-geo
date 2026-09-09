<?php

namespace Vulpo\Seo\Listeners;

use Statamic\Contracts\Entries\Entry as EntryContract;
use Statamic\Events\EntryDeleted;
use Statamic\Events\EntrySaved;
use Vulpo\Seo\Redirects\Redirect;
use Vulpo\Seo\Redirects\RedirectRepository;
use Vulpo\Seo\Redirects\UriLedger;
use Vulpo\Seo\Support\Settings;

/**
 * Keeps old links working: when an entry's URL changes — a renamed slug, a moved
 * page, a changed date — the old URL is redirected to the new one. Pages with
 * children also get a wildcard rule, because Statamic rewrites the children's
 * URLs without saving them individually.
 */
class TrackUriChanges
{
    public function __construct(
        private readonly RedirectRepository $redirects,
        private readonly UriLedger $ledger,
    ) {}

    public function handleSaved(EntrySaved $event): void
    {
        $entry = $event->entry;

        if (! $entry instanceof EntryContract || ! $id = $entry->id()) {
            return;
        }

        // First run on an existing site: record where everything lives today,
        // so later changes can be compared against it.
        if ($this->ledger->isEmpty()) {
            $this->ledger->prime();

            return;
        }

        $site = (string) $entry->locale();
        $previousUri = $this->ledger->get((string) $id, $site);
        $newUri = ($uri = $entry->uri()) ? '/'.trim($uri, '/') : null;

        $this->ledger->remember((string) $id, $site, $newUri);

        if (! $previousUri || ! $newUri || $previousUri === $newUri || ! $this->enabled()) {
            return;
        }

        $this->redirects->add(new Redirect(
            from: $previousUri,
            to: $newUri,
            source: Redirect::SOURCE_AUTO,
            created_at: now()->toDateTimeString(),
            site: $site,
        ));

        if ($this->hasChildren($entry)) {
            $this->redirects->add(new Redirect(
                from: $previousUri.'/*',
                to: $newUri.'/*',
                match: Redirect::MATCH_WILDCARD,
                source: Redirect::SOURCE_AUTO,
                created_at: now()->toDateTimeString(),
                site: $site,
            ));
        }
    }

    public function handleDeleted(EntryDeleted $event): void
    {
        $entry = $event->entry;

        if ($entry instanceof EntryContract && $id = $entry->id()) {
            $this->ledger->remember((string) $id, (string) $entry->locale(), null);
        }
    }

    private function enabled(): bool
    {
        return config('seo-and-geo.redirects.enabled', true)
            && Settings::bool('auto_create_redirects', (bool) config('seo-and-geo.redirects.auto_create_on_slug_change', true));
    }

    private function hasChildren(EntryContract $entry): bool
    {
        if (! method_exists($entry, 'page') || ! $page = $entry->page()) {
            return false;
        }

        try {
            return $page->pages()->all()->isNotEmpty();
        } catch (\Throwable) {
            return false;
        }
    }
}
