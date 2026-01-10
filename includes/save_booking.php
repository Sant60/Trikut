<?php
require "db.php";

$name  = $_POST['name'];
$phone = $_POST['phone'];
$date  = $_POST['date'];
$size  = $_POST['size'];

$pdo->prepare(
 "INSERT INTO bookings(name,phone,booking_time,guests)
  VALUES (?,?,?,?)"
)->execute([$name,$phone,$date,$size]);

$msg  = "New Table Booking%0A";
$msg .= "Name: $name%0A";
$msg .= "Phone: $phone%0A";
$msg .= "Date: $date%0A";
$msg .= "Guests: $size";

header("Location: https://wa.me/919419117903?text=$msg");
exit;