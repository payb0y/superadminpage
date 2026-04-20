<?php

return [
    'routes' => [
        ['name' => 'page#index', 'url' => '/', 'verb' => 'GET'],

        ['name' => 'dashboard#getData', 'url' => '/api/super/data', 'verb' => 'GET'],
        ['name' => 'dashboard#listOrgs', 'url' => '/api/super/orgs', 'verb' => 'GET'],
        ['name' => 'dashboard#getOrg', 'url' => '/api/super/orgs/{orgId}', 'verb' => 'GET'],
        ['name' => 'dashboard#getProjectTasks', 'url' => '/api/super/projects/{projectId}/tasks', 'verb' => 'GET'],
        ['name' => 'dashboard#listBackups', 'url' => '/api/super/backups', 'verb' => 'GET'],
        ['name' => 'dashboard#listAho', 'url' => '/api/super/aho', 'verb' => 'GET'],
        ['name' => 'dashboard#listSubscriptions', 'url' => '/api/super/subscriptions', 'verb' => 'GET'],
    ],
];
