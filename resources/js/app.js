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

/**
 * 何もないところを掴んで横へドラッグするとスクロールする（マウスだけ。タッチは元からできる）
 *
 * カードや見出しのドラッグは並べ替えに使うので、ここでは触らない。
 */
Alpine.directive('drag-scroll', (element) => {
    const ignored = 'article, [data-list-handle], a, button, input, textarea, select, label, form';
    let startX = 0;
    let startScrollLeft = 0;

    const overflows = () => element.scrollWidth > element.clientWidth;
    const panning = () => 'panning' in element.dataset;
    // スクロールバーの上でも押した先はこの要素やリストになるので、位置で見分ける
    const onScrollbar = ({ target, clientX, clientY }) => clientY > element.getBoundingClientRect().top + element.clientHeight
        || (target.clientWidth > 0 && clientX > target.getBoundingClientRect().left + target.clientWidth);

    element.addEventListener('pointerdown', (event) => {
        if (event.pointerType !== 'mouse' || event.button !== 0 || ! overflows()
            || event.target.closest(ignored) || onScrollbar(event)) {
            return;
        }

        startX = event.clientX;
        startScrollLeft = element.scrollLeft;
        element.dataset.panning = '';
        element.style.cursor = 'grabbing';
        element.style.userSelect = 'none';
        element.setPointerCapture(event.pointerId);
    });

    element.addEventListener('pointermove', (event) => {
        if (panning()) {
            element.scrollLeft = startScrollLeft - (event.clientX - startX);

            return;
        }

        element.style.cursor = overflows() && ! event.target.closest(ignored) && ! onScrollbar(event) ? 'grab' : '';
    });

    element.addEventListener('lostpointercapture', () => {
        delete element.dataset.panning;
        element.style.cursor = '';
        element.style.userSelect = '';
    });
});

/**
 * チェックリストの項目の並べ替え（同じタスクの枠をまたいで動かせる）
 *
 * 進捗が変わるので、保存したら詳細を取り直す。
 */
Alpine.directive('checklist-items', (element, { expression }, { cleanup }) => {
    const sortable = Sortable.create(element, {
        animation: 150,
        group: `checklist-${expression}`,
        handle: '[data-item-handle]',
        draggable: '[data-item-id]',
        onEnd: ({ item, from, to, oldIndex, newIndex }) => {
            if (from === to && oldIndex === newIndex) {
                return;
            }

            post(to.dataset.reorderUrl, {
                ids: idsOf(to, 'data-item-id'),
                moved: Number(item.getAttribute('data-item-id')),
            })
                .then(() => window.dispatchEvent(new CustomEvent('task-reload')))
                .catch(() => location.reload());
        },
    });

    cleanup(() => sortable.destroy());
});

Alpine.directive('board', (element, { expression }, { evaluate, cleanup }) => {
    const { tasksUrl, listsUrl, archiveUrl } = evaluate(expression);
    const archiveZone = document.querySelector('[data-archive-zone]');
    // Mac の Ctrl＋クリックは右クリック扱いになるので、Mac では Cmd で複数選択する
    const isMac = /Macintosh/.test(navigator.userAgent);
    // ドラッグしたまま端へ寄せると横へ送る。Chrome 任せだとボードの端に届きにくいので Sortable に任せる
    const autoScroll = { forceAutoScrollFallback: true, scrollSensitivity: 80 };

    const refreshCounts = () => element.querySelectorAll('[data-list-id]').forEach((list) => {
        list.querySelector('[data-list-count]').textContent = list.querySelectorAll('[data-task-id]').length;
    });

    // 他の人の変更でボードが差し替わるときに外す
    const sortables = [];
    cleanup(() => sortables.forEach((sortable) => sortable.destroy()));

    // カードを押したら開く。Ctrl（Mac は Cmd）は複数選択なので開かない
    element.addEventListener('click', (event) => {
        const card = event.target.closest('[data-task-id]');

        if (card && ! event.ctrlKey && ! event.metaKey && ! event.target.closest('button, a, form, [role=menu]')) {
            window.dispatchEvent(new CustomEvent('open-task', { detail: { url: card.dataset.taskUrl } }));
        }
    });

    // リストの並べ替え
    sortables.push(Sortable.create(element, {
        ...autoScroll,
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
            ...autoScroll,
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
        && ! board.querySelector('.task-selected, [data-panning], [data-menu-open]')
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
            Alpine.store('boardFilter').apply();
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

    // 自分の変更は通知を待たずに取り直す（Reverb が止まっていても画面が古いままにならない）
    const onRefresh = () => {
        stale = true;
        refresh();
    };

    window.addEventListener('board-refresh', onRefresh);

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
        window.removeEventListener('board-refresh', onRefresh);
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

/**
 * リストとタスクの ⋯ メニュー
 *
 * 押した場所の下に出す。画面の端で切れるときは内側へずらす。
 */
Alpine.data('boardMenu', () => ({
    menuOpen: false,

    toggle() {
        this.menuOpen = ! this.menuOpen;

        if (! this.menuOpen) {
            return;
        }

        this.$nextTick(() => {
            const { panel } = this.$refs;
            const trigger = this.$refs.trigger.getBoundingClientRect();

            panel.style.left = `${Math.max(8, trigger.right - panel.offsetWidth)}px`;
            panel.style.top = `${Math.max(8, Math.min(trigger.bottom + 4, window.innerHeight - panel.offsetHeight - 8))}px`;
        });
    },
}));

/**
 * アーカイブの確認
 *
 * 取り消し用のトーストが出るので、ドラッグでのアーカイブには出さない。
 */
Alpine.data('archiveConfirm', () => ({
    kind: '',
    name: '',
    note: '',
    form: null,

    ask(event) {
        const form = event.target;

        if (! form.dataset.archiveConfirm) {
            return;
        }

        event.preventDefault();

        this.form = form;
        this.kind = form.dataset.archiveConfirm;
        this.name = form.dataset.name;
        this.note = form.dataset.note ?? '';
        this.$refs.dialog.showModal();
    },

    accept() {
        // 確認している間に他の人の変更でボードが差し替わっていても送れるようにする
        if (! this.form.isConnected) {
            document.body.append(this.form);
        }

        // submit() は submit イベントを起こさないので、ここへ戻ってこない
        this.form.submit();
    },
}));

/**
 * ボードの絞り込み
 *
 * カードに持たせた data-status / data-due / data-assignees を見て、画面の中で隠すだけ。
 * 種類どうしは AND、同じ種類の中は OR。フォームの送信で読み込み直しても消えないよう、タブの間は覚えておく。
 */
const ymd = (date) => [
    date.getFullYear(),
    String(date.getMonth() + 1).padStart(2, '0'),
    String(date.getDate()).padStart(2, '0'),
].join('-');

const daysFrom = (date, days) => {
    const result = new Date(date);

    result.setDate(result.getDate() + days);

    return result;
};

Alpine.store('boardFilter', {
    key: null,
    me: null,
    statuses: [],
    due: null,
    from: '',
    to: '',
    assignees: [],
    mine: false,

    get count() {
        return this.statuses.length + this.assignees.length + (this.due ? 1 : 0);
    },

    load(projectId, me) {
        this.key = `flow.filter.${projectId}`;
        this.me = me;

        try {
            Object.assign(this, JSON.parse(sessionStorage.getItem(this.key) ?? '{}'));
        } catch {
            // 覚えていなければ絞り込みなし
        }

        this.apply();
    },

    changed() {
        const { statuses, due, from, to, assignees, mine } = this;

        remember(sessionStorage, this.key, JSON.stringify({ statuses, due, from, to, assignees, mine }));
        this.apply();
    },

    toggle(field, value) {
        this[field] = this[field].includes(value)
            ? this[field].filter((current) => current !== value)
            : [...this[field], value];
        this.changed();
    },

    toggleMine() {
        this.mine = ! this.mine;
        this.changed();
    },

    pickDue(value) {
        this.due = this.due === value ? null : value;
        this.changed();
    },

    clear() {
        Object.assign(this, { statuses: [], due: null, from: '', to: '', assignees: [], mine: false });
        this.changed();
    },

    /** 締切日の範囲。[から, まで]。空なら上限・下限なし */
    dueRange() {
        const today = new Date();
        const monday = daysFrom(today, -((today.getDay() + 6) % 7));

        return {
            past: ['', ymd(daysFrom(today, -1))],
            today: [ymd(today), ymd(today)],
            tomorrow: [ymd(daysFrom(today, 1)), ymd(daysFrom(today, 1))],
            week: [ymd(monday), ymd(daysFrom(monday, 6))],
            nextWeek: [ymd(daysFrom(monday, 7)), ymd(daysFrom(monday, 13))],
            next14: [ymd(today), ymd(daysFrom(today, 14))],
            custom: [this.from, this.to],
        }[this.due];
    },

    matches(card, range) {
        const { status, due, assignees } = card.dataset;
        const ids = assignees ? assignees.split(',').map(Number) : [];

        if (this.statuses.length > 0 && ! this.statuses.includes(status)) {
            return false;
        }

        if (this.mine && ! ids.includes(this.me)) {
            return false;
        }

        if (this.assignees.length > 0 && ! this.assignees.some((id) => (id === 0 ? ids.length === 0 : ids.includes(id)))) {
            return false;
        }

        if (range && (range[0] || range[1])) {
            return Boolean(due) && (! range[0] || due >= range[0]) && (! range[1] || due <= range[1]);
        }

        return true;
    },

    apply() {
        const range = this.due ? this.dueRange() : null;

        document.querySelectorAll('article[data-status]').forEach((card) => {
            card.classList.toggle('hidden', ! this.matches(card, range));
        });
    },
});

/**
 * タスクの移動先を選ぶダイアログ
 *
 * 他のプロジェクトへも動かせる。候補は他の人の変更があるので、開くたびに取り直す。
 */
Alpine.data('moveDialog', (targetsUrl, currentProjectId) => ({
    name: '',
    action: '',
    listId: null,
    projects: [],
    projectId: null,
    targetListId: null,
    loading: false,

    get listOptions() {
        const project = this.projects.find((candidate) => candidate.id === this.projectId);

        return (project?.lists ?? []).filter((list) => list.id !== this.listId);
    },

    get canSubmit() {
        return ! this.loading && Boolean(this.targetListId);
    },

    async open({ name, action, listId }) {
        Object.assign(this, { name, action, listId, projects: [], projectId: null, targetListId: null, loading: true });
        this.$refs.dialog.showModal();

        const response = await fetch(targetsUrl, { headers: { Accept: 'application/json' } });

        if (! response.ok) {
            location.reload();

            return;
        }

        this.projects = await response.json();
        this.loading = false;

        // 選択肢が描かれてからでないと select に値が入らない
        await this.$nextTick();
        this.projectId = currentProjectId;
        this.pickList();
    },

    async pickList() {
        await this.$nextTick();
        this.targetListId = this.listOptions[0]?.id ?? null;
    },
}));

/**
 * コメントのメンション
 *
 * 文字カーソルの直前が「@文字列」のとき、一致する参加者を出す。選んだら「@名前 」に置き換える。
 * 実際に誰へのメンションになるかはサーバーが本文から決める。
 */
Alpine.data('mentionBox', (members) => ({
    candidates: [],
    active: 0,
    start: 0,

    update() {
        const { body } = this.$refs;
        const match = body.value.slice(0, body.selectionStart).match(/[@＠]([^\s@＠]*)$/);

        this.active = 0;
        this.start = match?.index ?? 0;
        this.candidates = match ? members.filter((member) => member.name.includes(match[1])) : [];
    },

    keydown(event) {
        // 変換の確定の Enter は候補の決定にしない
        if (this.candidates.length === 0 || event.isComposing) {
            return;
        }

        const count = this.candidates.length;

        if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
            this.active = (this.active + (event.key === 'ArrowDown' ? 1 : count - 1)) % count;
        } else if (event.key === 'Enter' || event.key === 'Tab') {
            this.choose(this.candidates[this.active]);
        } else if (event.key === 'Escape') {
            this.candidates = [];
        } else {
            return;
        }

        event.preventDefault();
        event.stopPropagation();
    },

    choose(member) {
        const { body } = this.$refs;
        const before = `${body.value.slice(0, this.start)}@${member.name} `;

        body.value = before + body.value.slice(body.selectionStart);
        body.setSelectionRange(before.length, before.length);
        this.candidates = [];
    },
}));

/**
 * タスクの詳細
 *
 * 中身はサーバーが組み立てる。data-task-form の送信は画面遷移させず、返ってきた中身で差し替える。
 * 閉じたら、変更があればボードを取り直す。
 */
Alpine.data('taskDialog', (openUrl) => ({
    url: null,
    error: '',
    changed: false,

    init() {
        if (openUrl) {
            this.open(openUrl);
        }
    },

    async reload() {
        const response = await fetch(this.url, { headers: { Accept: 'text/html' } });

        if (response.ok) {
            this.changed = true;
            this.$refs.body.innerHTML = await response.text();
        }
    },

    async open(url) {
        this.url = url;
        this.error = '';
        this.$refs.body.innerHTML = '<p class="p-6 text-sm text-slate-400">読み込み中…</p>';
        this.$refs.dialog.showModal();

        const response = await fetch(url, { headers: { Accept: 'text/html' } });

        // 他の人がアーカイブしたり動かしたりしたばかりのとき
        if (! response.ok) {
            this.close();

            return;
        }

        this.$refs.body.innerHTML = await response.text();
    },

    close() {
        this.$refs.dialog.close();
    },

    closed() {
        if (this.changed) {
            this.changed = false;
            window.dispatchEvent(new CustomEvent('board-refresh'));
        }
    },

    async submit(event) {
        const form = event.target.closest('[data-task-form]');

        if (! form || event.defaultPrevented) {
            return;
        }

        event.preventDefault();
        this.error = '';

        const response = await fetch(form.action, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
                Accept: 'application/json',
            },
            body: new FormData(form, event.submitter),
        });

        if (response.status === 422) {
            const { errors } = await response.json();

            this.error = Object.values(errors)[0][0];

            return;
        }

        if (! response.ok) {
            this.error = '保存できませんでした。画面を読み込み直してください。';

            return;
        }

        this.changed = true;
        this.$refs.body.innerHTML = await response.text();

        if (form.dataset.refocus) {
            this.$refs.body.querySelector(form.dataset.refocus)?.focus();
        }
    },
}));

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

