<?php

namespace App\Console\Commands;

use App\Models\HelpdeskInformationSystem;
use App\Models\HelpdeskInformationSystemLanguage;
use App\Models\HelpdeskInformationSystemModule;
use App\Models\HelpdeskInformationSystemStatusEvent;
use App\Services\InformationSystemStaffMatcher;
use App\Services\InformationSystemStatusRecorder;
use App\Services\StaffDirectoryLookupService;
use App\Support\InformationSystemLanguageNormalizer;
use App\Support\InformationSystemStatus;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;

class ImportInformationSystemsCommand extends Command
{
    protected $signature = 'helpdesk:import-information-systems
                            {path? : Path to the Excel workbook}
                            {--fresh : Truncate systems, modules, language pivots, and events before import}';

    protected $description = 'Import Africa CDC Information Systems from Excel into Helpdesk';

    public function handle(
        InformationSystemStaffMatcher $matcher,
        StaffDirectoryLookupService $directory,
        InformationSystemStatusRecorder $recorder,
    ): int {
        $path = $this->argument('path')
            ?: base_path('../Africa CDC Information Systems.xlsx');

        if (! is_file($path)) {
            $this->error('File not found: '.$path);

            return self::FAILURE;
        }

        if ($this->option('fresh')) {
            DB::table('helpdesk_information_system_language')->delete();
            HelpdeskInformationSystemModule::query()->delete();
            HelpdeskInformationSystemStatusEvent::query()->delete();
            HelpdeskInformationSystem::query()->delete();
            $this->warn('Cleared existing information systems data (--fresh).');
        }

        $sheet = IOFactory::load($path)->getActiveSheet();
        $rows = $sheet->toArray(null, true, true, false);
        if ($rows === []) {
            $this->error('Workbook is empty.');

            return self::FAILURE;
        }

        // Detect header row (col B = System Name)
        $start = 0;
        foreach ($rows as $i => $row) {
            if (isset($row[1]) && strcasecmp(trim((string) $row[1]), 'System Name') === 0) {
                $start = $i + 1;
                break;
            }
        }

        $created = 0;
        $updated = 0;

        for ($i = $start; $i < count($rows); $i++) {
            $row = $rows[$i];
            $name = trim((string) ($row[1] ?? ''));
            if ($name === '') {
                continue;
            }

            $status = InformationSystemStatus::fromExcel(isset($row[3]) ? (string) $row[3] : null);
            $version = trim((string) ($row[10] ?? ''));
            if ($version === '') {
                $version = '1.0';
            }

            $focal = $matcher->resolve(isset($row[13]) ? (string) $row[13] : null);
            $mis = $matcher->resolve(isset($row[14]) ? (string) $row[14] : null);
            $divisionId = $directory->resolveDivisionIdByName(isset($row[12]) ? (string) $row[12] : null);

            $attrs = [
                'description' => $this->nullableString($row[2] ?? null),
                'status' => $status,
                'host' => $this->nullableString($row[4] ?? null),
                'host_name' => $this->nullableString($row[5] ?? null),
                'ip' => $this->nullableString($row[6] ?? null),
                'domain' => $this->nullableString($row[7] ?? null),
                'os' => $this->nullableString($row[8] ?? null),
                'version' => $version,
                'division_id' => $divisionId,
                'focal_staff_id' => $focal['staff_id'],
                'focal_name_raw' => $focal['name_raw'],
                'mis_focal_staff_id' => $mis['staff_id'],
                'mis_focal_name_raw' => $mis['name_raw'],
                'system_profile_url' => $this->urlOrNull($row[15] ?? null),
                'user_manual_users_url' => $this->urlOrNull($row[16] ?? null),
                'user_manual_managers_url' => $this->urlOrNull($row[17] ?? null),
                'user_manual_technical_url' => $this->urlOrNull($row[18] ?? null),
                'faqs' => $this->nullableString($row[19] ?? null),
                'sops' => $this->nullableString($row[20] ?? null),
                'total_users' => $this->nullableInt($row[21] ?? null),
                'estimated_annual_hosting_cost' => $this->nullableDecimal($row[22] ?? null),
            ];

            $existing = HelpdeskInformationSystem::query()->where('name', $name)->first();
            if ($existing) {
                $from = $existing->status;
                $existing->fill($attrs)->save();
                if ($from !== $status) {
                    $recorder->record('system', (int) $existing->id, $from, $status);
                }
                $system = $existing;
                $updated++;
            } else {
                $system = HelpdeskInformationSystem::query()->create(array_merge($attrs, [
                    'name' => $name,
                ]));
                $recorder->record('system', (int) $system->id, null, $status);
                $created++;
            }

            $langNames = InformationSystemLanguageNormalizer::normalizeList(
                isset($row[9]) ? (string) $row[9] : null
            );
            $langIds = [];
            foreach ($langNames as $langName) {
                $slug = InformationSystemLanguageNormalizer::slugFor($langName);
                if ($slug === '') {
                    continue;
                }
                $lang = HelpdeskInformationSystemLanguage::query()->firstOrCreate(
                    ['slug' => $slug],
                    ['name' => $langName, 'is_active' => true],
                );
                $langIds[] = $lang->id;
            }
            $system->languages()->sync($langIds);
        }

        $this->info("Import complete. Created {$created}, updated {$updated}.");

        return self::SUCCESS;
    }

    private function nullableString(mixed $v): ?string
    {
        $s = trim((string) $v);

        return $s === '' ? null : $s;
    }

    private function urlOrNull(mixed $v): ?string
    {
        $s = trim((string) $v);
        if ($s === '') {
            return null;
        }
        if (preg_match('#^https?://#i', $s)) {
            return $s;
        }

        return null;
    }

    private function nullableInt(mixed $v): ?int
    {
        if ($v === null || $v === '') {
            return null;
        }
        if (! is_numeric($v)) {
            return null;
        }

        return (int) $v;
    }

    private function nullableDecimal(mixed $v): ?float
    {
        if ($v === null || $v === '') {
            return null;
        }
        $s = preg_replace('/[^0-9.\-]/', '', (string) $v) ?? '';
        if ($s === '' || ! is_numeric($s)) {
            return null;
        }

        return round((float) $s, 2);
    }
}
