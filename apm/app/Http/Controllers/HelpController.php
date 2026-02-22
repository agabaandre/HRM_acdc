<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;

class HelpController extends Controller
{
    /**
     * Display help index page
     */
    public function index()
    {
        return view('help.index');
    }

    /**
     * Display user guide
     */
    public function userGuide()
    {
        $guidePath = base_path('documentation/USER_GUIDE.md');
        
        if (!File::exists($guidePath)) {
            abort(404, 'User guide not found');
        }

        $content = File::get($guidePath);
        $html = $this->markdownToHtml($content);

        return view('help.guide', [
            'title' => 'User Guide',
            'content' => $html,
            'guideType' => 'user'
        ]);
    }

    /**
     * Display approvers guide
     */
    public function approversGuide()
    {
        $guidePath = base_path('documentation/APPROVERS_GUIDE.md');
        
        if (!File::exists($guidePath)) {
            abort(404, 'Approvers guide not found');
        }

        $content = File::get($guidePath);
        $html = $this->markdownToHtml($content);

        return view('help.guide', [
            'title' => 'Approvers Guide',
            'content' => $html,
            'guideType' => 'approver'
        ]);
    }

    /**
     * Display a documentation file
     */
    public function documentation($file = null)
    {
        // If no file specified, show README.md
        if (!$file) {
            $file = 'README.md';
        }

        // Security: Only allow .md files
        if (!preg_match('/^[a-zA-Z0-9_-]+\.md$/', $file)) {
            abort(404, 'Invalid file name');
        }

        // Map common documentation files
        $fileMap = [
            'README.md' => 'documentation/README.md',
            'API_DOCUMENTATION.md' => 'documentation/API_DOCUMENTATION.md',
            'USER_GUIDE.md' => 'documentation/USER_GUIDE.md',
            'APPROVERS_GUIDE.md' => 'documentation/APPROVERS_GUIDE.md',
            'DEPLOYMENT.md' => 'documentation/DEPLOYMENT.md',
            'QUEUE_SETUP_GUIDE.md' => 'documentation/QUEUE_SETUP_GUIDE.md',
            'QUEUE_TROUBLESHOOTING.md' => 'documentation/QUEUE_TROUBLESHOOTING.md',
            'CRON_SETUP.md' => 'documentation/CRON_SETUP.md',
            'APPROVAL_TRAIL_MANAGEMENT.md' => 'documentation/APPROVAL_TRAIL_MANAGEMENT.md',
            'APPROVAL_TRAIL_ARCHIVING.md' => 'documentation/APPROVAL_TRAIL_ARCHIVING.md',
            'DOCUMENT_NUMBERING_SYSTEM.md' => 'documentation/DOCUMENT_NUMBERING_SYSTEM.md',
            'DOCUMENT_NUMBER_MANAGEMENT.md' => 'documentation/DOCUMENT_NUMBER_MANAGEMENT.md',
            'CHANGE_TRACKING_FEASIBILITY.md' => 'documentation/CHANGE_TRACKING_FEASIBILITY.md',
            'DAILY_NOTIFICATIONS_SETUP.md' => 'documentation/DAILY_NOTIFICATIONS_SETUP.md',
            'SESSION_EXPIRY_SETUP.md' => 'documentation/SESSION_EXPIRY_SETUP.md',
            'SUPERVISOR_SETUP_DEMO.md' => 'documentation/SUPERVISOR_SETUP_DEMO.md',
            'SYSTEMD_QUEUE_GUIDE.md' => 'documentation/SYSTEMD_QUEUE_GUIDE.md',
            'SYNC_IMPROVEMENTS.md' => 'documentation/SYNC_IMPROVEMENTS.md',
            'SIGNATURE_VERIFICATION.md' => 'documentation/SIGNATURE_VERIFICATION.md',
            'SYSTEM_UPDATES.md' => 'documentation/SYSTEM_UPDATES.md',
        ];

        // Check if file is in the map
        if (isset($fileMap[$file])) {
            $filePath = base_path($fileMap[$file]);
        } else {
            // Try to find in documentation directory
            $filePath = base_path('documentation/' . $file);
        }
        
        if (!File::exists($filePath)) {
            abort(404, 'Documentation file not found');
        }

        // Security: Ensure file is within documentation directory
        $realPath = realpath($filePath);
        $docPath = realpath(base_path('documentation'));
        if (!$realPath || strpos($realPath, $docPath) !== 0) {
            abort(404, 'Access denied');
        }

        $content = File::get($filePath);
        $html = $this->markdownToHtml($content);
        $html = $this->rewriteDocumentationLinks($html);

        // Generate title from filename
        $title = str_replace(['.md', '_'], ['', ' '], $file);
        $title = ucwords($title);

        return view('help.guide', [
            'title' => $title,
            'content' => $html,
            'guideType' => 'documentation'
        ]);
    }

    /**
     * Rewrite relative .md links in documentation HTML to point to our /documentation/{file} routes.
     */
    protected function rewriteDocumentationLinks(string $html): string
    {
        $base = rtrim(url('/documentation'), '/');
        // href="./Something.md" or href="Something.md" (same-dir only; do not rewrite ../)
        $html = preg_replace_callback(
            '/<a\s+href="((?:\.\/)?([^"]*?\.md))"[^>]*>/i',
            function ($m) use ($base) {
                $raw = $m[2];
                if (strpos($raw, '..') !== false) {
                    return $m[0]; // leave parent/other relative links as-is
                }
                $file = basename($raw);
                if (preg_match('/^[a-zA-Z0-9_.-]+\.md$/', $file)) {
                    return '<a href="' . $base . '/' . $file . '" class="doc-link">';
                }
                return $m[0];
            },
            $html
        );
        return $html;
    }

    /**
     * Convert heading text to an id slug for anchor links (lowercase, hyphens).
     */
    protected function headingToId(string $text): string
    {
        $stripped = strip_tags($text);
        $slug = strtolower(trim(preg_replace('/[^a-z0-9]+/i', '-', $stripped), '-'));
        return $slug === '' ? 'section' : $slug;
    }

    /**
     * Convert markdown to HTML
     */
    protected function markdownToHtml($markdown)
    {
        $lines = explode("\n", $markdown);
        $html = '';
        $inList = false;
        $inOrderedList = false;
        $inCodeBlock = false;
        $codeBlockContent = '';
        $usedIds = [];

        $closeLists = function () use (&$html, &$inList, &$inOrderedList) {
            if ($inList) {
                $html .= '</ul>';
                $inList = false;
            }
            if ($inOrderedList) {
                $html .= '</ol>';
                $inOrderedList = false;
            }
        };

        $headingId = function (string $rawTitle) use (&$usedIds) {
            $base = $this->headingToId($rawTitle);
            $id = $base;
            $n = 1;
            while (isset($usedIds[$id])) {
                $id = $base . '-' . (++$n);
            }
            $usedIds[$id] = true;
            return $id;
        };

        $inlineAndLinks = function ($content) {
            $content = preg_replace('/\*\*(.*?)\*\*/', '<strong>$1</strong>', $content);
            $content = preg_replace('/\*(.*?)\*/', '<em>$1</em>', $content);
            $content = preg_replace('/`([^`]+)`/', '<code>$1</code>', $content);
            $content = preg_replace('/\[([^\]]+)\]\(([^\)]+)\)/', '<a href="$2">$1</a>', $content);
            return $content;
        };

        foreach ($lines as $line) {
            if (preg_match('/^```/', $line)) {
                if ($inCodeBlock) {
                    $html .= '<pre><code>' . htmlspecialchars($codeBlockContent) . '</code></pre>';
                    $codeBlockContent = '';
                    $inCodeBlock = false;
                } else {
                    $closeLists();
                    $inCodeBlock = true;
                }
                continue;
            }

            if ($inCodeBlock) {
                $codeBlockContent .= $line . "\n";
                continue;
            }

            if (preg_match('/^#### (.*)$/', $line, $matches)) {
                $closeLists();
                $id = $headingId($matches[1]);
                $html .= '<h4 id="' . htmlspecialchars($id) . '">' . htmlspecialchars($matches[1]) . '</h4>';
                continue;
            }
            if (preg_match('/^### (.*)$/', $line, $matches)) {
                $closeLists();
                $id = $headingId($matches[1]);
                $html .= '<h3 id="' . htmlspecialchars($id) . '">' . htmlspecialchars($matches[1]) . '</h3>';
                continue;
            }
            if (preg_match('/^## (.*)$/', $line, $matches)) {
                $closeLists();
                $id = $headingId($matches[1]);
                $html .= '<h2 id="' . htmlspecialchars($id) . '">' . htmlspecialchars($matches[1]) . '</h2>';
                continue;
            }
            if (preg_match('/^# (.*)$/', $line, $matches)) {
                $closeLists();
                $id = $headingId($matches[1]);
                $html .= '<h1 id="' . htmlspecialchars($id) . '">' . htmlspecialchars($matches[1]) . '</h1>';
                continue;
            }

            if (preg_match('/^---$/', $line)) {
                $closeLists();
                $html .= '<hr>';
                continue;
            }

            // Unordered list
            if (preg_match('/^[-*] (.*)$/', $line, $matches)) {
                if ($inOrderedList) {
                    $html .= '</ol>';
                    $inOrderedList = false;
                }
                if (!$inList) {
                    $html .= '<ul>';
                    $inList = true;
                }
                $html .= '<li>' . $inlineAndLinks($matches[1]) . '</li>';
                continue;
            }

            // Ordered list (e.g. "1. item" or "2. **Bold**")
            if (preg_match('/^\d+\. (.*)$/', $line, $matches)) {
                if ($inList) {
                    $html .= '</ul>';
                    $inList = false;
                }
                if (!$inOrderedList) {
                    $html .= '<ol>';
                    $inOrderedList = true;
                }
                $html .= '<li>' . $inlineAndLinks($matches[1]) . '</li>';
                continue;
            }

            if (trim($line) === '') {
                $closeLists();
                continue;
            }

            $closeLists();
            $content = htmlspecialchars($line);
            $content = preg_replace('/\*\*(.*?)\*\*/', '<strong>$1</strong>', $content);
            $content = preg_replace('/\*(.*?)\*/', '<em>$1</em>', $content);
            $content = preg_replace('/`([^`]+)`/', '<code>$1</code>', $content);
            $content = preg_replace('/\[([^\]]+)\]\(([^\)]+)\)/', '<a href="$2">$1</a>', $content);
            $content = preg_replace('/!\[([^\]]*)\]\(([^\)]+)\)/', '<div class="screenshot-placeholder"><div class="placeholder-content"><i class="fas fa-image"></i><p>Screenshot Placeholder</p><small>$1</small></div></div>', $content);
            $html .= '<p>' . $content . '</p>';
        }

        $closeLists();

        return $html;
    }
}
