<?php

namespace Tests\Feature;

use App\Jobs\CategorizeTicketWithAi;
use App\Jobs\PollBusinessUnitMailboxesJob;
use App\Models\HelpdeskBusinessUnit;
use App\Models\HelpdeskEmailMessage;
use App\Models\HelpdeskProfile;
use App\Models\HelpdeskSetting;
use App\Models\HelpdeskTicket;
use App\Models\User;
use App\Services\BusinessUnitMailboxIntakeService;
use App\Services\ExchangeGraphMailReader;
use Database\Seeders\HelpdeskCategorySeeder;
use Illuminate\Contracts\Queue\ShouldQueue;
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
        $this->assertSame(1, $r2['skipped']);
        $this->assertSame(1, HelpdeskTicket::query()->where('source', 'email')->count());
        $this->assertSame(1, HelpdeskEmailMessage::query()->count());
        Bus::assertDispatched(CategorizeTicketWithAi::class);

        $ticket = HelpdeskTicket::query()->where('source', 'email')->first();
        $this->assertNotNull($ticket);
        $this->assertSame($unit->id, (int) $ticket->business_unit_id);
        $this->assertSame('ada@example.org', $ticket->requester_email);
        $this->assertNull($ticket->category_id);
        $this->assertSame($ticket->ticket_number, $r2['skipped_items'][0]['ticket_number'] ?? null);
        $this->assertSame('already_imported', $r2['skipped_items'][0]['reason'] ?? null);
        $this->assertSame(2, $fake->moveCalls);
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

    public function test_email_signature_images_are_not_stored_as_ticket_attachments(): void
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
                    'id' => 'msg-sig-1',
                    'internetMessageId' => '<sig@b>',
                    'subject' => 'Processing Email tickets',
                    'bodyPreview' => 'Test email tickets',
                    'body' => [
                        'contentType' => 'html',
                        'content' => '<p>Test email tickets</p>'
                            .'<div id="Signature">'
                            .'<p>Agaba Andrew<br>Software Developer<br>Africa Centres for Disease Control and Prevention<br>'
                            .'Ring Road, 16/17, Haile Garment Square, P.O. Box 3243, Addis Ababa, Ethiopia</p>'
                            .'<img src="cid:C2_signature_facebook_11560c04-a2fe-43e8-b56d-aab6d2b04dfc.png">'
                            .'<img src="cid:C2_signature_emailbanner-02_f69248d2-b624-4cd3-a187-207e1ac42140.jpg">'
                            .'</div>',
                    ],
                    'from' => ['emailAddress' => ['name' => 'Agaba Andrew', 'address' => 'AndrewA@africacdc.org']],
                    'receivedDateTime' => now()->toIso8601String(),
                ]];
            }

            public function listMessageAttachments(string $mailboxUpn, string $messageId): array
            {
                return [
                    [
                        '@odata.type' => '#microsoft.graph.fileAttachment',
                        'id' => 'att-fb',
                        'name' => 'C2_signature_facebook_11560c04-a2fe-43e8-b56d-aab6d2b04dfc.png',
                        'contentType' => 'image/png',
                        'size' => 80,
                        'isInline' => true,
                        'contentId' => 'C2_signature_facebook_11560c04-a2fe-43e8-b56d-aab6d2b04dfc.png',
                        'contentBytes' => $this->png,
                    ],
                    [
                        '@odata.type' => '#microsoft.graph.fileAttachment',
                        'id' => 'att-banner',
                        'name' => 'C2_signature_emailbanner-02_f69248d2-b624-4cd3-a187-207e1ac42140.jpg',
                        'contentType' => 'image/jpeg',
                        'size' => 80,
                        'isInline' => true,
                        'contentId' => 'C2_signature_emailbanner-02_f69248d2-b624-4cd3-a187-207e1ac42140.jpg',
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
        $this->assertStringContainsString('Test email tickets', (string) $ticket->description);
        $this->assertStringNotContainsString('Agaba Andrew', (string) $ticket->description);
        $this->assertStringNotContainsString('C2_signature_', (string) $ticket->description);

        $names = $ticket->attachments->pluck('original_name')->all();
        $this->assertSame(['error-log.pdf'], $names);
    }

    public function test_disabled_intake_skips_mailbox(): void
    {
        Bus::fake([CategorizeTicketWithAi::class]);
        $this->seed(HelpdeskCategorySeeder::class);

        HelpdeskSetting::query()->updateOrCreate(
            ['key' => HelpdeskSetting::KEY_EMAIL_TICKET_INTAKE_ENABLED],
            ['value' => '1']
        );

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
        $this->assertSame('unit_disabled', $result['reason'] ?? null);
        Bus::assertNothingDispatched();
    }

    public function test_master_switch_off_skips_mailbox_with_reason(): void
    {
        Bus::fake([CategorizeTicketWithAi::class]);
        $this->seed(HelpdeskCategorySeeder::class);

        HelpdeskSetting::query()->updateOrCreate(
            ['key' => HelpdeskSetting::KEY_EMAIL_TICKET_INTAKE_ENABLED],
            ['value' => '0']
        );

        $unit = HelpdeskBusinessUnit::query()->where('slug', 'it-mis')->firstOrFail();
        $unit->update([
            'email_intake_enabled' => true,
            'support_mailbox' => 'helpdesk@africacdc.org',
        ]);

        $fake = new class extends ExchangeGraphMailReader
        {
            public function listUnreadInbox(string $mailboxUpn, int $top = 25): array
            {
                $this->fail('Should not list mail when master intake switch is off');
            }
        };
        $this->app->instance(ExchangeGraphMailReader::class, $fake);

        $result = app(BusinessUnitMailboxIntakeService::class)->pollUnit($unit->fresh());
        $this->assertSame(0, $result['created']);
        $this->assertSame('master_disabled', $result['reason'] ?? null);
        Bus::assertNothingDispatched();
    }

    public function test_admin_process_email_intake_explains_when_master_off(): void
    {
        $this->seed(HelpdeskCategorySeeder::class);
        $unit = HelpdeskBusinessUnit::query()->where('slug', 'it-mis')->firstOrFail();
        $unit->update([
            'email_intake_enabled' => true,
            'support_mailbox' => 'helpdesk@africacdc.org',
        ]);

        HelpdeskSetting::query()->updateOrCreate(
            ['key' => HelpdeskSetting::KEY_EMAIL_TICKET_INTAKE_ENABLED],
            ['value' => '0']
        );

        $this->actingAs($this->helpdeskAdmin())
            ->postJson('/api/v1/admin/business-units/'.$unit->id.'/process-email-intake')
            ->assertStatus(422)
            ->assertJsonFragment(['master_disabled']);
    }

    public function test_admin_process_email_intake_creates_ticket(): void
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
            public function listUnreadInbox(string $mailboxUpn, int $top = 25): array
            {
                return [[
                    'id' => 'msg-process-now',
                    'internetMessageId' => '<now@b>',
                    'subject' => 'Process now',
                    'bodyPreview' => 'Please log this',
                    'body' => ['contentType' => 'Text', 'content' => 'Please log this'],
                    'from' => ['emailAddress' => ['name' => 'Ada', 'address' => 'ada@example.org']],
                    'receivedDateTime' => now()->toIso8601String(),
                ]];
            }

            public function listMessageAttachments(string $mailboxUpn, string $messageId): array
            {
                return [];
            }

            public function markReadAndMoveToProcessed(string $mailboxUpn, string $messageId): void {}
        };
        $this->app->instance(ExchangeGraphMailReader::class, $fake);

        $admin = $this->helpdeskAdmin();
        $this->actingAs($admin)
            ->postJson('/api/v1/admin/business-units/'.$unit->id.'/process-email-intake')
            ->assertOk()
            ->assertJsonPath('data.created', 1);

        $this->assertSame(1, HelpdeskTicket::query()->where('source', 'email')->count());
        $this->assertSame(1, HelpdeskEmailMessage::query()->count());

        $ticket = HelpdeskTicket::query()->where('source', 'email')->firstOrFail();
        $again = $this->actingAs($admin)
            ->postJson('/api/v1/admin/business-units/'.$unit->id.'/process-email-intake')
            ->assertOk();
        $again->assertJsonPath('data.created', 0);
        $again->assertJsonPath('data.skipped', 1);
        $this->assertStringContainsString($ticket->ticket_number, (string) $again->json('message'));
        $this->assertSame($ticket->ticket_number, $again->json('data.skipped_items.0.ticket_number'));
    }

    public function test_mailbox_poll_job_runs_inline_from_the_scheduler(): void
    {
        $this->assertFalse((new PollBusinessUnitMailboxesJob) instanceof ShouldQueue);
    }

    public function test_email_message_is_stored_before_attachments_are_imported(): void
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
            public function listUnreadInbox(string $mailboxUpn, int $top = 25): array
            {
                return [[
                    'id' => 'msg-att-throw',
                    'internetMessageId' => '<throw@b>',
                    'subject' => 'Has attachments',
                    'bodyPreview' => 'See file',
                    'body' => ['contentType' => 'Text', 'content' => 'See file'],
                    'from' => ['emailAddress' => ['name' => 'Ada', 'address' => 'ada@example.org']],
                    'receivedDateTime' => now()->toIso8601String(),
                ]];
            }

            public function listMessageAttachments(string $mailboxUpn, string $messageId): array
            {
                throw new \RuntimeException('Graph attachments exploded');
            }

            public function markReadAndMoveToProcessed(string $mailboxUpn, string $messageId): void {}
        };
        $this->app->instance(ExchangeGraphMailReader::class, $fake);

        $result = app(BusinessUnitMailboxIntakeService::class)->pollUnit($unit->fresh());
        $this->assertSame(1, $result['created']);
        $this->assertSame(1, HelpdeskTicket::query()->where('source', 'email')->count());
        $this->assertSame(1, HelpdeskEmailMessage::query()->count());
    }

    public function test_preview_names_the_ticket_when_mail_was_logged(): void
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

        $fake = $this->singleMessageGraphFake('msg-preview-ticket', 'Test Email push');
        $this->app->instance(ExchangeGraphMailReader::class, $fake);

        $svc = app(BusinessUnitMailboxIntakeService::class);
        $svc->pollUnit($unit->fresh());
        $ticket = HelpdeskTicket::query()->where('source', 'email')->first();
        $this->assertNotNull($ticket);

        $preview = $svc->previewUnread($unit->fresh(), 10);
        $this->assertTrue($preview['messages'][0]['already_imported']);
        $this->assertSame($ticket->ticket_number, $preview['messages'][0]['ticket_number']);
        $this->assertFalse($preview['messages'][0]['ticket_missing']);
    }

    public function test_deleted_ticket_is_not_treated_as_imported_and_can_be_relogged(): void
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

        $fake = $this->singleMessageGraphFake('msg-deleted-ticket', 'Test Email push');
        $this->app->instance(ExchangeGraphMailReader::class, $fake);

        $svc = app(BusinessUnitMailboxIntakeService::class);
        $svc->pollUnit($unit->fresh());
        $ticket = HelpdeskTicket::query()->where('source', 'email')->firstOrFail();
        $ticket->delete();

        $preview = $svc->previewUnread($unit->fresh(), 10);
        $this->assertFalse($preview['messages'][0]['already_imported']);
        $this->assertTrue($preview['messages'][0]['ticket_missing']);
        $this->assertNull($preview['messages'][0]['ticket_number']);

        $result = $svc->pollUnit($unit->fresh());
        $this->assertSame(1, $result['created']);
        $this->assertSame(1, HelpdeskTicket::query()->where('source', 'email')->count());
    }

    /**
     * @return ExchangeGraphMailReader
     */
    private function singleMessageGraphFake(string $graphId, string $subject): ExchangeGraphMailReader
    {
        return new class($graphId, $subject) extends ExchangeGraphMailReader
        {
            public function __construct(private string $graphId, private string $subject) {}

            public function listUnreadInbox(string $mailboxUpn, int $top = 25): array
            {
                return [[
                    'id' => $this->graphId,
                    'internetMessageId' => '<'.$this->graphId.'@b>',
                    'subject' => $this->subject,
                    'bodyPreview' => 'Body',
                    'body' => ['contentType' => 'Text', 'content' => 'Body'],
                    'from' => ['emailAddress' => ['name' => 'Ada', 'address' => 'ada@example.org']],
                    'receivedDateTime' => now()->toIso8601String(),
                ]];
            }

            public function listMessageAttachments(string $mailboxUpn, string $messageId): array
            {
                return [];
            }

            public function markReadAndMoveToProcessed(string $mailboxUpn, string $messageId): void {}
        };
    }

    private function helpdeskAdmin(): User
    {
        $admin = User::factory()->create();
        HelpdeskProfile::query()->create([
            'user_id' => $admin->id,
            'role' => HelpdeskProfile::ROLE_ADMIN,
            'staff_id' => 1,
            'grant_helpdesk_admin' => true,
        ]);

        return $admin;
    }
}
