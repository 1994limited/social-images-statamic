import { test } from 'node:test';
import assert from 'node:assert/strict';
import { fillCss, fontStack, shadowCss } from './runtime.js';

test('solid fills are their colour', () => {
    assert.equal(fillCss({ type: 'solid', color: '#ff0000' }), '#ff0000');
    assert.equal(fillCss(null), '');
});

test('gradients list their stops', () => {
    assert.equal(
        fillCss({ type: 'linear', angle: 135, stops: [{ color: '#000', position: 0 }, { color: '#fff', position: 100 }] }),
        'linear-gradient(135deg, #000 0%, #fff 100%)',
    );
    assert.equal(
        fillCss({ type: 'radial', x: 20, y: 80, stops: [{ color: '#000', position: 0 }, { color: '#fff', position: 70 }] }),
        'radial-gradient(circle at 20% 80%, #000 0%, #fff 70%)',
    );
});

test('font stacks are quoted, prefixed and fall back by category', () => {
    assert.equal(fontStack('Playfair Display', 'serif'), '"Playfair Display", serif');
    assert.equal(fontStack('Inter', undefined, 'si-'), '"si-Inter", sans-serif');
    assert.equal(fontStack('Bad"Name\\', 'monospace'), '"BadName", monospace');
});

test('shadows', () => {
    assert.equal(shadowCss({ x: 0, y: 10, blur: 30, color: '#0003' }), '0px 10px 30px #0003');
    assert.equal(shadowCss(null), '');
});

import { fitText } from './runtime.js';

// A stand-in element whose text fits once the font is 40px or smaller.
function fake(fitsAt) {
    const style = {};
    const inner = {
        style,
        get scrollHeight() { return parseFloat(style.fontSize) > fitsAt ? 999 : 10; },
        get scrollWidth() { return 10; },
        get clientWidth() { return 100; },
    };
    const element = { clientWidth: 100, clientHeight: 100 };
    globalThis.getComputedStyle = () => ({ paddingLeft: '0', paddingRight: '0', paddingTop: '0', paddingBottom: '0' });

    return { element, inner };
}

const layer = (size, min) => ({ size, min_size: min, max_lines: 0, line_height: 1.2, hug: false, padding: [0, 0, 0, 0], border: null });

test('text shrinks until it fits, but not below its smallest size', () => {
    assert.equal(fitText({ ...fake(40), layer: layer(80, 20) }), 40);
    assert.equal(fitText({ ...fake(10), layer: layer(80, 30) }), 30);
});

test('silly sizes are clamped, so fitting always ends', () => {
    assert.equal(fitText({ ...fake(40), layer: layer(Infinity, 20) }), 40);
    assert.equal(fitText({ ...fake(40), layer: layer(1e20, 1e20) }), 400);
    assert.equal(fitText({ ...fake(40), layer: layer(NaN, NaN) }), 40);
});

import { truncate } from './runtime.js';

test('text that still does not fit is cut at a whole word with an ellipsis', () => {
    const inner = { textContent: 'A field guide to leaving, and coming back again' };
    truncate(inner, () => inner.textContent.length <= 26);
    assert.equal(inner.textContent, 'A field guide to leaving…');
});

test('a single word too long for anything leaves just the ellipsis', () => {
    const inner = { textContent: 'Supercalifragilistic' };
    truncate(inner, () => inner.textContent.length <= 3);
    assert.equal(inner.textContent, '…');
});

test('a slightly smaller size that saves a line is preferred', () => {
    const style = {};
    // Three lines at 68px and above, two at 60px and below; 20px per line.
    const inner = {
        style,
        get scrollHeight() { return parseFloat(style.fontSize) > 60 ? 3 * 68 * 1.1 : 2 * parseFloat(style.fontSize) * 1.1; },
        get scrollWidth() { return 10; },
        get clientWidth() { return 100; },
    };
    const element = { clientWidth: 500, clientHeight: 400 };
    globalThis.getComputedStyle = () => ({ paddingLeft: '0', paddingRight: '0', paddingTop: '0', paddingBottom: '0' });

    assert.equal(fitText({ element, inner, layer: { size: 68, min_size: 30, max_lines: 0, line_height: 1.1, hug: false, padding: [0, 0, 0, 0], border: null } }), 60);
    // Not when it'd mean shrinking more than 15%.
    assert.equal(fitText({ element, inner, layer: { size: 80, min_size: 30, max_lines: 0, line_height: 1.1, hug: false, padding: [0, 0, 0, 0], border: null } }), 80);
});
