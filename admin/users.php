<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
// Only administrators
if ( ! current_user_can( 'manage_options' ) ) {
    wp_die( 'Access denied' );
}

global $wpdb;
$table_users = $wpdb->prefix . 'queues_users';

// Handle form submission
if ( isset( $_POST['queue_user_action'] ) && wp_verify_nonce( $_POST['_wpnonce'], 'manage_queue_users' ) ) {
    $action          = sanitize_text_field( $_POST['queue_user_action'] );
    $wp_user_id      = intval( $_POST['wp_user_id'] );
    $organization_id = intval( $_POST['organization_id'] );
    $id              = isset( $_POST['user_id'] ) ? intval( $_POST['user_id'] ) : 0;

    if ( $action === 'add' && $wp_user_id ) {
        $exists = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table_users} WHERE wp_user_id = %d", $wp_user_id ) );
        if ( ! $exists ) {
            $wpdb->insert( $table_users, [ 'wp_user_id' => $wp_user_id, 'organization_id' => $organization_id ], [ '%d','%d' ] );
            echo '<div class="notice notice-success"><p>User added.</p></div>';
        } else {
            echo '<div class="notice notice-warning"><p>User already exists.</p></div>';
        }
    }
    if ( $action === 'edit' && $id ) {
        $wpdb->update( $table_users, [ 'organization_id' => $organization_id ], [ 'id' => $id ], [ '%d' ], [ '%d' ] );
        echo '<div class="notice notice-success"><p>User updated.</p></div>';
    }
    if ( $action === 'delete' && isset( $_GET['delete_id'] ) && wp_verify_nonce( $_GET['_wpnonce'], 'delete_queue_user_' . intval( $_GET['delete_id'] ) ) ) {
        $wpdb->delete( $table_users, [ 'id' => intval( $_GET['delete_id'] ) ], [ '%d' ] );
        echo '<div class="notice notice-success"><p>User deleted.</p></div>';
    }
}

// Fetch data
$queue_users = $wpdb->get_results(
    "SELECT u.id, u.wp_user_id, w.display_name, org.name AS organization_name
     FROM {$table_users} u
     JOIN {$wpdb->users} w ON u.wp_user_id = w.ID
     LEFT JOIN {$wpdb->prefix}queues_organizations org ON u.organization_id = org.id
     ORDER BY w.display_name"
);
$orgs = $wpdb->get_results( "SELECT id,name FROM {$wpdb->prefix}queues_organizations ORDER BY name" );
$wp_users = get_users( [ 'fields' => [ 'ID','display_name' ] ] );
?>
<div class="wrap">
    <h1 class="wp-heading-inline">Manage Queue Users</h1>
    <hr class="wp-header-end">
    <form method="post" style="margin-top:20px;">
        <?php wp_nonce_field( 'manage_queue_users' ); ?>
        <table class="form-table">
            <tr>
                <th><label for="wp_user_id">WP User</label></th>
                <td>
                    <select name="wp_user_id" id="wp_user_id" required>
                        <option value="">— Select User —</option>
                        <?php foreach ( $wp_users as $u ) : ?>
                            <option value="<?php echo esc_attr( $u->ID ); ?>"><?php echo esc_html( $u->display_name ); ?></option>
                        <?php endforeach; ?>
                    </select>
                </td>
            </tr>
            <tr>
                <th><label for="organization_id">Organization</label></th>
                <td>
                    <select name="organization_id" id="organization_id" required>
                        <option value="">— Select Org —</option>
                        <?php foreach ( $orgs as $o ) : ?>
                            <option value="<?php echo esc_attr( $o->id ); ?>"><?php echo esc_html( $o->name ); ?></option>
                        <?php endforeach; ?>
                    </select>
                </td>
            </tr>
        </table>
        <input type="hidden" name="queue_user_action" value="add">
        <input type="hidden" name="user_id" value="">
        <?php submit_button( 'Add User' ); ?>
    </form>
    <h2 style="margin-top:40px;">Existing Queue Users</h2>
    <?php if ( $queue_users ) : ?>
        <table class="widefat fixed striped">
            <thead>
                <tr><th>ID</th><th>User</th><th>Org</th><th>Action</th></tr>
            </thead>
            <tbody>
                <?php foreach ( $queue_users as $qu ) : ?>
                    <tr>
                        <td><?php echo esc_html( $qu->id ); ?></td>
                        <td><?php echo esc_html( $qu->display_name ); ?></td>
                        <td><?php echo esc_html( $qu->organization_name ?: '—' ); ?></td>
                        <td>
                            <?php $del = wp_nonce_url( add_query_arg( ['delete_id'=>$qu->id] ), 'delete_queue_user_' . $qu->id ); ?>
                            <a href="<?php echo esc_url( $del ); ?>" class="button delete-queue-user">Delete</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php else : ?>
        <p>No users found.</p>
    <?php endif; ?>
</div>