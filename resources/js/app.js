import collapse from '@alpinejs/collapse';
import Alpine from 'alpinejs';
import Sortable, { MultiDrag } from 'sortablejs';

Sortable.mount(new MultiDrag());

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
    const { tasksUrl, listsUrl, archiveUrl } = evaluate(expression);
    const archiveZone = document.querySelector('[data-archive-zone]');
    // Mac の Ctrl＋クリックは右クリック扱いになるので、Mac では Cmd で複数選択する
    const isMac = /Macintosh/.test(navigator.userAgent);

    const refreshCounts = () => element.querySelectorAll('[data-list-id]').forEach((list) => {
        list.querySelector('[data-list-count]').textContent = list.querySelectorAll('[data-task-id]').length;
    });

    // リストの並べ替え
    Sortable.create(element, {
        animation: 150,
        handle: '[data-list-handle]',
        draggable: '[data-list-id]',
        onEnd: () => post(listsUrl, { ids: idsOf(element, 'data-list-id') }).catch(() => location.reload()),
    });

    // ドラッグ中だけ出るアーカイブの受け皿。ここに落ちたタスクは下の onEnd で処理する
    Sortable.create(archiveZone, {
        group: 'tasks',
        sort: false,
        multiDrag: true,
        selectedClass: 'task-selected',
        draggable: '[data-task-id]',
    });

    // タスクの並べ替え（リストをまたいで動かせる）。Ctrl（Mac は Cmd）を押しながらクリックすると複数選択できる
    element.querySelectorAll('[data-tasks]').forEach((container) => {
        Sortable.create(container, {
            animation: 150,
            group: 'tasks',
            draggable: '[data-task-id]',
            multiDrag: true,
            multiDragKey: isMac ? 'Meta' : 'ctrl',
            selectedClass: 'task-selected',
            // キーなしのクリックでも1件選択されてしまうので、その場で取り消す
            onSelect: (event) => {
                if (! event.originalEvent?.[isMac ? 'metaKey' : 'ctrlKey']) {
                    Sortable.utils.deselect(event.item);
                }
            },
            onStart: () => archiveZone.classList.remove('hidden'),
            onEnd: (event) => {
                const target = event.to;
                const items = event.items.length > 0 ? event.items : [event.item];

                archiveZone.classList.add('hidden');

                // アーカイブタブの件数と一緒に画面を作り直す
                if (target === archiveZone) {
                    post(archiveUrl, { ids: items.map((item) => Number(item.getAttribute('data-task-id'))) })
                        .finally(() => location.reload());

                    return;
                }

                items.forEach((item) => Sortable.utils.deselect(item));
                refreshCounts();

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

/**
 * 発行したパスワードの一覧を CSV で保存する
 *
 * サーバーには平文を残さないので、画面が持っている分をその場でファイルにする。
 */
Alpine.data('issuedPasswords', (rows) => ({
    download() {
        const quote = (value) => `"${String(value).replaceAll('"', '""')}"`;
        const lines = [
            ['名前', 'メールアドレス', 'パスワード'],
            ...rows.map((row) => [row.name, row.email, row.password]),
        ].map((line) => line.map(quote).join(','));

        const link = document.createElement('a');
        link.href = URL.createObjectURL(new Blob([`\uFEFF${lines.join('\r\n')}\r\n`], { type: 'text/csv' }));
        link.download = 'issued-passwords.csv';
        link.click();
        URL.revokeObjectURL(link.href);
    },
}));

Alpine.plugin(collapse);
Alpine.start();
