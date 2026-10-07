<?php

use Illuminate\Support\Facades\Cache;
use Statamic\Facades\Collection as CollectionFacade;
use Statamic\Facades\Entry;
use Statamic\Testing\Concerns\PreventsSavingStacheItemsToDisk;
use Vulpo\Seo\Contracts\SitemapProvider;
use Vulpo\Seo\Facades\Seo;
use Vulpo\Seo\Sitemap\Sitemap;
use Vulpo\Seo\Sitemap\SitemapUrl;
use Vulpo\Seo\Support\Settings;

uses(PreventsSavingStacheItemsToDisk::class);

/*
 * Products on a Laravel route are invisible to a sitemap built from entries,
 * which on a shop means the entire catalogue is missing and nobody notices,
 * because the sitemap itself looks perfectly healthy.
 */

final class FakeProducts implements SitemapProvider
{
    public static bool $down = false;

    public static int $calls = 0;

    /** @var array<int, string> */
    public static array $slugs = ['badmat', 'handdoek'];

    public function sitemapUrls(string $site): iterable
    {
        self::$calls++;

        if (self::$down) {
            throw new RuntimeException('Core is unreachable.');
        }

        foreach (self::$slugs as $slug) {
            yield SitemapUrl::make('/product/'.$slug)
                ->lastmod('2026-10-01')
                ->changefreq('daily')
                ->priority(0.9)
                ->image('/img/'.$slug.'.jpg', 'Foto van '.$slug);
        }
    }
}

final class EndlessProvider implements SitemapProvider
{
    public function sitemapUrls(string $site): iterable
    {
        $i = 0;

        while (true) {
            yield SitemapUrl::make('/product/'.$i++);
        }
    }
}

beforeEach(function () {
    FakeProducts::$down = false;
    FakeProducts::$calls = 0;
    FakeProducts::$slugs = ['badmat', 'handdoek'];

    Settings::swap([]);
    Cache::flush();
    Seo::sitemap()->flush();

    CollectionFacade::make('pages')->routes('{slug}')->save();
    Entry::make()->collection('pages')->slug('contact')->data(['title' => 'Contact'])->published(true)->save();
});

it('puts a provider url in the sitemap alongside the entries', function () {
    Seo::sitemap()->register(FakeProducts::class);

    $locs = array_column(Sitemap::forCurrentSite()->urls(), 'loc');

    expect($locs)
        ->toContain(url('/contact'))
        ->toContain(url('/product/badmat'))
        ->toContain(url('/product/handdoek'))
        // Sorted and deduped with everything else, not bolted on at the end.
        ->and($locs)->toBe(collect($locs)->sort()->values()->all());
});

it('serves the provider urls as xml, with their images', function () {
    Seo::sitemap()->register(FakeProducts::class);

    $this->get('/sitemap.xml')
        ->assertOk()
        ->assertSee('xmlns:image="http://www.google.com/schemas/sitemap-image/1.1"', false)
        ->assertSee('<loc>'.url('/product/badmat').'</loc>', false)
        ->assertSee('<image:loc>'.url('/img/badmat.jpg').'</image:loc>', false)
        ->assertSee('<image:title>Foto van badmat</image:title>', false)
        ->assertSee('<lastmod>2026-10-01</lastmod>', false)
        ->assertSee('<priority>0.9</priority>', false);
});

it('leaves out a lastmod the source does not have', function () {
    // Inventing one is worse than omitting it: it tells a crawler to come back
    // for a page that has not changed, and stops meaning anything at all once
    // every page claims today.
    Seo::sitemap()->register(fn () => [SitemapUrl::make('/product/badmat')], 'products');

    $url = collect(Sitemap::forCurrentSite()->urls())->firstWhere('loc', url('/product/badmat'));

    expect($url['lastmod'])->toBeNull();

    $this->get('/sitemap.xml')->assertDontSee('<lastmod></lastmod>', false);
});

it('keeps the entries when a provider falls over', function () {
    Seo::sitemap()->register(FakeProducts::class);
    FakeProducts::$down = true;

    $locs = array_column(Sitemap::forCurrentSite()->urls(), 'loc');

    expect($locs)->toContain(url('/contact'))->not->toContain(url('/product/badmat'));
});

it('serves yesterdays products while the source is down', function () {
    config()->set('seo-and-geo.sitemap.cache_minutes', 60);
    Seo::sitemap()->register(FakeProducts::class);

    // One good build, to lay down the last-good copy.
    Sitemap::forCurrentSite()->urls();

    FakeProducts::$down = true;
    Sitemap::flushCache();

    $locs = array_column(Sitemap::forCurrentSite()->urls(), 'loc');

    // A shrinking sitemap does not read as "the API is down", it reads as
    // "these pages are gone".
    expect($locs)->toContain(url('/product/badmat'));
});

it('retries within minutes rather than at the end of the hour', function () {
    config()->set('seo-and-geo.sitemap.cache_minutes', 60);
    config()->set('seo-and-geo.sitemap.retry_minutes', 5);

    Seo::sitemap()->register(FakeProducts::class);
    FakeProducts::$down = true;

    Sitemap::forCurrentSite()->urls();

    $ttl = Cache::driver()->getStore();
    expect(Cache::has('vulpo-seo:sitemap:default'))->toBeTrue();

    // The degraded build is cached, but only briefly: travelling past the retry
    // window gets a fresh attempt, and the recovered source is picked up.
    $this->travel(6)->minutes();
    FakeProducts::$down = false;

    expect(array_column(Sitemap::forCurrentSite()->urls(), 'loc'))
        ->toContain(url('/product/badmat'));
});

it('does not throw away the fallback when a page is saved', function () {
    config()->set('seo-and-geo.sitemap.cache_minutes', 60);
    Seo::sitemap()->register(FakeProducts::class);

    Sitemap::forCurrentSite()->urls();

    expect(Cache::has('vulpo-seo:sitemap:default:p:'.FakeProducts::class.':last-good'))->toBeTrue();

    Sitemap::flushCache();

    expect(Cache::has('vulpo-seo:sitemap:default:p:'.FakeProducts::class))->toBeFalse()
        ->and(Cache::has('vulpo-seo:sitemap:default:p:'.FakeProducts::class.':last-good'))->toBeTrue();
});

it('asks a provider once and reuses the answer', function () {
    config()->set('seo-and-geo.sitemap.cache_minutes', 60);
    Seo::sitemap()->register(FakeProducts::class);

    Sitemap::forCurrentSite()->urls();
    Sitemap::forCurrentSite()->urls();

    expect(FakeProducts::$calls)->toBe(1);
});

it('counts a provider registered twice once', function () {
    Seo::sitemap()->register(FakeProducts::class);
    Seo::sitemap()->register(FakeProducts::class);

    $locs = array_column(Sitemap::forCurrentSite()->urls(), 'loc');

    expect(array_count_values($locs)[url('/product/badmat')])->toBe(1);
});

it('refuses a closure with no name to cache it under', function () {
    expect(fn () => Seo::sitemap()->register(fn () => []))
        ->toThrow(InvalidArgumentException::class);
});

it('chunks provider urls along with the rest', function () {
    config()->set('seo-and-geo.sitemap.max_urls', 2);
    Seo::sitemap()->register(FakeProducts::class);

    $sitemap = Sitemap::forCurrentSite();

    // contact + two products.
    expect($sitemap->pages())->toBe(2)
        ->and($sitemap->page(1))->toHaveCount(2)
        ->and($sitemap->page(2))->toHaveCount(1);

    $this->get('/sitemap.xml')->assertSee('<sitemapindex', false);
});

it('cuts off a source that never stops', function () {
    config()->set('seo-and-geo.sitemap.max_provider_urls', 10);
    Seo::sitemap()->register(EndlessProvider::class);

    expect(Sitemap::forCurrentSite()->urls())->toHaveCount(11); // 10 + contact
});

it('lets a package drop only its own slice', function () {
    config()->set('seo-and-geo.sitemap.cache_minutes', 60);
    Seo::sitemap()->register(FakeProducts::class);

    Sitemap::forCurrentSite()->urls();
    FakeProducts::$slugs = ['badmat', 'handdoek', 'plaid'];

    Sitemap::flushProvider(FakeProducts::class);

    expect(array_column(Sitemap::forCurrentSite()->urls(), 'loc'))
        ->toContain(url('/product/plaid'));
});
