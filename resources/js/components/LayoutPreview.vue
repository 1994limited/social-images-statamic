<!--
    A layout drawn by the renderer, with sample text (or an entry's), at the
    width of its box. Used for the starters, which aren't saved templates.
-->
<script>
import { render } from '@social-images-core/runtime/runtime.js';

export default {
    props: {
        layout: { type: Object, required: true },
        previewUrl: { type: String, required: true },
        entry: { type: String, default: null },
    },

    data() {
        return { scale: 0.25, observer: null };
    },

    async mounted() {
        this.observer = new ResizeObserver(([entry]) => {
            this.scale = entry.contentRect.width / this.layout.width;
        });
        this.observer.observe(this.$el);

        try {
            const { data } = await this.$axios.post(this.previewUrl, { layout: this.layout, entry: this.entry });
            await render(this.$refs.stage, data.layout, data.data, { fontPrefix: 'si-', placeholders: true });
        } catch (error) {
            // A preview that can't be drawn just stays empty.
        }
    },

    beforeUnmount() {
        this.observer?.disconnect();
    },
};
</script>

<template>
    <div class="si-layout-preview" :style="{ aspectRatio: `${layout.width} / ${layout.height}` }">
        <div ref="stage" :style="{ transform: `scale(${scale})` }" class="si-layout-preview__stage" />
    </div>
</template>

<style scoped>
.si-layout-preview {
    position: relative;
    overflow: hidden;
    background: #f3f4f6;
}

.si-layout-preview__stage {
    position: absolute;
    top: 0;
    left: 0;
    transform-origin: 0 0;
    pointer-events: none;
}
</style>
