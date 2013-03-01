<?php

/////////////////////////////////////////////////////////////////////////////
// General information
/////////////////////////////////////////////////////////////////////////////

$app['basename'] = 'reports_database';
$app['version'] = '1.4.20';
$app['release'] = '1';
$app['vendor'] = 'ClearFoundation';
$app['packager'] = 'ClearFoundation';
$app['license'] = 'GPLv3';
$app['license_core'] = 'LGPLv3';
$app['description'] = lang('reports_database_app_description');

/////////////////////////////////////////////////////////////////////////////
// App name and categories
/////////////////////////////////////////////////////////////////////////////

$app['name'] = lang('reports_database_app_name');
$app['category'] = lang('base_category_reports');
$app['subcategory'] = lang('base_subcategory_settings');
$app['menu_enabled'] = FALSE;

/////////////////////////////////////////////////////////////////////////////
// Packaging
/////////////////////////////////////////////////////////////////////////////

$app['core_only'] = TRUE;

// TODO: remove cronie after cron workaround is no longer needed
$app['core_requires'] = array(
    'app-base-core >= 1:1.4.9',
    'app-reports',
    'app-system-database-core >= 1:1.2.4',
    'cronie',
    'webconfig-php-mysql',
);

$app['core_directory_manifest'] = array(
    '/var/clearos/reports_database' => array(),
    '/var/clearos/reports_database/cache' => array(),
);

$app['core_file_manifest'] = array(
    'initialize-report-tables' => array(
        'target' => '/usr/sbin/initialize-report-tables',
        'mode' => '0755',
    ),
);
