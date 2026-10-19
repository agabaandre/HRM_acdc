<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\StoreExternalIntegrationRequest;
use App\Http\Requests\Api\V1\Admin\UpdateExternalIntegrationRequest;
use App\Models\ExternalIntegration;
use App\Models\User;
use App\Services\ApprovalNotifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ExternalIntegrationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', ExternalIntegration::class);

        /** @var User $user */
        $user = $request->user();

        $query = ExternalIntegration::query()
            ->with('emailProvider:id,name,driver')
            ->orderBy('name');

        if (! $user->is_admin) {
            $ids = $user->allowedExternalIntegrationIds() ?? [];
            $query->whereIn('id', $ids === [] ? [0] : $ids);
        }

        $integrations = $query
            ->get()
            ->map(fn (ExternalIntegration $integration) => $this->transform($integration));

        return response()->json(['data' => $integrations]);
    }

    public function store(StoreExternalIntegrationRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        if (! $user->canRegisterClients()) {
            return response()->json([
                'message' => $user->is_admin
                    ? 'You are not allowed to create clients.'
                    : 'Enable authenticator app (2FA) in Security settings before registering a client.',
            ], 403);
        }

        $this->authorize('create', ExternalIntegration::class);
        $data = $request->validated();
        $clientSecret = $this->resolveClientSecret($data);
        $slug = $data['slug'] ?? $data['client_id'] ?? Str::slug($data['name']);

        $isActive = $user->is_admin
            ? (bool) ($data['is_active'] ?? true)
            : false;

        $integration = ExternalIntegration::query()->create([
            'name' => $data['name'],
            'slug' => $slug,
            'api_key_hash' => ExternalIntegration::hashClientSecret($clientSecret),
            'api_key_prefix' => ExternalIntegration::clientSecretHint($clientSecret),
            'email_provider_id' => $data['email_provider_id'] ?? null,
            'allowed_ips' => $data['allowed_ips'] ?? [],
            'settings' => $data['settings'] ?? [],
            'is_active' => $isActive,
            'description' => $data['description'] ?? null,
        ]);

        if (! $user->is_admin) {
            $user->externalIntegrations()->syncWithoutDetaching([$integration->id]);
        }

        return response()->json([
            'data' => $this->transform($integration->load('emailProvider:id,name,driver')),
            'message' => $user->is_admin
                ? 'Integration created. Share client_id and client_secret with the connecting system.'
                : 'Client created. It stays inactive until an administrator activates it. Share client_id and client_secret with the connecting system.',
            'client_secret' => $clientSecret,
        ], 201);
    }

    public function show(ExternalIntegration $externalIntegration): JsonResponse
    {
        $this->authorize('view', $externalIntegration);

        $externalIntegration->load('emailProvider:id,name,driver');

        return response()->json(['data' => $this->transform($externalIntegration)]);
    }

    public function update(
        UpdateExternalIntegrationRequest $request,
        ExternalIntegration $externalIntegration,
        ApprovalNotifier $notifier,
    ): JsonResponse {
        $this->authorize('update', $externalIntegration);

        /** @var User $user */
        $user = $request->user();
        $data = $request->validated();
        $clientSecret = null;
        $wasActive = (bool) $externalIntegration->is_active;

        if (! $user->is_admin) {
            // Non-admins cannot activate their own clients.
            unset($data['is_active']);
        }

        if (($data['generate_secret'] ?? false) || ! empty($data['client_secret'])) {
            $clientSecret = $this->resolveClientSecret($data);
            $data['api_key_hash'] = ExternalIntegration::hashClientSecret($clientSecret);
            $data['api_key_prefix'] = ExternalIntegration::clientSecretHint($clientSecret);
        }

        unset($data['client_secret'], $data['generate_secret']);

        $externalIntegration->update($data);
        $externalIntegration->refresh();

        $becameActive = ! $wasActive && (bool) $externalIntegration->is_active;
        $becameInactive = $wasActive && ! (bool) $externalIntegration->is_active;
        if ($becameActive) {
            $notifier->notifyIntegrationApproved($externalIntegration);
        } elseif ($becameInactive) {
            $notifier->notifyIntegrationRejected($externalIntegration);
        }

        $response = [
            'data' => $this->transform($externalIntegration->load('emailProvider:id,name,driver')),
        ];

        if ($clientSecret !== null) {
            $response['client_secret'] = $clientSecret;
            $response['message'] = 'Integration updated. Share the new client_secret with the connecting system.';
        } elseif ($becameActive) {
            $response['message'] = 'Integration approved and activated. Linked users have been notified.';
        } elseif ($becameInactive) {
            $response['message'] = 'Integration disabled. Linked users have been notified.';
        }

        return response()->json($response);
    }

    public function destroy(ExternalIntegration $externalIntegration): JsonResponse
    {
        $this->authorize('delete', $externalIntegration);

        if ($externalIntegration->is_active) {
            return response()->json([
                'message' => 'Active clients cannot be deleted. Disable the client first.',
            ], 422);
        }

        $externalIntegration->delete();

        return response()->json(['message' => 'Inactive client deleted.']);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function resolveClientSecret(array $data): string
    {
        if (($data['generate_secret'] ?? false) || empty($data['client_secret'])) {
            return ExternalIntegration::generateClientSecret();
        }

        return (string) $data['client_secret'];
    }

    /**
     * @return array<string, mixed>
     */
    private function transform(ExternalIntegration $integration): array
    {
        return [
            'id' => $integration->id,
            'name' => $integration->name,
            'slug' => $integration->slug,
            'client_id' => $integration->slug,
            'client_secret_hint' => $integration->api_key_prefix,
            'email_provider_id' => $integration->email_provider_id,
            'email_provider' => $integration->emailProvider ? [
                'id' => $integration->emailProvider->id,
                'name' => $integration->emailProvider->name,
                'driver' => $integration->emailProvider->driver->value,
            ] : null,
            'allowed_ips' => $integration->allowed_ips ?? [],
            'settings' => $integration->settings ?? [],
            'is_active' => $integration->is_active,
            'last_used_at' => $integration->last_used_at,
            'description' => $integration->description,
            'created_at' => $integration->created_at,
            'updated_at' => $integration->updated_at,
        ];
    }
}
