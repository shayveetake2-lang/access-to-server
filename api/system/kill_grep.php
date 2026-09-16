<?php
$output = [];
exec("killall -9 grep", $output);
exec("killall -9 find", $output);
echo "Killed runaway processes: " . implode("\n", $output);
?>
