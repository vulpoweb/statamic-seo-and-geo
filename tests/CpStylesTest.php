<?php

/**
 * The control panel screens are built from Statamic's own UI components, which
 * the CP registers globally as `ui-<kebab-name>` and compiles from this Blade
 * output as an in-DOM template. That is what keeps them looking native, so these
 * tests guard against drifting back to hand-written styling.
 */
it('builds the screens from the control panel UI components', function (string $view) {
    $blade = file_get_contents(__DIR__.'/../resources/views/cp/'.$view.'.blade.php');

    expect($blade)
        ->toContain('<ui-header')
        ->toContain('<ui-card-panel')
        ->toContain('<ui-table')
        ->toContain('<ui-button');
})->with(['ai-crawlers', 'not-found-log']);

it('does not style the screens itself', function (string $view) {
    $blade = file_get_contents(__DIR__.'/../resources/views/cp/'.$view.'.blade.php');

    // An inline <style> does not survive the CP's Vue app, and a published
    // stylesheet would mean maintaining a copy of the CP's design.
    expect($blade)->not->toContain('<style');
    expect(glob(__DIR__.'/../resources/css/*.css'))->toBeEmpty();
})->with(['ai-crawlers', 'not-found-log']);

it('only uses utility classes that exist in the control panel bundle', function () {
    $classes = [];

    foreach (glob(__DIR__.'/../resources/views/cp/*.blade.php') as $view) {
        preg_match_all('/class="([^"{}]+)"/', file_get_contents($view), $matches);

        foreach ($matches[1] as $attribute) {
            $classes = array_merge($classes, preg_split('/\s+/', trim($attribute)));
        }
    }

    // Responsive and arbitrary variants are the ones that bit us: the bundle is
    // compiled from Statamic's source, so a variant it never uses is absent.
    expect(array_filter(array_unique($classes), fn ($class) => str_contains($class, ':')))->toBeEmpty();
})->skip(fn () => ! is_dir(__DIR__.'/../resources/views/cp'), 'No control panel views.');
