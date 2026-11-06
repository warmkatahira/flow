import collapse from '@alpinejs/collapse';
import Alpine from 'alpinejs';
import Sortable, { MultiDrag } from 'sortablejs';
import './echo';

Sortable.mount(new MultiDrag());

/**
 * カンバンのドラッグ＆ドロップ
 *
 * 並べ替えた結果だけをサーバーへ送る。画面は Sortable が動かしたそのままにして、
 * 保存に失敗したときだけ読み込み直す。
 */
let pendingWrites = 0;

const post = (url, body) => {
    pendingWrites += 1;

    return fetch(url, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
            // 自分の操作の通知が自分へ返ってこないようにする
            'X-Socket-ID': window.Echo.socketId() ?? '',
            Accept: 'application/json',
        },
        body: JSON.stringify(body),
    }).then((response) => {
        if (! response.ok) {
            throw new Error(response.statusText);
        }
    }).finally(() => {
        pendingWrites -= 1;
    });
};

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

Alpine.directive('board', (element, { expression }, { evaluate, cleanup }) => {
    const { tasksUrl, listsUrl, archiveUrl } = evaluate(expression);
    const archiveZone = document.querySelector('[data-archive-zone]');
    // Mac の Ctrl＋クリックは右クリック扱いになるので、Mac では Cmd で複数選択する
    const isMac = /Macintosh/.test(navigator.userAgent);

    const refreshCounts = () => element.querySelectorAll('[data-list-id]').forEach((list) => {
        list.querySelector('[data-list-count]').textContent = list.querySelectorAll('[data-task-id]').length;
    });

    // 他の人の変更でボードが差し替わるときに外す
    const sortables = [];
    cleanup(() => sortables.forEach((sortable) => sortable.destroy()));

    // リストの並べ替え
    sortables.push(Sortable.create(element, {
        animation: 150,
        handle: '[data-list-handle]',
        draggable: '[data-list-id]',
        onEnd: () => post(listsUrl, { ids: idsOf(element, 'data-list-id') }).catch(() => location.reload()),
    }));

    // ドラッグ中だけ出るアーカイブの受け皿。ここに落ちたタスクは下の onEnd で処理する
    sortables.push(Sortable.create(archiveZone, {
        group: 'tasks',
        sort: false,
        multiDrag: true,
        selectedClass: 'task-selected',
        draggable: '[data-task-id]',
    }));

    // タスクの並べ替え（リストをまたいで動かせる）。Ctrl（Mac は Cmd）を押しながらクリックすると複数選択できる
    element.querySelectorAll('[data-tasks]').forEach((container) => {
        sortables.push(Sortable.create(container, {
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
                    moved: items.map((item) => Number(item.getAttribute('data-task-id'))),
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
        }));
    });
});

/**
 * 同じプロジェクトを見ている人の変更を、この画面へ反映する
 *
 * 通知には中身がないので、ボードをサーバーから取り直して差し替える。
 * 差し替えると操作中のものが消えるので、ドラッグ・入力・複数選択の最中は待ち、
 * 手が空いたところで反映する。自分の保存が終わる前に取り直すと古い状態へ戻るので、それも待つ。
 */
Alpine.directive('project-live', (element, { expression }, { evaluate, cleanup }) => {
    const { projectId, boardUrl } = evaluate(expression);
    const channel = `projects.${projectId}`;
    const board = element.querySelector('[data-live-board]');
    const archive = element.querySelector('[data-live-archive]');
    const archiveCount = element.querySelector('[data-live-archive-count]');

    let stale = false;
    let fetching = false;

    const isIdle = () => pendingWrites === 0
        && ! Sortable.dragged
        && ! board.querySelector('.task-selected')
        && ! [...board.querySelectorAll('form')].some((form) => form.offsetParent !== null);

    const refresh = async () => {
        if (! stale || fetching || ! isIdle()) {
            return;
        }

        stale = false;
        fetching = true;

        try {
            const response = await fetch(boardUrl, { headers: { Accept: 'application/json' } });

            if (! response.ok) {
                location.reload();

                return;
            }

            const data = await response.json();

            // 取り直している間に操作が始まったら、今回は捨ててやり直す
            if (! isIdle()) {
                stale = true;

                return;
            }

            const scrollLeft = board.querySelector('[x-board]')?.scrollLeft ?? 0;

            // 中の Alpine は差し替えを検知して自動で作り直される
            board.innerHTML = data.board;
            archive.innerHTML = data.archive;
            archiveCount.textContent = data.archiveCount;

            const scroller = board.querySelector('[x-board]');

            if (scroller) {
                scroller.scrollLeft = scrollLeft;
            }
        } catch {
            stale = true;
        } finally {
            fetching = false;
        }
    };

    const timer = setInterval(refresh, 500);

    window.Echo.private(channel).listen('.board.changed', ({ reload }) => {
        if (reload) {
            location.reload();

            return;
        }

        stale = true;
        refresh();
    });

    // 接続が切れていた間の通知は届かないので、つなぎ直したら取り直す
    const { connection } = window.Echo.connector.pusher;
    let connectedBefore = connection.state === 'connected';

    const onConnected = () => {
        stale = stale || connectedBefore;
        connectedBefore = true;
    };

    connection.bind('connected', onConnected);

    cleanup(() => {
        clearInterval(timer);
        connection.unbind('connected', onConnected);
        window.Echo.leave(channel);
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

/**
 * 左のプロジェクトリスト
 *
 * 広い画面では畳める列（状態は覚えておく）、狭い画面では開閉する引き出しになる。
 * 保存領域が使えないブラウザでも、覚えられないだけで動くようにする。
 */
const CLOSED_GROUPS_KEY = 'flow.sidebar.closedGroups';

const closedGroups = () => {
    try {
        return JSON.parse(localStorage.getItem(CLOSED_GROUPS_KEY) ?? '[]');
    } catch {
        return [];
    }
};

const remember = (storage, key, value) => {
    try {
        storage.setItem(key, value);
    } catch {
        // 覚えられないだけ
    }
};

Alpine.store('sidebar', {
    drawer: false,

    toggle() {
        if (! window.matchMedia('(min-width: 64rem)').matches) {
            this.drawer = ! this.drawer;

            return;
        }

        const root = document.documentElement;
        const collapsed = root.dataset.sidebar !== 'closed';

        if (collapsed) {
            root.dataset.sidebar = 'closed';
        } else {
            delete root.dataset.sidebar;
        }

        remember(localStorage, 'flow.sidebar.collapsed', collapsed ? '1' : '0');
    },
});

Alpine.data('sidebarGroup', (name, hasCurrent) => ({
    open: hasCurrent || ! closedGroups().includes(name),

    toggle() {
        this.open = ! this.open;

        const others = closedGroups().filter((closed) => closed !== name);

        remember(localStorage, CLOSED_GROUPS_KEY, JSON.stringify(this.open ? others : [...others, name]));
    },
}));

window.addEventListener('pagehide', () => {
    const tree = document.querySelector('[data-sidebar-scroll]');

    if (tree) {
        remember(sessionStorage, 'flow.sidebar.scroll', String(tree.scrollTop));
    }
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

/**
 * Echo exposes an expressive API for subscribing to channels and listening
 * for events that are broadcast by Laravel. Echo and event broadcasting
 * allow your team to quickly build robust real-time web applications.
 */

