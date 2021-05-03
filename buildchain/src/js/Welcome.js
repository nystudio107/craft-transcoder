import App from '@/vue/App.vue';
import { createApp } from 'vue';
import "vite/dynamic-import-polyfill";

import '@/css/app.pcss';

// App main
const main = async () => {
    // Async load the Vue 3 APIs we need from the Vue ESM
    // Create our vue instance
    const app = createApp(App);

    // Mount the app
    const root = app.mount('#app-container');

    return root;
};

// Execute async function
main().then( (root) => {
});
