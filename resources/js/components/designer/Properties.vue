<!--
    The selected layer's settings, or the layout's background when nothing
    is selected. Every change is emitted as a patch; the page keeps the
    history.
-->
<script>
import ColorInput from './ColorInput.vue';
import FillInput from './FillInput.vue';
import SourceList from './SourceList.vue';
import TokenMenu from './TokenMenu.vue';

export default {
    components: { ColorInput, FillInput, SourceList, TokenMenu },

    props: {
        layer: { type: Object, default: null },
        layout: { type: Object, required: true },
        fonts: { type: Array, required: true },
        collections: { type: Array, required: true },
        urls: { type: Object, required: true },
    },

    emits: ['update', 'update-layout', 'add-font', 'remove', 'duplicate'],

    computed: {
        font() {
            return this.fonts.find((font) => font.family === this.layer?.font?.family) ?? null;
        },

        weights() {
            return this.font?.weights?.length ? this.font.weights : [400, 700];
        },
    },

    methods: {
        set(patch) {
            this.$emit('update', patch);
        },

        number(key, event) {
            const value = parseFloat(event.target.value);

            if (!Number.isNaN(value)) {
                this.set({ [key]: value });
            }
        },

        setFont(patch) {
            const font = { ...this.layer.font, ...patch };

            if (patch.family === '__add') {
                this.$emit('add-font');

                return;
            }

            const family = this.fonts.find((f) => f.family === font.family);

            if (family && family.weights.length && !family.weights.includes(font.weight)) {
                font.weight = family.weights.reduce((best, w) => (Math.abs(w - font.weight) < Math.abs(best - font.weight) ? w : best), family.weights[0]);
            }

            this.set({ font });
        },

        insertToken(token) {
            const textarea = this.$refs.text;
            const value = this.layer.text;
            const at = textarea ? textarea.selectionStart : value.length;
            const end = textarea ? textarea.selectionEnd : value.length;

            this.set({ text: value.slice(0, at) + `{${token}}` + value.slice(end) });
        },

        setPadding(index, event) {
            const padding = [...this.layer.padding];
            padding[index] = parseFloat(event.target.value) || 0;
            this.set({ padding });
        },

        toggleBorder(on) {
            this.set({ border: on ? { width: 1, color: '#111111' } : null });
        },

        toggleShadow(on) {
            this.set({ shadow: on ? { x: 0, y: 12, blur: 32, color: '#00000033' } : null });
        },

        focusText() {
            this.$refs.text?.focus();
        },
    },
};
</script>

<template>
    <div class="si-props">
        <template v-if="!layer">
            <h3 class="si-props__title">{{ __('Background') }}</h3>
            <FillInput :model-value="layout.background" :allow-none="false" @update:model-value="$emit('update-layout', { background: $event })" />
            <p class="si-hint mt-4">{{ __('Select a layer to change it, or add one from the left.') }}</p>
        </template>

        <template v-else>
            <div class="si-props__head">
                <input class="si-input si-props__name" :value="layer.name" :aria-label="__('Layer name')" @input="set({ name: $event.target.value })" />
                <button type="button" class="si-icon-button" :title="__('Duplicate')" :aria-label="__('Duplicate')" @click="$emit('duplicate')">⧉</button>
                <button type="button" class="si-icon-button" :title="__('Delete')" :aria-label="__('Delete')" @click="$emit('remove')">🗑</button>
            </div>

            <!-- Text -->
            <section v-if="layer.type === 'text'" class="si-props__section">
                <div class="flex items-center justify-between">
                    <label class="si-label" for="si-text">{{ __('Text') }}</label>
                    <TokenMenu :collections="collections" @insert="insertToken" />
                </div>
                <textarea id="si-text" ref="text" class="si-input" rows="3" :value="layer.text" @input="set({ text: $event.target.value })" />
                <p class="si-hint">{{ __('Fields go in braces: {title}. Use ?? for a fallback: {subtitle ?? "Read more"}.') }}</p>

                <label class="si-label">{{ __('Font') }}</label>
                <select class="si-input" :value="layer.font.family" @change="setFont({ family: $event.target.value })">
                    <optgroup v-if="fonts.some((f) => f.source !== 'bundled')" :label="__('Your fonts')">
                        <option v-for="f in fonts.filter((f) => f.source !== 'bundled')" :key="f.family" :value="f.family">{{ f.family }}</option>
                    </optgroup>
                    <optgroup :label="__('Included')">
                        <option v-for="f in fonts.filter((f) => f.source === 'bundled')" :key="f.family" :value="f.family">{{ f.family }}</option>
                    </optgroup>
                    <option value="__add">{{ __('Add a font…') }}</option>
                </select>
                <div class="si-row">
                    <select class="si-input" :value="layer.font.weight" :aria-label="__('Weight')" @change="setFont({ weight: Number($event.target.value) })">
                        <option v-for="w in weights" :key="w" :value="w">{{ w }}</option>
                    </select>
                    <label class="si-check">
                        <input type="checkbox" :checked="layer.font.style === 'italic'" :disabled="font && !font.italic" @change="setFont({ style: $event.target.checked ? 'italic' : 'normal' })" />
                        {{ __('Italic') }}
                    </label>
                </div>

                <div class="si-grid2">
                    <label class="si-num"><span>{{ __('Size') }}</span><input type="number" class="si-input" :value="layer.size" min="6" @input="number('size', $event)" /></label>
                    <label class="si-num"><span>{{ __('Smallest') }}</span><input type="number" class="si-input" :value="layer.min_size" min="6" @input="number('min_size', $event)" /></label>
                    <label class="si-num"><span>{{ __('Line height') }}</span><input type="number" class="si-input" step="0.05" :value="layer.line_height" @input="number('line_height', $event)" /></label>
                    <label class="si-num"><span>{{ __('Spacing') }}</span><input type="number" class="si-input" step="0.01" :value="layer.letter_spacing" @input="number('letter_spacing', $event)" /></label>
                    <label class="si-num"><span>{{ __('Max lines') }}</span><input type="number" class="si-input" min="0" :value="layer.max_lines" @input="number('max_lines', $event)" /></label>
                </div>
                <p class="si-hint">{{ __('Text shrinks from Size down to Smallest to fit its box. Max lines 0 is no limit.') }}</p>

                <label class="si-label">{{ __('Colour') }}</label>
                <ColorInput :model-value="layer.color" @update:model-value="set({ color: $event })" />

                <div class="si-row">
                    <div class="si-segment" role="group" :aria-label="__('Align')">
                        <button v-for="a in ['left', 'center', 'right']" :key="a" type="button" :class="{ 'is-on': layer.align === a }" :title="__(a)" @click="set({ align: a })">{{ { left: '⇤', center: '↔', right: '⇥' }[a] }}</button>
                    </div>
                    <div class="si-segment" role="group" :aria-label="__('Vertical align')">
                        <button v-for="a in ['top', 'middle', 'bottom']" :key="a" type="button" :class="{ 'is-on': layer.valign === a }" :title="__(a)" @click="set({ valign: a })">{{ { top: '⤒', middle: '↕', bottom: '⤓' }[a] }}</button>
                    </div>
                </div>

                <div class="si-row">
                    <select class="si-input" :value="layer.transform" :aria-label="__('Case')" @change="set({ transform: $event.target.value })">
                        <option value="none">{{ __('As written') }}</option>
                        <option value="uppercase">{{ __('UPPERCASE') }}</option>
                        <option value="lowercase">{{ __('lowercase') }}</option>
                        <option value="capitalize">{{ __('Capitalise') }}</option>
                    </select>
                    <label class="si-check"><input type="checkbox" :checked="layer.balance" @change="set({ balance: $event.target.checked })" />{{ __('Balance lines') }}</label>
                </div>

                <h4 class="si-props__subtitle">{{ __('Box') }}</h4>
                <label class="si-check"><input type="checkbox" :checked="layer.hug" @change="set({ hug: $event.target.checked })" />{{ __('Fit the box to the text (a label or pill)') }}</label>
                <FillInput :model-value="layer.background" @update:model-value="set({ background: $event })" />
                <div class="si-grid4">
                    <label v-for="(side, i) in ['Top', 'Right', 'Bottom', 'Left']" :key="side" class="si-num"><span>{{ __(side) }}</span><input type="number" class="si-input" min="0" :value="layer.padding[i]" @input="setPadding(i, $event)" /></label>
                </div>
            </section>

            <!-- Image -->
            <section v-if="layer.type === 'image'" class="si-props__section">
                <label class="si-label">{{ __('Picture') }}</label>
                <SourceList :model-value="layer.source" :collections="collections" :upload-url="urls.upload" :upload-show-url="urls.upload_show" @update:model-value="set({ source: $event })" />
                <div class="si-row">
                    <select class="si-input" :value="layer.fit" :aria-label="__('Fit')" @change="set({ fit: $event.target.value })">
                        <option value="cover">{{ __('Fill the box (crop)') }}</option>
                        <option value="contain">{{ __('Fit inside the box') }}</option>
                    </select>
                    <label class="si-check"><input type="checkbox" :checked="layer.grayscale" @change="set({ grayscale: $event.target.checked })" />{{ __('Greyscale') }}</label>
                </div>
                <p class="si-hint">{{ __('Cropping keeps the asset’s focal point in view. Without a picture, the layer is left out.') }}</p>
                <label class="si-label">{{ __('Background') }}</label>
                <FillInput :model-value="layer.background" @update:model-value="set({ background: $event })" />
            </section>

            <!-- Shape -->
            <section v-if="layer.type === 'shape'" class="si-props__section">
                <div class="si-segment" role="group" :aria-label="__('Shape')">
                    <button type="button" :class="{ 'is-on': layer.shape === 'rect' }" @click="set({ shape: 'rect' })">{{ __('Rectangle') }}</button>
                    <button type="button" :class="{ 'is-on': layer.shape === 'ellipse' }" @click="set({ shape: 'ellipse' })">{{ __('Ellipse') }}</button>
                </div>
                <label class="si-label">{{ __('Fill') }}</label>
                <FillInput :model-value="layer.fill" @update:model-value="set({ fill: $event })" />
                <label class="si-num"><span>{{ __('Blur') }}</span><input type="number" class="si-input" min="0" :value="layer.blur" @input="number('blur', $event)" /></label>
            </section>

            <!-- Common: corners, border, shadow -->
            <section class="si-props__section">
                <label v-if="layer.type !== 'shape' || layer.shape === 'rect'" class="si-num"><span>{{ __('Corner radius') }}</span><input type="number" class="si-input" min="0" :value="layer.radius" @input="number('radius', $event)" /></label>

                <label class="si-check"><input type="checkbox" :checked="!!layer.border" @change="toggleBorder($event.target.checked)" />{{ __('Border') }}</label>
                <div v-if="layer.border" class="si-row">
                    <input type="number" class="si-input si-input--narrow" min="0" :value="layer.border.width" :aria-label="__('Border width')" @input="set({ border: { ...layer.border, width: Number($event.target.value) } })" />
                    <ColorInput :model-value="layer.border.color" @update:model-value="set({ border: { ...layer.border, color: $event } })" />
                </div>

                <label class="si-check"><input type="checkbox" :checked="!!layer.shadow" @change="toggleShadow($event.target.checked)" />{{ __('Shadow') }}</label>
                <template v-if="layer.shadow">
                    <div class="si-grid4">
                        <label v-for="key in ['x', 'y', 'blur']" :key="key" class="si-num"><span>{{ key }}</span><input type="number" class="si-input" :value="layer.shadow[key]" @input="set({ shadow: { ...layer.shadow, [key]: Number($event.target.value) } })" /></label>
                    </div>
                    <ColorInput :model-value="layer.shadow.color" @update:model-value="set({ shadow: { ...layer.shadow, color: $event } })" />
                </template>
            </section>

            <!-- Position -->
            <section class="si-props__section">
                <h4 class="si-props__subtitle">{{ __('Position') }}</h4>
                <div class="si-grid2">
                    <label v-for="key in ['x', 'y', 'width', 'height']" :key="key" class="si-num"><span>{{ { x: 'X', y: 'Y', width: __('Width'), height: __('Height') }[key] }}</span><input type="number" class="si-input" :value="layer[key]" @input="number(key, $event)" /></label>
                    <label class="si-num"><span>{{ __('Rotate') }}</span><input type="number" class="si-input" :value="layer.rotate" @input="number('rotate', $event)" /></label>
                    <label class="si-num"><span>{{ __('Opacity %') }}</span><input type="number" class="si-input" min="0" max="100" :value="Math.round(layer.opacity * 100)" @input="set({ opacity: Math.max(0, Math.min(100, Number($event.target.value))) / 100 })" /></label>
                </div>
                <div class="si-row">
                    <button type="button" class="si-link" @click="set({ x: Math.round((layout.width - layer.width) / 2) })">{{ __('Centre across') }}</button>
                    <button type="button" class="si-link" @click="set({ y: Math.round((layout.height - layer.height) / 2) })">{{ __('Centre down') }}</button>
                </div>
            </section>

            <!-- Condition -->
            <section class="si-props__section">
                <label class="si-label" for="si-when">{{ __('Show only when') }}</label>
                <input id="si-when" class="si-input font-mono" :value="layer.when" placeholder="subtitle ?? excerpt" @input="set({ when: $event.target.value || null })" />
                <p class="si-hint">{{ __('A field (or fields, with ??) that must have a value. Empty: always shown. Text with no value is left out anyway.') }}</p>
            </section>
        </template>
    </div>
</template>
