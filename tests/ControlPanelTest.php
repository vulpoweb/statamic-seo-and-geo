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
