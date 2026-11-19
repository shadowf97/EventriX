<?php
// ===================================================
// EventriX - Contact Support Page
// Academic support contact details & inquiry message form
// ===================================================

$base_path = '';
$page_title = 'Contact Support';

require_once __DIR__ . '/includes/database.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

$message_sent = false;
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($token)) {
        $errors[] = 'Security token invalid. Please refresh the page.';
    } else {
        $name = sanitize_input($_POST['contact_name'] ?? '');
        $email = sanitize_input($_POST['contact_email'] ?? '');
        $subject = sanitize_input($_POST['contact_subject'] ?? '');
        $body = sanitize_input($_POST['contact_body'] ?? '');

        if (empty($name) || empty($email) || empty($subject) || empty($body)) {
            $errors[] = 'Please fill out all fields in the contact form.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Please enter a valid email address.';
        } else {
            $message_sent = true;
            set_flash_message('success', "Thanks, {$name}. Your message was sent.");
        }
    }
}

require __DIR__ . '/includes/header.php';
?>

<center>
    <h2>Contact Support</h2>
    <p>Need help with an event or booking?</p>

    <hr>

    <table border="1" cellpadding="8" cellspacing="0" width="80%">
        <tr>
            <th width="35%">Contact Channel</th>
            <th>Details</th>
        </tr>
        <tr>
            <td><strong>Support Email</strong></td>
            <td>support@eventrix.com / admin@eventrix.com</td>
        </tr>
        <tr>
            <td><strong>Operating Hours</strong></td>
            <td>Sunday &ndash; Thursday: 9:00 AM &ndash; 6:00 PM (BST)</td>
        </tr>
        <tr>
            <td><strong>Department</strong></td>
            <td>EventriX Bangladesh Support Desk</td>
        </tr>
        <tr>
            <td><strong>Campus Office</strong></td>
            <td>Dhanmondi, Dhaka, Bangladesh</td>
        </tr>
    </table>

    <br><br>

    <!-- Support Message Form -->
    <fieldset>
        <legend><h3>Send Us a Direct Message</h3></legend>

        <?php if (!empty($errors)): ?>
            <div>
                <?php foreach ($errors as $err): ?>
                    <p><mark><strong>[Error]</strong> <?php echo e($err); ?></mark></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if ($message_sent): ?>
            <p><mark><strong>Message Sent!</strong> We have received your submission.</mark></p>
            <p><a href="contact.html">Send Another Message</a></p>
        <?php else: ?>
            <form action="contact.html" method="POST">
                <?php echo csrf_field(); ?>

                <p>
                    <label for="contact_name"><strong>Your Name:</strong></label><br>
                    <input type="text" id="contact_name" name="contact_name" required size="40"
                           value="<?php echo is_logged_in() ? e(current_user_name()) : (isset($_POST['contact_name']) ? e($_POST['contact_name']) : ''); ?>">
                </p>

                <p>
                    <label for="contact_email"><strong>Email Address:</strong></label><br>
                    <input type="email" id="contact_email" name="contact_email" required size="40"
                           value="<?php echo isset($_SESSION['email']) ? e($_SESSION['email']) : (isset($_POST['contact_email']) ? e($_POST['contact_email']) : ''); ?>">
                </p>

                <p>
                    <label for="contact_subject"><strong>Subject:</strong></label><br>
                    <input type="text" id="contact_subject" name="contact_subject" required size="40"
                           value="<?php echo isset($_POST['contact_subject']) ? e($_POST['contact_subject']) : ''; ?>"
                           placeholder="e.g. Booking assistance, host verification">
                </p>

                <p>
                    <label for="contact_body"><strong>Your Message:</strong></label><br>
                    <textarea id="contact_body" name="contact_body" rows="6" cols="42" required placeholder="Describe your inquiry..."><?php echo isset($_POST['contact_body']) ? e($_POST['contact_body']) : ''; ?></textarea>
                </p>

                <p>
                    <button type="submit"><strong>Submit Message</strong></button>
                </p>
            </form>
        <?php endif; ?>
    </fieldset>

    <br><br>
    <p><a href="index.html">&larr; Return to Homepage</a> | <a href="events.html">Browse Events</a></p>
</center>

<?php require __DIR__ . '/includes/footer.php'; ?>
