<?php

namespace App\Http\Controllers\User;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * CSV によるユーザーの一括登録（システム管理者のみ）
 *
 * 1行でも問題があれば1件も登録しない。途中まで入ると、直して再度アップロードしたときに
 * 登録済みの行がメールアドレスの重複で弾かれてしまうため。
 */
class UserImportController extends Controller
{
    /**
     * パスワードのハッシュ化に1件あたり時間がかかるので、1回の件数を絞る
     */
    private const MAX_ROWS = 100;

    /**
     * CSV の見出し => users の項目
     */
    private const COLUMNS = [
        '名前' => 'name',
        'メールアドレス' => 'email',
        '権限' => 'role',
        'ステータス' => 'is_active',
    ];

    /**
     * 省略できる見出し
     */
    private const OPTIONAL_COLUMNS = ['権限', 'ステータス'];

    public function create(): View
    {
        return view('users.import', [
            'maxRows' => self::MAX_ROWS,
            'roles' => UserRole::options(),
        ]);
    }

    /**
     * 見出しだけの雛形。Excel で文字化けしないよう BOM を付ける
     */
    public function template(): Response
    {
        return response("\xEF\xBB\xBF".implode(',', array_keys(self::COLUMNS))."\r\n", 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="users.csv"',
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate(
            ['csv' => ['required', 'file', 'extensions:csv', 'max:1024']],
            [],
            ['csv' => 'CSV ファイル'],
        );

        $lines = $this->parse($this->decode($request->file('csv')->getContent()));
        $header = array_map('trim', array_shift($lines) ?? []);

        $missing = array_diff(array_keys(self::COLUMNS), $header, self::OPTIONAL_COLUMNS);

        if ($missing !== []) {
            return back()->withErrors(['csv' => '見出しが足りません: '.implode('、', $missing)]);
        }

        $rows = $this->rows($header, $lines);

        if ($rows === []) {
            return back()->withErrors(['csv' => '登録するユーザーがありません。']);
        }

        if (count($rows) > self::MAX_ROWS) {
            return back()->withErrors(['csv' => '一度に登録できるのは '.self::MAX_ROWS.' 件までです。']);
        }

        $problems = $this->validateRows($rows);

        if ($problems !== []) {
            return back()->with('importProblems', $problems);
        }

        $issued = DB::transaction(fn () => array_map(function (array $row) {
            $user = new User($row);
            $password = $user->issuePassword();

            return [...$user->only('name', 'email'), 'password' => $password];
        }, array_values($rows)));

        return redirect()
            ->route('users.index')
            ->with('status', count($rows).'件のユーザーを登録しました。')
            ->with('issuedPasswords', $issued);
    }

    /**
     * Excel が保存する CSV は Shift_JIS のことが多いので、UTF-8 にそろえる
     */
    private function decode(string $content): string
    {
        if (str_starts_with($content, "\xEF\xBB\xBF")) {
            return substr($content, 3);
        }

        return mb_check_encoding($content, 'UTF-8')
            ? $content
            : mb_convert_encoding($content, 'UTF-8', 'SJIS-win');
    }

    /**
     * @return array<int, array<int, string|null>>
     */
    private function parse(string $content): array
    {
        $stream = fopen('php://temp', 'r+');
        fwrite($stream, $content);
        rewind($stream);

        $lines = [];

        while (($line = fgetcsv($stream, escape: '')) !== false) {
            $lines[] = $line;
        }

        fclose($stream);

        return $lines;
    }

    /**
     * 空行を飛ばして、[CSV の行番号 => users の項目] にする
     *
     * @param  array<int, string>  $header
     * @param  array<int, array<int, string|null>>  $lines
     * @return array<int, array<string, mixed>>
     */
    private function rows(array $header, array $lines): array
    {
        $rows = [];

        foreach ($lines as $index => $line) {
            if (trim(implode('', $line)) === '') {
                continue;
            }

            $values = [];

            foreach (self::COLUMNS as $title => $attribute) {
                $position = array_search($title, $header, true);

                $values[$attribute] = $position === false ? '' : trim($line[$position] ?? '');
            }

            // 見出しが1行目なので、データは2行目から
            $rows[$index + 2] = [
                ...$values,
                'role' => $this->role($values['role']),
                'is_active' => $this->isActive($values['is_active']),
            ];
        }

        return $rows;
    }

    /**
     * 画面の表記でも内部の値でも受け付ける。空なら一般
     */
    private function role(string $value): string
    {
        if ($value === '') {
            return UserRole::General->value;
        }

        $byLabel = array_search($value, UserRole::options(), true);

        return $byLabel === false ? $value : $byLabel;
    }

    /**
     * 空なら有効。それ以外の表記はバリデーションで弾けるようそのまま返す
     */
    private function isActive(string $value): mixed
    {
        return match ($value) {
            '', '有効' => true,
            '無効' => false,
            default => $value,
        };
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, array<int, string>> [行番号 => エラーメッセージ]
     */
    private function validateRows(array $rows): array
    {
        $problems = [];
        $seen = [];

        foreach ($rows as $number => $row) {
            $validator = Validator::make($row, [
                'name' => ['required', 'string', 'max:255'],
                // 削除済みのユーザーも対象（復元できるようメールアドレスを空けない）
                'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')],
                'role' => ['required', Rule::enum(UserRole::class)],
                'is_active' => ['required', 'boolean'],
            ], [], array_flip(self::COLUMNS));

            $messages = $validator->errors()->all();

            $email = mb_strtolower($row['email']);

            if ($email !== '' && isset($seen[$email])) {
                $messages[] = "メールアドレスが {$seen[$email]} 行目と重複しています。";
            }

            $seen[$email] ??= $number;

            if ($messages !== []) {
                $problems[$number] = $messages;
            }
        }

        return $problems;
    }
}
