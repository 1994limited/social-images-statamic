import { test } from 'node:test';
import assert from 'node:assert/strict';
import { resize, scaleLayers, snap } from './geometry.js';

const box = { x: 100, y: 100, width: 200, height: 100, rotate: 0 };

test('dragging the right edge widens the box and keeps its left side', () => {
    assert.deepEqual(resize(box, 1, 0, 50, 999), { x: 100, y: 100, width: 250, height: 100 });
});

test('dragging the top-left corner moves the corner and keeps the bottom-right', () => {
    assert.deepEqual(resize(box, -1, -1, -20, -10), { x: 80, y: 90, width: 220, height: 110 });
});

test('a corner can keep the proportions', () => {
    const next = resize(box, 1, 1, 100, 0, { keepRatio: true });
    assert.equal(next.width / next.height, 2);
});

test('a rotated box keeps its opposite edge in place', () => {
    const rotated = { ...box, rotate: 90 };
    // Rotated 90°, the box's right edge faces down: dragging down widens it.
    const next = resize(rotated, 1, 0, 0, 40);
    assert.equal(next.width, 240);
    assert.equal(next.height, 100);
    // The left edge's centre (rotated, at the top) hasn't moved.
    const before = [rotated.x + rotated.width / 2, rotated.y + rotated.height / 2 - rotated.width / 2];
    const after = [next.x + next.width / 2, next.y + next.height / 2 - next.width / 2];
    assert.deepEqual(after, before);
});

test('boxes never collapse', () => {
    assert.equal(resize(box, 1, 0, -500, 0).width, 4);
});

test('snapping to the canvas centre and another layer', () => {
    const canvas = { width: 1200, height: 630 };
    const result = snap({ x: 497, y: 52, width: 200, height: 100 }, canvas, [{ x: 0, y: 150, width: 10, height: 10 }]);
    assert.equal(result.x, 500);
    assert.equal(result.guides.x, 600);
    assert.equal(result.y, 50);
    assert.equal(result.guides.y, 150);
});

test('nothing snaps out of reach', () => {
    const result = snap({ x: 300, y: 300, width: 50, height: 50 }, { width: 1200, height: 630 }, []);
    assert.deepEqual(result, { x: 300, y: 300, guides: { x: null, y: null } });
});

test('layers scale to a new canvas, text by the smaller ratio', () => {
    const [layer] = scaleLayers(
        [{ type: 'text', x: 120, y: 63, width: 600, height: 315, size: 60, min_size: 30, padding: [10, 20, 10, 20] }],
        { width: 1200, height: 630 },
        { width: 1080, height: 1080 },
    );
    assert.deepEqual([layer.x, layer.width, layer.size, layer.min_size], [108, 540, 54, 27]);
    assert.equal(layer.height, 540);
    assert.deepEqual(layer.padding, [9, 18, 9, 18]);
});
