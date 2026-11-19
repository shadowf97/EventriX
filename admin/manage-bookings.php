<?php
// ===================================================
// EventriX - Platform Bookings Management (Admin)
// View all ticket reservations, attendee records, and transaction states
// ===================================================

$base_path = '../';
$page_title = 'Manage All Bookings';

require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

// Strict Authorization: Admin role only
require_role('admin', $base_path);

// Handle Admin Cancellation Override
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'admin_cancel_booking') {
    $token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($token)) {
        set_flash_message('error', 'Security token invalid.');
    } else {
        $booking_id = (int)($_POST['booking_id'] ?? 0);
        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare("SELECT b.*, e.title FROM bookings b JOIN events e ON b.event_id = e.id WHERE b.id = ? FOR UPDATE");
            $stmt->execute([$booking_id]);
            $b = $stmt->fetch();

            if ($b && $b['booking_status'] !== 'Cancelled') {
                $upd = $pdo->prepare("UPDATE bookings SET booking_status = 'Cancelled', payment_status = 'Refunded' WHERE id = ?");
                $upd->execute([$booking_id]);

                // Restore tickets
                $rest = $pdo->prepare("UPDATE events SET available_tickets = available_tickets + ? WHERE id = ?");
                $rest->execute([$b['quantity'], $b['event_id']]);

                // Update payment record
                $pay_upd = $pdo->prepare("UPDATE payments SET payment_status = 'Refunded' WHERE booking_id = ?");
                $pay_upd->execute([$booking_id]);

                $pdo->commit();
                set_flash_message('success', "Booking #{$b['booking_reference']} cancelled and tickets restored to inventory.");
            } else {
                $pdo->rollBack();
                set_flash_message('error', "Booking not found or already cancelled.");
            }
        } catch (Exception $e) {
            $pdo->rollBack();
            set_flash_message('error', 'Error cancelling booking: ' . $e->getMessage());
        }
    }
    header("Location: manage-bookings.html");
    exit();
}

$search = sanitize_input($_GET['search'] ?? '');
$booking_status = sanitize_input($_GET['b_status'] ?? '');
$payment_status = sanitize_input($_GET['p_status'] ?? '');
$filter_event_id = (int)($_GET['event_id'] ?? 0);

// Base Query
$query = "SELECT b.*, 
                 u.full_name AS attendee_name, u.email AS attendee_email,
                 e.title AS event_title, e.event_date,
                 h.full_name AS host_name,
                 p.payment_reference, p.payment_method
          FROM bookings b
          JOIN users u ON b.user_id = u.id
          JOIN events e ON b.event_id = e.id
          JOIN users h ON e.host_id = h.id
          LEFT JOIN payments p ON b.id = p.booking_id
          WHERE 1=1";
$params = [];

if (!empty($search)) {
    $query .= " AND (b.booking_reference LIKE ? OR u.full_name LIKE ? OR u.email LIKE ? OR e.title LIKE ?)";
    $like = "%{$search}%";
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}

if (!empty($booking_status)) {
    $query .= " AND b.booking_status = ?";
    $params[] = $booking_status;
}

if (!empty($payment_status)) {
    $query .= " AND b.payment_status = ?";
    $params[] = $payment_status;
}

if ($filter_event_id > 0) {
    $query .= " AND b.event_id = ?";
    $params[] = $filter_event_id;
}

$query .= " ORDER BY b.created_at DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$bookings = $stmt->fetchAll();

// Total Summary
$total_qty = 0;
$total_money = 0;
foreach ($bookings as $b_row) {
    if ($b_row['booking_status'] === 'Confirmed') {
        $total_qty += (int)$b_row['quantity'];
        $total_money += (float)$b_row['total_amount'];
    }
}

require __DIR__ . '/../includes/header.php';
?>

<center>
    <h2>Platform Ticket Bookings Directory</h2>
    <p>View and manage all ticket bookings.</p>

    <p>
        <a href="dashboard.html">&larr; Return to Dashboard</a> | 
        <a href="manage-payments.html">Payments</a> | 
        <a href="manage-events.html">Events Directory</a>
    </p>

    <hr>

    <!-- Search & Filter Controls -->
    <fieldset>
        <legend><strong>Filter Bookings</strong></legend>
        <form action="manage-bookings.html" method="GET">
            <p>
                <label for="search"><strong>Search:</strong></label>
                <input type="text" id="search" name="search" value="<?php echo e($search); ?>" placeholder="Ref, Attendee, Email, Event..." size="25">
                &nbsp;

                <label for="b_status"><strong>Booking Status:</strong></label>
                <select id="b_status" name="b_status">
                    <option value="">-- All --</option>
                    <option value="Confirmed" <?php echo ($booking_status === 'Confirmed') ? 'selected' : ''; ?>>Confirmed</option>
                    <option value="Cancelled" <?php echo ($booking_status === 'Cancelled') ? 'selected' : ''; ?>>Cancelled</option>
                    <option value="Pending Payment" <?php echo ($booking_status === 'Pending Payment') ? 'selected' : ''; ?>>Pending Payment</option>
                </select>
                &nbsp;

                <label for="p_status"><strong>Payment Status:</strong></label>
                <select id="p_status" name="p_status">
                    <option value="">-- All --</option>
                    <option value="Paid" <?php echo ($payment_status === 'Paid') ? 'selected' : ''; ?>>Paid</option>
                    <option value="Unpaid" <?php echo ($payment_status === 'Unpaid') ? 'selected' : ''; ?>>Unpaid</option>
                    <option value="Refunded" <?php echo ($payment_status === 'Refunded') ? 'selected' : ''; ?>>Refunded</option>
                </select>
                &nbsp;

                <button type="submit">Filter</button>
                <a href="manage-bookings.html"><button type="button">Reset</button></a>
            </p>
        </form>
    </fieldset>

    <br><br>

    <!-- Filtered Totals Box -->
    <table border="1" cellpadding="6" cellspacing="0">
        <tr align="center">
            <td width="200">Total Bookings Found: <strong><?php echo count($bookings); ?></strong></td>
            <td width="200">Confirmed Tickets: <strong><?php echo $total_qty; ?></strong></td>
            <td width="200">Confirmed Revenue: <strong><?php echo format_price($total_money); ?></strong></td>
        </tr>
    </table>

    <br>

    <section>
        <?php if (!empty($bookings)): ?>
            <table border="1" cellpadding="8" cellspacing="0" width="98%">
                <thead>
                    <tr>
                        <th>Booking Ref</th>
                        <th>Attendee Name &amp; Email</th>
                        <th>Event Title</th>
                        <th>Host</th>
                        <th>Tickets</th>
                        <th>Amount</th>
                        <th>Booking Status</th>
                        <th>Payment Status</th>
                        <th>Booked Date</th>
                        <th>Admin Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($bookings as $b): ?>
                        <tr>
                            <td><strong><?php echo e($b['booking_reference']); ?></strong></td>
                            <td>
                                <strong><?php echo e($b['attendee_name']); ?></strong><br>
                                <small><a href="mailto:<?php echo e($b['attendee_email']); ?>"><?php echo e($b['attendee_email']); ?></a></small>
                            </td>
                            <td>
                                <a href="../event-details.html?id=<?php echo $b['event_id']; ?>"><?php echo e($b['event_title']); ?></a><br>
                                <small>(<?php echo format_date($b['event_date']); ?>)</small>
                            </td>
                            <td><?php echo e($b['host_name']); ?></td>
                            <td align="center"><strong><?php echo $b['quantity']; ?></strong></td>
                            <td><?php echo format_price($b['total_amount']); ?></td>
                            <td align="center">
                                <?php if ($b['booking_status'] === 'Confirmed'): ?>
                                    <strong>Confirmed</strong>
                                <?php else: ?>
                                    <em><?php echo e($b['booking_status']); ?></em>
                                <?php endif; ?>
                            </td>
                            <td align="center"><?php echo e($b['payment_status']); ?></td>
                            <td><small><?php echo format_date($b['created_at']); ?> <?php echo date('g:i A', strtotime($b['created_at'])); ?></small></td>
                            <td align="center">
                                <?php if ($b['booking_status'] === 'Confirmed'): ?>
                                    <form action="manage-bookings.html" method="POST" onsubmit="return confirm('Cancel and refund this booking? This will return tickets to the event quota.');">
                                        <?php echo csrf_field(); ?>
                                        <input type="hidden" name="action" value="admin_cancel_booking">
                                        <input type="hidden" name="booking_id" value="<?php echo $b['id']; ?>">
                                        <button type="submit">Cancel &amp; Refund</button>
                                    </form>
                                <?php else: ?>
                                    <small>[No Actions]</small>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p><em>No bookings found matching your search filters.</em></p>
        <?php endif; ?>
    </section>
</center>

<?php require __DIR__ . '/../includes/footer.php'; ?>
