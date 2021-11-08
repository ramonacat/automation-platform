<?php

declare(strict_types=1);
$startTime = time();

while (time() - $startTime < 3) {
    echo '.' . PHP_EOL;
    flush();
    usleep(1000);
}
