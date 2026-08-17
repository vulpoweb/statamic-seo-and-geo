<ui-card-panel heading="{{ $title }}" subheading="{{ __('Last :days days', ['days' => $days]) }}">
    @if ($totals->isEmpty())
        <ui-description text="{{ __('No AI crawler visits logged.') }}"></ui-description>
    @else
        <div class="flex flex-wrap gap-2">
            @foreach ($totals as $bot => $hits)
                <ui-badge prepend="{{ $bot }}" text="{{ $hits }}"></ui-badge>
            @endforeach
        </div>
        <div class="mt-4">
            <ui-button href="{{ cp_route('vulpo-seo.ai-crawlers.index') }}" size="sm" text="{{ __('Open the crawler log') }}"></ui-button>
        </div>
    @endif
</ui-card-panel>
