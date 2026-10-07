<?php

namespace Vulpo\Seo\Tests;

/**
 * The routes in routes/web.php are evaluated while the addon's provider boots,
 * so config()->set() inside a test body runs far too late to affect them. This
 * sets the values before the application is built instead.
 *
 * Which is also why the namespace bug these tests guard against survived so
 * long: the existing HTTP tests only ever hit the default URLs, and a config
 * key that is never read looks exactly like one that happens to match.
 */
abstract class RoutesConfiguredTestCase extends TestCase
{
    /** @var array<string, mixed> */
    protected array $routeConfig = [];

    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        foreach ($this->routeConfig as $key => $value) {
            $app['config']->set($key, $value);
        }
    }
}
