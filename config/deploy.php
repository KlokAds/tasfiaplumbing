<?php

return [
    // Who may run System Update is controlled by the "system.update" permission (Team > Roles).

    'remote' => env('DEPLOY_REMOTE', 'origin'),
    'branch' => env('DEPLOY_BRANCH', 'main'),

    'git_binary' => env('DEPLOY_GIT_BINARY', 'git'),
    'php_binary' => env('DEPLOY_PHP_BINARY'),
    'composer_binary' => env('DEPLOY_COMPOSER_BINARY', 'composer'),
    'npm_binary' => env('DEPLOY_NPM_BINARY', 'npm'),

    // Most shared hosts have no Node. Build assets locally and commit public/build, or enable this.
    'build_assets' => (bool) env('DEPLOY_BUILD_ASSETS', false),
    'run_composer' => (bool) env('DEPLOY_RUN_COMPOSER', true),

    'step_timeout' => (int) env('DEPLOY_STEP_TIMEOUT', 600),

    // New database migrations run by themselves on the first visit after an update.
    'auto_migrate' => (bool) env('DEPLOY_AUTO_MIGRATE', true),
];
