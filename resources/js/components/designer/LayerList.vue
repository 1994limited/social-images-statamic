<!--
    The layout's layers, top first (the reverse of drawing order), with
    show/hide and lock, and drag (or the arrows) to reorder.
-->
<script>
export default {
    props: {
        layers: { type: Array, required: true },
        selected: { type: String, default: null },
    },

    emits: ['select', 'toggle', 'move', 'reorder'],

    data() {
        return { dragging: null, over: null };
    },

    computed: {
        rows() {
            return [...this.layers].reverse();
        },
    },

    methods: {
        icon(layer) {
            return { text: 'T', image: '▧', shape: layer.shape === 'ellipse' ? '●' : '■' }[layer.type];
        },

        drop(target) {
            if (this.dragging && this.dragging !== target) {
                this.$emit('reorder', this.dragging, target);
            }

            this.dragging = null;
            this.over = null;
        },
    },
};
</script>

<template>
    <ul class="si-layers" role="listbox" :aria-label="__('Layers')">
        <li
            v-for="layer in rows"
            :key="layer.id"
            class="si-layers__row"
            :class="{ 'is-selected': layer.id === selected, 'is-over': layer.id === over, 'is-hidden': layer.hidden }"
            role="option"
            :aria-selected="layer.id === selected"
            draggable="true"
            @click="$emit('select', layer.id)"
            @dragstart="dragging = layer.id"
            @dragover.prevent="over = layer.id"
            @dragleave="over = null"
            @drop.prevent="drop(layer.id)"
            @dragend="dragging = null; over = null"
        >
            <span class="si-layers__icon" aria-hidden="true">{{ icon(layer) }}</span>
            <span class="si-layers__name">{{ layer.name || layer.type }}</span>
            <span v-if="layer.when" class="si-layers__when" :title="__('Only when :when has a value', { when: layer.when })">?</span>
            <button type="button" class="si-icon-button" :title="layer.hidden ? __('Show') : __('Hide')" :aria-label="layer.hidden ? __('Show') : __('Hide')" @click.stop="$emit('toggle', layer.id, 'hidden')">
                {{ layer.hidden ? '◌' : '◉' }}
            </button>
            <button type="button" class="si-icon-button" :title="layer.locked ? __('Unlock') : __('Lock')" :aria-label="layer.locked ? __('Unlock') : __('Lock')" @click.stop="$emit('toggle', layer.id, 'locked')">
                {{ layer.locked ? '🔒' : '·' }}
            </button>
        </li>
    </ul>
</template>

<style scoped>
.si-layers {
    margin: 0;
    padding: 0;
    list-style: none;
}

.si-layers__row {
    display: flex;
    align-items: center;
    gap: 0.375rem;
    padding: 0.3rem 0.5rem;
    border-radius: 0.375rem;
    font-size: 0.8125rem;
    cursor: pointer;
}

.si-layers__row:hover {
    background: var(--si-hover);
}

.si-layers__row.is-selected {
    background: rgb(59 130 246 / 0.12);
}

.si-layers__row.is-over {
    box-shadow: inset 0 2px 0 #3b82f6;
}

.si-layers__row.is-hidden .si-layers__name {
    opacity: 0.45;
}

.si-layers__icon {
    width: 1.1rem;
    text-align: center;
    color: #6b7280;
    font-size: 0.75rem;
}

.si-layers__name {
    flex: 1;
    min-width: 0;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.si-layers__when {
    font-size: 0.6875rem;
    color: #b45309;
}
</style>
