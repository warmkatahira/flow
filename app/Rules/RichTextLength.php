<?php

namespace App\Rules;

use App\Support\RichText;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * 書式付きの本文を、書式を外した文字数で確かめる（タグの分は数えない）
 *
 * required のときは、書式だけで文字のない本文（空の段落など）も空として扱う。
 */
class RichTextLength implements ValidationRule
{
    public function __construct(
        private int $max = 10000,
        private bool $required = false,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $text = RichText::toPlainText(RichText::sanitize(is_string($value) ? $value : ''));

        if ($this->required && trim($text) === '') {
            $fail('validation.required')->translate();

            return;
        }

        if (mb_strlen($text) > $this->max) {
            $fail('validation.max.string')->translate(['max' => $this->max]);
        }
    }
}
