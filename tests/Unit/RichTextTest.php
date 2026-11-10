<?php

namespace Tests\Unit;

use App\Support\RichText;
use PHPUnit\Framework\TestCase;

class RichTextTest extends TestCase
{
    public function test_文字の装飾はそのまま残る(): void
    {
        $html = '<p><strong>太</strong><em>斜</em><u>下</u><s>消</s><span style="color: #dc2626; background-color: #fef08a; font-size: 1.25em">色</span></p>';

        $this->assertSame($html, RichText::sanitize($html));
    }

    public function test_許可していないタグと属性は外して文字だけ残す(): void
    {
        $this->assertSame(
            '<p>見出しリンク<strong>太字</strong></p>',
            RichText::sanitize('<h1 onclick="x()">見出し</h1><a href="javascript:alert(1)">リンク</a><b class="x">太字</b><script>alert(1)</script>'),
        );
    }

    public function test_決まった値以外の色と大きさは捨てる(): void
    {
        $this->assertSame(
            '<p>色</p>',
            RichText::sanitize('<p><span style="color: #123456; font-size: 40px; position: fixed">色</span></p>'),
        );
    }

    public function test_rgbで書かれた色も決まった値ならそろえて残す(): void
    {
        $this->assertSame(
            '<p><span style="color: #dc2626">赤</span></p>',
            RichText::sanitize('<p><span style="color: rgb(220, 38, 38)">赤</span></p>'),
        );
    }

    public function test_タグのない文章は行ごとの段落にしてエスケープする(): void
    {
        $this->assertSame('<p>1行目</p><p></p><p>a &amp; b</p>', RichText::sanitize("1行目\n\na & b"));
    }

    public function test_文字のない本文は空にする(): void
    {
        $this->assertSame('', RichText::sanitize('<p></p><p><strong></strong></p>'));
        $this->assertSame('', RichText::sanitize(null));
    }

    public function test_書式を外した文章にできる(): void
    {
        $this->assertSame("1行目\n2行目<b>\na&b", RichText::toPlainText('<p>1行目</p><p>2行目&lt;b&gt;<br>a&amp;b</p>'));
    }

    public function test_表示するときに文字の部分を置き換えられる(): void
    {
        $this->assertSame(
            '<p><strong><mark>@名前</mark> さん</strong></p>',
            RichText::render('<p><strong>@名前 さん</strong></p>', ['@名前' => '<mark>@名前</mark>']),
        );
    }
}
