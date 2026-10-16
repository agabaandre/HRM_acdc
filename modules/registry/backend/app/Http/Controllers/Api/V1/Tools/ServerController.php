<?php

namespace App\Http\Controllers\Api\V1\Tools;

use App\Http\Controllers\Controller;
use App\Models\ItServer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ServerController extends Controller
{
    use AuthorizesHelpdeskTools;

    public function summary(Request $request): JsonResponse
    {
        $this->ensureServerManager($request);

        $counts = ItServer::query()
            ->selectRaw('kind, count(*) as total')
            ->groupBy('kind')
            ->pluck('total', 'kind');

        return response()->json([
            'data' => [
                'total' => (int) $counts->sum(),
                'running' => ItServer::query()->where('status', ItServer::STATUS_RUNNING)->count(),
                'by_kind' => [
                    ItServer::KIND_AZURE_VM => (int) ($counts[ItServer::KIND_AZURE_VM] ?? 0),
                    ItServer::KIND_AZURE_LANDING_ZONE => (int) ($counts[ItServer::KIND_AZURE_LANDING_ZONE] ?? 0),
                    ItServer::KIND_AZURE_STORAGE => (int) ($counts[ItServer::KIND_AZURE_STORAGE] ?? 0),
                    ItServer::KIND_DATACENTER_VM => (int) ($counts[ItServer::KIND_DATACENTER_VM] ?? 0),
                ],
            ],
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $this->ensureServerManager($request);

        $query = ItServer::query()->orderBy('name');

        if ($request->filled('kind')) {
            $query->where('kind', $request->input('kind'));
        }
        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }
        if ($request->filled('q')) {
            $q = '%'.$request->input('q').'%';
            $query->where(function ($sub) use ($q) {
                $sub->where('name', 'like', $q)
                    ->orWhere('hostname', 'like', $q)
                    ->orWhere('location', 'like', $q)
                    ->orWhere('hosts', 'like', $q)
                    ->orWhere('running_details', 'like', $q)
                    ->orWhere('owner_name', 'like', $q)
                    ->orWhere('ip_address', 'like', $q);
            });
        }

        return response()->json([
            'data' => $query->limit(500)->get(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->ensureServerManager($request);

        $row = ItServer::query()->create($this->validated($request) + [
            'created_by_user_id' => $request->user()?->id,
        ]);

        return response()->json(['data' => $row], 201);
    }

    public function update(Request $request, ItServer $server): JsonResponse
    {
        $this->ensureServerManager($request);

        $server->fill($this->validated($request, $server));
        $server->save();

        return response()->json(['data' => $server->fresh()]);
    }

    public function destroy(Request $request, ItServer $server): JsonResponse
    {
        $this->ensureServerManager($request);
        $server->delete();

        return response()->json(['ok' => true]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?ItServer $existing = null): array
    {
        $validated = $request->validate([
            'kind' => ['required', 'string', Rule::in(ItServer::KINDS)],
            'name' => ['required', 'string', 'max:191'],
            'status' => ['required', 'string', Rule::in([
                ItServer::STATUS_RUNNING,
                ItServer::STATUS_STOPPED,
                ItServer::STATUS_DECOMMISSIONED,
            ])],
            'environment' => ['nullable', 'string', 'max:32'],
            'location' => ['nullable', 'string', 'max:191'],
            'hostname' => ['nullable', 'string', 'max:191'],
            'ip_address' => ['nullable', 'string', 'max:64'],
            'operating_system' => ['nullable', 'string', 'max:191'],
            'hosts' => ['nullable', 'string', 'max:5000'],
            'running_details' => ['nullable', 'string', 'max:5000'],
            'owner_name' => ['nullable', 'string', 'max:191'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'details' => ['nullable', 'array'],
            'details.subscription' => ['nullable', 'string', 'max:191'],
            'details.resource_group' => ['nullable', 'string', 'max:191'],
            'details.vm_size' => ['nullable', 'string', 'max:64'],
            'details.public_ip' => ['nullable', 'string', 'max:64'],
            'details.management_group' => ['nullable', 'string', 'max:191'],
            'details.connected_networks' => ['nullable', 'string', 'max:2000'],
            'details.storage_account' => ['nullable', 'string', 'max:191'],
            'details.container_name' => ['nullable', 'string', 'max:191'],
            'details.access_tier' => ['nullable', 'string', 'max:32'],
            'details.replication' => ['nullable', 'string', 'max:64'],
            'details.datacenter' => ['nullable', 'string', 'max:191'],
            'details.cluster' => ['nullable', 'string', 'max:191'],
            'details.cpu' => ['nullable', 'string', 'max:64'],
            'details.memory_gb' => ['nullable', 'numeric', 'min:0', 'max:99999'],
            'details.disk_gb' => ['nullable', 'numeric', 'min:0', 'max:9999999'],
        ]);

        $validated['details'] = $this->detailsForKind(
            $validated['kind'],
            $validated['details'] ?? ($existing?->details ?? []),
        );

        return $validated;
    }

    /**
     * @param  array<string, mixed>  $details
     * @return array<string, mixed>
     */
    private function detailsForKind(string $kind, array $details): array
    {
        $keys = match ($kind) {
            ItServer::KIND_AZURE_VM => ['subscription', 'resource_group', 'vm_size', 'public_ip'],
            ItServer::KIND_AZURE_LANDING_ZONE => ['subscription', 'management_group', 'connected_networks'],
            ItServer::KIND_AZURE_STORAGE => ['storage_account', 'container_name', 'access_tier', 'replication'],
            ItServer::KIND_DATACENTER_VM => ['datacenter', 'cluster', 'cpu', 'memory_gb', 'disk_gb'],
            default => [],
        };

        $kept = [];
        foreach ($keys as $key) {
            if (! array_key_exists($key, $details) || $details[$key] === null || $details[$key] === '') {
                continue;
            }
            $kept[$key] = $details[$key];
        }

        return $kept;
    }
}
