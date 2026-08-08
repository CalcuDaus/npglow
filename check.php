<?php
require 'includes/config.php';
$res = $conn->query('SHOW COLUMNS FROM consultation_logs');
while($row = $res->fetch_assoc()) {
    echo $row['Field'] . "\n";
}
