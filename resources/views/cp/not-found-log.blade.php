{{--
    Built from the control panel's own UI components. See the note in
    cp/ai-crawlers.blade.php for why.

    The "add redirect" form posts normally: ui-input and ui-button pass their
    attributes through to the real input and button elements, so name, value and
    required all reach the browser's form handling.
--}}
@extends('statamic::layout')

@section('title', $title)

@section('content')
    <div class="max-w-page mx-auto">
        <ui-header title="{{ __('404 log') }}" icon="globe-arrow">
            <form method="GET" action="{{ cp_route('vulpo-seo.not-found.index') }}" class="flex items-center gap-2">
                <ui-input
                    name="search"
                    value="{{ $search }}"
                    placeholder="{{ __('Search URLs') }}"
                    size="sm"
                    type="search"
                ></ui-input>
                <ui-button type="submit" size="sm" text="{{ __('Search') }}"></ui-button>
            </form>

            @if ($total && $canEdit)
                <form method="POST" action="{{ cp_route('vulpo-seo.not-found.clear') }}">
                    @csrf
                    <ui-button type="submit" text="{{ __('Clear log') }}"></ui-button>
                </form>
            @endif
        </ui-header>

        @if (session('success'))
            <ui-alert variant="success" text="{{ session('success') }}" class="mb-8"></ui-alert>
        @endif

        @if ($rows->isEmpty())
            <ui-card-panel heading="{{ __('404 log') }}" subheading="{{ __('URLs that visitors requested but that do not exist.') }}">
                <ui-description text="{{ $search !== '' ? __('Nothing matches that search.') : __('Nothing logged yet. Any 404 from now on shows up here.') }}"></ui-description>
            </ui-card-panel>
        @else
            <ui-card-panel
                heading="{{ __('Missing URLs') }}"
                subheading="{{ trans_choice(':count URL logged|:count URLs logged', $total, ['count' => $total]) }}"
            >
                <ui-table>
                    <ui-table-columns>
                        <ui-table-column>{{ __('URL') }}</ui-table-column>
                        @if ($multisite)
                            <ui-table-column>{{ __('Site') }}</ui-table-column>
                        @endif
                        <ui-table-column>{{ __('Hits') }}</ui-table-column>
                        <ui-table-column>{{ __('Last seen') }}</ui-table-column>
                        <ui-table-column>{{ __('Referrer') }}</ui-table-column>
                        <ui-table-column>{{ __('Redirect to') }}</ui-table-column>
                    </ui-table-columns>
                    <ui-table-rows>
                        @foreach ($rows as $row)
                            @php
                                $existing = $redirects->first(fn ($redirect) => strcasecmp($redirect->from, $row['path']) === 0
                                    && $redirect->appliesTo($row['site']));
                            @endphp
                            <ui-table-row>
                                <ui-table-cell class="font-mono text-xs">{{ $row['path'] }}</ui-table-cell>
                                @if ($multisite)
                                    <ui-table-cell class="text-gray-500 dark:text-gray-400">{{ $row['site'] ?: '—' }}</ui-table-cell>
                                @endif
                                <ui-table-cell class="tabular-nums">{{ $row['hits'] }}</ui-table-cell>
                                <ui-table-cell class="whitespace-nowrap text-gray-500 dark:text-gray-400 tabular-nums">{{ $row['last_seen'] }}</ui-table-cell>
                                <ui-table-cell class="text-gray-500 dark:text-gray-400 truncate">{{ $row['referer'] ?: '—' }}</ui-table-cell>
                                <ui-table-cell>
                                    @if ($existing)
                                        <ui-badge icon="arrow-right" text="{{ $existing->to }}"></ui-badge>
                                    @elseif (! $canEdit)
                                        <span class="text-gray-500 dark:text-gray-400">—</span>
                                    @else
                                        <div class="flex items-center gap-2">
                                            <form method="POST" action="{{ cp_route('vulpo-seo.redirects.store') }}" class="flex items-center gap-2">
                                                @csrf
                                                <input type="hidden" name="from" value="{{ $row['path'] }}">
                                                <input type="hidden" name="site" value="{{ $row['site'] }}">
                                                <ui-input name="to" required placeholder="/new-page" size="sm" class="w-48"></ui-input>
                                                <ui-button type="submit" variant="primary" size="sm" text="{{ __('Add') }}"></ui-button>
                                            </form>
                                            <form method="POST" action="{{ cp_route('vulpo-seo.not-found.destroy') }}">
                                                @csrf
                                                <input type="hidden" name="path" value="{{ $row['path'] }}">
                                                <input type="hidden" name="site" value="{{ $row['site'] }}">
                                                <ui-button type="submit" variant="ghost" size="sm" text="{{ __('Dismiss') }}"></ui-button>
                                            </form>
                                        </div>
                                    @endif
                                </ui-table-cell>
                            </ui-table-row>
                        @endforeach
                    </ui-table-rows>
                </ui-table>
            </ui-card-panel>

            @include('vulpo-seo::cp.partials.pagination', [
                'route' => cp_route('vulpo-seo.not-found.index'),
                'query' => ['search' => $search],
                'currentPage' => $currentPage,
                'totalPages' => $totalPages,
            ])
        @endif
    </div>
@endsection
