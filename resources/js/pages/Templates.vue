<!--
    Tools → Social Images: the site's templates, as live previews with sample
    text, and making new ones (blank, from a starter, or imported).

    Previews are each template's own page in a sandboxed frame, scaled down,
    so what's shown is what's rendered.
-->
<script>
import { Head, router } from '@statamic/cms/inertia';
import { Badge, Button, Dropdown, DropdownItem, DropdownMenu, DropdownSeparator, Header, Input, Modal, Textarea } from '@statamic/cms/ui';
import icon from '../../brand/nav-icon.svg?raw';
import LayoutPreview from '../components/LayoutPreview.vue';
import Thumbnail from '../components/Thumbnail.vue';

export default {
    components: { Badge, Button, Dropdown, DropdownItem, DropdownMenu, DropdownSeparator, Head, Header, Input, Modal, Textarea, LayoutPreview, Thumbnail },

    props: {
        templates: { type: Array, required: true },
        starters: { type: Array, required: true },
        canManage: { type: Boolean, default: false },
        urls: { type: Object, required: true },
        gallery: { type: String, required: true },
    },

    data() {
        return {
            rows: [...this.templates],
            creating: false,
            importing: false,
            importMode: 'paste',
            importJson: '',
            importUrl: '',
            importFile: null,
            importError: null,
            busy: false,
        };
    },

    computed: {
        icon: () => icon,
    },

    methods: {
        create(starter = null) {
            this.busy = true;

            this.$axios
                .post(this.urls.store, { starter })
                .then(({ data }) => router.visit(data.redirect))
                .catch((error) => this.$toast.error(error.response?.data?.message ?? this.__('Something went wrong')))
                .finally(() => (this.busy = false));
        },

        openImport() {
            this.importing = true;
            this.importError = null;
        },

        chooseFile(event) {
            this.importFile = event.target.files[0] ?? null;
        },

        runImport() {
            const form = new FormData();

            if (this.importMode === 'file' && this.importFile) {
                form.append('file', this.importFile);
            } else if (this.importMode === 'url') {
                form.append('url', this.importUrl);
            } else {
                form.append('json', this.importJson);
            }

            this.busy = true;
            this.importError = null;

            this.$axios
                .post(this.urls.import, form)
                .then(({ data }) => router.visit(data.redirect))
                .catch((error) => (this.importError = error.response?.data?.message ?? this.__('Something went wrong')))
                .finally(() => (this.busy = false));
        },

        duplicate(row) {
            this.$axios
                .post(row.duplicate_url)
                .then(({ data }) => router.visit(data.redirect))
                .catch(() => this.$toast.error(this.__('Something went wrong')));
        },

        destroy(row) {
            const warning = row.used_by
                ? this.__('Delete :title? :count settings rules use it; their entries will have no image until you choose another template.', { title: row.title, count: row.used_by })
                : this.__('Delete :title?', { title: row.title });

            if (!confirm(warning)) {
                return;
            }

            this.$axios
                .delete(row.delete_url)
                .then(() => {
                    this.rows = this.rows.filter((r) => r.handle !== row.handle);
                    this.$toast.success(this.__('Deleted'));
                })
                .catch(() => this.$toast.error(this.__('Something went wrong')));
        },

        sizes(row) {
            return Object.entries(row.layouts).map(([handle, size]) => `${handle} ${size.width}×${size.height}`).join(' · ');
        },
    },
};
</script>

<template>
    <Head :title="__('Social Images')" />

    <Header :title="__('Social Images')" :icon="icon">
        <Button :href="urls.images" :text="__('Images')" icon="image" />
        <Button :href="urls.settings" :text="__('Settings')" icon="cog" />
        <template v-if="canManage">
            <Button :text="__('Import')" icon="upload" @click="openImport" />
            <Button variant="primary" :text="__('Create template')" :loading="busy" @click="creating = true" />
        </template>
    </Header>

    <div v-if="!rows.length" class="mb-8 rounded-lg border border-dashed border-gray-300 p-10 text-center dark:border-gray-700">
        <p class="mb-1 font-medium">{{ __('No templates yet') }}</p>
        <p class="mb-4 text-sm text-gray-600 dark:text-gray-400">
            {{ __('Start from one of ours, design your own, or import one from the gallery.') }}
        </p>
        <Button v-if="canManage" variant="primary" :text="__('Create template')" @click="creating = true" />
    </div>

    <div class="si-templates">
        <div v-for="row in rows" :key="row.handle" class="si-template">
            <component :is="row.edit_url ? 'a' : 'div'" :href="row.edit_url" class="si-template__preview">
                <LayoutPreview v-if="row.layout" :layout="row.layout" :preview-url="urls.preview" />
                <Thumbnail v-else :src="row.preview_url" :width="row.layouts.og.width" :height="row.layouts.og.height" />
            </component>

            <div class="mt-2 flex items-start justify-between gap-2">
                <div class="min-w-0">
                    <a v-if="row.edit_url" :href="row.edit_url" class="block truncate font-medium hover:underline">{{ row.title }}</a>
                    <span v-else class="block truncate font-medium">{{ row.title }}</span>
                    <span class="text-xs text-gray-500">{{ sizes(row) }}</span>
                </div>

                <div class="flex shrink-0 items-center gap-1">
                    <Badge v-if="row.kind === 'view'" size="sm" color="purple" :text="__('Code')" />
                    <Badge v-if="row.used_by" size="sm" color="green" :text="__('In use')" />

                    <Dropdown v-if="canManage && row.edit_url">
                        <template #trigger>
                            <Button icon="dots" variant="ghost" size="sm" :aria-label="__('Actions for :title', { title: row.title })" />
                        </template>
                        <DropdownMenu>
                            <DropdownItem :text="__('Edit')" :href="row.edit_url" />
                            <DropdownItem :text="__('Duplicate')" @click="duplicate(row)" />
                            <DropdownItem :text="__('Export')" :href="row.export_url" />
                            <DropdownSeparator />
                            <DropdownItem :text="__('Delete')" variant="destructive" @click="destroy(row)" />
                        </DropdownMenu>
                    </Dropdown>
                </div>
            </div>
            <p v-if="row.kind === 'view'" class="mt-1 text-xs text-gray-500">
                {{ __('Code template: :view', { view: row.view }) }}
            </p>
        </div>
    </div>

    <Modal v-model:open="creating" :title="__('Create template')">
        <p class="mb-4 text-sm text-gray-600 dark:text-gray-400">{{ __('Start from a design and change anything, or start blank.') }}</p>
        <div class="si-starters">
            <button type="button" class="si-starter" :disabled="busy" @click="create()">
                <div class="si-starter__blank">+</div>
                <span class="font-medium">{{ __('Blank') }}</span>
            </button>
            <button v-for="starter in starters" :key="starter.key" type="button" class="si-starter" :disabled="busy" @click="create(starter.key)">
                <LayoutPreview v-if="starter.layout" :layout="starter.layout" :preview-url="urls.preview" class="si-starter__preview" />
                <span class="font-medium">{{ starter.title }}</span>
                <span class="text-xs text-gray-500">{{ starter.description }}</span>
            </button>
        </div>
    </Modal>

    <Modal v-model:open="importing" :title="__('Import a template')">
        <div class="mb-4 flex gap-2">
            <Button size="sm" :variant="importMode === 'paste' ? 'default' : 'ghost'" :text="__('Paste')" @click="importMode = 'paste'" />
            <Button size="sm" :variant="importMode === 'file' ? 'default' : 'ghost'" :text="__('File')" @click="importMode = 'file'" />
            <Button size="sm" :variant="importMode === 'url' ? 'default' : 'ghost'" :text="__('Address')" @click="importMode = 'url'" />
        </div>

        <Textarea v-if="importMode === 'paste'" v-model="importJson" :rows="10" class="font-mono text-xs" :placeholder="__('Paste a template’s JSON')" />
        <input v-else-if="importMode === 'file'" type="file" accept=".json,application/json" class="block w-full text-sm" @change="chooseFile" />
        <Input v-else v-model="importUrl" type="url" placeholder="https://…/template.json" />

        <p class="mt-3 text-xs text-gray-500">
            {{ __('Templates only hold layout: positions, colours, fonts and which fields to show. Their images and fonts are added to your site.') }}
            <a :href="gallery" target="_blank" rel="noopener" class="underline">{{ __('Browse the gallery') }}</a>
        </p>

        <p v-if="importError" class="mt-3 text-sm text-red-600">{{ importError }}</p>

        <div class="mt-4 flex justify-end gap-2">
            <Button :text="__('Cancel')" @click="importing = false" />
            <Button variant="primary" :loading="busy" :text="__('Import')" @click="runImport" />
        </div>
    </Modal>
</template>

<style scoped>
.si-templates {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(18rem, 1fr));
    gap: 1.5rem;
}

.si-template__preview {
    display: block;
    overflow: hidden;
    border-radius: 0.5rem;
    box-shadow: 0 0 0 1px rgb(0 0 0 / 0.08);
}

.si-starters {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(12rem, 1fr));
    gap: 1rem;
    max-height: 60vh;
    overflow-y: auto;
}

.si-starter {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
    text-align: left;
    padding: 0.5rem;
    border-radius: 0.5rem;
}

.si-starter:hover {
    background: rgb(0 0 0 / 0.04);
}

.si-starter__preview {
    border-radius: 0.375rem;
    overflow: hidden;
    box-shadow: 0 0 0 1px rgb(0 0 0 / 0.08);
}

.si-starter__blank {
    display: flex;
    aspect-ratio: 1200 / 630;
    align-items: center;
    justify-content: center;
    border: 1px dashed rgb(0 0 0 / 0.2);
    border-radius: 0.375rem;
    font-size: 2rem;
    color: #9ca3af;
}
</style>
