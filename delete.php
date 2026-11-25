<?php
session_start();

if(!isset($_SESSION['admin'])){
  header("Location: admin_login.php");
  exit();
}

include "db.php";

$id = $_GET['id'];

mysqli_query($conn,
  "DELETE FROM reservations WHERE reservation_id = $id"
);

header("Location: list.php");
exit();
?>
