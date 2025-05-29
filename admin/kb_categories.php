<?php
// File: admin/kb_categories.php
// Description: Admin page for Knowledgebase Categories

if ( ! defined( 'ABSPATH' ) ) exit;
if ( ! current_user_can( 'manage_options' ) ) wp_die();

global $wpdb;
$table = $wpdb->prefix . 'queues_kb_categories';

// Handle Add
if ( isset( $_POST['add_kb_cat'] ) ) {
    check_admin_referer( 'add_kb_cat_nonce' );
    $name = sanitize_text_field( $_POST['name'] );
    if ( $name ) {
        $wpdb->insert( $table, [ 'name' => $name ] );
    }
    wp_redirect( admin_url( 'admin.php?page=queues_kb_categories' ) );
    exit;
}

// Handle Update
if ( isset( $_POST['update_kb_cat'] ) ) {
    check_admin_referer( 'update_kb_cat_nonce' );
    $id   = intval( $_POST['kb_cat_id'] );
    $name = sanitize_text_field( $_POST['name'] );
    if ( $id && $name ) {
        $wpdb->update( $table, [ 'name' => $name ], [ 'id' => $id ] );
    }
    wp_redirect( admin_url( 'admin.php?page=queues_kb_categories' ) );
    exit;
}

// Handle Delete
if ( isset( $_GET['action'], $_GET['kb_cat'] ) && $_GET['action'] === 'delete' ) {
    $id = intval( $_GET['kb_cat'] );
    if ( wp_verify_nonce( $_GET['_wpnonce'], 'delete_kb_cat_' . $id ) ) {
        $wpdb->delete( $table, [ 'id' => $id ] );
    }
    wp_redirect( admin_url( 'admin.php?page=queues_kb_categories' ) );
    exit;
}

// Fetch all
$cats = $wpdb->get_results( "SELECT * FROM {$table} ORDER BY id ASC" );
?>
<div class="wrap">
    <h1><?php esc_html_e( 'KB Categories', 'queues' ); ?></h1>

    <h2><?php esc_html_e( 'Add New Category', 'queues' ); ?></h2>
    <form method="post">
        <?php wp_nonce_field( 'add_kb_cat_nonce' ); ?>
        <table class="form-table">
            <tr>
                <th><label for="name"><?php esc_html_e( 'Name', 'queues' ); ?></label></th>
                <td><input name="name" id="name" type="text" class="regular-text" required></td>
            </tr>
        </table>
        <?php submit_button( __( 'Add Category', 'queues' ), 'primary', 'add_kb_cat' ); ?>
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
        <?php foreach ( $cats as $cat ) :
            $edit = ( isset( $_GET['action'], $_GET['kb_cat'] ) 
                      && $_GET['action'] === 'edit' 
                      && intval( $_GET['kb_cat'] ) === $cat->id );
        ?>
            <tr>
                <td><?php echo esc_html( $cat->id ); ?></td>
                <td>
                    <?php if ( $edit ) : ?>
                        <form method="post" style="display:inline;">
                            <?php wp_nonce_field( 'update_kb_cat_nonce' ); ?>
                            <input type="hidden" name="kb_cat_id" value="<?php echo esc_attr( $cat->id ); ?>">
                            <input name="name" type="text" value="<?php echo esc_attr( $cat->name ); ?>" required>
                            <?php submit_button( __( 'Save', 'queues' ), 'small', 'update_kb_cat', false ); ?>
                            <a href="<?php echo esc_url( admin_url( 'admin.php?page=queues_kb_categories' ) ); ?>" class="button"><?php esc_html_e( 'Cancel', 'queues' ); ?></a>
                        </form>
                    <?php else : ?>
                        <?php echo esc_html( $cat->name ); ?>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if ( ! $edit ) : ?>
                        <a href="<?php echo esc_url( add_query_arg( [
                            'page'   => 'queues_kb_categories',
                            'action' => 'edit',
                            'kb_cat' => $cat->id,
                        ], admin_url( 'admin.php' ) ) ); ?>"><?php esc_html_e( 'Edit', 'queues' ); ?></a>
                        |
                        <?php $del = wp_nonce_url(
                            add_query_arg( [
                                'page'   => 'queues_kb_categories',
                                'action' => 'delete',
                                'kb_cat' => $cat->id,
                            ], admin_url( 'admin.php' ) ),
                            'delete_kb_cat_' . $cat->id
                        ); ?>
                        <a href="<?php echo esc_url( $del ); ?>" onclick="return confirm('<?php esc_attr_e( 'Delete this category?', 'queues' ); ?>')"><?php esc_html_e( 'Delete', 'queues' ); ?></a>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if ( empty( $cats ) ) : ?>
            <tr><td colspan="3"><?php esc_html_e( 'No categories found.', 'queues' ); ?></td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>
