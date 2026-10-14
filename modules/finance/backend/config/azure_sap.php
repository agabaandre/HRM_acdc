<?php

/**
 * Azure Blob Storage for SAP dump files.
 * Prefer AZURE_SAP_CONNECTION_STRING; account + key are used as fallback.
 * DB settings (fin_settings) override env when present.
 */
return [
    'connection_string' => (string) env('AZURE_SAP_CONNECTION_STRING', ''),
    'account_name' => (string) env('AZURE_SAP_ACCOUNT_NAME', ''),
    'account_key' => (string) env('AZURE_SAP_ACCOUNT_KEY', ''),
    'container' => (string) env('AZURE_SAP_CONTAINER', 'sap'),
    'prefix' => (string) env('AZURE_SAP_PREFIX', ''),
    'endpoint_suffix' => (string) env('AZURE_SAP_ENDPOINT_SUFFIX', 'core.windows.net'),
    'api_version' => '2021-08-06',
    'timeout' => (int) env('AZURE_SAP_TIMEOUT', 60),
    /** Background poll interval (hours) — must match scheduler cadence. */
    'poll_hours' => (int) env('AZURE_SAP_POLL_HOURS', 4),
    /** Delete remote blob after a successful import. */
    'delete_after_fetch' => filter_var(env('AZURE_SAP_DELETE_AFTER_FETCH', true), FILTER_VALIDATE_BOOL),
];
