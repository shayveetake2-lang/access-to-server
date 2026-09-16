<?php
$sessions = glob("/Applications/MAMP/tmp/php/sess_*");
foreach ($sessions as $file) {
    if (is_file($file)) unlink($file);
}
exec("killall -9 httpd");
echo "Hard restart initiated and sessions cleared.";
?>
