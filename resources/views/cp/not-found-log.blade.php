@extends('statamic::layout')

@section('title', $title)

@section('content')
    <header class="mb-6 flex items-center justify-between">
        <div>
            <h1>{{ __('404 log') }}</h1>
            <p class="text-sm text-gray-600 mt-1">
                {{ __('URLs that visitors requested but that do not exist. Turn the ones that matter into redirects.') }}
            </p>
        </div>

        @if ($rows->isNotEmpty())
            <form method="POST" action="{{ cp_route('vulpo-seo.not-found.clear') }}">
                @csrf
                <button type="submit" class="btn">{{ __('Clear log') }}</button>
            </form>
        @endif
    </header>

    @if (session('success'))
        <div class="mb-4 rounded-md bg-green-100 p-3 text-sm text-green-900">{{ session('success') }}</div>
    @endif

    @if ($rows->isEmpty())
        <div class="rounded-md border border-dashed p-8 text-center text-gray-600">
            {{ __('Nothing logged yet. Any 404 from now on shows up here.') }}
        </div>
    @else
        <div class="card p-0 overflow-x-auto">
            <table class="data-table w-full text-sm">
                <thead>
                    <tr>
                        <th class="p-3 text-start">{{ __('URL') }}</th>
                        <th class="p-3 text-start">{{ __('Hits') }}</th>
                        <th class="p-3 text-start">{{ __('Last seen') }}</th>
                        <th class="p-3 text-start">{{ __('Referrer') }}</th>
                        <th class="p-3 text-end">{{ __('Redirect to') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($rows as $row)
                        <tr class="border-t">
                            <td class="p-3 font-mono">{{ $row['path'] }}</td>
                            <td class="p-3">{{ $row['hits'] }}</td>
                            <td class="p-3 whitespace-nowrap">{{ $row['last_seen'] }}</td>
                            <td class="p-3 max-w-xs truncate text-gray-600">{{ $row['referer'] ?: '—' }}</td>
                            <td class="p-3">
                                @if ($redirects->has($row['path']))
                                    <span class="text-gray-600">
                                        {{ __('Redirects to') }} <span class="font-mono">{{ $redirects->get($row['path'])->to }}</span>
                                    </span>
                                @else
                                    <div class="flex items-center justify-end gap-2">
                                        <form method="POST" action="{{ cp_route('vulpo-seo.redirects.store') }}" class="flex items-center gap-2">
                                            @csrf
                                            <input type="hidden" name="from" value="{{ $row['path'] }}">
                                            <input
                                                type="text"
                                                name="to"
                                                required
                                                placeholder="/new-page"
                                                class="input-text w-48 font-mono text-sm"
                                            >
                                            <select name="status" class="input-text text-sm">
                                                <option value="301">301</option>
                                                <option value="302">302</option>
                                                <option value="410">410</option>
                                            </select>
                                            <button type="submit" class="btn-primary">{{ __('Add') }}</button>
                                        </form>
                                        <form method="POST" action="{{ cp_route('vulpo-seo.not-found.destroy') }}">
                                            @csrf
                                            <input type="hidden" name="path" value="{{ $row['path'] }}">
                                            <button type="submit" class="btn">{{ __('Dismiss') }}</button>
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
