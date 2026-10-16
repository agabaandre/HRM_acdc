<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ItServer extends Model
{
    public const KIND_AZURE_VM = 'azure_vm';

    public const KIND_AZURE_LANDING_ZONE = 'azure_landing_zone';

    public const KIND_AZURE_STORAGE = 'azure_storage';

    public const KIND_DATACENTER_VM = 'datacenter_vm';

    public const KINDS = [
        self::KIND_AZURE_VM,
        self::KIND_AZURE_LANDING_ZONE,
        self::KIND_AZURE_STORAGE,
        self::KIND_DATACENTER_VM,
    ];

    public const STATUS_RUNNING = 'running';

    public const STATUS_STOPPED = 'stopped';

    public const STATUS_DECOMMISSIONED = 'decommissioned';

    protected $table = 'it_servers';

    protected $fillable = [
        'kind',
        'name',
        'status',
        'environment',
        'location',
        'hostname',
        'ip_address',
        'operating_system',
        'hosts',
        'running_details',
        'owner_name',
        'notes',
        'details',
        'created_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'details' => 'array',
            'created_by_user_id' => 'integer',
        ];
    }
}
