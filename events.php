<?php
// ===================================================
// EventriX - Browse Events Page
// Search by name, filter by category and date
// ===================================================

$base_path = '';
$page_title = 'Browse Events';

require_once __DIR__ . '/includes/database.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

// Get search and filter parameters
$search = sanitize_input($_GET['search'] ?? '');
$category = sanitize_input($_GET['category'] ?? '');
$event_date = sanitize_input($_GET['date'] ?? '');

// Base query for approved events only
$query = "SELECT e.*, u.full_name AS host_name 
          FROM events e 
          JOIN users u ON e.host_id = u.id 
          WHERE e.status = 'Approved'";
$params = [];

// Apply search filter (title or description or location)
if (!empty($search)) {
    $query .= " AND (e.title LIKE ? OR e.description LIKE ? OR e.location LIKE ?)";
    $like = "%{$search}%";
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}

// Apply category filter
if (!empty($category)) {
    $query .= " AND e.category = ?";
    $params[] = $category;
}

// Apply date filter
if (!empty($event_date)) {
    $query .= " AND e.event_date = ?";
    $params[] = $event_date;
}

// Order by upcoming date
$query .= " ORDER BY e.event_date ASC, e.start_time ASC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$events = $stmt->fetchAll();

// Get list of all distinct categories for filter dropdown
$cat_stmt = $pdo->query("SELECT DISTINCT category FROM events WHERE status = 'Approved' ORDER BY category ASC");
$all_categories = $cat_stmt->fetchAll(PDO::FETCH_COLUMN);

require __DIR__ . '/includes/header.php';
?>

<center>
    <h2>Discover Events</h2>
    <p>Find exciting conferences, live music, sports, and educational workshops.</p>

    <!-- Search & Filter Form -->
    <fieldset>
        <legend><strong>Search &amp; Filter Events</strong></legend>
        <form action="events.html" method="GET">
            <p>
                <label for="search"><strong>Keyword / Title / City:</strong></label>
                <input type="text" id="search" name="search" value="<?php echo e($search); ?>" placeholder="Search by title, location..." size="25">
                &nbsp;&nbsp;

                <label for="category"><strong>Category:</strong></label>
                <select id="category" name="category">
                    <option value="">-- All Categories --</option>
                    <?php foreach ($all_categories as $cat): ?>
                        <option value="<?php echo e($cat); ?>" <?php echo ($category === $cat) ? 'selected' : ''; ?>>
                            <?php echo e($cat); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                &nbsp;&nbsp;

                <label for="date"><strong>Date:</strong></label>
                <input type="date" id="date" name="date" value="<?php echo e($event_date); ?>">
                &nbsp;&nbsp;

                <button type="submit"><strong>Filter Events</strong></button>
                <a href="events.html">Reset filters</a>
            </p>
        </form>
    </fieldset>

    <br><br>

    <!-- Events Results Table -->
    <section>
        <p>Showing <strong><?php echo count($events); ?></strong> event(s) matching your criteria.</p>

        <?php if (!empty($events)): ?>
            <table border="1" cellpadding="8" cellspacing="0" width="95%">
                <thead>
                    <tr>
                        <th>Cover</th>
                        <th>Event Title</th>
                        <th>Category</th>
                        <th>Date & Time</th>
                        <th>Location</th>
                        <th>Host</th>
                        <th>Price</th>
                        <th>Availability</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($events as $event): ?>
                        <tr>
                            <td align="center">
                                <?php if (!empty($event['image']) && file_exists(__DIR__ . '/' . $event['image'])): ?>
                                    <img class="event-cover-thumb" src="<?php echo e($event['image']); ?>" alt="<?php echo e($event['title']); ?> cover" width="80" height="50">
                                <?php else: ?>
                                    <small>[No Image]</small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <strong><a href="event-details.html?id=<?php echo $event['id']; ?>"><?php echo e($event['title']); ?></a></strong>
                                <br>
                                <small><?php echo e(substr($event['description'], 0, 80)); ?>...</small>
                            </td>
                            <td><?php echo e($event['category']); ?></td>
                            <td>
                                <?php echo format_date($event['event_date']); ?><br>
                                <small><?php echo format_time($event['start_time']); ?> - <?php echo format_time($event['end_time']); ?></small>
                            </td>
                            <td><?php echo e($event['location']); ?></td>
                            <td><?php echo e($event['host_name']); ?></td>
                            <td>
                                <?php echo $event['ticket_price'] > 0 ? format_price($event['ticket_price']) : '<strong>Free</strong>'; ?>
                            </td>
                            <td>
                                <?php if ($event['available_tickets'] > 0): ?>
                                    <strong><?php echo $event['available_tickets']; ?></strong> / <?php echo $event['total_tickets']; ?>
                                <?php else: ?>
                                    <mark>Sold Out</mark>
                                <?php endif; ?>
                            </td>
                            <td align="center">
                                <a href="event-details.html?id=<?php echo $event['id']; ?>">Details &amp; Book</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p><em>No events found matching your search. Try adjusting your filters or browse all events!</em></p>
        <?php endif; ?>
    </section>
</center>

<?php require __DIR__ . '/includes/footer.php'; ?>
