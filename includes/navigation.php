<?php
// Shared navigation

if (!isset($base_path)) {
    $base_path = '';
}
?>
<nav aria-label="Main navigation">
    <div class="nav-row">
        <p>
            <a href="<?php echo $base_path; ?>index.html"><i class="hgi-stroke hgi-home-01 ui-icon" aria-hidden="true"></i>Home</a> |
            <a href="<?php echo $base_path; ?>events.html"><i class="hgi-stroke hgi-ticket-01 ui-icon" aria-hidden="true"></i>Browse Events</a> |
            <a href="<?php echo $base_path; ?>about.html">About</a> |
            <a href="<?php echo $base_path; ?>contact.html">Contact</a>
        </p>
        <?php if (!is_logged_in()): ?>
            <a class="nav-auth" href="<?php echo $base_path; ?>login.html"><i class="hgi-stroke hgi-user ui-icon" aria-hidden="true"></i>Sign In / Sign Up</a>
        <?php endif; ?>
    </div>

    <?php if (is_logged_in()): ?>
        <details>
            <summary><strong>My Account</strong> — <?php echo e(current_user_name()); ?> (<?php echo e(ucfirst(current_user_role())); ?>)</summary>
            <ul>
                <?php if (current_user_role() === 'admin'): ?>
                    <li><a href="<?php echo $base_path; ?>admin/dashboard.html">Admin Dashboard</a></li>
                    <li><a href="<?php echo $base_path; ?>admin/event-approvals.html">Event Approvals</a></li>
                    <li><a href="<?php echo $base_path; ?>admin/manage-events.html">Manage Events</a></li>
                    <li><a href="<?php echo $base_path; ?>admin/manage-users.html">Manage Users</a></li>
                    <li><a href="<?php echo $base_path; ?>admin/manage-hosts.html">Manage Hosts</a></li>
                    <li><a href="<?php echo $base_path; ?>admin/manage-bookings.html">Manage Bookings</a></li>
                    <li><a href="<?php echo $base_path; ?>admin/manage-payments.html">Payments</a></li>
                <?php elseif (current_user_role() === 'host'): ?>
                    <li><a href="<?php echo $base_path; ?>host/dashboard.html">Host Dashboard</a></li>
                    <li><a href="<?php echo $base_path; ?>host/create-event.html">Create Event</a></li>
                    <li><a href="<?php echo $base_path; ?>host/manage-events.html">My Events</a></li>
                    <li><a href="<?php echo $base_path; ?>host/manage-bookings.html">Bookings</a></li>
                    <li><a href="<?php echo $base_path; ?>host/event-statistics.html">Sales Statistics</a></li>
                    <li><a href="<?php echo $base_path; ?>host/profile.html">My Profile</a></li>
                <?php else: ?>
                    <li><a href="<?php echo $base_path; ?>user/dashboard.html">My Dashboard</a></li>
                    <li><a href="<?php echo $base_path; ?>user/my-bookings.html">My Bookings</a></li>
                    <li><a href="<?php echo $base_path; ?>user/profile.html">My Profile</a></li>
                <?php endif; ?>
                <li><a href="<?php echo $base_path; ?>logout.html">Log Out</a></li>
            </ul>
        </details>
    <?php endif; ?>
</nav>
<hr>
