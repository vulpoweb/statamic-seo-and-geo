<?php

use Statamic\Events\EntryBlueprintFound;
use Statamic\Events\TermBlueprintFound;
use Statamic\Facades\Blueprint;
use Vulpo\Seo\Listeners\InjectFields;

function blueprintFor(string $namespace)
{
    return Blueprint::make('article')
        ->setNamespace($namespace)
        ->setContents(['tabs' => ['main' => ['fields' => [['handle' => 'title', 'field' => ['type' => 'text']]]]]]);
}

it('adds both tabs to an entry blueprint', function () {
    $blueprint = blueprintFor('collections.articles');

    app(InjectFields::class)->handleEntryBlueprint(new EntryBlueprintFound($blueprint));

    expect(array_keys($blueprint->contents()['tabs']))->toBe(['main', 'vulpo_seo', 'vulpo_seo_schema']);
});

it('skips collections that are excluded in the config', function () {
    config()->set('seo.fields.exclude_collections', ['articles']);

    $blueprint = blueprintFor('collections.articles');

    app(InjectFields::class)->handleEntryBlueprint(new EntryBlueprintFound($blueprint));

    expect(array_keys($blueprint->contents()['tabs']))->toBe(['main']);
});

it('skips injection entirely when switched off', function () {
    config()->set('seo.fields.entries', false);

    $blueprint = blueprintFor('collections.articles');

    app(InjectFields::class)->handleEntryBlueprint(new EntryBlueprintFound($blueprint));

    expect(array_keys($blueprint->contents()['tabs']))->toBe(['main']);
});

it('does not inject twice', function () {
    $blueprint = blueprintFor('collections.articles');

    app(InjectFields::class)->handleEntryBlueprint(new EntryBlueprintFound($blueprint));
    app(InjectFields::class)->handleEntryBlueprint(new EntryBlueprintFound($blueprint));

    expect(array_keys($blueprint->contents()['tabs']))->toBe(['main', 'vulpo_seo', 'vulpo_seo_schema']);
});

it('adds both tabs to a term blueprint', function () {
    $blueprint = blueprintFor('taxonomies.topics');

    app(InjectFields::class)->handleTermBlueprint(new TermBlueprintFound($blueprint));

    expect(array_keys($blueprint->contents()['tabs']))->toBe(['main', 'vulpo_seo', 'vulpo_seo_schema']);
});

it('skips taxonomies that are excluded in the config', function () {
    config()->set('seo.fields.exclude_taxonomies', ['topics']);

    $blueprint = blueprintFor('taxonomies.topics');

    app(InjectFields::class)->handleTermBlueprint(new TermBlueprintFound($blueprint));

    expect(array_keys($blueprint->contents()['tabs']))->toBe(['main']);
});

it('skips term injection entirely when switched off', function () {
    config()->set('seo.fields.terms', false);

    $blueprint = blueprintFor('taxonomies.topics');

    app(InjectFields::class)->handleTermBlueprint(new TermBlueprintFound($blueprint));

    expect(array_keys($blueprint->contents()['tabs']))->toBe(['main']);
});
