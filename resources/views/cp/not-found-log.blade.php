@extends('statamic::layout')

@section('title', $title)

@section('content')
    <header class="vs-header">
        <div>
            <h1 class="vs-title">{{ __('404 log') }}</h1>
            <p class="vs-subtitle">{{ __('URLs that visitors requested but that do not exist. Turn the ones that matter into redirects.') }}</p>
        </div>

        @if ($rows->isNotEmpty())
            <form method="POST" action="{{ cp_route('vulpo-seo.not-found.clear') }}">
                @csrf
                <button type="submit" class="vs-btn">{{ __('Clear log') }}</button>
            </form>
        @endif
    </header>

    @if (session('success'))
        <div class="vs-notice">{{ session('success') }}</div>
    @endif

    @if ($rows->isEmpty())
        <div class="vs-empty">{{ __('Nothing logged yet. Any 404 from now on shows up here.') }}</div>
    @else
        <div class="vs-panel">
            <table class="vs-table">
                <thead>
                    <tr>
                        <th>{{ __('URL') }}</th>
                        <th>{{ __('Hits') }}</th>
                        <th>{{ __('Last seen') }}</th>
                        <th>{{ __('Referrer') }}</th>
                        <th class="vs-end">{{ __('Redirect to') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($rows as $row)
                        <tr>
                            <td class="vs-path">{{ $row['path'] }}</td>
                            <td class="vs-num">{{ $row['hits'] }}</td>
                            <td class="vs-nowrap vs-muted">{{ $row['last_seen'] }}</td>
                            <td class="vs-truncate vs-muted">{{ $row['referer'] ?: '—' }}</td>
                            <td>
                                @if ($redirects->has($row['path']))
                                    <div class="vs-form vs-muted">
                                        {{ __('Redirects to') }}
                                        <span class="vs-path">{{ $redirects->get($row['path'])->to }}</span>
                                    </div>
                                @else
                                    <div class="vs-form">
                                        <form method="POST" action="{{ cp_route('vulpo-seo.redirects.store') }}" class="vs-form">
                                            @csrf
                                            <input type="hidden" name="from" value="{{ $row['path'] }}">
                                            <input type="text" name="to" required placeholder="/new-page" class="vs-input vs-input--path">
                                            <select name="status" class="vs-input">
                                                <option value="301">301</option>
                                                <option value="302">302</option>
                                                <option value="410">410</option>
                                            </select>
                                            <button type="submit" class="vs-btn vs-btn--primary">{{ __('Add') }}</button>
                                        </form>
                                        <form method="POST" action="{{ cp_route('vulpo-seo.not-found.destroy') }}">
                                            @csrf
                                            <input type="hidden" name="path" value="{{ $row['path'] }}">
                                            <button type="submit" class="vs-btn">{{ __('Dismiss') }}</button>
                                        </form>
                                    </div>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
@endsection
