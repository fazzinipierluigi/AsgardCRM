<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Middleware name
    |--------------------------------------------------------------------------
    |
    | The registration name of the middleware for the ACL
    |
    */
    'middleware' => 'acl',

    /*
    |--------------------------------------------------------------------------
    | Additional permission
    |--------------------------------------------------------------------------
    |
    | The list of additional permissions specified manually must be specified in
    | the following format:
    | ‘permission_key’ => 'Display name'
    |
    */
    'additional' => [

    ],

    /*
    |--------------------------------------------------------------------------
    | Permission from roles toggle
    |--------------------------------------------------------------------------
    |
    | This toggle specifies whether the package should automatically generate
    | a permission for each role.
    |
    */
    'role_user_creation' => true,

    /*
    |--------------------------------------------------------------------------
    | Clean toggle
    |--------------------------------------------------------------------------
    |
    | A toggle that enables or disables the automatic cleanup of permissions
    | that no longer exist.
    |
    */
    'clean_permission' => true,

    /*
    |--------------------------------------------------------------------------
    | Assignment map
    |--------------------------------------------------------------------------
    |
    | The mapping of automatic permission assignments to roles must be provided in the following format:
    | ‘permission_key’ => [
    |     ‘group_slug_1’,
    |     ‘group_slug_2’,
    |     ‘group_slug_3’,
    | ],
    |
    */
    'assign' => [

    ],

    /*
    |--------------------------------------------------------------------------
    | The key translation array
    |--------------------------------------------------------------------------
    |
    | An array that allows you to set the name of the permission based on its
    | key must be provided in the following format:
    | ‘permission_key’ => 'Display name'
    |
    */
    'translate' => [

    ],
];
