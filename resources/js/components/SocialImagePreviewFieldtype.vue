<!--
    The entry's social image in the publish form, with a button to remake
    it. PHP side: Fieldtypes\SocialImagePreview. The button posts to the
    images.regenerate CP route and swaps in the URL it returns, which carries
    a ?v= time so the browser drops its cached copy.

    While the image is missing or out of date (just saved, or the template
    changed) the preview asks images.show, which starts making it, and
    swaps it in when it lands. Until then it shows the previous image, if
    there is one.
-->
<script>
import { FieldtypeMixin } from '@statamic/cms';
import { Button } from '@statamic/cms/ui';

export default {
    mixins: [FieldtypeMixin],

    components: { Button },

    data() {
        return {
            url: this.meta.url,
            previous: this.meta.previous,
            failed: false,
            state: null,
            seconds: 0,
            override: null,
            regenerating: false,
            polling: false,
            pollTimer: null,
        };
    },

    computed: {
        /**
         * What's happening, in words: waiting for the queue, or rendering
         * and for how long.
         */
        progress() {
            if (this.regenerating) {
                return this.__('Rendering…');
            }

            if (this.state === 'rendering') {
                return this.__('Rendering… :s s', { s: this.seconds });
            }

            if (this.state === 'queued') {
                return this.__('Waiting to start…');
            }

            return this.__('Generating…');
        },

        busy() {
            return this.polling || this.regenerating;
        },

        ratio() {
            return `${this.meta.width || 1200} / ${this.meta.height || 630}`;
        },
    },

    watch: {
        meta(meta) {
            this.url = meta.url;
            this.previous = meta.previous;
            this.failed = false;
            this.pollIfMissing();
        },
    },

    mounted() {
        this.pollIfMissing();
    },

    beforeUnmount() {
        this.stopPolling();
    },

    methods: {
        pollIfMissing() {
            this.stopPolling();

            if (this.url || !this.meta.status_url || !this.meta.enabled) {
                return;
            }

            let attempts = 0;
            let waited = 0;
            this.polling = true;

            const check = () => {
                this.$axios
                    .get(this.meta.status_url)
                    .then(({ data }) => {
                        this.url = data.url || this.url;
                        this.previous = data.previous ?? this.previous;
                        this.failed = data.failed;
                        this.state = data.state;
                        this.seconds = data.seconds ?? 0;
                        this.override = data.override;
                    })
                    .catch(() => {})
                    .finally(() => {
                        // Every second at first, then backing off to every 15
                        // seconds, for up to 10 minutes: a busy queue can
                        // take a while to reach it.
                        attempts++;
                        const delay = Math.min(15000, 1000 * Math.pow(1.15, Math.max(0, attempts - 10)));
                        waited += delay;

                        if (this.url || this.failed || waited > 600000 || !this.polling) {
                            this.stopPolling();
                        } else {
                            this.pollTimer = setTimeout(check, delay);
                        }
                    });
            };

            this.pollTimer = setTimeout(check, 300);
        },

        stopPolling() {
            clearTimeout(this.pollTimer);
            this.pollTimer = null;
            this.polling = false;
        },

        regenerate() {
            this.stopPolling();
            this.regenerating = true;

            this.$axios
                .post(this.meta.regenerate_url)
                .then(({ data }) => {
                    this.url = data.url;
                    this.failed = false;
                    this.$toast.success(data.message);
                })
                .catch((error) => {
                    this.$toast.error(error.response?.data?.message ?? this.__('Something went wrong'));
                })
                .finally(() => {
                    this.regenerating = false;
                });
        },
    },
};
</script>

<template>
    <div class="si-preview">
        <template v-if="meta.regenerate_url">
            <a v-if="url" :href="url" target="_blank" rel="noopener" class="si-preview__frame" :style="{ aspectRatio: ratio }">
                <img :src="url" alt="" class="si-preview__image" />
            </a>
            <div v-else-if="previous && !failed" class="si-preview__frame si-preview__updating" :style="{ aspectRatio: ratio }">
                <img :src="previous" alt="" class="si-preview__image" />
                <span class="si-preview__badge">{{ busy ? progress : __('Out of date') }}</span>
                <span v-if="busy" class="si-preview__bar" />
            </div>
            <div v-else class="si-preview__frame si-preview__empty" :style="{ aspectRatio: ratio }">
                <template v-if="!meta.enabled">{{ __('Social images are turned off') }}</template>
                <template v-else-if="failed">{{ __('It couldn’t be generated. Choose Regenerate to see why.') }}</template>
                <template v-else-if="busy">{{ progress }}</template>
                <template v-else>{{ __('Not generated yet') }}</template>
                <span v-if="busy && !failed" class="si-preview__bar" />
            </div>

            <div class="si-preview__footer">
                <p class="si-preview__note">
                    {{ __(':template template, from the saved entry.', { template: meta.template }) }}
                </p>
                <Button
                    size="sm"
                    icon="sync"
                    :loading="regenerating"
                    :disabled="regenerating || polling || !meta.enabled"
                    :text="url ? __('Regenerate') : __('Generate')"
                    @click="regenerate"
                />
            </div>
        </template>

        <p v-else class="si-preview__note">
            {{ meta.template === null && meta.status_url === null ? __('This entry has no social image template yet, or hasn’t been saved.') : '' }}
        </p>
    </div>
</template>

<style scoped>
.si-preview__frame {
    display: block;
    overflow: hidden;
    border-radius: 0.5rem;
    box-shadow: 0 0 0 1px rgb(0 0 0 / 0.08);
    background: repeating-linear-gradient(45deg, rgb(0 0 0 / 0.03) 0 10px, transparent 10px 20px);
}

.si-preview__image {
    display: block;
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.si-preview__updating,
.si-preview__empty {
    position: relative;
}

/* An indeterminate bar: renders take seconds, with nothing to count. */
.si-preview__bar {
    position: absolute;
    left: 0;
    right: 0;
    bottom: 0;
    height: 3px;
    overflow: hidden;
    background: rgb(59 130 246 / 0.2);
}

.si-preview__bar::after {
    content: '';
    position: absolute;
    top: 0;
    bottom: 0;
    width: 35%;
    background: #3b82f6;
    animation: si-preview-bar 1.2s ease-in-out infinite;
}

@keyframes si-preview-bar {
    from { left: -35%; }
    to { left: 100%; }
}

@media (prefers-reduced-motion: reduce) {
    .si-preview__bar::after {
        animation: none;
        left: 0;
        width: 100%;
        opacity: 0.6;
    }
}

.si-preview__updating .si-preview__image {
    opacity: 0.55;
}

.si-preview__badge {
    position: absolute;
    left: 0.5rem;
    bottom: 0.5rem;
    padding: 0.125rem 0.5rem;
    border-radius: 999px;
    background: rgb(0 0 0 / 0.7);
    color: #fff;
    font-size: 0.75rem;
}

.si-preview__empty {
    text-align: center;
    padding: 0 1rem;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.8125rem;
    color: #6b6f76;
}

.si-preview__footer {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
    margin-top: 0.625rem;
}

.si-preview__note {
    margin: 0;
    font-size: 0.75rem;
    color: #6b6f76;
}
</style>
