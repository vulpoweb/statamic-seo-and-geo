<?php

use Statamic\Facades\User;
use Statamic\Testing\Concerns\PreventsSavingStacheItemsToDisk;
use Vulpo\Seo\Redirects\NotFoundLog;
use Vulpo\Seo\Redirects\Redirect;
use Vulpo\Seo\Redirects\RedirectRepository;

uses(PreventsSavingStacheItemsToDisk::class);

beforeEach(function () {
    $this->actingAs(User::make()->email('seo@example.com')->makeSuper()->save());
});

it('renders the redirects screen', function () {
    $this->get(cp_route('vulpo-seo.redirects.index'))->assertOk();
});

it('renders the 404 log', function () {
    $this->get(cp_route('vulpo-seo.not-found.index'))->assertOk()->assertSee('404 log');
});

it('renders the AI crawler log', function () {
    $this->get(cp_route('vulpo-seo.ai-crawlers.index'))->assertOk()->assertSee('AI crawlers');
});

it('saves redirects submitted from the control panel', function () {
    $this->post(cp_route('vulpo-seo.redirects.update'), [
        'redirects' => [
            ['from' => '/old', 'to' => '/new', 'status' => 301, 'match' => 'exact', 'active' => true],
        ],
    ])->assertOk();

    expect(app(RedirectRepository::class)->resolve('/old')['to'])->toBe('/new');
});

it('creates a redirect from the 404 log', function () {
    app(NotFoundLog::class)->record('/gone');

    $this->post(cp_route('vulpo-seo.redirects.store'), ['from' => '/gone', 'to' => '/here'])
        ->assertRedirect();

    expect(app(RedirectRepository::class)->has('/gone'))->toBeTrue();
});

it('offers to create a redirect when the matching rule is inactive', function () {
    app(NotFoundLog::class)->record('/paused');
    app(RedirectRepository::class)->add(new Redirect(from: '/paused', to: '/elsewhere', active: false));

    $this->get(cp_route('vulpo-seo.not-found.index'))
        ->assertOk()
        ->assertSee('/paused')
        // The row keeps the "add redirect" form instead of showing a destination.
        ->assertSee('name="to"', false)
        ->assertDontSee('/elsewhere');
});

it('shows the destination when the matching rule is active', function () {
    app(NotFoundLog::class)->record('/moved');
    app(RedirectRepository::class)->add(new Redirect(from: '/moved', to: '/elsewhere'));

    $this->get(cp_route('vulpo-seo.not-found.index'))->assertOk()->assertSee('/elsewhere');
});
