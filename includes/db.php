<?php
// includes/db.php - Secure Database Connection using PDO
$host = 'localhost';
$dbname = 'satitschool';
$username = 'root'; // XAMPP Default
$password = ''; // XAMPP Default

try {
    // กำหนด DSN (Data Source Name) และตั้งค่า charset เป็น utf8mb4 เพื่อรองรับภาษาไทยได้สมบูรณ์และป้องกัน SQL Injection บางประเภท
    $dsn = "mysql:host=$host;dbname=$dbname;charset=utf8mb4";
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // บังคับให้แสดง Error เป็น Exception เพื่อความปลอดภัย (ไม่เผยแพร่ Error ออกหน้าเว็บตรงๆ)
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,       // รูปแบบค่าที่ดึงมาเป็น Associative Array
        PDO::ATTR_EMULATE_PREPARES   => false,                  // ปิดการจำลอง Prepared Statements เพื่อบังคับใช้ Prepared Statement ของฐานข้อมูลจริง ป้องกัน SQL Injection อย่างเด็ดขาด
    ];

    $pdo = new PDO($dsn, $username, $password, $options);
} catch (PDOException $e) {
    // ในระบบ Production ควรเก็บ Log ไม่ควร echo Error ออกมาตรงๆ
    die("เกิดข้อผิดพลาดในการเชื่อมต่อฐานข้อมูล กรุณาติดต่อผู้ดูแลระบบ");
}
?>
