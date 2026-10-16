<?php

namespace Tests\Unit;

use App\Services\RiskAccessScope;
use App\Services\StaffPortalOrgClient;
use App\Support\RiskPermissions;
use Tests\TestCase;

class RiskAccessScopeTest extends TestCase
{
    public function test_director_sees_all_divisions_in_directorate(): void
    {
        $client = $this->createMock(StaffPortalOrgClient::class);
        $client->method('fetchOrg')->willReturn([
            'directorates' => [
                ['id' => 10, 'name' => 'Ops', 'director_id' => 77],
            ],
            'divisions' => [
                ['division_id' => 1, 'directorate_id' => 10, 'director_id' => null],
                ['division_id' => 2, 'directorate_id' => 10, 'director_id' => null],
                ['division_id' => 3, 'directorate_id' => 99, 'director_id' => null],
            ],
        ]);

        $scope = (new RiskAccessScope($client))->resolve(
            [RiskPermissions::VIEW_DIVISION],
            77,
            1
        );

        $this->assertSame('divisions', $scope['mode']);
        $this->assertSame([1, 2], $scope['division_ids']);
    }

    public function test_division_director_sees_that_division(): void
    {
        $client = $this->createMock(StaffPortalOrgClient::class);
        $client->method('fetchOrg')->willReturn([
            'directorates' => [
                ['id' => 10, 'name' => 'Ops', 'director_id' => null],
            ],
            'divisions' => [
                ['division_id' => 5, 'directorate_id' => 10, 'director_id' => 88],
                ['division_id' => 6, 'directorate_id' => 10, 'director_id' => null],
            ],
        ]);

        $scope = (new RiskAccessScope($client))->resolve(
            [RiskPermissions::MODULE],
            88,
            0
        );

        $this->assertSame('divisions', $scope['mode']);
        $this->assertSame([5], $scope['division_ids']);
    }
}
