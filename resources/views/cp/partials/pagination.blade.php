{{--
    Plain links, so the page keeps working without any JavaScript of ours.

    The variables are `currentPage` and `totalPages` rather than `page`, because
    statamic::layout renders `$page` as Inertia's page object.
--}}
@if ($totalPages > 1)
    <div class="flex items-center justify-between gap-2">
        <ui-description text="{{ __('Page :page of :pages', ['page' => $currentPage, 'pages' => $totalPages]) }}"></ui-description>

        <div class="flex items-center gap-2">
            @if ($currentPage > 1)
                <ui-button
                    size="sm"
                    href="{{ $route }}?{{ http_build_query(array_merge($query ?? [], ['page' => $currentPage - 1])) }}"
                    text="{{ __('Previous') }}"
                ></ui-button>
            @endif

            @if ($currentPage < $totalPages)
                <ui-button
                    size="sm"
                    href="{{ $route }}?{{ http_build_query(array_merge($query ?? [], ['page' => $currentPage + 1])) }}"
                    text="{{ __('Next') }}"
                ></ui-button>
            @endif
        </div>
    </div>
@endif
