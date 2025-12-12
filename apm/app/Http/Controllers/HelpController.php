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
     * Convert markdown to HTML
     */
    protected function markdownToHtml($markdown)
    {
        // Simple markdown to HTML converter
        // Split into lines for processing
        $lines = explode("\n", $markdown);
        $html = '';
        $inList = false;
        $inCodeBlock = false;
        $codeBlockContent = '';
        
        foreach ($lines as $line) {
            // Code blocks
            if (preg_match('/^```/', $line)) {
                if ($inCodeBlock) {
                    $html .= '<pre><code>' . htmlspecialchars($codeBlockContent) . '</code></pre>';
                    $codeBlockContent = '';
                    $inCodeBlock = false;
                } else {
                    $inCodeBlock = true;
                }
                continue;
            }
            
            if ($inCodeBlock) {
                $codeBlockContent .= $line . "\n";
                continue;
            }
            
            // Headers
            if (preg_match('/^#### (.*)$/', $line, $matches)) {
                if ($inList) {
                    $html .= '</ul>';
                    $inList = false;
                }
                $html .= '<h4>' . htmlspecialchars($matches[1]) . '</h4>';
                continue;
            }
            if (preg_match('/^### (.*)$/', $line, $matches)) {
                if ($inList) {
                    $html .= '</ul>';
                    $inList = false;
                }
                $html .= '<h3>' . htmlspecialchars($matches[1]) . '</h3>';
                continue;
            }
            if (preg_match('/^## (.*)$/', $line, $matches)) {
                if ($inList) {
                    $html .= '</ul>';
                    $inList = false;
                }
                $html .= '<h2>' . htmlspecialchars($matches[1]) . '</h2>';
                continue;
            }
            if (preg_match('/^# (.*)$/', $line, $matches)) {
                if ($inList) {
                    $html .= '</ul>';
                    $inList = false;
                }
                $html .= '<h1>' . htmlspecialchars($matches[1]) . '</h1>';
                continue;
            }
            
            // Horizontal rule
            if (preg_match('/^---$/', $line)) {
                if ($inList) {
                    $html .= '</ul>';
                    $inList = false;
                }
                $html .= '<hr>';
                continue;
            }
            
            // Lists
            if (preg_match('/^[-*] (.*)$/', $line, $matches)) {
                if (!$inList) {
                    $html .= '<ul>';
                    $inList = true;
                }
                $content = $matches[1];
                // Process inline formatting
                $content = preg_replace('/\*\*(.*?)\*\*/', '<strong>$1</strong>', $content);
                $content = preg_replace('/\*(.*?)\*/', '<em>$1</em>', $content);
                $content = preg_replace('/`([^`]+)`/', '<code>$1</code>', $content);
                $html .= '<li>' . $content . '</li>';
                continue;
            }
            
            // End list if needed
            if ($inList && trim($line) === '') {
                $html .= '</ul>';
                $inList = false;
                continue;
            }
            
            // Paragraphs
            if (trim($line) !== '') {
                if ($inList) {
                    $html .= '</ul>';
                    $inList = false;
                }
                $content = htmlspecialchars($line);
                // Process inline formatting
                $content = preg_replace('/\*\*(.*?)\*\*/', '<strong>$1</strong>', $content);
                $content = preg_replace('/\*(.*?)\*/', '<em>$1</em>', $content);
                $content = preg_replace('/`([^`]+)`/', '<code>$1</code>', $content);
                // Links
                $content = preg_replace('/\[([^\]]+)\]\(([^\)]+)\)/', '<a href="$2">$1</a>', $content);
                // Images (convert to placeholder)
                $content = preg_replace('/!\[([^\]]*)\]\(([^\)]+)\)/', '<div class="screenshot-placeholder"><div class="placeholder-content"><i class="fas fa-image"></i><p>Screenshot Placeholder</p><small>$1</small></div></div>', $content);
                $html .= '<p>' . $content . '</p>';
            }
        }
        
        // Close any open list
        if ($inList) {
            $html .= '</ul>';
        }
        
        return $html;
    }
}
