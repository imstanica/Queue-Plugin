<?php
if ( ! defined( 'ABSPATH' ) ) exit;
if ( ! current_user_can( 'manage_options' ) ) wp_die();

global $wpdb;
$p = $wpdb->prefix;
$table_users = "{$p}queues_users";
$table_orgs  = "{$p}queues_organizations";

// Fetch all users and orgs
$wp_users = get_users( [ 'fields' => [ 'ID', 'display_name' ] ] );
$orgs     = $wpdb->get_results( "SELECT id, name FROM {$table_orgs} ORDER BY name" );

// Handle Add/Edit/Delete
if ( isset( $_POST['queue_user_action'] ) && wp_verify_nonce( $_POST['_wpnonce'], 'manage_queue_users' ) ) {
    $action          = sanitize_text_field( $_POST['queue_user_action'] );
    $wp_user_id      = intval( $_POST['wp_user_id'] );
    $organization_id = intval( $_POST['organization_id'] );
    $id              = isset( $_POST['user_id'] ) ? intval( $_POST['user_id'] ) : 0;

    if ( $action === 'add' && $wp_user_id ) {
        $exists = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table_users} WHERE wp_user_id = %d", $wp_user_id ) );
        if ( ! $exists ) {
            $wpdb->insert( $table_users, [
                'wp_user_id' => $wp_user_id,
                'organization_id' => $organization_id
            ], [ '%d', '%d' ] );
        }
    }

    if ( $action === 'edit' && $id ) {
        $wpdb->update( $table_users, [ 'organization_id' => $organization_id ], [ 'id' => $id ], [ '%d' ], [ '%d' ] );
    }
}

if ( isset( $_GET['action'], $_GET['user'] ) && $_GET['action'] === 'delete' ) {
    $uid = intval( $_GET['user'] );
    if ( wp_verify_nonce( $_GET['_wpnonce'], 'delete_queue_user_' . $uid ) ) {
        $wpdb->delete( $table_users, [ 'id' => $uid ], [ '%d' ] );
    }
}

// Fetch combined data
$queue_users = $wpdb->get_results( "
    SELECT u.id, u.wp_user_id, u.organization_id, wp.display_name, org.name AS org_name
    FROM {$table_users} u
    JOIN {$wpdb->users} wp ON u.wp_user_id = wp.ID
    LEFT JOIN {$table_orgs} org ON u.organization_id = org.id
    ORDER BY wp.display_name
" );
?>

<div class="wrap">
    <h1><?php esc_html_e( 'Queue Users', 'queues' ); ?></h1>

    <h2><?php esc_html_e( 'Add New Queue User', 'queues' ); ?></h2>
    <form method="post">
        <?php wp_nonce_field( 'manage_queue_users' ); ?>
        <table class="form-table">
            <tr>
                <th><label for="wp_user_id"><?php esc_html_e( 'Select existing user', 'queues' ); ?></label></th>
                <td>
                    <select name="wp_user_id" id="wp_user_id" required style="width:300px;">
                        <option value=""><?php esc_html_e( '— Select User —', 'queues' ); ?></option>
                        <?php foreach ( $wp_users as $u ) : ?>
                            <option value="<?php echo esc_attr( $u->ID ); ?>"><?php echo esc_html( $u->display_name ); ?></option>
                        <?php endforeach; ?>
                    </select>
                </td>
            </tr>
            <tr>
            <th><label for="organization_id"><?php esc_html_e( 'Organization', 'queues' ); ?></label></th>
            <td>
                <select name="organization_id" id="organization_id" style="width: 300px;" required>
                <option value=""><?php esc_html_e( '— Select Org —', 'queues' ); ?></option>
                <?php foreach ( $orgs as $org ) : ?>
                    <option value="<?php echo esc_attr( $org->id ); ?>"><?php echo esc_html( $org->name ); ?></option>
                <?php endforeach; ?>
                </select>
            </td>
            </tr>
        </table>
        <input type="hidden" name="queue_user_action" value="add">
        <input type="hidden" name="user_id" value="">
        <?php submit_button( __( 'Add User', 'queues' ) ); ?>
    </form>

    <h2 style="margin-top:40px;"><?php esc_html_e( 'Existing Queue Users', 'queues' ); ?></h2>
    <table class="widefat fixed striped">
        <thead>
            <tr>
                <th><?php esc_html_e( 'ID', 'queues' ); ?></th>
                <th><?php esc_html_e( 'User', 'queues' ); ?></th>
                <th><?php esc_html_e( 'Organization', 'queues' ); ?></th>
                <th><?php esc_html_e( 'Actions', 'queues' ); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ( $queue_users as $qu ) :
                $edit = (
                    isset( $_GET['action'], $_GET['user'] ) &&
                    $_GET['action'] === 'edit' &&
                    intval( $_GET['user'] ) === $qu->id
                );
            ?>
                <tr>
                    <td><?php echo esc_html( $qu->id ); ?></td>
                    <td>
                        <?php if ( $edit ) : ?>
                            <form method="post" style="display:inline">
                                <?php wp_nonce_field( 'manage_queue_users' ); ?>
                                <input type="hidden" name="queue_user_action" value="edit">
                                <input type="hidden" name="user_id" value="<?php echo esc_attr( $qu->id ); ?>">
                                <strong><?php echo esc_html( $qu->display_name ); ?></strong>
                    </td>
                    <td>
                                <select name="organization_id" required>
                                    <option value=""><?php esc_html_e( '— Select Org —', 'queues' ); ?></option>
                                    <?php foreach ( $orgs as $org ) : ?>
                                        <option value="<?php echo esc_attr( $org->id ); ?>" <?php selected( $org->id, $qu->organization_id ); ?>>
                                            <?php echo esc_html( $org->name ); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <?php submit_button( __( 'Save', 'queues' ), 'small', '', false ); ?>
                                <a href="<?php echo admin_url( 'admin.php?page=queues_users' ); ?>" class="button"><?php esc_html_e( 'Cancel', 'queues' ); ?></a>
                            </form>
                        <?php else : ?>
                            <?php echo esc_html( $qu->display_name ); ?>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php echo esc_html( $qu->org_name ?: '—' ); ?>
                    </td>
                    <td>
                        <?php if ( ! $edit ) : ?>
                            <a href="<?php echo esc_url( add_query_arg([
                                'page'   => 'queues_users',
                                'action' => 'edit',
                                'user'   => $qu->id
                            ], admin_url('admin.php')) ); ?>"><?php esc_html_e( 'Edit', 'queues' ); ?></a> |
                            <?php
                            $del_url = wp_nonce_url(
                                add_query_arg([
                                    'page'   => 'queues_users',
                                    'action' => 'delete',
                                    'user'   => $qu->id
                                ], admin_url('admin.php')),
                                'delete_queue_user_' . $qu->id
                            );
                            ?>
                            <a href="<?php echo esc_url( $del_url ); ?>" onclick="return confirm('<?php esc_attr_e( 'Delete this user?', 'queues' ); ?>')"><?php esc_html_e( 'Delete', 'queues' ); ?></a>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if ( empty( $queue_users ) ) : ?>
                <tr><td colspan="4"><?php esc_html_e( 'No users found.', 'queues' ); ?></td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- Select2 for WP User Search -->
<!-- Select2 for WP User and Organization -->
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
jQuery(document).ready(function($) {
    $('#wp_user_id').select2({
        placeholder: "<?php esc_attr_e( 'Search user...', 'queues' ); ?>",
        width: 'resolve'
    });
    $('#organization_id').select2({
        placeholder: "<?php esc_attr_e( 'Search organization...', 'queues' ); ?>",
        width: 'resolve'
    });
});
</script>