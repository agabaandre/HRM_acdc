<?php

namespace App\Traits;

use Illuminate\Support\Str;

/**
 * Trims leading/trailing whitespace and invisible characters from Summernote HTML fields on save.
 *
 * @see \App\Helpers\PrintHelper::trimRichTextInput()
 */
trait TrimsSummernoteHtmlFields
{
    /**
     * Attribute names that store HTML from Summernote (copy/paste often adds outer spaces).
     *
     * @return array<int, string>
     */
    protected function summernoteHtmlFieldsToTrim(): array
    {
        return [];
    }

    protected static function bootTrimsSummernoteHtmlFields(): void
    {
        static::saving(function ($model) {
            foreach ($model->summernoteHtmlFieldsToTrim() as $attr) {
                $val = $model->getAttribute($attr);
                if (!is_string($val) || $val === '') {
                    continue;
                }
                $trimmed = Str::trim($val);
                if ($trimmed !== $val) {
                    $model->setAttribute($attr, $trimmed);
                }
            }
        });
    }
}
