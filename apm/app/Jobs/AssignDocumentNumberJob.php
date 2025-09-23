<?php

namespace App\Jobs;

use App\Services\DocumentNumberService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

class AssignDocumentNumberJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $model;
    protected $modelId;
    protected $modelType;
    protected $documentType;

    /**
     * Create a new job instance.
     */
    public function __construct(Model $model, string $documentType = null)
    {
        $this->model = $model;
        $this->modelId = $model->id;
        $this->modelType = get_class($model);
        $this->documentType = $documentType ?? DocumentNumberService::getDocumentTypeFromModel($model);
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            // Reload the model to get fresh data
            $model = $this->modelType::find($this->modelId);
            
            if (!$model) {
                Log::warning("Document number assignment failed: Model not found", [
                    'model_type' => $this->modelType,
                    'model_id' => $this->modelId
                ]);
                return;
            }

            // Check if document number already exists
            if ($model->document_number) {
                Log::info("Document number already assigned", [
                    'model_type' => $this->modelType,
                    'model_id' => $this->modelId,
                    'document_number' => $model->document_number
                ]);
                return;
            }

            // Generate document number with conflict resolution
            $documentNumber = DocumentNumberService::generateForModel($model, $this->documentType);
            
            // Update the model with the document number
            $model->update(['document_number' => $documentNumber]);
            
            Log::info("Document number assigned successfully", [
                'model_type' => $this->modelType,
                'model_id' => $this->modelId,
                'document_number' => $documentNumber,
                'document_type' => $this->documentType
            ]);
            
        } catch (\Illuminate\Database\QueryException $e) {
            // Handle unique constraint violations
            if ($e->getCode() == 23000) { // MySQL duplicate entry error
                Log::warning("Document number conflict detected, retrying with next available number", [
                    'model_type' => $this->modelType,
                    'model_id' => $this->modelId,
                    'error' => $e->getMessage()
                ]);
                
                try {
                    // Try to find next available number
                    $division = $model->division ?? $model->division_id;
                    $documentNumber = DocumentNumberService::findNextAvailableNumber(
                        $this->documentType,
                        is_object($division) ? $division->division_short_name : 'UNKNOWN'
                    );
                    
                    $model->update(['document_number' => $documentNumber]);
                    
                    Log::info("Document number assigned after conflict resolution", [
                        'model_type' => $this->modelType,
                        'model_id' => $this->modelId,
                        'document_number' => $documentNumber
                    ]);
                    
                } catch (\Exception $retryException) {
                    Log::error("Document number assignment failed after conflict resolution", [
                        'model_type' => $this->modelType,
                        'model_id' => $this->modelId,
                        'original_error' => $e->getMessage(),
                        'retry_error' => $retryException->getMessage()
                    ]);
                    
                    // Re-throw the original exception
                    throw $e;
                }
            } else {
                // Re-throw non-unique constraint errors
                throw $e;
            }
            
        } catch (\Exception $e) {
            Log::error("Document number assignment failed", [
                'model_type' => $this->modelType,
                'model_id' => $this->modelId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            // Re-throw to mark job as failed
            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error("Document number assignment job failed permanently", [
            'model_type' => $this->modelType,
            'model_id' => $this->modelId,
            'document_type' => $this->documentType,
            'error' => $exception->getMessage()
        ]);
    }
}