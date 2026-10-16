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
