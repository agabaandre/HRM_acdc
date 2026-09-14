<?php

namespace Tests\Unit;

use App\Support\RichTextDataUriExternalizer;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class RichTextDataUriExternalizerTest extends TestCase
{

    private function tinyPngDataUri(): string
    {
        $base64 = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';

        return 'data:image/png;base64,'.$base64;
    }

    public function test_externalizes_data_uri_to_summernote_storage_url(): void
    {
        Storage::fake('public');

        $html = '<p>Memo body</p><img src="'.$this->tinyPngDataUri().'" alt="shot">';

        $clean = RichTextDataUriExternalizer::externalize($html);

        $this->assertIsString($clean);
        $this->assertStringNotContainsString('data:image', $clean);
        $this->assertStringContainsString('/storage/uploads/summernote/', $clean);
        $files = Storage::disk('public')->allFiles('uploads/summernote');
        $this->assertCount(1, $files);
    }
}
