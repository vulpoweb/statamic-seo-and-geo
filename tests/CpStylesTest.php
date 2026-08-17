<?php

use Statamic\Statamic;

it('registers a control panel stylesheet', function () {
    // The CP bundle has no CSS for utility classes used in addon views, and an
    // inline <style> in the view does not survive the CP's Vue app, so the
    // screens depend on this published stylesheet being linked in the CP head.
    $styles = Statamic::availableStyles(request());

    expect($styles)->toHaveKey('seo');
    expect($styles['seo'][0])->toContain('cp.css');
});

it('ships the stylesheet file it registers', function () {
    expect(__DIR__.'/../resources/css/cp.css')->toBeFile();
});

it('does not inline styles into the control panel views', function () {
    foreach (glob(__DIR__.'/../resources/views/cp/*.blade.php') as $view) {
        expect(file_get_contents($view))->not->toContain('<style');
    }
});
