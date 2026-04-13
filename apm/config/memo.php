<?php

/**
 * Reference numbers for "other memos" (catalogue-driven types).
 *
 * Division-specific: {org}/{division_short}/{internal}/{ref_token}/{yy}/{seq}
 * Organisation-wide:  {org}/{internal}/{ref_token}/{yy}/{seq}
 */
return [
    'ref_org_path' => env('MEMO_REF_ORG_PATH', 'AU/CDC'),
    'internal_memo_segment' => env('MEMO_INTERNAL_SEGMENT', 'IM'),
];
