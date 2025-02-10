import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    plugins: [laravel(['resources/js/app.js'])],
    server: {
        host: '127.0.0.1',
        port: 5173,
        cors: {
            origin: 'https://joshcreative.test',  // Allow only this origin
            credentials: true
        }
    }
});
