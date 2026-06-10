<?php

namespace App\Support;

use App\Models\Activity;
use App\Models\ChangeRequest;
use App\Models\NonTravelMemo;
use App\Models\OtherMemo;
use App\Models\RequestARF;
use App\Models\ServiceRequest;
use App\Models\SpecialMemo;
use Illuminate\Database\Eloquent\Model;

/**
 * Build public signature-verify URLs and resolve documents by type slug + id.
 */
class SignatureVerifyUrl
{
    /** @var array<string, class-string<Model>> */
    public const TYPE_MODEL_MAP = [
        'activity' => Activity::class,
        'special_memo' => SpecialMemo::class,
        'non_travel_memo' => NonTravelMemo::class,
        'change_request' => ChangeRequest::class,
        'service_request' => ServiceRequest::class,
        'arf' => RequestARF::class,
        'other_memo' => OtherMemo::class,
    ];

    public static function typeSlugForModel(Model $model): ?string
    {
        $class = $model::class;

        foreach (self::TYPE_MODEL_MAP as $slug => $modelClass) {
            if ($class === $modelClass || is_subclass_of($model, $modelClass)) {
                return $slug;
            }
        }

        return null;
    }

    public static function forModel(Model $model): ?string
    {
        $type = self::typeSlugForModel($model);
        if (! $type || empty($model->id)) {
            return null;
        }

        return route('signature-verify.document', [
            'type' => $type,
            'id' => (int) $model->id,
        ], true);
    }

    public static function resolve(string $type, int $id): ?Model
    {
        $type = strtolower(trim($type));
        $modelClass = self::TYPE_MODEL_MAP[$type] ?? null;
        if (! $modelClass || $id <= 0) {
            return null;
        }

        return $modelClass::query()->find($id);
    }

    /** @return array<int, string> */
    public static function allowedTypeSlugs(): array
    {
        return array_keys(self::TYPE_MODEL_MAP);
    }
}
