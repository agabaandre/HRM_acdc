<?php

use App\Helpers\PrintHelper;

it('detects visible rich text and ignores empty summernote blocks', function () {
    expect(PrintHelper::richTextHasVisibleContent('<p>Background details</p>'))->toBeTrue()
        ->and(PrintHelper::richTextHasVisibleContent('<p><br></p>'))->toBeFalse()
        ->and(PrintHelper::richTextHasVisibleContent('<p><span style="color:#ffffff">Hidden</span></p>'))->toBeTrue();
});

it('renders white pasted text as readable on screen', function () {
    $html = '<p><span style="color: rgb(255, 255, 255);">Approval request</span></p>';
    $display = PrintHelper::sanitizeRichTextForDisplay($html);

    expect($display)->toContain('Approval request')
        ->and($display)->toContain('rgba(0, 0, 0, 0.87)')
        ->and($display)->not->toContain('rgb(255, 255, 255)');
});

it('falls back when dom sanitization strips visible content', function () {
    $html = '<table><tr><td>Cell value</td></tr></table>';
    $display = PrintHelper::sanitizeRichTextForDisplay($html);

    expect(PrintHelper::richTextHasVisibleContent($html))->toBeTrue()
        ->and($display)->toContain('Cell value');
});
