<?php

namespace Tests\Unit;

use Modules\Jobs\Services\EmailNotificationService;
use Modules\Jobs\Services\SendQueuedMailService;
use ReflectionMethod;
use Tests\TestCase;

class JobsEmailAuditBccTest extends TestCase
{
    public function test_system_email_never_uses_registry_or_mail_from(): void
    {
        config([
            'jobs.schedule.system_email' => 'registry@africacdc.org',
            'mail.from.address' => 'notifications@africacdc.org',
        ]);

        $svc = app(EmailNotificationService::class);

        $this->assertSame('system@africacdc.org', $svc->systemEmail());
        $this->assertSame(
            'staff@africacdc.org;system@africacdc.org',
            $svc->appendSystemInbox('staff@africacdc.org;registry@africacdc.org'),
        );
    }

    public function test_system_email_rejects_notifications_as_audit_bcc(): void
    {
        config([
            'jobs.schedule.system_email' => 'notifications@africacdc.org',
            'mail.from.address' => 'notifications@africacdc.org',
        ]);

        $this->assertSame('system@africacdc.org', app(EmailNotificationService::class)->systemEmail());
    }

    public function test_queued_mail_partitions_first_to_and_rest_as_bcc(): void
    {
        config([
            'jobs.schedule.system_email' => 'system@africacdc.org',
            'mail.from.address' => 'notifications@africacdc.org',
        ]);

        $sender = app(SendQueuedMailService::class);
        $method = new ReflectionMethod($sender, 'partitionToAndBcc');
        $method->setAccessible(true);

        [$to, $bcc] = $method->invoke($sender, [
            'staff@africacdc.org',
            'supervisor@africacdc.org',
            'registry@africacdc.org',
            'system@africacdc.org',
        ]);

        $this->assertSame(['staff@africacdc.org'], $to);
        $this->assertContains('system@africacdc.org', $bcc);
        $this->assertContains('supervisor@africacdc.org', $bcc);
        $this->assertNotContains('registry@africacdc.org', $bcc);
        $this->assertNotContains('registry@africacdc.org', $to);
    }
}
