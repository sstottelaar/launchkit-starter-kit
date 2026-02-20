import Alpine from "alpinejs";
import collapse from "@alpinejs/collapse";
import focus from "@alpinejs/focus";

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
    search: "",
    results: [],
    loading: false,
    searchTimeout: null,

    init() {
        this.$watch("search", (value) => {
            const query = value?.trim() ?? "";
            if (query.length < 2) {
                clearTimeout(this.searchTimeout);
                this.results = [];
                this.loading = false;
                return;
            }

            this.loading = true;
            clearTimeout(this.searchTimeout);
            this.searchTimeout = setTimeout(() => {
                this.performSearch(query);
            }, 200);
        });
    },

    async performSearch(query) {
        this.loading = true;
        try {
            const response = await fetch(
                `/search.json?${new URLSearchParams({ q: query })}`
            );
            this.results = response.ok ? await response.json() : [];
        } catch {
            this.results = [];
        } finally {
            this.loading = false;
        }
    },
}));

Alpine.start();
