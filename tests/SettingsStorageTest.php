<?php

use Statamic\Facades\Addon;
use Vulpo\Seo\Support\Settings;

afterEach(function () {
    if (file_exists($path = resource_path('addons/seo-and-geo.yaml'))) {
        unlink($path);
    }
});

it('keeps the addon slug that Statamic derives from the package name', function () {
    // A custom extra.statamic.slug breaks core: settings are written to
    // resources/addons/{slug}.yaml but read from resources/addons/{package}.yaml.
    expect(Addon::get(Settings::PACKAGE)->slug())->toBe('seo-and-geo');
});

it('reads back settings saved through the control panel', function () {
    Addon::get(Settings::PACKAGE)->settings()->set([
        'site_name' => 'Vulpo',
        'business_type' => 'ProfessionalService',
    ])->save();

    Settings::flush();

    expect(resource_path('addons/seo-and-geo.yaml'))->toBeFile();
    expect(Settings::string('site_name'))->toBe('Vulpo');
    expect(Settings::string('business_type'))->toBe('ProfessionalService');
});
