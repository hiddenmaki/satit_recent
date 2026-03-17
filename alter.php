<?php
require_once 'includes/db.php';
try {
    $pdo->exec("ALTER TABLE teachers ADD COLUMN line_id VARCHAR(100) NULL, ADD COLUMN profile_picture VARCHAR(255) NULL");
    echo "Columns added successfully";
} catch(PDOException $e) {
    if ($e->getCode() == '42S21') {
        echo "Columns already exist";
    } else {
        echo "Error: " . $e->getMessage();
    }
}
?>
