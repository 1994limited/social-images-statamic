/*
    New layers and the bits of a layer that decide what the server has to
    resolve for it (its text, picture or condition), so the designer only
    asks again when one of those changes.
*/

export function uid() {
    return Math.random().toString(36).slice(2, 10);
}

export function newText(layout) {
    return {
        id: uid(),
        type: 'text',
        name: 'Text',
        text: '{title}',
        x: Math.round(layout.width * 0.08),
        y: Math.round(layout.height * 0.3),
        width: Math.round(layout.width * 0.84),
        height: Math.round(layout.height * 0.4),
        rotate: 0,
        opacity: 1,
        hidden: false,
        locked: false,
        when: null,
        font: { family: 'Inter', weight: 700, style: 'normal' },
        size: 72,
        min_size: 32,
        line_height: 1.1,
        letter_spacing: 0,
        color: '#111111',
        align: 'left',
        valign: 'middle',
        transform: 'none',
        max_lines: 0,
        balance: true,
        hug: false,
        background: null,
        padding: [0, 0, 0, 0],
        radius: 0,
        border: null,
        shadow: null,
    };
}

export function newImage(layout) {
    return {
        id: uid(),
        type: 'image',
        name: 'Image',
        source: ['field:featured_image'],
        x: Math.round(layout.width * 0.55),
        y: Math.round(layout.height * 0.1),
        width: Math.round(layout.width * 0.4),
        height: Math.round(layout.height * 0.8),
        rotate: 0,
        opacity: 1,
        hidden: false,
        locked: false,
        when: null,
        fit: 'cover',
        radius: 16,
        border: null,
        shadow: null,
        grayscale: false,
        background: null,
    };
}

export function newShape(layout, shape = 'rect') {
    const size = Math.round(Math.min(layout.width, layout.height) * 0.3);

    return {
        id: uid(),
        type: 'shape',
        name: shape === 'ellipse' ? 'Circle' : 'Rectangle',
        shape,
        x: Math.round((layout.width - size) / 2),
        y: Math.round((layout.height - size) / 2),
        width: size,
        height: size,
        rotate: 0,
        opacity: 1,
        hidden: false,
        locked: false,
        when: null,
        fill: { type: 'solid', color: '#e5e7eb' },
        radius: 0,
        border: null,
        shadow: null,
        blur: 0,
    };
}

/**
 * What decides a layer's resolved data. Size is left out: a resized image
 * keeps showing its old crop until the new one arrives.
 */
export function bindingKey(layer) {
    if (layer.type === 'text') {
        return JSON.stringify([layer.text, layer.when, layer.font]);
    }

    if (layer.type === 'image') {
        return JSON.stringify([layer.source, layer.when]);
    }

    return JSON.stringify([layer.when]);
}

/**
 * What decides when to ask the server again: every layer's binding, and
 * image sizes (for the crop).
 */
export function fetchKey(layout) {
    return JSON.stringify(layout.layers.map((layer) => [
        layer.id,
        bindingKey(layer),
        layer.type === 'image' ? [Math.round(layer.width / 40), Math.round(layer.height / 40), layer.fit] : null,
    ]));
}
