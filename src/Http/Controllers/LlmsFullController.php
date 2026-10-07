<?php

namespace Vulpo\Seo\Http\Controllers;

use Illuminate\Http\Response;
use Vulpo\Seo\Llms\LlmsTxt;
use Vulpo\Seo\Support\PublicCache;
use Vulpo\Seo\Support\Settings;

/**
 * llms-full.txt: everything, where llms.txt is a map.
 *
 * Only worth serving when something has registered more than belongs in the
 * short version -- a product catalogue, typically.
 */
class LlmsFullController
{
    public function __invoke(): Response
    {
        abort_unless(Settings::bool('llms_enabled', true), 404);

        return PublicCache::apply(
            response(LlmsTxt::forCurrentSite()->renderFull())
                ->header('Content-Type', 'text/plain; charset=UTF-8'),
            'llms',
        );
    }
}
