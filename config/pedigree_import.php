<?php

/*
| Importing pedigrees from photos, scans and PDFs of pedigree sheets (App\Services\PedigreeImport). Each sheet is
| read by Claude, so every import costs a few cents: the limits below keep that in check.
*/

return [

    // Anthropic API key. Without one, the import page explains that importing is not available yet.
    'api_key' => env('ANTHROPIC_API_KEY'),

    'model' => env('PEDIGREE_IMPORT_MODEL', 'claude-opus-5-5'),

    // How hard the model thinks: low, medium, high, xhigh or max. Higher reads tricky sheets better and costs more.
    'effort' => env('PEDIGREE_IMPORT_EFFORT', 'medium'),

    // Sheets a free account can import in total. Premium accounts (and the free trial) aren't limited by this.
    'free_limit' => (int) env('PEDIGREE_IMPORT_FREE_LIMIT', 10),

    // Sheets any account can import per day, a guard against runaway costs
    'daily_limit' => (int) env('PEDIGREE_IMPORT_DAILY_LIMIT', 100),

    // Sheets read at the same time for one account; the rest wait their turn
    'parallel' => (int) env('PEDIGREE_IMPORT_PARALLEL', 2),

];
