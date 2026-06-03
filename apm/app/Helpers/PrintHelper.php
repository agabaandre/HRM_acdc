<?php

namespace App\Helpers;

use App\Models\OtherMemo;
use App\Models\Staff;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;

class PrintHelper
{
    /**
     * Sanitize and normalize HTML before mPDF parsing.
     * Removes risky/unsupported tags and normalizes malformed UTF-8/control chars.
     */
    public static function sanitizeHtmlForMpdf(?string $html): string
    {
        $html = (string) ($html ?? '');
        if ($html === '') {
            return '';
        }

        // Ensure valid UTF-8 and remove control chars that can break PDF parsers.
        if (!mb_check_encoding($html, 'UTF-8')) {
            $html = mb_convert_encoding($html, 'UTF-8', 'UTF-8, ISO-8859-1, Windows-1252');
        }
        $html = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $html) ?? $html;

        // Remove clearly unsafe/executable blocks only. Keep visual tags like svg for rendering fidelity.
        $html = preg_replace('#<(script|noscript|iframe|object|embed)\b[^>]*>.*?</\1>#is', '', $html) ?? $html;

        // Prefer HTML5 parser (composer package) because it preserves modern markup better than DOMDocument.
        if (class_exists(\Masterminds\HTML5::class)) {
            try {
                $parser = new \Masterminds\HTML5(['disable_html_ns' => true]);
                $dom = $parser->loadHTML($html);
                if ($dom instanceof \DOMDocument) {
                    $xpath = new \DOMXPath($dom);
                    foreach ($xpath->query('//*') as $node) {
                        if (!$node instanceof \DOMElement || !$node->hasAttributes()) {
                            continue;
                        }

                        $toRemove = [];
                        foreach ($node->attributes as $attr) {
                            $name = strtolower($attr->name);
                            $value = (string) $attr->value;

                            if (str_starts_with($name, 'on')) {
                                $toRemove[] = $name;
                                continue;
                            }
                            if (in_array($name, ['integrity', 'crossorigin', 'nonce'], true)) {
                                $toRemove[] = $name;
                                continue;
                            }
                            if (in_array($name, ['href', 'src'], true) && preg_match('/^\s*javascript:/i', $value)) {
                                $toRemove[] = $name;
                            }
                        }

                        foreach ($toRemove as $attrName) {
                            $node->removeAttribute($attrName);
                        }
                    }

                    return $parser->saveHTML($dom) ?: $html;
                }
            } catch (\Throwable $e) {
                // Fall through to DOMDocument fallback.
            }
        }

        if (!class_exists(\DOMDocument::class)) {
            return $html;
        }

        try {
            $internal = libxml_use_internal_errors(true);
            $dom = new \DOMDocument('1.0', 'UTF-8');

            // Parse as HTML fragment safely.
            $wrapped = '<!DOCTYPE html><html><head><meta charset="utf-8"></head><body>' . $html . '</body></html>';
            $dom->loadHTML($wrapped, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NOWARNING | LIBXML_NOERROR);

            // Remove dangerous attributes and normalize style/class noise.
            $xpath = new \DOMXPath($dom);
            foreach ($xpath->query('//*') as $node) {
                if (!$node instanceof \DOMElement || !$node->hasAttributes()) {
                    continue;
                }

                $toRemove = [];
                foreach ($node->attributes as $attr) {
                    $name = strtolower($attr->name);
                    $value = (string) $attr->value;

                    if (str_starts_with($name, 'on')) {
                        $toRemove[] = $name;
                        continue;
                    }
                    if (in_array($name, ['srcset', 'integrity', 'crossorigin', 'nonce'], true)) {
                        $toRemove[] = $name;
                        continue;
                    }
                    if (in_array($name, ['href', 'src'], true) && preg_match('/^\s*javascript:/i', $value)) {
                        $toRemove[] = $name;
                    }
                }

                foreach ($toRemove as $attrName) {
                    $node->removeAttribute($attrName);
                }
            }

            $clean = $dom->saveHTML() ?: $html;
            libxml_clear_errors();
            libxml_use_internal_errors($internal);

            return $clean;
        } catch (\Throwable $e) {
            return $html;
        }
    }

    /**
     * Embed staff signature in mPDF HTML: CI3 blocks direct /uploads/staff/signature/* and
     * server-side PDF generation cannot use session-cookie URLs. Read from disk + data URI.
     */
    public static function signatureDataUriForPdf(?string $filename): string
    {
        if ($filename === null || $filename === '') {
            return '';
        }

        $filename = basename(str_replace('\\', '/', $filename));
        if ($filename === '' || $filename === '.' || $filename === '..'
            || !preg_match('/^[a-zA-Z0-9_.-]+$/', $filename)) {
            return '';
        }

        $uploadsRoot = (string) config('staff_portal.uploads_root', dirname(base_path()) . DIRECTORY_SEPARATOR . 'uploads');
        $full = rtrim($uploadsRoot, DIRECTORY_SEPARATOR)
            . DIRECTORY_SEPARATOR . 'staff'
            . DIRECTORY_SEPARATOR . 'signature'
            . DIRECTORY_SEPARATOR . $filename;

        if (!is_file($full) || !is_readable($full)) {
            return '';
        }

        if (!Staff::query()->where('signature', $filename)->exists()) {
            return '';
        }

        $blob = @file_get_contents($full);
        if ($blob === false || $blob === '') {
            return '';
        }

        $mime = 'image/png';
        if (function_exists('finfo_open')) {
            $f = @finfo_open(FILEINFO_MIME_TYPE);
            if ($f) {
                $detected = @finfo_buffer($f, $blob);
                finfo_close($f);
                if (is_string($detected) && str_starts_with($detected, 'image/')) {
                    $mime = $detected;
                }
            }
        }

        return 'data:' . $mime . ';base64,' . base64_encode($blob);
    }

    /**
     * Turn a full HTML document (e.g. memo-pdf-simple) into a fragment safe to embed inside another
     * PDF template: all &lt;style&gt; blocks plus &lt;body&gt; inner HTML only. Avoids nested &lt;html&gt;
     * documents and avoids PCRE (.*) over huge strings, which triggers backtrack limits in mPDF/PHP.
     */
    public static function htmlFullDocumentToEmbedFragment(string $html): string
    {
        if ($html === '') {
            return '';
        }

        // Already a fragment (e.g. second pass or pre-processed embed). Avoid duplicating &lt;style&gt; blocks.
        if (stripos($html, '<html') === false) {
            return $html;
        }

        $out = '';
        $pos = 0;
        $len = strlen($html);
        $maxStyleBytes = 2_000_000;
        $styleBytes = 0;

        while ($pos < $len) {
            $styleOpen = stripos($html, '<style', $pos);
            if ($styleOpen === false) {
                break;
            }
            $gt = strpos($html, '>', $styleOpen);
            if ($gt === false) {
                break;
            }
            $cssStart = $gt + 1;
            $styleClose = stripos($html, '</style>', $cssStart);
            if ($styleClose === false) {
                break;
            }
            $chunkLen = $styleClose - $cssStart;
            if ($styleBytes + $chunkLen > $maxStyleBytes) {
                break;
            }
            $out .= '<style>' . substr($html, $cssStart, $chunkLen) . '</style>';
            $styleBytes += $chunkLen;
            $pos = $styleClose + 8;
        }

        $bodyOpen = stripos($html, '<body');
        if ($bodyOpen === false) {
            return $out . $html;
        }
        $gt = strpos($html, '>', $bodyOpen);
        if ($gt === false) {
            return $out . $html;
        }
        $innerStart = $gt + 1;
        $bodyClose = stripos($html, '</body>', $innerStart);
        if ($bodyClose === false) {
            return $out . trim(substr($html, $innerStart));
        }

        return $out . trim(substr($html, $innerStart, $bodyClose - $innerStart));
    }

    /**
     * Safely get staff email from approver data
     */
    public static function getStaffEmail($approver)
    {
        if (isset($approver['staff']) && isset($approver['staff']['work_email'])) {
            return $approver['staff']['work_email'];
        } elseif (isset($approver['oic_staff']) && isset($approver['oic_staff']['work_email'])) {
            return $approver['oic_staff']['work_email'];
        }
        return null;
    }
    
    /**
     * Safely get staff ID from approver data
     */
    public static function getStaffId($approver)
    {
        if (isset($approver['staff'])) {
            if (isset($approver['staff']['staff_id'])) {
                return $approver['staff']['staff_id'];
            }
            if (isset($approver['staff']['id'])) {
                return $approver['staff']['id'];
            }
        } elseif (isset($approver['oic_staff'])) {
            if (isset($approver['oic_staff']['staff_id'])) {
                return $approver['oic_staff']['staff_id'];
            }
            if (isset($approver['oic_staff']['id'])) {
                return $approver['oic_staff']['id'];
            }
        }
        return null;
    }
    
    /**
     * Generate verification hash for signatures
     */
    public static function generateVerificationHash($itemId, $staffId, $approvalDateTime = null)
    {
        if (!$itemId || !$staffId) return 'N/A';
        $dateTimeToUse = $approvalDateTime ? $approvalDateTime : date('Y-m-d H:i:s');
        return strtoupper(substr(md5(sha1($itemId . $staffId . $dateTimeToUse)), 0, 16));
    }

    /**
     * Get the approval date for a given staff ID and/or approval order from approval trails
     */
    public static function getApprovalDate($staffId, $approvalTrails, $order)
    {
        if (!$approvalTrails || (method_exists($approvalTrails, 'isEmpty') && $approvalTrails->isEmpty())) {
            return 'Not Signed';
        }

        // Normalize to collection and ignore non-signing actions
        if (is_array($approvalTrails)) {
            $approvalTrails = collect($approvalTrails);
        }
        // Signing date must follow actual approvals only (e.g. a later "returned" at the same
        // order must not override the latest approved signature when HOD changes mid-workflow).
        $approvalTrails = $approvalTrails->filter(function ($trail) {
            if (isset($trail->is_archived) && (int) $trail->is_archived === 1) {
                return false;
            }
            $action = strtolower((string) ($trail->action ?? ''));
            return in_array($action, ['approved', 'passed'], true);
        });

        $approval = null;

        // If we have a staff ID, try to find approval by staff_id and approval_order first
        if ($staffId) {
            $approval = $approvalTrails
                ->where('approval_order', (int)$order)
                ->where('staff_id', (int)$staffId)
                ->sortByDesc('created_at')
                ->first();
        }

        // If not found, try to find by oic_staff_id and approval_order
        if (!$approval && $staffId) {
            $approval = $approvalTrails
                ->where('approval_order', (int)$order)
                ->where('oic_staff_id', (int)$staffId)
                ->sortByDesc('created_at')
                ->first();
        }

        // If still not found, try to find by staff_id only (any order)
        if (!$approval && $staffId) {
            $approval = $approvalTrails
                ->where('staff_id', (int)$staffId)
                ->sortByDesc('created_at')
                ->first();
        }

        // If still not found, try to find by oic_staff_id only (any order)
        if (!$approval && $staffId) {
            $approval = $approvalTrails
                ->where('oic_staff_id', (int)$staffId)
                ->sortByDesc('created_at')
                ->first();
        }

        // If still not found, try to find by order only (any staff) — but prefer the most recent for that order
        if (!$approval) {
            $approval = $approvalTrails
                ->where('approval_order', (int)$order)
                ->sortByDesc('created_at')
                ->first();
        }

        if ($approval && isset($approval->created_at)) {
            return is_object($approval->created_at) 
                ? $approval->created_at->format('j F Y H:i') 
                : date('j F Y H:i', strtotime($approval->created_at));
        }

        return 'Not Signed';
    }

    /**
     * Render approver information with OIC support
     */
    public static function renderApproverInfo($approver, $role, $section, $context)
    {
        $isOic = isset($approver['is_oic']) ? $approver['is_oic'] : isset($approver['oic_staff']);
        $staff = $isOic ? $approver['oic_staff'] : $approver['staff'];
        $name = $isOic ? $staff['name'] . ' (OIC)' : trim(($staff['title'] ?? '') . ' ' . ($staff['name'] ?? ''));
        // Ensure name appears above role
        echo '<div class="approver-name">' . htmlspecialchars($name) . '</div>';
        echo '<div class="approver-title">' . htmlspecialchars($role) . '</div>';

        // Add OIC watermark if applicable
        if ($isOic) {
            echo '<div style="position: relative; display: inline-block;">';
            echo '<span style="position: absolute; top: -5px; right: -10px; background: #6b7280; color: white; padding: 2px 6px; border-radius: 3px; font-size: 8px; font-weight: bold; transform: rotate(15deg);">OIC</span>';
            echo '</div>';
        }

        // Show division name for FROM section
        if ($section === 'from') {
            $divisionName = $context->division->division_name ?? '';
            if (!empty($divisionName)) {
                echo '<div class="approver-title">' . htmlspecialchars($divisionName) . '</div>';
            }
        }
    }

    /**
     * Render signature with OIC support
     */
    public static function renderSignature($approver, $order, $approvalTrails, $item)
    {
        $isOic = isset($approver['is_oic']) ? $approver['is_oic'] : isset($approver['oic_staff']);
        $staff = $isOic ? $approver['oic_staff'] : $approver['staff'];
        // Ensure we use canonical staff_id regardless of payload shape
        $staffId = self::getStaffId($approver);

        // Derive signing date strictly from approval trails
        $approvalDate = self::getApprovalDate($staffId, $approvalTrails, $order);

        // Additional dynamic fallback: if not found, search any 'to' orders
        if ($approvalDate === 'Not Signed' && $item && $approvalTrails) {
            // Default SR 'to' orders are 31 and 32
            $toOrders = [31, 32];
            if (isset($item->forward_workflow_id)) {
                // Try to read from workflow definitions; if none are flagged, keep defaults
                $defined = \App\Models\WorkflowDefinition::where('workflow_id', $item->forward_workflow_id)
                    ->where('is_enabled', 1)
                    ->pluck('approval_order')
                    ->map(fn($v) => (int)$v)
                    ->toArray();
                if (!empty($defined)) {
                    // Prefer definitions but ensure SR 'to' orders are included
                    $toOrders = array_values(array_unique(array_merge($toOrders, $defined)));
                }
            }

            if (!empty($toOrders)) {
                if (is_array($approvalTrails)) { $approvalTrails = collect($approvalTrails); }
                $subset = $approvalTrails->whereIn('approval_order', $toOrders)->sortByDesc('created_at');
                if ($staffId) {
                    $match = $subset->first(function ($t) use ($staffId) {
                        return ((int)($t->staff_id ?? 0) === (int)$staffId) || ((int)($t->oic_staff_id ?? 0) === (int)$staffId);
                    });
                    if ($match) {
                        $approvalDate = is_object($match->created_at) ? $match->created_at->format('j F Y H:i') : date('j F Y H:i', strtotime($match->created_at));
                    }
                }
                if ($approvalDate === 'Not Signed' && $subset->first()) {
                    $latest = $subset->first();
                    $approvalDate = is_object($latest->created_at) ? $latest->created_at->format('j F Y H:i') : date('j F Y H:i', strtotime($latest->created_at));
                }
            }
        }

        // Final fallback: use the latest trail for this staff (any order in this SR)
        if ($approvalDate === 'Not Signed' && $approvalTrails && $staffId) {
            if (is_array($approvalTrails)) { $approvalTrails = collect($approvalTrails); }
            $byStaff = $approvalTrails->filter(function ($t) use ($staffId) {
                return ((int)($t->staff_id ?? 0) === (int)$staffId) || ((int)($t->oic_staff_id ?? 0) === (int)$staffId);
            })->sortByDesc('created_at');
            if ($byStaff->first()) {
                $latest = $byStaff->first();
                $approvalDate = is_object($latest->created_at) ? $latest->created_at->format('j F Y H:i') : date('j F Y H:i', strtotime($latest->created_at));
            }
        }

        echo '<div style="line-height: 1.2;">';
        
        // Always show signature image if available (even if not yet signed)
        if (isset($staff['signature']) && !empty($staff['signature'])) {
            $sigSrc = self::signatureDataUriForPdf($staff['signature']);
            echo '<small style="color: #666; font-style: normal; font-size: 9px;">Signed By:</small> ';
            if ($sigSrc !== '') {
                echo '<img class="signature-image" src="' . htmlspecialchars($sigSrc) . '" alt="Signature">';
            } else {
                echo '<small style="color: #666; font-style:normal;">Signed By: ' . htmlspecialchars($staff['work_email'] ?? 'Email not available') . '</small>';
            }
        } else {
            echo '<small style="color: #666; font-style:normal;">Signed By: ' . htmlspecialchars($staff['work_email'] ?? 'Email not available') . '</small>';
        }

        // For Service Requests, always use direct DB lookup with staff_id to ensure correct dates
        if ($item && isset($item->id) && $order && $staffId) {
            $dbFallback = \App\Models\ApprovalTrail::where('model_type', 'App\\Models\\ServiceRequest')
                ->where('model_id', $item->id)
                ->where('approval_order', (int)$order)
                ->where('action', 'approved')
                ->where(function($query) use ($staffId) {
                    $query->where('staff_id', $staffId)
                          ->orWhere('oic_staff_id', $staffId);
                })
                ->orderBy('created_at', 'desc')
                ->first();
            if ($dbFallback && isset($dbFallback->created_at)) {
                $approvalDate = is_object($dbFallback->created_at) ? $dbFallback->created_at->format('j F Y H:i') : date('j F Y H:i', strtotime($dbFallback->created_at));
            }
        }

        if ($approvalDate === 'Not Signed') {
            echo '<div class="signature-date" style="color:#999;"><em>Not Signed</em></div>';
        } else {
            echo '<div class="signature-date">' . htmlspecialchars($approvalDate) . '</div>';
            echo '<div class="signature-hash">Verify Hash: ' . htmlspecialchars(self::generateVerificationHash($item->id, $staffId, $approvalDate)) . '</div>';
        }
        
        echo '</div>';
    }

    /**
     * Render budget approver info with OIC support
     */
    public static function renderBudgetApproverInfo($approval, $label = '')
    {
        if (!$approval) {
            echo 'N/A';
            return;
        }

        $isOic = !empty($approval->oic_staff_id);
        $staff = $isOic ? $approval->oicStaff : $approval->staff;
        
        if (!$staff) {
            echo 'N/A';
            return;
        }

        $name = $staff->title . ' ' . $staff->fname . ' ' . $staff->lname . ' ' . $staff->oname;
        if ($isOic) {
            $name .= ' (OIC)';
        }

        echo '<div class="approver-name">' . htmlspecialchars($name) . '</div>';

        // Get role from workflow definition instead of job_name
        $role = 'N/A';
        if (isset($approval->workflowDefinition) && $approval->workflowDefinition) {
            $role = $approval->workflowDefinition->role ?? 'N/A';
        } elseif (isset($approval->role)) {
            $role = $approval->role;
        }
        echo '<div class="approver-title">' . htmlspecialchars($role) . '</div>';
    
        if($approval->workflowDefinition->approval_order == 1){
            echo '<div class="approver-title">' . htmlspecialchars($staff->division_name ?? 'N/A') . '</div>';
        }
        echo '<span class="fill line"></span>';
    }

    /**
     * Render budget signature with OIC support
     */
    public static function renderBudgetSignature($approval, $item, $label = '')
    {
        if (!$approval) {
            echo '<span style="color:#aaa;">N/A</span>';
            return;
        }

        $isOic = !empty($approval->oic_staff_id);
        $staff = $isOic ? $approval->oicStaff : $approval->staff;
        
        if (!$staff) {
            echo '<span style="color:#aaa;">N/A</span>';
            return;
        }

        $name = $staff->title . ' ' . $staff->fname . ' ' . $staff->lname . ' ' . $staff->oname;
        if ($isOic) {
            $name .= ' (OIC)';
        }

        echo '<div style="line-height: 1.5;">';
        
        echo '<small style="color: #666; font-style: normal; font-size: 9px;">Signed By:</small><br>';
        
        if (!empty($staff->signature)) {
            $sigSrc = self::signatureDataUriForPdf($staff->signature);
            if ($sigSrc !== '') {
                echo '<img class="signature-image" src="' . htmlspecialchars($sigSrc) . '" alt="Signature">';
            } else {
                echo '<small style="color: #666; font-style: normal;">' . htmlspecialchars($staff->work_email ?? 'Email not available') . '</small>';
            }
        } else {
            echo '<small style="color: #666; font-style: normal;">' . htmlspecialchars($staff->work_email ?? 'Email not available') . '</small>';
        }
        
        $approvalDate = is_object($approval->created_at) ? $approval->created_at->format('j F Y H:i') : date('j F Y H:i', strtotime($approval->created_at));
        echo '<div class="signature-date">' . htmlspecialchars($approvalDate) . '</div>';
        
        $hash = self::generateVerificationHash($item->id, $isOic ? $approval->oic_staff_id : $approval->staff_id, $approval->created_at);
        echo '<div class="signature-hash">Verify Hash: ' . htmlspecialchars($hash) . '</div>';
         
        // Add OIC watermark if applicable
        if ($isOic) {
            echo '<div style="position: relative; display: inline-block; margin-top: 5px;">';
            echo '<span style="position: absolute; top: -5px; right: -10px; background: #6b7280; color: white; padding: 2px 6px; border-radius: 3px; font-size: 8px; font-weight: bold; transform: rotate(15deg);">OIC</span>';
            echo '</div>';
        }
        
        echo '</div>';
    }

    /**
     * Get latest approval for a specific order
     */
    public static function getLatestApprovalForOrder($approvalTrails, $order)
    {
        // Ensure order is an integer for type-safe comparison
        $order = (int)$order;

        if (is_array($approvalTrails)) {
            $approvalTrails = collect($approvalTrails);
        }

        // Latest *signature* at this level: only approved rows (HOD can change mid-workflow;
        // "returned" or other actions must not win over a later approved signature).
        $approvals = $approvalTrails->filter(function ($trail) use ($order) {
            if (isset($trail->is_archived) && (int) $trail->is_archived === 1) {
                return false;
            }
            $trailOrder = (int) ($trail->approval_order ?? 0);
            $action = strtolower((string) ($trail->action ?? ''));

            return $trailOrder === $order && $action === 'approved';
        });

        return $approvals->sortByDesc('created_at')->first();
    }

    /**
     * Resolve which Chief of Staff approval order (10 or 11) applies for this memo.
     * Chief of Staff exists at both level 10 and 11; only one approves per memo depending on allowed divisions.
     * Backward compatibility: old memos approved by 10 only.
     *
     * @param \Illuminate\Support\Collection $approvalTrails
     * @param int $workflowId
     * @param mixed $divisionContext Division model, or array with division_id and/or category
     * @return int|null 10 or 11, or null if none found
     */
    public static function getChiefOfStaffApprovalOrder($approvalTrails, $workflowId, $divisionContext = null)
    {
        $workflowId = (int) $workflowId;
        $definitions = \App\Models\WorkflowDefinition::where('workflow_id', $workflowId)
            ->whereIn('approval_order', [10, 11])
            ->where('is_enabled', 1)
            ->orderBy('approval_order')
            ->get();

        $divisionId = null;
        $divisionCategory = null;
        if ($divisionContext) {
            if (is_object($divisionContext)) {
                $divisionId = $divisionContext->id ?? null;
                $divisionCategory = $divisionContext->category ?? null;
            } elseif (is_array($divisionContext)) {
                $divisionId = $divisionContext['division_id'] ?? $divisionContext['id'] ?? null;
                $divisionCategory = $divisionContext['category'] ?? null;
            }
        }

        // If we have division context, find the definition that allows this division
        if ($divisionId !== null || $divisionCategory !== null) {
            foreach ($definitions as $def) {
                $divisions = $def->divisions ?? [];
                $category = $def->category ?? null;
                $allowsDivision = false;
                if (is_array($divisions) && $divisionId !== null && in_array($divisionId, $divisions)) {
                    $allowsDivision = true;
                }
                if ($category !== null && $divisionCategory !== null && (string) $category === (string) $divisionCategory) {
                    $allowsDivision = true;
                }
                if ($allowsDivision) {
                    $order = (int) $def->approval_order;
                    $approval = self::getLatestApprovalForOrder($approvalTrails, $order);
                    if ($approval) {
                        return $order;
                    }
                }
            }
        }

        // Backward compatibility: try order 10 first (old memos), then 11
        $approval10 = self::getLatestApprovalForOrder($approvalTrails, 10);
        if ($approval10) {
            return 10;
        }
        $approval11 = self::getLatestApprovalForOrder($approvalTrails, 11);
        if ($approval11) {
            return 11;
        }
        return null;
    }

    /**
     * Generate short code from division name
     */
    public static function generateShortCodeFromDivision(string $name): string
    {
        $ignore = ['of', 'and', 'for', 'the', 'in'];
        $words = preg_split('/\s+/', strtolower($name));
        $initials = array_map(function ($word) use ($ignore) {
            // Check if word is not empty before accessing first character
            if (empty($word) || in_array($word, $ignore)) {
                return '';
            }
            return strtoupper($word[0]);
        }, $words);
        return implode('', array_filter($initials));
    }

    /**
     * Fetch approvers from approval trails with single record per order
     */
    public static function fetchApproversFromTrails($modelId, $modelType, $divisionId = null, $workflowId = null)
    {
        $approvers = [];
        
        // Fetch approval trails for the model with staff and OIC staff
        $query = \App\Models\ApprovalTrail::where('model_id', $modelId)
            ->where('model_type', $modelType)
            ->where('is_archived', 0)
            ->with(['staff', 'oicStaff']);
            
        // Add workflow_id filter if provided to avoid mixing up approvers from different workflows
        if ($workflowId) {
            $query->where('forward_workflow_id', $workflowId);
        }
        
        $approvalTrails = $query->orderBy('approval_order')
            ->orderBy('created_at', 'desc')
            ->get()
            ->filter(function ($trail) {
                return strtolower((string) ($trail->action ?? '')) === 'approved';
            });

        // Group by approval order, taking only the most recent approved row for each order
        $processedOrders = [];
        foreach ($approvalTrails as $trail) {
            $order = $trail->approval_order;
            
            // Skip if we already have the most recent approval for this order
            if (in_array($order, $processedOrders)) {
                continue;
            }
            
            // Determine if this is an OIC approver
            $isOic = !empty($trail->oic_staff_id);
            
            // Get the correct workflow definition based on approval_order and workflow_id
            $workflowDefinition = null;
            if ($trail->forward_workflow_id) {
                $workflowDefinition = \App\Models\WorkflowDefinition::where('approval_order', $order)
                    ->where('workflow_id', $trail->forward_workflow_id)
                    ->first();
            }
            
            $approver = [
                'staff' => $trail->staff ? [
                    'id' => $trail->staff->id,
                    'staff_id' => $trail->staff->id,
                    'name' => trim(($trail->staff->fname ?? '') . ' ' . ($trail->staff->lname ?? '') . ' ' . ($trail->staff->oname ?? '')),
                    'fname' => $trail->staff->fname ?? '',
                    'lname' => $trail->staff->lname ?? '',
                    'oname' => $trail->staff->oname ?? '',
                    'title' => $trail->staff->title ?? '',
                    'work_email' => $trail->staff->work_email ?? '',
                    'signature' => $trail->staff->signature ?? ''
                ] : null,
                'oic_staff' => $trail->oicStaff ? [
                    'id' => $trail->oicStaff->id,
                    'staff_id' => $trail->oicStaff->id,
                    'name' => trim(($trail->oicStaff->fname ?? '') . ' ' . ($trail->oicStaff->lname ?? '') . ' ' . ($trail->oicStaff->oname ?? '')),
                    'fname' => $trail->oicStaff->fname ?? '',
                    'lname' => $trail->oicStaff->lname ?? '',
                    'oname' => $trail->oicStaff->oname ?? '',
                    'title' => $trail->oicStaff->title ?? '',
                    'work_email' => $trail->oicStaff->work_email ?? '',
                    'signature' => $trail->oicStaff->signature ?? ''
                ] : null,
                'role' => $workflowDefinition ? $workflowDefinition->role : ($trail->role ?? 'Approver'),
                'order' => $order,
                'is_oic' => $isOic
            ];
            
            // Store as single record (not array) for each order
            $approvers[$order] = [$approver];
            $processedOrders[] = $order;
        }
        
        // Also fetch division head if available (only as fallback when no level 1 approver)
        if (!isset($approvers[1]) && $divisionId) {
            $division = \App\Models\Division::find($divisionId);
            if ($division && $division->division_head) {
                $divisionHead = \App\Models\Staff::find($division->division_head);
                if ($divisionHead) {
                    // Add division head as a single fallback approver
                    $approvers['division_head'] = [[
                        'staff' => [
                            'id' => $divisionHead->id,
                            'name' => $divisionHead->fname . ' ' . $divisionHead->lname,
                            'title' => $divisionHead->title,
                            'work_email' => $divisionHead->work_email,
                            'signature' => $divisionHead->signature
                        ],
                        'oic_staff' => null,
                        'role' => 'Head of Division',
                        'order' => 'division_head',
                        'is_oic' => false
                    ]];
                }
            }
        }

        return $approvers;
    }

    /**
     * Organize workflow steps by memo_print_section for dynamic memo rendering
     * This is a reusable helper for all memo print templates
     */
    public static function organizeWorkflowStepsBySection($workflowSteps)
    {
        $organizedSteps = [
            'to' => [],
            'through' => [],
            'from' => [],
            'others' => []
        ];

        foreach ($workflowSteps as $step) {
            $section = $step['memo_print_section'] ?? 'through';
            $organizedSteps[$section][] = $step;
        }

        // Sort each section by print_order first, then by approval order as fallback
        foreach ($organizedSteps as $section => $steps) {
            usort($steps, function($a, $b) {
                $aPrintOrder = $a['print_order'] ?? 0;
                $bPrintOrder = $b['print_order'] ?? 0;
                if ($aPrintOrder != $bPrintOrder) {
                    return $aPrintOrder <=> $bPrintOrder;
                }
                return ($a['order'] ?? 0) <=> ($b['order'] ?? 0);
            });
            $organizedSteps[$section] = $steps;
        }

        return $organizedSteps;
    }

    /**
     * Get workflow definitions with category filtering for memo printing
     * This is a reusable helper for all memo print templates
     */
    public static function getWorkflowDefinitionsForMemo($workflowId, $divisionCategory = null)
    {
        return \App\Models\WorkflowDefinition::where('workflow_id', $workflowId)
            ->where('is_enabled', 1)
            ->where(function($query) use ($divisionCategory) {
                $query->where('approval_order', '!=', 7)
                      ->orWhere(function($subQuery) use ($divisionCategory) {
                          $subQuery->where('approval_order', 7)
                                   ->where('category', $divisionCategory ?? '');
                      });
            })
            ->orderBy('approval_order')
            ->get();
    }

    /**
     * Organize approvers by section based on approval order and category for memo printing
     * This is a reusable helper for all memo print templates
     */
    public static function organizeApproversBySection($matrixId, $modelType, $divisionId, $workflowId, $divisionCategory = null)
    {
        // Fetch approvers from approval trails
        $approvers = self::fetchApproversFromTrails($matrixId, $modelType, $divisionId, $workflowId);

        // Organize approvers by section based on approval order and category
        $organizedApprovers = [
            'to' => [],
            'through' => [],
            'from' => [],
            'others' => []
        ];

        if ($workflowId) {
            // Get workflow definitions with category filtering
            $workflowDefinitions = self::getWorkflowDefinitionsForMemo($workflowId, $divisionCategory);

            // First, collect all approvers by section
            $sectionApprovers = [];
            foreach ($workflowDefinitions as $definition) {
                $section = $definition->memo_print_section ?? 'through';
                
                // Map approval orders to sections
                if ($definition->approval_order == 11) {
                    $section = 'to';
                } elseif (in_array($definition->approval_order, [7, 8, 9, 10])) {
                    $section = 'through';
                } elseif ($definition->approval_order == 1) {
                    $section = 'from';
                }

                // Ensure section is valid, default to 'through' if not
                if (!in_array($section, ['to', 'through', 'from', 'others'])) {
                    $section = 'through';
                }

                // Get approvers for this definition from approval trails
                $approversForOrder = [];
                if (isset($approvers[$definition->approval_order])) {
                    $approversForOrder = $approvers[$definition->approval_order];
                } elseif ($definition->approval_order == 1 && isset($approvers['division_head'])) {
                    $approversForOrder = $approvers['division_head'];
                }

                if (!empty($approversForOrder)) {
                    if (!isset($sectionApprovers[$section])) {
                        $sectionApprovers[$section] = [];
                    }
                    
                    // Add print_order and approval_order to each approver for sorting
                    foreach ($approversForOrder as $approver) {
                        $approver['print_order'] = $definition->print_order;
                        $approver['approval_order'] = $definition->approval_order;
                        $sectionApprovers[$section][] = $approver;
                    }
                }
            }
            
            // Sort each section by print_order first, then by approval_order as fallback
            foreach ($sectionApprovers as $section => $approvers) {
                usort($approvers, function($a, $b) {
                    $aPrintOrder = $a['print_order'] ?? 0;
                    $bPrintOrder = $b['print_order'] ?? 0;
                    if ($aPrintOrder != $bPrintOrder) {
                        return $aPrintOrder <=> $bPrintOrder;
                    }
                    return ($a['approval_order'] ?? 0) <=> ($b['approval_order'] ?? 0);
                });
                $organizedApprovers[$section] = $approvers;
            }
        }

        return $organizedApprovers;
    }

    /**
     * Get financial approvers for budget section based on workflow definition
     * This method dynamically gets the correct approvers for budget signatures
     */
    public static function getFinancialApprovers($activityApprovalTrails, $workflowId = 1)
    {
        $financialApprovers = [];

        if (is_array($activityApprovalTrails)) {
            $activityApprovalTrails = collect($activityApprovalTrails);
        }

        // Keep backward-compatible fallback orders for legacy workflows.
        $fallbackOrders = [
            'Head of Division' => 1,      // Prepared by
            'Finance Officer' => 5,       // Endorsed by (SFO)
            'Director Finance' => 6,      // Endorsed by (Director Finance)
            'Deputy Director General' => 9, // Approved by
        ];

        // Resolve role orders dynamically from workflow definitions (important for
        // non-matrix memos such as Special Memo where finance levels may differ).
        $roleKeywords = [
            'Head of Division' => ['head of division', 'hod'],
            'Finance Officer' => ['finance officer', 'senior finance officer', 'sfo'],
            'Director Finance' => ['director finance', 'director of finance'],
            'Deputy Director General' => ['deputy director general', 'ddg'],
        ];

        $resolvedOrdersByRole = [];
        $approvedTrails = $activityApprovalTrails
            ->filter(function ($trail) {
                if (isset($trail->is_archived) && (int) $trail->is_archived === 1) {
                    return false;
                }
                return strtolower((string) ($trail->action ?? '')) === 'approved';
            })
            ->sortByDesc('created_at')
            ->values();
        $workflowId = (int) $workflowId;
        if ($workflowId > 0) {
            $definitions = \App\Models\WorkflowDefinition::where('workflow_id', $workflowId)
                ->where('is_enabled', 1)
                ->get(['approval_order', 'role']);

            foreach ($roleKeywords as $role => $keywords) {
                $orders = [];
                foreach ($definitions as $definition) {
                    $defRole = strtolower((string) ($definition->role ?? ''));
                    foreach ($keywords as $keyword) {
                        if ($defRole !== '' && strpos($defRole, $keyword) !== false) {
                            $orders[] = (int) $definition->approval_order;
                            break;
                        }
                    }
                }
                $resolvedOrdersByRole[$role] = array_values(array_unique($orders));
            }
        }

        foreach ($fallbackOrders as $role => $fallbackOrder) {
            // Primary source: actual approved approval_trails role labels
            // (workflowDefinition/approverRole) for this memo/document.
            $keywords = $roleKeywords[$role] ?? [];
            $approvalByRoleName = $approvedTrails->first(function ($trail) use ($keywords) {
                $trailRole = strtolower((string) (
                    $trail->workflowDefinition->role
                    ?? $trail->approverRole->role
                    ?? ''
                ));
                if ($trailRole === '') {
                    return false;
                }
                foreach ($keywords as $keyword) {
                    if (strpos($trailRole, $keyword) !== false) {
                        return true;
                    }
                }
                return false;
            });

            if ($approvalByRoleName) {
                $financialApprovers[$role] = $approvalByRoleName;
                continue;
            }

            // Fallback source: resolve order(s) then pick latest approved trail at those orders.
            $orders = $resolvedOrdersByRole[$role] ?? [];
            if (empty($orders)) {
                $orders = [$fallbackOrder];
            }

            $approval = $approvedTrails
                ->first(function ($trail) use ($orders) {
                    $trailOrder = (int) ($trail->approval_order ?? 0);
                    return in_array($trailOrder, $orders, true);
                })
            ;

            if ($approval) {
                $financialApprovers[$role] = $approval;
            }
        }

        return $financialApprovers;
    }
    /**
     * @param \Illuminate\Support\Collection|array $activityApprovalTrails
     * @param int $workflowId
     * @param mixed $divisionContext Optional. Division model or array with division_id/category for Chief of Staff resolution (levels 10 vs 11).
     */
    public static function getARFApprovers($activityApprovalTrails, $workflowId = 1, $divisionContext = null)
    {
        $ARFApprovers = [];
        
        // Ensure it's a collection
        if (is_array($activityApprovalTrails)) {
            $activityApprovalTrails = collect($activityApprovalTrails);
        }
        
        // Define the financial approver roles and their expected approval orders.
        // Chief of Staff can be at 10 or 11 depending on allowed divisions; resolved below.
        $ARFRoles = [
            'Grants' => 3,             // Endorsed by (SFO)
            'Chief of Staff' => null,   // Resolved via getChiefOfStaffApprovalOrder (10 or 11)
            'Director General' => 12,  // Approved by
        ];
        
        foreach ($ARFRoles as $role => $expectedOrder) {
            if ($role === 'Chief of Staff') {
                $resolvedOrder = self::getChiefOfStaffApprovalOrder($activityApprovalTrails, $workflowId, $divisionContext);
                if ($resolvedOrder === null) {
                    continue;
                }
                $expectedOrder = $resolvedOrder;
            }
            $approval = self::getLatestApprovalForOrder($activityApprovalTrails, $expectedOrder);
            if ($approval) {
                // If it's an ApprovalTrail model, convert to structured array format
                if (is_object($approval) && method_exists($approval, 'getAttribute')) {
                    $isOic = !empty($approval->oic_staff_id);
                    $staffModel = $isOic ? $approval->oicStaff : $approval->staff;
                    
                    if ($staffModel) {
                        // Get role from workflow definition (filtered by workflow_id and approval_order)
                        $roleName = $role;
                        if ($workflowId && $expectedOrder) {
                            // First try to get from approval trail's forward_workflow_id if available
                            $trailWorkflowId = $approval->forward_workflow_id ?? $workflowId;
                            
                            $workflowDefinition = \App\Models\WorkflowDefinition::where('workflow_id', $trailWorkflowId)
                                ->where('approval_order', $expectedOrder)
                                ->where('is_enabled', 1)
                                ->first();
                            
                            if ($workflowDefinition) {
                                $roleName = $workflowDefinition->role ?? $role;
                            } elseif ($approval->approverRole) {
                                // Fallback to approverRole relationship if workflow definition not found
                                $roleName = $approval->approverRole->role ?? $role;
                            }
                        } elseif ($approval->approverRole) {
                            // Fallback to approverRole relationship if workflowId not available
                            $roleName = $approval->approverRole->role ?? $role;
                        }
                        
                        $ARFApprovers[$role] = [
                            'staff' => [
                                'id' => $staffModel->id ?? null,
                                'staff_id' => $staffModel->staff_id ?? ($staffModel->id ?? null),
                                'fname' => $staffModel->fname ?? '',
                                'lname' => $staffModel->lname ?? '',
                                'oname' => $staffModel->oname ?? '',
                                'title' => $staffModel->title ?? '',
                                'signature' => $staffModel->signature ?? null,
                                'work_email' => $staffModel->work_email ?? null
                            ],
                            'oic_staff' => $isOic && $approval->oicStaff ? [
                                'id' => $approval->oicStaff->id ?? null,
                                'staff_id' => $approval->oicStaff->staff_id ?? ($approval->oicStaff->id ?? null),
                                'fname' => $approval->oicStaff->fname ?? '',
                                'lname' => $approval->oicStaff->lname ?? '',
                                'oname' => $approval->oicStaff->oname ?? '',
                                'title' => $approval->oicStaff->title ?? '',
                                'signature' => $approval->oicStaff->signature ?? null,
                                'work_email' => $approval->oicStaff->work_email ?? null
                            ] : null,
                            'role' => $roleName,
                            'order' => (int)$expectedOrder,
                            'is_oic' => $isOic,
                            'created_at' => $approval->created_at ?? null
                        ];
                    } else {
                        // Staff not found, but keep the approval object for backward compatibility
                        $ARFApprovers[$role] = $approval;
                    }
                } else {
                    // Already in array format or other format
                    $ARFApprovers[$role] = $approval;
                }
            }
        }
        
        return $ARFApprovers;
    }
    public static function getServiceRequestApprovers($workflowId, $divisionId = null, $approvalTrails = null)
    {
        $organizedApprovers = [
            'to' => [],
            'from' => []
        ];

        // Default SR workflow to 3 if not provided
        if (!$workflowId) {
            $workflowId = 3;
        }

        if (!$workflowId) {
            return $organizedApprovers;
        }

        // Get workflow definitions for the workflow, sorted by print_order then approval_order
        $workflowDefinitions = \App\Models\WorkflowDefinition::where('workflow_id', $workflowId)
            ->where('is_enabled', 1)
            ->orderBy('print_order')
            ->orderBy('approval_order')
            ->get();

        foreach ($workflowDefinitions as $definition) {
            // Only include steps explicitly marked for the 'to' section
            if (($definition->memo_print_section ?? 'through') !== 'to') {
                continue;
            }

            $approverPayload = null;

            // Prefer using approval trails to resolve the actual approver (including OIC)
            if ($approvalTrails && method_exists($approvalTrails, 'where')) {
                $latestForOrder = self::getLatestApprovalForOrder($approvalTrails, (int)$definition->approval_order);
                if ($latestForOrder) {
                    $isOic = !empty($latestForOrder->oic_staff_id);
                    $staffModel = $isOic ? $latestForOrder->oicStaff : $latestForOrder->staff;
                    if ($staffModel) {
                        $approverPayload = [
                            'staff' => [
                                'id' => $staffModel->id ?? null,
                                'staff_id' => $staffModel->staff_id ?? ($staffModel->id ?? null),
                                'name' => trim(($staffModel->fname ?? '') . ' ' . ($staffModel->lname ?? '')),
                                'title' => $staffModel->title ?? '',
                                'signature' => $staffModel->signature ?? null,
                                'work_email' => $staffModel->work_email ?? null
                            ],
                            'oic_staff' => $isOic && $latestForOrder->oicStaff ? [
                                'id' => $latestForOrder->oicStaff->id ?? null,
                                'staff_id' => $latestForOrder->oicStaff->staff_id ?? ($latestForOrder->oicStaff->id ?? null),
                                'name' => trim(($latestForOrder->oicStaff->fname ?? '') . ' ' . ($latestForOrder->oicStaff->lname ?? '')),
                                'title' => $latestForOrder->oicStaff->title ?? '',
                                'signature' => $latestForOrder->oicStaff->signature ?? null,
                                'work_email' => $latestForOrder->oicStaff->work_email ?? null
                            ] : null,
                            'role' => $definition->role,
                            'order' => (int)$definition->approval_order,
                            'is_oic' => $isOic
                        ];
                    }
                }
            }

            // Fallback: no trail yet; attempt to resolve by division-specific role-owner (e.g., division head)
            if (!$approverPayload) {
                // Division-specific roles can attempt a division head match if role indicates HOD
                if ($definition->is_division_specific && $divisionId && stripos($definition->role, 'Head of Division') !== false) {
                    $division = \App\Models\Division::find($divisionId);
                    if ($division && $division->division_head) {
                        $staff = \App\Models\Staff::find($division->division_head);
                        if ($staff) {
                            $approverPayload = [
                                'staff' => [
                                    'id' => $staff->id,
                                    'name' => trim(($staff->fname ?? '') . ' ' . ($staff->lname ?? '')),
                                    'title' => $staff->title ?? '',
                                    'signature' => $staff->signature ?? null,
                                    'work_email' => $staff->work_email ?? null
                                ],
                                'oic_staff' => null,
                                'role' => $definition->role,
                                'order' => (int)$definition->approval_order,
                                'is_oic' => false
                            ];
                        }
                    }
                }
            }

            // Fallback 2: use Approver assignment table for this workflow definition
            if (!$approverPayload) {
                $assignment = \App\Models\Approver::where('workflow_dfn_id', $definition->id)
                    ->with(['staff', 'oicStaff'])
                    ->first();
                if ($assignment && $assignment->staff) {
                    $approverPayload = [
                        'staff' => [
                            'id' => $assignment->staff->id ?? null,
                            'staff_id' => $assignment->staff->staff_id ?? ($assignment->staff->id ?? null),
                            'name' => trim(($assignment->staff->fname ?? '') . ' ' . ($assignment->staff->lname ?? '')),
                            'title' => $assignment->staff->title ?? '',
                            'signature' => $assignment->staff->signature ?? null,
                            'work_email' => $assignment->staff->work_email ?? null
                        ],
                        'oic_staff' => $assignment->oicStaff ? [
                            'id' => $assignment->oicStaff->id ?? null,
                            'staff_id' => $assignment->oicStaff->staff_id ?? ($assignment->oicStaff->id ?? null),
                            'name' => trim(($assignment->oicStaff->fname ?? '') . ' ' . ($assignment->oicStaff->lname ?? '')),
                            'title' => $assignment->oicStaff->title ?? '',
                            'signature' => $assignment->oicStaff->signature ?? null,
                            'work_email' => $assignment->oicStaff->work_email ?? null
                        ] : null,
                        'role' => $definition->role,
                        'order' => (int)$definition->approval_order,
                        'is_oic' => false
                    ];
                }
            }

            // If still no payload, render role placeholder without staff
            if (!$approverPayload) {
                $approverPayload = [
                    'staff' => [
                        'id' => null,
                        'name' => '',
                        'title' => '',
                        'signature' => null,
                        'work_email' => null
                    ],
                    'oic_staff' => null,
                    'role' => $definition->role,
                    'order' => (int)$definition->approval_order,
                    'is_oic' => false
                ];
            }

            $organizedApprovers['to'][] = $approverPayload;
        }

        return $organizedApprovers;
    }

    /**
     * Trim leading/trailing whitespace and invisible characters (NBSP, BOM, zero-width, etc.)
     * from pasted Summernote HTML before save or sanitization.
     */
    public static function trimRichTextInput(?string $html): string
    {
        if ($html === null) {
            return '';
        }

        return Str::trim($html);
    }

    /**
     * Payload field key used as the PDF document title (subject), if present.
     */
    public static function otherMemoSubjectFieldKey(OtherMemo $memo): ?string
    {
        $payload = is_array($memo->payload) ? $memo->payload : [];
        $schema = is_array($memo->fields_schema_snapshot) ? $memo->fields_schema_snapshot : [];

        foreach ($schema as $field) {
            if (! is_array($field)) {
                continue;
            }
            $key = $field['field'] ?? '';
            if (! is_string($key) || $key === '') {
                continue;
            }
            $enabled = ! array_key_exists('enabled', $field) || filter_var($field['enabled'], FILTER_VALIDATE_BOOLEAN);
            if (! $enabled) {
                continue;
            }
            $display = strtolower((string) ($field['display'] ?? ''));
            $keyLower = strtolower($key);
            if ($keyLower === 'subject' || str_contains($display, 'subject')) {
                $val = trim((string) ($payload[$key] ?? ''));

                return $val !== '' ? $key : null;
            }
        }

        foreach (['subject', 'memo_subject', 'title'] as $fallbackKey) {
            if (trim((string) ($payload[$fallbackKey] ?? '')) !== '') {
                return $fallbackKey;
            }
        }

        return null;
    }

    /**
     * Centered heading for other-memo PDFs.
     */
    public static function otherMemoPdfHeading(): string
    {
        return 'INTEROFFICE MEMORANDUM';
    }

    /**
     * Subject line text for other-memo PDFs (from payload schema).
     */
    public static function otherMemoSubjectText(OtherMemo $memo): string
    {
        $key = self::otherMemoSubjectFieldKey($memo);
        if ($key !== null) {
            $val = trim((string) (($memo->payload ?? [])[$key] ?? ''));
            if ($val !== '') {
                return $val;
            }
        }

        $fallback = trim((string) ($memo->memo_type_name_snapshot ?? ''));

        return $fallback !== '' ? $fallback : 'N/A';
    }

    /**
     * @deprecated Use otherMemoPdfHeading() and otherMemoSubjectText() instead.
     */
    public static function otherMemoDocumentTitle(OtherMemo $memo): string
    {
        return self::otherMemoSubjectText($memo);
    }

    /**
     * When no approver is placed in To or From, assign by approval order:
     * first → from, last → to, middle → through.
     *
     * @param array<int, array<string, mixed>> $approvers
     * @return array<int, array<string, mixed>>
     */
    public static function applyOtherMemoDefaultSections(array $approvers): array
    {
        if ($approvers === []) {
            return [];
        }

        $hasTo = false;
        $hasFrom = false;
        foreach ($approvers as $row) {
            if (! is_array($row)) {
                continue;
            }
            $section = strtolower(trim((string) ($row['memo_section'] ?? 'through')));
            if ($section === 'to') {
                $hasTo = true;
            }
            if ($section === 'from') {
                $hasFrom = true;
            }
        }

        if ($hasTo || $hasFrom) {
            return $approvers;
        }

        $sorted = $approvers;
        usort(
            $sorted,
            fn (array $a, array $b): int => ((int) ($a['sequence'] ?? 0)) <=> ((int) ($b['sequence'] ?? 0))
        );

        $count = count($sorted);
        foreach ($sorted as $i => &$row) {
            if (! is_array($row)) {
                continue;
            }
            if ($count === 1) {
                $row['memo_section'] = 'to';
            } elseif ($i === 0) {
                $row['memo_section'] = 'from';
            } elseif ($i === $count - 1) {
                $row['memo_section'] = 'to';
            } else {
                $row['memo_section'] = 'through';
            }
        }
        unset($row);

        return $sorted;
    }

    /**
     * Group other-memo approvers by To / Through / From (creator picks section per step).
     *
     * @return array{to: array<int, array<string, mixed>>, through: array<int, array<string, mixed>>, from: array<int, array<string, mixed>>}
     */
    public static function organizeOtherMemoApproversBySection(OtherMemo $memo): array
    {
        $sections = ['to' => [], 'through' => [], 'from' => []];
        $config = $memo->approvers_config;
        if (! is_array($config) || $config === []) {
            return $sections;
        }

        $config = self::applyOtherMemoDefaultSections($config);

        $staffIds = collect($config)
            ->pluck('staff_id')
            ->map(fn ($v) => (int) $v)
            ->filter(fn ($v) => $v > 0)
            ->unique()
            ->values()
            ->all();

        $staffById = $staffIds === []
            ? collect()
            : Staff::query()->whereIn('staff_id', $staffIds)->get()->keyBy('staff_id');

        foreach ($config as $row) {
            if (! is_array($row)) {
                continue;
            }
            $sid = (int) ($row['staff_id'] ?? 0);
            if ($sid <= 0) {
                continue;
            }
            $staff = $staffById->get($sid);
            if (! $staff) {
                continue;
            }
            $section = strtolower(trim((string) ($row['memo_section'] ?? 'through')));
            if (! isset($sections[$section])) {
                $section = 'through';
            }
            $name = trim(
                ($staff->title ? $staff->title.' ' : '')
                .$staff->fname.' '
                .$staff->lname
                .($staff->oname ? ' '.$staff->oname : '')
            );
            $sections[$section][] = [
                'sequence' => (int) ($row['sequence'] ?? 0),
                'staff_id' => $sid,
                'role' => trim((string) ($row['role_label'] ?? '')) !== '' ? (string) $row['role_label'] : 'Approver',
                'staff' => [
                    'staff_id' => $sid,
                    'name' => $name,
                    'title' => $staff->title,
                    'fname' => $staff->fname,
                    'lname' => $staff->lname,
                    'oname' => $staff->oname,
                    'work_email' => $staff->work_email,
                    'signature' => $staff->signature,
                ],
            ];
        }

        foreach (array_keys($sections) as $key) {
            usort(
                $sections[$key],
                function (array $a, array $b) use ($key): int {
                    $cmp = ((int) ($a['sequence'] ?? 0)) <=> ((int) ($b['sequence'] ?? 0));

                    // Printed header: To block lists highest step first (final approver on top).
                    return $key === 'to' ? -$cmp : $cmp;
                }
            );
        }

        return $sections;
    }

    /**
     * Approval step status for compliance maps (draft = all pending).
     */
    public static function otherMemoApproverStepStatus(OtherMemo $memo, int $sequence): string
    {
        if ($memo->overall_status === OtherMemo::STATUS_APPROVED) {
            return 'approved';
        }
        if (in_array($memo->overall_status, [OtherMemo::STATUS_DRAFT, OtherMemo::STATUS_CANCELLED], true)) {
            return 'pending';
        }
        if ($memo->overall_status === OtherMemo::STATUS_RETURNED) {
            $returnedAt = (int) ($memo->returned_at_sequence ?? 0);
            if ($returnedAt > 0 && $sequence >= $returnedAt) {
                return 'pending';
            }
        }
        $active = (int) ($memo->active_sequence ?? 0);
        if ($active <= 0) {
            return 'pending';
        }
        if ($sequence < $active) {
            return 'approved';
        }
        if ($sequence === $active) {
            return 'current';
        }

        return 'waiting';
    }

    /**
     * Body field content for other-memo PDF (no field label — text only, below subject).
     */
    public static function renderOtherMemoPdfBodyField(string $fieldType, mixed $value): void
    {
        if ($value === null || $value === '') {
            return;
        }

        if ($fieldType === 'text_summernote') {
            $html = self::sanitizeRichTextForMpdf(is_string($value) ? $value : '');
            if ($html === '') {
                return;
            }
            echo '<div class="memo-body-block rich-text-content html-content">'.$html.'</div>';

            return;
        }

        if ($fieldType === 'textarea') {
            $text = is_scalar($value) ? (string) $value : '';
            if (trim($text) === '') {
                return;
            }
            echo '<div class="memo-body-block rich-text-content">';
            echo nl2br(htmlspecialchars($text, ENT_QUOTES, 'UTF-8'));
            echo '</div>';

            return;
        }

        $text = is_scalar($value) ? (string) $value : json_encode($value);
        if (trim($text) === '') {
            return;
        }
        echo '<p class="memo-body-block">'.htmlspecialchars($text, ENT_QUOTES, 'UTF-8').'</p>';
    }

    /**
     * CC block after memo body on other-memo PDFs.
     *
     * @param  array<string, mixed>|null  $ccConfig
     */
    public static function renderOtherMemoPdfCc(?array $ccConfig): void
    {
        if (! \App\Support\OtherMemoCc::hasCcForPdf($ccConfig)) {
            return;
        }

        $mode = (string) ($ccConfig['mode'] ?? '');
        echo '<div class="memo-cc-block" style="margin-top: 18px;">';
        echo '<div class="section-label" style="margin-bottom: 6px;">Cc:</div>';

        if ($mode === 'all') {
            $heading = trim((string) ($ccConfig['all_staff_heading'] ?? ''));
            $label = \App\Support\OtherMemoCc::labelOrDefault($ccConfig['all_staff_label'] ?? null);
            if ($heading !== '') {
                echo '<div class="memo-cc-line" style="font-size: 14px; color: #100f0f; font-weight: bold; margin-bottom: 4px;">';
                echo htmlspecialchars($heading, ENT_QUOTES, 'UTF-8');
                echo '</div>';
            }
            echo '<div class="memo-cc-line" style="font-size: 14px; color: #100f0f; font-weight: bold;">';
            echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8');
            echo '</div>';
        } elseif ($mode === 'specific' && is_array($ccConfig['staff'] ?? null)) {
            foreach ($ccConfig['staff'] as $row) {
                if (! is_array($row)) {
                    continue;
                }
                $name = trim((string) ($row['name'] ?? ''));
                $role = trim((string) ($row['role_label'] ?? ''));
                if ($name === '') {
                    continue;
                }
                $line = $role !== '' ? $name.' ('.$role.')' : $name;
                echo '<div class="memo-cc-line" style="font-size: 14px; color: #100f0f; margin-bottom: 3px;">';
                echo htmlspecialchars($line, ENT_QUOTES, 'UTF-8');
                echo '</div>';
            }
        }

        echo '</div>';
    }

    /**
     * Approver name + role for other-memo PDF header rows.
     */
    public static function renderOtherMemoApproverInfo(array $approver, OtherMemo $memo, string $section): void
    {
        $staff = $approver['staff'] ?? [];
        $name = trim((string) ($staff['name'] ?? ''));
        if ($name === '' && is_array($staff)) {
            $name = trim(
                (($staff['title'] ?? '') ? $staff['title'].' ' : '')
                .($staff['fname'] ?? '').' '
                .($staff['lname'] ?? '')
                .(isset($staff['oname']) ? ' '.$staff['oname'] : '')
            );
        }
        echo '<div class="approver-name">'.htmlspecialchars($name !== '' ? $name : 'N/A', ENT_QUOTES, 'UTF-8').'</div>';
        echo '<div class="approver-title">'.htmlspecialchars((string) ($approver['role'] ?? ''), ENT_QUOTES, 'UTF-8').'</div>';

        if ($section === 'from' && $memo->relationLoaded('division') && $memo->division) {
            $divName = trim((string) ($memo->division->division_name ?? ''));
            if ($divName !== '') {
                echo '<div class="approver-title">'.htmlspecialchars($divName, ENT_QUOTES, 'UTF-8').'</div>';
            }
        }
    }

    /**
     * Signed-by, timestamp, and verify hash for an other-memo approver step.
     *
     * @param \Illuminate\Support\Collection<int, \App\Models\OtherMemoApprovalTrail>|array<int, \App\Models\OtherMemoApprovalTrail> $approvalTrails
     */
    public static function renderOtherMemoSignature(array $approver, $approvalTrails, OtherMemo $memo): void
    {
        self::renderSignature($approver, (int) ($approver['sequence'] ?? 0), $approvalTrails, $memo);
    }

    /**
     * CSS fragment for memo PDF templates (tighter margins, field rows, section flow).
     */
    public static function memoPdfLayoutStyles(): string
    {
        $path = resource_path('views/partials/memo-pdf-layout-styles.php');

        return is_file($path) ? (string) file_get_contents($path) : '';
    }

    /**
     * Plain-text memo field (e.g. Subject) — label and value on one row to avoid orphaned labels in mPDF.
     */
    public static function renderMemoPdfPlainField(string $label, ?string $value, string $valueClass = ''): void
    {
        $valueClassAttr = $valueClass !== '' ? ' class="' . htmlspecialchars($valueClass, ENT_QUOTES, 'UTF-8') . '"' : '';
        echo '<table class="memo-field-table"><tr class="memo-field-row">';
        echo '<td class="memo-field-label"><strong class="section-label">' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</strong></td>';
        echo '<td class="memo-field-body"' . $valueClassAttr . '>' . htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8') . '</td>';
        echo '</tr></table>';
    }

    /**
     * Subject row for other-memo PDFs — bold + underlined (mPDF ignores class underline on table cells).
     */
    public static function renderOtherMemoPdfSubject(?string $subjectText): void
    {
        $text = trim((string) ($subjectText ?? ''));
        if ($text === '') {
            return;
        }
        $escaped = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
        echo '<table class="memo-field-table"><tr class="memo-field-row">';
        echo '<td class="memo-field-label"><strong class="section-label">Subject:</strong></td>';
        echo '<td class="memo-field-body subject-text">';
        echo '<strong style="font-weight:bold;font-style:normal;color:#100f0f;">';
        echo '<u style="text-decoration:underline;">' . $escaped . '</u>';
        echo '</strong></td></tr></table>';
    }

    /**
     * Rich-text memo field (e.g. Background, Justification) — single table row so content flows on the same page as the label when space allows.
     */
    public static function renderMemoPdfRichField(string $label, ?string $html): void
    {
        echo '<table class="memo-field-table"><tr class="memo-field-row">';
        echo '<td class="memo-field-label"><strong class="section-label">' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</strong></td>';
        echo '<td class="memo-field-body justify-text">' . self::sanitizeRichTextForMpdf($html) . '</td>';
        echo '</tr></table>';
    }

    /**
     * Normalize Summernote / rich HTML for mPDF and browser views.
     *
     * Strips float/clear/absolute positioning, unsupported properties (e.g. text-wrap-mode),
     * rgba backgrounds on spans, and Summernote float classes. Forces images to block layout
     * with max-width so JPEG/PNG embeds render reliably instead of throwing layout errors.
     * Output is wrapped in a div with classes "rich-text-content" and "html-content".
     */
    public static function sanitizeRichTextForMpdf(?string $html): string
    {
        $html = self::trimRichTextInput($html);
        if ($html === '') {
            return '';
        }

        $decoded = html_entity_decode($html, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        libxml_use_internal_errors(true);
        $dom = new \DOMDocument('1.0', 'UTF-8');
        $wrapper = '<div id="mpdf-rich-root">' . $decoded . '</div>';
        // Standard HTML parse (wraps in html/body); more reliable than NOIMPLIED for Summernote fragments
        $loaded = @$dom->loadHTML('<?xml encoding="UTF-8">' . $wrapper);
        libxml_clear_errors();

        if (!$loaded) {
            return self::sanitizeRichTextForMpdfFallback($decoded);
        }

        $root = $dom->getElementById('mpdf-rich-root');
        if (!$root) {
            return self::sanitizeRichTextForMpdfFallback($decoded);
        }

        $xpath = new \DOMXPath($dom);

        foreach ($xpath->query('//comment()') as $comment) {
            if ($comment->parentNode) {
                $comment->parentNode->removeChild($comment);
            }
        }

        self::trimDomDocumentEdges($root);

        foreach ($xpath->query('//*[@class]') as $el) {
            /** @var \DOMElement $el */
            $class = $el->getAttribute('class');
            if ($class === '') {
                continue;
            }
            $parts = preg_split('/\s+/', trim($class), -1, PREG_SPLIT_NO_EMPTY);
            if (!$parts) {
                $el->removeAttribute('class');
                continue;
            }
            $filtered = array_values(array_filter($parts, function ($c) {
                return stripos($c, 'note-float') === false
                    && stripos($c, 'note-image') === false;
            }));
            if (count($filtered) === 0) {
                $el->removeAttribute('class');
            } else {
                $el->setAttribute('class', implode(' ', $filtered));
            }
        }

        foreach ($xpath->query('//*[@style]') as $el) {
            /** @var \DOMElement $el */
            $style = $el->getAttribute('style');
            $style = preg_replace('/\b(float|clear|text-wrap-mode)\s*:\s*[^;]+;?/i', '', $style);
            $style = preg_replace('/\bposition\s*:\s*(absolute|fixed|sticky)\s*;?/i', '', $style);
            $style = preg_replace('/\b(z-index|transform)\s*:\s*[^;]+;?/i', '', $style);
            $style = preg_replace('/\bbackground(?:-color)?\s*:\s*rgba\([^)]+\)\s*;?/i', '', $style);
            $style = trim(preg_replace('/\s*;\s*/', ';', $style), " ;\t\n\r\0\x0B");
            if ($style === '') {
                $el->removeAttribute('style');
            } else {
                $el->setAttribute('style', $style);
            }
        }

        foreach ($xpath->query('//img') as $img) {
            /** @var \DOMElement $img */
            $img->removeAttribute('class');
            $style = $img->getAttribute('style');
            $style = preg_replace('/\b(float|clear|vertical-align)\s*:\s*[^;]+;?/i', '', $style);
            $style = preg_replace('/\bwidth\s*:\s*[^;]+;?/i', '', $style);
            $style = preg_replace('/\bheight\s*:\s*[^;]+;?/i', '', $style);
            $style = trim($style . ' max-width:100%; height:auto; display:block; margin:8px 0;');
            $style = preg_replace('/\s*;\s*/', '; ', trim($style));
            $img->setAttribute('style', $style);

            $src = $img->getAttribute('src');
            if ($src !== '' && !preg_match('#^https?://#i', $src)) {
                $base = rtrim((string) (config('app.url') ?: ''), '/');
                if ($base !== '') {
                    if (str_starts_with($src, '//')) {
                        $img->setAttribute('src', (str_starts_with($base, 'https') ? 'https:' : 'http:') . $src);
                    } elseif (str_starts_with($src, '/')) {
                        $img->setAttribute('src', $base . $src);
                    } else {
                        $img->setAttribute('src', $base . '/' . ltrim($src, '/'));
                    }
                }
            }
        }

        $inner = '';
        foreach ($root->childNodes as $child) {
            $inner .= $dom->saveHTML($child);
        }
        $inner = Str::trim($inner);
        if ($inner === '') {
            return '';
        }

        return '<div class="rich-text-content html-content" style="margin:8px 0;text-align:left;overflow:visible;">' . $inner . '</div>';
    }

    /**
     * Remove leading/trailing whitespace-only text nodes and empty p/div blocks (e.g. trailing &lt;p&gt;&lt;br&gt;&lt;/p&gt;).
     */
    private static function trimDomDocumentEdges(\DOMElement $root): void
    {
        self::trimDomLeadingEdge($root);
        self::trimDomTrailingEdge($root);
    }

    private static function trimDomLeadingEdge(\DOMElement $parent): void
    {
        while ($parent->firstChild) {
            $c = $parent->firstChild;
            if ($c->nodeType === XML_TEXT_NODE) {
                $text = $c->textContent;
                $trimmedLeft = preg_replace('/^\s+/u', '', $text);
                if ($trimmedLeft === '') {
                    $parent->removeChild($c);
                    continue;
                }
                if ($trimmedLeft !== $text) {
                    $c->textContent = $trimmedLeft;
                }
                return;
            }
            if ($c->nodeType === XML_ELEMENT_NODE && self::isEmptyRichBlock($c)) {
                $parent->removeChild($c);
                continue;
            }
            return;
        }
    }

    private static function trimDomTrailingEdge(\DOMElement $parent): void
    {
        while ($parent->lastChild) {
            $c = $parent->lastChild;
            if ($c->nodeType === XML_TEXT_NODE) {
                $text = $c->textContent;
                $trimmedRight = preg_replace('/\s+$/u', '', $text);
                if ($trimmedRight === '') {
                    $parent->removeChild($c);
                    continue;
                }
                if ($trimmedRight !== $text) {
                    $c->textContent = $trimmedRight;
                }
                return;
            }
            if ($c->nodeType === XML_ELEMENT_NODE && self::isEmptyRichBlock($c)) {
                $parent->removeChild($c);
                continue;
            }
            return;
        }
    }

    /**
     * True for p/div that only contain whitespace, &nbsp;, and/or br (common paste cruft).
     */
    private static function isEmptyRichBlock(\DOMNode $node): bool
    {
        if ($node->nodeType !== XML_ELEMENT_NODE) {
            return false;
        }
        /** @var \DOMElement $el */
        $el = $node;
        $tag = strtolower($el->tagName);
        if (!in_array($tag, ['p', 'div'], true)) {
            return false;
        }
        foreach ($el->childNodes as $child) {
            if ($child->nodeType === XML_TEXT_NODE) {
                $t = preg_replace('/\x{00A0}/u', ' ', $child->textContent);
                if (Str::trim($t) !== '') {
                    return false;
                }
                continue;
            }
            if ($child->nodeType === XML_ELEMENT_NODE) {
                $name = strtolower($child->nodeName);
                if ($name === 'br') {
                    continue;
                }
                return false;
            }
        }

        return true;
    }

    /**
     * Regex-based fallback when DOM parsing fails (malformed markup).
     */
    private static function sanitizeRichTextForMpdfFallback(string $html): string
    {
        $html = Str::trim($html);
        if ($html === '') {
            return '';
        }
        $out = preg_replace('/<!--.*?-->/s', '', $html);
        $out = preg_replace('/\bclass\s*=\s*"(?:[^"]*\bnote-float[^"]*)"/i', '', $out);
        $out = preg_replace('/\bclass\s*=\s*\'(?:[^\']*\bnote-float[^\']*)\'/i', '', $out);
        $out = preg_replace_callback(
            '/<img\b[^>]*(?:\/)?>/i',
            function ($m) {
                $tag = $m[0];
                $tag = preg_replace('/\sstyle\s*=\s*"[^"]*"/i', '', $tag);
                $tag = preg_replace("/\sstyle\s*=\s*'[^']*'/i", '', $tag);
                $tag = preg_replace('/\sclass\s*=\s*"[^"]*"/i', '', $tag);
                if (preg_match('/\/\s*>$/', $tag)) {
                    return preg_replace('/\/\s*>$/', ' style="max-width:100%;height:auto;display:block;margin:8px 0;" />', $tag);
                }

                return preg_replace('/>$/', ' style="max-width:100%;height:auto;display:block;margin:8px 0;">', $tag);
            },
            $out
        );

        $out = Str::trim($out);
        if ($out === '') {
            return '';
        }

        return '<div class="rich-text-content html-content" style="margin:8px 0;text-align:left;">' . $out . '</div>';
    }

    /**
     * Resolve a stored activity/memo attachment to an absolute filesystem path.
     *
     * @param  array<string, mixed>  $attachment
     */
    public static function resolveAttachmentDiskPath(array $attachment): ?string
    {
        $relative = $attachment['path'] ?? ($attachment['file_path'] ?? '');
        if (! is_string($relative) || trim($relative) === '') {
            return null;
        }

        $relative = ltrim(str_replace('\\', '/', $relative), '/');
        $fullPath = storage_path('app/public/' . $relative);

        return is_file($fullPath) ? $fullPath : null;
    }

    /** Office document extensions converted to PDF before embedding in the annex. */
    private const OFFICE_DOCUMENT_EXTENSIONS = ['doc', 'docx', 'odt', 'rtf'];

    /**
     * Append an attachments appendix (index + embedded PDF/image/Word pages) to an mPDF instance.
     *
     * @param  array<int, array<string, mixed>>  $attachments
     */
    public static function appendAttachmentsAppendixToMpdf(\Mpdf\Mpdf $mpdf, array $attachments, string $appendixTitle = 'Appendix — Attachments'): void
    {
        $rows = [];
        foreach ($attachments as $attachment) {
            if (! is_array($attachment)) {
                continue;
            }
            $diskPath = self::resolveAttachmentDiskPath($attachment);
            if ($diskPath === null) {
                continue;
            }
            $originalName = (string) ($attachment['original_name'] ?? ($attachment['filename'] ?? ($attachment['name'] ?? basename($diskPath))));
            $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
            if ($ext === '') {
                $ext = strtolower(pathinfo($diskPath, PATHINFO_EXTENSION));
            }
            $rows[] = [
                'type' => (string) ($attachment['type'] ?? 'Document'),
                'name' => $originalName,
                'path' => $diskPath,
                'ext' => $ext,
            ];
        }

        if ($rows === []) {
            return;
        }

        self::beginAttachmentsAppendixLayout($mpdf);

        $indexHtml = '<div style="page-break-before: always;">'
            . '<p class="section-label" style="color:#006633;font-weight:bold;font-size:14px;margin:0 0 12px;">'
            . htmlspecialchars($appendixTitle, ENT_QUOTES, 'UTF-8')
            . '</p>'
            . '<table style="width:100%;border-collapse:collapse;font-size:11px;margin-bottom:16px;">'
            . '<thead><tr style="background:#f9fafb;">'
            . '<th style="padding:8px;text-align:left;width:8%;">#</th>'
            . '<th style="padding:8px;text-align:left;width:22%;">Type</th>'
            . '<th style="padding:8px;text-align:left;">File name</th>'
            . '</tr></thead><tbody>';

        foreach ($rows as $i => $row) {
            $indexHtml .= '<tr>'
                . '<td style="padding:8px;vertical-align:top;">' . ($i + 1) . '</td>'
                . '<td style="padding:8px;vertical-align:top;">' . htmlspecialchars($row['type'], ENT_QUOTES, 'UTF-8') . '</td>'
                . '<td style="padding:8px;vertical-align:top;">' . htmlspecialchars($row['name'], ENT_QUOTES, 'UTF-8') . '</td>'
                . '</tr>';
        }

        $indexHtml .= '</tbody></table></div>';

        try {
            $mpdf->WriteHTML($indexHtml, \Mpdf\HTMLParserMode::HTML_BODY);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('mPDF attachments appendix index failed', [
                'message' => $e->getMessage(),
            ]);

            return;
        }

        $imageExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

        foreach ($rows as $i => $row) {
            $label = 'Attachment ' . ($i + 1) . ': ' . $row['name'];
            $escapedLabel = htmlspecialchars($label, ENT_QUOTES, 'UTF-8');

            if ($row['ext'] === 'pdf') {
                self::importPdfPagesIntoMpdf($mpdf, $row['path'], $escapedLabel);

                continue;
            }

            if (in_array($row['ext'], self::OFFICE_DOCUMENT_EXTENSIONS, true)) {
                self::importOfficeDocumentIntoMpdf($mpdf, $row['path'], $escapedLabel);

                continue;
            }

            if (in_array($row['ext'], $imageExtensions, true)) {
                self::embedImagePageIntoMpdf($mpdf, $row['path'], $escapedLabel);
            }
        }
    }

    /**
     * Convert Word (and other office) attachments to PDF via LibreOffice, then embed pages.
     */
    private static function importOfficeDocumentIntoMpdf(\Mpdf\Mpdf $mpdf, string $documentPath, string $escapedLabel): void
    {
        $pdfPath = self::convertOfficeDocumentToPdf($documentPath);
        if ($pdfPath === null) {
            self::writeAttachmentImportFailurePage(
                $mpdf,
                $escapedLabel,
                'This Word document could not be converted for the PDF annex. Install LibreOffice (libreoffice or soffice) on the server.'
            );

            return;
        }

        try {
            self::importPdfPagesIntoMpdf($mpdf, $pdfPath, $escapedLabel);
        } finally {
            if (is_file($pdfPath) && str_starts_with($pdfPath, sys_get_temp_dir())) {
                @unlink($pdfPath);
            }
        }
    }

    /**
     * Convert .doc/.docx (and related) to PDF using headless LibreOffice.
     */
    private static function convertOfficeDocumentToPdf(string $sourcePath): ?string
    {
        if (! is_readable($sourcePath)) {
            return null;
        }

        $libreOffice = self::findExecutable(['libreoffice', 'soffice', 'loffice']);
        if ($libreOffice === null) {
            Log::warning('LibreOffice not found; cannot convert Word attachment for PDF annex');

            return null;
        }

        $outDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'apm_office_out_' . uniqid('', true);
        $profileDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'apm_lo_profile_' . uniqid('', true);

        if (! @mkdir($outDir, 0700, true) && ! is_dir($outDir)) {
            return null;
        }
        if (! @mkdir($profileDir, 0700, true) && ! is_dir($profileDir)) {
            self::removeDirectoryRecursive($outDir);

            return null;
        }

        $profileUri = 'file://' . str_replace(' ', '%20', str_replace('\\', '/', $profileDir));

        try {
            $result = Process::timeout(180)->run([
                $libreOffice,
                '--headless',
                '--nologo',
                '--nofirststartwizard',
                '-env:UserInstallation=' . $profileUri,
                '--convert-to',
                'pdf',
                '--outdir',
                $outDir,
                $sourcePath,
            ]);
        } catch (\Throwable $e) {
            Log::warning('LibreOffice convert process failed', [
                'path' => $sourcePath,
                'message' => $e->getMessage(),
            ]);
            self::removeDirectoryRecursive($outDir);
            self::removeDirectoryRecursive($profileDir);

            return null;
        }

        if (! $result->successful()) {
            Log::warning('LibreOffice convert failed', [
                'path' => $sourcePath,
                'stderr' => $result->errorOutput(),
            ]);
            self::removeDirectoryRecursive($outDir);
            self::removeDirectoryRecursive($profileDir);

            return null;
        }

        $expectedBase = pathinfo($sourcePath, PATHINFO_FILENAME);
        $converted = $outDir . DIRECTORY_SEPARATOR . $expectedBase . '.pdf';
        if (! is_file($converted)) {
            $matches = glob($outDir . DIRECTORY_SEPARATOR . '*.pdf') ?: [];
            $converted = $matches[0] ?? '';
        }

        self::removeDirectoryRecursive($profileDir);

        if ($converted === '' || ! is_file($converted)) {
            self::removeDirectoryRecursive($outDir);

            return null;
        }

        $tmpPdf = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'apm_word_' . uniqid('', true) . '.pdf';
        $copied = @copy($converted, $tmpPdf);
        self::removeDirectoryRecursive($outDir);

        return $copied && is_file($tmpPdf) ? $tmpPdf : null;
    }

    private static function removeDirectoryRecursive(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }

        $items = scandir($dir);
        if ($items === false) {
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir . DIRECTORY_SEPARATOR . $item;
            if (is_dir($path)) {
                self::removeDirectoryRecursive($path);
            } else {
                @unlink($path);
            }
        }

        @rmdir($dir);
    }

    private static function importPdfPagesIntoMpdf(\Mpdf\Mpdf $mpdf, string $pdfPath, string $escapedLabel): void
    {
        if (self::tryImportPdfPagesViaMpdf($mpdf, $pdfPath)) {
            return;
        }

        $republished = self::republishPdfViaGhostscript($pdfPath);
        if ($republished !== null) {
            try {
                if (self::tryImportPdfPagesViaMpdf($mpdf, $republished)) {
                    return;
                }
            } finally {
                @unlink($republished);
            }
        }

        if (self::tryImportPdfPagesAsRasterImages($mpdf, $pdfPath, $escapedLabel)) {
            return;
        }

        self::writeAttachmentImportFailurePage($mpdf, $escapedLabel);
    }

    /**
     * Import attachment PDF pages via mPDF/FPDI (works for most vector PDFs).
     */
    private static function tryImportPdfPagesViaMpdf(\Mpdf\Mpdf $mpdf, string $pdfPath): bool
    {
        try {
            $pageCount = $mpdf->SetSourceFile($pdfPath);
        } catch (\Throwable $e) {
            Log::warning('mPDF could not import attachment PDF', [
                'path' => $pdfPath,
                'message' => $e->getMessage(),
            ]);

            return false;
        }

        if ($pageCount < 1) {
            return false;
        }

        $imported = 0;
        for ($page = 1; $page <= $pageCount; $page++) {
            try {
                $tplId = $mpdf->ImportPage($page);
                $size = $mpdf->getTemplateSize($tplId);
                $width = is_array($size) ? ($size['width'] ?? null) : null;
                $height = is_array($size) ? ($size['height'] ?? null) : null;
                $mpdf->AddPage();
                $mpdf->UseTemplate($tplId, 0, 0, $width, $height);
                $imported++;
            } catch (\Throwable $e) {
                Log::warning('mPDF could not import attachment PDF page', [
                    'path' => $pdfPath,
                    'page' => $page,
                    'message' => $e->getMessage(),
                ]);
            }
        }

        return $imported > 0;
    }

    /**
     * Rasterize scanned/image PDFs and embed each page as a PNG (Imagick, Ghostscript, or pdftoppm).
     */
    private static function tryImportPdfPagesAsRasterImages(\Mpdf\Mpdf $mpdf, string $pdfPath, string $escapedLabel): bool
    {
        $pngPaths = self::rasterizePdfToPngPaths($pdfPath);
        if ($pngPaths === []) {
            return false;
        }

        try {
            foreach ($pngPaths as $pngPath) {
                self::embedImagePageIntoMpdf($mpdf, $pngPath, $escapedLabel);
            }
        } finally {
            foreach ($pngPaths as $pngPath) {
                if (is_string($pngPath) && is_file($pngPath) && str_starts_with($pngPath, sys_get_temp_dir())) {
                    @unlink($pngPath);
                }
            }
        }

        return true;
    }

    /**
     * @return list<string>
     */
    private static function rasterizePdfToPngPaths(string $pdfPath): array
    {
        if (! is_readable($pdfPath)) {
            return [];
        }

        $paths = self::rasterizePdfWithImagick($pdfPath);
        if ($paths !== []) {
            return $paths;
        }

        $paths = self::rasterizePdfWithGhostscript($pdfPath);
        if ($paths !== []) {
            return $paths;
        }

        return self::rasterizePdfWithPdftoppm($pdfPath);
    }

    /**
     * @return list<string>
     */
    private static function rasterizePdfWithImagick(string $pdfPath): array
    {
        if (! extension_loaded('imagick') || ! class_exists(\Imagick::class)) {
            return [];
        }

        try {
            $imagick = new \Imagick();
            $imagick->setResolution(150, 150);
            $imagick->readImage($pdfPath);

            $paths = [];
            foreach ($imagick as $page) {
                $page->setImageFormat('png');
                $page->setImageAlphaChannel(\Imagick::ALPHACHANNEL_REMOVE);
                $page->setBackgroundColor('white');
                $tmp = tempnam(sys_get_temp_dir(), 'apm_pdf_img_');
                if ($tmp === false) {
                    continue;
                }
                $pngPath = $tmp . '.png';
                @unlink($tmp);
                $page->writeImage($pngPath);
                if (is_file($pngPath)) {
                    $paths[] = $pngPath;
                }
            }

            $imagick->clear();
            $imagick->destroy();

            return $paths;
        } catch (\Throwable $e) {
            Log::warning('Imagick could not rasterize attachment PDF', [
                'path' => $pdfPath,
                'message' => $e->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * @return list<string>
     */
    private static function rasterizePdfWithGhostscript(string $pdfPath): array
    {
        $gs = self::findExecutable(['gs', 'gswin64c', 'gswin32c']);
        if ($gs === null) {
            return [];
        }

        $base = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'apm_attach_' . uniqid('', true);
        $pattern = $base . '_%d.png';

        try {
            $result = Process::timeout(180)->run([
                $gs,
                '-dSAFER',
                '-dBATCH',
                '-dNOPAUSE',
                '-sDEVICE=png16m',
                '-r150',
                '-dTextAlphaBits=4',
                '-dGraphicsAlphaBits=4',
                '-sOutputFile=' . $pattern,
                $pdfPath,
            ]);
        } catch (\Throwable $e) {
            Log::warning('Ghostscript rasterize process failed', [
                'path' => $pdfPath,
                'message' => $e->getMessage(),
            ]);

            return [];
        }

        if (! $result->successful()) {
            Log::warning('Ghostscript rasterize failed', [
                'path' => $pdfPath,
                'stderr' => $result->errorOutput(),
            ]);

            return [];
        }

        $paths = glob($base . '_*.png') ?: [];
        natsort($paths);

        return array_values($paths);
    }

    /**
     * @return list<string>
     */
    private static function rasterizePdfWithPdftoppm(string $pdfPath): array
    {
        $pdftoppm = self::findExecutable(['pdftoppm', 'pdftoppm.exe']);
        if ($pdftoppm === null) {
            return [];
        }

        $prefix = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'apm_attach_' . uniqid('', true) . '_page';

        try {
            $result = Process::timeout(180)->run([
                $pdftoppm,
                '-png',
                '-r',
                '150',
                $pdfPath,
                $prefix,
            ]);
        } catch (\Throwable $e) {
            Log::warning('pdftoppm rasterize process failed', [
                'path' => $pdfPath,
                'message' => $e->getMessage(),
            ]);

            return [];
        }

        if (! $result->successful()) {
            Log::warning('pdftoppm rasterize failed', [
                'path' => $pdfPath,
                'stderr' => $result->errorOutput(),
            ]);

            return [];
        }

        $paths = glob($prefix . '-*.png') ?: [];
        natsort($paths);

        return array_values($paths);
    }

    /**
     * Re-publish PDF to PDF 1.4 for FPDI when the original uses unsupported compression.
     */
    private static function republishPdfViaGhostscript(string $pdfPath): ?string
    {
        $gs = self::findExecutable(['gs', 'gswin64c', 'gswin32c']);
        if ($gs === null) {
            return null;
        }

        $out = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'apm_repub_' . uniqid('', true) . '.pdf';

        try {
            $result = Process::timeout(180)->run([
                $gs,
                '-dSAFER',
                '-dBATCH',
                '-dNOPAUSE',
                '-dCompatibilityLevel=1.4',
                '-dPDFSETTINGS=/default',
                '-sDEVICE=pdfwrite',
                '-sOutputFile=' . $out,
                $pdfPath,
            ]);
        } catch (\Throwable $e) {
            Log::warning('Ghostscript republish process failed', [
                'path' => $pdfPath,
                'message' => $e->getMessage(),
            ]);

            return null;
        }

        if (! $result->successful() || ! is_file($out)) {
            Log::warning('Ghostscript republish failed', [
                'path' => $pdfPath,
                'stderr' => $result->errorOutput(),
            ]);
            @unlink($out);

            return null;
        }

        return $out;
    }

    /**
     * @param  list<string>  $names
     */
    private static function findExecutable(array $names): ?string
    {
        foreach ($names as $name) {
            $which = PHP_OS_FAMILY === 'Windows' ? 'where' : 'which';
            try {
                $result = Process::timeout(5)->run([$which, $name]);
                if ($result->successful()) {
                    $lines = preg_split('/\R/', trim($result->output())) ?: [];
                    $first = trim((string) ($lines[0] ?? ''));
                    if ($first !== '' && is_file($first)) {
                        return $first;
                    }
                }
            } catch (\Throwable) {
                // try next
            }
        }

        foreach ($names as $name) {
            if (is_file($name)) {
                return $name;
            }
        }

        return null;
    }

    private static function embedImagePageIntoMpdf(\Mpdf\Mpdf $mpdf, string $imagePath, string $escapedLabel): void
    {
        $src = str_replace('\\', '/', $imagePath);
        $html = '<div style="page-break-before: always;">'
            . '<p style="font-size:12px;font-weight:bold;margin:0 0 8px;">' . $escapedLabel . '</p>'
            . '<img src="' . htmlspecialchars($src, ENT_QUOTES, 'UTF-8') . '" '
            . 'style="max-width:100%;height:auto;display:block;margin:0 auto;" />'
            . '</div>';

        try {
            $mpdf->WriteHTML($html, \Mpdf\HTMLParserMode::HTML_BODY);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('mPDF could not embed attachment image', [
                'path' => $imagePath,
                'message' => $e->getMessage(),
            ]);
            self::writeAttachmentImportFailurePage($mpdf, $escapedLabel);
        }
    }

    private static function writeAttachmentImportFailurePage(
        \Mpdf\Mpdf $mpdf,
        string $escapedLabel,
        string $message = 'This attachment could not be embedded in the PDF printout.'
    ): void {
        $html = '<div style="page-break-before: always;">'
            . '<p style="font-size:12px;font-weight:bold;margin:0 0 8px;">' . $escapedLabel . '</p>'
            . '<p style="font-size:10px;color:#64748b;">' . htmlspecialchars($message, ENT_QUOTES, 'UTF-8') . '</p>'
            . '</div>';

        try {
            $mpdf->WriteHTML($html, \Mpdf\HTMLParserMode::HTML_BODY);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('mPDF attachment failure page failed', [
                'message' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Strip memo branding header/footer and tighten margins for appendix pages only.
     */
    private static function beginAttachmentsAppendixLayout(\Mpdf\Mpdf $mpdf): void
    {
        $mpdf->SetHTMLHeader('');
        $mpdf->SetHTMLFooter('');
        $mpdf->SetHeader('');
        $mpdf->SetFooter('');
        $mpdf->SetMargins(8, 8, 8);
        $mpdf->SetAutoPageBreak(true, 8);
    }

}