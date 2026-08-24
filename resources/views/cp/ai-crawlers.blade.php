{{--
    Built from the control panel's own UI components. Every export of Statamic's
    @ui package is registered globally as a `ui-<kebab-name>` Vue component, and
    the CP compiles this Blade output as an in-DOM template, so these screens use
    the same components as core screens instead of hand-written CSS.
--}}
@extends('statamic::layout')

@section('title', $title)

@section('content')
    <div class="max-w-page mx-auto">
        <ui-header title="{{ __('AI crawlers') }}" icon="ai-search-spark">
            @if ($rows->isNotEmpty() && $canEdit)
                <form method="POST" action="{{ cp_route('vulpo-seo.ai-crawlers.clear') }}">
                    @csrf
                    <ui-button type="submit" text="{{ __('Clear log') }}"></ui-button>
                </form>
            @endif
        </ui-header>

        @if ($rows->isEmpty())
            <ui-card-panel heading="{{ __('AI crawlers') }}" subheading="{{ __('Visits from AI assistants and their crawlers. Being readable is what gets a site quoted and recommended.') }}">
                <ui-description text="{{ __('No AI crawler visits logged yet.') }}"></ui-description>
            </ui-card-panel>
        @else
            <div class="flex flex-wrap gap-2 mb-8">
                @foreach ($totals as $bot => $hits)
                    <ui-badge size="lg" prepend="{{ $bot }}" text="{{ $hits }}"></ui-badge>
                @endforeach
            </div>

            <ui-card-panel heading="{{ __('Visits') }}" subheading="{{ __('Grouped per crawler per day. Kept for :days days.', ['days' => config('seo.ai_crawlers.retention_days', 30)]) }}">
                <ui-table>
                    <ui-table-columns>
                        <ui-table-column>{{ __('Date') }}</ui-table-column>
                        <ui-table-column>{{ __('Crawler') }}</ui-table-column>
                        <ui-table-column>{{ __('Hits') }}</ui-table-column>
                        <ui-table-column>{{ __('Last page') }}</ui-table-column>
                        <ui-table-column>{{ __('Last seen') }}</ui-table-column>
                    </ui-table-columns>
                    <ui-table-rows>
                        @foreach ($rows as $row)
                            <ui-table-row>
                                <ui-table-cell class="whitespace-nowrap tabular-nums">{{ $row['date'] }}</ui-table-cell>
                                <ui-table-cell>{{ $row['bot'] }}</ui-table-cell>
                                <ui-table-cell class="tabular-nums">{{ $row['hits'] }}</ui-table-cell>
                                <ui-table-cell class="font-mono text-xs truncate">{{ $row['last_path'] }}</ui-table-cell>
                                <ui-table-cell class="whitespace-nowrap text-gray-500 tabular-nums">{{ $row['last_seen'] }}</ui-table-cell>
                            </ui-table-row>
                        @endforeach
                    </ui-table-rows>
                </ui-table>
            </ui-card-panel>
        @endif
    </div>
@endsection
