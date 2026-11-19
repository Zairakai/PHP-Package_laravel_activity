<?php

declare(strict_types=1);

return [
    // Log name of the pivot entries (the log_name column of Spatie, which is indexed). It also narrows the
    // $role->activities()->related() query. Set it to null to use the default log name of Spatie.
    'log_name' => env('ACTIVITY_PIVOT_LOG_NAME', 'pivot'),

    // Columns of the pivot tables that are never written in the log: sensitive values that cannot be recreated.
    'hidden_columns' => [
        'password',
        'remember_token',
        'token',
        'api_token',
        'secret',
    ],

    // Also leave out the $hidden columns of the pivot model of the relation (->using(RoleUser::class)).
    'use_pivot_hidden' => env('ACTIVITY_PIVOT_USE_HIDDEN', true),
];
