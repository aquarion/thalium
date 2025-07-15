import { defineConfig } from "vite";
import laravel from "laravel-vite-plugin";

import { resolve } from "path";

export default defineConfig({
    plugins: [
        laravel({
            input: [
                "resources/css/app.css",
                "resources/js/app.js",
                "resources/css/admin.css",
                "resources/js/admin.js",
                "resources/css/comingSoon.css",
                "resources/js/comingSoon.js",
            ],
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
