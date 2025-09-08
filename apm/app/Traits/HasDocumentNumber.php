<?php

namespace App\Traits;

use App\Jobs\AssignDocumentNumberJob;
use App\Services\DocumentNumberService;

trait HasDocumentNumber
{
    /**
     * Boot the trait
     */
    protected static function bootHasDocumentNumber()
    {
        // Assign document number after model is created
        static::created(function ($model) {
            if (empty($model->document_number)) {
                // Dispatch job to assign document number asynchronously
                AssignDocumentNumberJob::dispatch($model);
            }
        });
    }

    /**
     * Get the document type for this model
     */
    public function getDocumentType(): string
    {
        return DocumentNumberService::getDocumentTypeFromModel($this);
    }

    /**
     * Generate document number for this model
     */
    public function generateDocumentNumber(): string
    {
        return DocumentNumberService::generateForAnyModel($this);
    }

    /**
     * Get next document number preview
     */
    public function getNextDocumentNumberPreview(): string
    {
        // Load division if not already loaded
        if (!$this->relationLoaded('division') && $this->division_id) {
            $this->load('division');
        }
        
        $division = $this->division ?? $this->division_id;
        return DocumentNumberService::getNextNumberPreview(
            $this->getDocumentType(),
            $division
        );
    }

    /**
     * Manually assign document number
     */
    public function assignDocumentNumber(): void
    {
        if (empty($this->document_number)) {
            $documentNumber = $this->generateDocumentNumber();
            $this->update(['document_number' => $documentNumber]);
        }
    }

    /**
     * Check if document number is valid
     */
    public function hasValidDocumentNumber(): bool
    {
        return !empty($this->document_number) && 
               DocumentNumberService::validateDocumentNumber($this->document_number);
    }

    /**
     * Get document number components
     */
    public function getDocumentNumberComponents(): array
    {
        if (!$this->hasValidDocumentNumber()) {
            return [];
        }

        return DocumentNumberService::parseDocumentNumber($this->document_number);
    }
}
