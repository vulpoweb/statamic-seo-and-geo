<?php

namespace Vulpo\Seo\Contracts;

use Vulpo\Seo\Llms\LlmsLink;

interface LlmsProvider
{
    /**
     * Links for llms.txt. Implement LlmsFullProvider as well to list more in
     * llms-full.txt than belongs in the short map.
     *
     * @return iterable<int, LlmsLink>
     */
    public function llmsLinks(string $site): iterable;
}
