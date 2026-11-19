<?php
// ===================================================
// EventriX - Payment Records (Admin)
// Review payment records, settlement tracking, and payment method statistics
// ===================================================

$base_path = '../';
$page_title = 'Payment Records';

require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

// Strict Authorization: Admin role only
require_role('admin', $base_path);

$search = sanitize_input($_GET['search'] ?? '');
$method_filter = sanitize_input($_GET['method'] ?? '');
$status_filter = sanitize_input($_GET['status'] ?? '');

$query = "SELECT p.*, 
                 b.booking_reference, b.quantity,
                 u.full_name AS attendee_name, u.email AS attendee_email,
                 e.title AS event_title,
                 h.full_name AS host_name
          FROM payments p
          JOIN bookings b ON p.booking_id = b.id
          JOIN users u ON b.user_id = u.id
          JOIN events e ON b.event_id = e.id
          JOIN users h ON e.host_id = h.id
          WHERE 1=1";
$params = [];

if (!empty($search)) {
    $query .= " AND (p.payment_reference LIKE ? OR b.booking_reference LIKE ? OR u.full_name LIKE ? OR e.title LIKE ?)";
    $like = "%{$search}%";
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}

if (!empty($method_filter)) {
    $query .= " AND p.payment_method = ?";
    $params[] = $method_filter;
}

if (!empty($status_filter)) {
    $query .= " AND p.payment_status = ?";
    $params[] = $status_filter;
}

$query .= " ORDER BY p.created_at DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$payments = $stmt->fetchAll();

// Financial Totals calculation
$total_paid = 0;
$total_refunded = 0;
foreach ($payments as $pay) {
    if ($pay['payment_status'] === 'Paid') {
        $total_paid += (float)$pay['amount'];
    } elseif ($pay['payment_status'] === 'Refunded') {
        $total_refunded += (float)$pay['amount'];
    }
}
$net_revenue = $total_paid - $total_refunded;

require __DIR__ . '/../includes/header.php';
?>

<center>
    <h2>Payment Records</h2>
    <p>View ticket payments and refunds.</p>

    <p>
        <a href="dashboard.html">&larr; Return to Dashboard</a> | 
        <a href="manage-bookings.html">All Bookings</a>
    </p>

    <hr>

    <!-- Financial Summary Box -->
    <table border="1" cellpadding="8" cellspacing="0">
        <tr align="center">
            <th width="180">Total Transactions</th>
            <th width="180">Gross Collected (Paid)</th>
            <th width="180">Total Refunded</th>
            <th width="180">Net Revenue</th>
        </tr>
        <tr align="center">
            <td><h3><?php echo count($payments); ?></h3></td>
            <td><h3><?php echo format_price($total_paid); ?></h3></td>
            <td><h3><?php echo format_price($total_refunded); ?></h3></td>
            <td><h3><mark><?php echo format_price($net_revenue); ?></mark></h3></td>
        </tr>
    </table>

    <br><br>

    <!-- Filter Form -->
    <fieldset>
        <legend><strong>Filter Payments</strong></legend>
        <form action="manage-payments.html" method="GET">
            <p>
                <label for="search"><strong>Search:</strong></label>
                <input type="text" id="search" name="search" value="<?php echo e($search); ?>" placeholder="Payment Ref, Booking Ref, Attendee..." size="25">
                &nbsp;

                <label for="status"><strong>Payment Status:</strong></label>
                <select id="status" name="status">
                    <option value="">-- All Statuses --</option>
                    <option value="Paid" <?php echo ($status_filter === 'Paid') ? 'selected' : ''; ?>>Paid</option>
                    <option value="Refunded" <?php echo ($status_filter === 'Refunded') ? 'selected' : ''; ?>>Refunded</option>
                    <option value="Failed" <?php echo ($status_filter === 'Failed') ? 'selected' : ''; ?>>Failed</option>
                </select>
                &nbsp;

                <button type="submit">Filter</button>
                <a href="manage-payments.html"><button type="button">Reset</button></a>
            </p>
        </form>
    </fieldset>

    <br><br>

    <section>
        <?php if (!empty($payments)): ?>
            <table border="1" cellpadding="8" cellspacing="0" width="95%">
                <thead>
                    <tr>
                        <th>Payment Ref</th>
                        <th>Booking Ref</th>
                        <th>Attendee</th>
                        <th>Event</th>
                        <th>Host</th>
                        <th>Amount</th>
                        <th>Payment Method</th>
                        <th>Payment Status</th>
                        <th>Transaction Timestamp</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($payments as $p): ?>
                        <tr>
                            <td><strong><?php echo e($p['payment_reference']); ?></strong></td>
                            <td><?php echo e($p['booking_reference']); ?> (<?php echo $p['quantity']; ?> tix)</td>
                            <td><?php echo e($p['attendee_name']); ?><br><small><?php echo e($p['attendee_email']); ?></small></td>
                            <td><?php echo e($p['event_title']); ?></td>
                            <td><?php echo e($p['host_name']); ?></td>
                            <td><strong><?php echo format_price($p['amount']); ?></strong></td>
                            <td><?php echo e($p['payment_method']); ?></td>
                            <td align="center">
                                <?php if ($p['payment_status'] === 'Paid'): ?>
                                    <strong>Paid</strong>
                                <?php elseif ($p['payment_status'] === 'Refunded'): ?>
                                    <em>Refunded</em>
                                <?php else: ?>
                                    <mark><?php echo e($p['payment_status']); ?></mark>
                                <?php endif; ?>
                            </td>
                            <td><small><?php echo format_date($p['created_at']); ?> <?php echo date('g:i A', strtotime($p['created_at'])); ?></small></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p><em>No payment records match the specified filters.</em></p>
        <?php endif; ?>
    </section>
</center>

<?php require __DIR__ . '/../includes/footer.php'; ?>
