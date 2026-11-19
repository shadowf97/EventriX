<?php
// ===================================================
// EventriX - User Bookings List
// Complete booking history and status tracking for attendees
// ===================================================

$base_path = '../';
$page_title = 'My Bookings';

require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

// Strict Authorization: User role only
require_role('user', $base_path);

$user_id = current_user_id();
$filter_status = sanitize_input($_GET['status'] ?? '');

// Handle Booking Cancellation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'cancel_booking') {
    $cancel_booking_id = (int)($_POST['booking_id'] ?? 0);
    $token = $_POST['csrf_token'] ?? '';

    if (!verify_csrf_token($token)) {
        set_flash_message('error', 'Security token invalid. Cancellation aborted.');
    } else {
        try {
            $pdo->beginTransaction();

            // Fetch booking ensuring ownership and locking
            $stmt = $pdo->prepare("SELECT b.*, e.event_date, e.title 
                                  FROM bookings b 
                                  JOIN events e ON b.event_id = e.id 
                                  WHERE b.id = ? AND b.user_id = ? FOR UPDATE");
            $stmt->execute([$cancel_booking_id, $user_id]);
            $b_to_cancel = $stmt->fetch();

            if (!$b_to_cancel) {
                throw new Exception('Booking not found or permission denied.');
            }

            if ($b_to_cancel['booking_status'] === 'Cancelled') {
                throw new Exception('This booking has already been cancelled.');
            }

            // Cannot cancel past events
            if (strtotime($b_to_cancel['event_date']) < strtotime(date('Y-m-d'))) {
                throw new Exception('Past event bookings cannot be cancelled.');
            }

            // Update booking status
            $upd_stmt = $pdo->prepare("UPDATE bookings SET booking_status = 'Cancelled', payment_status = 'Refunded' WHERE id = ?");
            $upd_stmt->execute([$cancel_booking_id]);

            // Restore tickets to event availability
            $rest_stmt = $pdo->prepare("UPDATE events SET available_tickets = available_tickets + ? WHERE id = ?");
            $rest_stmt->execute([$b_to_cancel['quantity'], $b_to_cancel['event_id']]);

            // Update payment record to refunded if exists
            $pay_upd = $pdo->prepare("UPDATE payments SET payment_status = 'Refunded' WHERE booking_id = ?");
            $pay_upd->execute([$cancel_booking_id]);

            // Log cancellation

            $pdo->commit();
            set_flash_message('success', 'Booking #' . $b_to_cancel['booking_reference'] . ' has been successfully cancelled and tickets were refunded.');
            header("Location: my-bookings.html");
            exit();

        } catch (Exception $e) {
            $pdo->rollBack();
            set_flash_message('error', 'Failed to cancel booking: ' . $e->getMessage());
            header("Location: my-bookings.html");
            exit();
        }
    }
}

// Fetch user's bookings with optional status filter
$query = "SELECT b.*, e.title, e.category, e.event_date, e.start_time, e.end_time, e.location 
          FROM bookings b 
          JOIN events e ON b.event_id = e.id 
          WHERE b.user_id = ?";
$params = [$user_id];

if (!empty($filter_status)) {
    $query .= " AND b.booking_status = ?";
    $params[] = $filter_status;
}

$query .= " ORDER BY b.created_at DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$bookings = $stmt->fetchAll();

require __DIR__ . '/../includes/header.php';
?>

<center>
    <h2>My Ticket Bookings</h2>
    <p>View your complete booking history, access digital receipts, or cancel upcoming reservations.</p>

    <p>
        <a href="dashboard.html">&larr; Return to Dashboard</a> | 
        <a href="../events.html">Browse More Events</a>
    </p>

    <!-- Filter Buttons -->
    <p>
        Filter by Status: 
        <a href="my-bookings.html">[All]</a> &nbsp;
        <a href="my-bookings.html?status=Confirmed">[Confirmed]</a> &nbsp;
        <a href="my-bookings.html?status=Cancelled">[Cancelled]</a> &nbsp;
        <a href="my-bookings.html?status=Pending+Payment">[Pending]</a>
    </p>

    <hr>

    <section>
        <?php if (!empty($bookings)): ?>
            <table border="1" cellpadding="8" cellspacing="0" width="95%">
                <thead>
                    <tr>
                        <th>Booking Ref</th>
                        <th>Event Details</th>
                        <th>Event Date & Time</th>
                        <th>Location</th>
                        <th>Quantity</th>
                        <th>Total Price</th>
                        <th>Booking Status</th>
                        <th>Payment Status</th>
                        <th>Booked On</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($bookings as $b): ?>
                        <?php 
                            $can_cancel = ($b['booking_status'] === 'Confirmed') && (strtotime($b['event_date']) >= strtotime(date('Y-m-d')));
                        ?>
                        <tr>
                            <td><strong><?php echo e($b['booking_reference']); ?></strong></td>
                            <td>
                                <strong><a href="../event-details.html?id=<?php echo $b['event_id']; ?>"><?php echo e($b['title']); ?></a></strong><br>
                                <small>[<?php echo e($b['category']); ?>]</small>
                            </td>
                            <td>
                                <?php echo format_date($b['event_date']); ?><br>
                                <small><?php echo format_time($b['start_time']); ?> - <?php echo format_time($b['end_time']); ?></small>
                            </td>
                            <td><?php echo e($b['location']); ?></td>
                            <td align="center"><?php echo $b['quantity']; ?></td>
                            <td><?php echo format_price($b['total_amount']); ?></td>
                            <td align="center">
                                <?php if ($b['booking_status'] === 'Confirmed'): ?>
                                    <strong><?php echo e($b['booking_status']); ?></strong>
                                <?php else: ?>
                                    <em><?php echo e($b['booking_status']); ?></em>
                                <?php endif; ?>
                            </td>
                            <td align="center"><?php echo e($b['payment_status']); ?></td>
                            <td><small><?php echo format_date($b['created_at']); ?></small></td>
                            <td align="center">
                                <a href="booking-details.html?id=<?php echo $b['id']; ?>"><button type="button">Details</button></a>
                                
                                <?php if ($can_cancel): ?>
                                    <form action="my-bookings.html" method="POST" onsubmit="return confirm('Are you sure you want to cancel this booking? This action will refund your tickets.');">
                                        <?php echo csrf_field(); ?>
                                        <input type="hidden" name="action" value="cancel_booking">
                                        <input type="hidden" name="booking_id" value="<?php echo $b['id']; ?>">
                                        <button type="submit">Cancel</button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p><em>No bookings found matching the selected criteria.</em></p>
            <p><a href="../events.html">Browse upcoming events to book tickets!</a></p>
        <?php endif; ?>
    </section>
</center>

<?php require __DIR__ . '/../includes/footer.php'; ?>
