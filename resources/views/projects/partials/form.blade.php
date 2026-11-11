@php
    use App\Enums\ProjectRole;

    $me = auth()->user();

    // 入力エラーで戻ってきたときは、送信された参加者をそのまま復元する。
    // 全員のチェックを外して送信すると members 自体が飛んでこないので、
    // old() の有無ではなくエラーの有無で判定する。
    $oldMembers = $errors->any() ? array_map('strval', (array) old('members', [])) : null;
@endphp

@csrf

{{-- プロジェクト名 --}}
<div>
    <x-input-label for="name" value="プロジェクト名" :required="true" />
    <x-text-input id="name" name="name" type="text" :value="old('name', $project?->name)"
                  required autofocus maxlength="255" />
    <x-input-error :messages="$errors->get('name')" />
</div>

{{-- アイコン画像。選ぶと表示エリアを決めるポップアップが開き、切り抜いた画像だけを送る（resources/js/image-cropper.js） --}}
<div x-data="imageCropper">
    <x-input-label for="icon" value="アイコン画像" />

    <div class="flex items-center gap-4">
        {{-- 切り抜いたら、保存する前からその画像を見せる --}}
        <img x-show="preview" x-cloak :src="preview" alt="" class="h-16 w-16 shrink-0 rounded-xl object-cover ring-1 ring-slate-200">
        @if ($project)
            <x-project-icon :project="$project" size="lg" x-show="! preview" />
        @endif

        <div class="flex-1">
            <input autocomplete="off" id="icon" name="icon" type="file" accept="image/jpeg,image/png,image/webp" x-ref="input" @change="open($event.target.files)"
                   class="block w-full text-sm text-slate-600 file:mr-3 file:rounded-lg file:border-0 file:bg-sky-50 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-sky-700 hover:file:bg-sky-100">
            <p class="mt-1 text-xs text-slate-500">
                JPEG / PNG / WebP。選んだあとに表示する範囲を調整できます。未設定のときはプロジェクト名の頭文字を表示します。
            </p>
        </div>
    </div>

    <x-image-cropper-dialog />

    <p x-show="error" x-cloak x-text="error" class="mt-2 text-sm text-rose-600"></p>
    <x-input-error :messages="$errors->get('icon')" />

    @if ($project?->icon_path)
        <label class="mt-2 inline-flex cursor-pointer items-center gap-2 text-sm text-slate-600">
            <input autocomplete="off" type="checkbox" name="remove_icon" value="1" class="peer sr-only">
            <x-check-mark />
            アイコン画像を削除する
        </label>
    @endif
</div>

{{-- 参加者。My Project は本人専用なので選ばせない --}}
@if ($project?->isPersonal())
<div>
    <x-input-label value="参加者" />
    <p class="text-sm text-slate-600">My Project はあなた専用のプロジェクトです。他の人は参加できません。</p>
</div>
@else
<div>
    <x-input-label value="参加者" :required="true" />
    <p class="mb-2 text-xs text-slate-500">
        オーナーだけがプロジェクトの編集・アーカイブ・参加者の変更をできます。
    </p>

    @php
        // 行ごとの選択の初期値と、絞り込みに使う文字（名前とメールを小文字にしたもの）を先に決めておく
        $members = $users->map(function ($user) use ($project, $me, $oldMembers) {
            $currentRole = $project?->roleOf($user);

            // 作成時、自分は必ずオーナーとして参加する
            $isCreator = $project === null && $me->is($user);

            return [
                'user' => $user,
                'isCreator' => $isCreator,
                'checked' => $isCreator
                    || ($oldMembers !== null
                        ? in_array((string) $user->id, $oldMembers, true)
                        : $currentRole !== null),
                'role' => $isCreator
                    ? ProjectRole::Owner->value
                    : old("roles.{$user->id}", $currentRole?->value ?? ProjectRole::Member->value),
            ];
        });
    @endphp

    {{-- 人数が多くても探せるよう、名前・メールで絞り込み、選んだ人だけにもできる。
         隠すのは見た目だけなので、隠れた人の選択と権限もそのまま送られる --}}
    <div x-data="{
             q: '',
             onlySelected: false,
             selected: @js($members->mapWithKeys(fn ($m) => [$m['user']->id => $m['checked']])),
             texts: @js($members->mapWithKeys(fn ($m) => [$m['user']->id => mb_strtolower($m['user']->name.' '.$m['user']->email)])),
             get count() { return Object.values(this.selected).filter(Boolean).length },
             visible(id) { return (! this.onlySelected || this.selected[id]) && this.texts[id].includes(this.q.trim().toLowerCase()) },
             get noneShown() { return ! Object.keys(this.texts).some((id) => this.visible(id)) },
         }">
    <div class="mb-2 flex flex-wrap items-center gap-3">
        <div class="relative min-w-0 flex-1">
            <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                <path fill-rule="evenodd" clip-rule="evenodd" d="M9 3.5a5.5 5.5 0 100 11 5.5 5.5 0 000-11zM2 9a7 7 0 1112.452 4.391l3.328 3.329a.75.75 0 11-1.06 1.06l-3.329-3.328A7 7 0 012 9z" />
            </svg>
            {{-- name を付けないので送られない。Enter で送信されないようにする --}}
            <input type="search" x-model="q" @keydown.enter.prevent autocomplete="off" placeholder="名前・メールで絞り込み"
                   aria-label="参加者を名前・メールで絞り込み"
                   class="block w-full rounded-lg border-slate-300 py-1.5 pl-9 text-sm shadow-sm focus:border-sky-500 focus:ring-sky-500">
        </div>
        <label class="inline-flex cursor-pointer items-center gap-2 text-sm text-slate-600">
            <input type="checkbox" x-model="onlySelected" class="rounded border-slate-300 text-sky-600 focus:ring-sky-500">
            選択中のみ表示
        </label>
        <span class="text-xs text-slate-500"><span x-text="count">{{ $members->where('checked', true)->count() }}</span>人を選択中</span>
    </div>

    {{-- 一覧は決まった高さの中でスクロールし、絞り込みの欄と送信ボタンが画面から逃げないようにする。
         行のどこを押しても選べる。選んだ行は色を付け、選んでいない人の権限は薄くする --}}
    <div class="max-h-[26rem] space-y-2 overflow-y-auto overscroll-contain rounded-xl border border-slate-200 bg-slate-50/60 p-2">
        @foreach ($members as ['user' => $user, 'isCreator' => $isCreator, 'checked' => $checked, 'role' => $selectedRole])
            <div x-show="visible({{ $user->id }})"
                 class="flex items-center gap-3 rounded-xl border border-slate-200 bg-white px-3 py-2.5 transition has-[input:checked]:border-sky-300 has-[input:checked]:bg-sky-50/60 has-[input:focus-visible]:ring-2 has-[input:focus-visible]:ring-sky-500/40 [&:not(:has(input:checked))_select]:opacity-50">
                <label for="member-{{ $user->id }}" @class(['flex min-w-0 flex-1 items-center gap-3', 'cursor-pointer' => ! $isCreator])>
                    <input autocomplete="off" type="checkbox" id="member-{{ $user->id }}" name="members[]" value="{{ $user->id }}"
                           @checked($checked) @disabled($isCreator) @change="selected[{{ $user->id }}] = $event.target.checked" class="peer sr-only">
                    <x-check-mark />

                    <x-user-avatar :user="$user" size="sm" />

                    <span class="min-w-0 flex-1">
                        <span class="block truncate text-sm text-slate-800">
                            {{ $user->name }}
                            @if ($me->is($user))
                                <span class="ml-1 text-xs text-slate-400">(自分)</span>
                            @endif
                        </span>
                        <span class="block truncate text-xs text-slate-500">{{ $user->email }}</span>
                    </span>
                </label>

                @if ($isCreator)
                    {{-- disabled な input は送信されないので、値はサーバー側で必ずオーナーに固定する --}}
                    <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium {{ ProjectRole::Owner->badgeClass() }}">
                        オーナー（固定）
                    </span>
                @else
                    {{-- x-select-input は w-full を持っているので、幅は外側の箱で決める --}}
                    <div class="w-32 shrink-0">
                        <x-select-input name="roles[{{ $user->id }}]" :options="$roles"
                                        :selected="$selectedRole" class="py-1 text-sm" />
                    </div>
                @endif
            </div>
        @endforeach
        <p x-show="noneShown" x-cloak class="py-6 text-center text-sm text-slate-500">該当するユーザーがいません。</p>
    </div>
    </div>

    <x-input-error :messages="$errors->get('members')" />
</div>
@endif

<div class="flex items-center gap-3 pt-2">
    <x-primary-button>{{ $submitLabel }}</x-primary-button>
    <a href="{{ $project ? route('projects.show', $project) : route('dashboard') }}"
       class="text-sm text-slate-600 hover:text-slate-900">キャンセル</a>
</div>
