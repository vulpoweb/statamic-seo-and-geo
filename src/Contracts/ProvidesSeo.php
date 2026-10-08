<?php

namespace Vulpo\Seo\Contracts;

interface ProvidesSeo
{
    /**
     * @return array<string, mixed> keyed by the logical names in SeoOverlay::KEYS
     */
    public function toSeoArray(): array;
}
