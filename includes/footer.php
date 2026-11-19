<?php
// Shared footer

if (!isset($base_path)) {
    $base_path = '';
}
?>
    </main>

    <br>
    <hr>
    <footer>
        <p><strong>EventriX</strong> &mdash; Every Event. One Experience.</p>
        <p>
            <a href="<?php echo $base_path; ?>index.html">Home</a> |
            <a href="<?php echo $base_path; ?>events.html">Browse Events</a> |
            <a href="<?php echo $base_path; ?>about.html">About</a> |
            <a href="<?php echo $base_path; ?>contact.html">Contact Support</a>
        </p>
        <p><small>&copy; <?php echo date('Y'); ?> EventriX Platform. All rights reserved.</small></p>
    </footer>
</body>
</html>
