<?php
return [
    'base_domain' => env('TENANCY_BASE_DOMAIN', 'arthadesk.in'),
    'admin_host' => env('TENANCY_ADMIN_HOST', 'app.arthadesk.in'),
    'enforce_hosts' => env('TENANCY_ENFORCE_HOSTS', true),
    'reserved_slugs' => array_values(array_filter(array_map('trim', explode(',', (string)env(
        'TENANCY_RESERVED_SLUGS',
        'www,admin,api,app,mail,ftp,smtp,imap,pop,ns1,ns2,dns,cdn,static,assets,status,support,help,billing,accounts,login,signup,register,dashboard,portal,cloud,dev,staging,test,beta'
    ))))),
    'min_slug_length' => 3,
    'max_slug_length' => 50,
];
