<?php

namespace Modules\Settings\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Core\Support\PortalPermission;
use Modules\Jobs\Services\StaffJobsScheduleService;
use RuntimeException;
use Throwable;

class StaffJobsSettingsController extends Controller
{
    public function __construct(
        protected StaffJobsScheduleService $schedule,
    ) {}

    public function show(): JsonResponse
    {
        PortalPermission::authorize(15);

        return response()->json([
            'data' => [
                'schedule' => $this->schedule->resolved(),
                'schedule_path' => $this->schedule->displayPath(),
                'daily_jobs_meta' => $this->schedule->dailyJobsMeta(),
                'instant_jobs' => collect($this->schedule->instantJobs())
                    ->map(fn (array $def, string $key) => [
                        'key' => $key,
                        'label' => $def['label'],
                    ])
                    ->values()
                    ->all(),
            ],
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        PortalPermission::authorize(15);

        $payload = $this->schedule->fromRequest($request->all());
        if (! $this->schedule->write($payload)) {
            return response()->json([
                'message' => 'Could not save schedule. Ensure application/cache is writable by the web server.',
            ], 500);
        }

        return response()->json([
            'message' => 'Schedule saved. The next scheduler run will use these values.',
            'data' => [
                'schedule' => $this->schedule->resolved(),
                'schedule_path' => $this->schedule->displayPath(),
            ],
        ]);
    }

    public function run(Request $request): JsonResponse
    {
        PortalPermission::authorize(15);

        $data = $request->validate([
            'job_key' => 'required|string|max:80',
        ]);

        try {
            $result = $this->schedule->runNow($data['job_key']);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (Throwable $e) {
            return response()->json([
                'message' => 'Job failed: '.$e->getMessage(),
            ], 500);
        }

        return response()->json([
            'message' => ($result['ok'] ? 'Ran: ' : 'Job finished with errors: ').$result['label'],
            'data' => $result,
        ], $result['ok'] ? 200 : 500);
    }
}
