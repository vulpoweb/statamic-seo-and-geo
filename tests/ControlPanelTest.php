<?php

it('registers the control panel screens', function () {
    expect(cp_route('vulpo-seo.redirects.index'))->toContain('vulpo-seo/redirects');
    expect(cp_route('vulpo-seo.not-found.index'))->toContain('vulpo-seo/404-log');
    expect(cp_route('vulpo-seo.ai-crawlers.index'))->toContain('vulpo-seo/ai-crawlers');
});

it('keeps the control panel screens behind authentication', function () {
    $this->get(cp_route('vulpo-seo.redirects.index'))->assertRedirect();
    $this->get(cp_route('vulpo-seo.not-found.index'))->assertRedirect();
});

it('ships every translatable string in lang/en.json', function () {
    $translations = json_decode(file_get_contents(__DIR__.'/../lang/en.json'), true);

    expect($translations)->toBeArray();

    $missing = [];

    $files = collect(['/../src', '/../resources/views'])
        ->flatMap(fn (string $directory) => iterator_to_array(new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(__DIR__.$directory, FilesystemIterator::SKIP_DOTS),
        )))
        ->filter(fn ($file) => str_ends_with($file->getFilename(), '.php'))
        ->values();

    expect($files)->not->toBeEmpty();

    foreach ($files as $file) {
        preg_match_all('/__\(\s*([\'"])(.+?)\1/', file_get_contents($file->getPathname()), $matches);

        foreach ($matches[2] as $string) {
            if (! array_key_exists($string, $translations)) {
                $missing[] = $file->getFilename().': '.$string;
            }
        }
    }

    expect($missing)->toBe([]);
});
