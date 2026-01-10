<?php
session_start();
require "../includes/db.php";
if(!isset($_SESSION['admin'])) exit;

$b=$pdo->query("SELECT * FROM bookings ORDER BY booking_time DESC");
foreach($b as $r){
echo $r['name']." | ".$r['phone']." | ".$r['booking_time']."<br>";
}