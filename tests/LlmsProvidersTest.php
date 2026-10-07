<?php

use Illuminate\Support\Facades\Cache;
use Statamic\Facades\Collection as CollectionFacade;
use Statamic\Facades\Entry;
use Statamic\Testing\Concerns\PreventsSavingStacheItemsToDisk;
use Vulpo\Seo\Contracts\LlmsFullProvider;
use Vulpo\Seo\Facades\Seo;
use Vulpo\Seo\Llms\LlmsLink;
use Vulpo\Seo\Llms\LlmsTxt;
use Vulpo\Seo\Support\Settings;

uses(PreventsSavingStacheItemsToDisk::class);

final class FakeCatalogue implements LlmsFullProvider
{
    public static bool $down = false;

    public static int $count = 3;

    public function llmsLinks(string $site): iterable
    {
        if (self::$down) {
            throw new RuntimeException('Core is unreachable.');
        }

        // The short file gets the categories, not the catalogue.
        yield new LlmsLink(url('/badkamer'), 'Badkamer', 'Handdoeken en badjassen.', 'Categorieën');
    }

    public function llmsFullLinks(string $site): iterable
    {
        if (self::$down) {
            throw new RuntimeException('Core is unreachable.');
        }

        foreach (range(1, self::$count) as $i) {
            yield new LlmsLink(url('/product/p'.$i), 'Product '.$i, 'Vanaf € 19,95', 'Producten');
        }
    }
}

beforeEach(function () {
    FakeCatalogue::$down = false;
    FakeCatalogue::$count = 3;

    Settings::swap(['site_name' => 'Gloed interieur']);
    Cache::flush();
    Seo::llms()->flush();

    CollectionFacade::make('pages')->routes('{slug}')->save();
    Entry::make()->collection('pages')->slug('contact')
        ->data(['title' => 'Contact', 'seo_description' => 'Bel of kom langs.'])
        ->published(true)->save();
});

it('files provider links under their own heading', function () {
    Seo::llms()->register(FakeCatalogue::class);

    expect(LlmsTxt::forCurrentSite()->render())
        ->toContain('## Categorieën')
        ->toContain('- [Badkamer]('.url('/badkamer').'): Handdoeken en badjassen.')
        // The site's own pages are still there.
        ->toContain('- [Contact]('.url('/contact').'): Bel of kom langs.');
});

it('keeps the catalogue out of the short file and in the full one', function () {
    // llms.txt is meant to be read whole. A thousand products in it is not a
    // map, it is a dump.
    Seo::llms()->register(FakeCatalogue::class);

    expect(LlmsTxt::forCurrentSite()->render())
        ->toContain('## Categorieën')
        ->not->toContain('/product/p1');

    expect(LlmsTxt::forCurrentSite()->renderFull())
        ->toContain('## Producten')
        ->toContain('- [Product 1]('.url('/product/p1').'): Vanaf € 19,95');
});

it('serves both files', function () {
    Seo::llms()->register(FakeCatalogue::class);

    $this->get('/llms.txt')->assertOk()->assertSee('Categorieën');
    $this->get('/llms-full.txt')->assertOk()->assertSee('Product 1');
});

it('falls back to the default heading', function () {
    Settings::swap(['site_name' => 'Gloed', 'llms_default_group' => 'Elders']);
    Seo::llms()->register(fn () => [new LlmsLink(url('/x'), 'Ergens')], 'misc');

    expect(LlmsTxt::forCurrentSite()->render())->toContain('## Elders');
});

it('caps one heading so it cannot crowd out the pages', function () {
    config()->set('seo-and-geo.llms.max_per_group', 2);
    FakeCatalogue::$count = 10;

    Seo::llms()->register(fn (string $site) => array_map(
        fn (int $i) => new LlmsLink(url('/product/p'.$i), 'Product '.$i, null, 'Producten'),
        range(1, 10),
    ), 'catalogue');

    $rendered = LlmsTxt::forCurrentSite()->render();

    expect(substr_count($rendered, ']('.url('/product/')))->toBe(2);
});

it('lets the entries fill the budget first', function () {
    // Adding a catalogue must never push the shop's own pages out.
    config()->set('seo-and-geo.llms.max_urls', 1);

    Seo::llms()->register(fn () => [new LlmsLink(url('/badkamer'), 'Badkamer')], 'cats');

    $rendered = LlmsTxt::forCurrentSite()->render();

    expect($rendered)->toContain('/contact')->not->toContain('/badkamer');
});

it('keeps the pages when a provider falls over', function () {
    Seo::llms()->register(FakeCatalogue::class);
    FakeCatalogue::$down = true;

    expect(LlmsTxt::forCurrentSite()->render())
        ->toContain('/contact')
        ->not->toContain('/badkamer');
});

it('serves the last good links while the source is down', function () {
    config()->set('seo-and-geo.llms.cache_minutes', 60);
    Seo::llms()->register(FakeCatalogue::class);

    LlmsTxt::forCurrentSite()->render();

    FakeCatalogue::$down = true;
    LlmsTxt::flushCache();

    expect(LlmsTxt::forCurrentSite()->render())->toContain('/badkamer');
});
