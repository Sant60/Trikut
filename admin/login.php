<?php
session_start();
require "../includes/db.php";
if($_POST){
$q=$pdo->prepare("SELECT * FROM admins WHERE username=?");
$q->execute([$_POST['username']]);
$u=$q->fetch();
if($u && password_verify($_POST['password'],$u['password'])){
$_SESSION['admin']=$u['id'];
header("Location: dashboard.php");
}
}
?>
<form method="post">
<input name="username">
<input type="password" name="password">
<button>Login</button>
</form>