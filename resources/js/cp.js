/**
 * Live Google and social previews for the SEO tab.
 *
 * Deliberately buildless. Statamic aliases Vue to the full bundler build and
 * exposes it as window.Vue, registers components through
 * window.Statamic.$components, and exposes the control panel's modules on
 * window.__STATAMIC__ — so a plain script can register a fieldtype with a
 * runtime-compiled template. No npm, no Vite, nothing to recompile when
 * Statamic releases a new minor.
 */
(function () {
    /**
     * Addon scripts are emitted before the control panel's own Vite modules, so
     * window.Statamic does not exist yet when this file runs. Wait for it rather
     * than bailing out, which would leave the field showing
     * "Component seo_preview-fieldtype does not exist".
     */
    function whenReady(callback) {
        if (window.Statamic && window.Vue && window.Statamic.$components) return callback();

        let attempts = 0;
        const interval = setInterval(function () {
            if (window.Statamic && window.Vue && window.Statamic.$components) {
                clearInterval(interval);
                callback();
            } else if (++attempts > 200) {
                clearInterval(interval);
                console.error('[vulpo/seo] Statamic did not become available, the SEO preview is unavailable.');
            }
        }, 25);
    }

    whenReady(register);

    function register() {
        const Statamic = window.Statamic;
        const Vue = window.Vue;
        const ui = (window.__STATAMIC__ || {}).ui || {};

        /**
         * Google truncates by pixel width, not character count, so measure the text
         * the way the browser would render it.
         */
        const measure = (function () {
            const canvas = document.createElement('canvas');
            const context = canvas.getContext('2d');

            return function (text, font) {
                context.font = font;

                return context.measureText(text).width;
            };
        })();

        function truncate(text, maxWidth, font) {
            text = (text || '').replace(/\s+/g, ' ').trim();

            if (!text || measure(text, font) <= maxWidth) {
                return { text: text, truncated: false };
            }

            let cut = text;

            while (cut.length && measure(cut + '…', font) > maxWidth) {
                cut = cut.slice(0, -1);
            }

            // Avoid cutting mid-word when there is a space to fall back to.
            const lastSpace = cut.lastIndexOf(' ');
            if (lastSpace > maxWidth / 12) {
                cut = cut.slice(0, lastSpace);
            }

            return { text: cut + '…', truncated: true };
        }

        const TITLE_FONT = '400 20px Arial, sans-serif';
        const DESCRIPTION_FONT = '400 14px Arial, sans-serif';
        const TITLE_WIDTH = 580;
        const DESCRIPTION_WIDTH = 920;

        Statamic.$components.register('seo_preview-fieldtype', {
            props: ['value', 'config', 'handle', 'meta', 'readOnly'],

            setup() {
                const container = ui.publishContextKey ? Vue.inject(ui.publishContextKey, null) : null;

                const values = Vue.computed(() => {
                    const bag = container && container.values ? container.values : null;

                    return (bag && (bag.value ?? bag)) || {};
                });

                return { values };
            },

            data() {
                return { tab: 'google' };
            },

            computed: {
                preloaded() {
                    return this.meta || {};
                },

                handles() {
                    return this.preloaded.handles || {};
                },

                rawTitle() {
                    return this.values[this.handles.title] || this.preloaded.fallback_title || '';
                },

                rawDescription() {
                    return this.values[this.handles.description] || this.preloaded.default_description || '';
                },

                noindex() {
                    return !!this.values[this.handles.noindex] || this.preloaded.noindex;
                },

                fullTitle() {
                    const siteName = this.preloaded.site_name;
                    const title = this.rawTitle || siteName || '';

                    if (!this.preloaded.append_site_name || !siteName || title === siteName) {
                        return title;
                    }

                    return title + ' ' + (this.preloaded.separator || '|') + ' ' + siteName;
                },

                title() {
                    return truncate(this.fullTitle, TITLE_WIDTH, TITLE_FONT);
                },

                description() {
                    return truncate(this.rawDescription, DESCRIPTION_WIDTH, DESCRIPTION_FONT);
                },

                breadcrumb() {
                    const url = this.preloaded.url || '';

                    return url.replace(/^https?:\/\//, '').replace(/\/$/, '').split('/').join(' › ');
                },

                host() {
                    return (this.preloaded.url || '').replace(/^https?:\/\//, '').split('/')[0];
                },

                image() {
                    return this.preloaded.image;
                },

                warnings() {
                    const warnings = [];

                    if (!this.rawTitle) warnings.push(__('No title set, falling back to the page title.'));
                    if (!this.rawDescription) warnings.push(__('No description set.'));
                    if (this.title.truncated) warnings.push(__('The title will be cut off in search results.'));
                    if (this.description.truncated) warnings.push(__('The description will be cut off in search results.'));
                    if (this.noindex) warnings.push(__('This page is hidden from search engines.'));

                    return warnings;
                },
            },

            template: `
                <div data-seo-preview>
                    <div style="display:flex; gap:.5rem; margin-bottom:.75rem;">
                        <ui-button
                            size="sm"
                            :variant="tab === 'google' ? 'pressed' : 'ghost'"
                            :text="__('Google')"
                            @click="tab = 'google'"
                        ></ui-button>
                        <ui-button
                            size="sm"
                            :variant="tab === 'social' ? 'pressed' : 'ghost'"
                            :text="__('Social')"
                            @click="tab = 'social'"
                        ></ui-button>
                    </div>

                    <ui-card>
                        <div v-if="tab === 'google'" style="font-family: Arial, sans-serif; max-width: 600px;">
                            <div style="font-size:12px; color:#4d5156; line-height:1.4;">{{ breadcrumb }}</div>
                            <div style="font-size:20px; line-height:1.3; color:#1a0dab; margin-top:2px;">{{ title.text }}</div>
                            <div style="font-size:14px; line-height:1.58; color:#4d5156; margin-top:4px;">{{ description.text }}</div>
                        </div>

                        <div v-else style="max-width: 520px;">
                            <div style="border:1px solid var(--theme-color-content-border, #e4e4e7); border-radius:.75rem; overflow:hidden;">
                                <div
                                    v-if="image"
                                    :style="{ backgroundImage: 'url(' + image + ')' }"
                                    style="aspect-ratio: 1200 / 630; background-size: cover; background-position: center;"
                                ></div>
                                <div
                                    v-else
                                    style="aspect-ratio: 1200 / 630; display:flex; align-items:center; justify-content:center; font-size:.8125rem;"
                                    class="text-gray-500"
                                >{{ __('No sharing image set') }}</div>
                                <div style="padding:.625rem .75rem;">
                                    <div style="font-size:.75rem; text-transform:uppercase;" class="text-gray-500">{{ host }}</div>
                                    <div style="font-weight:600; font-size:.9375rem; margin-top:.125rem;">{{ fullTitle }}</div>
                                    <div style="font-size:.8125rem; margin-top:.125rem;" class="text-gray-500">{{ description.text }}</div>
                                </div>
                            </div>
                        </div>
                    </ui-card>

                    <ul v-if="warnings.length" style="margin-top:.75rem; display:flex; flex-wrap:wrap; gap:.375rem; list-style:none; padding:0;">
                        <li v-for="warning in warnings" :key="warning">
                            <ui-badge color="amber" size="sm" :text="warning"></ui-badge>
                        </li>
                    </ul>
                </div>
            `,
        });
    }
})();
