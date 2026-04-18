<?php
header('Content-Type: text/plain; charset=utf-8');

$result = function_exists('opcache_reset') ? opcache_reset() : false;
echo $result ? "OPCACHE_RESET_OK\n" : "OPCACHE_RESET_UNAVAILABLE\n";
