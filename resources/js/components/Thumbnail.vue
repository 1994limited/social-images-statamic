<!--
    A template's real page (its thumbnail route), shown at the width of its
    box. The frame is sandboxed with scripts but no same-origin access, and
    the page has a strict Content-Security-Policy.
-->
<script>
export default {
    props: {
        src: { type: String, required: true },
        width: { type: Number, default: 1200 },
        height: { type: Number, default: 630 },
    },

    data() {
        return { scale: 0.25, observer: null };
    },

    mounted() {
        this.observer = new ResizeObserver(([entry]) => {
            this.scale = entry.contentRect.width / this.width;
        });
        this.observer.observe(this.$el);
    },

    beforeUnmount() {
        this.observer?.disconnect();
    },
};
</script>

<template>
    <div class="si-thumb" :style="{ aspectRatio: `${width} / ${height}` }">
        <iframe
            :src="src"
            sandbox="allow-scripts"
            loading="lazy"
            tabindex="-1"
            aria-hidden="true"
            :style="{ width: `${width}px`, height: `${height}px`, transform: `scale(${scale})` }"
        />
    </div>
</template>

<style scoped>
.si-thumb {
    position: relative;
    overflow: hidden;
    background: #f3f4f6;
}

.si-thumb iframe {
    position: absolute;
    top: 0;
    left: 0;
    border: 0;
    transform-origin: 0 0;
    pointer-events: none;
}
</style>
