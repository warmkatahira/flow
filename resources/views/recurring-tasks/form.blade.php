{{--
    定期タスクの作成・編集。上にひな形（作るタスクの中身）、下に繰り返しの設定を並べる
    繰り返し方に応じて出す項目を変えるのと、チェックリストの行の増減は Alpine で行う（送信はふつうのフォーム）
--}}
@php
    use App\Enums\HolidayRule;
    use App\Enums\RecurrenceFrequency;
    use App\Models\RecurringTask;

    $editing = $recurring->exists;
    $weekdayNames = [1 => '月', 2 => '火', 3 => '水', 4 => '木', 5 => '金', 6 => '土', 7 => '日'];
    $value = fn (string $key) => old($key, $recurring->{$key} instanceof \BackedEnum ? $recurring->{$key}->value : $recurring->{$key});
    $ids = fn (string $key) => collect(old($key, $recurring->{$key} ?? []))->map(fn ($id) => (int) $id)->all();
    $checklists = collect(old('checklists', $recurring->checklists ?? []))->map(fn ($checklist) => [
        'title' => $checklist['title'] ?? '',
        'items' => collect($checklist['items'] ?? [])->map(fn ($item) => [
            'title' => $item['title'] ?? '',
            'assignee_id' => isset($item['assignee_id']) ? (string) $item['assignee_id'] : '',
        ])->values()->all(),
    ])->values()->all();

    $labelClass = 'block text-sm text-slate-600';
    $inputClass = 'mt-1.5 block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-800 focus:border-sky-500 focus:ring-2 focus:ring-sky-500/30 focus:outline-none';
    $smallInputClass = 'rounded-lg border border-slate-300 px-2.5 py-1.5 text-sm text-slate-800 focus:border-sky-500 focus:ring-2 focus:ring-sky-500/30 focus:outline-none';
    // relative は、中の見えないチェックボックス（sr-only）をラベルの位置に置くため。ないと離れた場所に置かれ、
    // 押したときにフォーカスがそこへ移って、画面がスクロールしてしまう
    $chipClass = 'relative inline-flex cursor-pointer items-center gap-1.5 rounded-full border border-slate-200 bg-white px-3 py-1 text-sm text-slate-700 transition hover:border-sky-300 has-[:checked]:border-sky-500 has-[:checked]:bg-sky-50 has-[:checked]:text-sky-800';
    $sectionClass = 'rounded-xl bg-white px-6 py-6 shadow-sm ring-1 ring-slate-200';
@endphp

<x-layouts.app :title="$project->name.' の定期タスク'" fill sidebar>
    <x-project-header :project="$project" active="recurring" :archive-count="$archiveCount" />

    <div class="min-h-0 flex-1 overflow-y-auto">
        <div class="mx-auto max-w-3xl px-4 py-8 sm:px-6 lg:px-8">
            <a href="{{ route('recurring-tasks.index', $project) }}" class="text-sm text-sky-700 hover:underline">← 定期タスクの一覧へ</a>
            <h1 class="mt-2 text-lg text-slate-800">{{ $editing ? '定期タスクを編集' : '定期タスクを作る' }}</h1>

            @if ($errors->any())
                <div class="mt-4 rounded-lg bg-rose-50 px-4 py-3 text-sm text-rose-700" role="alert">
                    入力に誤りがあります。赤字の項目を確かめてください。
                </div>
            @endif

            <form autocomplete="off" method="POST" class="mt-6 space-y-6"
                  action="{{ $editing ? route('recurring-tasks.update', $recurring) : route('recurring-tasks.store', $project) }}"
                  x-data="{
                      frequency: @js($value('frequency')),
                      holidayRule: @js($value('holiday_rule')),
                      checklists: @js($checklists),
                      get canShift() { return ! ['daily', 'weekdays'].includes(this.frequency) },
                      addChecklist() { this.checklists.push({ title: '', items: [] }); this.$nextTick(() => this.$refs.checklists.querySelector('[data-checklist]:last-child input')?.focus()) },
                      addItem(checklist) { checklist.items.push({ title: '', assignee_id: '' }) },
                  }"
                  x-effect="if (! canShift && ['before', 'after'].includes(holidayRule)) holidayRule = 'keep'">
                @csrf
                @if ($editing)
                    @method('PUT')
                @endif

                {{-- ひな形 --}}
                <section class="{{ $sectionClass }} space-y-5">
                    <h2 class="text-sm font-bold text-slate-500">作るタスク</h2>

                    <div>
                        <label for="title" class="{{ $labelClass }}">タイトル</label>
                        <input id="title" type="text" name="title" value="{{ $value('title') }}" required maxlength="255" class="{{ $inputClass }}">
                        <p class="mt-1.5 text-xs text-slate-500">
                            作る日を入れられます:
                            @foreach (RecurringTask::PLACEHOLDERS as $placeholder)
                                <code class="rounded bg-slate-100 px-1 py-0.5 text-slate-700">{{ $placeholder }}</code>
                            @endforeach
                            （例: 「月次締め（{年月}）」→「月次締め（{{ today()->format('Y年n月') }}）」）
                        </p>
                        <x-input-error :messages="$errors->get('title')" class="mt-1.5" />
                    </div>

                    <div x-data="richField(@js($value('description') ?? ''))">
                        <span class="{{ $labelClass }}">説明</span>
                        <input type="hidden" name="description" x-ref="input" value="{{ $value('description') }}">
                        <div class="mt-1.5 rounded-lg border border-slate-300 focus-within:border-sky-500 focus-within:ring-2 focus-within:ring-sky-500/30">
                            <div class="border-b border-slate-200 px-1.5 py-1">
                                @include('projects.partials.rich-text-toolbar')
                            </div>
                            <div x-ref="editor" class="px-3 py-2 text-sm text-slate-700"></div>
                        </div>
                        <x-input-error :messages="$errors->get('description')" class="mt-1.5" />
                    </div>

                    <div class="grid gap-5 sm:grid-cols-2">
                        <div>
                            <label for="task_list_id" class="{{ $labelClass }}">入れるリスト</label>
                            <select id="task_list_id" name="task_list_id" required class="{{ $inputClass }}">
                                @foreach ($lists as $list)
                                    <option value="{{ $list->id }}" @selected((int) $value('task_list_id') === $list->id)>{{ $list->name }}</option>
                                @endforeach
                            </select>
                            <p class="mt-1.5 text-xs text-slate-500">リストがなくなっていたら、先頭のリストに入れます。</p>
                            <x-input-error :messages="$errors->get('task_list_id')" class="mt-1.5" />
                        </div>

                        <div>
                            <label for="due_days" class="{{ $labelClass }}">期限</label>
                            <div class="mt-1.5 flex items-center gap-2 text-sm text-slate-600">
                                作った日から
                                <input id="due_days" type="number" name="due_days" value="{{ $value('due_days') }}" min="0" max="365" inputmode="numeric"
                                       class="{{ $smallInputClass }} w-20">
                                日後
                            </div>
                            <p class="mt-1.5 text-xs text-slate-500">開始日は作った日です。空なら終了日を入れません（0 ならその日のうち）。</p>
                            <x-input-error :messages="$errors->get('due_days')" class="mt-1.5" />
                        </div>
                    </div>

                    <fieldset>
                        <legend class="{{ $labelClass }}">タグ</legend>
                        <div class="mt-1.5 flex flex-wrap gap-2">
                            @forelse ($labels as $label)
                                <label class="{{ $chipClass }}">
                                    <input type="checkbox" name="label_ids[]" value="{{ $label->id }}" @checked(in_array($label->id, $ids('label_ids'), true)) class="sr-only">
                                    <span class="h-2.5 w-2.5 rounded-full" style="background-color: {{ $label->color }}"></span>
                                    {{ $label->name }}
                                </label>
                            @empty
                                <p class="text-sm text-slate-400">タグはまだありません。</p>
                            @endforelse
                        </div>
                    </fieldset>

                    @foreach (['assignee_ids' => '担当者', 'follower_ids' => 'フォローする人'] as $key => $legend)
                        <fieldset>
                            <legend class="{{ $labelClass }}">{{ $legend }}</legend>
                            <div class="mt-1.5 flex flex-wrap gap-2">
                                @foreach ($members as $member)
                                    <label class="{{ $chipClass }} pl-1.5">
                                        <input type="checkbox" name="{{ $key }}[]" value="{{ $member->id }}" @checked(in_array($member->id, $ids($key), true)) class="sr-only">
                                        <x-user-avatar :user="$member" size="xxs" />
                                        {{ $member->name }}
                                    </label>
                                @endforeach
                            </div>
                            @if ($key === 'follower_ids')
                                <p class="mt-1.5 text-xs text-slate-500">作ったタスクに動きがあると、この人たちに通知が届きます。</p>
                            @endif
                        </fieldset>
                    @endforeach

                    {{-- チェックリスト。チェックはすべて外した状態で作る --}}
                    <div>
                        <span class="{{ $labelClass }}">チェックリスト</span>
                        <div x-ref="checklists" class="mt-1.5 space-y-3">
                            <template x-for="(checklist, c) in checklists" :key="c">
                                <div data-checklist class="rounded-lg border border-slate-200 bg-slate-50/60 p-3">
                                    <div class="flex items-center gap-2">
                                        <input type="text" :name="`checklists[${c}][title]`" x-model="checklist.title" required maxlength="255" placeholder="チェックリストの名前"
                                               class="{{ $smallInputClass }} min-w-0 flex-1 bg-white">
                                        <button type="button" @click="checklists.splice(c, 1)" class="rounded-lg px-2 py-1 text-xs text-rose-600 hover:bg-rose-50">削除</button>
                                    </div>
                                    <ul class="mt-2 space-y-1.5 pl-4">
                                        <template x-for="(item, i) in checklist.items" :key="i">
                                            <li class="flex items-center gap-2">
                                                <span class="h-3.5 w-3.5 shrink-0 rounded border-[1.5px] border-slate-400" aria-hidden="true"></span>
                                                <input type="text" :name="`checklists[${c}][items][${i}][title]`" x-model="item.title" required maxlength="255" placeholder="項目"
                                                       class="{{ $smallInputClass }} min-w-0 flex-1 bg-white">
                                                <select :name="`checklists[${c}][items][${i}][assignee_id]`" x-model="item.assignee_id" aria-label="項目の担当者"
                                                        class="{{ $smallInputClass }} w-36 bg-white">
                                                    <option value="">担当者なし</option>
                                                    @foreach ($members as $member)
                                                        <option value="{{ $member->id }}">{{ $member->name }}</option>
                                                    @endforeach
                                                </select>
                                                <button type="button" @click="checklist.items.splice(i, 1)" aria-label="項目を削除" class="rounded p-1 text-slate-400 hover:bg-slate-100 hover:text-rose-600">✕</button>
                                            </li>
                                        </template>
                                    </ul>
                                    <button type="button" @click="addItem(checklist)" class="ml-4 mt-2 text-xs text-sky-700 hover:underline">＋ 項目を追加</button>
                                </div>
                            </template>
                        </div>
                        <button type="button" @click="addChecklist()" class="mt-2 text-sm text-sky-700 hover:underline">＋ チェックリストを追加</button>
                        <x-input-error :messages="collect($errors->getMessages())->filter(fn ($messages, $key) => str_starts_with($key, 'checklists'))->flatten()->unique()->all()" class="mt-1.5" />
                    </div>
                </section>

                {{-- 繰り返し --}}
                <section class="{{ $sectionClass }} space-y-5">
                    <h2 class="text-sm font-bold text-slate-500">繰り返し</h2>

                    <div class="flex flex-wrap items-end gap-3">
                        <div>
                            <label for="frequency" class="{{ $labelClass }}">作る日</label>
                            <select id="frequency" name="frequency" x-model="frequency" class="{{ $smallInputClass }} mt-1.5 w-48">
                                @foreach (RecurrenceFrequency::options() as $key => $label)
                                    <option value="{{ $key }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>

                        {{-- 毎週: 曜日 --}}
                        <div x-show="frequency === 'weekly'" x-cloak class="flex flex-wrap gap-1.5" role="group" aria-label="曜日">
                            @foreach ($weekdayNames as $day => $name)
                                <label class="{{ $chipClass }} h-9 w-9 justify-center px-0">
                                    <input type="checkbox" name="weekdays[]" value="{{ $day }}" @checked(in_array($day, $ids('weekdays'), true))
                                           :disabled="frequency !== 'weekly'" class="sr-only">
                                    {{ $name }}
                                </label>
                            @endforeach
                        </div>

                        {{-- 毎年: 月 --}}
                        <div x-show="frequency === 'yearly'" x-cloak>
                            <select name="month" aria-label="月" :disabled="frequency !== 'yearly'" class="{{ $smallInputClass }}">
                                @foreach (range(1, 12) as $month)
                                    <option value="{{ $month }}" @selected((int) $value('month') === $month)>{{ $month }}月</option>
                                @endforeach
                            </select>
                        </div>

                        {{-- 毎月・毎年: 日 --}}
                        <div x-show="['monthly_day', 'yearly'].includes(frequency)" x-cloak>
                            <select name="month_day" aria-label="日" :disabled="! ['monthly_day', 'yearly'].includes(frequency)" class="{{ $smallInputClass }}">
                                @foreach (range(1, 30) as $day)
                                    <option value="{{ $day }}" @selected((int) $value('month_day') === $day)>{{ $day }}日</option>
                                @endforeach
                                <option value="31" @selected((int) $value('month_day') === 31)>末日</option>
                            </select>
                        </div>

                        {{-- 毎月: 第n◯曜日 --}}
                        <div x-show="frequency === 'monthly_nth'" x-cloak class="flex gap-2">
                            <select name="nth" aria-label="第何週" :disabled="frequency !== 'monthly_nth'" class="{{ $smallInputClass }}">
                                @foreach ([1 => '第1', 2 => '第2', 3 => '第3', 4 => '第4', 5 => '最終'] as $nth => $name)
                                    <option value="{{ $nth }}" @selected((int) $value('nth') === $nth)>{{ $name }}</option>
                                @endforeach
                            </select>
                            <select name="nth_weekday" aria-label="曜日" :disabled="frequency !== 'monthly_nth'" class="{{ $smallInputClass }}">
                                @foreach ($weekdayNames as $day => $name)
                                    <option value="{{ $day }}" @selected((int) $value('nth_weekday') === $day)>{{ $name }}曜</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <p x-show="['monthly_day', 'yearly'].includes(frequency)" x-cloak class="-mt-3 text-xs text-slate-500">31日や2月29日のように、その月にない日は月末に作ります。</p>
                    <x-input-error :messages="[...$errors->get('frequency'), ...$errors->get('weekdays'), ...$errors->get('month_day'), ...$errors->get('month'), ...$errors->get('nth'), ...$errors->get('nth_weekday')]" class="mt-1.5" />

                    <fieldset>
                        <legend class="{{ $labelClass }}">土日・祝日に当たったとき</legend>
                        <div class="mt-1.5 flex flex-wrap gap-2">
                            @foreach (HolidayRule::cases() as $rule)
                                <label class="{{ $chipClass }}" @if ($rule->shifts()) x-show="canShift" @endif>
                                    <input type="radio" name="holiday_rule" value="{{ $rule->value }}" x-model="holidayRule" class="sr-only">
                                    {{ $rule->label() }}
                                </label>
                            @endforeach
                        </div>
                        <p class="mt-1.5 text-xs text-slate-500">祝日は振替休日・国民の休日も含みます。毎日・平日のときは「そのまま作る」か「作らない」から選びます。</p>
                        <x-input-error :messages="$errors->get('holiday_rule')" class="mt-1.5" />
                    </fieldset>

                    <div class="grid gap-5 sm:grid-cols-2">
                        <div>
                            <label for="starts_on" class="{{ $labelClass }}">作り始める日</label>
                            <input id="starts_on" type="date" name="starts_on" value="{{ old('starts_on', $recurring->starts_on?->format('Y-m-d')) }}" required class="{{ $inputClass }}">
                            <x-input-error :messages="$errors->get('starts_on')" class="mt-1.5" />
                        </div>
                        <div>
                            <label for="ends_on" class="{{ $labelClass }}">終わる日（任意）</label>
                            <input id="ends_on" type="date" name="ends_on" value="{{ old('ends_on', $recurring->ends_on?->format('Y-m-d')) }}" class="{{ $inputClass }}">
                            <p class="mt-1.5 text-xs text-slate-500">空なら、一時停止か削除をするまで続けます。</p>
                            <x-input-error :messages="$errors->get('ends_on')" class="mt-1.5" />
                        </div>
                    </div>

                    @if ($editing && ! $recurring->isPaused() && ($upcoming = $recurring->upcoming(3)))
                        <p class="rounded-lg bg-sky-50 px-4 py-3 text-sm text-sky-900">
                            今の設定で次に作る日:
                            {{ collect($upcoming)->map(fn ($date) => $date->locale('ja')->isoFormat('YYYY/M/D(ddd)'))->join('、') }}
                        </p>
                    @endif
                </section>

                <div class="flex items-center justify-end gap-3">
                    <a href="{{ route('recurring-tasks.index', $project) }}" class="rounded-lg px-4 py-2 text-sm text-slate-600 hover:bg-slate-100">キャンセル</a>
                    <button type="submit" class="rounded-lg bg-sky-600 px-6 py-2 text-sm font-semibold text-white transition hover:bg-sky-700">
                        {{ $editing ? '更新する' : '作る' }}
                    </button>
                </div>
            </form>
        </div>
    </div>
</x-layouts.app>
