<?php

namespace Vulpo\Seo\Http\Controllers;

use Illuminate\Http\Response;
use Vulpo\Seo\Llms\LlmsTxt;
use Vulpo\Seo\Support\Settings;

class LlmsController
{
    public function __invoke(): Response
    {
        abort_unless(Settings::bool('llms_enabled', true), 404);

        return response(LlmsTxt::forCurrentSite()->render())
            ->header('Content-Type', 'text/plain; charset=UTF-8');
    }
}
