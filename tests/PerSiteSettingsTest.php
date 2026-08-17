<?php

use Statamic\Facades\YAML;
use Vulpo\Seo\Support\Settings;

it('falls back to the global settings without an override', function () {
    Settings::swap([
        'site_name' => 'Vulpo',
        'site_overrides' => [
            ['site' => ['fr'], 'site_name' => 'Vulpo FR'],
        ],
    ]);

    // The default test site is "default", so the French row must not apply.
    expect(Settings::string('site_name'))->toBe('Vulpo');
});

it('prefers the override for the current site', function () {
    Settings::swap([
        'site_name' => 'Vulpo',
        'default_description' => 'Global description',
        'site_overrides' => [
            ['site' => ['default'], 'site_name' => 'Vulpo NL'],
        ],
    ]);

    expect(Settings::string('site_name'))->toBe('Vulpo NL');
    // Keys the row leaves out still come from the global settings.
    expect(Settings::string('default_description'))->toBe('Global description');
});

it('ignores an override row with an empty value', function () {
    Settings::swap([
        'site_name' => 'Vulpo',
        'site_overrides' => [
            ['site' => ['default'], 'site_name' => '', 'llms_summary' => 'Site specific summary'],
        ],
    ]);

    expect(Settings::string('site_name'))->toBe('Vulpo');
    expect(Settings::string('llms_summary'))->toBe('Site specific summary');
});

it('accepts a site handle stored as a plain string', function () {
    Settings::swap([
        'site_name' => 'Vulpo',
        'site_overrides' => [
            ['site' => 'default', 'site_name' => 'Vulpo NL'],
        ],
    ]);

    expect(Settings::string('site_name'))->toBe('Vulpo NL');
});

it('has a sites tab in the settings blueprint', function () {
    $blueprint = YAML::file(__DIR__.'/../resources/blueprints/settings.yaml')->parse();

    expect($blueprint['tabs'])->toHaveKey('sites');
    expect($blueprint['tabs']['sites']['sections'][0]['fields'][0]['handle'])->toBe('site_overrides');
});
