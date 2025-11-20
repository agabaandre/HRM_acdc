<?php
/**
 * Home Helper Functions
 * 
 * This helper file contains all the functions needed for the home page dashboard.
 * It provides functions to get pending action counts, recent activities, and other
 * data needed to display the home page widgets.
 */

use App\Models\ActivityApprovalTrail;
use App\Models\ApprovalTrail;
use App\Models\Matrix;
use App\Models\NonTravelMemo;
use App\Models\SpecialMemo;
use App\Models\ServiceRequest;
use App\Models\RequestARF;
use App\Models\Division;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;



if (!function_exists('user_info')) {
   function user_info() {
        // Get user as array from session, fallback to empty array
        $user = session('user', []);
        // Convert to object for property access
        $user = (object) $user;

        $firstName = $user->fname ?? '';
        $lastName = $user->lname ?? '';
        $otherName = $user->other_name ?? '';

        // Define a set of professional color classes
        $avatarColors = [
            'bg-primary', 'bg-success', 'bg-info', 'bg-warning', 'bg-danger', 'bg-secondary'
        ];
        // Pick color based on first letter of first name
        $firstLetter = strtoupper((!empty($firstName) ? $firstName[0] : 'A'));
        $colorIndex = (ord($firstLetter) - 65) % count($avatarColors);
        if ($colorIndex < 0) $colorIndex = 0;
        $avatarColor = $avatarColors[$colorIndex];

        $photo = $user->photo ?? '';
        $photoData = $user->photo_data ?? null; // Base64 encoded photo from API
        $baseUrl = $user->base_url ?? '';
        
        // Generate initials for fallback avatar
        $initials = '';
        if ($firstName && $lastName) {
            $initials = strtoupper(substr($firstName, 0, 1) . substr($lastName, 0, 1));
        } elseif ($firstName) {
            $initials = strtoupper(substr($firstName, 0, 2));
        } else {
            $initials = 'U';
        }
        
        // Generate color for avatar (matching approver dashboard logic)
        $colors = ['#119a48', '#1bb85a', '#0d7a3a', '#9f2240', '#c44569', '#2c3e50'];
        $colorIndex = (ord(strtoupper($firstName[0] ?? 'A')) - 65) % count($colors);
        if ($colorIndex < 0) $colorIndex = 0;
        $bgColor = $colors[$colorIndex];
        
        $hasPhoto = false;
        $imageSrc = '';
        
        // First, try to use photo_data (base64) from session if available
        if (!empty($photoData)) {
            $hasPhoto = true;
            // Determine image type from base64 data or default to jpeg
            $imageType = 'jpeg';
            if (strpos($photoData, 'data:image/') === 0) {
                // If it already has data URI prefix, use it directly
                $imageSrc = $photoData;
            } else {
                // Otherwise, add data URI prefix
                // Try to detect image type from base64 data
                $decoded = @base64_decode($photoData, true);
                if ($decoded !== false) {
                    $finfo = @finfo_open(FILEINFO_MIME_TYPE);
                    if ($finfo) {
                        $mimeType = @finfo_buffer($finfo, $decoded);
                        if ($mimeType && strpos($mimeType, 'image/') === 0) {
                            $imageType = str_replace('image/', '', $mimeType);
                        }
                        @finfo_close($finfo);
                    }
                }
                $imageSrc = 'data:image/' . $imageType . ';base64,' . $photoData;
            }
        } elseif (!empty($photo) && $photo !== null && trim($photo) !== '') {
            // Try to show photo from filesystem (let browser handle if file doesn't exist)
            $hasPhoto = true;
            $cleanBaseUrl = rtrim($baseUrl, '/');
            $imageSrc = htmlspecialchars($cleanBaseUrl . '/uploads/staff/' . $photo);
        }

        ob_start();
        if ($hasPhoto) {
            // Show photo with fallback avatar hidden by default
            ?>
            <div style="position: relative; width: 40px; height: 40px; flex-shrink: 0;">
                <img src="<?php echo $imageSrc; ?>"
                    class="user-img rounded-circle" 
                    style="width: 40px; height: 40px; object-fit: cover; position: absolute; top: 0; left: 0; z-index: 2; display: block;" 
                    alt="user avatar"
                    onerror="this.style.display='none'; var next = this.nextElementSibling; if(next) { next.style.display='flex'; next.style.zIndex='1'; }"
                    onload="var next = this.nextElementSibling; if(next) { next.style.display='none'; }">
                <div class="rounded-circle d-flex align-items-center justify-content-center text-white" 
                    style="display: none; width: 35px; height: 35px; background-color: <?php echo $bgColor; ?>; font-weight: 600; font-size: 14px; position: absolute; top: 0; left: 0; z-index: 1;">
                    <?php echo $initials; ?>
                </div>
            </div>
            <?php
        } else {
            // Show initials avatar only
            ?>
            <div class="rounded-circle d-flex align-items-center justify-content-center text-white" 
                style="width: 35px; height: 35px; background-color: <?php echo $bgColor; ?>; font-weight: 600; font-size: 14px;">
                <?php echo $initials; ?>
            </div>
            <?php
        }
        return ob_get_clean();
    }
}

if (!function_exists('get_staff_pending_action_count')) {
    /**
     * Get the count of pending actions for a specific module for the current staff member
     *
     * @param string $module The module to check (matrices, non-travel, special-memo, service-requests, request-arf)
     * @return int
     */
    function get_staff_pending_action_count(string $module, int $staffId = null): int
    {
        if (!$staffId) {
        $user = session('user', []);
        $staffId = $user['staff_id'] ?? null;
        }
        
        if (!$staffId) {
            return 0;
        }

        switch ($module) {
            case 'matrices':
                return get_pending_matrices_count($staffId);
            case 'non-travel':
                return get_pending_non_travel_memo_count($staffId);
            case 'special-memo':
                return get_pending_special_memo_count($staffId);
            case 'service-requests':
                return get_pending_service_requests_count($staffId);
            case 'request-arf':
                return get_pending_request_arf_count($staffId);
            case 'single-memo':
                return get_pending_single_memo_count($staffId);
            case 'change-request':
                return get_pending_change_request_count($staffId);
            default:
                return 0;
        }
    }
}

if (!function_exists('get_pending_matrices_count')) {
    /**
     * Get count of pending matrices that require action from the current staff member
     *
     * @param int $staffId
     * @return int
     */
    function get_pending_matrices_count(int $staffId): int
    {
        try {
            // Use the PendingApprovalsService for consistency
            $pendingApprovalsService = new \App\Services\PendingApprovalsService([
                'staff_id' => $staffId,
                'division_id' => user_session('division_id'),
                'permissions' => user_session('permissions', []),
                'name' => user_session('name', ''),
                'email' => user_session('email', ''),
                'base_url' => config('app.url')
            ]);
            
            $summaryStats = $pendingApprovalsService->getSummaryStats();
            return $summaryStats['by_category']['Matrix'] ?? 0;
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Error getting pending matrices count: ' . $e->getMessage());
            return 0;
        }
    }
}

if (!function_exists('get_pending_non_travel_memo_count')) {
    /**
     * Get count of pending non-travel memo activities that require action from the current staff member
     *
     * @param int $staffId
     * @return int
     */
    function get_pending_non_travel_memo_count(int $staffId): int
    {
        try {
            // Use the PendingApprovalsService for consistency
            $pendingApprovalsService = new \App\Services\PendingApprovalsService([
                'staff_id' => $staffId,
                'division_id' => user_session('division_id'),
                'permissions' => user_session('permissions', []),
                'name' => user_session('name', ''),
                'email' => user_session('email', ''),
                'base_url' => config('app.url')
            ]);
            
            $summaryStats = $pendingApprovalsService->getSummaryStats();
            return $summaryStats['by_category']['Non-Travel Memo'] ?? 0;
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Error getting pending non-travel memo count: ' . $e->getMessage());
            return 0;
        }
    }
}

if (!function_exists('get_pending_special_memo_count')) {
    /**
     * Get count of pending special memos that require action from the current staff member
     *
     * @param int $staffId
     * @return int
     */
    function get_pending_special_memo_count(int $staffId): int
    {
        try {
            // Use the PendingApprovalsService for consistency
            $pendingApprovalsService = new \App\Services\PendingApprovalsService([
                'staff_id' => $staffId,
                'division_id' => user_session('division_id'),
                'permissions' => user_session('permissions', []),
                'name' => user_session('name', ''),
                'email' => user_session('email', ''),
                'base_url' => config('app.url')
            ]);
            
            $summaryStats = $pendingApprovalsService->getSummaryStats();
            return $summaryStats['by_category']['Special Memo'] ?? 0;
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Error getting pending special memo count: ' . $e->getMessage());
            return 0;
        }
    }
}

if (!function_exists('get_pending_service_requests_count')) {
    /**
     * Get count of pending service requests that require action from the current staff member
     *
     * @param int $staffId
     * @return int
     */
    function get_pending_service_requests_count(int $staffId): int
    {
        try {
            // Use the PendingApprovalsService for consistency
            $pendingApprovalsService = new \App\Services\PendingApprovalsService([
                'staff_id' => $staffId,
                'division_id' => user_session('division_id'),
                'permissions' => user_session('permissions', []),
                'name' => user_session('name', ''),
                'email' => user_session('email', ''),
                'base_url' => config('app.url')
            ]);
            
            $summaryStats = $pendingApprovalsService->getSummaryStats();
            return $summaryStats['by_category']['Service Request'] ?? 0;
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Error getting pending service requests count: ' . $e->getMessage());
            return 0;
        }
    }
}

if (!function_exists('generate_pdf')) {
    /**
     * Generate a PDF using mPDF, or preview the HTML in the browser for debugging.
     * Ensures all CSS from the view is included in the PDF.
     *
     * @param string $view
     * @param array $data
     * @param array $options
     *        - 'preview_html' (bool): If true, output HTML to browser instead of PDF (for debug)
     * @return \Mpdf\Mpdf|string
     */
    function generate_pdf($view, $data = [], $options = [])
    {
        // Set timezone
        date_default_timezone_set("Africa/Nairobi");

        // Load the view with data
        if (strpos($view, '.blade.php') !== false) {
            // If it's a Blade view, render it normally
            $html = view($view, $data)->render();
        } else {
            // If it's a PHP template, include it directly
            $templatePath = resource_path('views/' . str_replace('.', '/', $view) . '.php');
            if (file_exists($templatePath)) {
                // Extract variables from data array for PHP template
                extract($data);

                // Start output buffering before including template
                ob_start();
                include $templatePath;
                $html = ob_get_contents();
                ob_end_clean();
            } else {
                // Fallback to Blade view
                $html = view($view, $data)->render();
            }
        }

        // If preview_html option is set, output HTML directly for debugging
        if (!empty($options['preview_html'])) {
            // Set content type header for HTML if not already sent
            if (!headers_sent()) {
                header('Content-Type: text/html; charset=utf-8');
            }
            echo $html;
            exit;
        }

      // mPDF font configuration with Arial + safe fallback
$defaultConfig      = (new \Mpdf\Config\ConfigVariables())->getDefaults();
$fontDirs           = $defaultConfig['fontDir'];
//dd($fontDirs);
$defaultFontConfig  = (new \Mpdf\Config\FontVariables())->getDefaults();
$fontData           = $defaultFontConfig['fontdata'];
//dd($fontData);
$arialFontDir = public_path('assets/fonts/arial');
//dd($arialFontDir);
$arialFiles = [
    'R'  => $arialFontDir . DIRECTORY_SEPARATOR . 'ARIAL.TTF',
    'B'  => $arialFontDir . DIRECTORY_SEPARATOR . 'ARIALBD.TTF',
    'I'  => $arialFontDir . DIRECTORY_SEPARATOR . 'ARIALI.TTF',
    'BI' => $arialFontDir . DIRECTORY_SEPARATOR . 'ARIALBI.TTF',
];

// Determine if all Arial files exist (Linux is case-sensitive)
$haveArial =
    is_dir($arialFontDir) &&
    file_exists($arialFiles['R']) &&
    file_exists($arialFiles['B']) &&
    file_exists($arialFiles['I']) &&
    file_exists($arialFiles['BI']);

if (!$haveArial) {
    Log::warning('Arial fonts not found or incomplete. Falling back to DejaVuSans.', [
        'dir_exists' => is_dir($arialFontDir),
        'files'      => $arialFiles
    ]);
}

$mpdf = new \Mpdf\Mpdf([
    'mode'     => 'utf-8',
    'format'   => 'A4',
    'tempDir'  => storage_path('app/mpdf_tmp'), // ensure this exists & is writable
    'fontDir'  => $haveArial ? array_merge($fontDirs, [$arialFontDir]) : $fontDirs,
    'fontdata' => $haveArial
        ? $fontData + [
            'arial' => [
                'R'  => 'ARIAL.TTF',
                'B'  => 'ARIALBD.TTF',
                'I'  => 'ARIALI.TTF',
                'BI' => 'ARIALBI.TTF',
            ],
        ]
        : $fontData, // keep defaults if no Arial
    'default_font' => $haveArial ? 'arial' : 'freesans',
    'default_font_size' => 10,
]);

        // Set PDF margins exactly like CodeIgniter
        $mpdf->SetMargins(10, 10, 35);         // left, top, right margins
        $mpdf->SetAutoPageBreak(true, 30); 
        $header = '<div style="width: 100%; text-align: center; padding-bottom: 5px;">
            <div style="width: 100%; padding-bottom: 5px;">
                <div style="width: 100%; padding: 10px 0;">
                    <!-- Top Row: Logo and Tagline -->
                    <div style="display:flex; justify-content: space-between; align-items: center;">
                        <!-- Left: Logo -->
                        <div style="width: 60%; text-align: left; float:left;">
                            <img src="' . asset('assets/images/logo.png') . '" alt="Africa CDC Logo" style="height: 80px;">
                        </div>
                        <!-- Right: Tagline -->
                        <div style="text-align: right; width: 35%; float:right; margin-top:10px;">
                            <span style="font-size: 14px; color: #911C39;">Safeguarding Africa\'s Health</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>';
        $mpdf->SetHTMLHeader($header);    // allow auto page break with 30mm bottom margin for footer

        // Set footer exactly like CodeIgniter
        $footer = ' <table width="100%" style="font-size: 8pt; color: #911C39; border:none; margin-top: 4px; !important">
            <tr>
                <td align="left" style="border: none;">
                    Africa CDC Headquarters, Ring Road, 16/17,<br>
                    Haile Garment Lafto Square, Nifas Silk-Lafto Sub City,<br>
                    P.O Box: 200050 Addis Ababa, Tel: +251(0) 112175100/75200<br>
                    Email: <a href="mailto:registry@africacdc.org" style="color: #911C39;">registry@africacdc.org</a>
                </td>
                <td align="left" style="border: none;">
                    Source: Africa CDC  Central Business Platform<br>
                    Generated on: ' . date('d F, Y h:i A') . '<br>
                    ' . config('app.url') . '
                    <br>By:'.user_session('name').'
                </td>
            </tr>
        </table>'.  '<p style="text-align:right; font-size: 8pt;">Page {PAGENO} of {nbpg}</p>';

        $mpdf->SetHTMLFooter($footer);

        // Write HTML content exactly like CodeIgniter with error handling
        try {
            $mpdf->WriteHTML($html);
        } catch (Exception $e) {
            // If there's an error, try with minimal HTML
            $simpleHtml = '<html><body><p>Error generating PDF. Please try again.</p></body></html>';
            $mpdf->WriteHTML($simpleHtml);
        }

        return $mpdf;
    }
}

// Backward compatibility function
if (!function_exists('mpdf_print')) {
    /**
     * Backward compatibility function for mpdf_print
     * @deprecated Use generate_pdf() instead
     */
    function mpdf_print($view, $data = [], $options = [])
    {
        return generate_pdf($view, $data, $options);
    }
}







if (!function_exists('allow_activity_operations')) {
    /**
     * Check if activity operations are allowed based on environment variable
     *
     * @return bool
     */
    function allow_activity_operations(): bool
    {
        return env('ALLOW_ACTIVITY_OPERATIONS', false);
    }
}


if (!function_exists('get_pending_single_memo_count')) {
    /**
     * Get count of pending single memos that require action from the current staff member
     *
     * @param int $staffId
     * @return int
     */
    function get_pending_single_memo_count(int $staffId): int
    {
        try {
            // Use the PendingApprovalsService for consistency
            $pendingApprovalsService = new \App\Services\PendingApprovalsService([
                'staff_id' => $staffId,
                'division_id' => user_session('division_id'),
                'permissions' => user_session('permissions', []),
                'name' => user_session('name', ''),
                'email' => user_session('email', ''),
                'base_url' => config('app.url')
            ]);
            
            $summaryStats = $pendingApprovalsService->getSummaryStats();
            return $summaryStats['by_category']['Single Memo'] ?? 0;
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Error getting pending single memo count: ' . $e->getMessage());
            return 0;
        }
    }
}

if (!function_exists('get_pending_request_arf_count')) {
    /**
     * Get count of pending ARF requests that require action from the current staff member
     *
     * @param int $staffId
     * @return int
     */
    function get_pending_request_arf_count(int $staffId): int
    {
        try {
            // Use the PendingApprovalsService for consistency
            $pendingApprovalsService = new \App\Services\PendingApprovalsService([
                'staff_id' => $staffId,
                'division_id' => user_session('division_id'),
                'permissions' => user_session('permissions', []),
                'name' => user_session('name', ''),
                'email' => user_session('email', ''),
                'base_url' => config('app.url')
            ]);
            
            $summaryStats = $pendingApprovalsService->getSummaryStats();
            return $summaryStats['by_category']['ARF'] ?? 0;
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Error getting pending ARF count: ' . $e->getMessage());
            return 0;
        }
    }
}

if (!function_exists('get_pending_arf_count')) {
    /**
     * Alias for get_pending_request_arf_count for consistency
     *
     * @param int $staffId
     * @return int
     */
    function get_pending_arf_count(int $staffId): int
    {
        return get_pending_request_arf_count($staffId);
    }
}

if (!function_exists('get_pending_change_request_count')) {
    /**
     * Get count of pending change requests that require action from the current staff member
     *
     * @param int $staffId
     * @return int
     */
    function get_pending_change_request_count(int $staffId): int
    {
        try {
            // Use the PendingApprovalsService for consistency
            $pendingApprovalsService = new \App\Services\PendingApprovalsService([
                'staff_id' => $staffId,
                'division_id' => user_session('division_id'),
                'permissions' => user_session('permissions', []),
                'name' => user_session('name', ''),
                'email' => user_session('email', ''),
                'base_url' => config('app.url')
            ]);
            
            $summaryStats = $pendingApprovalsService->getSummaryStats();
            return $summaryStats['by_category']['Change Request'] ?? 0;
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Error getting pending change request count: ' . $e->getMessage());
        return 0;
        }
    }
}

if (!function_exists('get_staff_total_pending_count')) {
    /**
     * Get the total count of all pending actions across all modules for the current staff member
     *
     * @return int
     */
    function get_staff_total_pending_count(int $staffId = null): int
    {
        if (!$staffId) {
            $user = session('user', []);
            $staffId = $user['staff_id'] ?? null;
        }
        
        if (!$staffId) {
            return 0;
        }
        
        $modules = ['matrices', 'non-travel', 'special-memo', 'service-requests', 'request-arf', 'single-memo', 'change-request'];
        $total = 0;
        
        foreach ($modules as $module) {
            $total += get_staff_pending_action_count($module, $staffId);
        }
        
        return $total;
    }
}

if (!function_exists('get_staff_recent_activities')) {
    /**
     * Get recent activities for the current staff member across all modules
     *
     * @param int $limit
     * @return array
     */
    function get_staff_recent_activities(int $limit = 5): array
    {
        $user = session('user', []);
        $staffId = $user['staff_id'] ?? null;
        
        if (!$staffId) {
            return [];
        }

        $activities = [];
        
        // Get recent matrices
        $recentMatrices = Matrix::where(function ($query) use ($staffId) {
            $query->where('staff_id', $staffId)
                ->orWhere('focal_person_id', $staffId);
        })
        ->orderBy('updated_at', 'desc')
        ->limit($limit)
        ->get(['id', 'title', 'overall_status', 'updated_at']);
        
        foreach ($recentMatrices as $matrix) {
            $activities[] = [
                'type' => 'matrix',
                'id' => $matrix->id,
                'title' => $matrix->title,
                'status' => $matrix->overall_status,
                'updated_at' => $matrix->updated_at,
                'url' => route('matrices.show', $matrix->id)
            ];
        }
        
        // Sort by updated_at and return limited results
        usort($activities, function ($a, $b) {
            return $b['updated_at'] <=> $a['updated_at'];
        });
        
        return array_slice($activities, 0, $limit);
    }
} 

if (!function_exists('get_pending_change_request_count')) {
    /**
     * Get count of pending change requests that require action from the current staff member
     *
     * @param int $staffId
     * @return int
     */
    function get_pending_change_request_count(int $staffId): int
    {
        $userDivisionId = user_session('division_id');
        
        // Use the same logic as other pending approval functions
        $query = \App\Models\ChangeRequest::where('overall_status', 'pending')
            ->where('forward_workflow_id', '!=', null)
            ->where('approval_level', '>', 0);

        $query->where(function($query) use ($userDivisionId, $staffId) {
            // Case 1: Division-specific approval - check if user's division matches change request division
            if ($userDivisionId) {
                $query->whereHas('forwardWorkflow.workflowDefinitions', function($subQ) {
                    $subQ->where('is_division_specific', 1)
                    ->whereNull('division_reference_column')
                          ->where('approval_order', \Illuminate\Support\Facades\DB::raw('change_request.approval_level'));
                })
                ->where('division_id', $userDivisionId);
            }

            // Case 1b: Division-specific approval with division_reference_column
            if ($staffId) {
                $query->orWhere(function($subQ) use ($staffId, $userDivisionId) {
                    $divisionsTable = (new \App\Models\Division())->getTable();
                    $subQ->whereRaw("EXISTS (
                        SELECT 1 FROM workflow_definition wd 
                        JOIN {$divisionsTable} d ON d.id = change_request.division_id 
                        WHERE wd.workflow_id = change_request.forward_workflow_id 
                        AND wd.is_division_specific = 1 
                        AND wd.division_reference_column IS NOT NULL 
                        AND wd.approval_order = change_request.approval_level
                        AND ( d.focal_person = ? OR
                            d.division_head = ? OR
                            d.admin_assistant = ? OR
                            d.finance_officer = ? OR
                            d.head_oic_id = ? OR
                            d.director_id = ? OR
                            d.director_oic_id = ?
                            OR (d.id=change_request.division_id AND d.id=?)
                        )
                    )", [$staffId, $staffId, $staffId, $staffId, $staffId, $staffId, $staffId, $userDivisionId])
                    ->orWhere(function($subQ2) use ($staffId) {
                        $subQ2->where('approval_level', $staffId)
                              ->orWhereHas('approvalTrails', function($trailQ) use ($staffId) {
                                $trailQ->where('staff_id', '=',$staffId);
                              });
                    });
                });
            }
            
            // Case 2: Non-division-specific approval - check workflow definition and approver
            if ($staffId) {
                $query->orWhere(function($subQ) use ($staffId) {
                    $subQ->whereHas('forwardWorkflow.workflowDefinitions', function($workflowQ) use ($staffId) {
                        $workflowQ->where('is_division_specific','=', 0)
                                  ->where('approval_order', \Illuminate\Support\Facades\DB::raw('change_request.approval_level'))
                                  ->whereHas('approvers', function($approverQ) use ($staffId) {
                                      $approverQ->where('staff_id', $staffId);
                                  });
                    });
                });
            }

            $query->orWhere('division_id', $userDivisionId);
        });

        return $query->count();
    }
} 
if (!function_exists('get_base_url')) {
    function get_base_url() {
        $root = (isset($_SERVER["HTTPS"]) ? "https://" : "http://") . $_SERVER["HTTP_HOST"];
        $root .= str_replace(basename($_SERVER["SCRIPT_NAME"]), "", $_SERVER["SCRIPT_NAME"]);
        $base_url = $root;

        $https = false;
        if (isset($_SERVER['HTTPS']) && ($_SERVER['HTTPS'] == 'on' || $_SERVER['HTTPS'] == 1) || isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] == 'https') {
            $protocol = 'https://';
        } else {
            $protocol = 'http://';
            }

        $dirname = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/') . '/';
        $root = $protocol . $_SERVER['HTTP_HOST'] . $dirname;
                $base_url = $root;

        return $base_url;
    }
}

// ============================================================================
// RETURNED MEMOS COUNT FUNCTIONS
// ============================================================================

if (!function_exists('get_my_returned_matrices_count')) {
    /**
     * Get count of returned matrices for the current staff member (including division staff)
     *
     * @param int $staffId
     * @return int
     */
    function get_my_returned_matrices_count(int $staffId): int
    {
        try {
            return \App\Models\Matrix::where('overall_status', 'returned')
                ->where(function($q) use ($staffId) {
                    $q->where('staff_id', $staffId)
                      ->orWhereHas('division', function($divisionQuery) use ($staffId) {
                          $divisionQuery->where('division_head', $staffId)
                                       ->orWhere('focal_person', $staffId)
                                       ->orWhere('admin_assistant', $staffId);
                      });
                })
                ->count();
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Error getting returned matrices count: ' . $e->getMessage());
            return 0;
        }
    }
}

if (!function_exists('get_my_returned_special_memo_count')) {
    /**
     * Get count of returned special memos for the current staff member (including division staff)
     *
     * @param int $staffId
     * @return int
     */
    function get_my_returned_special_memo_count(int $staffId): int
    {
        try {
            return \App\Models\SpecialMemo::where('overall_status', 'returned')
                ->where(function($q) use ($staffId) {
                    $q->where('responsible_person_id', $staffId)
                      ->orWhereHas('division', function($divisionQuery) use ($staffId) {
                          $divisionQuery->where('division_head', $staffId)
                                       ->orWhere('focal_person', $staffId)
                                       ->orWhere('admin_assistant', $staffId);
                      });
                })
                ->count();
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Error getting returned special memos count: ' . $e->getMessage());
            return 0;
        }
    }
}

if (!function_exists('get_my_returned_non_travel_memo_count')) {
    /**
     * Get count of returned non-travel memos for the current staff member (including division staff)
     *
     * @param int $staffId
     * @return int
     */
    function get_my_returned_non_travel_memo_count(int $staffId): int
    {
        try {
            return \App\Models\NonTravelMemo::where('overall_status', 'returned')
                ->where(function($q) use ($staffId) {
                    $q->where('staff_id', $staffId)
                      ->orWhereHas('division', function($divisionQuery) use ($staffId) {
                          $divisionQuery->where('division_head', $staffId)
                                       ->orWhere('focal_person', $staffId)
                                       ->orWhere('admin_assistant', $staffId);
                      });
                })
                ->count();
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Error getting returned non-travel memos count: ' . $e->getMessage());
            return 0;
        }
    }
}

if (!function_exists('get_my_returned_single_memo_count')) {
    /**
     * Get count of returned single memos for the current staff member (including division staff)
     * Note: For single memos, includes both 'returned' and 'draft' status since returned single memos become draft for immediate editing
     *
     * @param int $staffId
     * @return int
     */
    function get_my_returned_single_memo_count(int $staffId): int
    {
        try {
            return \App\Models\Activity::where('is_single_memo', true)
                ->whereIn('overall_status', ['returned', 'draft'])
                ->where(function($q) use ($staffId) {
                    $q->where('staff_id', $staffId)
                      ->orWhere('responsible_person_id', $staffId)
                      ->orWhereHas('division', function($divisionQuery) use ($staffId) {
                          $divisionQuery->where('division_head', $staffId)
                                       ->orWhere('focal_person', $staffId)
                                       ->orWhere('admin_assistant', $staffId);
                      });
                })
                ->count();
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Error getting returned single memos count: ' . $e->getMessage());
            return 0;
        }
    }
}

if (!function_exists('get_my_returned_service_requests_count')) {
    /**
     * Get count of returned service requests for the current staff member (including division staff)
     *
     * @param int $staffId
     * @return int
     */
    function get_my_returned_service_requests_count(int $staffId): int
    {
        try {
            return \App\Models\ServiceRequest::where('overall_status', 'returned')
                ->where(function($q) use ($staffId) {
                    $q->where('staff_id', $staffId)
                      ->orWhereHas('division', function($divisionQuery) use ($staffId) {
                          $divisionQuery->where('division_head', $staffId)
                                       ->orWhere('focal_person', $staffId)
                                       ->orWhere('admin_assistant', $staffId);
                      });
                })
                ->count();
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Error getting returned service requests count: ' . $e->getMessage());
            return 0;
        }
    }
}

if (!function_exists('get_my_returned_request_arf_count')) {
    /**
     * Get count of returned ARF requests for the current staff member (including division staff)
     *
     * @param int $staffId
     * @return int
     */
    function get_my_returned_request_arf_count(int $staffId): int
    {
        try {
            return \App\Models\RequestARF::where('overall_status', 'returned')
                ->where(function($q) use ($staffId) {
                    $q->where('staff_id', $staffId)
                      ->orWhereHas('division', function($divisionQuery) use ($staffId) {
                          $divisionQuery->where('division_head', $staffId)
                                       ->orWhere('focal_person', $staffId)
                                       ->orWhere('admin_assistant', $staffId);
                      });
                })
                ->count();
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Error getting returned ARF requests count: ' . $e->getMessage());
            return 0;
        }
    }
}

if (!function_exists('get_my_returned_change_request_count')) {
    /**
     * Get count of returned change requests for the current staff member (including division staff)
     *
     * @param int $staffId
     * @return int
     */
    function get_my_returned_change_request_count(int $staffId): int
    {
        try {
            return \App\Models\ChangeRequest::where('overall_status', 'returned')
                ->where(function($q) use ($staffId) {
                    $q->where('responsible_person_id', $staffId)
                      ->orWhereHas('division', function($divisionQuery) use ($staffId) {
                          $divisionQuery->where('division_head', $staffId)
                                       ->orWhere('focal_person', $staffId)
                                       ->orWhere('admin_assistant', $staffId);
                      });
                })
                ->count();
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Error getting returned change requests count: ' . $e->getMessage());
            return 0;
        }
    }
} 
