<?php echo '<?xml version="1.0" encoding="UTF-8"?>'."\n"; ?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:xhtml="http://www.w3.org/1999/xhtml">
@foreach ($urls as $url)
    <url>
        <loc>{{ $url['loc'] }}</loc>
@if ($url['lastmod'])
        <lastmod>{{ $url['lastmod'] }}</lastmod>
@endif
@if ($url['changefreq'])
        <changefreq>{{ $url['changefreq'] }}</changefreq>
@endif
@if ($url['priority'])
        <priority>{{ $url['priority'] }}</priority>
@endif
@foreach ($url['alternates'] ?? [] as $alternate)
        <xhtml:link rel="alternate" hreflang="{{ $alternate['hreflang'] }}" href="{{ $alternate['href'] }}"/>
@endforeach
    </url>
@endforeach
</urlset>
