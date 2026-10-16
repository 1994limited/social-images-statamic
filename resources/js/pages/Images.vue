<!--
    Tools → Social Images → Images: every entry with a template, and its
    images. Can make the missing ones or remake them all.

    Runs are driven from here, one request per image to the images.regenerate
    route (the entry form's button), so each image shows as it lands and a
    long run never meets a request timeout. When the screenshot service says
    it's busy (429), the run waits and tries again; after several refusals in
    a row it stops, since a daily allowance has probably run out.
-->
<script>
import { Head } from '@statamic/cms/inertia';
import { Badge, Button, Header, Input, Select } from '@statamic/cms/ui';
import icon from '../../brand/nav-icon.svg?raw';

const WAIT = 15;
const ATTEMPTS = 4;

export default {
    components: { Badge, Button, Head, Header, Input, Select },

    props: {
        entries: { type: Array, required: true },
        enabled: { type: Boolean, required: true },
        urls: { type: Object, required: true },
    },

    data() {
        return {
            images: this.entries.flatMap((entry) => entry.layouts.map((layout) => ({
                ...layout,
                key: `${entry.id}:${layout.layout}`,
                entry,
                status: layout.url ? 'current' : layout.previous ? 'outdated' : 'missing',
            }))),
            queue: [],
            done: 0,
            stopping: false,
            waiting: 0,
            query: '',
            collection: null,
        };
    },

    computed: {
        icon: () => icon,

        running() {
            return this.queue.length > 0;
        },

        /**
         * Images to make: missing ones, and out-of-date ones (whose previous
         * image is still in use until then).
         */
        missing() {
            return this.images.filter((image) => !image.url && image.entry.can_edit);
        },

        outdated() {
            return this.missing.filter((image) => image.previous);
        },

        missingLabel() {
            const out = this.outdated.length;
            const none = this.missing.length - out;

            if (out && !none) {
                return this.__('Update :count out of date', { count: out });
            }

            if (out) {
                return this.__('Generate :count missing or out of date', { count: this.missing.length });
            }

            return this.__('Generate :count missing', { count: none });
        },

        editable() {
            return this.images.filter((image) => image.entry.can_edit);
        },

        failed() {
            return this.images.filter((image) => image.status === 'failed');
        },

        collections() {
            return [
                { label: this.__('All collections'), value: null },
                ...[...new Set(this.entries.map((entry) => entry.collection))].map((title) => ({ label: title, value: title })),
            ];
        },

        rows() {
            const query = this.query.trim().toLowerCase();

            return this.entries
                .filter((entry) => !this.collection || entry.collection === this.collection)
                .filter((entry) => !query || entry.title.toLowerCase().includes(query))
                .map((entry) => ({ ...entry, images: this.images.filter((image) => image.entry.id === entry.id) }));
        },
    },

    methods: {
        generateMissing() {
            this.run(this.missing);
        },

        regenerateAll() {
            if (confirm(this.__('Remake all :count images? Each takes a few seconds.', { count: this.editable.length }))) {
                this.run(this.editable);
            }
        },

        regenerate(image) {
            this.run([image]);
        },

        async run(images) {
            this.queue = [...images];
            this.done = 0;
            this.stopping = false;
            let stoppedByLimit = false;

            for (const image of this.queue) {
                if (this.stopping) {
                    break;
                }

                const result = await this.render(image);
                this.done++;

                if (result === 'limit') {
                    stoppedByLimit = true;
                    break;
                }
            }

            const total = this.queue.length;
            const stopped = this.stopping;
            this.queue = [];

            if (stoppedByLimit) {
                this.$toast.error(this.__('The screenshot service keeps refusing: its limit has probably been reached. Try again later; finished images are kept.'));
            } else if (this.failed.length) {
                this.$toast.error(this.__(':count images failed. See the log for details.', { count: this.failed.length }));
            } else if (stopped) {
                this.$toast.success(this.__('Stopped after :done of :total.', { done: this.done, total }));
            } else {
                this.$toast.success(total === 1 ? this.__('Image generated') : this.__(':count images generated', { count: total }));
            }
        },

        async render(image, attempt = 1) {
            image.status = 'rendering';

            try {
                const { data } = await this.$axios.post(image.regenerate_url);
                image.url = data.url;
                image.status = 'current';

                return 'ok';
            } catch (error) {
                if (error.response?.data?.rate_limited && attempt < ATTEMPTS && !this.stopping) {
                    image.status = 'waiting';
                    await this.wait(WAIT * attempt);

                    return this.render(image, attempt + 1);
                }

                image.status = 'failed';

                return error.response?.data?.rate_limited ? 'limit' : 'failed';
            }
        },

        wait(seconds) {
            this.waiting = seconds;

            return new Promise((resolve) => {
                const tick = setInterval(() => {
                    this.waiting--;

                    if (this.waiting <= 0 || this.stopping) {
                        clearInterval(tick);
                        this.waiting = 0;
                        resolve();
                    }
                }, 1000);
            });
        },

        stop() {
            this.stopping = true;
        },

        badge(image) {
            return {
                current: { text: this.__('Current'), color: 'green' },
                missing: { text: this.__('Missing'), color: 'amber' },
                outdated: { text: this.__('Out of date'), color: 'amber' },
                rendering: { text: this.__('Rendering…'), color: 'blue' },
                waiting: { text: this.__('Waiting :s s', { s: this.waiting }), color: 'blue' },
                failed: { text: this.__('Failed'), color: 'red' },
            }[image.status];
        },
    },
};
</script>

<template>
    <Head :title="[__('Images'), __('Social Images')]" />

    <Header :title="__('Social Images')" :icon="icon">
        <template v-if="running">
            <span class="text-sm text-gray-600 tabular-nums dark:text-gray-400">
                {{ done }} / {{ queue.length }}<template v-if="waiting"> · {{ __('waiting :s s for the screenshot service', { s: waiting }) }}</template>
            </span>
            <Button :text="__('Stop')" :disabled="stopping" @click="stop" />
        </template>
        <template v-else>
            <Button :href="urls.templates" :text="__('Templates')" icon="layout-grid" />
            <Button
                :text="missingLabel"
                :disabled="!enabled || !missing.length"
                @click="generateMissing"
            />
            <Button variant="primary" icon="sync" :text="__('Regenerate all')" :disabled="!enabled || !editable.length" @click="regenerateAll" />
        </template>
    </Header>

    <p v-if="!enabled" class="mb-4 text-sm text-amber-700 dark:text-amber-400">
        {{ __('Social images are turned off (SOCIAL_IMAGES_ENABLED=false).') }}
    </p>

    <div v-if="entries.length" class="mb-6 flex flex-wrap gap-3">
        <Input v-model="query" icon="magnifying-glass" :placeholder="__('Search by title')" clearable :aria-label="__('Search')" class="max-w-xs" />
        <Select v-model="collection" :options="collections" option-label="label" option-value="value" :aria-label="__('Collection')" class="max-w-xs" />
    </div>

    <div v-if="!entries.length" class="rounded-lg border border-dashed border-gray-300 p-10 text-center text-sm text-gray-600 dark:border-gray-700 dark:text-gray-400">
        <p class="mb-3">{{ __('No entries have a template yet. Choose which template each collection uses in the settings.') }}</p>
        <Button :href="urls.settings" :text="__('Settings')" icon="cog" />
    </div>

    <div class="si-grid">
        <div v-for="row in rows" :key="row.id" class="si-card">
            <div class="si-card__images" :class="{ 'si-card__images--many': row.images.length > 1 }">
                <div v-for="image in row.images" :key="image.key" class="si-card__image">
                    <a v-if="image.url" :href="image.url" target="_blank" rel="noopener" class="si-frame">
                        <img :src="image.url" alt="" loading="lazy" />
                    </a>
                    <a v-else-if="image.previous" :href="image.previous" target="_blank" rel="noopener" class="si-frame si-frame--outdated" :title="__('Still in use until the new one is made')">
                        <img :src="image.previous" alt="" loading="lazy" />
                    </a>
                    <div v-else class="si-frame si-frame--empty">{{ __('Not generated yet') }}</div>
                    <div class="mt-1 flex items-center justify-between gap-2">
                        <span class="text-xs text-gray-500">{{ image.layout }}</span>
                        <div class="flex items-center gap-1">
                            <Badge size="sm" v-bind="badge(image)" />
                            <Button
                                v-if="row.can_edit && !running"
                                size="xs"
                                variant="ghost"
                                icon="sync"
                                :aria-label="__('Regenerate')"
                                :disabled="!enabled"
                                @click="regenerate(image)"
                            />
                        </div>
                    </div>
                </div>
            </div>

            <div class="mt-2 min-w-0">
                <a :href="row.edit_url" class="block truncate text-sm font-medium hover:underline">{{ row.title }}</a>
                <span class="text-xs text-gray-500">
                    {{ row.collection }} · {{ row.template }}<template v-if="row.override"> · {{ __('uses its own image') }}</template>
                </span>
            </div>
        </div>
    </div>
</template>

<style scoped>
.si-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(17rem, 1fr));
    gap: 1.5rem;
}

.si-card__images--many {
    display: grid;
    grid-template-columns: 2fr 1fr;
    gap: 0.5rem;
    align-items: start;
}

.si-frame {
    display: block;
    overflow: hidden;
    border-radius: 0.5rem;
    box-shadow: 0 0 0 1px rgb(0 0 0 / 0.08);
    background: repeating-linear-gradient(45deg, rgb(0 0 0 / 0.03) 0 10px, transparent 10px 20px);
}

.si-frame img {
    display: block;
    width: 100%;
    height: auto;
}

.si-frame--outdated img {
    opacity: 0.6;
}

.si-frame--empty {
    display: flex;
    aspect-ratio: 1200 / 630;
    align-items: center;
    justify-content: center;
    font-size: 0.75rem;
    color: #6b6f76;
}
</style>
