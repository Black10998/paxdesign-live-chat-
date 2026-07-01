<?php
if (!defined('ABSPATH')) exit;

global $wpdb;
$table_name = $wpdb->prefix . 'paxdesign_bookings';
$bookings = $wpdb->get_results("SELECT * FROM $table_name ORDER BY created_at DESC LIMIT 50");

function paxdesign_booking_admin_service_label($booking) {
    if (!empty($booking->service_details)) {
        $details = json_decode($booking->service_details, true);
        if (is_array($details) && !empty($details['name'])) {
            return $details['name'];
        }
    }

    if (!empty($booking->service)) {
        $services = PAXdesign_Booking::get_instance()->get_services();
        if (isset($services[$booking->service]['name'])) {
            return $services[$booking->service]['name'];
        }
        return ucfirst($booking->service);
    }

    return '—';
}
?>

<div class="wrap">
    <h1>PAXdesign Booking System</h1>
    
    <div class="card">
        <h2>Letzte Buchungen</h2>
        
        <?php if (empty($bookings)) : ?>
            <p>Noch keine Buchungen vorhanden.</p>
        <?php else : ?>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Datum</th>
                        <th>Uhrzeit</th>
                        <th>Kunde</th>
                        <th>E-Mail</th>
                        <th>Team Member</th>
                        <th>Service</th>
                        <th>Zweck</th>
                        <th>Status</th>
                        <th>Erstellt am</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($bookings as $booking) : ?>
                    <tr>
                        <td><?php echo esc_html($booking->id); ?></td>
                        <td><?php echo esc_html(date('d.m.Y', strtotime($booking->booking_date))); ?></td>
                        <td><?php echo esc_html($booking->booking_time); ?></td>
                        <td><?php echo esc_html($booking->customer_name); ?></td>
                        <td><a href="mailto:<?php echo esc_attr($booking->customer_email); ?>"><?php echo esc_html($booking->customer_email); ?></a></td>
                        <td><?php echo esc_html(ucfirst($booking->team_member)); ?></td>
                        <td><?php echo esc_html(paxdesign_booking_admin_service_label($booking)); ?></td>
                        <td><?php echo esc_html($booking->purpose); ?></td>
                        <td><?php echo esc_html($booking->status); ?></td>
                        <td><?php echo esc_html(date('d.m.Y H:i', strtotime($booking->created_at))); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>
