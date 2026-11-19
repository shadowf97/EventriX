<?php
// ===================================================
// EventriX - User Booking Details & Digital Receipt
// View attendee ticket voucher and payment transaction info
// ===================================================

$base_path = '../';
$page_title = 'Booking Details';

require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

// Strict Authorization: User role only
require_role('user', $base_path);

$user_id = current_user_id();
$booking_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($booking_id <= 0) {
    set_flash_message('error', 'Invalid booking requested.');
    header("Location: my-bookings.html");
    exit();
}

// Fetch booking with event and host details, STRICTLY checking user_id ownership
$stmt = $pdo->prepare("SELECT b.*, 
                             e.title AS event_title, e.category, e.event_date, e.start_time, e.end_time, e.location, e.ticket_price, e.image,
                             u.full_name AS host_name, u.email AS host_email,
                             p.payment_reference, p.payment_method, p.payment_status AS pay_status, p.created_at AS payment_date
                      FROM bookings b
                      JOIN events e ON b.event_id = e.id
                      JOIN users u ON e.host_id = u.id
                      LEFT JOIN payments p ON b.id = p.booking_id
                      WHERE b.id = ? AND b.user_id = ?
                      LIMIT 1");
$stmt->execute([$booking_id, $user_id]);
$booking = $stmt->fetch();

if (!$booking) {
    // If not found or belongs to another user
    set_flash_message('error', 'Booking record not found or access denied.');
    header("Location: my-bookings.html");
    exit();
}

require __DIR__ . '/../includes/header.php';
?>

<center>
    <p>
        <a href="my-bookings.html">&larr; Back to My Bookings</a> | 
        <a href="dashboard.html">Dashboard</a>
    </p>

    <h2>Booking Voucher &amp; Digital Receipt</h2>
    <p>Reference Code: <strong><?php echo e($booking['booking_reference']); ?></strong></p>

    <hr>

    <!-- Printable Receipt Box -->
    <fieldset>
        <legend><h3>EventriX Digital Admission Ticket</h3></legend>

        <center>
            <p><strong>Status: </strong>
                <?php if ($booking['booking_status'] === 'Confirmed'): ?>
                    <mark><strong>CONFIRMED &amp; ADMITTED</strong></mark>
                <?php else: ?>
                    <mark><?php echo strtoupper(e($booking['booking_status'])); ?></mark>
                <?php endif; ?>
            </p>
        </center>

        <table border="1" cellpadding="8" cellspacing="0" width="100%">
            <tr>
                <td width="35%"><strong>Event Name:</strong></td>
                <td><strong><?php echo e($booking['event_title']); ?></strong></td>
            </tr>
            <tr>
                <td><strong>Category:</strong></td>
                <td><?php echo e($booking['category']); ?></td>
            </tr>
            <tr>
                <td><strong>Event Date:</strong></td>
                <td><strong><?php echo format_date($booking['event_date']); ?></strong></td>
            </tr>
            <tr>
                <td><strong>Time:</strong></td>
                <td><?php echo format_time($booking['start_time']); ?> &ndash; <?php echo format_time($booking['end_time']); ?></td>
            </tr>
            <tr>
                <td><strong>Venue Location:</strong></td>
                <td><?php echo e($booking['location']); ?></td>
            </tr>
            <tr>
                <td><strong>Host / Organizer:</strong></td>
                <td><?php echo e($booking['host_name']); ?> (<?php echo e($booking['host_email']); ?>)</td>
            </tr>
            <tr>
                <td><strong>Attendee Name:</strong></td>
                <td><?php echo e(current_user_name()); ?> (<?php echo e($_SESSION['email'] ?? ''); ?>)</td>
            </tr>
            <tr>
                <td><strong>Number of Tickets:</strong></td>
                <td><strong><?php echo $booking['quantity']; ?> ticket(s)</strong></td>
            </tr>
            <tr>
                <td><strong>Unit Price:</strong></td>
                <td><?php echo format_price($booking['ticket_price']); ?></td>
            </tr>
            <tr>
                <td><strong>Total Amount:</strong></td>
                <td><h3><?php echo format_price($booking['total_amount']); ?></h3></td>
            </tr>
        </table>

        <h4>Payment Details</h4>
        <table border="1" cellpadding="6" cellspacing="0" width="100%">
            <tr>
                <td width="35%"><strong>Payment Reference:</strong></td>
                <td><?php echo !empty($booking['payment_reference']) ? e($booking['payment_reference']) : 'N/A'; ?></td>
            </tr>
            <tr>
                <td><strong>Payment Method:</strong></td>
                <td><?php echo !empty($booking['payment_method']) ? e($booking['payment_method']) : 'N/A'; ?></td>
            </tr>
            <tr>
                <td><strong>Payment Status:</strong></td>
                <td><strong><?php echo e($booking['payment_status']); ?></strong></td>
            </tr>
            <tr>
                <td><strong>Transaction Date:</strong></td>
                <td><?php echo !empty($booking['payment_date']) ? format_date($booking['payment_date']) . ' ' . date('g:i A', strtotime($booking['payment_date'])) : format_date($booking['created_at']); ?></td>
            </tr>
        </table>

        <br>
        <center>
            <button type="button" onclick="window.print()"><strong>Print Admission Receipt</strong></button>
        </center>
    </fieldset>

    <br><br>
    <p><small>Present this digital voucher or printed receipt at the venue check-in desk.</small></p>
</center>

<?php require __DIR__ . '/../includes/footer.php'; ?>
