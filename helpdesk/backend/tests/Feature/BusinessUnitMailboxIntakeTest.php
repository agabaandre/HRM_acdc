<?php

namespace Tests\Feature;

use App\Jobs\CategorizeTicketWithAi;
use App\Models\HelpdeskBusinessUnit;
use App\Models\HelpdeskEmailMessage;
use App\Models\HelpdeskTicket;
use App\Services\BusinessUnitMailboxIntakeService;
use App\Services\ExchangeGraphMailReader;
use Database\Seeders\HelpdeskCategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

class BusinessUnitMailboxIntakeTest extends TestCase
{
    use RefreshDatabase;

    public function test_unread_email_creates_ticket_and_is_idempotent(): void
    {
        Bus::fake([CategorizeTicketWithAi::class]);
        $this->seed(HelpdeskCategorySeeder::class);

        $unit = HelpdeskBusinessUnit::query()->where('slug', 'it-mis')->firstOrFail();
        $unit->update([
            'email_intake_enabled' => true,
            'support_mailbox' => 'helpdesk@africacdc.org',
        ]);

        $fake = new class extends ExchangeGraphMailReader
        {
            public int $moveCalls = 0;

            public function listUnreadInbox(string $mailboxUpn, int $top = 25): array
            {
                return [[
                    'id' => 'msg-1',
                    'internetMessageId' => '<a@b>',
                    'subject' => 'VPN down',
                    'bodyPreview' => 'Cannot connect',
                    'body' => ['contentType' => 'Text', 'content' => 'Cannot connect to VPN'],
                    'from' => ['emailAddress' => ['name' => 'Ada', 'address' => 'ada@example.org']],
                    'receivedDateTime' => now()->toIso8601String(),
                ]];
            }

            public function ensureProcessedFolderId(string $mailboxUpn): string
            {
                return 'folder-processed';
            }

            public function markReadAndMoveToProcessed(string $mailboxUpn, string $messageId): void
            {
                $this->moveCalls++;
            }
        };

        $this->app->instance(ExchangeGraphMailReader::class, $fake);

        $svc = app(BusinessUnitMailboxIntakeService::class);
        $r1 = $svc->pollUnit($unit->fresh());
        $r2 = $svc->pollUnit($unit->fresh());

        $this->assertSame(1, $r1['created']);
        $this->assertSame(0, $r2['created']);
        $this->assertSame(1, HelpdeskTicket::query()->where('source', 'email')->count());
        $this->assertSame(1, HelpdeskEmailMessage::query()->count());
        Bus::assertDispatched(CategorizeTicketWithAi::class);
        $this->assertSame(1, $fake->moveCalls);

        $ticket = HelpdeskTicket::query()->where('source', 'email')->first();
        $this->assertNotNull($ticket);
        $this->assertSame($unit->id, (int) $ticket->business_unit_id);
        $this->assertSame('ada@example.org', $ticket->requester_email);
        $this->assertNull($ticket->category_id);
    }

    public function test_disabled_intake_skips_mailbox(): void
    {
        Bus::fake([CategorizeTicketWithAi::class]);
        $this->seed(HelpdeskCategorySeeder::class);

        $unit = HelpdeskBusinessUnit::query()->where('slug', 'it-mis')->firstOrFail();
        $unit->update([
            'email_intake_enabled' => false,
            'support_mailbox' => 'helpdesk@africacdc.org',
        ]);

        $fake = new class extends ExchangeGraphMailReader
        {
            public function listUnreadInbox(string $mailboxUpn, int $top = 25): array
            {
                $this->fail('Should not list mail when intake disabled');
            }
        };
        $this->app->instance(ExchangeGraphMailReader::class, $fake);

        $result = app(BusinessUnitMailboxIntakeService::class)->pollUnit($unit->fresh());
        $this->assertSame(0, $result['created']);
        Bus::assertNothingDispatched();
    }
}
