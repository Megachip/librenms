<?php

$graph_array['height'] = '100';
$graph_array['width'] = '215';
$graph_array['to'] = \App\Facades\LibrenmsConfig::get('time.now');
$graph_array['id'] = $port['port_id'];
$graph_array['type'] = $graph_type;

require 'includes/html/print-graphrow.inc.php';
