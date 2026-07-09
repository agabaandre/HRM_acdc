<?php

use App\Helpers\PrintHelper;

it('decodes entity-encoded summernote html for the editor', function () {
    $stored = '&lt;p&gt;Background context for the activity.&lt;/p&gt;';
    $prepared = PrintHelper::prepareRichTextForEditor($stored);

    expect($prepared)->toBe('<p>Background context for the activity.</p>');
});

it('wraps plain text without markup in a paragraph', function () {
    expect(PrintHelper::prepareRichTextForEditor('Plain background text'))
        ->toBe('<p>Plain background text</p>');
});

it('strips a summernote note-editable wrapper', function () {
    $stored = '<div class="note-editable"><p>Request for approval details</p></div>';
    $prepared = PrintHelper::prepareRichTextForEditor($stored);

    expect($prepared)->toBe('<p>Request for approval details</p>');
});
