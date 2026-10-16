/*
    The Social Images renderer: draws a designer template's layout into an
    element. The same file runs in the Control Panel's designer (imported by
    Vite) and in the page the screenshot is taken of (inlined by
    Rendering\Page, which strips the `export`s and exposes it as
    window.SocialImages), so what the designer shows is what's rendered.

    Everything is set through element.style properties and textContent,
    never as HTML or a CSS string, and the layout has been through
    Templates\Schema, so a template can't add markup or script.

    The page sets html[data-social-ready] once fonts and images have loaded
    and text has been fitted; the screenshot waits for it.

    Bump Rendering\Page::RUNTIME_VERSION when a change here would change how
    an existing template looks, so images re-render.
*/

const loadedFonts = new Map();

/**
 * The CSS background for a fill from the template (see Schema::fill()).
 */
export function fillCss(fill) {
    if (!fill) {
        return '';
    }

    if (fill.type === 'solid') {
        return fill.color;
    }

    const stops = (fill.stops || []).map((stop) => `${stop.color} ${stop.position}%`).join(', ');

    if (fill.type === 'linear') {
        return `linear-gradient(${fill.angle}deg, ${stops})`;
    }

    if (fill.type === 'radial') {
        return `radial-gradient(circle at ${fill.x}% ${fill.y}%, ${stops})`;
    }

    return '';
}

export function shadowCss(shadow) {
    return shadow ? `${shadow.x}px ${shadow.y}px ${shadow.blur}px ${shadow.color}` : '';
}

export function fontStack(family, category, prefix = '') {
    const generic = category === 'serif' ? 'serif' : category === 'monospace' ? 'monospace' : 'sans-serif';

    return `"${(prefix + String(family)).replace(/["\\]/g, '')}", ${generic}`;
}

/**
 * Load the fonts the page needs (each once), as FontFace objects.
 */
export async function loadFonts(fonts, doc = document, prefix = '') {
    const loads = (fonts || []).map((font) => {
        const key = [prefix + font.family, font.style, font.weight, font.unicode_range, font.src.length, font.src.slice(-40)].join('|');

        if (!loadedFonts.has(key)) {
            const descriptors = { style: font.style, weight: font.weight, display: 'block' };

            if (font.unicode_range) {
                descriptors.unicodeRange = font.unicode_range;
            }

            const face = new FontFace(prefix + font.family, `url(${font.src})`, descriptors);
            doc.fonts.add(face);
            loadedFonts.set(key, face.load().catch(() => null));
        }

        return loadedFonts.get(key);
    });

    await Promise.all(loads);
}

/**
 * Draw a layout into `root`, which becomes the canvas. `data.layers` holds
 * what the server resolved for each layer by id: { text }, { src } or
 * { hidden: true }. Resolves once fonts and images are in and text fitted.
 *
 * Options: `designer` keeps layers that would be hidden (faded instead);
 * `fontPrefix` names the fonts apart from the page's own (the Control
 * Panel has its own Inter, say).
 */
export async function render(root, layout, data = {}, options = {}) {
    const doc = root.ownerDocument;
    const layers = data.layers || {};
    const categories = data.categories || {};

    const prefix = options.fontPrefix || '';

    await loadFonts(data.fonts, doc, prefix);

    root.replaceChildren();
    Object.assign(root.style, {
        position: 'relative',
        width: `${layout.width}px`,
        height: `${layout.height}px`,
        overflow: 'hidden',
        background: fillCss(layout.background) || '#ffffff',
        // In the designer the canvas sits in the Control Panel, whose own
        // type settings (font features, spacing) would otherwise carry in.
        fontFeatureSettings: 'normal',
        fontVariationSettings: 'normal',
        fontVariant: 'normal',
        fontKerning: 'auto',
        letterSpacing: 'normal',
        wordSpacing: 'normal',
        lineHeight: 'normal',
        textTransform: 'none',
        textIndent: '0',
        textAlign: 'left',
        color: '#000000',
    });

    const texts = [];
    const images = [];

    for (const layer of layout.layers) {
        const resolved = layers[layer.id] || {};
        const unavailable = resolved.hidden || (layer.type === 'image' && !resolved.src && !options.designer);

        if (layer.hidden || (unavailable && !options.designer)) {
            continue;
        }

        const element = doc.createElement('div');
        element.dataset.layerId = layer.id;
        Object.assign(element.style, {
            position: 'absolute',
            left: `${layer.x}px`,
            top: `${layer.y}px`,
            width: `${layer.width}px`,
            height: `${layer.height}px`,
            transform: layer.rotate ? `rotate(${layer.rotate}deg)` : '',
            opacity: String(unavailable ? Math.min(layer.opacity, 0.25) : layer.opacity),
            boxSizing: 'border-box',
        });

        if (layer.type === 'text') {
            texts.push(drawText(element, layer, resolved.text ?? '', fontStack(layer.font.family, categories[layer.font.family], prefix), doc));
        } else if (layer.type === 'image') {
            const img = drawImage(element, layer, resolved.src, doc, options);

            if (img) {
                images.push(img);
            }
        } else {
            drawShape(element, layer);
        }

        root.appendChild(element);
    }

    await Promise.all(images.map((img) => (img.decode ? img.decode().catch(() => null) : null)));

    texts.forEach(fitText);
}

function drawText(element, layer, text, family, doc) {
    const inner = doc.createElement('div');
    inner.textContent = text;

    Object.assign(element.style, {
        display: 'flex',
        flexDirection: 'column',
        justifyContent: { top: 'flex-start', middle: 'center', bottom: 'flex-end' }[layer.valign],
        alignItems: layer.hug ? { left: 'flex-start', center: 'center', right: 'flex-end' }[layer.align] : 'stretch',
    });

    // A hugging layer (a "pill") puts its background round the text;
    // otherwise round the whole box.
    const boxed = layer.hug ? inner : element;
    Object.assign(boxed.style, {
        background: fillCss(layer.background),
        padding: layer.padding.map((side) => `${side}px`).join(' '),
        borderRadius: layer.radius ? `${layer.radius}px` : '',
        border: layer.border ? `${layer.border.width}px solid ${layer.border.color}` : '',
        boxShadow: shadowCss(layer.shadow),
        boxSizing: 'border-box',
    });

    Object.assign(inner.style, {
        fontFamily: family,
        fontWeight: String(layer.font.weight),
        fontStyle: layer.font.style,
        fontSize: `${layer.size}px`,
        lineHeight: String(layer.line_height),
        letterSpacing: `${layer.letter_spacing}em`,
        color: layer.color,
        textAlign: layer.align,
        textTransform: layer.transform === 'none' ? '' : layer.transform,
        textWrap: layer.balance ? 'balance' : '',
        // Words aren't broken while fitting, so a long one shrinks the text
        // rather than splitting; fitText breaks them only as a last resort.
        overflowWrap: 'normal',
        whiteSpace: 'pre-line',
        maxWidth: '100%',
        minHeight: '0',
        // Not clipped: a tight line height puts the last line's descenders
        // (g, y, p) below its box, and they'd be cut off.
        overflow: 'visible',
    });

    element.appendChild(inner);

    return { element, inner, layer };
}

/**
 * Step the size down until the text fits its box, in height, in the width
 * of its longest word, and in its maximum lines, but not below its
 * minimum size. Whatever still doesn't fit is cut at a whole line.
 */
export function fitText({ element, inner, layer }) {
    const available = () => {
        const style = getComputedStyle(element);

        return {
            width: element.clientWidth - parseFloat(style.paddingLeft) - parseFloat(style.paddingRight),
            height: element.clientHeight - parseFloat(style.paddingTop) - parseFloat(style.paddingBottom),
        };
    };

    // A hugging layer's padding and border are on the text itself.
    const extra = layer.hug ? layer.padding[0] + layer.padding[2] + (layer.border ? layer.border.width * 2 : 0) : 0;

    const lines = (size) => Math.round((inner.scrollHeight - extra) / (size * layer.line_height));

    const fits = (size) => {
        const box = available();

        return inner.scrollHeight <= box.height + 1
            && inner.scrollWidth <= inner.clientWidth + 1
            && (!layer.max_lines || lines(size) <= layer.max_lines);
    };

    const max = clamp(layer.size, 6, 400, 64);
    const min = clamp(layer.min_size, 6, max, 6);
    let size = max;
    inner.style.overflowWrap = 'normal';
    inner.style.fontSize = `${size}px`;

    for (let step = 0; size > min && !fits(size) && step < 400; step++) {
        size = Math.max(min, size - 2);
        inner.style.fontSize = `${size}px`;
    }

    if (!fits(size)) {
        inner.style.overflowWrap = 'break-word';
    }

    if (!fits(size)) {
        const box = available();
        const room = Math.max(1, Math.floor((box.height - extra) / (size * layer.line_height)));
        const clamp = layer.max_lines ? Math.min(layer.max_lines, room) : room;

        truncate(inner, (n) => lines(size) <= clamp && inner.scrollHeight <= box.height + 1);
    }

    return size;
}

/**
 * Cut text to as many whole words as pass `fits`, ending with an ellipsis.
 * Nothing is clipped, so the last line keeps its descenders and the next
 * line doesn't peek out under them.
 */
export function truncate(inner, fits) {
    const words = String(inner.textContent ?? '').split(/(\s+)/);
    let low = 0;
    let high = words.length;

    while (low < high) {
        const middle = Math.ceil((low + high) / 2);
        inner.textContent = words.slice(0, middle).join('').trimEnd() + '…';

        if (fits()) {
            low = middle;
        } else {
            high = middle - 1;
        }
    }

    inner.textContent = low ? words.slice(0, low).join('').trimEnd().replace(/[\s,;:.\-–—]+$/, '') + '…' : '…';
}

function clamp(value, low, high, fallback) {
    const number = Number(value);

    return Number.isFinite(number) ? Math.min(high, Math.max(low, number)) : fallback;
}

function drawImage(element, layer, src, doc, options) {
    Object.assign(element.style, {
        overflow: 'hidden',
        borderRadius: layer.radius ? `${layer.radius}px` : '',
        border: layer.border ? `${layer.border.width}px solid ${layer.border.color}` : '',
        boxShadow: shadowCss(layer.shadow),
        background: fillCss(layer.background),
    });

    if (!src) {
        if (options.designer) {
            Object.assign(element.style, {
                background: fillCss(layer.background) || 'repeating-linear-gradient(45deg, #e5e7eb 0 12px, #f3f4f6 12px 24px)',
            });
        }

        return null;
    }

    const img = doc.createElement('img');
    img.alt = '';
    img.src = src;
    img.draggable = false;
    Object.assign(img.style, {
        display: 'block',
        width: '100%',
        height: '100%',
        objectFit: layer.fit,
        filter: layer.grayscale ? 'grayscale(1)' : '',
    });

    element.appendChild(img);

    return img;
}

function drawShape(element, layer) {
    Object.assign(element.style, {
        background: fillCss(layer.fill),
        borderRadius: layer.shape === 'ellipse' ? '50%' : layer.radius ? `${layer.radius}px` : '',
        border: layer.border ? `${layer.border.width}px solid ${layer.border.color}` : '',
        boxShadow: shadowCss(layer.shadow),
        filter: layer.blur ? `blur(${layer.blur}px)` : '',
    });
}

/**
 * For code templates: fit every [data-fit] element (data-fit="64" is the
 * largest size, data-fit-min="28" the smallest, data-fit-lines="3" the most
 * lines) to its parent, once fonts and images have loaded, then mark the
 * page ready.
 */
export async function ready(doc = document) {
    await doc.fonts.ready;
    await Promise.all(Array.from(doc.images).map((img) => (img.decode ? img.decode().catch(() => null) : null)));

    doc.querySelectorAll('[data-fit]').forEach((inner) => {
        const size = parseFloat(inner.dataset.fit) || parseFloat(getComputedStyle(inner).fontSize);
        const lineHeight = parseFloat(getComputedStyle(inner).lineHeight) / parseFloat(getComputedStyle(inner).fontSize) || 1.2;

        fitText({
            element: inner.parentElement,
            inner,
            layer: {
                size,
                min_size: parseFloat(inner.dataset.fitMin) || Math.min(size, 16),
                max_lines: clamp(parseInt(inner.dataset.fitLines, 10) || 0, 0, 50, 0),
                line_height: lineHeight,
                hug: false,
                padding: [0, 0, 0, 0],
                border: null,
            },
        });
    });

    doc.documentElement.setAttribute('data-social-ready', '');
}
