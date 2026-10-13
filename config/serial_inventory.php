<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Serial inventory settings
    |--------------------------------------------------------------------------
    |
    | Only XLSX files are accepted in v1. Only column M (عامل اول) is ever
    | read; every other column is inert. Tapsi never consumes site serials.
    |
    */

    // Master rollout switch. While false, new tracked checkout fails closed.
    // Previously enabled orders remain recoverable through payment reconciliation.
    'enabled' => env('SERIAL_INVENTORY_ENABLED', false),

    'parser_version' => 'v1',

    'column_letter' => 'M',

    'header_row' => 1,

    'first_data_row' => 2,

    'expected_header' => 'عامل اول',

    'import_chunk_size' => 500,

    'max_import_rows' => 50000,

    'max_upload_mb' => 10,

    'max_expanded_bytes' => 100 * 1024 * 1024,

    'reserve_minutes' => 20,

    'queue' => 'serial-imports',

    // Run analyze/import inline in the web request instead of queueing.
    // Suitable for the usual small files; keep false for large files to
    // avoid HTTP/proxy timeouts (the queue path stays available).
    'sync' => env('SERIAL_IMPORT_SYNC', false),
];
