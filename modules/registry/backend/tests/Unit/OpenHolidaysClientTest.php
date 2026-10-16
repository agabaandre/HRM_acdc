<?php

namespace Tests\Unit;

use Illuminate\Support\Facades\Http;
use Modules\Leave\Services\OpenHolidaysClient;
use Tests\TestCase;

class OpenHolidaysClientTest extends TestCase
{
    public function test_maps_nationwide_english_names_and_marks_eid_movable(): void
    {
        Http::fake([
            'openholidaysapi.org/PublicHolidays*' => Http::response([
                [
                    'id' => 'aaa-111',
                    'nationwide' => true,
                    'startDate' => '2026-12-25',
                    'name' => [
                        ['language' => 'EN', 'text' => 'Christmas Day'],
                        ['language' => 'AF', 'text' => 'Kersdag'],
                    ],
                ],
                [
                    'id' => 'bbb-222',
                    'nationwide' => true,
                    'startDate' => '2026-03-20',
                    'name' => [
                        ['language' => 'EN', 'text' => 'Eid al-Fitr'],
                    ],
                ],
                [
                    'id' => 'ccc-333',
                    'nationwide' => false,
                    'startDate' => '2026-01-02',
                    'name' => [
                        ['language' => 'EN', 'text' => 'Regional only'],
                    ],
                ],
            ], 200),
        ]);

        $rows = app(OpenHolidaysClient::class)->publicHolidays('ZA', 2026);

        $this->assertCount(2, $rows);
        $this->assertSame('Christmas Day', $rows[0]['name']);
        $this->assertSame('yearly_md', $rows[0]['recurrence']);
        $this->assertFalse($rows[0]['is_movable']);
        $this->assertSame('Eid al-Fitr', $rows[1]['name']);
        $this->assertSame('once', $rows[1]['recurrence']);
        $this->assertTrue($rows[1]['is_movable']);
    }
}
