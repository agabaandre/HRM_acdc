<?php

use Illuminate\Database\Migrations\Migration;
use Modules\Leave\Support\LeaveAccess;

return new class extends Migration
{
    public function up(): void
    {
        LeaveAccess::syncManageBalancesPermission();
    }

    public function down(): void
    {
        // Keep permission 96; assignment is the live access control.
    }
};
