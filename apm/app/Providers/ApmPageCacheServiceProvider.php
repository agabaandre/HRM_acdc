<?php

namespace App\Providers;

use App\Models\Activity;
use App\Models\ActivityApprovalTrail;
use App\Models\ApprovalTrail;
use App\Models\ApproverDocumentTimingRecord;
use App\Models\ChangeRequest;
use App\Models\Division;
use App\Models\Matrix;
use App\Models\MatrixApprovalTrail;
use App\Models\NonTravelMemo;
use App\Models\NonTravelMemoApprovalTrail;
use App\Models\OtherMemo;
use App\Models\OtherMemoApprovalTrail;
use App\Models\RequestARF;
use App\Models\ServiceRequest;
use App\Models\ServiceRequestApprovalTrail;
use App\Models\SpecialMemo;
use App\Models\Staff;
use App\Models\WeeklyBriefingContributor;
use App\Models\WeeklyBriefingReport;
use App\Models\WeeklyBriefingSetting;
use App\Services\ApmPageCache;
use Illuminate\Support\ServiceProvider;

class ApmPageCacheServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $documentScopes = [
            'approver_dashboard',
            'reports',
            'matrices',
            'activities',
            'change_requests',
        ];

        $documentModels = [
            Matrix::class,
            Activity::class,
            ChangeRequest::class,
            SpecialMemo::class,
            NonTravelMemo::class,
            OtherMemo::class,
            RequestARF::class,
            ServiceRequest::class,
            ApprovalTrail::class,
            ActivityApprovalTrail::class,
            MatrixApprovalTrail::class,
            NonTravelMemoApprovalTrail::class,
            OtherMemoApprovalTrail::class,
            ServiceRequestApprovalTrail::class,
            ApproverDocumentTimingRecord::class,
        ];

        foreach ($documentModels as $modelClass) {
            $modelClass::saved(static function () use ($documentScopes): void {
                ApmPageCache::bust($documentScopes);
            });
            $modelClass::deleted(static function () use ($documentScopes): void {
                ApmPageCache::bust($documentScopes);
            });
        }

        foreach ([WeeklyBriefingReport::class, WeeklyBriefingSetting::class, WeeklyBriefingContributor::class] as $modelClass) {
            $modelClass::saved(static function (): void {
                ApmPageCache::bust('weekly_briefing');
            });
            $modelClass::deleted(static function (): void {
                ApmPageCache::bust('weekly_briefing');
            });
        }

        foreach ([Division::class, Staff::class] as $modelClass) {
            $modelClass::saved(static function (): void {
                ApmPageCache::bust('lookups');
            });
            $modelClass::deleted(static function (): void {
                ApmPageCache::bust('lookups');
            });
        }
    }
}
