<?php echo '<?xml version="1.0" encoding="UTF-8"?>'."\n"; ?>
<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
@for ($page = 1; $page <= $pages; $page++)
    <sitemap>
        <loc>{{ route('vulpo-seo.sitemap.page', ['page' => $page]) }}</loc>
    </sitemap>
@endfor
</sitemapindex>
