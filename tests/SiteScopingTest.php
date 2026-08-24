<?php

use Statamic\Facades\Site;
use Vulpo\Seo\Redirects\NotFoundLog;
use Vulpo\Seo\Redirects\Redirect;
use Vulpo\Seo\Redirects\RedirectRepository;

beforeEach(function () {
    // Statamic keeps everything single-site until multisite is switched on.
    config()->set('statamic.editions.pro', true);
    config()->set('statamic.system.multisite', true);

    Site::setSites([
        'default' => ['name' => 'Dutch', 'locale' => 'nl_BE', 'url' => 'http://localhost/'],
        'fr' => ['name' => 'French', 'locale' => 'fr_BE', 'url' => 'http://localhost/fr/'],
    ]);
});

it('applies a rule without a site to every site', function () {
    $redirect = new Redirect(from: '/old', to: '/new');

    expect($redirect->destinationFor('/old', 'default'))->toBe('/new');
    expect($redirect->destinationFor('/old', 'fr'))->toBe('/new');
});

it('keeps a scoped rule to its own site', function () {
    $redirect = new Redirect(from: '/contact', to: '/kontakt', site: 'fr');

    expect($redirect->destinationFor('/contact', 'fr'))->toBe('/kontakt');
    expect($redirect->destinationFor('/contact', 'default'))->toBeNull();
});

it('resolves per site', function () {
    $repository = app(RedirectRepository::class);

    $repository->save([
        ['from' => '/contact', 'to' => '/contact-nl', 'site' => 'default'],
        ['from' => '/contact', 'to' => '/contact-fr', 'site' => ['fr']],
    ]);
    $repository->flush();

    expect($repository->resolve('/contact', 'default')['to'])->toBe('/contact-nl');
    expect($repository->resolve('/contact', 'fr')['to'])->toBe('/contact-fr');
});

it('keeps rules for the same path on different sites side by side', function () {
    $repository = app(RedirectRepository::class);

    $repository->add(new Redirect(from: '/contact', to: '/contact-nl', site: 'default'));
    $repository->add(new Redirect(from: '/contact', to: '/contact-fr', site: 'fr'));

    // Adding the French rule must not replace the Dutch one.
    expect($repository->all())->toHaveCount(2);
    expect($repository->has('/contact', 'default'))->toBeTrue();
    expect($repository->has('/contact', 'fr'))->toBeTrue();
});

it('replaces a rule for the same path on the same site', function () {
    $repository = app(RedirectRepository::class);

    $repository->add(new Redirect(from: '/contact', to: '/first', site: 'fr'));
    $repository->add(new Redirect(from: '/contact', to: '/second', site: 'fr'));

    expect($repository->all())->toHaveCount(1);
    expect($repository->resolve('/contact', 'fr')['to'])->toBe('/second');
});

it('removes only the rule for the given site', function () {
    $repository = app(RedirectRepository::class);

    $repository->add(new Redirect(from: '/contact', to: '/contact-nl', site: 'default'));
    $repository->add(new Redirect(from: '/contact', to: '/contact-fr', site: 'fr'));
    $repository->remove('/contact', 'fr');

    expect($repository->all())->toHaveCount(1);
    expect($repository->resolve('/contact', 'default')['to'])->toBe('/contact-nl');
});

it('reads a rule saved before sites existed', function () {
    // Rules written by earlier versions have no site key at all.
    $repository = app(RedirectRepository::class);
    $repository->save([['from' => '/old', 'to' => '/new']]);
    $repository->flush();

    expect($repository->all()->first()->site)->toBeNull();
    expect($repository->resolve('/old', 'fr')['to'])->toBe('/new');
});

it('logs 404s per site', function () {
    $log = app(NotFoundLog::class);

    $log->record('/missing', null, 'default');
    $log->record('/missing', null, 'fr');
    $log->record('/missing', null, 'fr');

    expect($log->all())->toHaveCount(2);
    expect($log->all()->firstWhere('site', 'fr')['hits'])->toBe(2);
    expect($log->all()->firstWhere('site', 'default')['hits'])->toBe(1);

    $log->forget('/missing', 'fr');

    expect($log->all())->toHaveCount(1);
    expect($log->all()->first()['site'])->toBe('default');
});
