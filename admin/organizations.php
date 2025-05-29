<?php
// File: admin/organizations.php
// Description: Admin page for managing Organizations (Add, Edit, Delete)

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
if ( ! current_user_can( 'manage_options' ) ) {
    wp_die( __( 'You do not have sufficient permissions to access this page.', 'queues' ) );
}

global $wpdb;
$table           = $wpdb->prefix . 'queues_organizations';
$users_table     = $wpdb->prefix . 'queues_users';
$wp_users_table  = $wpdb->prefix . 'users';

// Fetch users for Manager dropdown
$managers = $wpdb->get_results( "
    SELECT u.id, wp.display_name
    FROM {$users_table} u
    LEFT JOIN {$wp_users_table} wp ON u.wp_user_id = wp.ID
    ORDER BY wp.display_name ASC
" );

// Handle Add
if ( isset( $_POST['add_org'] ) ) {
    check_admin_referer( 'add_org_nonce' );
    $name      = sanitize_text_field( $_POST['name'] );
    $address   = sanitize_textarea_field( $_POST['address'] );
    $phone     = sanitize_text_field( $_POST['phone'] );
    $manager   = intval( $_POST['manager_id'] ) ?: null;
    if ( $name ) {
        $wpdb->insert( $table, [
            'name'       => $name,
            'address'    => $address,
            'phone'      => $phone,
            'manager_id' => $manager,
        ] );
    }
}

// Handle Update
if ( isset( $_POST['update_org'] ) ) {
    check_admin_referer( 'update_org_nonce' );
    $id        = intval( $_POST['org_id'] );
    $name      = sanitize_text_field( $_POST['name'] );
    $address   = sanitize_textarea_field( $_POST['address'] );
    $phone     = sanitize_text_field( $_POST['phone'] );
    $manager   = intval( $_POST['manager_id'] ) ?: null;
    if ( $id && $name ) {
        $wpdb->update( $table, [
            'name'       => $name,
            'address'    => $address,
            'phone'      => $phone,
            'manager_id' => $manager,
        ], [ 'id' => $id ] );
    }
}

// Handle Delete
if ( isset( $_GET['action'], $_GET['org'] ) && $_GET['action'] === 'delete' ) {
    $org_id = intval( $_GET['org'] );
    if ( wp_verify_nonce( $_GET['_wpnonce'], 'delete_org_' . $org_id ) ) {
        $wpdb->delete( $table, [ 'id' => $org_id ] );
    }
}

// Fetch all organizations
$orgs = $wpdb->get_results( "SELECT * FROM {$table} ORDER BY id ASC" );
?>
<div class="wrap">
    <h1><?php esc_html_e( 'Organizations', 'queues' ); ?></h1>

    <h2><?php esc_html_e( 'Add New Organization', 'queues' ); ?></h2>
    <form method="post">
        <?php wp_nonce_field( 'add_org_nonce' ); ?>
        <table class="form-table">
            <tr>
                <th scope="row"><label for="name"><?php esc_html_e( 'Name', 'queues' ); ?></label></th>
                <td><input name="name" id="name" type="text" class="regular-text" required></td>
            </tr>
            <tr>
                <th scope="row"><label for="address"><?php esc_html_e( 'Address', 'queues' ); ?></label></th>
                <td><textarea name="address" id="address" class="large-text" rows="2"></textarea></td>
            </tr>
            <tr>
                <th scope="row"><label for="phone"><?php esc_html_e( 'Phone', 'queues' ); ?></label></th>
                <td><input name="phone" id="phone" type="text" class="regular-text"></td>
            </tr>
            <tr>
                <th scope="row"><label for="manager_id"><?php esc_html_e( 'Manager', 'queues' ); ?></label></th>
                <td>
                    <select name="manager_id" id="manager_id" style="width: 300px;">
                        <option value=""><?php esc_html_e( '— None —', 'queues' ); ?></option>
                        <?php foreach ( $managers as $m ) : ?>
                            <option value="<?php echo esc_attr( $m->id ); ?>">
                                <?php echo esc_html( $m->display_name ); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </td>
            </tr>
        </table>
        <?php submit_button( __( 'Add Organization', 'queues' ), 'primary', 'add_org' ); ?>
    </form>

    <h2><?php esc_html_e( 'Existing Organizations', 'queues' ); ?></h2>
    <table class="widefat fixed striped">
        <thead>
            <tr>
                <th><?php esc_html_e( 'ID', 'queues' ); ?></th>
                <th><?php esc_html_e( 'Name', 'queues' ); ?></th>
                <th><?php esc_html_e( 'Address', 'queues' ); ?></th>
                <th><?php esc_html_e( 'Phone', 'queues' ); ?></th>
                <th><?php esc_html_e( 'Manager', 'queues' ); ?></th>
                <th><?php esc_html_e( 'Actions', 'queues' ); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ( $orgs as $org ) :
                $edit_mode = (
                    isset( $_GET['action'], $_GET['org'] ) &&
                    $_GET['action'] === 'edit' &&
                    intval( $_GET['org'] ) === intval( $org->id )
                );
            ?>
                <tr>
                    <td><?php echo esc_html( $org->id ); ?></td>
                    <td>
                        <?php if ( $edit_mode ) : ?>
                            <form method="post" style="display:inline;">
                                <?php wp_nonce_field( 'update_org_nonce' ); ?>
                                <input type="hidden" name="org_id" value="<?php echo esc_attr( $org->id ); ?>">
                                <input name="name" type="text" value="<?php echo esc_attr( $org->name ); ?>" required>
                        <?php else : ?>
                            <?php echo esc_html( $org->name ); ?>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ( $edit_mode ) : ?>
                                <textarea name="address" class="large-text" rows="2"><?php echo esc_textarea( $org->address ); ?></textarea>
                        <?php else : ?>
                            <?php echo nl2br( esc_html( $org->address ) ); ?>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ( $edit_mode ) : ?>
                                <input name="phone" type="text" value="<?php echo esc_attr( $org->phone ); ?>">
                        <?php else : ?>
                            <?php echo esc_html( $org->phone ); ?>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ( $edit_mode ) : ?>
                                <select name="manager_id">
                                    <option value=""><?php esc_html_e( '— None —', 'queues' ); ?></option>
                                    <?php foreach ( $managers as $m ) : ?>
                                        <option value="<?php echo esc_attr( $m->id ); ?>" <?php selected( $org->manager_id, $m->id ); ?>>
                                            <?php echo esc_html( $m->display_name ); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <?php submit_button( __( 'Save', 'queues' ), 'small', 'update_org', false ); ?>
                                <a href="<?php echo esc_url( admin_url( 'admin.php?page=queues_organizations' ) ); ?>" class="button">
                                    <?php esc_html_e( 'Cancel', 'queues' ); ?>
                                </a>
                            </form>
                        <?php else : ?>
                            <?php
                                $mgr = array_filter( $managers, fn( $u ) => $u->id === $org->manager_id );
                                echo $mgr ? esc_html( array_values( $mgr )[0]->display_name ) : '—';
                            ?>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ( ! $edit_mode ) : ?>
                            <a href="<?php echo esc_url( add_query_arg( [
                                'page' => 'queues_organizations',
                                'action' => 'edit',
                                'org' => $org->id,
                            ], admin_url( 'admin.php' ) ) ); ?>"><?php esc_html_e( 'Edit', 'queues' ); ?></a>
                            |
                            <?php
                            $del_url = wp_nonce_url(
                                add_query_arg( [
                                    'page' => 'queues_organizations',
                                    'action' => 'delete',
                                    'org' => $org->id,
                                ], admin_url( 'admin.php' ) ),
                                'delete_org_' . $org->id
                            );
                            ?>
                            <a href="<?php echo esc_url( $del_url ); ?>" onclick="return confirm('<?php esc_attr_e( 'Delete this organization?', 'queues' ); ?>')">
                                <?php esc_html_e( 'Delete', 'queues' ); ?>
                            </a>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>

            <?php if ( empty( $orgs ) ) : ?>
                <tr>
                    <td colspan="6"><?php esc_html_e( 'No organizations found.', 'queues' ); ?></td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>
<!-- Select2 for Manager dropdown -->
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
jQuery(document).ready(function($) {
    $('#manager_id').select2({
        placeholder: "<?php esc_attr_e( 'Search manager...', 'queues' ); ?>",
        width: 'resolve'
    });

    // Apply select2 on edit rows as well
    <?php foreach ( $orgs as $org ) : ?>
        $('#manager_id_<?php echo esc_js( $org->id ); ?>').select2({
            placeholder: "<?php esc_attr_e( 'Search manager...', 'queues' ); ?>",
            width: 'resolve'
        });
    <?php endforeach; ?>
});
</script>