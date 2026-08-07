import Alpine from 'alpinejs';
import Sortable from 'sortablejs';

/**
 * カンバンのドラッグ＆ドロップ
 *
 * 並べ替えた結果だけをサーバーへ送る。画面は Sortable が動かしたそのままにして、
 * 保存に失敗したときだけ読み込み直す。
 */
const post = (url, body) =>
    fetch(url, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
            Accept: 'application/json',
        },
        body: JSON.stringify(body),
    }).then((response) => {
        if (! response.ok) {
            throw new Error(response.statusText);
        }
    });

const idsOf = (container, attribute) =>
    [...container.querySelectorAll(`[${attribute}]`)].map((element) => Number(element.getAttribute(attribute)));

/**
 * 1つの入れ物の中だけを並べ替える汎用版（グループ管理などで使う）
 */
Alpine.directive('sortable', (element, { expression }, { evaluate }) => {
    const { url, attribute = 'data-id', handle = null } = evaluate(expression);

    Sortable.create(element, {
        animation: 150,
        handle,
        draggable: `[${attribute}]`,
        onEnd: () => post(url, { ids: idsOf(element, attribute) }).catch(() => location.reload()),
    });
});

Alpine.directive('board', (element, { expression }, { evaluate }) => {
    const { tasksUrl, listsUrl } = evaluate(expression);

    // リストの並べ替え
    Sortable.create(element, {
        animation: 150,
        handle: '[data-list-handle]',
        draggable: '[data-list-id]',
        onEnd: () => post(listsUrl, { ids: idsOf(element, 'data-list-id') }).catch(() => location.reload()),
    });

    // タスクの並べ替え（リストをまたいで動かせる）
    element.querySelectorAll('[data-tasks]').forEach((container) => {
        Sortable.create(container, {
            animation: 150,
            group: 'tasks',
            draggable: '[data-task-id]',
            onEnd: (event) => {
                const target = event.to;

                post(tasksUrl, {
                    list_id: Number(target.getAttribute('data-tasks')),
                    ids: idsOf(target, 'data-task-id'),
                })
                    .then(() => {
                        // 移動元のリストも詰め直す
                        if (event.from !== target) {
                            return post(tasksUrl, {
                                list_id: Number(event.from.getAttribute('data-tasks')),
                                ids: idsOf(event.from, 'data-task-id'),
                            });
                        }
                    })
                    .catch(() => location.reload());
            },
        });
    });
});

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
