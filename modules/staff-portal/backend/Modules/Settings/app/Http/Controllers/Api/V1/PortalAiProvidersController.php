<?php

namespace Modules\Settings\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Modules\Core\Support\PortalPermission;
use Modules\Settings\Ai\PortalAiCompatibleClient;
use Modules\Settings\Services\PortalAiProviderService;
use Modules\Settings\Support\PortalAiProvidersConfig;

class PortalAiProvidersController extends Controller
{
    public function __construct(
        private PortalAiProviderService $providers,
        private PortalAiCompatibleClient $client,
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
            'driver' => ['required', 'string', Rule::in(PortalAiProvidersConfig::driverKeys())],
            'api_endpoint' => 'nullable|string|max:512',
            'model' => 'nullable|string|max:191',
            'api_key' => 'nullable|string|max:8192',
            'description' => 'nullable|string',
            'is_default' => 'sometimes|boolean',
            'is_active' => 'sometimes|boolean',
        ]);

        $row = $this->providers->create($data);

        return response()->json([
            'message' => 'AI provider created.',
            'data' => $this->providers->present($row),
        ], 201);
    }

    public function update(Request $request, string $uuid): JsonResponse
    {
        PortalPermission::authorize(15);

        $provider = $this->providers->findByUuid($uuid);
        $data = $request->validate([
            'name' => 'sometimes|string|max:255',
            'api_endpoint' => 'nullable|string|max:512',
            'model' => 'nullable|string|max:191',
            'api_key' => 'nullable|string|max:8192',
            'description' => 'nullable|string',
            'is_default' => 'sometimes|boolean',
            'is_active' => 'sometimes|boolean',
        ]);

        $row = $this->providers->update($provider, $data);

        return response()->json([
            'message' => 'AI provider updated.',
            'data' => $this->providers->present($row),
        ]);
    }

    public function destroy(string $uuid): JsonResponse
    {
        PortalPermission::authorize(15);
        $this->providers->delete($this->providers->findByUuid($uuid));

        return response()->json(['message' => 'AI provider deleted.']);
    }

    public function setDefault(string $uuid): JsonResponse
    {
        PortalPermission::authorize(15);
        $row = $this->providers->setDefault($this->providers->findByUuid($uuid));

        return response()->json([
            'message' => 'Default AI provider updated.',
            'data' => $this->providers->present($row),
        ]);
    }

    public function test(Request $request, ?string $uuid = null): JsonResponse
    {
        PortalPermission::authorize(15);

        $validated = $request->validate([
            'api_endpoint' => ['nullable', 'string', 'max:512'],
            'model' => ['nullable', 'string', 'max:191'],
            'api_key' => ['nullable', 'string', 'max:8192'],
            'driver' => ['nullable', 'string', Rule::in(PortalAiProvidersConfig::driverKeys())],
        ]);

        $provider = $uuid ? $this->providers->findByUuid($uuid) : $this->providers->defaultProvider();
        $result = $this->client->testConnection(
            $provider,
            $validated['api_key'] ?? null,
            $validated['api_endpoint'] ?? null,
            $validated['model'] ?? null,
            ($validated['driver'] ?? '') !== '' ? $validated['driver'] : null,
        );

        return response()->json(['data' => $result], $result['ok'] ? 200 : 422);
    }
}
