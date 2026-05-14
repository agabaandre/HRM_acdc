<?php

namespace Tests\Feature;

use App\Models\HelpdeskCategory;
use App\Models\HelpdeskProfile;
use App\Models\User;
use Database\Seeders\HelpdeskCategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TicketApiTest extends TestCase
{
    use RefreshDatabase;

    private function actingHelpdeskUser(int $staffId = 9001, string $role = HelpdeskProfile::ROLE_USER): User
    {
        $user = User::factory()->create([
            'email' => 'ticket-user-'.$staffId.'@example.org',
        ]);
        HelpdeskProfile::query()->create([
            'user_id' => $user->id,
            'staff_id' => $staffId,
            'role' => $role,
            'synced_at' => now(),
        ]);

        return $user->fresh(['helpdeskProfile']);
    }

    public function test_authenticated_user_can_create_and_list_own_ticket(): void
    {
        $this->seed(HelpdeskCategorySeeder::class);
        $cat = HelpdeskCategory::query()->firstOrFail();

        $user = $this->actingHelpdeskUser(77701);
        Sanctum::actingAs($user);

        $create = $this->postJson('/api/v1/tickets', [
            'category_id' => $cat->id,
            'description' => '<p>Outlook error</p>',
        ]);

        $create->assertCreated();
        $subject = (string) $create->json('data.subject');
        $this->assertStringContainsString($cat->name, $subject);
        $this->assertLessThanOrEqual(199, strlen($subject));

        $list = $this->getJson('/api/v1/tickets');
        $list->assertOk();
        $this->assertNotEmpty($list->json('data'));
    }

    public function test_end_user_can_create_ticket_on_behalf_of_other_staff_when_directory_cached(): void
    {
        $this->seed(HelpdeskCategorySeeder::class);
        $cat = HelpdeskCategory::query()->firstOrFail();

        $this->seedHelpdeskStaffDirectoryCache(77702, 'colleague@example.org', 'Col', 'League', 1, 2, 'Addis Hub');

        $user = $this->actingHelpdeskUser(77701);
        Sanctum::actingAs($user);

        $res = $this->postJson('/api/v1/tickets', [
            'category_id' => $cat->id,
            'description' => 'On behalf test',
            'requester_staff_id' => 77702,
        ]);

        $res->assertCreated();
        $this->assertSame(77702, (int) $res->json('data.requester_staff_id'));
        $this->assertSame('colleague@example.org', $res->json('data.requester_email'));
    }

    public function test_admin_can_delete_ticket(): void
    {
        $this->seed(HelpdeskCategorySeeder::class);
        $cat = HelpdeskCategory::query()->firstOrFail();

        $user = $this->actingHelpdeskUser(88802, HelpdeskProfile::ROLE_ADMIN);
        Sanctum::actingAs($user);

        $this->seedHelpdeskStaffDirectoryCache(888021, 'other.staff@example.org', 'Other', 'Staff');

        $tid = $this->postJson('/api/v1/tickets', [
            'category_id' => $cat->id,
            'description' => 'x',
            'requester_staff_id' => 888021,
        ])->json('data.id');

        $this->deleteJson('/api/v1/tickets/'.$tid)->assertNoContent();
        $this->assertDatabaseMissing('helpdesk_tickets', ['id' => $tid]);
    }
}
