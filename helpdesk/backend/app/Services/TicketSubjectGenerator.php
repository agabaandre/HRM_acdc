<?php

namespace App\Services;

use App\Models\HelpdeskCategory;
use Illuminate\Support\Str;

class TicketSubjectGenerator
{
    public function __construct(
        private readonly TicketSubjectAiService $ai,
    ) {}

    /**
     * URS §8: subject from category + requester + short details (AI when configured).
     * Max 199 characters; not collected on the create form.
     */
    public function generate(HelpdeskCategory $category, string $requesterName, ?string $descriptionHtml): string
    {
        $plain = trim(preg_replace('/\s+/', ' ', strip_tags((string) $descriptionHtml)));
        $snippet = Str::limit($plain, 56, '');
        $aiBit = trim($this->ai->hint($plain));

        $base = trim($category->name.' — '.$requesterName);
        $subject = $base;
        if ($snippet !== '') {
            $subject .= ': '.$snippet;
        }
        if ($aiBit !== '' && ! str_contains(strtolower($subject), strtolower($aiBit))) {
            $subject .= ' · '.$aiBit;
        }

        return Str::limit(trim($subject), 199, '…');
    }
}
