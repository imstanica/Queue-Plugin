<?php
// File: admin/statuses.php
// Description: Admin page for managing Ticket Statuses (Add, Edit, Delete)

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! current_user_can( 'manage_options' ) ) {
    wp_die( __( 'You do not have sufficient permissions to access this page.', 'queues' ) );
}

global $wpdb;
$table_name = $wpdb->prefix . 'queues_statuses';

// Handle Add New Status
if ( isset( $_POST['add_status'] ) ) {
    check_admin_referer( 'add_status_nonce' );
    $name = sanitize_text_field( $_POST['name'] );
    if ( ! empty( $name ) ) {
        $wpdb->insert( $table_name, [ 'name' => $name ] );
    }
}

// Handle Update Status
if ( isset( $_POST['update_status'] ) ) {
    check_admin_referer( 'update_status_nonce' );
    $id   = intval( $_POST['status_id'] );
    $name = sanitize_text_field( $_POST['name'] );
    if ( $id && ! empty( $name ) ) {
        $wpdb->update( $table_name, [ 'name' => $name ], [ 'id' => $id ] );
    }
}

// Handle Delete Status
if ( isset( $_GET['action'], $_GET['status'] ) && $_GET['action'] === 'delete' ) {
    $status_id = intval( $_GET['status'] );
    if ( isset( $_GET['_wpnonce'] ) && wp_verify_nonce( $_GET['_wpnonce'], 'delete_status_' . $status_id ) ) {
        $wpdb->delete( $table_name, [ 'id' => $status_id ] );
    }
}

// Fetch all statuses
$items = $wpdb->get_results( "SELECT * FROM {$table_name} ORDER BY id ASC" );
?>
<div class="wrap">
    <h1><?php esc_html_e( 'Ticket Statuses', 'queues' ); ?></h1>

    <h2><?php esc_html_e( 'Add New Status', 'queues' ); ?></h2>
    <form method="post" action="">
        <?php wp_nonce_field( 'add_status_nonce' ); ?>
        <table class="form-table">
            <tr>
                <th scope="row"><label for="name"><?php esc_html_e( 'Name', 'queues' ); ?></label></th>
                <td><input name="name" id="name" type="text" class="regular-text" required></td>
            </tr>
        </table>
        <?php submit_button( __( 'Add Status', 'queues' ), 'primary', 'add_status' ); ?>
    </form>

    <h2><?php esc_html_e( 'Existing Statuses', 'queues' ); ?></h2>
    <table class="widefat fixed striped">
        <thead>
            <tr>
                <th><?php esc_html_e( 'ID', 'queues' ); ?></th>
                <th><?php esc_html_e( 'Name', 'queues' ); ?></th>
                <th><?php esc_html_e( 'Actions', 'queues' ); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ( $items as $item ) :
                $edit_mode = (
                    isset( $_GET['action'], $_GET['status'] )
                    && $_GET['action'] === 'edit'
                    && intval( $_GET['status'] ) === intval( $item->id )
                );
            ?>
                <tr>
                    <td><?php echo esc_html( $item->id ); ?></td>
                    <td>
                        <?php if ( $edit_mode ) : ?>
                            <form method="post" style="display:inline;">
                                <?php wp_nonce_field( 'update_status_nonce' ); ?>
                                <input type="hidden" name="status_id" value="<?php echo esc_attr( $item->id ); ?>">
                                <input name="name" type="text" value="<?php echo esc_attr( $item->name ); ?>" required>
                                <?php submit_button( __( 'Save', 'queues' ), 'small', 'update_status', false ); ?>
                                <a href="<?php echo esc_url( admin_url( 'admin.php?page=queues_statuses' ) ); ?>" class="button"><?php esc_html_e( 'Cancel', 'queues' ); ?></a>
                            </form>
                        <?php else : ?>
                            <?php echo esc_html( $item->name ); ?>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ( ! $edit_mode ) : ?>
                            <a href="<?php
                                echo esc_url( add_query_arg( [
                                    'page'   => 'queues_statuses',
                                    'action' => 'edit',
                                    'status' => $item->id,
                                ], admin_url( 'admin.php' ) ) );
                            ?>"><?php esc_html_e( 'Edit', 'queues' ); ?></a>
                            |
                            <?php
                            $del_url = wp_nonce_url(
                                add_query_arg( [
                                    'page'   => 'queues_statuses',
                                    'action' => 'delete',
                                    'status' => $item->id,
                                ], admin_url( 'admin.php' ) ),
                                'delete_status_' . $item->id
                            );
                            ?>
                            <a href="<?php echo esc_url( $del_url ); ?>" onclick="return confirm('<?php esc_attr_e( 'Delete this status?', 'queues' ); ?>')"><?php esc_html_e( 'Delete', 'queues' ); ?></a>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>

            <?php if ( empty( $items ) ) : ?>
                <tr>
                    <td colspan="3"><?php esc_html_e( 'No statuses found.', 'queues' ); ?></td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>
