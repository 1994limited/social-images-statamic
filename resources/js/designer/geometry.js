/*
    The designer's geometry: resizing a (possibly rotated) layer from one of
    its handles, and snapping a moving layer to the canvas and to the other
    layers. Positions are a layer's box before rotation; CSS rotates it
    about its centre, as the renderer does.
*/

const rad = (deg) => (deg * Math.PI) / 180;

function rotate([x, y], deg) {
    const a = rad(deg);

    return [x * Math.cos(a) - y * Math.sin(a), x * Math.sin(a) + y * Math.cos(a)];
}

/**
 * The box after dragging handle (hx, hy) — each -1, 0 or 1 — by (dx, dy)
 * canvas pixels. The opposite side or corner stays where it is. With
 * `keepRatio` a corner keeps the box's proportions.
 */
export function resize(box, hx, hy, dx, dy, { keepRatio = false, min = 4 } = {}) {
    const [lx, ly] = rotate([dx, dy], -(box.rotate || 0));

    let width = Math.max(min, box.width + hx * lx);
    let height = Math.max(min, box.height + hy * ly);

    if (keepRatio && hx && hy) {
        const scale = Math.max(width / box.width, height / box.height);
        width = Math.max(min, box.width * scale);
        height = Math.max(min, box.height * scale);
    }

    const centre = [box.x + box.width / 2, box.y + box.height / 2];
    const [ax, ay] = rotate([(-hx * box.width) / 2, (-hy * box.height) / 2], box.rotate || 0);
    const anchor = [centre[0] + ax, centre[1] + ay];
    const [bx, by] = rotate([(-hx * width) / 2, (-hy * height) / 2], box.rotate || 0);
    const next = [anchor[0] - bx, anchor[1] - by];

    return {
        x: round(next[0] - width / 2),
        y: round(next[1] - height / 2),
        width: round(width),
        height: round(height),
    };
}

/**
 * Snap a moving box's edges and centre to the canvas's and the other
 * layers', within `threshold` pixels. Returns the snapped position and the
 * guide lines to draw.
 */
export function snap(box, canvas, others, threshold = 6) {
    const xs = [0, canvas.width / 2, canvas.width];
    const ys = [0, canvas.height / 2, canvas.height];

    for (const other of others) {
        xs.push(other.x, other.x + other.width / 2, other.x + other.width);
        ys.push(other.y, other.y + other.height / 2, other.y + other.height);
    }

    const best = (start, size, targets) => {
        let found = null;

        for (const offset of [0, size / 2, size]) {
            for (const target of targets) {
                const distance = target - (start + offset);

                if (Math.abs(distance) <= threshold && (!found || Math.abs(distance) < Math.abs(found.distance))) {
                    found = { distance, line: target };
                }
            }
        }

        return found;
    };

    const sx = best(box.x, box.width, xs);
    const sy = best(box.y, box.height, ys);

    return {
        x: round(box.x + (sx ? sx.distance : 0)),
        y: round(box.y + (sy ? sy.distance : 0)),
        guides: { x: sx ? sx.line : null, y: sy ? sy.line : null },
    };
}

/**
 * A layout's layers moved and resized for another canvas size, to start a
 * new layout from the base one. Text sizes scale with the smaller ratio.
 */
export function scaleLayers(layers, from, to) {
    const sx = to.width / from.width;
    const sy = to.height / from.height;
    const s = Math.min(sx, sy);

    return layers.map((layer) => {
        const scaled = {
            ...JSON.parse(JSON.stringify(layer)),
            x: round(layer.x * sx),
            y: round(layer.y * sy),
            width: round(layer.width * sx),
            height: round(layer.height * sy),
        };

        if (layer.type === 'text') {
            scaled.size = round(layer.size * s);
            scaled.min_size = Math.min(scaled.size, round(layer.min_size * s));
            scaled.padding = layer.padding.map((side) => round(side * s));
        }

        return scaled;
    });
}

function round(value) {
    return Math.round(value * 10) / 10;
}
