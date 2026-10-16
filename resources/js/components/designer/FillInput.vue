<!--
    A fill: none, a colour, or a linear or radial gradient.
-->
<script>
import ColorInput from './ColorInput.vue';

export default {
    components: { ColorInput },

    props: {
        modelValue: { type: Object, default: null },
        allowNone: { type: Boolean, default: true },
    },

    emits: ['update:modelValue'],

    computed: {
        type() {
            return this.modelValue?.type ?? 'none';
        },
    },

    methods: {
        setType(type) {
            const first = this.modelValue?.color ?? this.modelValue?.stops?.[0]?.color ?? '#111111';
            const last = this.modelValue?.stops?.at(-1)?.color ?? '#ffffff';

            const fill = {
                none: null,
                solid: { type: 'solid', color: first },
                linear: { type: 'linear', angle: 135, stops: [{ color: first, position: 0 }, { color: last, position: 100 }] },
                radial: { type: 'radial', x: 50, y: 50, stops: [{ color: first, position: 0 }, { color: last, position: 100 }] },
            }[type];

            this.$emit('update:modelValue', fill);
        },

        set(patch) {
            this.$emit('update:modelValue', { ...this.modelValue, ...patch });
        },

        setStop(index, patch) {
            const stops = this.modelValue.stops.map((stop, i) => (i === index ? { ...stop, ...patch } : stop));
            this.set({ stops });
        },

        addStop() {
            const stops = [...this.modelValue.stops];
            stops.splice(stops.length - 1, 0, { color: stops.at(-1).color, position: 50 });
            this.set({ stops });
        },

        removeStop(index) {
            this.set({ stops: this.modelValue.stops.filter((_, i) => i !== index) });
        },
    },
};
</script>

<template>
    <div class="space-y-2">
        <select class="si-input" :value="type" :aria-label="__('Fill')" @change="setType($event.target.value)">
            <option v-if="allowNone" value="none">{{ __('None') }}</option>
            <option value="solid">{{ __('Colour') }}</option>
            <option value="linear">{{ __('Linear gradient') }}</option>
            <option value="radial">{{ __('Radial gradient') }}</option>
        </select>

        <ColorInput v-if="type === 'solid'" :model-value="modelValue.color" @update:model-value="set({ color: $event })" />

        <template v-if="type === 'linear' || type === 'radial'">
            <div v-if="type === 'linear'" class="si-row">
                <label class="si-label">{{ __('Angle') }}</label>
                <input type="number" class="si-input" :value="modelValue.angle" @input="set({ angle: Number($event.target.value) })" />
            </div>
            <div v-else class="si-row">
                <label class="si-label">{{ __('Centre') }}</label>
                <input type="number" class="si-input" min="0" max="100" :value="modelValue.x" :aria-label="__('Across (%)')" @input="set({ x: Number($event.target.value) })" />
                <input type="number" class="si-input" min="0" max="100" :value="modelValue.y" :aria-label="__('Down (%)')" @input="set({ y: Number($event.target.value) })" />
            </div>
            <div v-for="(stop, index) in modelValue.stops" :key="index" class="si-row">
                <ColorInput :model-value="stop.color" @update:model-value="setStop(index, { color: $event })" />
                <input type="number" class="si-input si-input--narrow" min="0" max="100" :value="stop.position" :aria-label="__('Position (%)')" @input="setStop(index, { position: Number($event.target.value) })" />
                <button v-if="modelValue.stops.length > 2" type="button" class="si-icon-button" :aria-label="__('Remove')" @click="removeStop(index)">×</button>
            </div>
            <button v-if="modelValue.stops.length < 8" type="button" class="si-link" @click="addStop">{{ __('Add a colour') }}</button>
        </template>
    </div>
</template>
