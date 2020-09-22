<?php
if (($tz = getenv('PHP_TIMEZONE')) && in_array($tz, timezone_identifiers_list())) {
    date_default_timezone_set($tz);
}

print json_encode(array(
    'status' => 'OK',
    'timestamp' => date('Y-m-d H:i:s'),
    'hostname' => gethostname(),
));
