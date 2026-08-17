<ui-card-panel heading="{{ $title }}">
    @if ($rows->isEmpty())
        <ui-description text="{{ __('No 404s logged.') }}"></ui-description>
    @else
        <ui-table>
            <ui-table-columns>
                <ui-table-column>{{ __('URL') }}</ui-table-column>
                <ui-table-column>{{ __('Hits') }}</ui-table-column>
                <ui-table-column>{{ __('Last seen') }}</ui-table-column>
            </ui-table-columns>
            <ui-table-rows>
                @foreach ($rows as $row)
                    <ui-table-row>
                        <ui-table-cell class="font-mono text-xs truncate">{{ $row['path'] }}</ui-table-cell>
                        <ui-table-cell class="tabular-nums">{{ $row['hits'] }}</ui-table-cell>
                        <ui-table-cell class="whitespace-nowrap text-gray-500 tabular-nums">{{ $row['last_seen'] }}</ui-table-cell>
                    </ui-table-row>
                @endforeach
            </ui-table-rows>
        </ui-table>
        <div class="mt-4">
            <ui-button href="{{ cp_route('vulpo-seo.not-found.index') }}" size="sm" text="{{ __('Open the 404 log') }}"></ui-button>
        </div>
    @endif
</ui-card-panel>
