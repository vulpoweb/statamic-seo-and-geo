<?php

namespace Vulpo\Seo\Seo;

use Statamic\Contracts\Entries\Entry as EntryContract;
use Statamic\Facades\Entry;
use Statamic\Facades\Site;
use Statamic\Tags\Context;
use Vulpo\Seo\Support\Assets;
use Vulpo\Seo\Support\Nominatim;
use Vulpo\Seo\Support\Settings;
use Vulpo\Seo\Support\ValueReader;

/**
 * Builds the JSON-LD structured data that search engines and AI assistants read:
 *
 *  - Organization / LocalBusiness and WebSite, site-wide, from the CP settings
 *  - BreadcrumbList for any page below the homepage
 *  - A per-page node (FAQPage, Article, Service or Person) from the entry's
 *    "Structured data" tab
 */
class Schema
{
    public function __construct(
        private readonly ValueReader $values,
        private readonly ?EntryContract $entry = null,
    ) {}

    public static function forContext(Context $context): self
    {
        $id = $context->raw('id');
        $entry = is_string($id) || is_int($id) ? Entry::find($id) : null;

        return new self(
            values: ValueReader::fromContext($context),
            entry: $entry instanceof EntryContract ? $entry : null,
        );
    }

    public function render(): string
    {
        return implode("\n", array_map(
            fn (array $node) => '<script type="application/ld+json">'.json_encode(
                $node,
                JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT
            ).'</script>',
            $this->nodes(),
        ));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function nodes(): array
    {
        return array_values(array_filter([
            Settings::bool('schema_organization', true) ? $this->organization() : null,
            Settings::bool('schema_website', true) ? $this->website() : null,
            Settings::bool('schema_breadcrumbs', true) ? $this->breadcrumbs() : null,
            $this->page(),
            $this->custom(),
        ]));
    }

    /**
     * @return array<string, mixed>|null
     */
    public function organization(): ?array
    {
        if (! $name = $this->organizationName()) {
            return null;
        }

        $type = Settings::string('business_type', 'Organization');
        $isLocalBusiness = $type !== 'Organization';

        $organization = array_filter([
            '@context' => 'https://schema.org',
            '@type' => $type,
            '@id' => $this->base().'/#organization',
            'name' => $name,
            'url' => $this->base().'/',
            'logo' => Assets::url(Settings::get('logo')),
            'description' => Settings::string('business_description'),
            'knowsAbout' => Settings::list('knows_about') ?: null,
            'areaServed' => Settings::string('area_served'),
            'foundingDate' => Settings::string('founding_year'),
            'email' => Settings::string('email'),
            'telephone' => Settings::string('phone'),
            'sameAs' => $this->socialProfiles() ?: null,
            'address' => $this->address(),
        ]);

        if (! $isLocalBusiness) {
            return $organization;
        }

        if ($coordinates = $this->coordinates()) {
            $organization['geo'] = [
                '@type' => 'GeoCoordinates',
                'latitude' => $coordinates['lat'],
                'longitude' => $coordinates['lng'],
            ];
        }

        if ($hours = $this->openingHours()) {
            $organization['openingHoursSpecification'] = $hours;
        }

        if ($priceRange = Settings::string('price_range')) {
            $organization['priceRange'] = $priceRange;
        }

        return $organization;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function website(): ?array
    {
        if (! $name = $this->organizationName()) {
            return null;
        }

        $languages = Site::all()->map->shortLocale()->filter()->unique()->values()->all();

        return array_filter([
            '@context' => 'https://schema.org',
            '@type' => 'WebSite',
            '@id' => $this->base().'/#website',
            'name' => $name,
            'url' => $this->base().'/',
            'inLanguage' => count($languages) > 1 ? $languages : $this->language(),
            'publisher' => ['@id' => $this->base().'/#organization'],
        ]);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function breadcrumbs(): ?array
    {
        if (! $this->entry) {
            return null;
        }

        $trail = [];
        $node = $this->entry;
        $guard = 0;

        while ($node && $guard++ < 10) {
            $trail[] = $node;
            $node = method_exists($node, 'parent') ? $node->parent() : null;
        }

        $trail = array_reverse($trail);
        $rootUrl = Site::current()->url();

        if ($trail === [] || rtrim((string) $trail[0]->url(), '/') !== rtrim((string) $rootUrl, '/')) {
            array_unshift($trail, null); // Placeholder for the homepage.
        }

        // Nothing worth emitting when the trail is just the homepage.
        if (count($trail) < 2) {
            return null;
        }

        $items = [];

        foreach ($trail as $position => $node) {
            $items[] = [
                '@type' => 'ListItem',
                'position' => $position + 1,
                'name' => $node ? ($node->value('title') ?: __('Home')) : __('Home'),
                'item' => $this->absolute($node ? $node->url() : $rootUrl),
            ];
        }

        return [
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => $items,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function page(): ?array
    {
        return match ($this->values->string('schema_type')) {
            'faq' => $this->faq(),
            'article' => $this->article(),
            'service' => $this->service(),
            'person' => $this->person(),
            'product' => $this->product(),
            'event' => $this->event(),
            default => null,
        };
    }

    /**
     * @return array<string, mixed>|null
     */
    private function faq(): ?array
    {
        $questions = [];

        foreach ($this->values->rows('faqs') as $row) {
            $question = $row['question'] ?? null;
            $answer = $row['answer'] ?? null;

            if (! $question || ! $answer) {
                continue;
            }

            $questions[] = [
                '@type' => 'Question',
                'name' => (string) $question,
                'acceptedAnswer' => [
                    '@type' => 'Answer',
                    'text' => trim(strip_tags((string) $answer)),
                ],
            ];
        }

        if ($questions === []) {
            return null;
        }

        return array_filter([
            '@context' => 'https://schema.org',
            '@type' => 'FAQPage',
            'inLanguage' => $this->language(),
            'mainEntity' => $questions,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function article(): array
    {
        $author = $this->values->string('article_author');

        return array_filter([
            '@context' => 'https://schema.org',
            '@type' => 'Article',
            'headline' => $this->values->string('article_headline') ?: $this->pageTitle(),
            'description' => $this->values->string('article_description'),
            'author' => $author ? ['@type' => 'Person', 'name' => $author] : null,
            'datePublished' => $this->values->string('article_published'),
            'image' => Assets::url($this->values->field('article_image') ?: $this->values->field('image')),
            'mainEntityOfPage' => $this->pageUrl(),
            'inLanguage' => $this->language(),
            'publisher' => ['@id' => $this->base().'/#organization'],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function service(): array
    {
        return array_filter([
            '@context' => 'https://schema.org',
            '@type' => 'Service',
            'name' => $this->values->string('service_name') ?: $this->pageTitle(),
            'description' => $this->values->string('service_description'),
            'areaServed' => $this->values->string('service_area') ?: Settings::string('area_served'),
            'provider' => ['@id' => $this->base().'/#organization'],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function person(): array
    {
        return array_filter([
            '@context' => 'https://schema.org',
            '@type' => 'Person',
            'name' => $this->values->string('person_name') ?: $this->pageTitle(),
            'jobTitle' => $this->values->string('person_job'),
            'url' => $this->pageUrl(),
            'worksFor' => ['@id' => $this->base().'/#organization'],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function product(): array
    {
        $price = $this->values->string('product_price');

        return array_filter([
            '@context' => 'https://schema.org',
            '@type' => 'Product',
            'name' => $this->values->string('product_name') ?: $this->pageTitle(),
            'description' => $this->values->string('product_description'),
            'sku' => $this->values->string('product_sku'),
            'image' => Assets::url($this->values->field('product_image') ?: $this->values->field('image')),
            'brand' => ($brand = $this->values->string('product_brand'))
                ? ['@type' => 'Brand', 'name' => $brand]
                : null,
            'offers' => $price === null ? null : array_filter([
                '@type' => 'Offer',
                'price' => $price,
                'priceCurrency' => $this->values->string('product_currency') ?: 'EUR',
                'availability' => 'https://schema.org/'.($this->values->string('product_availability') ?: 'InStock'),
                'url' => $this->pageUrl(),
            ]),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function event(): array
    {
        return array_filter([
            '@context' => 'https://schema.org',
            '@type' => 'Event',
            'name' => $this->values->string('event_name') ?: $this->pageTitle(),
            'description' => $this->values->string('event_description'),
            'startDate' => $this->values->string('event_start'),
            'endDate' => $this->values->string('event_end'),
            'url' => $this->values->string('event_url') ?: $this->pageUrl(),
            'image' => Assets::url($this->values->field('image')),
            'location' => ($location = $this->values->string('event_location'))
                ? ['@type' => 'Place', 'name' => $location]
                : null,
            'organizer' => ['@id' => $this->base().'/#organization'],
        ]);
    }

    /**
     * A raw JSON-LD escape hatch, for the types this addon does not model.
     * Invalid JSON is skipped rather than breaking the page.
     *
     * @return array<string, mixed>|null
     */
    public function custom(): ?array
    {
        if (! $json = $this->values->string('custom_schema')) {
            return null;
        }

        try {
            $decoded = json_decode($json, true, 32, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }

        if (! is_array($decoded) || $decoded === []) {
            return null;
        }

        // Allow a bare node without the boilerplate.
        return array_key_exists('@context', $decoded)
            ? $decoded
            : array_merge(['@context' => 'https://schema.org'], $decoded);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function address(): ?array
    {
        if (! $street = Settings::string('address_street')) {
            return null;
        }

        return array_filter([
            '@type' => 'PostalAddress',
            'streetAddress' => $street,
            'addressLocality' => Settings::string('address_locality'),
            'postalCode' => Settings::string('postal_code'),
            'addressCountry' => Settings::string('country_code'),
        ]);
    }

    /**
     * Manual coordinates win; otherwise geocode the address for free.
     *
     * @return array{lat: float, lng: float}|null
     */
    private function coordinates(): ?array
    {
        $lat = Settings::get('geo_lat');
        $lng = Settings::get('geo_lng');

        if (is_numeric($lat) && is_numeric($lng)) {
            return ['lat' => (float) $lat, 'lng' => (float) $lng];
        }

        $query = implode(', ', array_filter([
            Settings::string('address_street'),
            Settings::string('postal_code'),
            Settings::string('address_locality'),
            Settings::string('country_code'),
        ]));

        return $query === '' ? null : Nominatim::coordinates($query);
    }

    /**
     * @return array<int, array<string, string>>|null
     */
    private function openingHours(): ?array
    {
        $specification = [];

        foreach (Settings::rows('opening_hours') as $row) {
            if (empty($row['day']) || empty($row['opens']) || empty($row['closes'])) {
                continue;
            }

            $specification[] = [
                '@type' => 'OpeningHoursSpecification',
                'dayOfWeek' => (string) $row['day'],
                'opens' => (string) $row['opens'],
                'closes' => (string) $row['closes'],
            ];
        }

        return $specification ?: null;
    }

    /**
     * @return array<int, string>
     */
    private function socialProfiles(): array
    {
        return array_values(array_filter(
            array_map(fn ($url) => is_string($url) ? trim($url) : null, Settings::list('social_profiles')),
            fn (?string $url) => $url && str_starts_with($url, 'http'),
        ));
    }

    private function organizationName(): ?string
    {
        return Settings::string('business_name')
            ?: Settings::string('site_name')
            ?: (string) Site::current()->name();
    }

    private function pageTitle(): ?string
    {
        $title = $this->values->string('title')
            ?: $this->values->handle('title')
            ?: $this->entry?->value('title');

        return is_scalar($title) && trim((string) $title) !== '' ? trim((string) $title) : null;
    }

    private function pageUrl(): string
    {
        return $this->entry ? $this->absolute($this->entry->url()) : request()->url();
    }

    private function absolute(?string $url): string
    {
        if (! $url) {
            return $this->base().'/';
        }

        return str_starts_with($url, 'http') ? $url : $this->base().'/'.ltrim($url, '/');
    }

    private function base(): string
    {
        return rtrim(url('/'), '/');
    }

    private function language(): ?string
    {
        return Site::current()->shortLocale() ?: null;
    }
}
