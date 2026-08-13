<?php

namespace Modules\Auth\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Laravel\Passport\Client;
use Laravel\Passport\ClientRepository;
use Laravel\Passport\Passport;
use Modules\Core\Support\PortalPermission;

class OAuthClientApiController extends Controller
{
    public function __construct(
        protected ClientRepository $clients,
    ) {
    }

    public function index(): JsonResponse
    {
        PortalPermission::authorize(17);

        $items = Passport::client()
            ->newQuery()
            ->where('revoked', false)
            ->orderBy('name')
            ->get()
            ->reject(fn (Client $client): bool => $this->isPersonalAccessClient($client))
            ->values()
            ->map(fn (Client $client): array => $this->serializeClient($client))
            ->all();

        return response()->json([
            'data' => $items,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        PortalPermission::authorize(17);

        $payload = [
            'name' => trim((string) $request->input('name', '')),
            'redirect_uris' => $this->normalizeRedirectUris($request->input('redirect_uris', $request->input('redirect'))),
            'public' => $request->boolean('public'),
        ];

        Validator::make($payload, [
            'name' => ['required', 'string', 'max:255'],
            'redirect_uris' => ['required', 'array', 'min:1'],
            'redirect_uris.*' => ['required', 'url', 'max:2048'],
            'public' => ['boolean'],
        ])->validate();

        $client = $this->clients->createAuthorizationCodeGrantClient(
            $payload['name'],
            $payload['redirect_uris'],
            ! $payload['public'],
        );

        return response()->json([
            'data' => $this->serializeClient($client, true),
        ], 201);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        PortalPermission::authorize(17);

        $client = $this->clients->findActive($id);
        abort_if(! $client, 404, 'OAuth client not found.');
        abort_if($this->isPersonalAccessClient($client), 404, 'OAuth client not found.');

        $payload = [
            'name' => trim((string) $request->input('name', $client->name)),
            'redirect_uris' => $this->normalizeRedirectUris(
                $request->input('redirect_uris', $request->input('redirect', $client->redirect_uris)),
            ),
        ];

        Validator::make($payload, [
            'name' => ['required', 'string', 'max:255'],
            'redirect_uris' => ['required', 'array', 'min:1'],
            'redirect_uris.*' => ['required', 'url', 'max:2048'],
        ])->validate();

        $this->clients->update($client, $payload['name'], $payload['redirect_uris']);

        $fresh = $this->clients->findActive($id) ?? $client->fresh();

        return response()->json([
            'message' => 'OAuth client updated.',
            'data' => $this->serializeClient($fresh),
        ]);
    }

    public function destroy(string $id): JsonResponse
    {
        PortalPermission::authorize(17);

        $client = $this->clients->findActive($id);
        abort_if(! $client, 404, 'OAuth client not found.');

        $this->clients->delete($client);

        return response()->json([
            'ok' => true,
            'message' => 'OAuth client revoked.',
        ]);
    }

    /**
     * @return array<int, string>
     */
    protected function normalizeRedirectUris(mixed $value): array
    {
        if (is_array($value)) {
            return collect($value)
                ->map(fn (mixed $item): string => trim((string) $item))
                ->filter()
                ->values()
                ->all();
        }

        return collect(preg_split('/[\r\n,]+/', (string) $value) ?: [])
            ->map(fn (string $item): string => trim($item))
            ->filter()
            ->values()
            ->all();
    }

    protected function isPersonalAccessClient(Client $client): bool
    {
        if ($client->personal_access_client) {
            return true;
        }

        return in_array('personal_access', $client->grant_types, true);
    }

    /**
     * @return array<string, mixed>
     */
    protected function serializeClient(Client $client, bool $includePlainSecret = false): array
    {
        return [
            'id' => $client->getKey(),
            'name' => $client->name,
            'redirect_uris' => array_values(array_filter(
                array_map('strval', is_array($client->redirect_uris) ? $client->redirect_uris : []),
            )),
            'grant_types' => $client->grant_types,
            'public' => ! $client->confidential(),
            'created_at' => $client->created_at?->toJSON(),
            'updated_at' => $client->updated_at?->toJSON(),
            'plain_secret' => $includePlainSecret ? $client->plainSecret : null,
        ];
    }
}
