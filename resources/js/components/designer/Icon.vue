<!--
    The designer's icons: 16px line drawings in the current colour, so they
    sit with Statamic's own. Decorative; the button around each one has the
    label.
-->
<script>
const PATHS = {
    text: '<path d="M3.5 4V3h9v1M8 3v10M6.5 13h3"/>',
    image: '<rect x="2.5" y="3" width="11" height="10" rx="1.5"/><path d="M2.5 11l3-3 2.5 2.5 2-2 3.5 3.5"/><circle cx="10.5" cy="6" r="1"/>',
    rect: '<rect x="3" y="3.5" width="10" height="9" rx="1"/>',
    ellipse: '<circle cx="8" cy="8" r="5"/>',
    eye: '<path d="M1.5 8C2.7 5 5.3 3.5 8 3.5S13.3 5 14.5 8c-1.2 3-3.8 4.5-6.5 4.5S2.7 11 1.5 8z"/><circle cx="8" cy="8" r="2"/>',
    'eye-off': '<path d="M2 2l12 12M6.5 6.6A2 2 0 0 0 9.4 9.5M4.2 4.3C2.9 5.2 2 6.5 1.5 8c1.2 3 3.8 4.5 6.5 4.5 1.2 0 2.4-.3 3.4-.9M7 3.6c.3 0 .7-.1 1-.1 2.7 0 5.3 1.5 6.5 4.5-.3.8-.8 1.6-1.4 2.2"/>',
    lock: '<rect x="3.5" y="7" width="9" height="6.5" rx="1"/><path d="M5.5 7V5a2.5 2.5 0 0 1 5 0v2"/>',
    unlock: '<rect x="3.5" y="7" width="9" height="6.5" rx="1"/><path d="M5.5 7V5a2.5 2.5 0 0 1 4.9-.7"/>',
    duplicate: '<rect x="5.5" y="5.5" width="8" height="8" rx="1"/><path d="M10.5 5.5V3.5a1 1 0 0 0-1-1h-6a1 1 0 0 0-1 1v6a1 1 0 0 0 1 1h2"/>',
    trash: '<path d="M2.5 4.5h11M6 4.5V3h4v1.5M4 4.5l.7 9h6.6l.7-9M6.8 7v4.5M9.2 7v4.5"/>',
    close: '<path d="M4 4l8 8M12 4l-8 8"/>',
    up: '<path d="M8 13V3M4 7l4-4 4 4"/>',
    plus: '<path d="M8 3v10M3 8h10"/>',
    'align-left': '<path d="M2.5 3.5h11M2.5 6.5h7M2.5 9.5h11M2.5 12.5h7"/>',
    'align-center': '<path d="M2.5 3.5h11M4.5 6.5h7M2.5 9.5h11M4.5 12.5h7"/>',
    'align-right': '<path d="M2.5 3.5h11M6.5 6.5h7M2.5 9.5h11M6.5 12.5h7"/>',
    'align-top': '<path d="M2.5 2.5h11M6 5v8.5h4V5z"/>',
    'align-middle': '<path d="M2.5 8h11M6 4v8h4V4z"/>',
    'align-bottom': '<path d="M2.5 13.5h11M6 2.5V11h4V2.5z"/>',
};

export default {
    props: {
        name: { type: String, required: true },
    },

    computed: {
        paths() {
            return PATHS[this.name] ?? '';
        },
    },
};
</script>

<template>
    <svg class="si-icon" viewBox="0 0 16 16" aria-hidden="true" focusable="false" v-html="paths" />
</template>
