<!--
    The settings page's renderer check. PHP side: Fieldtypes\SocialImagesRenderer.
    The button posts to the renderer.check CP route, which renders a test
    image and says how it went.
-->
<script>
import { FieldtypeMixin } from '@statamic/cms';
import { Button } from '@statamic/cms/ui';

export default {
    mixins: [FieldtypeMixin],

    components: { Button },

    data() {
        return { checking: false, result: null };
    },

    methods: {
        async check() {
            this.checking = true;
            this.result = null;

            try {
                const { data } = await this.$axios.post(this.meta.check_url);
                this.result = data;
            } catch (error) {
                this.result = { ok: false, message: error.response?.data?.message || this.__('The check couldn’t run.'), fix: null };
            } finally {
                this.checking = false;
            }
        },
    },
};
</script>

<template>
    <div class="si-renderer">
        <p class="si-renderer__driver" v-html="__('Images are rendered with <code>:driver</code>. Change it with SOCIAL_IMAGES_RENDERER in .env.', { driver: meta.driver })" />
        <Button :text="checking ? __('Rendering a test image…') : __('Test')" :disabled="checking" icon="sync" @click="check" />
        <div v-if="result" class="si-renderer__result" :class="result.ok ? 'si-renderer__result--ok' : 'si-renderer__result--failed'" role="status">
            <p>{{ result.message }}</p>
            <p v-if="result.fix" class="si-renderer__fix">{{ result.fix }}</p>
        </div>
    </div>
</template>

<style scoped>
.si-renderer {
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    gap: 0.75rem;
}

.si-renderer__driver {
    font-size: 0.875rem;
}

.si-renderer__result {
    border-radius: 0.375rem;
    padding: 0.5rem 0.75rem;
    font-size: 0.875rem;
    border: 1px solid var(--si-line);
}

.si-renderer__result--ok {
    color: var(--si-ok);
}

.si-renderer__result--failed {
    color: var(--si-bad);
}

.si-renderer__fix {
    margin-top: 0.25rem;
    opacity: 0.85;
}
</style>
