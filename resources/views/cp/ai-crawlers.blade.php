@extends('statamic::layout')

@section('title', $title)

@section('content')
    <header class="vs-header">
        <div>
            <h1 class="vs-title">{{ __('AI crawlers') }}</h1>
            <p class="vs-subtitle">{{ __('Visits from AI assistants and their crawlers. Being readable is what gets a site quoted and recommended.') }}</p>
        </div>

        @if ($rows->isNotEmpty())
            <form method="POST" action="{{ cp_route('vulpo-seo.ai-crawlers.clear') }}">
                @csrf
                <button type="submit" class="vs-btn">{{ __('Clear log') }}</button>
            </form>
        @endif
    </header>

    @if ($rows->isEmpty())
        <div class="vs-empty">{{ __('No AI crawler visits logged yet.') }}</div>
    @else
        <div class="vs-stats">
            @foreach ($totals as $bot => $hits)
                <div class="vs-stat">
                    <div class="vs-stat__label">{{ $bot }}</div>
                    <div class="vs-stat__value vs-num">{{ $hits }}</div>
                </div>
            @endforeach
        </div>

        <div class="vs-panel">
            <table class="vs-table">
                <thead>
                    <tr>
                        <th>{{ __('Date') }}</th>
                        <th>{{ __('Crawler') }}</th>
                        <th>{{ __('Hits') }}</th>
                        <th>{{ __('Last page') }}</th>
                        <th>{{ __('Last seen') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($rows as $row)
                        <tr>
                            <td class="vs-nowrap">{{ $row['date'] }}</td>
                            <td>{{ $row['bot'] }}</td>
                            <td class="vs-num">{{ $row['hits'] }}</td>
                            <td class="vs-path vs-truncate">{{ $row['last_path'] }}</td>
                            <td class="vs-nowrap vs-muted">{{ $row['last_seen'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
@endsection
