<?php

use Vulpo\Seo\Tests\RoutesConfiguredTestCase;

/*
 * The config file is seo-and-geo.php, so the keys live under `seo-and-geo`.
 * routes/web.php used to read them under `seo`, which meant every lookup missed
 * and fell through to its hardcoded default: the routes still worked, and the
 * published config was silently inert. These tests fail the moment that regresses.
 */

final class DisabledRoutesTest extends RoutesConfiguredTestCase
{
    protected array $routeConfig = [
        'seo-and-geo.sitemap.enabled' => false,
        'seo-and-geo.robots.enabled' => false,
        'seo-and-geo.llms.enabled' => false,
    ];

    public function test_it_registers_nothing_when_every_route_is_disabled(): void
    {
        $this->get('/sitemap.xml')->assertNotFound();
        $this->get('/sitemap-1.xml')->assertNotFound();
        $this->get('/robots.txt')->assertNotFound();
        $this->get('/llms.txt')->assertNotFound();
    }
}
