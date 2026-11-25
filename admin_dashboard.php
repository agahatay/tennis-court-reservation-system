<?php
session_start();
if(!isset($_SESSION['admin'])){
  header("Location: admin_login.php");
  exit();
}
include "db.php";

$today = date('Y-m-d');

$todayCount = mysqli_fetch_assoc(mysqli_query($conn,
  "SELECT COUNT(*) AS c FROM reservations WHERE date = '$today'"
))['c'] ?? 0;

$totalCount = mysqli_fetch_assoc(mysqli_query($conn,
  "SELECT COUNT(*) AS c FROM reservations"
))['c'] ?? 0;

$topCourtRow = mysqli_fetch_assoc(mysqli_query($conn,
  "SELECT courts.court_name, COUNT(*) AS c
   FROM reservations
   LEFT JOIN courts ON reservations.court_id = courts.court_id
   GROUP BY reservations.court_id
   ORDER BY c DESC
   LIMIT 1"
));
$topCourtName = $topCourtRow['court_name'] ?? '-';
$topCourtCount = $topCourtRow['c'] ?? 0;

/* is_paid eklendi */
$next5 = mysqli_query($conn,
  "SELECT r.date, r.time, r.is_paid,
          co.court_name,
          cu.name, cu.phone
   FROM reservations r
   LEFT JOIN courts co ON r.court_id = co.court_id
   LEFT JOIN customers cu ON r.customer_id = cu.customer_id
   WHERE r.date >= '$today'
   ORDER BY r.date, r.time
   LIMIT 5"
);
?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <title>Admin Dashboard</title>
  <style>
    body { font-family: Arial; background:#f2f2f2; padding:20px; }
    .wrap { max-width: 1000px; margin: 0 auto; }
    .top { display:flex; justify-content:space-between; align-items:center; margin-bottom:12px; }
    a { color:#27ae60; text-decoration:none; font-weight:bold; margin-left:12px; }
    .cards { display:grid; grid-template-columns: repeat(3, 1fr); gap:12px; margin-bottom:12px; }
    .card { background:#fff; border-radius:10px; padding:14px; box-shadow:0 10px 25px rgba(0,0,0,0.10); }
    .card h3 { margin:0 0 6px 0; font-size:14px; opacity:.8; }
    .card .num { font-size:28px; font-weight:bold; }
    table { width:100%; border-collapse:collapse; background:#fff; border-radius:10px; overflow:hidden; box-shadow:0 10px 25px rgba(0,0,0,0.10); }
    th, td { padding:10px; border-bottom:1px solid #eee; text-align:center; font-size:13px; }
    th { background:#27ae60; color:#fff; }
    .paid { color:#27ae60; font-weight:bold; }
    .unpaid { color:#c0392b; font-weight:bold; }

    /* VERSION etiketi */
    .version {
      position: fixed;
      bottom: 10px;
      right: 10px;
      background: #000;
      color: #fff;
      padding: 6px 10px;
      border-radius: 8px;
      font-size: 12px;
      z-index: 9999;
      opacity: .85;
    }
  </style>
</head>
<body>

<div class="version">ADMIN_DASH v3</div>

<div class="wrap">

  <div class="top">
    <h2 style="margin:0;">Admin Dashboard</h2>
    <div>
      <a href="list.php">Reservations</a>
      <a href="schedule.php">Daily Schedule</a>
      <a href="payments.php">Payments</a>
      <a href="logout.php">Logout</a>
    </div>
  </div>

  <div class="cards">
    <div class="card">
      <h3>Today (<?php echo htmlspecialchars($today); ?>)</h3>
      <div class="num"><?php echo (int)$todayCount; ?></div>
    </div>
    <div class="card">
      <h3>Total Reservations</h3>
      <div class="num"><?php echo (int)$totalCount; ?></div>
    </div>
    <div class="card">
      <h3>Top Court</h3>
      <div class="num"><?php echo htmlspecialchars($topCourtName); ?></div>
      <div style="margin-top:6px; opacity:.8;">Bookings: <?php echo (int)$topCourtCount; ?></div>
    </div>
  </div>

  <h3 style="margin:10px 0;">Next 5 Reservations</h3>
  <table>
    <tr>
      <th>Date</th>
      <th>Time</th>
      <th>Court</th>
      <th>Name</th>
      <th>Phone</th>
      <th>Paid</th>
    </tr>

    <?php while($row = mysqli_fetch_assoc($next5)) { ?>
      <tr>
        <td><?php echo htmlspecialchars($row['date']); ?></td>
        <td><?php echo htmlspecialchars($row['time']); ?></td>
        <td><?php echo htmlspecialchars($row['court_name'] ?? '-'); ?></td>
        <td><?php echo htmlspecialchars($row['name'] ?? '-'); ?></td>
        <td><?php echo htmlspecialchars($row['phone'] ?? '-'); ?></td>
        <td>
          <?php if ((int)($row['is_paid'] ?? 0) === 1): ?>
            <span class="paid">PAID</span>
          <?php else: ?>
            <span class="unpaid">UNPAID</span>
          <?php endif; ?>
        </td>
      </tr>
    <?php } ?>
  </table>

</div>
</body>
</html>
