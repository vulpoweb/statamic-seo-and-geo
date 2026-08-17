<?php

namespace Vulpo\Seo\Fieldtypes;

use Statamic\Contracts\Data\Augmentable;
use Statamic\Contracts\Entries\Entry as EntryContract;
use Statamic\Facades\Site;
use Statamic\Fields\Fieldtype;
use Vulpo\Seo\Support\Assets;
use Vulpo\Seo\Support\Settings;
use Vulpo\Seo\Support\ValueReader;

/**
 * A read-only field that shows how the page will look in Google and when it is
 * shared. The Vue component updates from the sibling SEO fields as they are
 * typed; everything that cannot be resolved in the browser (the site defaults,
 * the URL, the resolved image) is passed in through preload().
 */
class SeoPreview extends Fieldtype
{
    protected static $handle = 'seo_preview';

    protected static $title = 'SEO preview';

    protected $localizable = false;

    protected $validatable = false;

    protected $defaultable = false;

    protected $icon = 'magnifying-glass';

    protected $categories = ['special'];

    /**
     * Nothing is stored: the preview is derived from the other fields.
     */
    public function process($data)
    {
        return null;
    }

    public function preProcess($data)
    {
        return null;
    }

    /**
     * @return array<string, mixed>
     */
    public function preload(): array
    {
        $parent = $this->field?->parent();
        $values = $parent instanceof Augmentable
            ? ValueReader::fromData($parent)
            : ValueReader::empty();

        return [
            'url' => $this->url($parent),
            'site_name' => Settings::string('site_name') ?: (string) Site::current()->name(),
            'separator' => Settings::string('title_separator', '|'),
            'append_site_name' => Settings::bool('append_site_name', true),
            'default_description' => Settings::string('default_description'),
            'image' => Assets::url($values->field('image')) ?: Assets::url(Settings::get('default_image')),
            'noindex' => $values->bool('noindex') || Settings::bool('noindex_site'),
            'handles' => [
                'title' => 'seo_title',
                'description' => 'seo_description',
                'noindex' => 'seo_noindex',
                'image' => 'seo_image',
            ],
            'fallback_title' => $parent && method_exists($parent, 'value') ? $parent->value('title') : null,
        ];
    }

    private function url(mixed $parent): string
    {
        $url = $parent instanceof EntryContract || (is_object($parent) && method_exists($parent, 'absoluteUrl'))
            ? $parent->absoluteUrl()
            : null;

        return $url ?: rtrim(Site::current()->absoluteUrl(), '/').'/…';
    }
}
