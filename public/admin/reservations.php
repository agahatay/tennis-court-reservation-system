<?php
session_start();
if(!isset($_SESSION['admin'])){
  header("Location: login.php");
  exit();
}
require __DIR__ . "/../../src/database.php";


if(isset($_GET['toggle_paid'])){
  $pid = (int)($_GET['toggle_paid'] ?? 0);
  $val = (int)($_GET['v'] ?? 0);
  $val = ($val === 1) ? 1 : 0;

  if($pid > 0){
    $stmt = mysqli_prepare($conn, "UPDATE reservations SET is_paid = ? WHERE reservation_id = ?");
    mysqli_stmt_bind_param($stmt, "ii", $val, $pid);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
  }

  
  $q = urlencode($_GET['q'] ?? '');
  $d = urlencode($_GET['date'] ?? '');
  $u = urlencode($_GET['unpaid'] ?? '');
  header("Location: reservations.php?q=$q&date=$d&unpaid=$u");
  exit();
}


$q = trim($_GET['q'] ?? '');
$filterDate = trim($_GET['date'] ?? '');
$onlyUnpaid = isset($_GET['unpaid']) && $_GET['unpaid'] === '1';

$whereParts = [];

if($q !== ''){
  $qSafe = mysqli_real_escape_string($conn, $q);
  $whereParts[] = "(cu.name LIKE '%$qSafe%' OR cu.phone LIKE '%$qSafe%')";
}
if($filterDate !== ''){
  $dSafe = mysqli_real_escape_string($conn, $filterDate);
  $whereParts[] = "r.date = '$dSafe'";
}
if($onlyUnpaid){
  $whereParts[] = "COALESCE(r.is_paid,0) = 0";
}

$whereSql = "";
if(count($whereParts) > 0){
  $whereSql = "WHERE " . implode(" AND ", $whereParts);
}

$result = mysqli_query($conn,
 "SELECT r.reservation_id,
         cu.name,
         cu.phone,
         c.court_name,
         r.date,
         r.time,
         r.price,
         r.is_paid
  FROM reservations r
  LEFT JOIN customers cu ON r.customer_id = cu.customer_id
  LEFT JOIN courts c ON r.court_id = c.court_id
  $whereSql
  ORDER BY r.date, r.time"
);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Admin Panel - Reservations</title>
  <style>
    body { font-family: Arial; background: #f2f2f2; padding: 20px; }
    .wrap { max-width: 1200px; margin: 0 auto; }
    .topbar { display:flex; justify-content:space-between; align-items:center; margin-bottom:12px; }
    .topbar a { text-decoration:none; font-weight:bold; color:#27ae60; margin-left:12px; }

    table { width:100%; border-collapse:collapse; background:white; box-shadow:0 10px 25px rgba(0,0,0,0.12); border-radius:8px; overflow:hidden; }
    th, td { padding:10px; border-bottom:1px solid #eee; text-align:center; }
    th { background:#27ae60; color:white; }
    tr:nth-child(even){ background:#fafafa; }

    a.delete { color:red; text-decoration:none; font-weight:bold; }
    a.togglePaid { color:#27ae60; text-decoration:none; font-weight:bold; }

    .badge { display:inline-block; padding:4px 10px; border-radius:999px; font-weight:700; font-size:12px; }
    .paidYes { background:#d4edda; color:#155724; }
    .paidNo  { background:#f8d7da; color:#721c24; }

    .filters {
      background:#fff; padding:12px; border-radius:8px;
      box-shadow:0 6px 15px rgba(0,0,0,0.08);
      margin-bottom:12px; display:flex; gap:10px; flex-wrap:wrap; align-items:center;
    }
    .filters input[type="text"], .filters input[type="date"] { padding:8px; }
    .filters label { display:flex; align-items:center; gap:6px; font-weight:700; }
    .filters button { padding:8px 12px; background:#27ae60; color:#fff; border:none; cursor:pointer; }
    .filters a { color:#27ae60; font-weight:bold; text-decoration:none; }
  </style>
</head>
<body>
<div class="wrap">

  <div class="topbar">
    <h2 style="margin:0;">Admin Panel - Reservations</h2>
    <div>
      <a href="dashboard.php">Dashboard</a>
      <a href="schedule.php">Daily Schedule</a>
      <a href="payments.php">Payments</a>
      <a href="logout.php">Logout</a>
    </div>
  </div>

  <form class="filters" method="GET">
    <input type="text" name="q" placeholder="Search name or phone" value="<?php echo htmlspecialchars($q); ?>">
    <input type="date" name="date" value="<?php echo htmlspecialchars($filterDate); ?>">

    <label>
      <input type="checkbox" name="unpaid" value="1" <?php echo $onlyUnpaid ? 'checked' : ''; ?>>
      Only Unpaid
    </label>

    <button type="submit">Apply</button>
    <a href="reservations.php">Clear</a>
  </form>

  <table>
    <tr>
      <th>Name</th>
      <th>Phone</th>
      <th>Court</th>
      <th>Date</th>
      <th>Time</th>
      <th>Price</th>
      <th>Paid</th>
      <th>Action</th>
    </tr>

    <?php while($row = mysqli_fetch_assoc($result)) { ?>
      <tr>
        <td><?php echo htmlspecialchars($row['name'] ?? '-'); ?></td>
        <td><?php echo htmlspecialchars($row['phone'] ?? '-'); ?></td>
        <td><?php echo htmlspecialchars($row['court_name'] ?? '-'); ?></td>
        <td><?php echo htmlspecialchars($row['date'] ?? '-'); ?></td>
        <td><?php echo htmlspecialchars($row['time'] ?? '-'); ?></td>
        <td><?php echo number_format((float)($row['price'] ?? 0), 2); ?></td>

        <td>
          <?php if((int)($row['is_paid'] ?? 0) === 1){ ?>
            <span class="badge paidYes">Yes</span>
          <?php } else { ?>
            <span class="badge paidNo">No</span>
          <?php } ?>
        </td>

        <td>
          <?php
            $rid = (int)($row['reservation_id'] ?? 0);
            $isPaid = ((int)($row['is_paid'] ?? 0) === 1);
            $nextVal = $isPaid ? 0 : 1;
            $btnText = $isPaid ? "Mark Unpaid" : "Mark Paid";
            $confirmText = $isPaid ? "Mark this reservation as UNPAID?" : "Mark this reservation as PAID?";
          ?>

          <a class="togglePaid"
             href="reservations.php?toggle_paid=<?php echo $rid; ?>&v=<?php echo $nextVal; ?>&q=<?php echo urlencode($q); ?>&date=<?php echo urlencode($filterDate); ?>&unpaid=<?php echo $onlyUnpaid ? '1' : ''; ?>"
             onclick="return confirm('<?php echo $confirmText; ?>')">
             <?php echo $btnText; ?>
          </a>

          &nbsp;|&nbsp;

          <a class="delete"
             href="delete-reservation.php?id=<?php echo $rid; ?>"
             onclick="return confirm('Delete this reservation?')">
             Delete
          </a>
        </td>
      </tr>
    <?php } ?>
  </table>

  <div style="margin-top:14px; text-align:center;">
    <a href="../index.php" style="text-decoration:none; font-weight:bold; color:#27ae60;">← Back to Reservation Page</a>
  </div>

</div>
</body>
</html>
