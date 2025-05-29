<?php
// File: admin/categories.php
// Description: Admin page for managing Ticket Categories (Add, Edit, Delete)

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! current_user_can( 'manage_options' ) ) {
    wp_die( __( 'You do not have sufficient permissions to access this page.', 'queues' ) );
}

global $wpdb;
$table_name = $wpdb->prefix . 'queues_categories';

// Handle Add New Category
if ( isset( $_POST['add_category'] ) ) {
    check_admin_referer( 'add_category_nonce' );
    $name = sanitize_text_field( $_POST['name'] );
    if ( ! empty( $name ) ) {
        $wpdb->insert( $table_name, [ 'name' => $name ] );
    }
}

// Handle Update Category
if ( isset( $_POST['update_category'] ) ) {
    check_admin_referer( 'update_category_nonce' );
    $id   = intval( $_POST['category_id'] );
    $name = sanitize_text_field( $_POST['name'] );
    if ( $id && ! empty( $name ) ) {
        $wpdb->update( $table_name, [ 'name' => $name ], [ 'id' => $id ] );
    }
}

// Handle Delete Category
if ( isset( $_GET['action'], $_GET['category'] ) && $_GET['action'] === 'delete' ) {
    $category_id = intval( $_GET['category'] );
    if ( isset( $_GET['_wpnonce'] ) && wp_verify_nonce( $_GET['_wpnonce'], 'delete_category_' . $category_id ) ) {
        $wpdb->delete( $table_name, [ 'id' => $category_id ] );
    }
}

// Fetch all categories
$items = $wpdb->get_results( "SELECT * FROM {$table_name} ORDER BY id ASC" );
?>
<div class="wrap">
    <h1><?php esc_html_e( 'Ticket Categories', 'queues' ); ?></h1>

    <h2><?php esc_html_e( 'Add New Category', 'queues' ); ?></h2>
    <form method="post" action="">
        <?php wp_nonce_field( 'add_category_nonce' ); ?>
        <table class="form-table">
            <tr>
                <th scope="row"><label for="name"><?php esc_html_e( 'Name', 'queues' ); ?></label></th>
                <td><input name="name" id="name" type="text" class="regular-text" required></td>
            </tr>
        </table>
        <?php submit_button( __( 'Add Category', 'queues' ), 'primary', 'add_category' ); ?>
    </form>

    <h2><?php esc_html_e( 'Existing Categories', 'queues' ); ?></h2>
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
                    isset( $_GET['action'], $_GET['category'] )
                    && $_GET['action'] === 'edit'
                    && intval( $_GET['category'] ) === intval( $item->id )
                );
            ?>
                <tr>
                    <td><?php echo esc_html( $item->id ); ?></td>
                    <td>
                        <?php if ( $edit_mode ) : ?>
                            <form method="post" style="display:inline;">
                                <?php wp_nonce_field( 'update_category_nonce' ); ?>
                                <input type="hidden" name="category_id" value="<?php echo esc_attr( $item->id ); ?>">
                                <input name="name" type="text" value="<?php echo esc_attr( $item->name ); ?>" required>
                                <?php submit_button( __( 'Save', 'queues' ), 'small', 'update_category', false ); ?>
                                <a href="<?php echo esc_url( admin_url( 'admin.php?page=queues_categories' ) ); ?>" class="button">
                                    <?php esc_html_e( 'Cancel', 'queues' ); ?>
                                </a>
                            </form>
                        <?php else : ?>
                            <?php echo esc_html( $item->name ); ?>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ( ! $edit_mode ) : ?>
                            <a href="<?php
                                echo esc_url( add_query_arg( [
                                    'page'     => 'queues_categories',
                                    'action'   => 'edit',
                                    'category' => $item->id,
                                ], admin_url( 'admin.php' ) ) );
                            ?>"><?php esc_html_e( 'Edit', 'queues' ); ?></a>
                            |
                            <?php
                            $del_url = wp_nonce_url(
                                add_query_arg( [
                                    'page'     => 'queues_categories',
                                    'action'   => 'delete',
                                    'category' => $item->id,
                                ], admin_url( 'admin.php' ) ),
                                'delete_category_' . $item->id
                            );
                            ?>
                            <a href="<?php echo esc_url( $del_url ); ?>" onclick="return confirm('<?php esc_attr_e( 'Delete this category?', 'queues' ); ?>')">
                                <?php esc_html_e( 'Delete', 'queues' ); ?>
                            </a>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>

            <?php if ( empty( $items ) ) : ?>
                <tr>
                    <td colspan="3"><?php esc_html_e( 'No categories found.', 'queues' ); ?></td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>
