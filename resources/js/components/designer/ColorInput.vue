<!--
    A colour with transparency, as #rrggbb or #rrggbbaa.
-->
<script>
export default {
    props: {
        modelValue: { type: String, default: '#000000' },
        label: { type: String, default: null },
    },

    emits: ['update:modelValue'],

    computed: {
        hex() {
            const value = this.expand(this.modelValue);

            return value.slice(0, 7);
        },

        alpha() {
            const value = this.expand(this.modelValue);

            return value.length === 9 ? Math.round((parseInt(value.slice(7), 16) / 255) * 100) : 100;
        },
    },

    methods: {
        expand(value) {
            value = (value || '#000000').toLowerCase();

            if (/^#[0-9a-f]{3,4}$/.test(value)) {
                return '#' + value.slice(1).split('').map((c) => c + c).join('');
            }

            return /^#([0-9a-f]{6}|[0-9a-f]{8})$/.test(value) ? value : '#000000';
        },

        emit(hex, alpha) {
            const a = Math.max(0, Math.min(100, Number(alpha)));
            const suffix = a >= 100 ? '' : Math.round((a / 100) * 255).toString(16).padStart(2, '0');
            this.$emit('update:modelValue', hex + suffix);
        },

        typed(event) {
            const value = event.target.value.trim().toLowerCase();

            if (/^#([0-9a-f]{3}|[0-9a-f]{4}|[0-9a-f]{6}|[0-9a-f]{8})$/.test(value)) {
                this.$emit('update:modelValue', value);
            }
        },
    },
};
</script>

<template>
    <div class="si-color">
        <input type="color" :value="hex" :aria-label="label || __('Colour')" @input="emit($event.target.value, alpha)" />
        <input type="text" class="si-input si-color__hex" :value="modelValue" spellcheck="false" :aria-label="label || __('Colour')" @change="typed" />
        <input
            type="number"
            class="si-input si-color__alpha"
            min="0"
            max="100"
            :value="alpha"
            :aria-label="__('Opacity (%)')"
            :title="__('Opacity (%)')"
            @input="emit(hex, $event.target.value)"
        />
    </div>
</template>
