import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
            // Fontes empacotadas localmente via @fontsource (import no app.css).
            // NÃO usar o plugin bunny()/fonts: ele baixava de fonts.bunny.net no build
            // (dependência de rede → ECONNRESET no deploy de produção).
        }),
        tailwindcss(),
    ],
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
