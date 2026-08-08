<?php
require_once 'includes/config.php';
$result = $conn->query("SELECT id, name FROM products");
while($row = $result->fetch_assoc()) {
    echo $row['id'] . " - " . $row['name'] . "\n";
}
