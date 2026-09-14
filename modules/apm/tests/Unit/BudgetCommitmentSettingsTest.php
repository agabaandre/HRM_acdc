<?php

namespace Tests\Unit;

use App\Services\BudgetCommitmentSettings;
use PHPUnit\Framework\TestCase;

class BudgetCommitmentSettingsTest extends TestCase
{
    public function test_parse_status_list_trims_and_lowercases(): void
    {
        $settings = new class extends BudgetCommitmentSettings {
            public function parseForTest(?string $raw): array
            {
                $ref = new \ReflectionMethod(BudgetCommitmentSettings::class, 'parseStatusList');
                $ref->setAccessible(true);

                return $ref->invoke($this, $raw, BudgetCommitmentSettings::DEFAULT_MEMO_STATUSES);
            }
        };

        $this->assertSame(['draft', 'pending', 'approved'], $settings->parseForTest(' Draft, PENDING ,approved '));
        $this->assertSame(BudgetCommitmentSettings::DEFAULT_MEMO_STATUSES, $settings->parseForTest(''));
    }
}
