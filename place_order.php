<?php
require "includes/db.php";

$data = json_decode(file_get_contents("php://input"), true);

$name  = trim($data['name']);
$phone = trim($data['phone']);
$cart  = $data['cart'];

$total = 0;
$itemsArr = [];

foreach ($cart as $item) {
  $lineTotal = $item['price'] * $item['qty'];
  $total += $lineTotal;
  $itemsArr[] = $item['name']." x".$item['qty']." = ₹".$lineTotal;
}

$itemsText = implode(", ", $itemsArr);

/* ---- SAVE TO DB ---- */
$stmt = $pdo->prepare(
  "INSERT INTO orders (customer_name, customer_phone, items, total)
   VALUES (?,?,?,?)"
);
$stmt->execute([$name, $phone, $itemsText, $total]);

/* ---- WHATSAPP MESSAGE ---- */
$owner = "919419117903"; // OWNER NUMBER (country code ke sath)

$msg = urlencode(
  "🍽 NEW ORDER\n\n".
  "Name: $name\n".
  "Phone: $phone\n\n".
  "Items:\n".implode("\n",$itemsArr)."\n\n".
  "Total: ₹$total"
);

echo json_encode([
  "success" => true,
  "whatsapp" => "https://wa.me/$owner?text=$msg"
]);
