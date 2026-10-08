<?php

use Vulpo\Seo\Tests\RoutesConfiguredTestCase;

/*
 * The config file is seo-and-geo.php, so the keys live under `seo-and-geo`.
 * routes/web.php used to read them under `seo`, which meant every lookup missed
 * and fell through to its hardcoded default: the routes still worked, and the
 * published config was silently inert. These tests fail the moment that regresses.
 */

final class CustomRoutesTest extends RoutesConfiguredTestCase
{
    protected array $routeConfig = [
        'seo-and-geo.sitemap.route' => 'sitemap_index.xml',
        'seo-and-geo.robots.route' => 'robots-txt',
        'seo-and-geo.llms.route' => 'ai.txt',
    ];

    public function test_it_serves_the_sitemap_at_the_configured_route(): void
    {
        $this->get('/sitemap_index.xml')->assertOk()->assertSee('<urlset', false);
        $this->get('/sitemap.xml')->assertNotFound();
    }

    public function test_it_serves_the_paged_sitemap_alongside_the_configured_route(): void
    {
        $this->get('/sitemap_index-1.xml')->assertOk();
    }

    public function test_it_serves_robots_and_llms_at_their_configured_routes(): void
    {
        $this->get('/robots-txt')->assertOk()->assertSee('User-agent');
        $this->get('/ai.txt')->assertOk();

        $this->get('/robots.txt')->assertNotFound();
        $this->get('/llms.txt')->assertNotFound();
    }
}
