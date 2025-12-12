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
