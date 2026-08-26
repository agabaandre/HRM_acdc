<?php

namespace Tests\Feature;

use App\Jobs\CategorizeTicketWithAi;
use App\Models\HelpdeskBusinessUnit;
use App\Models\HelpdeskEmailMessage;
use App\Models\HelpdeskSetting;
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

        HelpdeskSetting::query()->updateOrCreate(
            ['key' => HelpdeskSetting::KEY_EMAIL_TICKET_INTAKE_ENABLED],
            ['value' => '1']
        );

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

            public function listMessageAttachments(string $mailboxUpn, string $messageId): array
            {
                return [];
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

    public function test_email_file_and_inline_attachments_are_stored_on_the_ticket(): void
    {
        Bus::fake([CategorizeTicketWithAi::class]);
        $this->seed(HelpdeskCategorySeeder::class);
        \Illuminate\Support\Facades\Storage::fake('public');

        HelpdeskSetting::query()->updateOrCreate(
            ['key' => HelpdeskSetting::KEY_EMAIL_TICKET_INTAKE_ENABLED],
            ['value' => '1']
        );

        $unit = HelpdeskBusinessUnit::query()->where('slug', 'it-mis')->firstOrFail();
        $unit->update([
            'email_intake_enabled' => true,
            'support_mailbox' => 'helpdesk@africacdc.org',
        ]);

        $png = base64_encode(base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==', true) ?: 'png');
        $pdf = base64_encode('%PDF-1.4 fake');

        $fake = new class($png, $pdf) extends ExchangeGraphMailReader
        {
            public function __construct(private string $png, private string $pdf) {}

            public function listUnreadInbox(string $mailboxUpn, int $top = 25): array
            {
                return [[
                    'id' => 'msg-att-1',
                    'internetMessageId' => '<att@b>',
                    'subject' => 'Screenshot of the error',
                    'bodyPreview' => 'See attached',
                    'body' => [
                        'contentType' => 'html',
                        'content' => '<p>See screenshot</p><img src="cid:img001">',
                    ],
                    'from' => ['emailAddress' => ['name' => 'Ada', 'address' => 'ada@example.org']],
                    'receivedDateTime' => now()->toIso8601String(),
                ]];
            }

            public function listMessageAttachments(string $mailboxUpn, string $messageId): array
            {
                return [
                    [
                        '@odata.type' => '#microsoft.graph.fileAttachment',
                        'id' => 'att-inline',
                        'name' => 'screenshot.png',
                        'contentType' => 'image/png',
                        'size' => 80,
                        'isInline' => true,
                        'contentId' => 'img001',
                        'contentBytes' => $this->png,
                    ],
                    [
                        '@odata.type' => '#microsoft.graph.fileAttachment',
                        'id' => 'att-pdf',
                        'name' => 'error-log.pdf',
                        'contentType' => 'application/pdf',
                        'size' => 20,
                        'isInline' => false,
                        'contentBytes' => $this->pdf,
                    ],
                    [
                        '@odata.type' => '#microsoft.graph.fileAttachment',
                        'id' => 'att-winmail',
                        'name' => 'winmail.dat',
                        'contentType' => 'application/ms-tnef',
                        'size' => 12,
                        'isInline' => false,
                        'contentBytes' => base64_encode('tnef'),
                    ],
                ];
            }

            public function ensureProcessedFolderId(string $mailboxUpn): string
            {
                return 'folder-processed';
            }

            public function markReadAndMoveToProcessed(string $mailboxUpn, string $messageId): void {}
        };

        $this->app->instance(ExchangeGraphMailReader::class, $fake);

        $result = app(BusinessUnitMailboxIntakeService::class)->pollUnit($unit->fresh());
        $this->assertSame(1, $result['created']);

        $ticket = HelpdeskTicket::query()->where('source', 'email')->first();
        $this->assertNotNull($ticket);
        $ticket->load('attachments');
        $this->assertCount(2, $ticket->attachments);

        $names = $ticket->attachments->pluck('original_name')->all();
        $this->assertContains('screenshot.png', $names);
        $this->assertContains('error-log.pdf', $names);
        $this->assertNotContains('winmail.dat', $names);

        $inline = $ticket->attachments->firstWhere('original_name', 'screenshot.png');
        $this->assertNotNull($inline);
        $this->assertTrue($inline->isInlineImage());
        $this->assertStringContainsString('/api/v1/attachments/'.$inline->id.'/file', $ticket->description);
        $this->assertStringNotContainsString('cid:img001', $ticket->description);

        $pdfRow = $ticket->attachments->firstWhere('original_name', 'error-log.pdf');
        $this->assertNotNull($pdfRow);
        $this->assertFalse($pdfRow->isInlineImage());
        $this->assertTrue(\Illuminate\Support\Facades\Storage::disk('public')->exists($pdfRow->path));
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
