<!--
    The designer's canvas: the layout drawn by the renderer (the same one
    the screenshot uses), scaled to fit, with a layer you can select, drag,
    resize and snap on top.

    While dragging, only the dragged layer's box moves; the full render runs
    when the drag ends (so text is refitted once, not on every pixel).
-->
<script>
import { render } from '../../../runtime/runtime.js';
import { resize, snap } from '../../designer/geometry.js';

const HANDLES = [
    [-1, -1], [0, -1], [1, -1],
    [-1, 0], [1, 0],
    [-1, 1], [0, 1], [1, 1],
];

export default {
    props: {
        layout: { type: Object, required: true },
        data: { type: Object, required: true },
        selected: { type: String, default: null },
    },

    emits: ['select', 'change', 'commit', 'edit'],

    data() {
        return {
            scale: 0.5,
            observer: null,
            drag: null,
            guides: { x: null, y: null },
            frame: null,
            rendering: false,
        };
    },

    computed: {
        handles: () => HANDLES,

        boxes() {
            return this.layout.layers.filter((layer) => !layer.hidden);
        },

        selectedLayer() {
            return this.layout.layers.find((layer) => layer.id === this.selected) ?? null;
        },
    },

    watch: {
        layout: { deep: true, handler: 'schedule' },
        data: { deep: true, handler: 'schedule' },
    },

    mounted() {
        this.observer = new ResizeObserver(() => this.fit());
        this.observer.observe(this.$refs.viewport);
        this.fit();
        this.draw();
    },

    beforeUnmount() {
        this.observer?.disconnect();
        cancelAnimationFrame(this.frame);
        this.stopDrag();
    },

    methods: {
        fit() {
            const viewport = this.$refs.viewport;

            if (!viewport) {
                return;
            }

            const padding = 48;
            this.scale = Math.min(
                (viewport.clientWidth - padding) / this.layout.width,
                (viewport.clientHeight - padding) / this.layout.height,
                1,
            );
        },

        schedule() {
            if (this.drag) {
                return;
            }

            cancelAnimationFrame(this.frame);
            this.frame = requestAnimationFrame(() => this.draw());
        },

        async draw() {
            this.fit();
            await render(this.$refs.stage, this.layout, this.data, { designer: true, fontPrefix: 'si-' });
        },

        boxStyle(layer) {
            return {
                left: `${layer.x * this.scale}px`,
                top: `${layer.y * this.scale}px`,
                width: `${layer.width * this.scale}px`,
                height: `${layer.height * this.scale}px`,
                transform: layer.rotate ? `rotate(${layer.rotate}deg)` : '',
            };
        },

        pointerDown(event, layer) {
            if (event.button !== 0) {
                return;
            }

            this.$emit('select', layer.id);

            if (layer.locked) {
                return;
            }

            this.startDrag(event, layer, null);
        },

        handleDown(event, handle) {
            event.stopPropagation();
            this.startDrag(event, this.selectedLayer, handle);
        },

        startDrag(event, layer, handle) {
            event.preventDefault();

            this.drag = {
                id: layer.id,
                handle,
                startX: event.clientX,
                startY: event.clientY,
                box: { x: layer.x, y: layer.y, width: layer.width, height: layer.height, rotate: layer.rotate },
                moved: false,
            };

            window.addEventListener('pointermove', this.pointerMove);
            window.addEventListener('pointerup', this.pointerUp);
        },

        pointerMove(event) {
            const drag = this.drag;

            if (!drag) {
                return;
            }

            const dx = (event.clientX - drag.startX) / this.scale;
            const dy = (event.clientY - drag.startY) / this.scale;

            if (!drag.moved && Math.abs(dx) + Math.abs(dy) < 2) {
                return;
            }

            drag.moved = true;
            let patch;

            if (drag.handle) {
                patch = resize(drag.box, drag.handle[0], drag.handle[1], dx, dy, { keepRatio: event.shiftKey });
                this.guides = { x: null, y: null };
            } else {
                const moved = { ...drag.box, x: drag.box.x + dx, y: drag.box.y + dy };

                if (event.altKey || drag.box.rotate) {
                    patch = { x: Math.round(moved.x), y: Math.round(moved.y) };
                    this.guides = { x: null, y: null };
                } else {
                    const others = this.boxes.filter((layer) => layer.id !== drag.id && !layer.rotate);
                    const snapped = snap(moved, this.layout, others, 6 / this.scale);
                    patch = { x: snapped.x, y: snapped.y };
                    this.guides = snapped.guides;
                }
            }

            this.$emit('change', drag.id, patch);
            this.moveRendered(drag.id, patch);
        },

        /**
         * Move the drawn layer itself while dragging, without a full render.
         */
        moveRendered(id, patch) {
            const element = this.$refs.stage?.querySelector(`[data-layer-id="${CSS.escape(id)}"]`);

            if (!element) {
                return;
            }

            for (const key of ['x', 'y', 'width', 'height']) {
                if (patch[key] !== undefined) {
                    element.style[{ x: 'left', y: 'top', width: 'width', height: 'height' }[key]] = `${patch[key]}px`;
                }
            }
        },

        pointerUp() {
            const moved = this.drag?.moved;
            this.stopDrag();
            this.guides = { x: null, y: null };

            if (moved) {
                this.$emit('commit');
                this.draw();
            }
        },

        stopDrag() {
            this.drag = null;
            window.removeEventListener('pointermove', this.pointerMove);
            window.removeEventListener('pointerup', this.pointerUp);
        },

        backgroundDown(event) {
            if (event.target === event.currentTarget || event.target === this.$refs.viewport) {
                this.$emit('select', null);
            }
        },

        cursor(handle) {
            const [x, y] = handle;

            return x === 0 ? 'ns-resize' : y === 0 ? 'ew-resize' : x === y ? 'nwse-resize' : 'nesw-resize';
        },
    },
};
</script>

<template>
    <div ref="viewport" class="si-canvas" @pointerdown="backgroundDown">
        <div class="si-canvas__page" :style="{ width: `${layout.width * scale}px`, height: `${layout.height * scale}px` }" @pointerdown.self="$emit('select', null)">
            <div ref="stage" class="si-canvas__stage" :style="{ transform: `scale(${scale})` }" />

            <div class="si-canvas__overlay" @pointerdown.self="$emit('select', null)">
                <div
                    v-for="layer in boxes"
                    :key="layer.id"
                    class="si-canvas__box"
                    :class="{ 'is-selected': layer.id === selected, 'is-locked': layer.locked }"
                    :style="boxStyle(layer)"
                    :title="layer.name"
                    @pointerdown="pointerDown($event, layer)"
                    @dblclick="$emit('edit', layer.id)"
                >
                    <template v-if="layer.id === selected && !layer.locked">
                        <span
                            v-for="handle in handles"
                            :key="handle.join()"
                            class="si-canvas__handle"
                            :style="{ left: `${(handle[0] + 1) * 50}%`, top: `${(handle[1] + 1) * 50}%`, cursor: cursor(handle) }"
                            @pointerdown="handleDown($event, handle)"
                        />
                    </template>
                </div>

                <div v-if="guides.x !== null" class="si-canvas__guide si-canvas__guide--x" :style="{ left: `${guides.x * scale}px` }" />
                <div v-if="guides.y !== null" class="si-canvas__guide si-canvas__guide--y" :style="{ top: `${guides.y * scale}px` }" />
            </div>
        </div>
    </div>
</template>

<style scoped>
.si-canvas {
    position: relative;
    display: flex;
    align-items: center;
    justify-content: center;
    height: 100%;
    min-height: 420px;
    overflow: hidden;
    background-color: var(--si-canvas);
    background-image: radial-gradient(var(--si-dots) 1px, transparent 1px);
    background-size: 16px 16px;
    user-select: none;
}

.si-canvas__page {
    position: relative;
    box-shadow: 0 1px 2px rgb(0 0 0 / 0.1), 0 12px 32px -8px rgb(0 0 0 / 0.2);
}

.si-canvas__stage {
    position: absolute;
    top: 0;
    left: 0;
    transform-origin: 0 0;
    pointer-events: none;
}

.si-canvas__overlay {
    position: absolute;
    inset: 0;
}

.si-canvas__box {
    position: absolute;
    cursor: move;
    outline: 1px solid transparent;
}

.si-canvas__box:hover {
    outline-color: rgb(59 130 246 / 0.5);
}

.si-canvas__box.is-selected {
    outline: 1.5px solid #3b82f6;
}

.si-canvas__box.is-locked {
    cursor: default;
}

.si-canvas__handle {
    position: absolute;
    width: 10px;
    height: 10px;
    margin: -5px 0 0 -5px;
    background: #fff;
    border: 1.5px solid #3b82f6;
    border-radius: 2px;
}

.si-canvas__guide {
    position: absolute;
    pointer-events: none;
    background: #ec4899;
}

.si-canvas__guide--x {
    top: 0;
    bottom: 0;
    width: 1px;
}

.si-canvas__guide--y {
    left: 0;
    right: 0;
    height: 1px;
}
</style>
