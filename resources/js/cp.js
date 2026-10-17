import Templates from './pages/Templates.vue';
import Designer from './pages/Designer.vue';
import Images from './pages/Images.vue';
import SocialImagePreviewFieldtype from './components/SocialImagePreviewFieldtype.vue';
import SocialImagesRendererFieldtype from './components/SocialImagesRendererFieldtype.vue';

Statamic.booting(() => {
    Statamic.$inertia.register('social-images::Templates', Templates);
    Statamic.$inertia.register('social-images::Designer', Designer);
    Statamic.$inertia.register('social-images::Images', Images);
    Statamic.$components.register('social_image_preview-fieldtype', SocialImagePreviewFieldtype);
    Statamic.$components.register('social_images_renderer-fieldtype', SocialImagesRendererFieldtype);
});
