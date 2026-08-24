<?php

use Statamic\Facades\Role;
use Statamic\Facades\User;
use Statamic\Testing\Concerns\PreventsSavingStacheItemsToDisk;
use Vulpo\Seo\AiCrawlers\CrawlerLog;
use Vulpo\Seo\Redirects\NotFoundLog;
use Vulpo\Seo\Redirects\Redirect;
use Vulpo\Seo\Redirects\RedirectRepository;

uses(PreventsSavingStacheItemsToDisk::class);

/**
 * A user who may open the control panel and look at the SEO screens, but not
 * change anything. Control panel access is its own permission, so a role without
 * it never even reaches ours.
 */
function readOnlyUser(string $email, string $role): Statamic\Contracts\Auth\User
{
    // More than one user needs Pro, and this test needs a second one.
    config()->set('statamic.editions.pro', true);

    $role = Role::make($role)
        ->addPermission('access cp')
        ->addPermission('view vulpo seo')
        ->save();

    $user = User::make()->email($email)->save();
    $user->assignRole($role)->save();

    return $user;
}

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

it('hides the write controls from a read-only user', function () {
    // A user with view but not edit sees the data and no way to change it.
    $user = readOnlyUser('readonly@example.com', 'seo-viewer');

    $this->actingAs($user);

    app(NotFoundLog::class)->record('/missing');
    app(CrawlerLog::class)->record('Claude', '/');

    // The button labels themselves are asserted through their form actions: the
    // CP ships every translation string to the browser, so the words appear in
    // the page's JS payload whether or not the button is rendered.
    $this->get(cp_route('vulpo-seo.not-found.index'))
        ->assertOk()
        ->assertSee('/missing')
        ->assertDontSee(cp_route('vulpo-seo.not-found.clear'), false)
        ->assertDontSee(cp_route('vulpo-seo.not-found.destroy'), false)
        ->assertDontSee('name="to"', false);

    $this->get(cp_route('vulpo-seo.ai-crawlers.index'))
        ->assertOk()
        ->assertSee('Claude')
        ->assertDontSee(cp_route('vulpo-seo.ai-crawlers.clear'), false);
});

it('blocks write actions for a read-only user', function () {
    $user = readOnlyUser('readonly2@example.com', 'seo-viewer-2');

    $this->actingAs($user);

    // Statamic's control panel bounces an unauthorised write back to /cp rather
    // than answering 403.
    $this->post(cp_route('vulpo-seo.not-found.clear'))->assertRedirect(cp_route('index'));
    $this->post(cp_route('vulpo-seo.redirects.store'), ['from' => '/a', 'to' => '/b'])
        ->assertRedirect(cp_route('index'));

    expect(app(RedirectRepository::class)->has('/a'))->toBeFalse();
});

/**
 * statamic::layout renders the view's `$page` variable into the root element as
 * Inertia's page object. A controller passing its own `page` (a pagination
 * number, say) replaces it, Inertia then boots with no component name, and the
 * whole control panel app dies with "Cannot read properties of undefined" —
 * leaving an unstyled page. Only a browser shows that, so the guard is here.
 */
it('leaves the control panel page object intact on every screen', function () {
    app(NotFoundLog::class)->record('/missing');
    app(CrawlerLog::class)->record('ClaudeBot', '/');

    $screens = [
        cp_route('vulpo-seo.not-found.index'),
        cp_route('vulpo-seo.not-found.index').'?page=2',
        cp_route('vulpo-seo.ai-crawlers.index'),
    ];

    foreach ($screens as $screen) {
        $html = $this->get($screen)->assertOk()->content();

        preg_match('/id="statamic"\s+data-page="([^"]*)"/', $html, $matches);

        $data = json_decode(html_entity_decode($matches[1] ?? '', ENT_QUOTES), true);

        expect($data)->toBeArray("data-page is not an object on {$screen}");
        expect($data['component'] ?? null)->toBe('NonInertiaPage', "no component on {$screen}");
    }
});
