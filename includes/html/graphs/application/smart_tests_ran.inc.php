<?php

$name = 'smart';
$unit_text = '';
$unitlen = 20;
$bigdescrlen = 10;
$smalldescrlen = 10;
$colours = 'mega';
$dostack = 0;
$printtotal = 0;
$addarea = 1;
$transparency = 15;

$rrd_filename = Rrd::name($device['hostname'], ['app', $name, $app->app_id, $vars['disk']]);

$rrd_list = [
    [
        'filename' => $rrd_filename,
        'descr' => 'Extended',
        'ds' => 'extended',
    ],
    [
        'filename' => $rrd_filename,
        'descr' => 'Short',
        'ds' => 'short',
    ],
    [
        'filename' => $rrd_filename,
        'descr' => 'Selective',
        'ds' => 'selective',
    ],
    [
        'filename' => $rrd_filename,
        'descr' => 'Conveyance',
        'ds' => 'conveyance',
    ],
];

require 'includes/html/graphs/generic_multi_line_exact_numbers.inc.php';
