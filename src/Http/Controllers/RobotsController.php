<?php

namespace Vulpo\Seo\Http\Controllers;

use Illuminate\Http\Response;
use Vulpo\Seo\Robots\RobotsTxt;
use Vulpo\Seo\Support\Settings;

class RobotsController
{
    public function __invoke(RobotsTxt $robots): Response
    {
        abort_unless(Settings::bool('robots_enabled', true), 404);

        return response($robots->render())->header('Content-Type', 'text/plain; charset=UTF-8');
    }
}
