<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class TicketNumberGenerator
{
    public function next(): string
    {
        $year = (int) date('Y');

        return DB::transaction(function () use ($year) {
            $row = DB::table('helpdesk_ticket_sequences')->where('year', $year)->lockForUpdate()->first();
            if ($row === null) {
                DB::table('helpdesk_ticket_sequences')->insert([
                    'year' => $year,
                    'last_seq' => 1,
                ]);

                return sprintf('HD-%d-%06d', $year, 1);
            }
            $next = (int) $row->last_seq + 1;
            DB::table('helpdesk_ticket_sequences')->where('year', $year)->update(['last_seq' => $next]);

            return sprintf('HD-%d-%06d', $year, $next);
        });
    }
}
