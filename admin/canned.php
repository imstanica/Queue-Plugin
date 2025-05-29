<?php
// File: admin/canned.php
// Description: Admin page for managing Canned Responses (Category optional)

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
if ( ! current_user_can( 'manage_options' ) ) {
    wp_die( __( 'You do not have sufficient permissions to access this page.', 'queues' ) );
}

global $wpdb;
$table_name  = $wpdb->prefix . 'queues_canned';
$cat_table   = $wpdb->prefix . 'queues_categories';

// Handle “Add New” submission
if ( isset( $_POST['add_canned'] ) ) {
    check_admin_referer( 'add_canned_nonce' );
    $name       = sanitize_text_field( $_POST['name'] );
    $category   = intval( $_POST['category_id'] ) ?: null;
    $response   = sanitize_textarea_field( $_POST['response'] );

    // Category is optional
    if ( $name && $response ) {
        $wpdb->insert( $table_name, [
            'name'        => $name,
            'category_id' => $category,
            'response'    => $response,
        ] );
    }
}

// Handle “Update” submission
if ( isset( $_POST['update_canned'] ) ) {
    check_admin_referer( 'update_canned_nonce' );
    $id         = intval( $_POST['canned_id'] );
    $name       = sanitize_text_field( $_POST['name'] );
    $category   = intval( $_POST['category_id'] ) ?: null;
    $response   = sanitize_textarea_field( $_POST['response'] );

    if ( $id && $name && $response ) {
        $wpdb->update( $table_name, [
            'name'        => $name,
            'category_id' => $category,
            'response'    => $response,
        ], [ 'id' => $id ] );
    }
}

// Handle “Delete”
if ( isset( $_GET['action'], $_GET['canned'] ) && $_GET['action'] === 'delete' ) {
    $cid = intval( $_GET['canned'] );
    if ( wp_verify_nonce( $_GET['_wpnonce'], 'delete_canned_' . $cid ) ) {
        $wpdb->delete( $table_name, [ 'id' => $cid ] );
    }
}

// Fetch categories for dropdown
$categories = $wpdb->get_results( "SELECT id, name FROM {$cat_table} ORDER BY name ASC" );

// Fetch all canned responses
$canned = $wpdb->get_results( "SELECT * FROM {$table_name} ORDER BY id ASC" );
?>
<div class="wrap">
    <h1><?php esc_html_e( 'Canned Responses', 'queues' ); ?></h1>

    <h2><?php esc_html_e( 'Add New Canned Response', 'queues' ); ?></h2>
    <form method="post" action="">
        <?php wp_nonce_field( 'add_canned_nonce' ); ?>
        <table class="form-table">
            <tr>
                <th scope="row"><label for="name"><?php esc_html_e( 'Short Name', 'queues' ); ?></label></th>
                <td><input name="name" id="name" type="text" class="regular-text" required></td>
            </tr>
            <tr>
                <th scope="row"><label for="category_id"><?php esc_html_e( 'Category (optional)', 'queues' ); ?></label></th>
                <td>
                    <select name="category_id" id="category_id">
                        <option value=""><?php esc_html_e( '— None —', 'queues' ); ?></option>
                        <?php foreach ( $categories as $cat ) : ?>
                            <option value="<?php echo esc_attr( $cat->id ); ?>">
                                <?php echo esc_html( $cat->name ); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="response"><?php esc_html_e( 'Response Message', 'queues' ); ?></label></th>
                <td><textarea name="response" id="response" class="large-text" rows="5" required></textarea></td>
            </tr>
        </table>
        <?php submit_button( __( 'Add Canned Response', 'queues' ), 'primary', 'add_canned' ); ?>
    </form>

    <h2><?php esc_html_e( 'Existing Canned Responses', 'queues' ); ?></h2>
    <table class="widefat fixed striped">
        <thead>
            <tr>
                <th><?php esc_html_e( 'ID', 'queues' ); ?></th>
                <th><?php esc_html_e( 'Name', 'queues' ); ?></th>
                <th><?php esc_html_e( 'Category', 'queues' ); ?></th>
                <th><?php esc_html_e( 'Response', 'queues' ); ?></th>
                <th><?php esc_html_e( 'Actions', 'queues' ); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ( $canned as $item ) :
                $edit_mode = (
                    isset( $_GET['action'], $_GET['canned'] ) &&
                    $_GET['action'] === 'edit' &&
                    intval( $_GET['canned'] ) === intval( $item->id )
                );
            ?>
                <tr>
                    <td><?php echo esc_html( $item->id ); ?></td>
                    <td>
                        <?php if ( $edit_mode ) : ?>
                            <form method="post" style="display:inline;">
                                <?php wp_nonce_field( 'update_canned_nonce' ); ?>
                                <input type="hidden" name="canned_id" value="<?php echo esc_attr( $item->id ); ?>">
                                <input name="name" type="text" value="<?php echo esc_attr( $item->name ); ?>" required>
                        <?php else : ?>
                            <?php echo esc_html( $item->name ); ?>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ( $edit_mode ) : ?>
                                <select name="category_id">
                                    <option value=""><?php esc_html_e( '— None —', 'queues' ); ?></option>
                                    <?php foreach ( $categories as $cat ) : ?>
                                        <option value="<?php echo esc_attr( $cat->id ); ?>"
                                            <?php selected( $item->category_id, $cat->id ); ?>>
                                            <?php echo esc_html( $cat->name ); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                        <?php else : ?>
                            <?php
                                if ( $item->category_id ) {
                                    $cat = $wpdb->get_row( $wpdb->prepare(
                                        "SELECT name FROM {$cat_table} WHERE id = %d", $item->category_id
                                    ) );
                                    echo esc_html( $cat ? $cat->name : '' );
                                } else {
                                    echo '—';
                                }
                            ?>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ( $edit_mode ) : ?>
                                <textarea name="response" class="large-text" rows="4" required><?php echo esc_textarea( $item->response ); ?></textarea>
                                <?php submit_button( __( 'Save', 'queues' ), 'small', 'update_canned', false ); ?>
                                <a href="<?php echo esc_url( admin_url( 'admin.php?page=queues_canned' ) ); ?>" class="button">
                                    <?php esc_html_e( 'Cancel', 'queues' ); ?>
                                </a>
                            </form>
                        <?php else : ?>
                            <?php echo wp_trim_words( esc_html( $item->response ), 20, '…' ); ?>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ( ! $edit_mode ) : ?>
                            <a href="<?php echo esc_url( add_query_arg( [
                                'page'    => 'queues_canned',
                                'action'  => 'edit',
                                'canned'  => $item->id,
                            ], admin_url( 'admin.php' ) ) ); ?>"><?php esc_html_e( 'Edit', 'queues' ); ?></a>
                            |
                            <?php
                            $del_url = wp_nonce_url(
                                add_query_arg( [
                                    'page'    => 'queues_canned',
                                    'action'  => 'delete',
                                    'canned'  => $item->id,
                                ], admin_url( 'admin.php' ) ),
                                'delete_canned_' . $item->id
                            );
                            ?>
                            <a href="<?php echo esc_url( $del_url ); ?>"
                               onclick="return confirm('<?php esc_attr_e( 'Delete this canned response?', 'queues' ); ?>')">
                                <?php esc_html_e( 'Delete', 'queues' ); ?>
                            </a>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>

            <?php if ( empty( $canned ) ) : ?>
                <tr>
                    <td colspan="5"><?php esc_html_e( 'No canned responses found.', 'queues' ); ?></td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>
