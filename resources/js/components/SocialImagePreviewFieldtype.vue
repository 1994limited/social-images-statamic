<!--
    The entry's social image in the publish form, with a button to remake
    it. PHP side: Fieldtypes\SocialImagePreview. The button posts to the
    images.regenerate CP route and swaps in the URL it returns, which carries
    a ?v= time so the browser drops its cached copy.

    A save that changes the image renders it after the save response has
    gone, so the form reloads before the new file exists. While the image is
    missing the preview asks images.show for a while and swaps it in when it
    lands.
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
            override: null,
            regenerating: false,
            polling: false,
            pollTimer: null,
        };
    },

    computed: {
        ratio() {
            return `${this.meta.width || 1200} / ${this.meta.height || 630}`;
        },
    },

    watch: {
        meta(meta) {
            this.url = meta.url;
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
            this.polling = true;

            const check = () => {
                this.$axios
                    .get(this.meta.status_url)
                    .then(({ data }) => {
                        this.url = data.url || this.url;
                        this.override = data.override;
                    })
                    .catch(() => {})
                    .finally(() => {
                        if (this.url || ++attempts >= 20 || !this.polling) {
                            this.stopPolling();
                        } else {
                            this.pollTimer = setTimeout(check, 1500);
                        }
                    });
            };

            this.pollTimer = setTimeout(check, 1000);
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
            <div v-else class="si-preview__frame si-preview__empty" :style="{ aspectRatio: ratio }">
                <template v-if="!meta.enabled">{{ __('Social images are turned off') }}</template>
                <template v-else-if="polling || regenerating">{{ __('Generating…') }}</template>
                <template v-else>{{ __('Not generated yet') }}</template>
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

.si-preview__empty {
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
