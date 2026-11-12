<?php

namespace App\Http\Controllers\Task;

use App\Enums\TaskStatus;
use App\Http\Controllers\Controller;
use App\Models\Task;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * タスクの検索（ナビの検索欄）
 *
 * 参加しているプロジェクトのボードに出ているタスクから探す。言葉はスペースで区切ると AND。
 * それぞれの言葉は、タイトルかプロジェクト名に含まれるか、番号（「12」「#12」）が一致すればよい。
 */
class TaskSearchController extends Controller
{
    /** 出す件数の上限 */
    public const LIMIT = 20;

    public function __invoke(Request $request): View
    {
        $words = self::words((string) $request->query('q', ''));

        $tasks = $words === []
            ? collect()
            : Task::query()
                ->onBoardFor($request->user())
                ->where(function (Builder $query) use ($words) {
                    foreach ($words as $word) {
                        $query->where(fn (Builder $query) => self::matchWord($query, $word));
                    }
                })
                ->with(['project', 'list'])
                // 完了・中止は後ろに回し、その中では最近動いたものから
                ->orderByRaw('tasks.status in (?, ?)', [TaskStatus::Done->value, TaskStatus::Canceled->value])
                ->orderByDesc('tasks.updated_at')
                ->orderByDesc('tasks.id')
                ->limit(self::LIMIT)
                ->get();

        return view('tasks.partials.search-results', [
            'tasks' => $tasks,
            'searched' => $words !== [],
        ]);
    }

    /**
     * 検索の言葉（全角スペースでも区切る。長すぎる入力は切る）
     *
     * @return list<string>
     */
    private static function words(string $q): array
    {
        $q = Str::limit(trim($q), 100, '');

        return array_values(array_filter(preg_split('/[\s\x{3000}]+/u', $q) ?: [], fn ($word) => $word !== ''));
    }

    private static function matchWord(Builder $query, string $word): void
    {
        // 「#12」は番号だけで探す
        if (preg_match('/^[#＃](\d+)$/u', $word, $matches)) {
            $query->where('tasks.number', (int) $matches[1]);

            return;
        }

        $like = '%'.addcslashes($word, '\\%_').'%';

        $query->where('tasks.title', 'like', $like)
            ->orWhereHas('project', fn (Builder $query) => $query->where('name', 'like', $like));

        if (ctype_digit($word)) {
            $query->orWhere('tasks.number', (int) $word);
        }
    }
}
