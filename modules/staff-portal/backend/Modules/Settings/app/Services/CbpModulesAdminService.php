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

    /**
     * Built-in CBP modules that must exist for home + SSO launch.
     * New installs and settings pages call ensureCoreModules().
     *
     * @var list<array<string, mixed>>
     */
    public const CORE_MODULES = [
        [
            'module_key' => 'staff_portal',
            'system_name' => 'Staff Portal',
            'description' => 'Manage staff details, contracts, appraisals and access HR services efficiently.',
            'base_url' => 'dashboard',
            'base_url_development' => null,
            'base_url_production' => null,
            'icon_class' => 'fa-users',
            'permission_code' => '84',
            'uses_staff_portal_token' => 0,
            'is_production' => 1,
            'is_enabled' => 1,
            'show_in_apm_menu' => 1,
            'alternate_base_url' => 'auth/profile',
            'alternate_for_role_id' => 17,
            'target_resolver' => 'codeigniter',
            'sort_order' => 10,
        ],
        [
            'module_key' => 'approvals_management',
            'system_name' => 'Approvals Management (APM)',
            'description' => 'Tracks submissions, reviews, and approvals for travel matrices, single and special memos, change, DSA and ARF requests.',
            'base_url' => 'apm',
            'base_url_development' => null,
            'base_url_production' => null,
            'icon_class' => 'fa-sitemap',
            'permission_code' => '85',
            'uses_staff_portal_token' => 1,
            'is_production' => 1,
            'is_enabled' => 1,
            'show_in_apm_menu' => 0,
            'alternate_base_url' => null,
            'alternate_for_role_id' => null,
            'target_resolver' => 'staff_app_token',
            'sort_order' => 20,
        ],
        [
            'module_key' => 'finance_management',
            'system_name' => 'Finance Management',
            'description' => 'Manage financial reports, invoices, budgets, transactions, and vendor information.',
            'base_url' => 'finance',
            'base_url_development' => null,
            'base_url_production' => null,
            'icon_class' => 'fa-wallet',
            'permission_code' => '92',
            'uses_staff_portal_token' => 1,
            'is_production' => 1,
            'is_enabled' => 1,
            'show_in_apm_menu' => 1,
            'alternate_base_url' => null,
            'alternate_for_role_id' => null,
            'target_resolver' => 'staff_app_token',
            'sort_order' => 30,
        ],
        [
            'module_key' => 'helpdesk_itsm',
            'system_name' => 'HelpDesk',
            'description' => 'Log incidents and service requests; session opens from the Staff portal (same sign-on as APM).',
            'base_url' => 'helpdesk',
            'base_url_development' => 'http://localhost/staff/helpdesk',
            'base_url_production' => null,
            'icon_class' => 'fa-headset',
            'permission_code' => '93',
            'uses_staff_portal_token' => 1,
            'is_production' => 1,
            'is_enabled' => 1,
            'show_in_apm_menu' => 0,
            'alternate_base_url' => null,
            'alternate_for_role_id' => null,
            'target_resolver' => 'staff_app_token',
            'sort_order' => 35,
        ],
        [
            'module_key' => 'risk_register',
            'system_name' => 'Risk Register',
            'description' => 'Enterprise risk register, quarterly reviews, and OIO oversight.',
            'base_url' => 'risk-register',
            'base_url_development' => null,
            'base_url_production' => null,
            'icon_class' => 'fa-shield-halved',
            'permission_code' => '118',
            'uses_staff_portal_token' => 1,
            'is_production' => 1,
            'is_enabled' => 1,
            'show_in_apm_menu' => 1,
            'alternate_base_url' => null,
            'alternate_for_role_id' => null,
            'target_resolver' => 'staff_app_token',
            'sort_order' => 40,
        ],
        [
            'module_key' => 'it',
            'system_name' => 'IT',
            'description' => 'IT services, assets, and operational tooling for Africa CDC staff.',
            'base_url' => 'it',
            'base_url_development' => null,
            'base_url_production' => null,
            'icon_class' => 'fa-cogs',
            'permission_code' => '125',
            'uses_staff_portal_token' => 1,
            'is_production' => 1,
            'is_enabled' => 1,
            'show_in_apm_menu' => 0,
            'alternate_base_url' => null,
            'alternate_for_role_id' => null,
            'target_resolver' => 'staff_app_token',
            'sort_order' => 45,
        ],
        [
            'module_key' => 'registry',
            'system_name' => 'Registry',
            'description' => 'Weekly, current-year, and archived registry documents.',
            'base_url' => 'registry',
            'base_url_development' => null,
            'base_url_production' => null,
            'icon_class' => 'fa-file-alt',
            'permission_code' => '126',
            'uses_staff_portal_token' => 1,
            'is_production' => 1,
            'is_enabled' => 1,
            'show_in_apm_menu' => 0,
            'alternate_base_url' => null,
            'alternate_for_role_id' => null,
            'target_resolver' => 'staff_app_token',
            'sort_order' => 50,
        ],
    ];

    /**
     * Catalog modules that are not built yet. Seeded disabled so they stay
     * out of the menu until enabled, or until Show for Admin is turned on.
     *
     * @var list<array{module_key: string, system_name: string, icon_class: string, base_url: string}>
     */
    public const PLACEHOLDER_MODULES = [
        ['module_key' => 'office_seating', 'system_name' => 'Office Seating (OFS)', 'icon_class' => 'fa-chair', 'base_url' => 'office-seating'],
        ['module_key' => 'room_booking', 'system_name' => 'Room Booking (RMB)', 'icon_class' => 'fa-door-open', 'base_url' => 'room-booking'],
        ['module_key' => 'access_control', 'system_name' => 'Access Control (ACL)', 'icon_class' => 'fa-key', 'base_url' => 'access-control'],
        ['module_key' => 'fleet', 'system_name' => 'Fleet Management (FLT)', 'icon_class' => 'fa-car', 'base_url' => 'fleet'],
        ['module_key' => 'recruitment', 'system_name' => 'Recruitment (REC)', 'icon_class' => 'fa-user-plus', 'base_url' => 'recruitment'],
        ['module_key' => 'staff_locator', 'system_name' => 'Staff Locator (STL)', 'icon_class' => 'fa-location-dot', 'base_url' => 'staff-locator'],
        ['module_key' => 'self_service', 'system_name' => 'Self Service (SLS)', 'icon_class' => 'fa-user', 'base_url' => 'self-service'],
        ['module_key' => 'work_time', 'system_name' => 'Work Time Management (WTM)', 'icon_class' => 'fa-clock', 'base_url' => 'work-time'],
        ['module_key' => 'budgeting', 'system_name' => 'Budgeting (BDG)', 'icon_class' => 'fa-file-invoice-dollar', 'base_url' => 'budgeting'],
        ['module_key' => 'accounting', 'system_name' => 'Accounting (ACC)', 'icon_class' => 'fa-calculator', 'base_url' => 'accounting'],
        ['module_key' => 'procurement_plan', 'system_name' => 'Annual Procurement Plan (APP)', 'icon_class' => 'fa-list-check', 'base_url' => 'procurement-plan'],
        ['module_key' => 'sourcing', 'system_name' => 'Sourcing (SRC)', 'icon_class' => 'fa-magnifying-glass-dollar', 'base_url' => 'sourcing'],
        ['module_key' => 'e_procurement', 'system_name' => 'Electronic Procurement (EPR)', 'icon_class' => 'fa-cart-shopping', 'base_url' => 'e-procurement'],
        ['module_key' => 'contracts', 'system_name' => 'Contracts (CTR)', 'icon_class' => 'fa-file-contract', 'base_url' => 'contracts'],
        ['module_key' => 'logistics', 'system_name' => 'Logistics (LOG)', 'icon_class' => 'fa-truck', 'base_url' => 'logistics'],
        ['module_key' => 'assets', 'system_name' => 'Assets (AST)', 'icon_class' => 'fa-boxes-stacked', 'base_url' => 'assets'],
        ['module_key' => 'travel', 'system_name' => 'Travel (TRV)', 'icon_class' => 'fa-plane', 'base_url' => 'travel'],
        ['module_key' => 'misdashboard', 'system_name' => 'MIS Dashboard (MSD)', 'icon_class' => 'fa-chart-pie', 'base_url' => 'misdashboard'],
        ['module_key' => 'epr_logistics', 'system_name' => 'EPR Logistics (LOG)', 'icon_class' => 'fa-truck-medical', 'base_url' => 'epr-logistics'],
        ['module_key' => 'ephem', 'system_name' => 'ePHEM (EPH)', 'icon_class' => 'fa-kit-medical', 'base_url' => 'ephem'],
        ['module_key' => 'avhoc', 'system_name' => 'AVHOC (AVH)', 'icon_class' => 'fa-people-group', 'base_url' => 'avhoc'],
        ['module_key' => 'frorp', 'system_name' => 'FRORP (FRP)', 'icon_class' => 'fa-flag', 'base_url' => 'frorp'],
        ['module_key' => 'ems', 'system_name' => 'EMS (EMS)', 'icon_class' => 'fa-truck-medical', 'base_url' => 'ems'],
        ['module_key' => 'events_dashboard', 'system_name' => 'Events Dashboard (EVD)', 'icon_class' => 'fa-calendar-days', 'base_url' => 'events-dashboard'],
        ['module_key' => 'imst', 'system_name' => 'IMST tracker (IMS)', 'icon_class' => 'fa-virus', 'base_url' => 'imst'],
        ['module_key' => 'nims', 'system_name' => 'NIMS (NIM)', 'icon_class' => 'fa-flask', 'base_url' => 'nims'],
        ['module_key' => 'asana', 'system_name' => 'Asana (ASN)', 'icon_class' => 'fa-list-check', 'base_url' => 'asana'],
        ['module_key' => 'prioritization', 'system_name' => 'Prioritization (PRI)', 'icon_class' => 'fa-ranking-star', 'base_url' => 'prioritization'],
        ['module_key' => 'knowledge_hub', 'system_name' => 'Knowledge Hub (KH)', 'icon_class' => 'fa-book', 'base_url' => 'knowledge-hub'],
        ['module_key' => 'redcap', 'system_name' => 'RedCap (RDC)', 'icon_class' => 'fa-database', 'base_url' => 'redcap'],
        ['module_key' => 'media_hub', 'system_name' => 'Media Hub (MDH)', 'icon_class' => 'fa-bullhorn', 'base_url' => 'media-hub'],
        ['module_key' => 'cue', 'system_name' => 'CUE (CUE)', 'icon_class' => 'fa-graduation-cap', 'base_url' => 'cue'],
        ['module_key' => 'lms', 'system_name' => 'Learning Management System (LMS)', 'icon_class' => 'fa-chalkboard-user', 'base_url' => 'lms'],
        ['module_key' => 'scorecard', 'system_name' => 'Scorecard Dashboard (SCD)', 'icon_class' => 'fa-chart-column', 'base_url' => 'scorecard'],
        ['module_key' => 'starlink', 'system_name' => 'StarLink Dashboard (SLD)', 'icon_class' => 'fa-satellite-dish', 'base_url' => 'starlink'],
    ];

    /**
     * Home division chips. Operations tabs match the home row
     * (PRA, Admin & Facilities, HR, Finance, SCM, MIS, OIO).
     * Program chips are the same control on the Programs view.
     *
     * @var list<string>
     */
    public const PRIMARY_TABS = [
        'PRA',
        'Admin & Facilities',
        'HR',
        'Finance',
        'SCM',
        'MIS',
        'OIO',
        'EPR',
        'SDI',
        'LAB',
        'Science',
        'Comms',
        'PHIR',
        'Lusaka Agenda',
    ];

    /**
     * Top home tabs. Keys are stored; labels are shown in settings.
     *
     * @var array<string, string>
     */
    public const HOME_AREAS = [
        'operations' => 'Operations',
        'programs' => 'Programs',
        'configuration' => 'Configuration',
    ];

    /**
     * Modules that start on the Programs tab. Every other module starts on Operations.
     *
     * @var list<string>
     */
    public const PROGRAM_MODULE_KEYS = [
        'epr_logistics',
        'ephem',
        'avhoc',
        'frorp',
        'ems',
        'events_dashboard',
        'imst',
        'nims',
        'asana',
        'prioritization',
        'knowledge_hub',
        'redcap',
        'media_hub',
        'cue',
        'lms',
        'scorecard',
        'starlink',
    ];

    /**
     * Default chip for each module. Blank rows are filled once; later edits are kept.
     *
     * @var array<string, string>
     */
    public const PRIMARY_TAB_BY_MODULE = [
        'pra' => 'PRA',
        'approvals_management' => 'Admin & Facilities',
        'helpdesk_itsm' => 'Admin & Facilities',
        'helpdesk' => 'Admin & Facilities',
        'registry' => 'Admin & Facilities',
        'office_seating' => 'Admin & Facilities',
        'room_booking' => 'Admin & Facilities',
        'access_control' => 'Admin & Facilities',
        'fleet' => 'Admin & Facilities',
        'staff_portal' => 'HR',
        'recruitment' => 'HR',
        'staff_locator' => 'HR',
        'self_service' => 'HR',
        'work_time' => 'HR',
        'budgeting' => 'Finance',
        'finance_management' => 'Finance',
        'accounting' => 'Finance',
        'procurement_plan' => 'SCM',
        'sourcing' => 'SCM',
        'e_procurement' => 'SCM',
        'contracts' => 'SCM',
        'logistics' => 'SCM',
        'assets' => 'SCM',
        'travel' => 'SCM',
        'it' => 'MIS',
        'devtrack' => 'MIS',
        'misdashboard' => 'MIS',
        'risk_register' => 'OIO',
        'epr_logistics' => 'EPR',
        'ephem' => 'EPR',
        'avhoc' => 'EPR',
        'frorp' => 'EPR',
        'ems' => 'SDI',
        'events_dashboard' => 'SDI',
        'imst' => 'SDI',
        'nims' => 'LAB',
        'asana' => 'LAB',
        'prioritization' => 'Science',
        'knowledge_hub' => 'Science',
        'redcap' => 'Science',
        'media_hub' => 'Comms',
        'cue' => 'PHIR',
        'lms' => 'PHIR',
        'scorecard' => 'Lusaka Agenda',
        'starlink' => 'MIS',
    ];

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
            'fa-chair' => 'Chair',
            'fa-door-open' => 'Door',
            'fa-car' => 'Car',
            'fa-user-plus' => 'User plus',
            'fa-location-dot' => 'Location',
            'fa-clock' => 'Clock',
            'fa-file-invoice-dollar' => 'Invoice',
            'fa-calculator' => 'Calculator',
            'fa-list-check' => 'Checklist',
            'fa-magnifying-glass-dollar' => 'Search',
            'fa-cart-shopping' => 'Cart',
            'fa-file-contract' => 'Contract',
            'fa-truck' => 'Truck',
            'fa-boxes-stacked' => 'Boxes',
            'fa-plane' => 'Plane',
            'fa-chart-pie' => 'Chart pie',
            'fa-truck-medical' => 'Medical truck',
            'fa-kit-medical' => 'Medical kit',
            'fa-people-group' => 'People',
            'fa-flag' => 'Flag',
            'fa-virus' => 'Virus',
            'fa-flask' => 'Flask',
            'fa-ranking-star' => 'Ranking',
            'fa-book' => 'Book',
            'fa-database' => 'Database',
            'fa-bullhorn' => 'Bullhorn',
            'fa-graduation-cap' => 'Graduation cap',
            'fa-chalkboard-user' => 'Teaching',
            'fa-chart-column' => 'Chart column',
            'fa-satellite-dish' => 'Satellite',
            'fa-calendar-days' => 'Calendar',
        ];
    }

    public function tableExists(): bool
    {
        return Schema::hasTable('cbp_modules');
    }

    /**
     * Insert missing core modules and ensure each module permission is on the admin group.
     * Safe to call on every settings load / nav bootstrap.
     *
     * @return array{inserted: list<string>, assigned: int}
     */
    public function ensureCoreModules(): array
    {
        $inserted = [];
        $assigned = 0;
        if (! $this->tableExists()) {
            return ['inserted' => $inserted, 'assigned' => $assigned];
        }

        $this->ensureShowForAdminColumn();
        $this->ensurePrimaryTabColumn();
        $this->ensureHomeAreaColumn();

        foreach (self::CORE_MODULES as $def) {
            $key = (string) $def['module_key'];
            $existing = DB::table('cbp_modules')->where('module_key', $key)->first();
            if (! $existing) {
                $permCode = (string) ($def['permission_code'] ?? '');
                if ($permCode !== '' && Schema::hasTable('permissions')) {
                    $this->ensureNamedPermissionExists((int) $permCode, $key, (string) $def['system_name']);
                }
                DB::table('cbp_modules')->insert([
                    'module_key' => $key,
                    'system_name' => mb_substr((string) $def['system_name'], 0, 191),
                    'description' => $def['description'] ?? null,
                    'base_url' => (string) ($def['base_url'] ?? ''),
                    'base_url_development' => $this->nullableString($def['base_url_development'] ?? null),
                    'base_url_production' => $this->nullableString($def['base_url_production'] ?? null),
                    'icon_class' => mb_substr((string) ($def['icon_class'] ?? 'fa-th'), 0, 128),
                    'permission_code' => mb_substr((string) ($def['permission_code'] ?? ''), 0, 32),
                    'uses_staff_portal_token' => (int) ($def['uses_staff_portal_token'] ?? 0),
                    'is_production' => (int) ($def['is_production'] ?? 1),
                    'is_enabled' => (int) ($def['is_enabled'] ?? 1),
                    'show_for_admin' => (int) ($def['show_for_admin'] ?? 0),
                    'show_in_apm_menu' => (int) ($def['show_in_apm_menu'] ?? 0),
                    'primary_tab' => self::PRIMARY_TAB_BY_MODULE[$key] ?? null,
                    'home_area' => $this->defaultHomeArea($key),
                    'alternate_base_url' => $this->nullableString($def['alternate_base_url'] ?? null),
                    'alternate_for_role_id' => $this->nullableUint($def['alternate_for_role_id'] ?? null),
                    'target_resolver' => (string) ($def['target_resolver'] ?? 'codeigniter'),
                    'sort_order' => (int) ($def['sort_order'] ?? 100),
                ]);
                $inserted[] = $key;
                $existing = DB::table('cbp_modules')->where('module_key', $key)->first();
            }

            if ($existing) {
                $before = $this->groupHasPermission((int) $existing->permission_code, self::AUTO_ASSIGN_GROUP_ID);
                $this->ensureModulePermissionAssignedToAdmin((int) $existing->id);
                if (! $before && $this->groupHasPermission((int) $existing->permission_code, self::AUTO_ASSIGN_GROUP_ID)) {
                    $assigned++;
                }
            }
        }

        $inserted = array_values(array_unique(array_merge($inserted, $this->ensurePlaceholderModules())));
        $this->backfillPrimaryTabs();
        $this->backfillHomeAreas();

        $this->disableDuplicateCoreLaunchTargets();

        // Ensure Funding Portfolio manage permission exists on finance/admin groups.
        try {
            (new FundingPortfolioPermissionSeeder)->seed();
        } catch (\Throwable) {
            // Permissions table may be unavailable during early bootstrap.
        }

        try {
            (new ItModulePermissionSeeder)->seed();
        } catch (\Throwable) {
            // Permissions table may be unavailable during early bootstrap.
        }

        try {
            (new RegistryModulePermissionSeeder)->seed();
        } catch (\Throwable) {
            // Permissions table may be unavailable during early bootstrap.
        }

        // Any other enabled modules also get admin auto-assign (new systems).
        $rows = DB::table('cbp_modules')->select(['id', 'permission_code'])->get();
        foreach ($rows as $row) {
            $before = $this->groupHasPermission((int) $row->permission_code, self::AUTO_ASSIGN_GROUP_ID);
            $this->ensureModulePermissionAssignedToAdmin((int) $row->id);
            if (! $before && $this->groupHasPermission((int) $row->permission_code, self::AUTO_ASSIGN_GROUP_ID)) {
                $assigned++;
            }
        }

        return ['inserted' => $inserted, 'assigned' => $assigned];
    }

    /**
     * Admins can open a disabled module when this flag is on.
     */
    public function ensureShowForAdminColumn(): void
    {
        if (! $this->tableExists() || Schema::hasColumn('cbp_modules', 'show_for_admin')) {
            return;
        }

        Schema::table('cbp_modules', function ($table) {
            $table->boolean('show_for_admin')->default(false)->after('is_enabled');
        });
    }

    /**
     * Home division chip this module appears under.
     */
    public function ensurePrimaryTabColumn(): void
    {
        if (! $this->tableExists() || Schema::hasColumn('cbp_modules', 'primary_tab')) {
            return;
        }

        Schema::table('cbp_modules', function ($table) {
            $table->string('primary_tab', 64)->nullable()->after('system_name');
        });
    }

    /**
     * Top home tab: operations, programs, or configuration.
     */
    public function ensureHomeAreaColumn(): void
    {
        if (! $this->tableExists() || Schema::hasColumn('cbp_modules', 'home_area')) {
            return;
        }

        Schema::table('cbp_modules', function ($table) {
            $table->string('home_area', 32)->nullable()->after('primary_tab');
        });
    }

    /**
     * Fill a blank home area from the catalog. Does not overwrite a saved choice.
     */
    public function backfillHomeAreas(): void
    {
        if (! $this->tableExists() || ! Schema::hasColumn('cbp_modules', 'home_area')) {
            return;
        }

        $rows = DB::table('cbp_modules')
            ->where(function ($query) {
                $query->whereNull('home_area')->orWhere('home_area', '');
            })
            ->get(['id', 'module_key']);

        foreach ($rows as $row) {
            DB::table('cbp_modules')->where('id', $row->id)->update([
                'home_area' => $this->defaultHomeArea((string) $row->module_key),
                'updated_at' => now(),
            ]);
        }
    }

    public function defaultHomeArea(string $moduleKey): string
    {
        return in_array($moduleKey, self::PROGRAM_MODULE_KEYS, true) ? 'programs' : 'operations';
    }

    public function normalizeHomeArea(mixed $value): ?string
    {
        $area = strtolower(trim((string) ($value ?? '')));
        if ($area === '' || ! array_key_exists($area, self::HOME_AREAS)) {
            return null;
        }

        return $area;
    }

    /**
     * Fill a blank primary tab from the catalog. Does not overwrite a saved choice.
     */
    public function backfillPrimaryTabs(): void
    {
        if (! $this->tableExists() || ! Schema::hasColumn('cbp_modules', 'primary_tab')) {
            return;
        }

        foreach (self::PRIMARY_TAB_BY_MODULE as $key => $tab) {
            DB::table('cbp_modules')
                ->where('module_key', $key)
                ->where(function ($query) {
                    $query->whereNull('primary_tab')->orWhere('primary_tab', '');
                })
                ->update(['primary_tab' => $tab, 'updated_at' => now()]);
        }
    }

    public function normalizePrimaryTab(mixed $value): ?string
    {
        $tab = trim((string) ($value ?? ''));
        if ($tab === '') {
            return null;
        }
        if (! in_array($tab, self::PRIMARY_TABS, true)) {
            return null;
        }

        return $tab;
    }

    /**
     * Insert catalog modules that have no app yet. Existing rows are left as they are.
     *
     * @return list<string>
     */
    public function ensurePlaceholderModules(): array
    {
        $inserted = [];
        if (! $this->tableExists()) {
            return $inserted;
        }

        $this->ensureShowForAdminColumn();
        $this->ensurePrimaryTabColumn();
        $this->ensureHomeAreaColumn();
        $sort = 200;
        foreach (self::PLACEHOLDER_MODULES as $def) {
            $key = (string) $def['module_key'];
            $sort += 10;
            if (DB::table('cbp_modules')->where('module_key', $key)->exists()) {
                continue;
            }

            $name = (string) $def['system_name'];
            $permId = 0;
            try {
                $permId = $this->createPermissionAndAssignAdmin($key, $name);
            } catch (\Throwable) {
                $permId = 0;
            }

            DB::table('cbp_modules')->insert([
                'module_key' => $key,
                'system_name' => mb_substr($name, 0, 191),
                'description' => 'Not available yet.',
                'base_url' => (string) $def['base_url'],
                'base_url_development' => null,
                'base_url_production' => null,
                'icon_class' => mb_substr((string) $def['icon_class'], 0, 128),
                'permission_code' => $permId > 0 ? (string) $permId : '0',
                'uses_staff_portal_token' => 0,
                'is_production' => 1,
                'is_enabled' => 0,
                'show_for_admin' => 0,
                'show_in_apm_menu' => 0,
                'primary_tab' => self::PRIMARY_TAB_BY_MODULE[$key] ?? null,
                'home_area' => $this->defaultHomeArea($key),
                'alternate_base_url' => null,
                'alternate_for_role_id' => null,
                'target_resolver' => 'codeigniter',
                'sort_order' => $sort,
            ]);
            $inserted[] = $key;
        }

        return $inserted;
    }

    /**
     * Disable extra enabled rows that launch the same app path as a CORE module
     * (e.g. a legacy HelpDesk row beside helpdesk_itsm). Keeps the CORE module_key.
     */
    protected function disableDuplicateCoreLaunchTargets(): void
    {
        $canonicalByPath = [];
        foreach (self::CORE_MODULES as $def) {
            $path = self::launchPathKey((string) ($def['base_url'] ?? ''));
            if ($path === '') {
                continue;
            }
            $canonicalByPath[$path] = (string) $def['module_key'];
        }

        if ($canonicalByPath === []) {
            return;
        }

        $enabled = DB::table('cbp_modules')
            ->where('is_enabled', 1)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get(['id', 'module_key', 'base_url', 'system_name']);

        /** @var array<string, list<object>> $groups */
        $groups = [];
        foreach ($enabled as $row) {
            $path = self::launchPathKey((string) ($row->base_url ?? ''));
            if ($path === '' || ! isset($canonicalByPath[$path])) {
                $name = strtolower(preg_replace('/\s+/', '', (string) ($row->system_name ?? '')) ?? '');
                if ($name === 'helpdesk' && isset($canonicalByPath['helpdesk'])) {
                    $path = 'helpdesk';
                } else {
                    continue;
                }
            }
            $groups[$path][] = $row;
        }

        $disableIds = [];
        foreach ($groups as $path => $rows) {
            if (count($rows) < 2) {
                continue;
            }
            $canonicalKey = $canonicalByPath[$path];
            $keepId = null;
            foreach ($rows as $row) {
                if ((string) $row->module_key === $canonicalKey) {
                    $keepId = (int) $row->id;
                    break;
                }
            }
            if ($keepId === null) {
                $keepId = (int) $rows[0]->id;
            }
            foreach ($rows as $row) {
                if ((int) $row->id !== $keepId) {
                    $disableIds[] = (int) $row->id;
                }
            }
        }

        if ($disableIds === []) {
            return;
        }

        DB::table('cbp_modules')
            ->whereIn('id', $disableIds)
            ->update(['is_enabled' => 0, 'updated_at' => now()]);
    }

    /**
     * Normalize base_url / absolute URL to the last path segment (apm, helpdesk, …).
     */
    public static function launchPathKey(string $baseUrl): string
    {
        $s = trim($baseUrl);
        if ($s === '') {
            return '';
        }
        if (preg_match('#^https?://#i', $s)) {
            $path = (string) (parse_url($s, PHP_URL_PATH) ?: '');
            $s = $path;
        }
        $s = strtolower(trim(str_replace('\\', '/', $s), '/'));
        if ($s === '') {
            return '';
        }
        $parts = array_values(array_filter(explode('/', $s), static fn ($p) => $p !== ''));

        return $parts === [] ? '' : (string) $parts[array_key_last($parts)];
    }

    protected function groupHasPermission(int $permissionId, int $groupId): bool
    {
        if ($permissionId < 1 || $groupId < 1 || ! Schema::hasTable('group_permissions')) {
            return false;
        }

        return DB::table('group_permissions')
            ->where('group_id', $groupId)
            ->where('permission_id', $permissionId)
            ->exists();
    }

    /**
     * Ensure a permission row exists at a known id (legacy CBP codes 84/85/92/93).
     */
    protected function ensureNamedPermissionExists(int $id, string $moduleKey, string $systemName): void
    {
        if ($id < 1 || ! Schema::hasTable('permissions')) {
            return;
        }
        if (DB::table('permissions')->where('id', $id)->exists()) {
            return;
        }

        $name = 'cbp_'.trim((string) preg_replace('/[^a-z0-9_]+/', '_', strtolower($moduleKey)), '_');
        $insert = [
            'id' => $id,
            'name' => $name,
            'definition' => mb_substr('CBP module access: '.trim($systemName), 0, 255),
        ];
        if (Schema::hasColumn('permissions', 'module')) {
            $insert['module'] = 'cbp';
        }
        try {
            DB::table('permissions')->insert($insert);
        } catch (\Throwable) {
            // Auto-increment collision — leave existing registry alone.
        }
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
        $this->ensureShowForAdminColumn();
        $this->ensurePrimaryTabColumn();
        $this->ensureHomeAreaColumn();

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

        $primaryTabError = $this->primaryTabError($data);
        if ($primaryTabError !== null) {
            return ['ok' => false, 'message' => $primaryTabError];
        }
        $homeAreaError = $this->homeAreaError($data);
        if ($homeAreaError !== null) {
            return ['ok' => false, 'message' => $homeAreaError];
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
                    'show_for_admin' => $this->boolInt($data['show_for_admin'] ?? false),
                    'show_in_apm_menu' => $this->boolInt($data['show_in_apm_menu'] ?? false),
                    'primary_tab' => $this->normalizePrimaryTab($data['primary_tab'] ?? null),
                    'home_area' => $this->normalizeHomeArea($data['home_area'] ?? null),
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
        $this->ensureShowForAdminColumn();
        $this->ensurePrimaryTabColumn();
        $this->ensureHomeAreaColumn();
        if (! DB::table('cbp_modules')->where('id', $id)->exists()) {
            return ['ok' => false, 'message' => 'Module not found.'];
        }

        $name = trim((string) ($data['system_name'] ?? ''));
        if ($name === '') {
            return ['ok' => false, 'message' => 'System name is required.'];
        }

        $primaryTabError = $this->primaryTabError($data);
        if ($primaryTabError !== null) {
            return ['ok' => false, 'message' => $primaryTabError];
        }
        $homeAreaError = $this->homeAreaError($data);
        if ($homeAreaError !== null) {
            return ['ok' => false, 'message' => $homeAreaError];
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
            'show_for_admin' => $this->boolInt($data['show_for_admin'] ?? false),
            'show_in_apm_menu' => $this->boolInt($data['show_in_apm_menu'] ?? false),
            'primary_tab' => $this->normalizePrimaryTab($data['primary_tab'] ?? null),
            'home_area' => $this->normalizeHomeArea($data['home_area'] ?? null),
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

    /**
     * @param  array<string, mixed>  $data
     */
    protected function primaryTabError(array $data): ?string
    {
        $tab = trim((string) ($data['primary_tab'] ?? ''));
        if ($tab === '') {
            return null;
        }
        if (! in_array($tab, self::PRIMARY_TABS, true)) {
            return 'Primary tab must be one of the home division chips.';
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function homeAreaError(array $data): ?string
    {
        if ($this->normalizeHomeArea($data['home_area'] ?? null) === null) {
            return 'Choose Operations, Programs, or Configuration.';
        }

        return null;
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
