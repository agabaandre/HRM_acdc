<?php

namespace Modules\Settings\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * CI3-parity admin for `cbp_modules` (create / update + permission seeding).
 */
class CbpModulesAdminService
{
    public const AUTO_ASSIGN_GROUP_ID = 10;

    /** @var list<string> */
    public const TARGET_RESOLVERS = ['codeigniter', 'staff_app_token', 'finance_host', 'external_microservice'];

    /**
     * @return array<string, string>
     */
    public static function targetResolverLabels(): array
    {
        return [
            'codeigniter' => 'Staff portal — internal path (no token)',
            'staff_app_token' => 'Staff host — path with session token (APM / Finance / Helpdesk)',
            'finance_host' => 'Finance app — dev / prod host rules',
            'external_microservice' => 'External system — different server (HTTPS URL)',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function iconOptions(): array
    {
        return [
            'fa-th' => 'Default grid',
            'fa-users' => 'Users',
            'fa-user' => 'User',
            'fa-sitemap' => 'Sitemap',
            'fa-wallet' => 'Wallet',
            'fa-headset' => 'Headset',
            'fa-chart-line' => 'Chart line',
            'fa-building' => 'Building',
            'fa-briefcase' => 'Briefcase',
            'fa-cogs' => 'Cogs',
            'fa-th-large' => 'Grid',
            'fa-file-alt' => 'File',
            'fa-globe' => 'Globe',
            'fa-hand-holding-usd' => 'Hand holding USD',
            'fa-shield-alt' => 'Shield',
            'fa-tachometer-alt' => 'Dashboard',
            'fa-project-diagram' => 'Project diagram',
            'fa-envelope' => 'Envelope',
            'fa-key' => 'Key',
            'fa-external-link-alt' => 'External link',
        ];
    }

    public function tableExists(): bool
    {
        return Schema::hasTable('cbp_modules');
    }

    /**
     * @return list<object>
     */
    public function allOrdered(): array
    {
        if (! $this->tableExists()) {
            return [];
        }

        return DB::table('cbp_modules')
            ->orderBy('sort_order')
            ->orderBy('system_name')
            ->get()
            ->all();
    }

    public function nextSortOrder(): int
    {
        if (! $this->tableExists()) {
            return 100;
        }
        $mx = (int) DB::table('cbp_modules')->max('sort_order');

        return $mx + 10;
    }

    public function nextPermissionIdHint(): int
    {
        if (! Schema::hasTable('permissions')) {
            return 1;
        }

        return (int) DB::table('permissions')->max('id') + 1;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function validateTargetConfiguration(array $data): ?string
    {
        $resolver = isset($data['target_resolver']) ? (string) $data['target_resolver'] : 'codeigniter';
        if (! in_array($resolver, self::TARGET_RESOLVERS, true)) {
            return 'Invalid link target. Choose a valid option from the list.';
        }
        if ($resolver === 'staff_app_token' && trim((string) ($data['base_url'] ?? '')) === '') {
            return '“Staff host + token” requires a path segment under the Staff app (e.g. apm).';
        }
        if ($resolver === 'external_microservice' && ! $this->externalMicroserviceHasAnyUrl($data)) {
            return 'External system: provide a development URL, production URL, or a single URL for all environments.';
        }
        if ($resolver === 'codeigniter'
            && trim((string) ($data['base_url'] ?? '')) === ''
            && trim((string) ($data['alternate_base_url'] ?? '')) === '') {
            return 'Staff portal path: set “Base path” or an alternate path with role ID.';
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{ok: bool, message: string, permission_id?: int, id?: int}
     */
    public function create(array $data): array
    {
        if (! $this->tableExists()) {
            return ['ok' => false, 'message' => 'The cbp_modules table is missing. Run the SQL migration.'];
        }

        $key = strtolower(trim((string) ($data['module_key'] ?? '')));
        $key = (string) preg_replace('/[^a-z0-9_]+/', '_', $key);
        $key = trim($key, '_');
        if ($key === '' || ! preg_match('/^[a-z][a-z0-9_]{0,63}$/', $key)) {
            return ['ok' => false, 'message' => 'Module key is required: start with a letter, then letters, digits, or underscores (max 64 characters).'];
        }
        if (DB::table('cbp_modules')->where('module_key', $key)->exists()) {
            return ['ok' => false, 'message' => 'That module key is already in use.'];
        }

        $name = trim((string) ($data['system_name'] ?? ''));
        if ($name === '') {
            return ['ok' => false, 'message' => 'System name is required.'];
        }

        $validation = $this->validateTargetConfiguration($data);
        if ($validation !== null) {
            return ['ok' => false, 'message' => $validation];
        }

        $resolver = (string) ($data['target_resolver'] ?? 'codeigniter');
        if (! in_array($resolver, self::TARGET_RESOLVERS, true)) {
            $resolver = 'codeigniter';
        }

        $icon = trim((string) ($data['icon_class'] ?? 'fa-th'));
        if ($icon === '') {
            $icon = 'fa-th';
        }

        try {
            $result = DB::transaction(function () use ($data, $key, $name, $resolver, $icon) {
                $newPermId = $this->createPermissionAndAssignAdmin($key, $name);
                if ($newPermId < 1) {
                    throw new \RuntimeException('Could not create a permission row. Check that the permissions and group_permissions tables exist.');
                }

                $id = (int) DB::table('cbp_modules')->insertGetId([
                    'module_key' => $key,
                    'system_name' => mb_substr($name, 0, 191),
                    'description' => isset($data['description']) ? (string) $data['description'] : null,
                    'base_url' => (string) ($data['base_url'] ?? ''),
                    'base_url_development' => $this->nullableString($data['base_url_development'] ?? null),
                    'base_url_production' => $this->nullableString($data['base_url_production'] ?? null),
                    'icon_class' => mb_substr($icon, 0, 128),
                    'permission_code' => mb_substr((string) $newPermId, 0, 32),
                    'uses_staff_portal_token' => $this->boolInt($data['uses_staff_portal_token'] ?? false),
                    'is_production' => $this->boolInt($data['is_production'] ?? true),
                    'is_enabled' => $this->boolInt($data['is_enabled'] ?? true),
                    'show_in_apm_menu' => $this->boolInt($data['show_in_apm_menu'] ?? false),
                    'alternate_base_url' => $this->nullableString($data['alternate_base_url'] ?? null),
                    'alternate_for_role_id' => $this->nullableUint($data['alternate_for_role_id'] ?? null),
                    'target_resolver' => $resolver,
                    'sort_order' => isset($data['sort_order']) && $data['sort_order'] !== '' && $data['sort_order'] !== null
                        ? (int) $data['sort_order']
                        : $this->nextSortOrder(),
                ]);

                return ['id' => $id, 'permission_id' => $newPermId];
            });
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => $e->getMessage()];
        }

        return [
            'ok' => true,
            'message' => 'Module created. New permission ID '.$result['permission_id'].' was added and assigned to admin group (role '.self::AUTO_ASSIGN_GROUP_ID.').',
            'permission_id' => $result['permission_id'],
            'id' => $result['id'],
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{ok: bool, message: string}
     */
    public function update(int $id, array $data): array
    {
        if (! $this->tableExists()) {
            return ['ok' => false, 'message' => 'The cbp_modules table is missing.'];
        }
        if (! DB::table('cbp_modules')->where('id', $id)->exists()) {
            return ['ok' => false, 'message' => 'Module not found.'];
        }

        $name = trim((string) ($data['system_name'] ?? ''));
        if ($name === '') {
            return ['ok' => false, 'message' => 'System name is required.'];
        }

        $validation = $this->validateTargetConfiguration($data);
        if ($validation !== null) {
            return ['ok' => false, 'message' => $validation];
        }

        $resolver = (string) ($data['target_resolver'] ?? 'codeigniter');
        if (! in_array($resolver, self::TARGET_RESOLVERS, true)) {
            $resolver = 'codeigniter';
        }

        $icon = trim((string) ($data['icon_class'] ?? 'fa-th'));
        if ($icon === '') {
            $icon = 'fa-th';
        }

        $permCode = trim((string) ($data['permission_code'] ?? ''));
        if ($permCode === '') {
            return ['ok' => false, 'message' => 'Permission code is required.'];
        }

        DB::table('cbp_modules')->where('id', $id)->update([
            'system_name' => mb_substr($name, 0, 191),
            'description' => isset($data['description']) ? (string) $data['description'] : null,
            'base_url' => (string) ($data['base_url'] ?? ''),
            'base_url_development' => $this->nullableString($data['base_url_development'] ?? null),
            'base_url_production' => $this->nullableString($data['base_url_production'] ?? null),
            'icon_class' => mb_substr($icon, 0, 128),
            'permission_code' => mb_substr($permCode, 0, 32),
            'uses_staff_portal_token' => $this->boolInt($data['uses_staff_portal_token'] ?? false),
            'is_production' => $this->boolInt($data['is_production'] ?? false),
            'is_enabled' => $this->boolInt($data['is_enabled'] ?? false),
            'show_in_apm_menu' => $this->boolInt($data['show_in_apm_menu'] ?? false),
            'alternate_base_url' => $this->nullableString($data['alternate_base_url'] ?? null),
            'alternate_for_role_id' => $this->nullableUint($data['alternate_for_role_id'] ?? null),
            'target_resolver' => $resolver,
            'sort_order' => (int) ($data['sort_order'] ?? 0),
        ]);

        $this->ensureModulePermissionAssignedToAdmin($id);

        return ['ok' => true, 'message' => 'Module saved.'];
    }

    public function ensureModulePermissionAssignedToAdmin(int $moduleId): void
    {
        $row = DB::table('cbp_modules')->where('id', $moduleId)->first();
        if (! $row) {
            return;
        }
        $pid = (int) $row->permission_code;
        if ($pid < 1) {
            return;
        }
        $this->ensurePermissionInGroup($pid, self::AUTO_ASSIGN_GROUP_ID);
    }

    protected function createPermissionAndAssignAdmin(string $moduleKey, string $systemName): int
    {
        if (! Schema::hasTable('permissions')) {
            return 0;
        }
        $base = 'cbp_'.trim((string) preg_replace('/[^a-z0-9_]+/', '_', strtolower($moduleKey)), '_');
        if ($base === 'cbp_') {
            $base = 'cbp_module';
        }
        $candidate = $base;
        $suffix = 2;
        while (DB::table('permissions')->where('name', $candidate)->exists()) {
            $candidate = $base.'_'.$suffix;
            $suffix++;
        }

        $insert = [
            'name' => $candidate,
            'definition' => mb_substr('CBP module access: '.trim($systemName), 0, 255),
        ];
        if (Schema::hasColumn('permissions', 'module')) {
            $insert['module'] = 'cbp';
        }

        $pid = (int) DB::table('permissions')->insertGetId($insert);
        if ($pid < 1) {
            return 0;
        }
        $this->ensurePermissionInGroup($pid, self::AUTO_ASSIGN_GROUP_ID);

        return $pid;
    }

    protected function ensurePermissionInGroup(int $permissionId, int $groupId): void
    {
        if ($permissionId < 1 || $groupId < 1 || ! Schema::hasTable('group_permissions')) {
            return;
        }
        $exists = DB::table('group_permissions')
            ->where('group_id', $groupId)
            ->where('permission_id', $permissionId)
            ->exists();
        if (! $exists) {
            DB::table('group_permissions')->insert([
                'group_id' => $groupId,
                'permission_id' => $permissionId,
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function externalMicroserviceHasAnyUrl(array $data): bool
    {
        foreach (['base_url_development', 'base_url_production', 'base_url'] as $k) {
            if (trim((string) ($data[$k] ?? '')) !== '') {
                return true;
            }
        }

        return false;
    }

    protected function nullableString(mixed $v): ?string
    {
        if ($v === null || $v === '') {
            return null;
        }
        $s = trim((string) $v);

        return $s === '' ? null : $s;
    }

    protected function nullableUint(mixed $v): ?int
    {
        if ($v === null || $v === '') {
            return null;
        }
        $n = (int) $v;

        return $n > 0 ? $n : null;
    }

    protected function boolInt(mixed $v): int
    {
        if (is_bool($v)) {
            return $v ? 1 : 0;
        }
        if (is_string($v)) {
            return in_array(strtolower($v), ['1', 'true', 'yes', 'on'], true) ? 1 : 0;
        }

        return ! empty($v) ? 1 : 0;
    }
}
