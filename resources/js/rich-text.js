import { Editor } from '@tiptap/core';
import { BackgroundColor, Color, FontSize, TextStyle } from '@tiptap/extension-text-style';
import { Placeholder } from '@tiptap/extensions';
import StarterKit from '@tiptap/starter-kit';
import Alpine from 'alpinejs';

/**
 * 説明とコメントの書式付きエディタ
 *
 * 使える書式は文字の装飾だけ（太字・斜体・下線・取り消し線・文字色・背景色・大きさ）。
 * サーバー（App\Support\RichText）も同じものだけを通すので、ここで増やすときは両方を合わせる。
 *
 * エディタは Alpine のリアクティブな値に入れない（Proxy で包まれると ProseMirror が壊れる）。
 * 各コンポーネントの閉じた変数に持ち、editor() で取り出す。
 */
const createEditor = (element, { content, placeholder, onTransaction, onUpdate, handleKeyDown }) => new Editor({
    element,
    content,
    extensions: [
        StarterKit.configure({
            heading: false,
            bulletList: false,
            orderedList: false,
            listItem: false,
            listKeymap: false,
            blockquote: false,
            codeBlock: false,
            code: false,
            horizontalRule: false,
            link: false,
            dropcursor: false,
            gapcursor: false,
            trailingNode: false,
        }),
        TextStyle,
        Color,
        BackgroundColor,
        FontSize,
        Placeholder.configure({ placeholder }),
    ],
    editorProps: {
        attributes: { class: 'rich-text rich-text-editor' },
        handleKeyDown,
    },
    // 渡さなかったものを undefined のまま渡すと、Tiptap がそれを呼ぼうとして落ちる
    ...(onTransaction && { onTransaction }),
    ...(onUpdate && { onUpdate }),
});

/**
 * ツールバーの操作（resources/views/projects/partials/rich-text-toolbar.blade.php）
 *
 * 使う側のコンポーネントが editor() を持っていること。
 */
const formatting = () => ({
    marks: { bold: false, italic: false, underline: false, strike: false, color: null, backgroundColor: null, fontSize: null },
    menu: null,

    refreshMarks() {
        const editor = this.editor();
        const style = editor.getAttributes('textStyle');

        this.marks = {
            bold: editor.isActive('bold'),
            italic: editor.isActive('italic'),
            underline: editor.isActive('underline'),
            strike: editor.isActive('strike'),
            color: style.color ?? null,
            backgroundColor: style.backgroundColor ?? null,
            fontSize: style.fontSize ?? null,
        };
    },

    toggleMenu(name) {
        this.menu = this.menu === name ? null : name;
    },

    toggle(mark) {
        const command = `toggle${mark[0].toUpperCase()}${mark.slice(1)}`;

        this.editor().chain().focus()[command]().run();
    },

    /**
     * 文字色・背景色・大きさ。値が空なら外す
     */
    setStyle(attribute, value) {
        const name = `${attribute[0].toUpperCase()}${attribute.slice(1)}`;
        const chain = this.editor().chain().focus();

        (value ? chain[`set${name}`](value) : chain[`unset${name}`]()).run();
        this.menu = null;
    },

    clearFormatting() {
        this.editor().chain().focus().unsetAllMarks().run();
        this.menu = null;
    },
});

/**
 * タスクの説明。押すと編集になり、エディタの外へフォーカスが出たら保存する
 */
Alpine.data('richDescription', (saved) => {
    let editor = null;

    return {
        ...formatting(),
        editing: false,

        editor: () => editor,

        start() {
            this.editing = true;

            this.$nextTick(() => {
                editor ??= createEditor(this.$refs.editor, {
                    content: saved,
                    placeholder: '説明を書く',
                    onTransaction: () => this.refreshMarks(),
                    handleKeyDown: (view, event) => {
                        // ダイアログまで Escape が届くと閉じてしまう
                        if (event.key === 'Escape') {
                            event.preventDefault();
                            event.stopPropagation();
                            this.cancel();

                            return true;
                        }

                        return false;
                    },
                });

                editor.commands.focus('end');
            });
        },

        focusout(event) {
            // ツールバーへ移っただけなら続ける
            if (this.editing && ! this.$root.contains(event.relatedTarget)) {
                this.finish();
            }
        },

        finish() {
            const html = editor.isEmpty ? '' : editor.getHTML();

            this.editing = false;
            this.menu = null;

            if (html === saved) {
                return;
            }

            this.$refs.input.value = html;
            this.$refs.input.form.requestSubmit();
        },

        cancel() {
            editor.commands.setContent(saved);
            this.editing = false;
            this.menu = null;
            editor.commands.blur();
        },

        destroy() {
            editor?.destroy();
        },
    };
});

/**
 * コメントの入力欄。@ を打つとプロジェクトの参加者から選べる
 *
 * メンションは「@名前」という文字のまま入れる。誰へのメンションかはサーバーが本文の文字から決める。
 */
Alpine.data('commentEditor', (members, limits = { files: 5, bytes: 10 * 1024 * 1024 }) => {
    let editor = null;

    return {
        ...formatting(),
        candidates: [],
        highlighted: 0,
        from: 0,
        // 添付するファイル。ボタンで選んでも、ドラッグ＆ドロップしても、ここにためてから input へ写す
        files: [],
        fileError: '',
        dragging: 0,

        editor: () => editor,

        init() {
            editor = createEditor(this.$refs.editor, {
                content: '',
                placeholder: 'コメントを書く（@ でメンション）',
                onTransaction: () => this.refreshMarks(),
                onUpdate: () => this.lookup(),
                handleKeyDown: (view, event) => this.keydown(event),
            });

            editor.on('selectionUpdate', () => this.lookup());
            editor.on('blur', () => {
                this.candidates = [];
            });
        },

        lookup() {
            const { $from, empty } = editor.state.selection;
            const before = empty ? $from.parent.textBetween(0, $from.parentOffset, undefined, '￼') : '';
            const match = before.match(/[@＠]([^\s@＠]*)$/);

            this.highlighted = 0;
            this.from = match ? $from.pos - match[0].length : 0;
            this.candidates = match ? members.filter((member) => member.name.includes(match[1])) : [];
        },

        /**
         * 候補が出ているときの矢印・Enter・Tab・Escape。処理したら true（エディタには渡さない）
         */
        keydown(event) {
            // 変換の確定の Enter は候補の決定にしない
            if (this.candidates.length === 0 || event.isComposing) {
                return false;
            }

            const count = this.candidates.length;

            if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
                this.highlighted = (this.highlighted + (event.key === 'ArrowDown' ? 1 : count - 1)) % count;
            } else if (event.key === 'Enter' || event.key === 'Tab') {
                this.choose(this.candidates[this.highlighted]);
            } else if (event.key === 'Escape') {
                this.candidates = [];
            } else {
                return false;
            }

            event.preventDefault();
            event.stopPropagation();

            return true;
        },

        choose(member) {
            editor.chain().focus()
                .insertContentAt({ from: this.from, to: editor.state.selection.from }, { type: 'text', text: `@${member.name} ` })
                .run();
            this.candidates = [];
        },

        /**
         * 添付するファイルを足す。上限を超える分と大きすぎるものは足さずに知らせる
         */
        addFiles(list) {
            const incoming = Array.from(list ?? []);
            const tooLarge = incoming.filter((file) => file.size > limits.bytes);
            const room = limits.files - this.files.length;
            const accepted = incoming.filter((file) => file.size <= limits.bytes).slice(0, Math.max(room, 0));

            this.fileError = tooLarge.length > 0
                ? `添付できるファイルは1つ${Math.round(limits.bytes / 1024 / 1024)}MBまでです。`
                : (incoming.length - tooLarge.length > accepted.length ? `ファイルは1つのコメントに${limits.files}つまで添付できます。` : '');

            this.files = [...this.files, ...accepted];
            this.syncFiles();
        },

        removeFile(index) {
            this.files = this.files.filter((_, current) => current !== index);
            this.fileError = '';
            this.syncFiles();
        },

        /** ためたファイルを input[type=file] へ写す（フォームの送信で一緒に送られる） */
        syncFiles() {
            const transfer = new DataTransfer();

            this.files.forEach((file) => transfer.items.add(file));
            this.$refs.files.files = transfer.files;
        },

        dragenter(event) {
            if (event.dataTransfer?.types.includes('Files')) {
                this.dragging += 1;
            }
        },

        drop(event) {
            this.dragging = 0;
            this.addFiles(event.dataTransfer?.files);
        },

        sizeLabel(bytes) {
            return bytes >= 1024 * 1024 ? `${(bytes / 1024 / 1024).toFixed(1)} MB` : `${Math.max(1, Math.round(bytes / 1024))} KB`;
        },

        /**
         * 送る前にエディタの中身をフォームへ写す。本文もファイルもなければ送らない
         */
        submit(event) {
            if (this.files.length > 0 && (editor.isEmpty || editor.getText().trim() === '')) {
                this.$refs.input.value = '';

                return;
            }

            if (editor.isEmpty || editor.getText().trim() === '') {
                event.preventDefault();
                editor.commands.focus();

                return;
            }

            this.$refs.input.value = editor.getHTML();
        },

        destroy() {
            editor?.destroy();
        },
    };
});
