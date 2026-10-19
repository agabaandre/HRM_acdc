<?php

namespace Tests\Unit;

use App\Jobs\SendEmailJob;
use Tests\TestCase;

class SendEmailJobTest extends TestCase
{
    public function test_email_job_retries_five_times_before_being_dropped(): void
    {
        $job = new SendEmailJob(1);

        $this->assertSame(5, $job->tries);
        $this->assertSame([30, 60, 120, 300, 600], $job->backoff);
    }
}
