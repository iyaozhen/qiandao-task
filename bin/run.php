<?php

use Qiandao\Master;

require "../vendor/autoload.php";
$config = require("../conf/config.php");

$master = new Master($config);
$master->run();

