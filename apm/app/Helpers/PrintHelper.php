<?php

namespace App\Helpers;

use App\Models\Staff;
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
        
        // Define the financial approver roles and their expected approval orders
        $financialRoles = [
            'Head of Division' => 1,      // Prepared by
            'Finance Officer' => 5,       // Endorsed by (SFO)
            'Director Finance' => 6,      // Endorsed by (Director Finance)
            'Deputy Director General' => 9 // Approved by
        ];
        
        foreach ($financialRoles as $role => $expectedOrder) {
            $approval = self::getLatestApprovalForOrder($activityApprovalTrails, $expectedOrder);
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
     * Sanitize textarea-like content for plain text/table cells in print templates.
     * This avoids CSS/script fragments leaking into PDF content.
     */
    public static function sanitizeTextareaPlainTextForPrint($value): string
    {
        $text = (string) ($value ?? '');
        if ($text === '') {
            return 'N/A';
        }

        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('#<(script|style|noscript)\b[^>]*>.*?</\1>#is', ' ', $text) ?? $text;
        $text = strip_tags($text);
        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;
        $text = trim($text);

        return $text !== '' ? $text : 'N/A';
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

}