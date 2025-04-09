<?php declare(strict_types=1);

return [
    'token' => env('LOKALISE_API_TOKEN'),
    'project_id' => env('LOKALISE_PROJECT_ID'),
    'base_path' => base_path(),
    'skip_json_files' => true,
    'convert_keys' => false,
];
