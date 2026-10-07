<?php

namespace Vulpo\Seo\Llms;

/**
 * One line in llms.txt: a link, and a sentence saying what is behind it.
 *
 * `group` is the "## heading" it files under. Null means the default heading
 * from the settings, which is what a provider with nothing in particular to say
 * about its own section should use.
 */
final readonly class LlmsLink
{
    public function __construct(
        public string $url,
        public string $title,
        public ?string $description = null,
        public ?string $group = null,
    ) {}
}
