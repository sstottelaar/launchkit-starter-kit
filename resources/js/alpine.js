import Alpine from "alpinejs";
import collapse from "@alpinejs/collapse";
import focus from "@alpinejs/focus";
import axios from "axios";
import Fuse from "fuse.js";

Alpine.plugin(collapse);
Alpine.plugin(focus);
window.Alpine = Alpine;

document.addEventListener("alpine:init", () => {
    Alpine.store("mobileMenu", {
        open: false,

        toggle() {
            this.open = !this.open;
        },
    });

    Alpine.store("searchOverlay", {
        open: false,

        toggle() {
            this.open = !this.open;
        },
    });
});

Alpine.data("search", () => ({
    fuse: null,
    search: "",
    results: [],

    init() {
        axios.get(`/search/data.json`).then((response) => {
            this.fuse = new Fuse(response.data, {
                includeScore: true,
                threshold: 0.4,
                keys: ["title"],
            });
        });

        this.$watch("search", (value) => {
            if (!this.fuse || !value) {
                this.results = [];
                return;
            }

            const data = this.fuse
                .search(value)
                .sort((a, b) => a.score - b.score)
                .slice(0, 10)
                .map(({ item }) => ({
                    title: item.title,
                    url: item.url,
                    blueprint: item.blueprint,
                }));

            this.results = data;
        });
    },
}));

Alpine.start();
