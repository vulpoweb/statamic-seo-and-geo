<?php

use Illuminate\Support\Facades\File;
use Vulpo\Seo\Redirects\RedirectRepository;

function csv(string $contents): string
{
    $path = storage_path('framework/testing/redirects-'.uniqid().'.csv');

    File::ensureDirectoryExists(dirname($path));
    File::put($path, $contents);

    return $path;
}

it('imports a csv with a header row', function () {
    $path = csv("from,to,status\n/old,/new,301\n/gone,/,410\n");

    $this->artisan("vulpo:seo:import-redirects {$path}")->assertSuccessful();

    $redirects = app(RedirectRepository::class)->all()->keyBy->from;

    expect($redirects)->toHaveCount(2);
    expect($redirects['/old']->to)->toBe('/new');
    expect($redirects['/gone']->status)->toBe(410);
});

it('imports a csv without a header row', function () {
    $path = csv("/old,/new\n");

    $this->artisan("vulpo:seo:import-redirects {$path}")->assertSuccessful();

    expect(app(RedirectRepository::class)->all()->first()->status)->toBe(301);
});

it('accepts alternative column names and marks wildcards', function () {
    $path = csv("source,destination,code\n/blog/*,/news,302\n");

    $this->artisan("vulpo:seo:import-redirects {$path}")->assertSuccessful();

    $redirect = app(RedirectRepository::class)->all()->first();

    expect($redirect->match)->toBe('wildcard');
    expect($redirect->status)->toBe(302);
});

it('skips incomplete rows instead of failing the import', function () {
    $path = csv("from,to\n/old,/new\n/broken,\n,/nowhere\n");

    $this->artisan("vulpo:seo:import-redirects {$path}")
        ->expectsOutputToContain('Redirects imported: 1')
        ->assertSuccessful();

    expect(app(RedirectRepository::class)->all())->toHaveCount(1);
});

it('writes nothing on a dry run', function () {
    $path = csv("from,to\n/old,/new\n");

    $this->artisan("vulpo:seo:import-redirects {$path} --dry-run")->assertSuccessful();

    expect(app(RedirectRepository::class)->all())->toBeEmpty();
});

it('fails cleanly when the file is missing', function () {
    $this->artisan('vulpo:seo:import-redirects /nope/redirects.csv')->assertFailed();
});
