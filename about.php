<?php
// ===================================================
// EventriX - About Page
// Academic project information, mission, and architecture
// ===================================================

$base_path = '';
$page_title = 'About EventriX';

require_once __DIR__ . '/includes/database.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

require __DIR__ . '/includes/header.php';
?>

<center>
    <h2>About EventriX</h2>
    <p>A simple Bangladesh event and ticket platform.</p>

    <hr>

    <section>
        <h3>Project Purpose</h3>
        <p>EventriX is a Web Technology course project for managing events and tickets.</p>

        <h3>Built With</h3>
        <ul>
            <li><strong>Semantic HTML5:</strong> Structured, accessible page layouts, forms, and data tables with standard browser defaults.</li>
            <li><strong>PHP 8 (Backend Logic):</strong> Procedural, readable, and structured PHP using native sessions, PDO prepared statements, and transactional safety.</li>
            <li><strong>MySQL Database:</strong> Relational design featuring foreign key constraints, indexing, and ACID transaction support.</li>
            <li><strong>Vanilla JavaScript:</strong> Lightweight client-side form validation and dynamic tab switching.</li>
        </ul>

        <h3>The Three-Tier Role Model</h3>
        <table border="1" cellpadding="8" cellspacing="0" width="100%">
            <thead>
                <tr>
                    <th>Role</th>
                    <th>Responsibilities</th>
                    <th>Access Scope</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><strong>Administrator</strong></td>
                    <td>Event approval, account management, bookings, and payments.</td>
                    <td>Global platform control. (Private registration disabled).</td>
                </tr>
                <tr>
                    <td><strong>Host</strong></td>
                    <td>Event authoring, ticket quota configuration, pricing, registration deadlines, and event-specific attendee sales tracking.</td>
                    <td>Isolated private workspace; hosts only access their own events.</td>
                </tr>
                <tr>
                    <td><strong>User (Attendee)</strong></td>
                    <td>Event discovery, ticket booking, real-time quota verification, reference code issuance, and booking management.</td>
                    <td>Personal account and private booking history.</td>
                </tr>
            </tbody>
        </table>

        <h3>Academic Coding Standards</h3>
        <p>
            To facilitate peer review and instructor evaluation, EventriX prioritizes readability: straightforward variable naming, descriptive SQL statements, strict input escaping (preventing XSS), parameterized statements (preventing SQL Injection), and CSRF tokens for sensitive state-altering actions.
        </p>
    </section>

    <hr>
    <p><a href="index.html">&larr; Return to Homepage</a> | <a href="events.html">Browse Current Events</a></p>
</center>

<?php require __DIR__ . '/includes/footer.php'; ?>
