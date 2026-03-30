<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * CLI entry points for scheduled jobs (single place to wire cron → jobs/jobs methods).
 * Pattern mirrors application/modules/jobs/Jobs.php (master scheduler): CLI-only, echo progress.
 *
 * Single crontab line (every minute; edit tick_schedule() below for times):
 *   * * * * * /usr/bin/php /path/to/staff/index.php jobs/run/tick
 */
class Run extends MX_Controller
{
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Wall-clock schedule for tick(). Change values here only.
     *
     * - send_instant_mails: run every cron invocation (every minute).
     * - send_mails_interval_minutes: full queue pass every N minutes (15 => :00, :15, :30, :45).
     * - performance_notifications: once daily at hour:minute, or false to disable.
     * - performance_unified_approval_reminder: once daily at hour:minute, or false to disable.
     * - cron_register: once daily at hour:minute (bundles birthday + accounts + contracts), or false.
     * - mark_due_contracts / staff_birthday: daily at hour:minute, or false.
     * - manage_accounts_hourly_minute: every hour at this minute, or null to disable.
     */
    private function tick_schedule()
    {
        return [
            'send_instant_mails'           => true,
            'send_mails_interval_minutes'  => 15,
            'performance_notifications'    => ['hour' => 7, 'minute' => 0],
            'performance_unified_approval_reminder' => ['hour' => 10, 'minute' => 0],
            'cron_register'                => false,
            'mark_due_contracts'           => ['hour' => 23, 'minute' => 0],
            'staff_birthday'               => ['hour' => 3, 'minute' => 0],
            'manage_accounts_hourly_minute' => 0,
        ];
    }

    /**
     * One entry point for cron: call from crontab every minute.
     */
    public function tick()
    {
        if (!$this->input->is_cli_request()) {
            show_error('CLI only.', 403);
            return;
        }

        $s = $this->tick_schedule();

        $now    = time();
        $minute = (int) date('i', $now);
        $hour   = (int) date('H', $now);

        echo '[' . date('Y-m-d H:i:s') . "] jobs/run/tick\n";

        if (!empty($s['send_instant_mails'])) {
            echo "  → send_instant_mails\n";
            Modules::run('jobs/jobs/send_instant_mails');
        }

        $n = isset($s['send_mails_interval_minutes']) ? (int) $s['send_mails_interval_minutes'] : 0;
        if ($n > 0 && ($minute % $n === 0)) {
            echo "  → send_mails (interval {$n}m)\n";
            Modules::run('jobs/jobs/send_mails');
        }

        if ($this->tick_match_clock($s['performance_notifications'] ?? false, $hour, $minute)) {
            echo "  → performance_notifications (PPA / Midterm / Endterm queue)\n";
            $this->_run_performance_notifications();
        }
        if ($this->tick_match_clock($s['performance_unified_approval_reminder'] ?? false, $hour, $minute)) {
            echo "  → performance_unified_approval_reminder\n";
            Modules::run('jobs/jobs/notify_supervisors_pending_performance_unified');
        }

        if ($this->tick_match_clock($s['cron_register'] ?? false, $hour, $minute)) {
            echo "  → cron_register\n";
            Modules::run('jobs/jobs/cron_register');
        }

        if ($this->tick_match_clock($s['mark_due_contracts'] ?? false, $hour, $minute)) {
            echo "  → mark_due_contracts\n";
            Modules::run('jobs/jobs/mark_due_contracts');
        }

        if ($this->tick_match_clock($s['staff_birthday'] ?? false, $hour, $minute)) {
            echo "  → staff_birthday\n";
            Modules::run('jobs/jobs/staff_birthday');
        }

        if (isset($s['manage_accounts_hourly_minute']) && $s['manage_accounts_hourly_minute'] !== null && $s['manage_accounts_hourly_minute'] !== '') {
            if ($minute === (int) $s['manage_accounts_hourly_minute']) {
                echo "  → manage_accounts (hourly)\n";
                Modules::run('jobs/jobs/manage_accounts');
            }
        }

        echo "  done.\n";
    }

    /**
     * @param array|false $spec ['hour'=>int,'minute'=>int] or false
     */
    private function tick_match_clock($spec, $hour, $minute)
    {
        if ($spec === false || empty($spec) || !is_array($spec)) {
            return false;
        }
        $h = isset($spec['hour']) ? (int) $spec['hour'] : 0;
        $m = isset($spec['minute']) ? (int) $spec['minute'] : 0;
        return $hour === $h && $minute === $m;
    }

    /**
     * Queue supervisor/staff performance reminder emails (PPA, Midterm, Endterm).
     * Delegates to jobs/jobs controller methods.
     *
     * Crontab example (daily, before mail dispatch):
     *   0 7 * * * /usr/bin/php /path/to/staff/index.php jobs/run/performance_notifications
     */
    public function performance_notifications()
    {
        if (!$this->input->is_cli_request()) {
            show_error('CLI only.', 403);
            return;
        }

        $this->_run_performance_notifications();
    }

    /** Shared implementation (also used from tick()). */
    private function _run_performance_notifications()
    {
        $started = date('Y-m-d H:i:s');
        echo "============================================\n";
        echo " performance_notifications started: {$started}\n";
        echo "============================================\n";

        $steps = [
            'jobs/jobs/notify_supervisors_pending_ppas'       => 'PPA supervisor reminders',
            'jobs/jobs/notify_supervisors_pending_midterms'   => 'Midterm supervisor reminders',
            'jobs/jobs/notify_supervisors_pending_endterms'   => 'Endterm supervisor reminders',
        ];

        foreach ($steps as $route => $label) {
            echo "\n--- {$label} ---\n";
            Modules::run($route);
            echo "OK\n";
        }

        echo "\n============================================\n";
        echo ' performance_notifications completed: ' . date('Y-m-d H:i:s') . "\n";
        echo "============================================\n";
    }

    /**
     * CLI test: queue three sample supervisor emails (PPA, Midterm, Endterm first) and try to send.
     *
     * Default recipient is agabaandre@gmail.com. Override with env (CodeIgniter disallows @ in CLI URI args):
     *
     *   php index.php jobs/run/test_performance_notifications
     *   TEST_EMAIL=other@example.com php index.php jobs/run/test_performance_notifications
     */
    public function test_performance_notifications()
    {
        if (!$this->input->is_cli_request()) {
            show_error('CLI only.', 403);
            return;
        }

        $candidate = getenv('TEST_EMAIL');
        $email = ($candidate && filter_var($candidate, FILTER_VALIDATE_EMAIL))
            ? $candidate
            : 'agabaandre@gmail.com';

        $cfg = $this->db->get('ppa_configs')->row();
        if (!$cfg) {
            echo "Error: ppa_configs row missing.\n";
            return;
        }

        $ppa_period = str_replace(' ', '-', previous_period());
        $endterm_period = str_replace(' ', '-', endterm_reminder_period());
        $disp = date('Y-m-d');

        $sample_obj = [(object) [
            'staff_name' => 'Sample Staff (test)',
            'entry_id' => '0',
            'staff_id' => '0',
        ]];

        $sample_endterm_arr = [[
            'staff_name' => 'Sample Staff (test)',
            'entry_id' => '0',
            'staff_id' => '0',
        ]];

        echo "Test performance notifications → {$email}\n";

        // PPA
        $data = [
            'supervisor_name' => 'Test Supervisor',
            'period'          => $ppa_period,
            'deadline'        => $cfg->ppa_deadline,
            'pending_list'    => $sample_obj,
            'subject'         => "[TEST] Reminder: Pending PPA Approvals for {$ppa_period}",
            'email_to'        => $email,
        ];
        $data['body'] = $this->load->view('supervisor_reminder', $data, true);
        $eid = md5('TEST-PPA-' . $email . '-' . microtime(true));
        golobal_log_email('Staff Portal System', $email, $data['body'], $data['subject'], 0, $disp, $disp, $eid);
        echo "  queued PPA (entry_id hash)\n";

        // Midterm
        $data = [
            'supervisor_name' => 'Test Supervisor',
            'period'          => $ppa_period,
            'deadline'        => $cfg->mid_term_deadline,
            'pending_list'    => $sample_obj,
            'subject'         => "[TEST] Reminder: Pending Midterm Approvals for {$ppa_period}",
            'email_to'        => $email,
        ];
        $data['body'] = $this->load->view('supervisor_reminder_midterm', $data, true);
        $eid = md5('TEST-MID-' . $email . '-' . microtime(true));
        golobal_log_email('Staff Portal System', $email, $data['body'], $data['subject'], 0, $disp, $disp, $eid);
        echo "  queued Midterm\n";

        // Endterm (first supervisor template)
        $data = [
            'supervisor_name' => 'Test Supervisor',
            'period'          => $endterm_period,
            'deadline'        => $cfg->end_term_deadline,
            'pending_list'    => $sample_endterm_arr,
            'subject'         => "[TEST] Reminder: Pending Endterm Approvals for {$endterm_period}",
            'email_to'        => $email,
        ];
        $data['body'] = $this->load->view('supervisor_reminder_endterm_first', $data, true);
        $eid = md5('TEST-END1-' . $email . '-' . microtime(true));
        golobal_log_email('Staff Portal System', $email, $data['body'], $data['subject'], 0, $disp, $disp, $eid);
        echo "  queued Endterm (first supervisor)\n";

        echo "\nRunning send_instant_mails (one pass)…\n";
        Modules::run('jobs/jobs/send_instant_mails');
        echo "Done. Look for three messages with [TEST] in the subject (inbox/spam).\n";
    }

    /**
     * Optional one-shot: queue performance notifications then run one pass of the mail sender.
     * Use when you want same cron line to queue + send without waiting for send_mails schedule.
     */
    public function performance_notifications_and_send()
    {
        if (!$this->input->is_cli_request()) {
            show_error('CLI only.', 403);
            return;
        }

        $this->performance_notifications();

        echo "\n--- send_mails (one pass) ---\n";
        Modules::run('jobs/jobs/send_mails');
        echo "Done.\n";
    }

    /**
     * Queue unified performance approval reminders (first/second approver) and send one pass.
     * Scheduled by tick() at 10:00 by default.
     */
    public function performance_unified_approval_reminder()
    {
        if (!$this->input->is_cli_request()) {
            show_error('CLI only.', 403);
            return;
        }

        echo "--- performance_unified_approval_reminder ---\n";
        Modules::run('jobs/jobs/notify_supervisors_pending_performance_unified');
        echo "--- send_mails (one pass) ---\n";
        Modules::run('jobs/jobs/send_mails');
        echo "Done.\n";
    }

    public function index()
    {
        if (!$this->input->is_cli_request()) {
            show_404();
            return;
        }

        echo "jobs/run CLI:\n";
        echo "  php index.php jobs/run/tick                    # single crontab entry (edit tick_schedule() in Run.php)\n";
        echo "  php index.php jobs/run/performance_notifications\n";
        echo "  php index.php jobs/run/performance_unified_approval_reminder\n";
        echo "  php index.php jobs/run/performance_notifications_and_send\n";
        echo "  php index.php jobs/run/test_performance_notifications\n";
        echo "  TEST_EMAIL=you@example.com php index.php jobs/run/test_performance_notifications\n";
    }
}
