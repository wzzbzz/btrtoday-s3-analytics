<?php

require __DIR__ . "/reports.php";



if (php_sapi_name() != 'cli') {
    throw new Exception('This application must be run on the command line.');
}


$reports = new SeriesAnalyticsReports();
$reports->system_init();
echo "\ndone.\n";