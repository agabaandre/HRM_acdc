<?php
/**
 * API Documentation View
 * Displays the API documentation in a readable web format
 */
?>

<style>
    .api-documentation {
        max-width: 1200px;
        margin: 0 auto;
        padding: 2rem;
        font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
        line-height: 1.6;
        color: #333;
    }

    .api-documentation h1 {
        color: #348f41;
        border-bottom: 3px solid #348f41;
        padding-bottom: 0.5rem;
        margin-top: 2rem;
        margin-bottom: 1rem;
    }

    .api-documentation h2 {
        color: #2c7a2e;
        margin-top: 2rem;
        margin-bottom: 1rem;
        padding-left: 0.5rem;
        border-left: 4px solid #348f41;
    }

    .api-documentation h3 {
        color: #4a9d4f;
        margin-top: 1.5rem;
        margin-bottom: 0.75rem;
    }

    .api-documentation h4 {
        color: #5fb366;
        margin-top: 1.25rem;
        margin-bottom: 0.5rem;
    }

    .api-documentation code {
        background-color: #f4f4f4;
        padding: 0.2rem 0.4rem;
        border-radius: 3px;
        font-family: 'Courier New', monospace;
        font-size: 0.9em;
        color: #d63384;
    }

    .api-documentation pre {
        background-color: #f8f9fa;
        border: 1px solid #dee2e6;
        border-radius: 5px;
        padding: 1rem;
        overflow-x: auto;
        margin: 1rem 0;
    }

    .api-documentation pre code {
        background-color: transparent;
        padding: 0;
        color: #333;
        display: block;
    }

    .api-documentation table {
        width: 100%;
        border-collapse: collapse;
        margin: 1rem 0;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    }

    .api-documentation table th {
        background-color: #348f41;
        color: white;
        padding: 0.75rem;
        text-align: left;
        font-weight: 600;
    }

    .api-documentation table td {
        padding: 0.75rem;
        border-bottom: 1px solid #dee2e6;
    }

    .api-documentation table tr:hover {
        background-color: #f8f9fa;
    }

    .api-documentation blockquote {
        border-left: 4px solid #348f41;
        padding-left: 1rem;
        margin: 1rem 0;
        color: #666;
        font-style: italic;
    }

    .api-documentation a {
        color: #348f41;
        text-decoration: none;
    }

    .api-documentation a:hover {
        text-decoration: underline;
    }

    .api-documentation ul, .api-documentation ol {
        margin: 1rem 0;
        padding-left: 2rem;
    }

    .api-documentation li {
        margin: 0.5rem 0;
    }

    .api-documentation hr {
        border: none;
        border-top: 2px solid #dee2e6;
        margin: 2rem 0;
    }

    .api-documentation .badge {
        display: inline-block;
        padding: 0.25rem 0.5rem;
        border-radius: 3px;
        font-size: 0.85em;
        font-weight: 600;
        margin-left: 0.5rem;
    }

    .api-documentation .badge-success {
        background-color: #28a745;
        color: white;
    }

    .api-documentation .badge-info {
        background-color: #17a2b8;
        color: white;
    }

    .api-documentation .badge-warning {
        background-color: #ffc107;
        color: #333;
    }

    .api-documentation .endpoint-method {
        display: inline-block;
        padding: 0.25rem 0.75rem;
        border-radius: 3px;
        font-weight: 600;
        font-size: 0.85em;
        margin-right: 0.5rem;
    }

    .api-documentation .method-get {
        background-color: #61affe;
        color: white;
    }

    .api-documentation .method-post {
        background-color: #49cc90;
        color: white;
    }

    .api-documentation .method-put {
        background-color: #fca130;
        color: white;
    }

    .api-documentation .method-delete {
        background-color: #f93e3e;
        color: white;
    }

    .api-documentation .toc {
        background-color: #f8f9fa;
        border: 1px solid #dee2e6;
        border-radius: 5px;
        padding: 1.5rem;
        margin: 2rem 0;
    }

    .api-documentation .toc ul {
        list-style-type: none;
        padding-left: 0;
    }

    .api-documentation .toc li {
        margin: 0.5rem 0;
    }

    .api-documentation .toc a {
        color: #348f41;
        text-decoration: none;
    }

    .api-documentation .toc a:hover {
        text-decoration: underline;
    }

    @media (max-width: 768px) {
        .api-documentation {
            padding: 1rem;
        }

        .api-documentation table {
            font-size: 0.85em;
        }

        .api-documentation pre {
            font-size: 0.85em;
        }
    }
</style>

<div class="api-documentation">
    <?php echo $documentation_html; ?>
</div>

