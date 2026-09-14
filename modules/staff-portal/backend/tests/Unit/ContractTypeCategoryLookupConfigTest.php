<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class ContractTypeCategoryLookupConfigTest extends TestCase
{
    public function test_contract_types_lookup_includes_category_select(): void
    {
        $tables = require dirname(__DIR__, 2).'/Modules/Settings/config/lookup-tables.php';

        $this->assertArrayHasKey('contract_types', $tables);
        $this->assertSame('Contract Types', $tables['contract_types']['label']);
        $this->assertSame('contract_type_id', $tables['contract_types']['pk']);
        $this->assertSame('contract_type', $tables['contract_types']['order']);

        $columns = $tables['contract_types']['columns'];
        $this->assertArrayHasKey('contract_type', $columns);
        $this->assertSame(['label' => 'Type', 'required' => true], $columns['contract_type']);

        $this->assertArrayHasKey('category', $columns);
        $this->assertSame('Category', $columns['category']['label']);
        $this->assertTrue($columns['category']['required']);
        $this->assertSame('select', $columns['category']['type']);
        $this->assertSame([
            'main_staff' => 'Main staff',
            'other_staff' => 'Other staff',
        ], $columns['category']['options']);
    }
}
