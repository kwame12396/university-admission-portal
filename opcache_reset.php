<?php
if ($_SERVER['REMOTE_ADDR'] !== '127.0.0.1' && $_SERVER['REMOTE_ADDR'] !== '::1') die('Local only');
if (function_exists('opcache_reset')) {
    opcache_reset();
    echo '✔ OPcache reset';
} else {
    echo 'OPcache not enabled';
}
