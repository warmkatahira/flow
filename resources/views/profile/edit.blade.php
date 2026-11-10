<x-layouts.app title="プロフィール">
    <x-slot:header>
        <h1 class="text-lg font-bold text-slate-800">プロフィール</h1>
    </x-slot:header>

    <div class="max-w-2xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-6">
        <div class="rounded-xl bg-white px-6 py-6 shadow-sm">
            <h2 class="text-sm font-bold text-slate-700">アイコン画像</h2>

            {{--
                選ぶか枠にドロップすると表示エリアを決めるポップアップが開き、切り抜いた画像だけを保存する
                （resources/js/image-cropper.js の imageCropper。プロジェクトのアイコンと同じ）。
                枠の外に落としてもブラウザが画像を開いて画面を離れないよう、window でも止める。
            --}}
            <form autocomplete="off" method="POST" action="{{ route('profile.avatar.update') }}"
                  enctype="multipart/form-data" class="mt-4"
                  x-data="imageCropper({ round: true })"
                  @dragover.window.prevent
                  @drop.window.prevent>
                @csrf
                @method('PUT')

                <div class="flex flex-col items-center gap-5 sm:flex-row">
                    <div class="shrink-0">
                        <div x-show="! preview">
                            <x-user-avatar :user="$user" size="lg" />
                        </div>
                        <img x-show="preview" x-cloak :src="preview" alt="選んだ画像のプレビュー"
                             class="h-24 w-24 rounded-full object-cover">
                    </div>

                    {{-- 子要素の上で dragleave が走ってちらつかないよう、中身はポインターを拾わせない --}}
                    <label for="avatar"
                           @dragover.prevent="dragging = true"
                           @dragleave.prevent="dragging = false"
                           @drop.prevent="dragging = false; open($event.dataTransfer.files)"
                           :class="dragging ? 'border-sky-500 bg-sky-50' : 'border-slate-300 hover:border-slate-400 hover:bg-slate-50'"
                           class="block w-full flex-1 cursor-pointer rounded-xl border-2 border-dashed px-4 py-6 text-center transition focus-within:ring-2 focus-within:ring-sky-500/30">
                        <input autocomplete="off" id="avatar" name="avatar" type="file" x-ref="input"
                               accept="image/jpeg,image/png,image/webp" class="sr-only"
                               @change="open($event.target.files)">

                        <span class="pointer-events-none block text-sm font-medium text-slate-700">
                            画像をここにドラッグ＆ドロップ
                        </span>
                        <span class="pointer-events-none mt-1 block text-xs text-sky-700">またはクリックして選択</span>
                        <span class="pointer-events-none mt-2 block text-xs text-slate-500">JPEG / PNG / WebP。選んだあとに表示する範囲を調整できます</span>
                    </label>
                </div>

                <p x-show="error" x-cloak x-text="error" class="mt-2 text-sm text-rose-600"></p>
                <x-input-error :messages="$errors->get('avatar')" />

                <div x-show="preview" x-cloak class="mt-4 flex items-center gap-3">
                    <x-primary-button>保存する</x-primary-button>
                    <button type="button" @click="clear()" class="text-sm text-slate-600 hover:text-slate-900">やめる</button>
                    <span class="min-w-0 truncate text-xs text-slate-500" x-text="name"></span>
                </div>

                <x-image-cropper-dialog />
            </form>

            @if ($user->avatar_path)
                <form autocomplete="off" method="POST" action="{{ route('profile.avatar.destroy') }}" class="mt-4">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="text-xs text-rose-600 hover:text-rose-800">画像を削除して頭文字に戻す</button>
                </form>
            @endif
        </div>

        <div class="rounded-xl bg-white px-6 py-6 shadow-sm">
            <h2 class="text-sm font-bold text-slate-700">アカウント</h2>

            <dl class="mt-4 space-y-3 text-sm">
                <div>
                    <dt class="text-xs text-slate-500">名前</dt>
                    <dd class="text-slate-800">{{ $user->name }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-slate-500">メールアドレス</dt>
                    <dd class="break-all text-slate-800">{{ $user->email }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-slate-500">権限</dt>
                    <dd class="text-slate-800">{{ $user->role->label() }}</dd>
                </div>
            </dl>

            <a href="{{ route('password.edit') }}"
               class="mt-5 inline-flex items-center justify-center rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">
                パスワードを変更
            </a>
        </div>
    </div>
</x-layouts.app>
