# Document Numbering System

## Overview

This system provides automatic generation of unique document numbers for all document types in the application. The format follows the pattern:

```
AU/CDC/{DIVISION_SHORT_NAME}/IM/{DOCUMENT_TYPE}/{COUNTER}
```

**Important**: The `{DIVISION_SHORT_NAME}` comes from the `division_short_name` field in the `divisions` table. Ensure all divisions have this field populated for proper document numbering.

## Document Types

| Type | Code | Description |
|------|------|-------------|
| Quarterly Matrix | QM | Matrix activities |
| Non Travel Memo | NT | Non-travel memos |
| Special Memo | SPM | Special memos |
| Single Memo | SM | Single memos (activities) |
| Change Request | CR | Change requests |
| Service Request | SR | Service requests |
| ARF | ARF | Advance Request Forms |

## Features

### ✅ **Division-Specific Counters**
- Each division has its own counter sequence
- Counters start at 001 for each division
- No conflicts between divisions

### ✅ **Year-Based Reset**
- Counters reset to 001 at the beginning of each year
- Automatic year detection (current year by default)

### ✅ **Race Condition Prevention**
- Database-level locking prevents duplicate numbers
- Atomic counter increments
- Job-based assignment for high concurrency

### ✅ **Automatic Assignment**
- Document numbers assigned automatically when models are created
- Background job processing prevents blocking
- Fallback to immediate assignment if needed

### ✅ **Conflict Resolution**
- Automatic retry logic when document number conflicts occur
- Unique constraint violation handling in background jobs
- Gap prevention after activity deletions
- Command-line tools for fixing existing conflicts

## Usage

### Automatic Assignment (Recommended)

Models with the `HasDocumentNumber` trait automatically get document numbers:

```php
// Create a new matrix - document number assigned automatically
$matrix = Matrix::create([
    'division_id' => 1,
    'year' => 2024,
    'quarter' => 'Q1',
    // ... other fields
]);

// Document number will be: AU/CDC/CR/IM/QM/001
echo $matrix->document_number;
```

### Manual Assignment

```php
use App\Services\DocumentNumberService;

// Generate for specific document type
$number = DocumentNumberService::generateDocumentNumber('QM', 'CR', 1);

// Generate for any model
$number = DocumentNumberService::generateForAnyModel($matrix);

// Get preview without incrementing counter
$preview = DocumentNumberService::getNextNumberPreview('QM', $division);
```

### Helper Functions

```php
// Generate document number
$number = generateDocumentNumber($model);

// Assign document number via job
assignDocumentNumber($model);

// Get next number preview
$preview = getNextDocumentNumberPreview('QM', $division);
```

## Model Integration

### Adding to New Models

1. **Add the trait:**
```php
use App\Traits\HasDocumentNumber;

class YourModel extends Model
{
    use HasDocumentNumber;
}
```

2. **Add to fillable:**
```php
protected $fillable = [
    // ... other fields
    'document_number',
];
```

3. **Add database column:**
```php
$table->string('document_number', 50)->nullable()->unique();
```

### Document Type Mapping

The system automatically detects document types based on model class:

```php
// In DocumentNumberService
return match ($className) {
    'Matrix' => null, // No document number - just a container
    'NonTravelMemo' => DocumentCounter::TYPE_NON_TRAVEL_MEMO,
    'SpecialMemo' => DocumentCounter::TYPE_SPECIAL_MEMO,
    'Activity' => self::getActivityDocumentType($model), // QM or SM based on Matrix status
    'ServiceRequest' => DocumentCounter::TYPE_SERVICE_REQUEST,
    'RequestARF' => DocumentCounter::TYPE_ARF,
    default => 'UNKNOWN'
};
```

### Activity Document Type Logic

Activities get different document types based on their `is_single_memo` field:

```php
private static function getActivityDocumentType(Model $activity): string
{
    // Check if activity is marked as single memo
    if (isset($activity->is_single_memo) && $activity->is_single_memo == 1) {
        return DocumentCounter::TYPE_SINGLE_MEMO; // SM
    }
    
    // Activities not marked as single memo are part of quarterly matrix
    return DocumentCounter::TYPE_QUARTERLY_MATRIX; // QM
}
```

**Business Logic:**
- **`is_single_memo = 1`** → Single Memo (`SM`)
- **`is_single_memo = 0`** → Quarterly Matrix (`QM`)

This approach is more reliable as it directly uses the database field that indicates whether an activity should be treated as a single memo or part of a quarterly matrix.

## Database Schema

### Document Counters Table

```sql
CREATE TABLE document_counters (
    id BIGINT PRIMARY KEY,
    division_short_name VARCHAR(10),
    year INT,
    document_type VARCHAR(10),
    counter INT DEFAULT 0,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    
    UNIQUE KEY unique_division_year_type (division_short_name, year, document_type),
    INDEX idx_division_year (division_short_name, year),
    INDEX idx_document_type (document_type)
);
```

### Model Tables

All relevant tables now have a `document_number` column:

```sql
ALTER TABLE matrices ADD COLUMN document_number VARCHAR(50) UNIQUE;
ALTER TABLE activities ADD COLUMN document_number VARCHAR(50) UNIQUE;
ALTER TABLE non_travel_memos ADD COLUMN document_number VARCHAR(50) UNIQUE;
ALTER TABLE special_memos ADD COLUMN document_number VARCHAR(50) UNIQUE;
ALTER TABLE service_requests ADD COLUMN document_number VARCHAR(50) UNIQUE;
ALTER TABLE request_arfs ADD COLUMN document_number VARCHAR(50) UNIQUE;
```

## Commands

### Test Document Number Generation

```bash
# Test with default division
php artisan test:document-numbers

# Test with specific division
php artisan test:document-numbers --division=1
```

### Check Division Short Names

```bash
# Check which divisions have short names
php artisan check:division-short-names
```

### Assign Numbers to Existing Records

```bash
# Preview what would be assigned
php artisan assign:document-numbers --dry-run

# Assign numbers to existing records
php artisan assign:document-numbers
```

### Reset Counters for New Year

```bash
# Reset all counters for new year (run on January 1st)
php artisan tinker
>>> App\Models\DocumentCounter::resetCountersForNewYear(2025);
```

### Fix Document Number Conflicts

```bash
# Check for conflicts without making changes (dry run)
php artisan fix:document-conflicts --dry-run

# Fix all document number conflicts
php artisan fix:document-conflicts

# Fix conflicts for specific division only
php artisan fix:document-conflicts --division=1

# Reset counters to next available numbers after deletions
php artisan fix:document-conflicts --reset-counters

# Fix conflicts and reset counters in one command
php artisan fix:document-conflicts --reset-counters
```

## Conflict Resolution System

### How Conflicts Occur

Document number conflicts can happen when:

1. **Activity Deletion**: An activity with document number `AU/CDC/CR/IM/SM/001` is deleted, but the counter remains at 001
2. **Duplicate Generation**: A new activity gets the same document number `AU/CDC/CR/IM/SM/001`
3. **Unique Constraint Violation**: The database rejects the duplicate, causing the job to fail

### Automatic Resolution

The system now includes robust conflict resolution:

#### **DocumentNumberService Enhancements**
- **Retry Logic**: Up to 10 attempts to generate unique document numbers
- **Uniqueness Check**: Verifies document numbers across all tables before assignment
- **Next Available**: Finds the next available number when conflicts occur

#### **AssignDocumentNumberJob Improvements**
- **Constraint Handling**: Catches MySQL duplicate entry errors (code 23000)
- **Automatic Retry**: Finds next available number when conflicts detected
- **Enhanced Logging**: Tracks conflict resolution attempts

#### **Counter Reset Functionality**
- **Gap Prevention**: Resets counters to prevent gaps after deletions
- **Smart Detection**: Finds highest used document number and resets accordingly

### Manual Conflict Resolution

Use the `fix:document-conflicts` command to resolve existing conflicts:

```bash
# Dry run to see what would be fixed
php artisan fix:document-conflicts --dry-run

# Fix all conflicts
php artisan fix:document-conflicts

# Fix for specific division
php artisan fix:document-conflicts --division=1

# Reset counters after deletions
php artisan fix:document-conflicts --reset-counters
```

### Conflict Resolution Process

1. **Detection**: Scans all tables for duplicate document numbers
2. **Analysis**: Identifies which records have conflicts
3. **Resolution**: Keeps first record, assigns new numbers to duplicates
4. **Verification**: Ensures all document numbers are unique
5. **Logging**: Records all changes for audit trail

## API Methods

### DocumentCounter Model

```php
// Get next counter (atomic operation)
$counter = DocumentCounter::getNextCounter('CR', 'QM', 2024);

// Get division statistics
$stats = DocumentCounter::getDivisionStats('CR', 2024);

// Reset counters for new year
DocumentCounter::resetCountersForNewYear(2025);

// Get all document types
$types = DocumentCounter::getDocumentTypes();
```

### DocumentNumberService

```php
// Generate document number
$number = DocumentNumberService::generateDocumentNumber('QM', 'CR', 1, 2024);

// Generate for model
$number = DocumentNumberService::generateForModel($matrix, 'QM');

// Generate for any model
$number = DocumentNumberService::generateForAnyModel($matrix);

// Get document type from model
$type = DocumentNumberService::getDocumentTypeFromModel($matrix);

// Validate document number
$isValid = DocumentNumberService::validateDocumentNumber($number);

// Parse document number
$components = DocumentNumberService::parseDocumentNumber($number);

// Get preview
$preview = DocumentNumberService::getNextNumberPreview('QM', $division);

// Find next available number (conflict resolution)
$nextNumber = DocumentNumberService::findNextAvailableNumber('SM', 'CR');

// Reset counter after deletions
DocumentNumberService::resetCounterAfterDeletion('SM', 'CR', 2024);
```

## Examples

### Example Document Numbers

```
AU/CDC/CR/IM/QM/001    # Central RCC, Quarterly Matrix, #1
AU/CDC/CR/IM/NT/001    # Central RCC, Non Travel Memo, #1
AU/CDC/DHI/IM/SR/001   # Data Hub, Service Request, #1
AU/CDC/EPI/IM/ARF/001  # Epidemiology, ARF, #1
```

### Usage in Controllers

```php
public function store(Request $request)
{
    $matrix = Matrix::create($request->validated());
    
    // Document number assigned automatically
    return response()->json([
        'matrix' => $matrix,
        'document_number' => $matrix->document_number
    ]);
}
```

### Usage in Views

```blade
@if($matrix->document_number)
    <div class="document-number">
        <strong>Document Number:</strong> {{ $matrix->document_number }}
    </div>
@endif
```

## Error Handling

### Common Issues

1. **Missing Division Short Name**
   - System falls back to 'UNKNOWN'
   - Ensure divisions have `division_short_name` set

2. **Counter Collision**
   - Database locking prevents this
   - If it occurs, check for race conditions

3. **Invalid Document Type**
   - System returns 'UNKNOWN'
   - Add mapping in `DocumentNumberService`

### Logging

All document number operations are logged:

```php
// Check logs
tail -f storage/logs/laravel.log | grep "Document number"
```

## Performance Considerations

### Indexes

The system includes optimized indexes for:
- Division/year lookups
- Document type filtering
- Unique constraints

### Caching

Consider caching division short names for better performance:

```php
// Cache division short names
$divisionShortName = Cache::remember("division_short_name_{$divisionId}", 3600, function() use ($divisionId) {
    return Division::find($divisionId)->division_short_name;
});
```

### Queue Processing

Document number assignment uses Laravel queues:

```bash
# Process jobs
php artisan queue:work

# Monitor queue
php artisan queue:monitor
```

## Migration Guide

### From Old System

1. **Run migrations:**
```bash
php artisan migrate
```

2. **Assign numbers to existing records:**
```bash
php artisan assign:document-numbers
```

3. **Update views to show document numbers:**
```blade
{{ $model->document_number ?? 'Pending...' }}
```

### Testing

```bash
# Test the system
php artisan test:document-numbers

# Test with specific division
php artisan test:document-numbers --division=1
```

## Division Short Name Requirements

### Prerequisites

Before using the document numbering system, ensure all divisions have `division_short_name` populated:

```bash
# Check division short names
php artisan check:division-short-names

# Generate missing short names (if using CodeIgniter system)
php artisan settings:force_generate_short_names
```

### Division Short Name Format

- Should be 2-10 characters
- Uppercase letters and numbers only
- No spaces or special characters
- Examples: `CR`, `DHI`, `ODDG`, `P&GM-D`

### Database Structure

The system reads from the `divisions` table:

```sql
SELECT id, division_name, division_short_name 
FROM divisions 
WHERE division_short_name IS NOT NULL;
```

## Troubleshooting

### Document Numbers Not Generated

1. Check if model uses `HasDocumentNumber` trait
2. Verify `document_number` is in fillable array
3. Check queue is processing jobs
4. Review logs for errors
5. **Verify division has `division_short_name` set**

### Duplicate Numbers

1. Check for race conditions
2. Verify database locking is working
3. Check for manual counter manipulation

### Invalid Format

1. **Verify division has `division_short_name`** (most common issue)
2. Check document type mapping
3. Validate using `DocumentNumberService::validateDocumentNumber()`

### Division Short Name Issues

```bash
# Check which divisions need short names
php artisan check:division-short-names

# If divisions are missing short names, generate them
php artisan settings:force_generate_short_names
```

## Support

For issues or questions:
1. Check the logs first
2. Run the test command
3. Verify database constraints
4. Review the code documentation
