{{--
    Self-contained styles for the addon's control panel screens.

    Statamic's control panel CSS is compiled from its own source, so Tailwind
    utility classes used in an addon view are not in the bundle. Everything here
    is plain CSS built on the CP's theme variables, so it follows light and dark
    mode without depending on classes that may not exist.
--}}
<style>
    .vs-header { display: flex; align-items: flex-start; justify-content: space-between; gap: 1rem; margin-bottom: 1.5rem; }
    .vs-header p { margin: .25rem 0 0; font-size: .875rem; color: var(--theme-color-gray-500, #71717a); max-width: 60ch; }

    .vs-stats {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(9.5rem, 1fr));
        gap: .75rem;
        margin-bottom: 1.5rem;
    }
    .vs-stat {
        background: var(--theme-color-content-bg, #fff);
        border: 1px solid var(--theme-color-content-border, #e4e4e7);
        border-radius: .5rem;
        padding: .75rem 1rem;
    }
    .vs-stat__label { font-size: .8125rem; color: var(--theme-color-gray-500, #71717a); }
    .vs-stat__value { font-size: 1.5rem; font-weight: 600; line-height: 1.2; margin-top: .125rem; }

    .vs-panel {
        background: var(--theme-color-content-bg, #fff);
        border: 1px solid var(--theme-color-content-border, #e4e4e7);
        border-radius: .5rem;
        overflow-x: auto;
    }
    .vs-table { width: 100%; border-collapse: collapse; font-size: .875rem; }
    .vs-table th {
        text-align: start;
        font-weight: 500;
        font-size: .75rem;
        letter-spacing: .02em;
        text-transform: uppercase;
        color: var(--theme-color-gray-500, #71717a);
        padding: .625rem .75rem;
        white-space: nowrap;
    }
    .vs-table td { padding: .5rem .75rem; vertical-align: middle; }
    .vs-table tbody tr { border-top: 1px solid var(--theme-color-content-border, #e4e4e7); }
    .vs-table .vs-num { font-variant-numeric: tabular-nums; }
    .vs-table .vs-path { font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: .8125rem; }
    .vs-table .vs-muted { color: var(--theme-color-gray-500, #71717a); }
    .vs-table .vs-nowrap { white-space: nowrap; }
    .vs-table .vs-truncate { max-width: 18rem; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .vs-table .vs-end { text-align: end; }

    .vs-empty {
        border: 1px dashed var(--theme-color-content-border, #e4e4e7);
        border-radius: .5rem;
        padding: 2.5rem 1rem;
        text-align: center;
        color: var(--theme-color-gray-500, #71717a);
        font-size: .875rem;
    }

    .vs-btn {
        display: inline-flex;
        align-items: center;
        gap: .375rem;
        border: 1px solid var(--theme-color-content-border, #e4e4e7);
        background: var(--theme-color-content-bg, #fff);
        border-radius: .375rem;
        padding: .375rem .75rem;
        font-size: .8125rem;
        line-height: 1.25;
        cursor: pointer;
        white-space: nowrap;
    }
    .vs-btn:hover { border-color: var(--theme-color-gray-400, #a1a1aa); }
    .vs-btn--primary {
        background: var(--theme-color-ui-accent-bg, #4f46e5);
        border-color: transparent;
        color: #fff;
    }

    .vs-form { display: flex; align-items: center; justify-content: flex-end; gap: .375rem; }
    .vs-input {
        border: 1px solid var(--theme-color-content-border, #e4e4e7);
        background: var(--theme-color-content-bg, #fff);
        color: inherit;
        border-radius: .375rem;
        padding: .375rem .5rem;
        font-size: .8125rem;
    }
    .vs-input--path { width: 12rem; font-family: ui-monospace, SFMono-Regular, Menlo, monospace; }

    .vs-notice {
        border-radius: .375rem;
        padding: .625rem .75rem;
        margin-bottom: 1rem;
        font-size: .875rem;
        background: color-mix(in oklab, var(--theme-color-success, #22c55e) 18%, transparent);
    }
</style>
