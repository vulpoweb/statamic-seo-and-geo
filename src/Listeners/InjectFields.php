<?php

namespace Vulpo\Seo\Listeners;

use Statamic\Events\EntryBlueprintFound;
use Statamic\Events\TermBlueprintFound;
use Statamic\Facades\Blink;
use Statamic\Fields\Blueprint;
use Statamic\Fields\BlueprintRepository;
use Statamic\Support\Str;

/**
 * Adds the "SEO" and "Structured data" tabs to entry and term blueprints at
 * runtime, so a site gets the fields without anyone editing blueprints.
 *
 * Publishing the blueprints (`php artisan vendor:publish --tag=vulpo-seo-blueprints`)
 * lets a project customise the fields; the published copy wins.
 */
class InjectFields
{
    private const TABS = ['vulpo_seo', 'vulpo_seo_schema'];

    public function handleEntryBlueprint(EntryBlueprintFound $event): void
    {
        if (! config('seo.fields.entries', true)) {
            return;
        }

        if ($this->isExcluded($event->blueprint, 'collections.', 'exclude_collections')) {
            return;
        }

        $this->inject($event->blueprint);
    }

    public function handleTermBlueprint(TermBlueprintFound $event): void
    {
        if (! config('seo.fields.terms', true)) {
            return;
        }

        if ($this->isExcluded($event->blueprint, 'taxonomies.', 'exclude_taxonomies')) {
            return;
        }

        $this->inject($event->blueprint);
    }

    private function inject(Blueprint $blueprint): void
    {
        $contents = $blueprint->contents();

        // Already injected, or the project placed the fields itself.
        foreach (self::TABS as $tab) {
            if (array_key_exists($tab, $contents['tabs'] ?? [])) {
                return;
            }
        }

        if (! $fields = $this->fieldsBlueprint()) {
            return;
        }

        $contents['tabs'] = array_merge($contents['tabs'] ?? [], $fields->contents()['tabs']);

        Blink::forget("blueprint-contents-{$blueprint->namespace()}-{$blueprint->handle()}");

        $blueprint->setContents($contents);
    }

    private function fieldsBlueprint(): ?Blueprint
    {
        $published = resource_path('blueprints/vendor/vulpo-seo');

        $directory = file_exists("{$published}/entry-fields.yaml")
            ? $published
            : __DIR__.'/../../resources/blueprints';

        return (new BlueprintRepository)->setDirectory($directory)->find('entry-fields');
    }

    private function isExcluded(Blueprint $blueprint, string $prefix, string $configKey): bool
    {
        $namespace = (string) $blueprint->namespace();

        if (! Str::startsWith($namespace, $prefix)) {
            return false;
        }

        $handle = Str::after($namespace, $prefix);

        return in_array($handle, (array) config("seo.fields.{$configKey}", []), true);
    }
}
