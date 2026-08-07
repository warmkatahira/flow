<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Blade;

class GenerateFaviconCommand extends Command
{
    protected $signature = 'flow:favicon';

    protected $description = 'ロゴマークのコンポーネントから favicon の SVG を書き出す';

    /**
     * マークの形は resources/views/components/flow-mark.blade.php にしか書かれていない。
     * 形を直したらこのコマンドを流し直せば favicon 側も追従する。
     *
     * favicon.ico と apple-touch-icon.png はここでは作らない（SVG のラスタライズが必要なため）。
     * 形を変えたときは書き出した SVG から作り直すこと。
     */
    public function handle(): int
    {
        $badge = $this->tidy(Blade::render('<x-flow-mark variant="badge" standalone />'));

        $files = [
            // モダンブラウザ向け。角丸のバッジ
            'favicon.svg' => $badge,
            // apple-touch-icon / PWA アイコンの元。角丸は OS 側が付けるので四角のまま
            'favicon-square.svg' => str_replace('rx="7.5"', 'rx="0"', $badge),
        ];

        foreach ($files as $name => $svg) {
            file_put_contents(public_path($name), $svg);
            $this->info("書き出しました: public/{$name}");
        }

        return self::SUCCESS;
    }

    /**
     * Blade の出力をそのまま置くと、余分な空白と毎回変わる id で差分が出続ける。
     * 中身が同じなら同じファイルになるように整える。
     */
    private function tidy(string $svg): string
    {
        $svg = preg_replace('/flow-mark-[0-9a-f]+/', 'flow', $svg);
        $svg = preg_replace('/\s+class=""/', '', $svg);
        $svg = preg_replace('/\s+/', ' ', $svg);
        $svg = preg_replace('/>\s+</', '><', $svg);
        $svg = preg_replace('/\s+(\/?)>/', '$1>', $svg);

        return "<!-- php artisan flow:favicon で生成。直接編集せず flow-mark.blade.php を直すこと -->\n"
            .trim($svg)."\n";
    }
}
