<!--
    Insert a {token}: the entry's title and fields (from every collection),
    the site, the collection, the date, the URL.
-->
<script>
export default {
    props: {
        collections: { type: Array, required: true },
        kind: { type: String, default: 'text' },
        // Which side of the link the menu lines up with: "right" opens
        // leftwards (for a link at the panel's right edge), "left" rightwards.
        align: { type: String, default: 'right' },
    },

    emits: ['insert'],

    data() {
        return { open: false, query: '' };
    },

    computed: {
        groups() {
            const query = this.query.trim().toLowerCase();
            const match = (item) => !query || item.label.toLowerCase().includes(query) || item.value.toLowerCase().includes(query);

            const general = this.kind === 'image' ? [] : [
                { label: this.__('Title'), value: 'title' },
                { label: this.__('Site name'), value: 'site:name' },
                { label: this.__('Collection'), value: 'collection:title' },
                { label: this.__('Date (16 October 2026)'), value: 'date' },
                { label: this.__('Date (16 Oct 2026)'), value: 'date:j M Y' },
                { label: this.__('Author'), value: 'author:name' },
                { label: this.__('Domain'), value: 'domain' },
                { label: this.__('Path'), value: 'path' },
            ];

            const groups = [{ title: this.__('General'), items: general.filter(match) }];

            for (const collection of this.collections) {
                const items = collection.fields
                    .filter((field) => (this.kind === 'image' ? field.kind === 'image' || field.kind === 'related' : field.kind !== 'image'))
                    .filter((field) => field.handle !== 'title')
                    .map((field) => ({
                        label: field.display || field.handle,
                        value: field.kind === 'related' ? `${field.handle}.${this.kind === 'image' ? 'avatar' : 'title'}` : field.handle,
                        hint: field.type,
                    }))
                    .filter(match);

                if (items.length) {
                    groups.push({ title: collection.title, items });
                }
            }

            return groups.filter((group) => group.items.length);
        },
    },

    methods: {
        pick(item) {
            this.$emit('insert', item.value);
            this.open = false;
            this.query = '';
        },
    },
};
</script>

<template>
    <div class="si-tokens">
        <button type="button" class="si-link" @click="open = !open">{{ kind === 'image' ? __('Add a field') : __('Insert a field') }}</button>
        <div v-if="open" class="si-tokens__menu" :class="`si-tokens__menu--${align}`" @keydown.esc="open = false">
            <input v-model="query" class="si-input" :placeholder="__('Search fields')" autofocus />
            <div v-for="group in groups" :key="group.title" class="si-tokens__group">
                <p class="si-tokens__title">{{ group.title }}</p>
                <button v-for="item in group.items" :key="group.title + item.value" type="button" class="si-tokens__item" @click="pick(item)">
                    <span>{{ item.label }}</span>
                    <code>{{ item.value }}</code>
                </button>
            </div>
            <p v-if="!groups.length" class="si-hint">{{ __('No fields match.') }}</p>
        </div>
    </div>
</template>

<style scoped>
.si-tokens {
    position: relative;
}

.si-tokens__menu {
    position: absolute;
    z-index: 20;
    top: 1.5rem;
    width: 17rem;
    max-width: calc(100vw - 2rem);
    max-height: 22rem;
    overflow-y: auto;
    padding: 0.5rem;
    background: var(--si-surface);
    border-radius: 0.5rem;
    box-shadow: 0 0 0 1px rgb(0 0 0 / 0.08), 0 12px 24px -8px rgb(0 0 0 / 0.25);
}

.si-tokens__menu--right {
    right: 0;
}

.si-tokens__menu--left {
    left: 0;
}

.si-tokens__title {
    margin: 0.5rem 0.25rem 0.25rem;
    font-size: 0.6875rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    color: #6b7280;
}

.si-tokens__item {
    display: flex;
    width: 100%;
    justify-content: space-between;
    gap: 0.5rem;
    padding: 0.25rem 0.375rem;
    border-radius: 0.25rem;
    font-size: 0.8125rem;
    text-align: left;
}

.si-tokens__item:hover {
    background: rgb(59 130 246 / 0.1);
}

.si-tokens__item code {
    padding: 0;
    background: none;
    font-size: 0.6875rem;
    color: #6b7280;
}
</style>
