<?php

namespace Vulpo\Seo\Seo;

use Statamic\Contracts\Entries\Entry as EntryContract;
use Statamic\Facades\Entry;
use Statamic\Facades\Site;
use Statamic\Tags\Context;
use Vulpo\Seo\Support\Assets;
use Vulpo\Seo\Support\Settings;
use Vulpo\Seo\Support\ValueReader;

/**
 * Resolves and renders the `<head>` meta tags for the page being rendered:
 * title, description, canonical, robots, Open Graph and Twitter cards, plus
 * hreflang alternates on multisite installs.
 *
 * Per-page fields win, then the control panel defaults, then sensible guesses.
 */
class Meta
{
    public function __construct(
        private readonly ValueReader $values,
        private readonly ?EntryContract $entry = null,
        private readonly ?string $contextTitle = null,
    ) {}

    public static function forContext(Context $context): self
    {
        return new self(
            values: ValueReader::fromContext($context),
            entry: self::entryFromContext($context),
            contextTitle: is_scalar($title = $context->raw('title')) ? (string) $title : null,
        );
    }

    public function title(): string
    {
        $title = $this->values->string('title') ?: $this->contextTitle ?: $this->siteName();

        $suffix = $this->siteName();

        if (! Settings::bool('append_site_name', true) || ! $suffix || $title === $suffix) {
            return $title;
        }

        return $title.' '.Settings::string('title_separator', '|').' '.$suffix;
    }

    public function description(): ?string
    {
        return $this->values->string('description') ?: Settings::string('default_description');
    }

    public function canonical(): string
    {
        return $this->values->string('canonical') ?: request()->url();
    }

    public function robots(): string
    {
        $noindex = $this->values->bool('noindex') || Settings::bool('noindex_site');
        $nofollow = $this->values->bool('nofollow') || Settings::bool('noindex_site');

        $directives = [
            $noindex ? 'noindex' : 'index',
            $nofollow ? 'nofollow' : 'follow',
        ];

        if (! $noindex && Settings::bool('large_image_previews', true)) {
            $directives[] = 'max-image-preview:large';
        }

        return implode(', ', $directives);
    }

    public function imageUrl(): ?string
    {
        return Assets::url($this->values->field('image'))
            ?: Assets::url(Settings::get('default_image'));
    }

    public function render(): string
    {
        return implode("\n", $this->tags());
    }

    /**
     * @return array<int, string>
     */
    public function tags(): array
    {
        $tags = [
            '<title>'.e($this->title()).'</title>',
            $this->link('canonical', $this->canonical()),
            $this->meta('name', 'robots', $this->robots()),
        ];

        if ($description = $this->description()) {
            $tags[] = $this->meta('name', 'description', $description);
        }

        return array_values(array_filter(array_merge(
            $tags,
            $this->openGraphTags(),
            $this->twitterTags(),
            $this->alternateTags(),
            $this->verificationTags(),
        )));
    }

    /**
     * @return array<int, string>
     */
    private function openGraphTags(): array
    {
        if (! Settings::bool('open_graph', true)) {
            return [];
        }

        $tags = [
            $this->meta('property', 'og:type', $this->openGraphType()),
            $this->meta('property', 'og:url', $this->canonical()),
            $this->meta('property', 'og:title', $this->title()),
            $this->meta('property', 'og:locale', str_replace('-', '_', Site::current()->locale())),
        ];

        if ($siteName = $this->siteName()) {
            $tags[] = $this->meta('property', 'og:site_name', $siteName);
        }

        if ($description = $this->description()) {
            $tags[] = $this->meta('property', 'og:description', $description);
        }

        if ($image = $this->imageUrl()) {
            $tags[] = $this->meta('property', 'og:image', $image);

            if ($asset = Assets::find($this->values->field('image') ?: Settings::get('default_image'))) {
                if ($width = $asset->width()) {
                    $tags[] = $this->meta('property', 'og:image:width', (string) $width);
                }
                if ($height = $asset->height()) {
                    $tags[] = $this->meta('property', 'og:image:height', (string) $height);
                }
                if ($alt = $asset->get('alt')) {
                    $tags[] = $this->meta('property', 'og:image:alt', (string) $alt);
                }
            }
        }

        return $tags;
    }

    /**
     * @return array<int, string>
     */
    private function twitterTags(): array
    {
        if (! Settings::bool('twitter_cards', true)) {
            return [];
        }

        $image = $this->imageUrl();

        $tags = [
            $this->meta('name', 'twitter:card', $image ? 'summary_large_image' : 'summary'),
            $this->meta('name', 'twitter:title', $this->title()),
        ];

        if ($description = $this->description()) {
            $tags[] = $this->meta('name', 'twitter:description', $description);
        }

        if ($image) {
            $tags[] = $this->meta('name', 'twitter:image', $image);
        }

        if ($handle = Settings::string('twitter_handle')) {
            $tags[] = $this->meta('name', 'twitter:site', '@'.ltrim($handle, '@'));
        }

        return $tags;
    }

    /**
     * Ownership verification for search consoles and social platforms. Only
     * needed on the homepage, but harmless everywhere and simpler to reason
     * about, which is how every service documents it.
     *
     * @return array<int, string>
     */
    private function verificationTags(): array
    {
        $tags = [];

        $known = [
            'verify_google' => 'google-site-verification',
            'verify_bing' => 'msvalidate.01',
            'verify_pinterest' => 'p:domain_verify',
            'verify_facebook' => 'facebook-domain-verification',
        ];

        foreach ($known as $setting => $name) {
            if ($content = Settings::string($setting)) {
                $tags[] = $this->meta('name', $name, $this->verificationCode($content));
            }
        }

        foreach (Settings::rows('verify_custom') as $row) {
            $name = isset($row['name']) ? trim((string) $row['name']) : '';
            $content = isset($row['content']) ? trim((string) $row['content']) : '';

            if ($name !== '' && $content !== '') {
                $tags[] = $this->meta('name', $name, $this->verificationCode($content));
            }
        }

        return $tags;
    }

    /**
     * Editors paste the whole meta tag as often as they paste the code, so pull
     * the content value back out when they do.
     */
    private function verificationCode(string $value): string
    {
        if (preg_match('/content=["\']([^"\']+)["\']/i', $value, $matches)) {
            return trim($matches[1]);
        }

        return trim($value);
    }

    /**
     * hreflang alternates for the entry's other locales.
     *
     * @return array<int, string>
     */
    private function alternateTags(): array
    {
        if (! $this->entry || Site::all()->count() < 2 || ! Settings::bool('hreflang', true)) {
            return [];
        }

        $tags = [];

        foreach (Site::all() as $site) {
            $localized = $this->entry->in($site->handle());

            if (! $localized || ! $localized->published() || ! $url = $localized->absoluteUrl()) {
                continue;
            }

            $tags[] = '<link rel="alternate" hreflang="'.e($site->shortLocale()).'" href="'.e($url).'">';
        }

        return count($tags) > 1 ? $tags : [];
    }

    private function openGraphType(): string
    {
        return $this->values->string('schema_type') === 'article' ? 'article' : 'website';
    }

    private function siteName(): string
    {
        return Settings::string('site_name') ?: (string) Site::current()->name();
    }

    private function meta(string $attribute, string $name, string $content): string
    {
        return '<meta '.$attribute.'="'.e($name).'" content="'.e($content).'">';
    }

    private function link(string $rel, string $href): string
    {
        return '<link rel="'.e($rel).'" href="'.e($href).'">';
    }

    private static function entryFromContext(Context $context): ?EntryContract
    {
        $id = $context->raw('id');

        if (! is_string($id) && ! is_int($id)) {
            return null;
        }

        $entry = Entry::find($id);

        return $entry instanceof EntryContract ? $entry : null;
    }
}
