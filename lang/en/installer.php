<?php

declare(strict_types=1);

return [
    'invalid_skip'     => 'Unknown installation step(s): :steps. Supported steps: :supported.',
    'skipped'          => 'Skipped [:step].',
    'planned'          => 'Would run [:step]: :description',
    'dry_run_complete' => 'Dry run complete. No files changed.',
    'config'           => 'Publish social-login configuration and apply the selected providers.',
    'migrations'       => 'Publish the social accounts migration if it is not already present.',
    'environment'      => 'Append missing credentials and callback URLs for selected providers to .env and .env.example.',
    'routes'           => 'Publish the social-login web routes.',
];
