<?php
session_start();

if(!isset($_SESSION['admin'])){
  header("Location: login.php");
  exit();
}

require __DIR__ . "/../../src/database.php";

$id = $_GET['id'];

mysqli_query($conn,
  "DELETE FROM reservations WHERE reservation_id = $id"
);

header("Location: reservations.php");
exit();
?>
