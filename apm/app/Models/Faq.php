<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Faq extends Model
{
    use HasFactory;

    /** Placeholder in answer HTML replaced with staff portal base URL at display time */
    public const PLACEHOLDER_STAFF_PORTAL_URL = '{{staff_portal_url}}';

    protected $fillable = [
        'faq_category_id',
        'question',
        'answer',
        'sort_order',
        'search_keywords',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function category()
    {
        return $this->belongsTo(FaqCategory::class, 'faq_category_id');
    }

    /** Get answer with {{staff_portal_url}} replaced by configured staff portal base URL */
    public function getResolvedAnswerAttribute(): string
    {
        $url = rtrim(config('app.staff_portal_url', config('app.url')), '/');
        return str_replace(self::PLACEHOLDER_STAFF_PORTAL_URL, $url, $this->answer);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('id');
    }
}
