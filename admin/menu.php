<?php
session_start();
require "../includes/db.php";
if(!isset($_SESSION['admin'])) exit;

if($_POST){
$img = uniqid().".jpg";
move_uploaded_file($_FILES['image']['tmp_name'], "../assets/images/".$img);
$pdo->prepare("INSERT INTO menu_items(name,description,price,image) VALUES (?,?,?,?)")
->execute([$_POST['name'],$_POST['description'],$_POST['price'],$img]);
}
?>
<form method="post" enctype="multipart/form-data">
<input name="name" placeholder="Name"><br>
<input name="price" placeholder="Price"><br>
<textarea name="description"></textarea><br>
<input type="file" name="image"><br>
<button>add</button>
</form>