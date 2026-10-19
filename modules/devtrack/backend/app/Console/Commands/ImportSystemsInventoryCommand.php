<?php

namespace App\Console\Commands;

use App\Services\SystemsInventoryImportService;
use Illuminate\Console\Command;
use Symfony\Component\Process\Process;

class ImportSystemsInventoryCommand extends Command
{
    protected $signature = 'devtrack:import-systems-inventory
                            {path : Path to Africa_CDC_Systems_Inventory xlsx file}
                            {--dry-run : Report changes without writing to the database}';

    protected $description = 'Import or merge Africa CDC systems inventory into projects and work requests';

    public function handle(SystemsInventoryImportService $import): int
    {
        $path = (string) $this->argument('path');
        if (! is_file($path)) {
            $this->error("File not found: {$path}");

            return self::FAILURE;
        }

        $rows = $this->readSpreadsheet($path);
        if ($rows === []) {
            $this->error('No data rows found in the spreadsheet.');

            return self::FAILURE;
        }

        $this->info('Loaded '.count($rows).' systems from inventory.');

        $stats = $import->import($rows, (bool) $this->option('dry-run'));

        $this->table(
            ['Metric', 'Count'],
            collect($stats)->map(fn ($count, $key) => [str_replace('_', ' ', $key), $count])->values()->all(),
        );

        if ($this->option('dry-run')) {
            $this->warn('Dry run only — no database changes were made.');
        } else {
            $this->info('Import complete.');
        }

        return self::SUCCESS;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function readSpreadsheet(string $path): array
    {
        $python = <<<'PY'
import json, sys
try:
    import openpyxl
except ImportError:
    print(json.dumps({"error": "openpyxl not installed"}))
    sys.exit(2)
path = sys.argv[1]
wb = openpyxl.load_workbook(path, read_only=True, data_only=True)
ws = wb.active
rows = list(ws.iter_rows(values_only=True))
if not rows:
    print(json.dumps([]))
    sys.exit(0)
header = [str(c or "").strip().lower() for c in rows[0]]
out = []
for raw in rows[1:]:
    if raw[1] is None or str(raw[1]).strip() == "":
        continue
    data = dict(zip(header, raw))
    out.append({
        "inventory_number": data.get("#") or data.get("no") or raw[0],
        "name": str(raw[1]).strip(),
        "inventory_status": str(raw[2] or "").strip(),
        "version": str(raw[3] or "").strip(),
        "division": str(raw[4] or "").strip(),
        "languages": str(raw[5] or "").strip(),
        "focal": str(raw[6] or "").strip(),
        "mis_focal": str(raw[7] or "").strip(),
        "modules": raw[8] if len(raw) > 8 else None,
    })
print(json.dumps(out))
PY;

        $process = new Process(['python3', '-c', $python, $path]);
        $process->run();

        if (! $process->isSuccessful()) {
            $this->error(trim($process->getErrorOutput() ?: $process->getOutput()));

            return [];
        }

        $decoded = json_decode($process->getOutput(), true);
        if (! is_array($decoded)) {
            return [];
        }
        if (isset($decoded['error'])) {
            $this->error((string) $decoded['error']);

            return [];
        }

        /** @var list<array<string, mixed>> $decoded */
        return $decoded;
    }
}
