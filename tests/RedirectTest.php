<?php

use Vulpo\Seo\Redirects\Redirect;

it('matches an exact path regardless of slashes and case', function () {
    $redirect = Redirect::fromArray(['from' => 'old-page/', 'to' => '/new-page']);

    expect($redirect->from)->toBe('/old-page');
    expect($redirect->destinationFor('/old-page'))->toBe('/new-page');
    expect($redirect->destinationFor('/Old-Page'))->toBe('/new-page');
    expect($redirect->destinationFor('/other'))->toBeNull();
});

it('matches wildcards and carries the remainder over', function () {
    $redirect = Redirect::fromArray([
        'from' => '/blog/*',
        'to' => '/news/*',
        'match' => Redirect::MATCH_WILDCARD,
    ]);

    expect($redirect->destinationFor('/blog/hello-world'))->toBe('/news/hello-world');
    expect($redirect->destinationFor('/blog/2026/hello'))->toBe('/news/2026/hello');
    expect($redirect->destinationFor('/shop/hello'))->toBeNull();
});

it('matches regexes with captures', function () {
    $redirect = Redirect::fromArray([
        'from' => '^/blog/(\d+)/(.+)$',
        'to' => '/news/$2',
        'match' => Redirect::MATCH_REGEX,
    ]);

    expect($redirect->destinationFor('/blog/2026/hello-world'))->toBe('/news/hello-world');
    expect($redirect->destinationFor('/blog/hello-world'))->toBeNull();
});

it('ignores inactive and nonsensical rules', function () {
    expect(Redirect::fromArray(['from' => '/a', 'to' => '/b', 'active' => false])->destinationFor('/a'))->toBeNull();
    expect(Redirect::fromArray(['from' => '/a', 'to' => '/a'])->isValid())->toBeFalse();
    expect(Redirect::fromArray(['from' => '', 'to' => '/b'])->isValid())->toBeFalse();
});

it('survives an invalid regex', function () {
    $redirect = Redirect::fromArray([
        'from' => '^/blog/([0-9]+$',
        'to' => '/news',
        'match' => Redirect::MATCH_REGEX,
    ]);

    expect($redirect->destinationFor('/blog/12'))->toBeNull();
});

it('leaves full URLs and regexes alone when normalising', function () {
    expect(Redirect::normalize('https://example.com/page'))->toBe('https://example.com/page');
    expect(Redirect::normalize('^/blog/(.*)$', Redirect::MATCH_REGEX))->toBe('^/blog/(.*)$');
});
