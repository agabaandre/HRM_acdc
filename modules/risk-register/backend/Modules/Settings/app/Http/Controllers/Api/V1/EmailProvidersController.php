<?php

namespace Modules\Settings\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\PortalMailer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Core\Support\PortalPermission;
use Modules\Settings\Services\EmailProvidersService;

class EmailProvidersController extends Controller
{
    public function __construct(
        private EmailProvidersService $providers,
        private PortalMailer $mailer,
    ) {}

    public function drivers(): JsonResponse
    {
        PortalPermission::authorize(15);

        return response()->json(['data' => $this->providers->driverDefinitions()]);
    }

    public function index(): JsonResponse
    {
        PortalPermission::authorize(15);

        return response()->json(['data' => $this->providers->list()]);
    }

    public function store(Request $request): JsonResponse
    {
        PortalPermission::authorize(15);

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'driver' => 'required|string|max:32',
            'from_address' => 'nullable|string|max:255',
            'from_name' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'is_default' => 'sometimes|boolean',
            'is_active' => 'sometimes|boolean',
            'config' => 'sometimes|array',
        ]);

        $row = $this->providers->create($data);

        return response()->json([
            'message' => 'Email provider created.',
            'data' => $this->providers->present($row),
        ], 201);
    }

    public function update(Request $request, string $uuid): JsonResponse
    {
        PortalPermission::authorize(15);

        $provider = $this->providers->findByUuid($uuid);
        $data = $request->validate([
            'name' => 'sometimes|string|max:255',
            'from_address' => 'nullable|string|max:255',
            'from_name' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'is_default' => 'sometimes|boolean',
            'is_active' => 'sometimes|boolean',
            'config' => 'sometimes|array',
        ]);

        $row = $this->providers->update($provider, $data);

        return response()->json([
            'message' => 'Email provider updated.',
            'data' => $this->providers->present($row),
        ]);
    }

    public function destroy(string $uuid): JsonResponse
    {
        PortalPermission::authorize(15);
        $this->providers->delete($this->providers->findByUuid($uuid));

        return response()->json(['message' => 'Email provider deleted.']);
    }

    public function setDefault(string $uuid): JsonResponse
    {
        PortalPermission::authorize(15);
        $row = $this->providers->setDefault($this->providers->findByUuid($uuid));

        return response()->json([
            'message' => 'Default email provider updated.',
            'data' => $this->providers->present($row),
        ]);
    }

    public function test(Request $request, string $uuid): JsonResponse
    {
        PortalPermission::authorize(15);

        $data = $request->validate([
            'to' => 'required|email',
        ]);

        $provider = $this->providers->findByUuid($uuid);
        $this->mailer->send(
            $data['to'],
            'Staff Portal email test',
            '<p>This is a test message from Staff Portal email servers.</p>',
            [],
            $provider,
        );

        return response()->json(['message' => 'Test email sent to '.$data['to'].'.']);
    }
}
