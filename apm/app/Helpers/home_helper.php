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
        $firstLetter = strtoupper($firstName[0] ?? 'A');
        $colorIndex = (ord($firstLetter) - 65) % count($avatarColors);
        if ($colorIndex < 0) $colorIndex = 0;
        $avatarColor = $avatarColors[$colorIndex];

        $photo = $user->photo ?? '';
        $baseUrl = $user->base_url ?? '';
        $photoPath = function_exists('public_path') ? public_path('uploads/staff/' . $photo) : __DIR__ . '/../../../public/uploads/staff/' . $photo;

        $showImage = false;
        if ($photo && file_exists($photoPath) && filesize($photoPath) > 100) { // 0.1kb = 100 bytes
            $showImage = true;
        }

        ob_start();
        if ($showImage) {
            ?>
            <img src="<?php echo htmlspecialchars($baseUrl . 'uploads/staff/' . $photo); ?>"
                class="user-img" alt="user avatar">
            <?php
        } else {
            ?>
            <div class="user-avatar <?php echo $avatarColor; ?> text-white d-flex align-items-center justify-content-center" style="font-weight:600; font-size:1.1rem; width:40px; height:40px; border-radius:50%;">
                <?php if ($firstName && $lastName): ?>
                    <span><?php echo strtoupper(substr($firstName,0,1)) . strtoupper(substr($lastName,0,1)); ?></span>
                <?php elseif ($firstName): ?>
                    <span><?php echo strtoupper(substr($firstName,0,2)); ?></span>
                <?php else: ?>
                    <span>U</span>
                <?php endif; ?>
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
    function get_staff_pending_action_count(string $module): int
    {
        $user = session('user', []);
        $staffId = $user['staff_id'] ?? null;
        
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
        $userDivisionId = user_session('division_id');
        
        // Use the same logic as the pendingApprovals method for consistency
        $query = Matrix::where('overall_status', 'pending')
            ->where('forward_workflow_id', '!=', null)
            ->where('approval_level', '>', 0);

        $query->where(function($query) use ($userDivisionId, $staffId) {
            // Case 1: Division-specific approval - check if user's division matches matrix division
            if ($userDivisionId) {
                $query->whereHas('forwardWorkflow.workflowDefinitions', function($subQ): void {
                    $subQ->where('is_division_specific', 1)
                    ->whereNull('division_reference_column')
                          ->where('approval_order', \Illuminate\Support\Facades\DB::raw('matrices.approval_level'));
                })
                ->where('division_id', $userDivisionId);
            }

            // Case 1b: Division-specific approval with division_reference_column - check if user's staff_id matches the value in the division_reference_column
            if ($staffId) {
                $query->orWhere(function($subQ) use ($staffId, $userDivisionId) {
                    $divisionsTable = (new Division())->getTable();
                    $subQ->whereRaw("EXISTS (
                        SELECT 1 FROM workflow_definition wd 
                        JOIN {$divisionsTable} d ON d.id = matrices.division_id 
                        WHERE wd.workflow_id = matrices.forward_workflow_id 
                        AND wd.is_division_specific = 1 
                        AND wd.division_reference_column IS NOT NULL 
                        AND wd.approval_order = matrices.approval_level
                        AND ( d.focal_person = ? OR
                            d.division_head = ? OR
                            d.admin_assistant = ? OR
                            d.finance_officer = ? OR
                            d.head_oic_id = ? OR
                            d.director_id = ? OR
                            d.director_oic_id = ?
                            OR (d.id=matrices.division_id AND d.id=?)
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
                                  ->where('approval_order', \Illuminate\Support\Facades\DB::raw('matrices.approval_level'))
                                  ->whereHas('approvers', function($approverQ) use ($staffId) {
                                      $approverQ->where('staff_id', $staffId);
                                  });
                    });
                });
            }

            $query->orWhere('division_id', $userDivisionId);
        });

        // Get the matrices and apply the same filtering as pendingApprovals method
        $matrices = $query->get();
        
        // Apply the same additional filtering as pendingApprovals method for consistency
        $filteredMatrices = $matrices->filter(function ($matrix) {
            return can_take_action($matrix);
        });
        
        return $filteredMatrices->count();
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
        $userDivisionId = user_session('division_id');
        
        // Use the same logic as the pendingApprovals method for consistency
        $query = NonTravelMemo::where('overall_status', 'pending')
            ->where('forward_workflow_id', '!=', null)
            ->where('approval_level', '>', 0);

        $query->where(function($query) use ($userDivisionId, $staffId) {
            // Case 1: Division-specific approval - check if user's division matches memo division
            if ($userDivisionId) {
                $query->whereHas('forwardWorkflow.workflowDefinitions', function($subQ): void {
                    $subQ->where('is_division_specific', 1)
                    ->whereNull('division_reference_column')
                          ->where('approval_order', \Illuminate\Support\Facades\DB::raw('non_travel_memos.approval_level'));
                })
                ->where('division_id', $userDivisionId);
            }

            // Case 1b: Division-specific approval with division_reference_column - check if user's staff_id matches the value in the division_reference_column
            if ($staffId) {
                $query->orWhere(function($subQ) use ($staffId, $userDivisionId) {
                    $divisionsTable = (new \App\Models\Division())->getTable();
                    $subQ->whereRaw("EXISTS (
                        SELECT 1 FROM workflow_definition wd 
                        JOIN {$divisionsTable} d ON d.id = non_travel_memos.division_id 
                        WHERE wd.workflow_id = non_travel_memos.forward_workflow_id 
                        AND wd.is_division_specific = 1 
                        AND wd.division_reference_column IS NOT NULL 
                        AND wd.approval_order = non_travel_memos.approval_level
                        AND ( d.focal_person = ? OR
                            d.division_head = ? OR
                            d.admin_assistant = ? OR
                            d.finance_officer = ? OR
                            d.head_oic_id = ? OR
                            d.director_id = ? OR
                            d.director_oic_id = ?
                            OR (d.id=non_travel_memos.division_id AND d.id=?)
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
                                  ->where('approval_order', \Illuminate\Support\Facades\DB::raw('non_travel_memos.approval_level'))
                                  ->whereHas('approvers', function($approverQ) use ($staffId) {
                                      $approverQ->where('staff_id', $staffId);
                                  });
                    });
                });
            }

            $query->orWhere('division_id', $userDivisionId);
        });

        // Get the memos and apply the same filtering as pendingApprovals method
        $memos = $query->get();
        
        // Apply the same additional filtering as pendingApprovals method for consistency
        $filteredMemos = $memos->filter(function ($memo) {
            return can_take_action_generic($memo);
        });
        
        return $filteredMemos->count();
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
        $userDivisionId = user_session('division_id');
        
        // Simplified query that directly checks if the user can approve the memo
        $query = SpecialMemo::where('overall_status', 'pending')
            ->where('forward_workflow_id', '!=', null)
            ->where('approval_level', '>', 0);

        $query->where(function($query) use ($userDivisionId, $staffId) {
            // Case 1: Check if user is an approver for the current approval level
            $query->whereHas('forwardWorkflow.workflowDefinitions', function($subQ) use ($staffId) {
                $subQ->where('approval_order', \Illuminate\Support\Facades\DB::raw('special_memos.approval_level'))
                      ->where(function($workflowQ) use ($staffId) {
                          // Check if user is in approvers table
                          $workflowQ->whereHas('approvers', function($approverQ) use ($staffId) {
                              $approverQ->where('staff_id', $staffId);
                          });
                      });
            });

            // Case 2: Check if user has division-specific role for the current approval level
            if ($userDivisionId) {
                $query->orWhere(function($subQ) use ($userDivisionId, $staffId) {
                    $subQ->whereHas('forwardWorkflow.workflowDefinitions', function($workflowQ) use ($userDivisionId, $staffId) {
                        $workflowQ->where('is_division_specific', 1)
                                  ->where('approval_order', \Illuminate\Support\Facades\DB::raw('special_memos.approval_level'))
                                  ->where(function($divQ) use ($userDivisionId, $staffId) {

                                    $divisionsTable = (new \App\Models\Division())->getTable();
                                      // Check division roles
                                      $divQ->whereRaw("EXISTS (
                                          SELECT 1 FROM {$divisionsTable} d 
                                          WHERE d.id = special_memos.division_id 
                                          AND d.id = ?
                                          AND (
                                              d.focal_person = ? OR
                                              d.division_head = ? OR
                                              d.admin_assistant = ? OR
                                              d.finance_officer = ? OR
                                              d.head_oic_id = ? OR
                                              d.director_id = ? OR
                                              d.director_oic_id = ?
                                          )
                                      )", [$userDivisionId, $staffId, $staffId, $staffId, $staffId, $staffId, $staffId, $staffId]);
                                  });
                    });
                });
            }
        });

        // Get the memos and apply the can_take_action_generic filter
        $memos = $query->get();
        
        // Apply the same additional filtering as pendingApprovals method for consistency
        $filteredMemos = $memos->filter(function ($memo) use ($staffId) {
            return can_take_action_generic($memo, $staffId);
        });
        
        return $filteredMemos->count();
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
        // For now, just return count of pending service requests
        // TODO: Implement proper approval logic when ServiceRequest approval system is added
        return ServiceRequest::where('approval_status', 'pending')
            ->where('forward_workflow_id', '!=', null)
            ->count();
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
    \Log::warning('Arial fonts not found or incomplete. Falling back to DejaVuSans.', [
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
        $footer = ' <table width="100%" style="font-size: 8pt; color: #911C39; border:none;">
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

if (!function_exists('get_pending_request_arf_count')) {
    /**
     * Get count of pending ARF requests that require action from the current staff member
     *
     * @param int $staffId
     * @return int
     */
    function get_pending_request_arf_count(int $staffId): int
    {
        // For now, just return count of pending ARF requests
        // TODO: Implement proper approval logic when RequestARF approval system is added
        return RequestARF::where('overall_status', 'submitted')
            ->where('forward_workflow_id', '!=', null)
            ->count();
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
        // Single memos are activities, so check activity approval trails
        return DB::table('activities')
            ->join('approval_trails', function($join) {
                $join->on('activities.id', '=', 'approval_trails.model_id')
                     ->where('approval_trails.model_type', 'App\\Models\\Activity');
            })
            ->where('activities.overall_status', 'pending')
            ->where('activities.forward_workflow_id', '!=', null)
            ->where('activities.approval_level', '>', 0)
            ->where('approval_trails.staff_id', '!=', $staffId) // Not approved by current user
            ->whereNotExists(function($query) use ($staffId) {
                $query->select(DB::raw(1))
                      ->from('approval_trails as at2')
                      ->whereRaw('at2.model_id = activities.id')
                      ->where('at2.model_type', 'App\\Models\\Activity')
                      ->where('at2.staff_id', $staffId)
                      ->where('at2.action', 'approved');
            })
            ->count();
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
        // Change requests are typically activities with specific request types
        // For now, return 0 as this needs to be implemented based on your change request structure
        return 0;
    }
}

if (!function_exists('get_staff_total_pending_count')) {
    /**
     * Get the total count of all pending actions across all modules for the current staff member
     *
     * @return int
     */
    function get_staff_total_pending_count(): int
    {
        $modules = ['matrices', 'non-travel', 'special-memo', 'service-requests', 'request-arf', 'single-memo', 'change-request'];
        $total = 0;
        
        foreach ($modules as $module) {
            $total += get_staff_pending_action_count($module);
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