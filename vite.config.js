import { defineConfig } from "vite";
import laravel from "laravel-vite-plugin";

import { resolve } from "path";

export default defineConfig({
    server: {
        cors: true,
    },
    plugins: [
        laravel({
            input: ["resources/sass/app.scss", "resources/js/app.js"],
            refresh: true,
        }),
    ],
    resolve: {
        alias: {
            "~chartjs": resolve("node_modules/chartjs"),
            "~bootstrap": resolve("node_modules/bootstrap"),
            "~bootstrap-icons": resolve("node_modules/bootstrap-icons"),
            "~matter": resolve("node_modules/matter"),
        },
    },
});
