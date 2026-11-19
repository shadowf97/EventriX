<?php
// ===================================================
// EventriX - Host Event Statistics & Sales Analytics
// Event-wise ticket sales, revenue generation, and occupancy rates
// ===================================================

$base_path = '../';
$page_title = 'Sales Statistics';

require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

// Strict Authorization: Host role only
require_role('host', $base_path);

$host_id = current_user_id();

// Event-wise statistics query
$query = "SELECT e.id, e.title, e.category, e.event_date, e.ticket_price, e.total_tickets, e.available_tickets, e.status,
                 COALESCE(SUM(CASE WHEN b.booking_status = 'Confirmed' THEN b.quantity ELSE 0 END), 0) AS tickets_sold,
                 COALESCE(SUM(CASE WHEN b.booking_status = 'Confirmed' THEN b.total_amount ELSE 0 END), 0) AS total_revenue,
                 COUNT(DISTINCT CASE WHEN b.booking_status = 'Confirmed' THEN b.id ELSE NULL END) AS total_orders
          FROM events e
          LEFT JOIN bookings b ON e.id = b.event_id
          WHERE e.host_id = ?
          GROUP BY e.id
          ORDER BY total_revenue DESC, tickets_sold DESC";

$stmt = $pdo->prepare($query);
$stmt->execute([$host_id]);
$stats = $stmt->fetchAll();

// Overall Cumulative Totals
$grand_total_tickets = 0;
$grand_tickets_sold = 0;
$grand_revenue = 0;
$grand_orders = 0;

foreach ($stats as $row) {
    $grand_total_tickets += (int)$row['total_tickets'];
    $grand_tickets_sold += (int)$row['tickets_sold'];
    $grand_revenue += (float)$row['total_revenue'];
    $grand_orders += (int)$row['total_orders'];
}

$overall_occupancy = ($grand_total_tickets > 0) ? round(($grand_tickets_sold / $grand_total_tickets) * 100, 1) : 0;

require __DIR__ . '/../includes/header.php';
?>

<center>
    <h2>Event-Wise Sales Statistics</h2>
    <p>Detailed performance analytics, occupancy rates, and revenue generation for each of your hosted events.</p>

    <p>
        <a href="dashboard.html">&larr; Return to Dashboard</a> | 
        <a href="manage-events.html">My Events</a> | 
        <a href="manage-bookings.html">Attendee List</a>
    </p>

    <hr>

    <!-- Cumulative Metrics Summary -->
    <table border="1" cellpadding="8" cellspacing="0">
        <tr align="center">
            <th width="160">Total Events Hosted</th>
            <th width="160">Total Capacity</th>
            <th width="160">Total Tickets Sold</th>
            <th width="160">Average Occupancy</th>
            <th width="180">Total Revenue</th>
        </tr>
        <tr align="center">
            <td><h3><?php echo count($stats); ?></h3></td>
            <td><h3><?php echo $grand_total_tickets; ?></h3></td>
            <td><h3><?php echo $grand_tickets_sold; ?></h3></td>
            <td><h3><?php echo $overall_occupancy; ?>%</h3></td>
            <td><h3><mark><?php echo format_price($grand_revenue); ?></mark></h3></td>
        </tr>
    </table>

    <br><br>

    <!-- Event-by-Event Breakdown Table -->
    <section>
        <h3>Individual Event Performance Breakdown</h3>
        <?php if (!empty($stats)): ?>
            <table border="1" cellpadding="8" cellspacing="0" width="95%">
                <thead>
                    <tr>
                        <th>Event Title</th>
                        <th>Category</th>
                        <th>Event Date</th>
                        <th>Ticket Price</th>
                        <th>Total Capacity</th>
                        <th>Tickets Sold</th>
                        <th>Available Left</th>
                        <th>Occupancy Rate</th>
                        <th>Confirmed Orders</th>
                        <th>Event Revenue</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($stats as $item): ?>
                        <?php 
                            $occupancy = ($item['total_tickets'] > 0) ? round(($item['tickets_sold'] / $item['total_tickets']) * 100, 1) : 0;
                        ?>
                        <tr>
                            <td>
                                <strong><a href="../event-details.html?id=<?php echo $item['id']; ?>"><?php echo e($item['title']); ?></a></strong><br>
                                <small>[Status: <?php echo e($item['status']); ?>]</small>
                            </td>
                            <td><?php echo e($item['category']); ?></td>
                            <td><?php echo format_date($item['event_date']); ?></td>
                            <td><?php echo format_price($item['ticket_price']); ?></td>
                            <td align="center"><?php echo $item['total_tickets']; ?></td>
                            <td align="center"><strong><?php echo $item['tickets_sold']; ?></strong></td>
                            <td align="center"><?php echo $item['available_tickets']; ?></td>
                            <td align="center">
                                <strong><?php echo $occupancy; ?>%</strong>
                                <br>
                                <progress value="<?php echo $item['tickets_sold']; ?>" max="<?php echo $item['total_tickets']; ?>"></progress>
                            </td>
                            <td align="center"><?php echo $item['total_orders']; ?></td>
                            <td align="center"><h3><?php echo format_price($item['total_revenue']); ?></h3></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p><em>No event statistics recorded yet. Start by creating an event!</em></p>
        <?php endif; ?>
    </section>
</center>

<?php require __DIR__ . '/../includes/footer.php'; ?>
