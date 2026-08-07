import Alpine from 'alpinejs';

/**
 * プロジェクトのお気に入り
 *
 * いいねと同じで、押した瞬間に画面へ反映して通信は裏で走らせる。
 * 失敗したら元の状態に戻す。
 */
Alpine.store('favorites', {
    ids: [],

    has(id) {
        return this.ids.includes(id);
    },

    set(id, isFavorite) {
        this.ids = isFavorite
            ? [...new Set([...this.ids, id])]
            : this.ids.filter((current) => current !== id);
    },

    async toggle(id, url) {
        const before = this.has(id);

        this.set(id, ! before);

        try {
            const response = await fetch(url, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
                    Accept: 'application/json',
                },
            });

            if (! response.ok) {
                throw new Error(response.statusText);
            }

            const { is_favorite: isFavorite } = await response.json();

            this.set(id, isFavorite);
        } catch {
            this.set(id, before);
        }
    },
});

window.Alpine = Alpine;

Alpine.start();
