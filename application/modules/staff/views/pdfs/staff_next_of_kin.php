<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?= html_escape($title ?? 'Staff Next of Kin') ?> - Africa CDC</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 8pt; }
        .header { text-align: center; margin-bottom: 12px; }
        .header img { height: 70px; }
        .title { font-size: 14pt; font-weight: bold; text-align: center; margin: 8px 0; }
        .note { font-size: 7pt; color: #444; text-align: center; margin-bottom: 10px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #333; padding: 4px; vertical-align: top; text-align: left; }
        th { background: #eee; font-size: 7pt; }
        td { word-wrap: break-word; }
        .col-n { width: 22px; }
        .col-sap { width: 48px; }
    </style>
</head>
<body>
<div class="container">
    <div class="header">
        <img src="<?= FCPATH . 'assets/images/AU_CDC_Logo-800.png' ?>" alt="AU CDC Logo">
    </div>
    <div class="title"><?= html_escape($title ?? 'Staff Next of Kin') ?></div>
    <div class="note">Latest contract Active, Due, or Under renewal. Address and next of kin from staff portal profile.</div>
    <table>
        <thead>
            <tr>
                <th class="col-n">#</th>
                <th class="col-sap">SAP</th>
                <th>Name</th>
                <th>Status</th>
                <th>Job / Division / Station</th>
                <th>Grade</th>
                <th>Work email</th>
                <th>Contacts</th>
                <th>Residential (duty station)</th>
                <th>Dep.</th>
                <th>Next of kin</th>
            </tr>
        </thead>
        <tbody>
            <?php
            $rows = isset($rows) ? $rows : [];
            $kin_name_by_id = isset($kin_name_by_id) && is_array($kin_name_by_id) ? $kin_name_by_id : [];
            $i = 1;
            foreach ($rows as $s) :
                $nok_raw = json_decode(isset($s->next_of_kin_json) ? $s->next_of_kin_json : '[]', true);
                if (!is_array($nok_raw)) {
                    $nok_raw = [];
                }
                $display_nok = [];
                foreach ($nok_raw as $nr) {
                    $out = ['name' => '', 'relationship_id' => 0, 'phone' => '', 'email' => ''];
                    if (is_array($nr)) {
                        $out['name'] = trim((string) ($nr['name'] ?? ''));
                        $out['relationship_id'] = (int) ($nr['relationship_id'] ?? 0);
                        $out['phone'] = trim((string) ($nr['phone'] ?? ''));
                        $out['email'] = trim((string) ($nr['email'] ?? ''));
                        if ($out['phone'] === '' && $out['email'] === '' && !empty($nr['contact'])) {
                            $c = trim((string) $nr['contact']);
                            if ($c !== '' && strpos($c, '@') !== false) {
                                $out['email'] = $c;
                            } elseif ($c !== '') {
                                $out['phone'] = $c;
                            }
                        }
                    }
                    if ($out['name'] !== '' || $out['phone'] !== '' || $out['email'] !== '' || $out['relationship_id'] > 0) {
                        $display_nok[] = $out;
                    }
                }
                $nok_bits = [];
                foreach ($display_nok as $k) {
                    $rel = $k['relationship_id'] > 0 && isset($kin_name_by_id[$k['relationship_id']])
                        ? $kin_name_by_id[$k['relationship_id']]
                        : '';
                    $nok_bits[] = html_escape(trim($k['name'] . ($rel !== '' ? ' (' . $rel . ')' : '')))
                        . '<br><small>T: ' . html_escape($k['phone'] ?: '—') . ' | E: ' . html_escape($k['email'] ?: '—') . '</small>';
                }
                $nok_cell = !empty($nok_bits) ? implode('<br>', $nok_bits) : '—';
                $name = trim(($s->title ?? '') . ' ' . ($s->fname ?? '') . ' ' . ($s->lname ?? '') . ' ' . ($s->oname ?? ''));
                $job_block = html_escape((string) ($s->job_name ?? '—'))
                    . '<br><small>' . html_escape((string) ($s->division_name ?? '')) . '</small>'
                    . '<br><small>' . html_escape((string) ($s->duty_station_name ?? '')) . '</small>';
                $contacts = [];
                if (!empty($s->tel_1)) {
                    $contacts[] = 'T1: ' . html_escape((string) $s->tel_1);
                }
                if (!empty($s->tel_2)) {
                    $contacts[] = 'T2: ' . html_escape((string) $s->tel_2);
                }
                if (!empty($s->whatsapp)) {
                    $contacts[] = 'WA: ' . html_escape((string) $s->whatsapp);
                }
                if (!empty($s->private_email)) {
                    $contacts[] = 'Pvt: ' . html_escape((string) $s->private_email);
                }
                if (!empty($s->physical_location)) {
                    $contacts[] = 'Loc: ' . nl2br(html_escape((string) $s->physical_location));
                }
                $contacts_cell = !empty($contacts) ? implode('<br>', $contacts) : '—';
                $addr = isset($s->residential_address_duty_station) ? nl2br(html_escape(trim((string) $s->residential_address_duty_station))) : '—';
                $deps = (isset($s->number_of_dependants) && $s->number_of_dependants !== null && $s->number_of_dependants !== '')
                    ? (string) (int) $s->number_of_dependants
                    : '—';
                ?>
                <tr>
                    <td class="col-n"><?= $i++ ?></td>
                    <td class="col-sap"><?= html_escape((string) ($s->SAPNO ?? '')) ?></td>
                    <td><?= html_escape($name) ?></td>
                    <td><?= html_escape((string) ($s->contract_status_label ?? '')) ?></td>
                    <td><?= $job_block ?></td>
                    <td><?= html_escape((string) ($s->grade ?? '')) ?></td>
                    <td><?= html_escape((string) ($s->work_email ?? '')) ?></td>
                    <td><?= $contacts_cell ?></td>
                    <td><?= $addr ?></td>
                    <td><?= html_escape($deps) ?></td>
                    <td><?= $nok_cell ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($rows)) : ?>
                <tr><td colspan="11" style="text-align:center;">No records.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>
</body>
</html>
