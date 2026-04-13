<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MemoTypeDefinition extends Model
{
    public const SIGNATURE_STYLES = [
        'top_right' => 'Top right',
        'bottom_left' => 'Bottom left',
        'top_left' => 'Top left',
        'bottom_right' => 'Bottom right',
    ];

    public const FIELD_TYPES = [
        'text' => 'Text',
        'textarea' => 'Textarea',
        'text_summernote' => 'Rich text (Summernote)',
        'number' => 'Number',
        'date' => 'Date',
        'email' => 'Email',
    ];

    protected $fillable = [
        'slug',
        'name',
        'description',
        'ref_prefix',
        'is_division_specific',
        'attachments_enabled',
        'signature_style',
        'fields_schema',
        'is_system',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'fields_schema' => 'array',
            'is_system' => 'boolean',
            'is_active' => 'boolean',
            'is_division_specific' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>|null  $schema
     * @return array<int, array<string, mixed>>
     */
    public static function normalizeFieldsSchemaRows(?array $schema): array
    {
        if (! is_array($schema)) {
            return [];
        }

        return array_values(array_map(function (array $row) {
            if (! array_key_exists('enabled', $row)) {
                $row['enabled'] = true;
            } else {
                $row['enabled'] = filter_var($row['enabled'], FILTER_VALIDATE_BOOLEAN);
            }

            return $row;
        }, $schema));
    }

    public function getSignatureStyleLabelAttribute(): string
    {
        return self::SIGNATURE_STYLES[$this->signature_style] ?? $this->signature_style;
    }

    /**
     * @return array<int, array{field: string, display: string, field_type: string, required?: bool}>
     */
    public static function defaultFieldsSchema(): array
    {
        return [
            ['field' => 'title', 'display' => 'Title', 'field_type' => 'text', 'required' => true, 'enabled' => true],
            ['field' => 'reference', 'display' => 'Reference', 'field_type' => 'text', 'required' => false, 'enabled' => true],
            ['field' => 'body', 'display' => 'Body / background', 'field_type' => 'text_summernote', 'required' => true, 'enabled' => true],
        ];
    }

    public function toApiArray(): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'name' => $this->name,
            'description' => $this->description ?? null,
            'ref_prefix' => $this->ref_prefix,
            'is_division_specific' => (bool) $this->is_division_specific,
            'attachments_enabled' => (bool) $this->attachments_enabled,
            'signature_style' => $this->signature_style,
            'signature_style_label' => $this->signature_style_label,
            'fields_schema' => self::normalizeFieldsSchemaRows($this->fields_schema ?? []),
            'is_system' => (bool) $this->is_system,
            'sort_order' => (int) $this->sort_order,
            'is_active' => (bool) $this->is_active,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
