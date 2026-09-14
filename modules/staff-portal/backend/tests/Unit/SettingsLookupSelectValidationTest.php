<?php

namespace Tests\Unit;

use Modules\Settings\Services\SettingsLookupService;
use PHPUnit\Framework\TestCase;

class SettingsLookupSelectValidationTest extends TestCase
{
    /**
     * @return array<string, mixed>
     */
    private function contractTypeColumns(): array
    {
        $tables = require dirname(__DIR__, 2).'/Modules/Settings/config/lookup-tables.php';

        return $tables['contract_types']['columns'];
    }

    public function test_rejects_category_that_is_not_an_option_key(): void
    {
        $errors = SettingsLookupService::selectValueErrors($this->contractTypeColumns(), [
            'contract_type' => 'Temp',
            'category' => 'contractor',
        ]);

        $this->assertArrayHasKey('category', $errors);
    }

    public function test_accepts_main_staff_and_other_staff_category(): void
    {
        $columns = $this->contractTypeColumns();

        $this->assertSame([], SettingsLookupService::selectValueErrors($columns, [
            'contract_type' => 'Temp',
            'category' => 'main_staff',
        ]));
        $this->assertSame([], SettingsLookupService::selectValueErrors($columns, [
            'contract_type' => 'Temp',
            'category' => 'other_staff',
        ]));
    }
}
