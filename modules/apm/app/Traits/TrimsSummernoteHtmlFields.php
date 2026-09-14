<?php

namespace App\Traits;

use App\Helpers\PrintHelper;
use App\Support\RichTextDataUriExternalizer;

/**
 * Trims leading/trailing whitespace and invisible characters from Summernote HTML fields on save.
 * Also externalizes pasted base64 images to uploaded file URLs.
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
                if (! is_string($val) || $val === '') {
                    continue;
                }
                $prepared = PrintHelper::trimRichTextInput(
                    RichTextDataUriExternalizer::externalize($val)
                );
                if ($prepared !== $val) {
                    $model->setAttribute($attr, $prepared);
                }
            }
        });
    }
}
