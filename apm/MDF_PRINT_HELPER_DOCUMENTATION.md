# MDF Print Helper Function Documentation

## Overview
The `mdf_print()` helper function provides a comprehensive and flexible way to generate PDFs using DOMPDF with configurable options including watermarks, page headers/footers, custom CSS, margins, and more.

## Basic Usage

```php
// Simple usage with defaults
$pdf = mdf_print('view.name', ['data' => 'value']);

// Advanced usage with custom options
$pdf = mdf_print('view.name', $data, [
    'title' => 'Custom Title',
    'orientation' => 'landscape',
    'watermark' => 'CONFIDENTIAL',
    'page_footer' => 'Page {PAGENO} of {nbpg}'
]);

// Download the PDF
return $pdf->download('filename.pdf');

// Stream the PDF
return $pdf->stream('filename.pdf');

// Get PDF as string
$pdfString = $pdf->output();
```

## Available Options

### Basic Options

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `orientation` | string | `'portrait'` | PDF orientation: `'portrait'` or `'landscape'` |
| `paper` | string | `'A4'` | Paper size: `'A4'`, `'A3'`, `'Letter'`, `'Legal'`, etc. |
| `title` | string | `'Document'` | Document title (metadata) |
| `filename` | string | `null` | Default filename for download |

### Watermark Options

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `watermark` | string | `null` | Watermark text to display |
| `watermark_opacity` | float | `0.3` | Watermark opacity (0.0 to 1.0) |
| `watermark_position` | string | `'center'` | Watermark position |
| `watermark_size` | string | `'large'` | Watermark size |

#### Watermark Positions
- `'center'` - Center of page
- `'top-left'` - Top left corner
- `'top-right'` - Top right corner
- `'bottom-left'` - Bottom left corner
- `'bottom-right'` - Bottom right corner
- `'top-center'` - Top center
- `'bottom-center'` - Bottom center

#### Watermark Sizes
- `'small'` - 24px font
- `'medium'` - 36px font
- `'large'` - 48px font
- `'xlarge'` - 72px font

### Page Layout Options

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `page_header` | string | `null` | Text to display at top of each page |
| `page_footer` | string | `null` | Text to display at bottom of each page |
| `margins` | array | See below | Page margins |
| `header_margin` | string | `'1cm'` | Margin from top for header |
| `footer_margin` | string | `'1cm'` | Margin from bottom for footer |

#### Default Margins
```php
'margins' => [
    'top' => '2cm',
    'right' => '2cm',
    'bottom' => '2cm',
    'left' => '2cm'
]
```

### Advanced Options

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `custom_css` | string | `null` | Additional CSS to inject |
| `download` | bool | `true` | Whether to allow download |
| `stream` | bool | `false` | Whether to stream the PDF |
| `inline` | bool | `false` | Whether to display inline |
| `encrypt` | bool | `false` | Whether to encrypt the PDF |
| `password` | string | `null` | Password for encrypted PDFs |
| `permissions` | array | See below | PDF permissions |

#### Default Permissions
```php
'permissions' => [
    'print', 'copy', 'modify', 'annot-forms', 
    'fill-forms', 'extract', 'assemble'
]
```

## Usage Examples

### 1. Basic Activity Memo
```php
$pdf = mdf_print('activities.memo-pdf', $data, [
    'title' => 'Activity Memo - ' . $activity->title,
    'watermark' => 'AU/CDC',
    'page_footer' => 'Generated on ' . now()->format('d/m/Y H:i:s')
]);
```

### 2. Confidential Document with Watermark
```php
$pdf = mdf_print('documents.confidential', $data, [
    'title' => 'Confidential Report',
    'watermark' => 'CONFIDENTIAL',
    'watermark_opacity' => 0.5,
    'watermark_position' => 'center',
    'watermark_size' => 'xlarge',
    'page_header' => 'CONFIDENTIAL - Internal Use Only',
    'page_footer' => 'Page {PAGENO} | ' . now()->format('d/m/Y')
]);
```

### 3. Landscape Report with Custom Margins
```php
$pdf = mdf_print('reports.landscape', $data, [
    'orientation' => 'landscape',
    'paper' => 'A3',
    'margins' => [
        'top' => '1.5cm',
        'right' => '1.5cm',
        'bottom' => '1.5cm',
        'left' => '1.5cm'
    ],
    'custom_css' => '
        .report-table { font-size: 10px; }
        .highlight { background-color: #ffff00; }
    '
]);
```

### 4. Encrypted Document
```php
$pdf = mdf_print('documents.secure', $data, [
    'title' => 'Secure Document',
    'encrypt' => true,
    'password' => 'secret123',
    'permissions' => ['print', 'copy'], // Restrict permissions
    'watermark' => 'SECURE',
    'page_footer' => 'Protected Document'
]);
```

### 5. Invoice with Company Branding
```php
$pdf = mdf_print('invoices.standard', $data, [
    'title' => 'Invoice #' . $invoice->number,
    'page_header' => 'AU/CDC - Official Invoice',
    'page_footer' => 'Thank you for your business | Page {PAGENO}',
    'watermark' => 'PAID',
    'watermark_opacity' => 0.1,
    'watermark_position' => 'bottom-right',
    'custom_css' => '
        .company-logo { max-width: 200px; }
        .invoice-total { font-weight: bold; color: #007bff; }
    '
]);
```

## PDF Output Methods

### Download
```php
return $pdf->download('filename.pdf');
```

### Stream (Display in Browser)
```php
return $pdf->stream('filename.pdf');
```

### Inline (Embed in HTML)
```php
return $pdf->inline('filename.pdf');
```

### Get as String
```php
$pdfString = $pdf->output();
```

### Save to File
```php
$pdf->save(storage_path('app/public/filename.pdf'));
```

## Helper Functions

The `mdf_print()` function uses several internal helper functions:

### `add_watermark_to_html()`
Adds watermark text to HTML content with configurable position, size, and opacity.

### `add_custom_css_to_html()`
Injects custom CSS into the HTML document.

### `add_page_headers_footers()`
Adds page headers and footers to the document.

## Best Practices

### 1. Watermark Usage
- Use low opacity (0.1-0.3) for subtle watermarks
- Position watermarks to avoid interfering with content
- Choose appropriate size based on document type

### 2. Page Headers/Footers
- Keep headers/footers concise
- Use consistent formatting across documents
- Include page numbers when appropriate

### 3. Margins
- Ensure margins are sufficient for printing
- Consider binding requirements for multi-page documents
- Test with different paper sizes

### 4. Custom CSS
- Use specific selectors to avoid conflicts
- Test CSS compatibility with DOMPDF
- Keep styles simple and focused

## Error Handling

```php
try {
    $pdf = mdf_print('view.name', $data, $options);
    return $pdf->download('document.pdf');
} catch (\Exception $e) {
    Log::error('PDF generation failed: ' . $e->getMessage());
    return back()->with('error', 'Failed to generate PDF');
}
```

## Performance Considerations

- Large documents may take time to generate
- Consider caching generated PDFs for frequently accessed documents
- Monitor memory usage for complex documents
- Use appropriate paper sizes to minimize processing time

## Troubleshooting

### Common Issues

1. **Watermark not visible**: Check opacity and z-index
2. **CSS not applied**: Verify CSS syntax and DOMPDF compatibility
3. **Margins not working**: Ensure margin values are valid CSS units
4. **Large file sizes**: Optimize images and reduce CSS complexity

### Debug Mode
```php
// Enable debug mode for troubleshooting
$pdf = mdf_print('view.name', $data, [
    'custom_css' => '
        * { border: 1px solid red !important; }
        .debug { background: yellow !important; }
    '
]);
```

## Integration Examples

### In Controllers
```php
public function generateReport(Request $request)
{
    $data = $this->getReportData($request);
    
    $pdf = mdf_print('reports.custom', $data, [
        'title' => 'Custom Report',
        'watermark' => 'DRAFT',
        'page_footer' => 'Generated by ' . auth()->user()->name
    ]);
    
    return $pdf->download('report.pdf');
}
```

### In Blade Views
```php
@php
    $pdf = mdf_print('documents.template', $data, [
        'watermark' => 'SAMPLE',
        'page_header' => 'Sample Document'
    ]);
@endphp

<a href="{{ route('download.pdf') }}" class="btn btn-primary">
    Download PDF
</a>
```

## Future Enhancements

- Support for image watermarks
- Advanced page numbering options
- Template system for common document types
- Batch PDF generation
- PDF merging capabilities
- Digital signature support
