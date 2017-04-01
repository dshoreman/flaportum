<?php

require 'vendor/autoload.php';

use App\October\Plugin as SupportForum;

$oc = new SupportForum('feegleweb-octoshop');

echo sprintf("Found %s pages of threads.", $oc->pageCount).PHP_EOL;
echo PHP_EOL;

echo "Preparing export. This could take a while... Time to stick the kettle on!".PHP_EOL;

$oc->export();

echo sprintf("Found %s topics containing a total of %s posts", $oc->topicCount, $oc->postCount).PHP_EOL;

echo "Bye!".PHP_EOL;
