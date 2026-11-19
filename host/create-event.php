<?php
// ===================================================
// EventriX - Create Event Page (Host)
// Author new events, upload cover pictures, and set ticketing parameters
// ===================================================

$base_path = '../';
$page_title = 'Create Event';

require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

// Strict Authorization: Host role only
require_role('host', $base_path);

$host_id = current_user_id();
$errors = [];

// Handle Event Creation Form Submission
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
        $total_tickets = (int)($_POST['total_tickets'] ?? 0);
        $registration_deadline = sanitize_input($_POST['registration_deadline'] ?? '');
        $save_as = sanitize_input($_POST['save_as'] ?? 'Pending Approval'); // Draft or Pending Approval

        // Form Validations
        if (empty($title) || empty($description) || empty($category) || empty($event_date) || 
            empty($start_time) || empty($end_time) || empty($location) || empty($registration_deadline)) {
            $errors[] = 'Please fill out all required event fields.';
        }

        if ($total_tickets < 1) {
            $errors[] = 'Total tickets capacity must be at least 1.';
        }

        if ($ticket_price < 0) {
            $errors[] = 'Ticket price cannot be negative.';
        }

        // Validate dates
        $today = date('Y-m-d');
        if ($event_date < $today) {
            $errors[] = 'Event date cannot be in the past.';
        }

        if ($start_time >= $end_time) {
            $errors[] = 'Event ending time must be after starting time.';
        }

        $deadline_dt = str_replace('T', ' ', $registration_deadline);
        $event_dt = $event_date . ' ' . $start_time;
        if (strtotime($deadline_dt) > strtotime($event_dt)) {
            $errors[] = 'Registration deadline cannot be after the event start time.';
        }

        if (!in_array($save_as, ['Draft', 'Pending Approval'], true)) {
            $save_as = 'Pending Approval';
        }

        // Handle Image Upload Securely
        $image_path = null;
        if (isset($_FILES['event_image']) && $_FILES['event_image']['error'] !== UPLOAD_ERR_NO_FILE) {
            $file = $_FILES['event_image'];
            if ($file['error'] !== UPLOAD_ERR_OK) {
                $errors[] = 'Failed to upload image. Error code: ' . $file['error'];
            } else {
                $max_size = 2 * 1024 * 1024; // 2MB
                if ($file['size'] > $max_size) {
                    $errors[] = 'Cover image must not exceed 2MB in file size.';
                } else {
                    $allowed_extensions = ['jpg', 'jpeg', 'png', 'webp'];
                    $allowed_mimes = ['image/jpeg', 'image/png', 'image/webp'];

                    $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                    $finfo = finfo_open(FILEINFO_MIME_TYPE);
                    $file_mime = finfo_file($finfo, $file['tmp_name']);
                    finfo_close($finfo);

                    if (!in_array($file_ext, $allowed_extensions, true) || !in_array($file_mime, $allowed_mimes, true)) {
                        $errors[] = 'Invalid image format. Only JPG, PNG, and WEBP files are allowed.';
                    } else {
                        // Generate safe unique filename
                        $safe_filename = 'event_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $file_ext;
                        $target_dir = __DIR__ . '/../uploads/events/';
                        if (!is_dir($target_dir)) {
                            mkdir($target_dir, 0777, true);
                        }
                        $target_file = $target_dir . $safe_filename;

                        if (move_uploaded_file($file['tmp_name'], $target_file)) {
                            $image_path = 'uploads/events/' . $safe_filename;
                        } else {
                            $errors[] = 'Could not save the uploaded image to server disk.';
                        }
                    }
                }
            }
        }

        // Insert event into MySQL if no validation errors
        if (empty($errors)) {
            $available_tickets = $total_tickets;
            $stmt = $pdo->prepare("INSERT INTO events 
                (host_id, title, description, category, image, event_date, start_time, end_time, location, ticket_price, total_tickets, available_tickets, registration_deadline, status, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
            
            $stmt->execute([
                $host_id,
                $title,
                $description,
                $category,
                $image_path,
                $event_date,
                $start_time,
                $end_time,
                $location,
                $ticket_price,
                $total_tickets,
                $available_tickets,
                $deadline_dt,
                $save_as
            ]);

            $new_event_id = $pdo->lastInsertId();

            $msg = ($save_as === 'Pending Approval') 
                ? "Event '{$title}' submitted successfully! It is now pending Administrator approval before public listing."
                : "Event '{$title}' saved as Draft.";

            set_flash_message('success', $msg);
            header("Location: manage-events.html");
            exit();
        }
    }
}

require __DIR__ . '/../includes/header.php';
?>

<center>
    <h2>Create New Event</h2>
    <p>Fill out the details below to publish an experience on the EventriX network.</p>

    <p><a href="manage-events.html">&larr; Back to My Events</a> | <a href="dashboard.html">Dashboard</a></p>

    <?php if (!empty($errors)): ?>
        <div>
            <?php foreach ($errors as $err): ?>
                <p><mark><strong>[Validation Error]</strong> <?php echo e($err); ?></mark></p>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <hr>

    <fieldset>
        <legend><h3>Event Information Form</h3></legend>
        <form action="create-event.html" method="POST" enctype="multipart/form-data">
            <?php echo csrf_field(); ?>

            <p>
                <label for="title"><strong>Event Title:</strong></label><br>
                <input type="text" id="title" name="title" required size="50"
                       value="<?php echo isset($_POST['title']) ? e($_POST['title']) : ''; ?>"
                       placeholder="e.g. Annual Web Developers Summit 2026">
            </p>

            <p>
                <label for="category"><strong>Category:</strong></label><br>
                <select id="category" name="category" required>
                    <option value="">-- Select Category --</option>
                    <?php
                    $preset_cats = ['Technology', 'Music', 'Business', 'Sports', 'Workshop', 'Arts & Culture', 'Education', 'Community'];
                    foreach ($preset_cats as $c) {
                        $sel = (isset($_POST['category']) && $_POST['category'] === $c) ? 'selected' : '';
                        echo "<option value='" . e($c) . "' {$sel}>" . e($c) . "</option>";
                    }
                    ?>
                </select>
            </p>

            <p>
                <label for="description"><strong>Full Description:</strong></label><br>
                <textarea id="description" name="description" rows="6" cols="52" required placeholder="Describe what attendees can expect, key speakers, schedule, etc."><?php echo isset($_POST['description']) ? e($_POST['description']) : ''; ?></textarea>
            </p>

            <p>
                <label for="event_image"><strong>Event Cover Image (Optional):</strong></label><br>
                <input type="file" id="event_image" name="event_image" accept="image/jpeg,image/png,image/webp">
                <br><small>(Accepted formats: JPG, PNG, WEBP. Maximum file size: 2MB)</small>
            </p>

            <table border="1" cellpadding="6" cellspacing="0" width="100%">
                <tr>
                    <td>
                        <label for="event_date"><strong>Event Date:</strong></label><br>
                        <input type="date" id="event_date" name="event_date" required min="<?php echo date('Y-m-d'); ?>"
                               value="<?php echo isset($_POST['event_date']) ? e($_POST['event_date']) : ''; ?>">
                    </td>
                    <td>
                        <label for="start_time"><strong>Start Time:</strong></label><br>
                        <input type="time" id="start_time" name="start_time" required
                               value="<?php echo isset($_POST['start_time']) ? e($_POST['start_time']) : '09:00'; ?>">
                    </td>
                    <td>
                        <label for="end_time"><strong>End Time:</strong></label><br>
                        <input type="time" id="end_time" name="end_time" required
                               value="<?php echo isset($_POST['end_time']) ? e($_POST['end_time']) : '17:00'; ?>">
                    </td>
                </tr>
            </table>

            <p>
                <label for="location"><strong>Venue Location:</strong></label><br>
                <input type="text" id="location" name="location" required size="50"
                       value="<?php echo isset($_POST['location']) ? e($_POST['location']) : ''; ?>"
                       placeholder="e.g. Bangladesh Shilpakala Academy, Dhaka">
            </p>

            <table border="1" cellpadding="6" cellspacing="0" width="100%">
                <tr>
                    <td>
                        <label for="ticket_price"><strong>Ticket Price (৳ BDT):</strong></label><br>
                        <input type="number" id="ticket_price" name="ticket_price" min="0" step="0.01" required
                               value="<?php echo isset($_POST['ticket_price']) ? e($_POST['ticket_price']) : '0.00'; ?>">
                        <br><small>(Set 0.00 for free entry)</small>
                    </td>
                    <td>
                        <label for="total_tickets"><strong>Maximum Ticket Capacity:</strong></label><br>
                        <input type="number" id="total_tickets" name="total_tickets" min="1" required
                               value="<?php echo isset($_POST['total_tickets']) ? e($_POST['total_tickets']) : '100'; ?>">
                    </td>
                </tr>
            </table>

            <p>
                <label for="registration_deadline"><strong>Registration Deadline:</strong></label><br>
                <input type="datetime-local" id="registration_deadline" name="registration_deadline" required
                       value="<?php echo isset($_POST['registration_deadline']) ? e($_POST['registration_deadline']) : ''; ?>">
                <br><small>(Users cannot book tickets after this date and time)</small>
            </p>

            <p>
                <label for="save_as"><strong>Initial Publishing Action:</strong></label><br>
                <select id="save_as" name="save_as">
                    <option value="Pending Approval">Submit for Admin Approval (Recommended)</option>
                    <option value="Draft">Save as Draft (Private)</option>
                </select>
                <br><small><em>All new events require Platform Admin approval before becoming visible on the public event catalog.</em></small>
            </p>

            <p>
                <button type="submit"><strong>Submit Event</strong></button>
            </p>
        </form>
    </fieldset>
</center>

<?php require __DIR__ . '/../includes/footer.php'; ?>
