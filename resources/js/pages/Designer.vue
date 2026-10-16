<!--
    Tools → Social Images → a template: the designer.

    Layers on the left, the canvas in the middle (drawn by the same renderer
    as the screenshot), the selected layer's settings on the right. The
    canvas shows sample text, or a real entry picked at the top. "Render"
    takes a real screenshot through the configured service.

    Keys: arrows nudge (shift: 10px), Delete removes, ⌘D duplicates, ⌘Z and
    ⇧⌘Z undo and redo, ⌘S saves, Escape deselects.
-->
<script>
import { Head } from '@statamic/cms/inertia';
import { Button, Header, Modal } from '@statamic/cms/ui';
import icon from '../../brand/nav-icon.svg?raw';
import Canvas from '../components/designer/Canvas.vue';
import Icon from '../components/designer/Icon.vue';
import LayerList from '../components/designer/LayerList.vue';
import Properties from '../components/designer/Properties.vue';
import { scaleLayers } from '../designer/geometry.js';
import { bindingKey, fetchKey, newImage, newShape, newText, uid } from '../designer/layers.js';

const HISTORY = 100;

export default {
    components: { Button, Canvas, Head, Header, Icon, LayerList, Modal, Properties },

    props: {
        template: { type: Object, required: true },
        fonts: { type: Array, required: true },
        presets: { type: Object, required: true },
        collections: { type: Array, required: true },
        entries: { type: Array, required: true },
        samples: { type: Object, required: true },
        urls: { type: Object, required: true },
    },

    data() {
        const { handle, ...data } = this.template;

        return {
            handle,
            design: JSON.parse(JSON.stringify(data)),
            active: 'og',
            selected: null,
            history: [],
            future: [],
            snapshot: JSON.stringify(data),
            savedSnapshot: JSON.stringify(data),
            commitTimer: null,
            resolved: {},
            fonts_: [...this.fonts],
            entry: null,
            entryOptions: [...this.entries],
            entrySearch: '',
            fetchTimer: null,
            saving: false,
            rendering: false,
            rendered: null,
            renderError: null,
            fontModal: false,
            fontForm: { mode: 'google', family: '', weights: [400, 700], italic: false, file: null, weight: 400, style: 'normal' },
            fontBusy: false,
            fontError: null,
            addingLayout: false,
            custom: { width: 1200, height: 1200 },
        };
    },

    computed: {
        icon: () => icon,

        layout() {
            return this.design.layouts[this.active];
        },

        layer() {
            return this.layout.layers.find((layer) => layer.id === this.selected) ?? null;
        },

        dirty() {
            return JSON.stringify(this.design) !== this.savedSnapshot;
        },

        layoutTabs() {
            return Object.entries(this.design.layouts).map(([handle, layout]) => ({
                handle,
                label: this.presets[handle]?.title ?? handle,
                size: `${layout.width}×${layout.height}`,
            }));
        },

        missingPresets() {
            return Object.entries(this.presets).filter(([handle]) => !this.design.layouts[handle]);
        },

        /**
         * What the canvas draws with: the server's data for each layer whose
         * binding hasn't changed since, and the raw text otherwise.
         */
        canvasData() {
            const cache = this.resolved[this.active] ?? { layers: {}, keys: {}, fonts: [], categories: {} };
            const layers = {};

            for (const layer of this.layout.layers) {
                if (cache.keys[layer.id] === bindingKey(layer) && cache.layers[layer.id]) {
                    layers[layer.id] = cache.layers[layer.id];
                } else if (layer.type === 'text') {
                    layers[layer.id] = { text: layer.text };
                } else if (layer.type === 'image' && cache.layers[layer.id]?.src) {
                    layers[layer.id] = cache.layers[layer.id];
                }
            }

            return { layers, fonts: cache.fonts, categories: cache.categories };
        },

        fetchSignature() {
            return fetchKey(this.layout) + '|' + this.entry + '|' + this.active;
        },
    },

    watch: {
        fetchSignature: {
            immediate: true,
            handler() {
                clearTimeout(this.fetchTimer);
                this.fetchTimer = setTimeout(this.fetchPreview, 250);
            },
        },
    },

    mounted() {
        window.addEventListener('keydown', this.keydown);
        window.addEventListener('beforeunload', this.beforeUnload);
    },

    beforeUnmount() {
        window.removeEventListener('keydown', this.keydown);
        window.removeEventListener('beforeunload', this.beforeUnload);
        clearTimeout(this.fetchTimer);
        clearTimeout(this.commitTimer);
    },

    methods: {
        /* Preview data ----------------------------------------------------- */

        fetchPreview() {
            const handle = this.active;
            const layout = JSON.parse(JSON.stringify(this.layout));
            const keys = Object.fromEntries(layout.layers.map((layer) => [layer.id, bindingKey(layer)]));

            this.$axios
                .post(this.urls.preview, { layout, entry: this.entry })
                .then(({ data }) => {
                    this.resolved = {
                        ...this.resolved,
                        [handle]: { layers: data.data.layers, fonts: data.data.fonts, categories: data.data.categories, keys },
                    };
                })
                .catch(() => {});
        },

        searchEntries() {
            this.$axios
                .get(this.urls.entries, { params: { search: this.entrySearch } })
                .then(({ data }) => (this.entryOptions = data.entries))
                .catch(() => {});
        },

        /* Changes and history ---------------------------------------------- */

        change(mutate, { immediate = false } = {}) {
            mutate();

            clearTimeout(this.commitTimer);

            if (immediate) {
                this.commit();
            } else {
                this.commitTimer = setTimeout(this.commit, 400);
            }
        },

        commit() {
            clearTimeout(this.commitTimer);
            const current = JSON.stringify(this.design);

            if (current === this.snapshot) {
                return;
            }

            this.history.push(this.snapshot);
            this.history = this.history.slice(-HISTORY);
            this.future = [];
            this.snapshot = current;
        },

        undo() {
            this.commit();

            if (!this.history.length) {
                return;
            }

            this.future.push(this.snapshot);
            this.snapshot = this.history.pop();
            this.design = JSON.parse(this.snapshot);
            this.keepSelection();
        },

        redo() {
            if (!this.future.length) {
                return;
            }

            this.history.push(this.snapshot);
            this.snapshot = this.future.pop();
            this.design = JSON.parse(this.snapshot);
            this.keepSelection();
        },

        keepSelection() {
            if (!this.design.layouts[this.active]) {
                this.active = 'og';
            }

            if (!this.layout.layers.some((layer) => layer.id === this.selected)) {
                this.selected = null;
            }
        },

        updateLayer(patch, id = this.selected) {
            const layer = this.layout.layers.find((l) => l.id === id);

            if (layer) {
                this.change(() => Object.assign(layer, patch));
            }
        },

        moveLayer(id, patch) {
            const layer = this.layout.layers.find((l) => l.id === id);

            if (layer) {
                Object.assign(layer, patch);
            }
        },

        updateLayout(patch) {
            this.change(() => Object.assign(this.layout, patch));
        },

        add(type) {
            const layer = { text: newText, image: newImage, shape: newShape, ellipse: (l) => newShape(l, 'ellipse') }[type](this.layout);
            this.change(() => this.layout.layers.push(layer), { immediate: true });
            this.selected = layer.id;
        },

        remove(id = this.selected) {
            this.change(() => (this.layout.layers = this.layout.layers.filter((layer) => layer.id !== id)), { immediate: true });
            this.selected = null;
        },

        duplicate(id = this.selected) {
            const index = this.layout.layers.findIndex((layer) => layer.id === id);

            if (index < 0) {
                return;
            }

            const copy = { ...JSON.parse(JSON.stringify(this.layout.layers[index])), id: uid(), x: this.layout.layers[index].x + 20, y: this.layout.layers[index].y + 20 };
            copy.name = `${copy.name} ${this.__('copy')}`;
            this.change(() => this.layout.layers.splice(index + 1, 0, copy), { immediate: true });
            this.selected = copy.id;
        },

        toggle(id, key) {
            this.updateLayer({ [key]: !this.layout.layers.find((l) => l.id === id)[key] }, id);
            this.commit();
        },

        /**
         * Move `id` to where `target` is in the list (which shows the top
         * layer first).
         */
        reorder(id, target) {
            this.change(() => {
                const layers = [...this.layout.layers];
                const from = layers.findIndex((l) => l.id === id);
                const [layer] = layers.splice(from, 1);
                const to = layers.findIndex((l) => l.id === target);
                layers.splice(from > to ? to + 1 : to, 0, layer);
                this.layout.layers = layers;
            }, { immediate: true });
        },

        /**
         * Move a layer one place in front (1) or behind (-1).
         */
        step(id, by) {
            const from = this.layout.layers.findIndex((l) => l.id === id);
            const to = from + by;

            if (from < 0 || to < 0 || to >= this.layout.layers.length) {
                return;
            }

            this.change(() => {
                const layers = [...this.layout.layers];
                [layers[from], layers[to]] = [layers[to], layers[from]];
                this.layout.layers = layers;
            }, { immediate: true });

            // Keep the moved layer's row focused for the next press.
            this.$nextTick(() => setTimeout(() => document.querySelector('.si-layers__select[aria-current="true"]')?.focus()));
        },

        nudge(dx, dy) {
            if (this.layer && !this.layer.locked) {
                this.updateLayer({ x: this.layer.x + dx, y: this.layer.y + dy });
            }
        },

        /* Layouts ------------------------------------------------------------ */

        addLayout(handle, size) {
            const base = this.design.layouts.og;
            const name = handle ?? `custom_${size.width}x${size.height}`;

            this.change(() => {
                this.design.layouts[name] = {
                    width: size.width,
                    height: size.height,
                    background: JSON.parse(JSON.stringify(base.background)),
                    layers: scaleLayers(base.layers, base, size),
                };
            }, { immediate: true });

            this.active = name;
            this.selected = null;
            this.addingLayout = false;
        },

        removeLayout(handle) {
            if (handle === 'og' || !confirm(this.__('Remove the :layout layout?', { layout: handle }))) {
                return;
            }

            this.change(() => delete this.design.layouts[handle], { immediate: true });
            this.active = 'og';
            this.selected = null;
        },

        switchLayout(handle) {
            this.active = handle;
            this.selected = null;
        },

        /* Fonts -------------------------------------------------------------- */

        addFont() {
            const form = new FormData();
            let url = this.urls.google;

            if (this.fontForm.mode === 'google') {
                form.append('family', this.fontForm.family);
                this.fontForm.weights.forEach((w) => form.append('weights[]', w));
                form.append('italic', this.fontForm.italic ? '1' : '0');
            } else {
                url = this.urls.font_upload;
                form.append('family', this.fontForm.family);
                form.append('weight', this.fontForm.weight);
                form.append('style', this.fontForm.style);

                if (this.fontForm.file) {
                    form.append('file', this.fontForm.file);
                }
            }

            this.fontBusy = true;
            this.fontError = null;

            this.$axios
                .post(url, form)
                .then(({ data }) => {
                    this.fonts_ = data.fonts;
                    const family = this.fontForm.family.trim();

                    if (this.layer?.type === 'text') {
                        this.updateLayer({ font: { ...this.layer.font, family } });
                    }

                    this.fontModal = false;
                    this.$toast.success(this.__(':family added', { family }));
                })
                .catch((error) => (this.fontError = error.response?.data?.message ?? this.__('Something went wrong')))
                .finally(() => (this.fontBusy = false));
        },

        /* Saving and rendering ---------------------------------------------- */

        save() {
            this.commit();
            this.saving = true;

            return this.$axios
                .patch(this.urls.update, { template: this.design })
                .then(({ data }) => {
                    const { handle, ...saved } = data.template;
                    this.savedSnapshot = JSON.stringify(saved);
                    // Keep ids and order as the server normalised them.
                    this.design = saved;
                    this.snapshot = this.savedSnapshot;
                    this.keepSelection();
                    this.$toast.success(this.__('Saved'));
                })
                .catch((error) => this.$toast.error(error.response?.data?.message ?? this.__('Something went wrong')))
                .finally(() => (this.saving = false));
        },

        render() {
            this.rendering = true;
            this.rendered = null;
            this.renderError = null;
            const started = performance.now();

            this.$axios
                .post(this.urls.render, { layout: this.layout, entry: this.entry })
                .then(({ data }) => (this.rendered = { image: data.image, seconds: ((performance.now() - started) / 1000).toFixed(1) }))
                .catch((error) => (this.renderError = error.response?.data?.message ?? this.__('Something went wrong')))
                .finally(() => (this.rendering = false));
        },

        async exportTemplate() {
            if (this.dirty) {
                await this.save();
            }

            window.location.href = this.urls.export;
        },

        /* Keys --------------------------------------------------------------- */

        keydown(event) {
            if (event.defaultPrevented) {
                return;
            }

            const typing = ['INPUT', 'TEXTAREA', 'SELECT'].includes(event.target.tagName) || event.target.isContentEditable;
            const mod = event.metaKey || event.ctrlKey;

            if (mod && event.key.toLowerCase() === 's') {
                event.preventDefault();
                this.save();

                return;
            }

            if (typing) {
                return;
            }

            if (mod && event.key.toLowerCase() === 'z') {
                event.preventDefault();
                event.shiftKey ? this.redo() : this.undo();
            } else if (mod && event.key.toLowerCase() === 'd' && this.layer) {
                event.preventDefault();
                this.duplicate();
            } else if ((event.key === 'Delete' || event.key === 'Backspace') && this.layer) {
                event.preventDefault();
                this.remove();
            } else if (event.key === 'Escape') {
                this.selected = null;
            } else if (event.key.startsWith('Arrow') && this.layer) {
                event.preventDefault();
                const step = event.shiftKey ? 10 : 1;
                const [dx, dy] = { ArrowLeft: [-step, 0], ArrowRight: [step, 0], ArrowUp: [0, -step], ArrowDown: [0, step] }[event.key];
                this.nudge(dx, dy);
            }
        },

        beforeUnload(event) {
            if (this.dirty) {
                event.preventDefault();
                event.returnValue = '';
            }
        },
    },
};
</script>

<template>
    <Head :title="[design.title, __('Social Images')]" />

    <Header :title="design.title" :icon="icon">
        <Button :href="urls.index" :text="__('Templates')" variant="ghost" />
        <Button icon="undo" variant="ghost" :aria-label="__('Undo')" :disabled="!history.length" @click="undo" />
        <Button icon="redo" variant="ghost" :aria-label="__('Redo')" :disabled="!future.length" @click="redo" />
        <Button :text="__('Export')" icon="download" @click="exportTemplate" />
        <Button :text="__('Render')" icon="image" :loading="rendering" @click="render" />
        <Button variant="primary" :text="dirty ? __('Save') : __('Saved')" :loading="saving" :disabled="!dirty" @click="save" />
    </Header>

    <div class="si-designer">
        <!-- Toolbar -->
        <div class="si-designer__bar">
            <input class="si-input si-designer__title" :value="design.title" :aria-label="__('Template name')" @input="change(() => (design.title = $event.target.value))" />

            <div class="si-tabs" role="tablist" :aria-label="__('Layouts')">
                <button
                    v-for="tab in layoutTabs"
                    :key="tab.handle"
                    type="button"
                    role="tab"
                    :aria-selected="tab.handle === active"
                    :class="{ 'is-on': tab.handle === active }"
                    @click="switchLayout(tab.handle)"
                >
                    {{ tab.label }} <span>{{ tab.size }}</span>
                    <span v-if="tab.handle !== 'og' && tab.handle === active" class="si-tabs__remove" role="button" tabindex="0" :aria-label="__('Remove this size')" :title="__('Remove this size')" @click.stop="removeLayout(tab.handle)" @keydown.enter.stop.prevent="removeLayout(tab.handle)"><Icon name="close" /></span>
                </button>
                <button type="button" class="si-tabs__add" @click="addingLayout = true"><Icon name="plus" /> {{ __('Size') }}</button>
            </div>

            <div class="si-designer__entry">
                <label class="si-label" for="si-entry">{{ __('Preview with') }}</label>
                <select id="si-entry" class="si-input" :value="entry ?? ''" @change="entry = $event.target.value || null">
                    <option value="">{{ __('Sample text') }}</option>
                    <option v-for="option in entryOptions" :key="option.id" :value="option.id">{{ option.title }} ({{ option.collection }})</option>
                </select>
                <input v-model="entrySearch" class="si-input si-designer__search" :placeholder="__('Find an entry')" @keydown.enter.prevent="searchEntries" @change="searchEntries" />
            </div>
        </div>

        <div class="si-designer__body">
            <!-- Layers -->
            <aside class="si-designer__side">
                <div class="si-designer__add">
                    <button type="button" @click="add('text')"><Icon name="text" />{{ __('Text') }}</button>
                    <button type="button" @click="add('image')"><Icon name="image" />{{ __('Image') }}</button>
                    <button type="button" @click="add('shape')"><Icon name="rect" />{{ __('Box') }}</button>
                    <button type="button" @click="add('ellipse')"><Icon name="ellipse" />{{ __('Circle') }}</button>
                </div>
                <LayerList :layers="layout.layers" :selected="selected" @select="selected = $event" @toggle="toggle" @reorder="reorder" @step="step" />
                <button type="button" class="si-layers-bg" :class="{ 'is-selected': !selected }" @click="selected = null">{{ __('Background') }}</button>
            </aside>

            <!-- Canvas -->
            <main class="si-designer__canvas">
                <Canvas
                    :layout="layout"
                    :data="canvasData"
                    :selected="selected"
                    @select="selected = $event"
                    @change="moveLayer"
                    @commit="commit"
                    @edit="selected = $event; $nextTick(() => $refs.properties?.focusText())"
                />
            </main>

            <!-- Properties -->
            <aside class="si-designer__side si-designer__side--wide">
                <Properties
                    ref="properties"
                    :layer="layer"
                    :layout="layout"
                    :fonts="fonts_"
                    :collections="collections"
                    :urls="urls"
                    @update="updateLayer"
                    @update-layout="updateLayout"
                    @add-font="fontModal = true"
                    @remove="remove()"
                    @duplicate="duplicate()"
                />
            </aside>
        </div>
    </div>

    <!-- Render -->
    <Modal :open="rendering || !!rendered || !!renderError" :title="__('Rendered image')" @update:open="!$event && (rendered = null, renderError = null)">
        <p v-if="rendering" class="si-hint">{{ __('Rendering through the screenshot service…') }}</p>
        <template v-if="rendered">
            <img :src="rendered.image" alt="" class="w-full rounded border border-gray-200" />
            <p class="si-hint mt-2">{{ __(':width × :height, rendered in :seconds s.', { width: layout.width, height: layout.height, seconds: rendered.seconds }) }}</p>
        </template>
        <p v-if="renderError" class="text-sm text-red-600">{{ renderError }}</p>
    </Modal>

    <!-- Add a layout -->
    <Modal v-model:open="addingLayout" :title="__('Add a size')">
        <p class="si-hint mb-3">{{ __('The new size starts from the Open Graph layout, scaled to fit. Then move things around.') }}</p>
        <div class="space-y-2">
            <button v-for="[handle, preset] in missingPresets" :key="handle" type="button" class="si-choice" @click="addLayout(handle, preset)">
                <span class="font-medium">{{ preset.title }}</span>
                <span class="si-hint">{{ preset.width }} × {{ preset.height }}</span>
            </button>
        </div>
        <div class="mt-4 flex items-end gap-2">
            <label class="si-num"><span>{{ __('Width') }}</span><input v-model.number="custom.width" type="number" class="si-input" min="200" max="2400" /></label>
            <label class="si-num"><span>{{ __('Height') }}</span><input v-model.number="custom.height" type="number" class="si-input" min="200" max="2400" /></label>
            <Button :text="__('Add custom size')" @click="addLayout(null, { width: Math.max(200, Math.min(2400, custom.width)), height: Math.max(200, Math.min(2400, custom.height)) })" />
        </div>
    </Modal>

    <!-- Add a font -->
    <Modal v-model:open="fontModal" :title="__('Add a font')">
        <div class="mb-4 flex gap-2">
            <Button size="sm" :variant="fontForm.mode === 'google' ? 'default' : 'ghost'" :text="__('Google Fonts')" @click="fontForm.mode = 'google'" />
            <Button size="sm" :variant="fontForm.mode === 'upload' ? 'default' : 'ghost'" :text="__('Upload')" @click="fontForm.mode = 'upload'" />
        </div>

        <label class="si-label" for="si-font-family">{{ __('Name') }}</label>
        <input id="si-font-family" v-model="fontForm.family" class="si-input" :placeholder="fontForm.mode === 'google' ? 'Lora' : __('Your font’s name')" />

        <template v-if="fontForm.mode === 'google'">
            <p class="si-label mt-3">{{ __('Weights') }}</p>
            <div class="flex flex-wrap gap-3">
                <label v-for="w in [300, 400, 500, 600, 700, 800, 900]" :key="w" class="si-check">
                    <input v-model="fontForm.weights" type="checkbox" :value="w" />{{ w }}
                </label>
            </div>
            <label class="si-check mt-2"><input v-model="fontForm.italic" type="checkbox" />{{ __('Italics too') }}</label>
            <p class="si-hint mt-3">{{ __('The font is copied to your site once. Images never load it from Google.') }}</p>
        </template>

        <template v-else>
            <input type="file" accept=".woff2,.woff,.ttf,.otf" class="mt-3 block w-full text-sm" @change="fontForm.file = $event.target.files[0]" />
            <div class="mt-3 flex gap-3">
                <label class="si-num"><span>{{ __('Weight') }}</span><input v-model.number="fontForm.weight" type="number" class="si-input" step="100" min="100" max="900" /></label>
                <label class="si-check"><input type="checkbox" :checked="fontForm.style === 'italic'" @change="fontForm.style = $event.target.checked ? 'italic' : 'normal'" />{{ __('Italic') }}</label>
            </div>
            <p class="si-hint mt-3">{{ __('Upload one file per weight and style. Make sure your font’s licence allows it to be used in images.') }}</p>
        </template>

        <p v-if="fontError" class="mt-3 text-sm text-red-600">{{ fontError }}</p>

        <div class="mt-4 flex justify-end gap-2">
            <Button :text="__('Cancel')" @click="fontModal = false" />
            <Button variant="primary" :loading="fontBusy" :disabled="!fontForm.family.trim()" :text="__('Add font')" @click="addFont" />
        </div>
    </Modal>
</template>

<style scoped>
.si-designer {
    display: flex;
    flex-direction: column;
    height: calc(100vh - 11rem);
    min-height: 560px;
    border-radius: 0.75rem;
    overflow: hidden;
    background: var(--si-surface);
    color: var(--si-text);
    box-shadow: 0 0 0 1px var(--si-line);
}

.si-designer__bar {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 0.75rem;
    padding: 0.5rem 0.75rem;
    border-bottom: 1px solid var(--si-line);
}

.si-designer__title {
    width: 14rem;
    font-weight: 600;
}

.si-designer__entry {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin-left: auto;
}

.si-designer__entry .si-input {
    width: 14rem;
}

.si-designer__search.si-input {
    width: 9rem;
}

.si-designer__body {
    display: grid;
    grid-template-columns: 13rem 1fr 18rem;
    flex: 1;
    min-height: 0;
}

.si-designer__side {
    overflow-y: auto;
    padding: 0.75rem;
    border-right: 1px solid var(--si-line);
}

.si-designer__side--wide {
    border-right: 0;
    border-left: 1px solid var(--si-line);
}

.si-designer__canvas {
    min-width: 0;
    min-height: 0;
}

.si-designer__add {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 0.375rem;
    margin-bottom: 0.75rem;
}

.si-designer__add button {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.375rem;
    padding: 0.375rem;
    border-radius: 0.375rem;
    font-size: 0.8125rem;
    background: var(--si-hover);
}

.si-designer__add button:hover {
    background: rgb(59 130 246 / 0.12);
}

.si-layers-bg {
    width: 100%;
    margin-top: 0.5rem;
    padding: 0.3rem 0.5rem;
    border-radius: 0.375rem;
    font-size: 0.8125rem;
    text-align: left;
    color: #6b7280;
}

.si-layers-bg.is-selected {
    background: rgb(59 130 246 / 0.12);
    color: inherit;
}

.si-tabs {
    display: flex;
    gap: 0.25rem;
}

.si-tabs button {
    display: inline-flex;
    align-items: center;
    gap: 0.375rem;
    padding: 0.3rem 0.625rem;
    border-radius: 0.375rem;
    font-size: 0.8125rem;
}

.si-tabs button span {
    font-size: 0.6875rem;
    color: #6b7280;
}

.si-tabs button.is-on {
    background: rgb(59 130 246 / 0.12);
}

.si-tabs__remove {
    display: inline-flex;
    margin-left: 0.125rem;
    border-radius: 0.25rem;
}

.si-tabs__remove:focus-visible {
    outline: 2px solid #3b82f6;
}

.si-tabs__add {
    color: #2563eb;
}

.si-choice {
    display: flex;
    width: 100%;
    justify-content: space-between;
    padding: 0.5rem 0.75rem;
    border-radius: 0.5rem;
    box-shadow: 0 0 0 1px var(--si-line);
}

.si-choice:hover {
    background: rgb(59 130 246 / 0.06);
}

@media (max-width: 1280px) {
    .si-designer__body {
        grid-template-columns: 11rem 1fr 15rem;
    }
}

/* Narrower: the canvas on top, full width, with the layers and settings
   beneath it, and the page scrolls. */
@media (max-width: 1100px) {
    .si-designer {
        height: auto;
    }

    .si-designer__body {
        grid-template-columns: 1fr;
        grid-template-areas: 'canvas' 'layers' 'settings';
    }

    .si-designer__canvas {
        grid-area: canvas;
        height: 60vw;
        min-height: 280px;
        max-height: 520px;
    }

    .si-designer__side {
        grid-area: layers;
        border-right: 0;
        border-top: 1px solid var(--si-line);
    }

    .si-designer__side--wide {
        grid-area: settings;
        border-left: 0;
    }

    .si-designer__entry {
        margin-left: 0;
        flex-wrap: wrap;
    }
}
</style>
