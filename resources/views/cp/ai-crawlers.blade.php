@extends('statamic::layout')

@section('title', $title)

@section('content')
    <header class="mb-6 flex items-center justify-between">
        <div>
            <h1>{{ __('AI crawlers') }}</h1>
            <p class="text-sm text-gray-600 mt-1">
                {{ __('Visits from AI assistants and their crawlers. Being readable is what gets a site quoted and recommended.') }}
            </p>
        </div>

        @if ($rows->isNotEmpty())
            <form method="POST" action="{{ cp_route('vulpo-seo.ai-crawlers.clear') }}">
                @csrf
                <button type="submit" class="btn">{{ __('Clear log') }}</button>
            </form>
        @endif
    </header>

    @if ($rows->isEmpty())
        <div class="rounded-md border border-dashed p-8 text-center text-gray-600">
            {{ __('No AI crawler visits logged yet.') }}
        </div>
    @else
        <div class="mb-6 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            @foreach ($totals as $bot => $hits)
                <div class="card p-4">
                    <div class="text-sm text-gray-600">{{ $bot }}</div>
                    <div class="text-2xl font-semibold">{{ $hits }}</div>
                </div>
            @endforeach
        </div>

        <div class="card p-0 overflow-x-auto">
            <table class="data-table w-full text-sm">
                <thead>
                    <tr>
                        <th class="p-3 text-start">{{ __('Date') }}</th>
                        <th class="p-3 text-start">{{ __('Crawler') }}</th>
                        <th class="p-3 text-start">{{ __('Hits') }}</th>
                        <th class="p-3 text-start">{{ __('Last page') }}</th>
                        <th class="p-3 text-start">{{ __('Last seen') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($rows as $row)
                        <tr class="border-t">
                            <td class="p-3 whitespace-nowrap">{{ $row['date'] }}</td>
                            <td class="p-3">{{ $row['bot'] }}</td>
                            <td class="p-3">{{ $row['hits'] }}</td>
                            <td class="p-3 font-mono max-w-xs truncate">{{ $row['last_path'] }}</td>
                            <td class="p-3 whitespace-nowrap text-gray-600">{{ $row['last_seen'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
@endsection
