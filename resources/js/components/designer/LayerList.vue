<!--
    The layout's layers, top first (the reverse of drawing order), with
    show/hide and lock, and drag (or the arrows) to reorder.
-->
<script>
import Icon from './Icon.vue';
export default {
    components: { Icon },

    props: {
        layers: { type: Array, required: true },
        selected: { type: String, default: null },
    },

    emits: ['select', 'toggle', 'step', 'reorder'],

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
            return layer.type === 'shape' ? layer.shape : layer.type;
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
    <ul class="si-layers" :aria-label="__('Layers')">
        <li
            v-for="(layer, index) in rows"
            :key="layer.id"
            class="si-layers__row"
            :class="{ 'is-selected': layer.id === selected, 'is-over': layer.id === over, 'is-hidden': layer.hidden }"
            draggable="true"
            @click="$emit('select', layer.id)"
            @dragstart="dragging = layer.id"
            @dragover.prevent="over = layer.id"
            @dragleave="over = null"
            @drop.prevent="drop(layer.id)"
            @dragend="dragging = null; over = null"
        >
            <button
                type="button"
                class="si-layers__select"
                :aria-current="layer.id === selected ? 'true' : null"
                :title="__('Alt + ↑ or ↓ moves it in front or behind')"
                @click.stop="$emit('select', layer.id)"
                @keydown.alt.up.prevent="$emit('step', layer.id, 1)"
                @keydown.alt.down.prevent="$emit('step', layer.id, -1)"
            >
                <Icon :name="icon(layer)" class="si-layers__icon" />
                <span class="si-layers__name">{{ layer.name || layer.type }}</span>
                <span v-if="layer.when" class="si-layers__when" :title="__('Only when :when has a value', { when: layer.when })">
                    <span aria-hidden="true">?</span>
                    <span class="sr-only">{{ __('Only when :when has a value', { when: layer.when }) }}</span>
                </span>
            </button>
            <button type="button" class="si-icon-button" :title="layer.hidden ? __('Show') : __('Hide')" :aria-label="(layer.hidden ? __('Show') : __('Hide')) + ' ' + (layer.name || layer.type)" :aria-pressed="layer.hidden ? 'true' : 'false'" @click.stop="$emit('toggle', layer.id, 'hidden')">
                <Icon :name="layer.hidden ? 'eye-off' : 'eye'" />
            </button>
            <button type="button" class="si-icon-button" :class="{ 'is-faint': !layer.locked }" :title="layer.locked ? __('Unlock') : __('Lock')" :aria-label="(layer.locked ? __('Unlock') : __('Lock')) + ' ' + (layer.name || layer.type)" :aria-pressed="layer.locked ? 'true' : 'false'" @click.stop="$emit('toggle', layer.id, 'locked')">
                <Icon :name="layer.locked ? 'lock' : 'unlock'" />
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

.si-layers__select {
    display: flex;
    flex: 1;
    min-width: 0;
    align-items: center;
    gap: 0.375rem;
    text-align: left;
    border-radius: 0.25rem;
}

.si-layers__icon {
    flex: none;
    color: #6b7280;
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
