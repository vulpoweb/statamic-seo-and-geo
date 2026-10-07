<?php

namespace Vulpo\Seo\Contracts;

use Vulpo\Seo\Llms\LlmsLink;

/**
 * The unabridged half.
 *
 * llms.txt is meant to be read whole, so it stays a short map. A catalogue of
 * ten thousand products belongs in llms-full.txt, and a provider that has one
 * says so by implementing this as well.
 */
interface LlmsFullProvider extends LlmsProvider
{
    /**
     * @return iterable<int, LlmsLink>
     */
    public function llmsFullLinks(string $site): iterable;
}
