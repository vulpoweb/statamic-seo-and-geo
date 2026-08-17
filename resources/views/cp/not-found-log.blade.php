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
            @if ($rows->isNotEmpty())
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
                <ui-description text="{{ __('Nothing logged yet. Any 404 from now on shows up here.') }}"></ui-description>
            </ui-card-panel>
        @else
            <ui-card-panel heading="{{ __('Missing URLs') }}" subheading="{{ __('Turn the ones that matter into redirects. Redirects are managed under SEO → Redirects.') }}">
                <ui-table>
                    <ui-table-columns>
                        <ui-table-column>{{ __('URL') }}</ui-table-column>
                        <ui-table-column>{{ __('Hits') }}</ui-table-column>
                        <ui-table-column>{{ __('Last seen') }}</ui-table-column>
                        <ui-table-column>{{ __('Referrer') }}</ui-table-column>
                        <ui-table-column>{{ __('Redirect to') }}</ui-table-column>
                    </ui-table-columns>
                    <ui-table-rows>
                        @foreach ($rows as $row)
                            <ui-table-row>
                                <ui-table-cell class="font-mono text-xs">{{ $row['path'] }}</ui-table-cell>
                                <ui-table-cell class="tabular-nums">{{ $row['hits'] }}</ui-table-cell>
                                <ui-table-cell class="whitespace-nowrap text-gray-500 tabular-nums">{{ $row['last_seen'] }}</ui-table-cell>
                                <ui-table-cell class="text-gray-500 truncate">{{ $row['referer'] ?: '—' }}</ui-table-cell>
                                <ui-table-cell>
                                    @if ($redirects->has($row['path']))
                                        <ui-badge
                                            icon="arrow-right"
                                            text="{{ $redirects->get($row['path'])->to }}"
                                        ></ui-badge>
                                    @else
                                        <div class="flex items-center gap-2">
                                            <form method="POST" action="{{ cp_route('vulpo-seo.redirects.store') }}" class="flex items-center gap-2">
                                                @csrf
                                                <input type="hidden" name="from" value="{{ $row['path'] }}">
                                                <ui-input name="to" required placeholder="/new-page" size="sm" class="w-48"></ui-input>
                                                <ui-button type="submit" variant="primary" size="sm" text="{{ __('Add') }}"></ui-button>
                                            </form>
                                            <form method="POST" action="{{ cp_route('vulpo-seo.not-found.destroy') }}">
                                                @csrf
                                                <input type="hidden" name="path" value="{{ $row['path'] }}">
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
        @endif
    </div>
@endsection
