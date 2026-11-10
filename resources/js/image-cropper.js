import Alpine from 'alpinejs';

/**
 * 画像の表示エリアを選ぶ（プロジェクトのアイコンなど）
 *
 * ファイルを選ぶとポップアップを開き、正方形の枠の中で画像を動かして（ドラッグ）、大きさを変える（スライダー・ホイール）。
 * 保存すると枠の中だけを OUTPUT 四方の PNG に切り抜き、同じ input[type=file] へ入れ直す。サーバーは切り抜いた画像だけを受け取る。
 * ポップアップは x-image-cropper-dialog。input には x-ref="input" を付け、選んだら open(files) を呼ぶ。
 * options.round は丸く表示するもの（プロフィールのアイコン）。枠を丸く見せるだけで、切り抜くのは正方形。
 */
const TYPES = ['image/jpeg', 'image/png', 'image/webp'];
const STAGE_WIDTH = 280;
const STAGE_HEIGHT = 240;
const FRAME = 200;
const OUTPUT = 512;
// 枠を埋める大きさから、何倍まで拡大できるか
const MAX_ZOOM = 4;

Alpine.data('imageCropper', (options = {}) => ({
    round: Boolean(options.round),
    error: '',
    STAGE_WIDTH,
    STAGE_HEIGHT,
    FRAME,
    src: '',
    preview: '',
    name: '',
    width: 0,
    height: 0,
    minScale: 1,
    scale: 1,
    x: 0,
    y: 0,
    // スライダーの値（0〜100）。拡大率は対数で割り当て、どこでも同じ手触りで変わるようにする
    zoom: 0,
    drag: null,
    // ファイルを枠の上へドラッグしている間（プロフィールの受け皿の見た目に使う）
    dragging: false,
    // 前に切り抜いて入れておいたファイル（選び直してやめたときに戻す）
    saved: null,

    get frameLeft() {
        return (STAGE_WIDTH - FRAME) / 2;
    },

    get frameTop() {
        return (STAGE_HEIGHT - FRAME) / 2;
    },

    get imageStyle() {
        return `width: ${this.width * this.scale}px; height: ${this.height * this.scale}px; transform: translate(${this.x}px, ${this.y}px);`;
    },

    open(files) {
        const file = files?.[0];

        this.error = '';

        if (! file) {
            return;
        }

        // accept はドラッグ＆ドロップには効かないので、ここでも確かめる
        if (! TYPES.includes(file.type)) {
            this.error = 'JPEG / PNG / WebP の画像を選んでください。';
            this.restore();

            return;
        }

        this.name = file.name.replace(/\.[^.]+$/, '');
        URL.revokeObjectURL(this.src);
        this.src = URL.createObjectURL(file);

        const image = new Image();

        image.onload = () => {
            this.width = image.naturalWidth;
            this.height = image.naturalHeight;
            this.minScale = Math.max(FRAME / this.width, FRAME / this.height);
            this.zoom = 0;
            this.scale = this.minScale;
            // 最初は真ん中に合わせる
            this.x = (STAGE_WIDTH - this.width * this.scale) / 2;
            this.y = (STAGE_HEIGHT - this.height * this.scale) / 2;
            this.$refs.cropper.showModal();
        };

        image.onerror = () => {
            this.cancel();
            this.error = '画像を読み込めませんでした。別の画像を選んでください。';
        };

        image.src = this.src;
    },

    /** 拡大率を変える。枠の真ん中に見えている所を動かさない */
    zoomTo(value) {
        this.zoom = Math.min(100, Math.max(0, Number(value)));

        const next = this.minScale * MAX_ZOOM ** (this.zoom / 100);
        const centerX = STAGE_WIDTH / 2;
        const centerY = STAGE_HEIGHT / 2;

        this.x = centerX - (centerX - this.x) * (next / this.scale);
        this.y = centerY - (centerY - this.y) * (next / this.scale);
        this.scale = next;
        this.clamp();
    },

    wheel(event) {
        this.zoomTo(this.zoom - Math.sign(event.deltaY) * 5);
    },

    /** 枠の中に画像のない所ができないよう押し戻す */
    clamp() {
        const right = this.frameLeft + FRAME;
        const bottom = this.frameTop + FRAME;

        this.x = Math.min(this.frameLeft, Math.max(right - this.width * this.scale, this.x));
        this.y = Math.min(this.frameTop, Math.max(bottom - this.height * this.scale, this.y));
    },

    start(event) {
        this.drag = { pointerX: event.clientX, pointerY: event.clientY, x: this.x, y: this.y };
        event.currentTarget.setPointerCapture(event.pointerId);
    },

    move(event) {
        if (! this.drag) {
            return;
        }

        this.x = this.drag.x + event.clientX - this.drag.pointerX;
        this.y = this.drag.y + event.clientY - this.drag.pointerY;
        this.clamp();
    },

    end() {
        this.drag = null;
    },

    /** 矢印キーでも動かせる */
    nudge(dx, dy) {
        this.x += dx;
        this.y += dy;
        this.clamp();
    },

    save() {
        const canvas = document.createElement('canvas');

        canvas.width = OUTPUT;
        canvas.height = OUTPUT;
        canvas.getContext('2d').drawImage(
            this.$refs.image,
            (this.frameLeft - this.x) / this.scale,
            (this.frameTop - this.y) / this.scale,
            FRAME / this.scale,
            FRAME / this.scale,
            0,
            0,
            OUTPUT,
            OUTPUT,
        );

        canvas.toBlob((blob) => {
            this.saved = new File([blob], `${this.name || 'icon'}.png`, { type: 'image/png' });
            this.restore();

            URL.revokeObjectURL(this.preview);
            this.preview = URL.createObjectURL(blob);
            this.$refs.cropper.close();
        }, 'image/png');
    },

    /** やめたら選んだファイルを外し、前に切り抜いたものがあればそれに戻す（切り抜いていない画像は送らない） */
    cancel() {
        if (this.$refs.cropper.open) {
            this.$refs.cropper.close();
        }

        this.restore();
        URL.revokeObjectURL(this.src);
        this.src = '';
    },

    /** input の中身を、切り抜いて保存したもの（なければ空）に戻す */
    restore() {
        if (! this.saved) {
            this.$refs.input.value = '';

            return;
        }

        // Alpine の proxy のままでは input に入らないので、元の File から作り直す
        const transfer = new DataTransfer();

        transfer.items.add(Alpine.raw(this.saved));
        this.$refs.input.files = transfer.files;
    },

    /** 切り抜いた画像も含めて、選ぶ前に戻す */
    clear() {
        this.saved = null;
        this.error = '';
        this.$refs.input.value = '';
        URL.revokeObjectURL(this.preview);
        this.preview = '';
    },
}));
