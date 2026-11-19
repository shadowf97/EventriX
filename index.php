<?php
// Home page

$base_path = '';
$page_title = 'EventriX';
$is_landing_page = true;

require_once __DIR__ . '/includes/database.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

// Featured events
$stmt = $pdo->prepare("SELECT e.*, u.full_name AS host_name 
                      FROM events e 
                      JOIN users u ON e.host_id = u.id 
                      WHERE e.status = 'Approved' AND e.event_date >= CURDATE() 
                      ORDER BY e.total_tickets DESC 
                      LIMIT 3");
$stmt->execute();
$featured_events = $stmt->fetchAll();

// Upcoming events
$stmt = $pdo->prepare("SELECT e.*, u.full_name AS host_name 
                      FROM events e 
                      JOIN users u ON e.host_id = u.id 
                      WHERE e.status = 'Approved' AND e.event_date >= CURDATE() 
                      ORDER BY e.event_date ASC, e.start_time ASC 
                      LIMIT 6");
$stmt->execute();
$upcoming_events = $stmt->fetchAll();

// Event categories
$stmt = $pdo->prepare("SELECT category, COUNT(*) as total 
                      FROM events 
                      WHERE status = 'Approved' 
                      GROUP BY category 
                      ORDER BY total DESC");
$stmt->execute();
$categories = $stmt->fetchAll();

require __DIR__ . '/includes/header.php';
?>

<div class="landing-page">
    <section class="landing-intro">
        <a class="landing-brand" href="index.html">
            <img class="site-logo" src="assets/images/eventrix-logo.png" alt="">
            <span>EventriX</span>
        </a>
        <p class="eyebrow">EVENT MANAGEMENT AND TICKETING</p>
        <h2>Find events in Bangladesh.</h2>
        <p>Book tickets. Host events.</p>
        <p>
            <a href="events.html"><i class="hgi-stroke hgi-ticket-01 ui-icon" aria-hidden="true"></i><strong>Browse All Events</strong></a>
            <?php if (!is_logged_in()): ?>
                <a href="login.html?tab=signup"><i class="hgi-stroke hgi-user ui-icon" aria-hidden="true"></i>Create Free Account</a>
            <?php else: ?>
                <a href="<?php echo current_user_role(); ?>/dashboard.html"><i class="hgi-stroke hgi-calendar-03 ui-icon" aria-hidden="true"></i>Go to My Dashboard</a>
            <?php endif; ?>
        </p>
    </section>

    <section class="landing-section">
        <h3>Explore by Category</h3>
        <p class="category-list">
            <?php if (!empty($categories)): ?>
                <?php foreach ($categories as $cat): ?>
                    <a class="category-tab" href="events.html?category=<?php echo urlencode($cat['category']); ?>">
                        <?php echo e($cat['category']); ?> <span class="category-count"><?php echo $cat['total']; ?></span>
                    </a>
                <?php endforeach; ?>
            <?php else: ?>
                <em>No categories found at this moment.</em>
            <?php endif; ?>
        </p>
    </section>

    <section class="landing-section">
        <h3>Featured Events</h3>
        <?php if (!empty($featured_events)): ?>
            <table border="1" cellpadding="8" cellspacing="0" width="90%">
                <thead>
                    <tr>
                        <th>Title</th>
                        <th>Category</th>
                        <th>Date & Time</th>
                        <th>Location</th>
                        <th>Price</th>
                        <th>Tickets Available</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($featured_events as $event): ?>
                        <tr>
                            <td><strong><?php echo e($event['title']); ?></strong></td>
                            <td><?php echo e($event['category']); ?></td>
                            <td><?php echo format_date($event['event_date']); ?><br><small><?php echo format_time($event['start_time']); ?> - <?php echo format_time($event['end_time']); ?></small></td>
                            <td><?php echo e($event['location']); ?></td>
                            <td><?php echo $event['ticket_price'] > 0 ? format_price($event['ticket_price']) : '<strong>Free</strong>'; ?></td>
                            <td><?php echo $event['available_tickets']; ?> / <?php echo $event['total_tickets']; ?></td>
                            <td>
                                <a href="event-details.html?id=<?php echo $event['id']; ?>">View &amp; Book</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p><em>No featured events right now. Check back soon!</em></p>
        <?php endif; ?>
    </section>

    <section class="landing-section">
        <h3>Upcoming Events</h3>
        <?php if (!empty($upcoming_events)): ?>
            <table border="1" cellpadding="8" cellspacing="0" width="90%">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Event Title</th>
                        <th>Category</th>
                        <th>Host</th>
                        <th>Location</th>
                        <th>Price</th>
                        <th>Status</th>
                        <th>Details</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($upcoming_events as $event): ?>
                        <tr>
                            <td><?php echo format_date($event['event_date']); ?></td>
                            <td><strong><?php echo e($event['title']); ?></strong></td>
                            <td><?php echo e($event['category']); ?></td>
                            <td><?php echo e($event['host_name']); ?></td>
                            <td><?php echo e($event['location']); ?></td>
                            <td><?php echo $event['ticket_price'] > 0 ? format_price($event['ticket_price']) : 'Free'; ?></td>
                            <td>
                                <?php if ($event['available_tickets'] > 0): ?>
                                    <small>[Tickets Available]</small>
                                <?php else: ?>
                                    <mark><strong>SOLD OUT</strong></mark>
                                <?php endif; ?>
                            </td>
                            <td>
                                <a href="event-details.html?id=<?php echo $event['id']; ?>">View Details</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <p><a href="events.html">View All Available Events &rarr;</a></p>
        <?php else: ?>
            <p><em>No upcoming events currently scheduled.</em></p>
        <?php endif; ?>
    </section>

    <section class="landing-section landing-cta">
        <h3>Host an Event</h3>
        <p>Create and manage events in one place.</p>
        <p>
            <a href="login.html?tab=signup"><i class="hgi-stroke hgi-calendar-add-01 ui-icon" aria-hidden="true"></i><strong>Get Started as Host</strong></a>
        </p>
    </section>

    <section class="landing-section">
        <h3>Why Choose EventriX?</h3>
        <table border="1" cellpadding="8" cellspacing="0">
            <tr>
                <th width="33%">For Attendees</th>
                <th width="33%">For Event Hosts</th>
                <th width="33%">For Administrators</th>
            </tr>
            <tr>
                <td valign="top">
                    <ul>
                        <li>Instant ticket booking</li>
                        <li>Live seat and ticket availability</li>
                        <li>Digital booking reference codes</li>
                        <li>Transparent order history</li>
                    </ul>
                </td>
                <td valign="top">
                    <ul>
                        <li>Easy event publishing</li>
                        <li>Attendee lists</li>
                        <li>Ticket sales tracking</li>
                        <li>Simple pricing and quotas</li>
                    </ul>
                </td>
                <td valign="top">
                    <ul>
                        <li>Event approval</li>
                        <li>User and host management</li>
                        <li>Booking overview</li>
                        <li>Payment records</li>
                    </ul>
                </td>
            </tr>
        </table>
    </section>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
