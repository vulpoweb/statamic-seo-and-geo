<?php

use Statamic\Contracts\Addons\SettingsRepository;
use Vulpo\Seo\Storage\EloquentRows;
use Vulpo\Seo\Storage\StorageManager;
use Vulpo\Seo\Storage\YamlRows;
use Vulpo\Seo\Support\Settings;

function manager(): StorageManager
{
    $manager = app(StorageManager::class);
    $manager->flush();

    return $manager;
}

it('stays on flat files when the site does', function () {
    config()->set('seo-and-geo.storage.driver', 'auto');

    foreach (['entries', 'taxonomies', 'globals', 'addon_settings'] as $repository) {
        config()->set("statamic.eloquent-driver.{$repository}.driver", 'file');
    }

    expect(manager()->driver())->toBe('file');
    expect(manager()->repository(StorageManager::REDIRECTS))->toBeInstanceOf(YamlRows::class);
});

it('follows the eloquent driver when the site keeps content in the database', function () {
    config()->set('seo-and-geo.storage.driver', 'auto');
    config()->set('statamic.eloquent-driver.entries.driver', 'eloquent');

    expect(manager()->driver())->toBe('eloquent');
    expect(manager()->repository(StorageManager::NOT_FOUND))->toBeInstanceOf(EloquentRows::class);
});

it('follows the eloquent driver when only addon settings are in the database', function () {
    config()->set('seo-and-geo.storage.driver', 'auto');

    foreach (['entries', 'taxonomies', 'globals'] as $repository) {
        config()->set("statamic.eloquent-driver.{$repository}.driver", 'file');
    }

    config()->set('statamic.eloquent-driver.addon_settings.driver', 'eloquent');

    expect(manager()->driver())->toBe('eloquent');
});

it('lets the project override the detection', function () {
    config()->set('statamic.eloquent-driver.entries.driver', 'eloquent');
    config()->set('seo-and-geo.storage.driver', 'file');

    expect(manager()->driver())->toBe('file');
    expect(manager()->repository(StorageManager::URIS))->toBeInstanceOf(YamlRows::class);
});

it('has a repository for every set', function () {
    config()->set('seo-and-geo.storage.driver', 'file');

    foreach ([StorageManager::REDIRECTS, StorageManager::NOT_FOUND, StorageManager::AI_CRAWLERS, StorageManager::URIS] as $set) {
        expect(manager()->repository($set))->toBeInstanceOf(YamlRows::class);
    }
});

it('rejects an unknown set', function () {
    manager()->repository('nonsense');
})->throws(InvalidArgumentException::class);

it('reads settings through Statamic, so they follow the site driver too', function () {
    // Statamic's own addon settings repository handles file vs database, and
    // eloquent-driver ships an eloquent implementation of it. The addon must not
    // reach around that.
    $settings = file_get_contents(__DIR__.'/../src/Support/Settings.php');

    expect($settings)->toContain('Addon::get(self::PACKAGE)?->settings()');
    expect($settings)->not->toContain('YamlFile');
});

it('reads blueprint defaults through Statamic on an eloquent settings driver', function () {
    config()->set('statamic.eloquent-driver.addon_settings.driver', 'eloquent');

    Settings::flush();

    // Nothing saved yet, so the blueprint defaults come through rather than a
    // failure, exactly as on the file driver.
    expect(Settings::string('title_separator'))->toBe('|');
    expect(Settings::bool('open_graph'))->toBeTrue();
});

it('keeps the front end up when the settings backend throws', function () {
    // On an eloquent-driver site the addon_settings table may not be migrated
    // yet. Missing SEO defaults must not be the reason a page 500s.
    app()->bind(SettingsRepository::class, function () {
        return new class implements SettingsRepository
        {
            public function find(string $addon): ?Statamic\Contracts\Addons\Settings
            {
                throw new RuntimeException('no such table: addon_settings');
            }

            public function make($addon, array $settings = []): Statamic\Contracts\Addons\Settings
            {
                throw new RuntimeException('no such table: addon_settings');
            }

            public function save(Statamic\Contracts\Addons\Settings $settings): bool
            {
                return false;
            }

            public function delete(Statamic\Contracts\Addons\Settings $settings): bool
            {
                return false;
            }
        };
    });

    Settings::flush();

    expect(Settings::all())->toBe([]);
    expect(Settings::string('site_name'))->toBeNull();
    expect(Settings::bool('open_graph', true))->toBeTrue();
});
