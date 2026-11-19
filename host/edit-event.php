<?php
// ===================================================
// EventriX - Edit Event Page (Host)
// Modify event details, update quotas, and upload replacement image
// ===================================================

$base_path = '../';
$page_title = 'Edit Event';

require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

// Strict Authorization: Host role only
require_role('host', $base_path);

$host_id = current_user_id();
$event_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($event_id <= 0) {
    set_flash_message('error', 'Invalid event ID.');
    header("Location: manage-events.html");
    exit();
}

// Fetch event with strict host ownership
$stmt = $pdo->prepare("SELECT * FROM events WHERE id = ? AND host_id = ? LIMIT 1");
$stmt->execute([$event_id, $host_id]);
$event = $stmt->fetch();

if (!$event) {
    set_flash_message('error', 'Event not found or you do not have permission to edit it.');
    header("Location: manage-events.html");
    exit();
}

// Calculate tickets already sold
$tickets_sold = $event['total_tickets'] - $event['available_tickets'];
$errors = [];

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($token)) {
        $errors[] = 'Security token invalid. Please refresh the page.';
    } else {
        $title = sanitize_input($_POST['title'] ?? '');
        $description = sanitize_input($_POST['description'] ?? '');
        $category = sanitize_input($_POST['category'] ?? '');
        $event_date = sanitize_input($_POST['event_date'] ?? '');
        $start_time = sanitize_input($_POST['start_time'] ?? '');
        $end_time = sanitize_input($_POST['end_time'] ?? '');
        $location = sanitize_input($_POST['location'] ?? '');
        $ticket_price = (float)($_POST['ticket_price'] ?? 0.00);
        $new_total_tickets = (int)($_POST['total_tickets'] ?? 0);
        $registration_deadline = sanitize_input($_POST['registration_deadline'] ?? '');
        $status = sanitize_input($_POST['status'] ?? $event['status']);

        // Validations
        if (empty($title) || empty($description) || empty($category) || empty($event_date) || 
            empty($start_time) || empty($end_time) || empty($location) || empty($registration_deadline)) {
            $errors[] = 'Please fill out all required fields.';
        }

        if ($new_total_tickets < $tickets_sold) {
            $errors[] = "Total capacity cannot be less than tickets already sold ({$tickets_sold} tickets).";
        }

        if ($start_time >= $end_time) {
            $errors[] = 'Event ending time must be after start time.';
        }

        $deadline_dt = str_replace('T', ' ', $registration_deadline);
        $event_dt = $event_date . ' ' . $start_time;
        if (strtotime($deadline_dt) > strtotime($event_dt)) {
            $errors[] = 'Registration deadline cannot be after event start time.';
        }

        // Allowed statuses for host to set
        $allowed_statuses = ['Draft', 'Pending Approval', 'Completed', 'Cancelled'];
        if ($event['status'] === 'Approved') {
            $allowed_statuses[] = 'Approved'; // Can maintain approved if already approved
        }
        if (!in_array($status, $allowed_statuses, true)) {
            $status = $event['status'];
        }

        // Handle Image Replacement
        $image_path = $event['image'];
        if (isset($_FILES['event_image']) && $_FILES['event_image']['error'] !== UPLOAD_ERR_NO_FILE) {
            $file = $_FILES['event_image'];
            if ($file['error'] !== UPLOAD_ERR_OK) {
                $errors[] = 'Failed to upload replacement image.';
            } else {
                $max_size = 2 * 1024 * 1024;
                if ($file['size'] > $max_size) {
                    $errors[] = 'Image exceeds 2MB limit.';
                } else {
                    $allowed_extensions = ['jpg', 'jpeg', 'png', 'webp'];
                    $allowed_mimes = ['image/jpeg', 'image/png', 'image/webp'];

                    $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                    $finfo = finfo_open(FILEINFO_MIME_TYPE);
                    $file_mime = finfo_file($finfo, $file['tmp_name']);
                    finfo_close($finfo);

                    if (!in_array($file_ext, $allowed_extensions, true) || !in_array($file_mime, $allowed_mimes, true)) {
                        $errors[] = 'Invalid image format. Allowed: JPG, PNG, WEBP.';
                    } else {
                        $safe_filename = 'event_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $file_ext;
                        $target_dir = __DIR__ . '/../uploads/events/';
                        $target_file = $target_dir . $safe_filename;

                        if (move_uploaded_file($file['tmp_name'], $target_file)) {
                            // Remove old image
                            if (!empty($event['image']) && file_exists(__DIR__ . '/../' . $event['image'])) {
                                unlink(__DIR__ . '/../' . $event['image']);
                            }
                            $image_path = 'uploads/events/' . $safe_filename;
                        } else {
                            $errors[] = 'Failed to store image on server.';
                        }
                    }
                }
            }
        }

        // Update database
        if (empty($errors)) {
            // Recalculate available tickets based on new total capacity
            $new_available = $new_total_tickets - $tickets_sold;

            $upd = $pdo->prepare("UPDATE events SET 
                title = ?, description = ?, category = ?, image = ?, 
                event_date = ?, start_time = ?, end_time = ?, location = ?, 
                ticket_price = ?, total_tickets = ?, available_tickets = ?, 
                registration_deadline = ?, status = ? 
                WHERE id = ? AND host_id = ?");

            $upd->execute([
                $title,
                $description,
                $category,
                $image_path,
                $event_date,
                $start_time,
                $end_time,
                $location,
                $ticket_price,
                $new_total_tickets,
                $new_available,
                $deadline_dt,
                $status,
                $event_id,
                $host_id
            ]);

            set_flash_message('success', "Event '{$title}' updated successfully.");
            header("Location: manage-events.html");
            exit();
        }
    }
}

require __DIR__ . '/../includes/header.php';
?>

<center>
    <h2>Edit Event: <?php echo e($event['title']); ?></h2>
    <p><a href="manage-events.html">&larr; Back to My Events</a> | <a href="manage-bookings.html?event_id=<?php echo $event['id']; ?>">View Attendees</a></p>

    <?php if (!empty($errors)): ?>
        <div>
            <?php foreach ($errors as $err): ?>
                <p><mark><strong>[Error]</strong> <?php echo e($err); ?></mark></p>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <hr>

    <fieldset>
        <legend><h3>Update Event Information</h3></legend>
        <form action="edit-event.html?id=<?php echo $event['id']; ?>" method="POST" enctype="multipart/form-data">
            <?php echo csrf_field(); ?>

            <p>
                <label for="title"><strong>Event Title:</strong></label><br>
                <input type="text" id="title" name="title" required size="50"
                       value="<?php echo e($event['title']); ?>">
            </p>

            <p>
                <label for="category"><strong>Category:</strong></label><br>
                <select id="category" name="category" required>
                    <?php
                    $preset_cats = ['Technology', 'Music', 'Business', 'Sports', 'Workshop', 'Arts & Culture', 'Education', 'Community'];
                    foreach ($preset_cats as $c) {
                        $sel = ($event['category'] === $c) ? 'selected' : '';
                        echo "<option value='" . e($c) . "' {$sel}>" . e($c) . "</option>";
                    }
                    ?>
                </select>
            </p>

            <p>
                <label for="description"><strong>Event Description:</strong></label><br>
                <textarea id="description" name="description" rows="6" cols="52" required><?php echo e($event['description']); ?></textarea>
            </p>

            <p>
                <strong>Current Cover Image:</strong><br>
                <?php if (!empty($event['image']) && file_exists(__DIR__ . '/../' . $event['image'])): ?>
                    <img src="../<?php echo e($event['image']); ?>" width="180"><br>
                <?php else: ?>
                    <em>No image currently uploaded.</em><br>
                <?php endif; ?>
                <label for="event_image"><strong>Replace Cover Image (Optional):</strong></label><br>
                <input type="file" id="event_image" name="event_image" accept="image/jpeg,image/png,image/webp">
            </p>

            <table border="1" cellpadding="6" cellspacing="0" width="100%">
                <tr>
                    <td>
                        <label for="event_date"><strong>Date:</strong></label><br>
                        <input type="date" id="event_date" name="event_date" required
                               value="<?php echo e($event['event_date']); ?>">
                    </td>
                    <td>
                        <label for="start_time"><strong>Start Time:</strong></label><br>
                        <input type="time" id="start_time" name="start_time" required
                               value="<?php echo e($event['start_time']); ?>">
                    </td>
                    <td>
                        <label for="end_time"><strong>End Time:</strong></label><br>
                        <input type="time" id="end_time" name="end_time" required
                               value="<?php echo e($event['end_time']); ?>">
                    </td>
                </tr>
            </table>

            <p>
                <label for="location"><strong>Venue Location:</strong></label><br>
                <input type="text" id="location" name="location" required size="50"
                       value="<?php echo e($event['location']); ?>">
            </p>

            <table border="1" cellpadding="6" cellspacing="0" width="100%">
                <tr>
                    <td>
                        <label for="ticket_price"><strong>Ticket Price (৳ BDT):</strong></label><br>
                        <input type="number" id="ticket_price" name="ticket_price" min="0" step="0.01" required
                               value="<?php echo e($event['ticket_price']); ?>">
                    </td>
                    <td>
                        <label for="total_tickets"><strong>Total Ticket Capacity:</strong></label><br>
                        <input type="number" id="total_tickets" name="total_tickets" min="<?php echo $tickets_sold; ?>" required
                               value="<?php echo e($event['total_tickets']); ?>">
                        <br><small>(Already sold: <strong><?php echo $tickets_sold; ?></strong>)</small>
                    </td>
                </tr>
            </table>

            <p>
                <label for="registration_deadline"><strong>Registration Deadline:</strong></label><br>
                <input type="datetime-local" id="registration_deadline" name="registration_deadline" required
                       value="<?php echo date('Y-m-d\TH:i', strtotime($event['registration_deadline'])); ?>">
            </p>

            <p>
                <label for="status"><strong>Event Status:</strong></label><br>
                <select id="status" name="status">
                    <?php if ($event['status'] === 'Approved'): ?>
                        <option value="Approved" selected>Approved / Published</option>
                    <?php endif; ?>
                    <option value="Pending Approval" <?php echo ($event['status'] === 'Pending Approval') ? 'selected' : ''; ?>>Pending Admin Approval</option>
                    <option value="Draft" <?php echo ($event['status'] === 'Draft') ? 'selected' : ''; ?>>Draft</option>
                    <option value="Completed" <?php echo ($event['status'] === 'Completed') ? 'selected' : ''; ?>>Completed</option>
                    <option value="Cancelled" <?php echo ($event['status'] === 'Cancelled') ? 'selected' : ''; ?>>Cancelled</option>
                </select>
            </p>

            <p>
                <button type="submit"><strong>Save Changes</strong></button>
            </p>
        </form>
    </fieldset>
</center>

<?php require __DIR__ . '/../includes/footer.php'; ?>
