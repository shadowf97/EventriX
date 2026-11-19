<?php
// ===================================================
// EventriX - Event Details & Ticket Booking Page
// Displays detailed event information and handles atomic ticket bookings
// ===================================================

$base_path = '';

require_once __DIR__ . '/includes/database.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

$event_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($event_id <= 0) {
    set_flash_message('error', 'Invalid event selected.');
    header("Location: events.html");
    exit();
}

// Fetch event details with host info
$stmt = $pdo->prepare("SELECT e.*, u.full_name AS host_name, u.email AS host_email 
                      FROM events e 
                      JOIN users u ON e.host_id = u.id 
                      WHERE e.id = ? 
                      LIMIT 1");
$stmt->execute([$event_id]);
$event = $stmt->fetch();

if (!$event) {
    set_flash_message('error', 'Event not found.');
    header("Location: events.html");
    exit();
}

$page_title = $event['title'];

// Authorization check for non-approved events:
// Only platform Admin or the host who created the event can preview unpublished/pending/draft events
$is_admin = is_logged_in() && current_user_role() === 'admin';
$is_owner_host = is_logged_in() && current_user_role() === 'host' && current_user_id() == $event['host_id'];

if ($event['status'] !== 'Approved' && !$is_admin && !$is_owner_host) {
    set_flash_message('error', 'This event is currently under review or not publicly available.');
    header("Location: events.html");
    exit();
}

// Booking eligibility checks
$now = new DateTime();
$deadline = new DateTime($event['registration_deadline']);
$deadline_passed = ($now > $deadline);
$is_sold_out = ($event['available_tickets'] <= 0);
$can_book = ($event['status'] === 'Approved') && !$deadline_passed && !$is_sold_out;

$booking_errors = [];

// Handle Booking Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'book_ticket') {
    // Require login
    if (!is_logged_in()) {
        set_flash_message('error', 'Please log in before booking tickets.');
        header("Location: login.html");
        exit();
    }

    // Only role 'user' can book tickets
    if (current_user_role() !== 'user') {
        $booking_errors[] = 'Only standard User accounts can book tickets. Administrators and Hosts cannot book.';
    }

    $token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($token)) {
        $booking_errors[] = 'Security token invalid. Please refresh and try again.';
    }

    $quantity = isset($_POST['quantity']) ? (int)$_POST['quantity'] : 0;
    $payment_method = sanitize_input($_POST['payment_method'] ?? 'Simulated Card');

    if ($quantity < 1) {
        $booking_errors[] = 'Please select at least 1 ticket.';
    } elseif ($quantity > 10) {
        $booking_errors[] = 'Maximum 10 tickets allowed per transaction.';
    }

    if ($deadline_passed) {
        $booking_errors[] = 'Registration deadline for this event has already passed.';
    }

    if (empty($booking_errors) && $can_book) {
        try {
            // DATABASE TRANSACTION TO PREVENT OVERSELLING (ACID)
            $pdo->beginTransaction();

            // Pessimistic locking: SELECT ... FOR UPDATE
            $lock_stmt = $pdo->prepare("SELECT available_tickets, ticket_price, status, registration_deadline 
                                        FROM events 
                                        WHERE id = ? FOR UPDATE");
            $lock_stmt->execute([$event_id]);
            $locked_event = $lock_stmt->fetch();

            if (!$locked_event || $locked_event['status'] !== 'Approved') {
                throw new Exception('This event is no longer available for booking.');
            }

            if (new DateTime() > new DateTime($locked_event['registration_deadline'])) {
                throw new Exception('The registration deadline passed while processing.');
            }

            if ($locked_event['available_tickets'] < $quantity) {
                throw new Exception('Not enough tickets available. Only ' . $locked_event['available_tickets'] . ' ticket(s) left.');
            }

            $total_amount = $quantity * (float)$locked_event['ticket_price'];
            $booking_ref = generate_booking_reference();
            $payment_ref = generate_payment_reference();
            $user_id = current_user_id();

            // 1. Insert Booking Record
            $book_insert = $pdo->prepare("INSERT INTO bookings 
                (booking_reference, user_id, event_id, quantity, total_amount, booking_status, payment_status, created_at) 
                VALUES (?, ?, ?, ?, ?, 'Confirmed', 'Paid', NOW())");
            $book_insert->execute([$booking_ref, $user_id, $event_id, $quantity, $total_amount]);
            $booking_id = $pdo->lastInsertId();

            // Save payment record
            $pay_insert = $pdo->prepare("INSERT INTO payments 
                (booking_id, payment_reference, amount, payment_method, payment_status, created_at) 
                VALUES (?, ?, ?, ?, 'Paid', NOW())");
            $pay_insert->execute([$booking_id, $payment_ref, $total_amount, $payment_method]);

            // 3. Deduct available tickets
            $deduct_stmt = $pdo->prepare("UPDATE events 
                                          SET available_tickets = available_tickets - ? 
                                          WHERE id = ?");
            $deduct_stmt->execute([$quantity, $event_id]);

            // 4. Log the transaction

            // Commit the transaction
            $pdo->commit();

            set_flash_message('success', 'Booking Confirmed! Booking Reference: ' . $booking_ref);
            header("Location: user/booking-details.html?id=" . $booking_id);
            exit();

        } catch (Exception $e) {
            $pdo->rollBack();
            $booking_errors[] = $e->getMessage();
        }
    }
}

require __DIR__ . '/includes/header.php';
?>

<center>
    <p><a href="events.html">&larr; Back to All Events</a></p>

    <?php if ($event['status'] !== 'Approved'): ?>
        <p><mark><strong>[Host/Admin Preview]</strong> Event Status: <strong><?php echo e($event['status']); ?></strong> (Not publicly visible)</mark></p>
    <?php endif; ?>

    <h2><?php echo e($event['title']); ?></h2>
    <p>Category: <strong><?php echo e($event['category']); ?></strong> | Organized by: <strong><?php echo e($event['host_name']); ?></strong></p>

    <hr>

    <!-- Event Image and Main Information -->
    <table border="1" cellpadding="10" cellspacing="0" width="85%">
        <tr>
            <?php if (!empty($event['image']) && file_exists(__DIR__ . '/' . $event['image'])): ?>
                <td width="35%" align="center" valign="middle">
                    <img class="event-cover-large" src="<?php echo e($event['image']); ?>" alt="<?php echo e($event['title']); ?> cover" width="280">
                </td>
            <?php endif; ?>
            <td valign="top">
                <h3>Event Overview</h3>
                <p><?php echo nl2br(e($event['description'])); ?></p>
                
                <table border="1" cellpadding="6" cellspacing="0" width="100%">
                    <tr>
                        <td width="30%"><strong>Date:</strong></td>
                        <td><?php echo format_date($event['event_date']); ?></td>
                    </tr>
                    <tr>
                        <td><strong>Time:</strong></td>
                        <td><?php echo format_time($event['start_time']); ?> &ndash; <?php echo format_time($event['end_time']); ?></td>
                    </tr>
                    <tr>
                        <td><strong>Location / Venue:</strong></td>
                        <td><?php echo e($event['location']); ?></td>
                    </tr>
                    <tr>
                        <td><strong>Ticket Price:</strong></td>
                        <td><strong><?php echo $event['ticket_price'] > 0 ? format_price($event['ticket_price']) : 'FREE'; ?></strong></td>
                    </tr>
                    <tr>
                        <td><strong>Tickets Available:</strong></td>
                        <td>
                            <?php if ($event['available_tickets'] > 0): ?>
                                <strong><?php echo $event['available_tickets']; ?></strong> / <?php echo $event['total_tickets']; ?>
                            <?php else: ?>
                                <mark><strong>SOLD OUT</strong></mark>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <td><strong>Registration Deadline:</strong></td>
                        <td>
                            <?php echo date('F j, Y g:i A', strtotime($event['registration_deadline'])); ?>
                            <?php if ($deadline_passed): ?>
                                <br><mark>Deadline has passed</mark>
                            <?php endif; ?>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    <br><br>

    <!-- Ticket Booking Section -->
    <section>
        <fieldset>
            <legend><h3>Book Your Tickets</h3></legend>

            <?php if (!empty($booking_errors)): ?>
                <div>
                    <?php foreach ($booking_errors as $b_err): ?>
                        <p><mark><strong>[Booking Alert]</strong> <?php echo e($b_err); ?></mark></p>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php if (!$can_book): ?>
                <?php if ($is_sold_out): ?>
                    <p><mark><strong>This event is currently sold out.</strong> No more tickets are available.</mark></p>
                <?php elseif ($deadline_passed): ?>
                    <p><mark><strong>Registration Closed.</strong> The registration deadline for this event has passed.</mark></p>
                <?php else: ?>
                    <p><mark>Ticket booking is not currently open for this event.</mark></p>
                <?php endif; ?>

            <?php elseif (!is_logged_in()): ?>
                <p>You must be signed in as an EventriX User to purchase tickets.</p>
                <p>
                    <a href="login.html"><strong>Sign In to Book Tickets</strong></a>
                    <a href="login.html?tab=signup">Create Free Account</a>
                </p>

            <?php elseif (current_user_role() !== 'user'): ?>
                <p>
                    <em>Note: You are currently signed in as a <strong><?php echo ucfirst(e(current_user_role())); ?></strong>.<br>
                    Only standard User accounts can book event tickets. Please log in with a User account to proceed.</em>
                </p>

            <?php else: ?>
                <!-- Active User Booking Form -->
                <form action="event-details.html?id=<?php echo $event['id']; ?>" method="POST" id="booking-form" onsubmit="return confirmBooking()">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="action" value="book_ticket">

                    <p>
                        <label for="ticket_quantity"><strong>Select Number of Tickets:</strong></label><br>
                        <input type="number" id="ticket_quantity" name="quantity" min="1" 
                               max="<?php echo min(10, $event['available_tickets']); ?>" value="1" required 
                               oninput="calculateTotalPrice()">
                        <small>(Maximum 10 tickets per booking)</small>
                    </p>

                    <p>
                        <strong>Price Per Ticket:</strong> <?php echo format_price($event['ticket_price']); ?><br>
                        <strong>Total Amount:</strong> <span id="display-total-price"><strong><?php echo format_price($event['ticket_price']); ?></strong></span>
                    </p>

                    <p>
                        <label for="payment_method"><strong>Select Payment Method:</strong></label><br>
                        <select id="payment_method" name="payment_method" required>
                            <option value="bKash">bKash</option>
                            <option value="Nagad">Nagad</option>
                            <option value="Rocket">Rocket</option>
                            <option value="Card Payment">Card Payment</option>
                            <option value="Cash / Pay at Venue">Pay at Venue</option>
                        </select>
                        <br><small><em>Academic Simulation: No actual money or banking details required.</em></small>
                    </p>

                    <p>
                        <label>
                            <input type="checkbox" id="confirm_agree" required>
                            I confirm this ticket booking and agree to attend the event.
                        </label>
                    </p>

                    <p>
                        <button type="submit"><strong>Confirm Booking &amp; Pay Now</strong></button>
                    </p>
                </form>

                <script>
                var ticketPrice = <?php echo (float)$event['ticket_price']; ?>;

                function calculateTotalPrice() {
                    var qtyInput = document.getElementById('ticket_quantity');
                    var qty = parseInt(qtyInput.value) || 1;
                    if (qty < 1) qty = 1;
                    var max = parseInt(qtyInput.getAttribute('max')) || 10;
                    if (qty > max) {
                        qty = max;
                        qtyInput.value = max;
                    }
                    var total = (qty * ticketPrice).toFixed(2);
                    document.getElementById('display-total-price').innerHTML = '<strong>৳' + total + '</strong>';
                }

                function confirmBooking() {
                    var qty = document.getElementById('ticket_quantity').value;
                    var agree = document.getElementById('confirm_agree').checked;
                    if (!agree) {
                        alert('Please check the confirmation box before proceeding.');
                        return false;
                    }
                    return confirm('Are you sure you want to confirm booking for ' + qty + ' ticket(s)?');
                }
                </script>
            <?php endif; ?>
        </fieldset>
    </section>
</center>

<?php require __DIR__ . '/includes/footer.php'; ?>
