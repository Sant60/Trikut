<?php
session_start();
require "../includes/db.php";
if(!isset($_SESSION['admin'])) exit;

if($_FILES){
$img = uniqid().".jpg";
move_uploaded_file($_FILES['image']['tmp_name'], "../assets/images/".$img);
$pdo->prepare("INSERT INTO gallery(image) VALUES(?)")->execute([$img]);
}
?>
<form method="post" enctype="multipart/form-data">
<input type="file" name="image">
<button>Upload</button>
</form>