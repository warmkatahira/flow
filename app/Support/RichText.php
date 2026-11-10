<?php

namespace App\Support;

use Closure;
use Dom\Element;
use Dom\HTMLDocument;
use Dom\Node;
use Dom\Text;

/**
 * 説明とコメントの書式付きの本文（HTML）
 *
 * エディタ（resources/js/rich-text.js）が出す HTML から、許可した書式だけを残して組み立て直す。
 * 段落・改行・太字・斜体・下線・取り消し線と、決まった値の文字色・背景色・大きさだけを通し、
 * それ以外のタグと属性は捨てる（中の文字は残す）。
 */
class RichText
{
    /** 文字の色（名前 => 値）。エディタの色の一覧もここから作る */
    public const TEXT_COLORS = [
        '灰' => '#64748b',
        '赤' => '#dc2626',
        '橙' => '#ea580c',
        '黄' => '#ca8a04',
        '緑' => '#16a34a',
        '青' => '#2563eb',
        '紫' => '#9333ea',
        '桃' => '#db2777',
    ];

    /** 文字の背景色（マーカー） */
    public const BACKGROUND_COLORS = [
        '灰' => '#e2e8f0',
        '赤' => '#fecaca',
        '橙' => '#fed7aa',
        '黄' => '#fef08a',
        '緑' => '#bbf7d0',
        '青' => '#bfdbfe',
        '紫' => '#e9d5ff',
        '桃' => '#fbcfe8',
    ];

    /** 文字の大きさ。周りの文字に対する倍率にして、どこに出しても釣り合うようにする */
    public const FONT_SIZES = [
        '小' => '0.85em',
        '大' => '1.25em',
        '特大' => '1.6em',
    ];

    private const MARKS = [
        'strong' => 'strong',
        'b' => 'strong',
        'em' => 'em',
        'i' => 'em',
        'u' => 'u',
        's' => 's',
        'strike' => 's',
        'del' => 's',
    ];

    /**
     * エディタに渡す選択肢
     *
     * @return array{colors: array<string, string>, backgrounds: array<string, string>, sizes: array<string, string>}
     */
    public static function presets(): array
    {
        return [
            'colors' => self::TEXT_COLORS,
            'backgrounds' => self::BACKGROUND_COLORS,
            'sizes' => self::FONT_SIZES,
        ];
    }

    /**
     * 保存する形に整える。中身が空なら空文字
     *
     * タグを含まない文字列は、書式のない文章として行ごとに段落にする（シーダーやテストから渡す本文、移行前のデータ）。
     */
    public static function sanitize(?string $html): string
    {
        if ($html === null || trim($html) === '') {
            return '';
        }

        if (! str_contains($html, '<')) {
            return self::fromPlainText($html);
        }

        $clean = self::rebuild($html);

        return self::toPlainText($clean) === '' ? '' : $clean;
    }

    /**
     * 表示用の HTML。保存済みの本文ももう一度組み立て直してから出す
     *
     * @param  array<string, string>  $replace  文字の部分の置き換え（エスケープ済みの文字 => HTML）。メンションの色付けに使う
     */
    public static function render(?string $html, array $replace = []): string
    {
        if ($html === null || $html === '') {
            return '';
        }

        if (! str_contains($html, '<')) {
            $html = self::fromPlainText($html);
        }

        // strtr は長い方から置き換えるので、名前が前方一致していても壊れない
        return self::rebuild($html, $replace === [] ? null : fn (string $escaped) => strtr($escaped, $replace));
    }

    /**
     * 書式を外した文章。段落と改行は改行にする
     */
    public static function toPlainText(?string $html): string
    {
        if ($html === null || $html === '') {
            return '';
        }

        if (! str_contains($html, '<')) {
            return trim($html);
        }

        $body = self::parse($html);
        $lines = [];

        foreach ($body->childNodes as $node) {
            $lines[] = self::textOf($node);
        }

        return trim(implode("\n", $lines));
    }

    /**
     * 書式のない文章を、行ごとの段落にする
     */
    public static function fromPlainText(string $text): string
    {
        $lines = preg_split('/\r\n|\r|\n/', rtrim($text));

        return implode('', array_map(fn (string $line) => '<p>'.e($line).'</p>', $lines));
    }

    private static function parse(string $html): Element
    {
        $document = HTMLDocument::createFromString(
            '<!DOCTYPE html><html><head><meta charset="utf-8"></head><body>'.$html.'</body></html>',
            LIBXML_NOERROR,
        );

        return $document->body;
    }

    /**
     * @param  (Closure(string): string)|null  $text
     */
    private static function rebuild(string $html, ?Closure $text = null): string
    {
        $out = '';
        $loose = '';

        foreach (self::parse($html)->childNodes as $node) {
            // 段落の外に出てしまった文字や書式は、まとめて1つの段落に入れる
            if ($node instanceof Element && in_array($node->localName, ['p', 'div'], true)) {
                if ($loose !== '') {
                    $out .= "<p>{$loose}</p>";
                    $loose = '';
                }

                $out .= '<p>'.self::inline($node, $text).'</p>';
            } else {
                $loose .= self::node($node, $text);
            }
        }

        if (trim(strip_tags($loose)) !== '' || str_contains($loose, '<br>')) {
            $out .= "<p>{$loose}</p>";
        }

        // 末尾の空の段落は落とす（エディタで改行だけ残したとき）
        return preg_replace('#(<p></p>)+$#', '', $out);
    }

    /**
     * @param  (Closure(string): string)|null  $text
     */
    private static function inline(Node $parent, ?Closure $text): string
    {
        $out = '';

        foreach ($parent->childNodes as $child) {
            $out .= self::node($child, $text);
        }

        return $out;
    }

    /**
     * @param  (Closure(string): string)|null  $text
     */
    private static function node(Node $node, ?Closure $text): string
    {
        if ($node instanceof Text) {
            $escaped = e($node->data);

            return $text ? $text($escaped) : $escaped;
        }

        if (! $node instanceof Element) {
            return '';
        }

        $name = $node->localName;

        // 中身ごと捨てるもの
        if (in_array($name, ['script', 'style', 'template', 'iframe', 'object', 'embed', 'svg', 'math', 'head', 'title'], true)) {
            return '';
        }

        if ($name === 'br') {
            return '<br>';
        }

        $inner = self::inline($node, $text);

        // 段落の中に入れ子の段落が来たら改行にする
        if (in_array($name, ['p', 'div'], true)) {
            return $inner === '' ? '' : $inner.'<br>';
        }

        if (isset(self::MARKS[$name])) {
            $tag = self::MARKS[$name];

            return $inner === '' ? '' : "<{$tag}>{$inner}</{$tag}>";
        }

        if ($name === 'span') {
            $style = self::style($node->getAttribute('style') ?? '');

            return $style === '' || $inner === '' ? $inner : '<span style="'.$style.'">'.$inner.'</span>';
        }

        // それ以外のタグは外して中の文字だけ残す
        return $inner;
    }

    /**
     * style のうち、決まった値の色・背景色・大きさだけを残す
     */
    private static function style(string $style): string
    {
        $kept = [];

        foreach (explode(';', $style) as $declaration) {
            if (! str_contains($declaration, ':')) {
                continue;
            }

            [$property, $value] = array_map(fn (string $part) => strtolower(trim($part)), explode(':', $declaration, 2));

            $allowed = match ($property) {
                'color' => self::TEXT_COLORS,
                'background-color' => self::BACKGROUND_COLORS,
                'font-size' => self::FONT_SIZES,
                default => [],
            };

            $value = $property === 'font-size' ? $value : self::hex($value);

            if ($value !== null && in_array($value, $allowed, true)) {
                $kept[$property] = $value;
            }
        }

        return implode('; ', array_map(fn (string $property, string $value) => "{$property}: {$value}", array_keys($kept), $kept));
    }

    /**
     * #rgb / #rrggbb / rgb(r, g, b) を #rrggbb にそろえる
     */
    private static function hex(string $value): ?string
    {
        if (preg_match('/^#([0-9a-f]{6})$/', $value, $match)) {
            return "#{$match[1]}";
        }

        if (preg_match('/^#([0-9a-f])([0-9a-f])([0-9a-f])$/', $value, $match)) {
            return "#{$match[1]}{$match[1]}{$match[2]}{$match[2]}{$match[3]}{$match[3]}";
        }

        if (preg_match('/^rgba?\(\s*(\d{1,3})\s*,\s*(\d{1,3})\s*,\s*(\d{1,3})\s*(,\s*1(\.0+)?\s*)?\)$/', $value, $match)) {
            return sprintf('#%02x%02x%02x', min(255, (int) $match[1]), min(255, (int) $match[2]), min(255, (int) $match[3]));
        }

        return null;
    }

    private static function textOf(Node $node): string
    {
        if ($node instanceof Text) {
            return $node->data;
        }

        if (! $node instanceof Element) {
            return '';
        }

        if ($node->localName === 'br') {
            return "\n";
        }

        if (in_array($node->localName, ['script', 'style', 'template'], true)) {
            return '';
        }

        $text = '';

        foreach ($node->childNodes as $child) {
            $text .= self::textOf($child);
        }

        return $text;
    }
}
