<!--
    Where an image layer's picture comes from, in order: the first that has
    an image is used. Entry fields, or an image uploaded to the template.
-->
<script>
import Icon from './Icon.vue';
import TokenMenu from './TokenMenu.vue';

export default {
    components: { Icon, TokenMenu },

    props: {
        modelValue: { type: Array, required: true },
        collections: { type: Array, required: true },
        uploadUrl: { type: String, required: true },
        uploadShowUrl: { type: String, required: true },
    },

    emits: ['update:modelValue'],

    data() {
        return { uploading: false };
    },

    methods: {
        label(source) {
            const [kind, value] = [source.slice(0, source.indexOf(':')), source.slice(source.indexOf(':') + 1)];

            return { field: this.__('Field: :field', { field: value }), upload: this.__('Uploaded image'), asset: this.__('Asset: :asset', { asset: value }) }[kind] ?? source;
        },

        preview(source) {
            return source.startsWith('upload:') ? this.uploadShowUrl.replace('__NAME__', source.slice(7)) : null;
        },

        add(source) {
            if (!this.modelValue.includes(source)) {
                this.$emit('update:modelValue', [...this.modelValue, source].slice(0, 6));
            }
        },

        remove(index) {
            this.$emit('update:modelValue', this.modelValue.filter((_, i) => i !== index));
        },

        up(index) {
            const list = [...this.modelValue];
            [list[index - 1], list[index]] = [list[index], list[index - 1]];
            this.$emit('update:modelValue', list);
        },

        upload(event) {
            const file = event.target.files[0];

            if (!file) {
                return;
            }

            const form = new FormData();
            form.append('file', file);
            this.uploading = true;

            this.$axios
                .post(this.uploadUrl, form)
                .then(({ data }) => this.add(data.source))
                .catch((error) => this.$toast.error(error.response?.data?.message ?? this.__('Something went wrong')))
                .finally(() => {
                    this.uploading = false;
                    event.target.value = '';
                });
        },
    },
};
</script>

<template>
    <div class="space-y-1.5">
        <div v-for="(source, index) in modelValue" :key="source" class="si-source">
            <img v-if="preview(source)" :src="preview(source)" alt="" class="si-source__thumb" />
            <span class="si-source__label">{{ index ? __('else') + ' ' : '' }}{{ label(source) }}</span>
            <button v-if="index" type="button" class="si-icon-button" :aria-label="__('Try this one earlier')" :title="__('Try this one earlier')" @click="up(index)"><Icon name="up" /></button>
            <button type="button" class="si-icon-button" :aria-label="__('Remove')" :title="__('Remove')" @click="remove(index)"><Icon name="close" /></button>
        </div>
        <p v-if="!modelValue.length" class="si-hint">{{ __('No picture yet.') }}</p>
        <div class="flex items-baseline justify-between gap-2">
            <TokenMenu :collections="collections" kind="image" align="left" @insert="add('field:' + $event)" />
            <label class="si-link cursor-pointer">
                {{ uploading ? __('Uploading…') : __('Upload an image') }}
                <input type="file" accept="image/png,image/jpeg,image/webp,image/gif,image/svg+xml,image/avif" class="sr-only" @change="upload" />
            </label>
        </div>
    </div>
</template>

<style scoped>
.si-source {
    display: flex;
    align-items: center;
    gap: 0.375rem;
    padding: 0.25rem 0.375rem;
    border-radius: 0.375rem;
    background: var(--si-hover);
    font-size: 0.8125rem;
}

.si-source__thumb {
    width: 1.5rem;
    height: 1.5rem;
    object-fit: contain;
    border-radius: 0.25rem;
    background: #fff;
}

.si-source__label {
    flex: 1;
    min-width: 0;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
</style>
