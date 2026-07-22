<?php

namespace App\Services;

use App\Models\HelpdeskCategory;
use App\Models\HelpdeskTicket;
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
        return $this->build($category->name, $requesterName, $descriptionHtml);
    }

    public function generateForBusinessUnit(string $businessUnitName, string $requesterName, ?string $descriptionHtml): string
    {
        return $this->build($businessUnitName, $requesterName, $descriptionHtml);
    }

    /**
     * Rebuild subject from the ticket's current category (preferred) or business unit.
     * Used when classification changes so the old category/BU wording is not left behind.
     */
    public function regenerateForTicket(HelpdeskTicket $ticket): string
    {
        $requesterLabel = $ticket->is_anonymous
            ? 'Anonymous'
            : (string) ($ticket->requester_name ?: 'Requester');

        $ticket->loadMissing(['category', 'businessUnit']);

        if ($ticket->category) {
            return $this->generate($ticket->category, $requesterLabel, $ticket->description);
        }

        if ($ticket->businessUnit) {
            return $this->generateForBusinessUnit(
                (string) $ticket->businessUnit->name,
                $requesterLabel,
                $ticket->description,
            );
        }

        return (string) $ticket->subject;
    }

    private function build(string $scopeName, string $requesterName, ?string $descriptionHtml): string
    {
        $plain = HtmlSanitizer::toPlainText($descriptionHtml) ?? '';
        $plain = trim(preg_replace('/\s+/', ' ', $plain) ?? '');
        $snippet = Str::limit($plain, 56, '');
        $aiBit = trim($this->ai->hint($plain));

        $base = trim($scopeName.' — '.$requesterName);
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
