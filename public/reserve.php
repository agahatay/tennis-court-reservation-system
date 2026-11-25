<?php
require __DIR__ . "/../src/database.php";


$timeSlots = [
  "08:00-09:00","09:00-10:00","10:00-11:00","11:00-12:00",
  "12:00-13:00","13:00-14:00","14:00-15:00","15:00-16:00",
  "16:00-17:00","17:00-18:00","18:00-19:00","19:00-20:00",
  "20:00-21:00"
];

$court_id = isset($_POST['court_id']) ? (int)$_POST['court_id'] : 0;
$date     = trim($_POST['date'] ?? '');
$name     = trim($_POST['name'] ?? '');
$phone    = trim($_POST['phone'] ?? '');
$time     = trim($_POST['time'] ?? '');


$price = 3000;

$redirectBase = "Location: index.php?court_id=".$court_id."&date=".urlencode($date);

if($court_id <= 0 || $date === '' || $name === '' || $phone === '' || $time === ''){
  header($redirectBase."&invalid=missing");
  exit();
}

if(!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)){
  header("Location: index.php?invalid=missing");
  exit();
}

if(!in_array($time, $timeSlots, true)){
  header($redirectBase."&invalid=time");
  exit();
}

$digits = preg_replace('/\D+/', '', $phone);
if(strlen($digits) < 10){
  header($redirectBase."&invalid=missing");
  exit();
}


$stmt = mysqli_prepare($conn, "SELECT reservation_id FROM reservations WHERE court_id = ? AND date = ? AND time = ? LIMIT 1");
mysqli_stmt_bind_param($stmt, "iss", $court_id, $date, $time);
mysqli_stmt_execute($stmt);
$existsRes = mysqli_stmt_get_result($stmt);
if(mysqli_fetch_assoc($existsRes)){
  header($redirectBase."&invalid=booked");
  exit();
}


$customer_id = null;

$stmt = mysqli_prepare($conn, "SELECT customer_id FROM customers WHERE phone = ? LIMIT 1");
mysqli_stmt_bind_param($stmt, "s", $phone);
mysqli_stmt_execute($stmt);
$r = mysqli_stmt_get_result($stmt);

if($row = mysqli_fetch_assoc($r)){
  $customer_id = (int)$row['customer_id'];
} else {
  $stmt = mysqli_prepare($conn, "INSERT INTO customers (name, phone) VALUES (?, ?)");
  mysqli_stmt_bind_param($stmt, "ss", $name, $phone);
  if(!mysqli_stmt_execute($stmt)){
    header($redirectBase."&invalid=db");
    exit();
  }
  $customer_id = (int)mysqli_insert_id($conn);
}


$stmt = mysqli_prepare($conn,
  "INSERT INTO reservations (customer_id, court_id, date, time, price, is_paid)
   VALUES (?, ?, ?, ?, ?, 0)"
);
mysqli_stmt_bind_param($stmt, "iissd", $customer_id, $court_id, $date, $time, $price);

if(!mysqli_stmt_execute($stmt)){
  header($redirectBase."&invalid=db");
  exit();
}

header($redirectBase."&success=1");
exit();
