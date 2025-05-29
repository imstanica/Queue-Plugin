<?php
// File: admin/kb_articles.php
// Description: Admin page for Knowledgebase Articles

if ( ! defined( 'ABSPATH' ) ) exit;
if ( ! current_user_can( 'manage_options' ) ) wp_die();

global $wpdb;
$art_table = $wpdb->prefix . 'queues_kb_articles';
$cat_table = $wpdb->prefix . 'queues_kb_categories';

// Fetch categories for dropdown
$cats = $wpdb->get_results( "SELECT id, name FROM {$cat_table} ORDER BY name ASC" );

// Handle Add
if ( isset( $_POST['add_kb_art'] ) ) {
    check_admin_referer( 'add_kb_art_nonce' );
    $cid     = intval( $_POST['kb_category_id'] );
    $title   = sanitize_text_field( $_POST['title'] );
    $content = wp_kses_post( $_POST['content'] );
    if ( $cid && $title && $content ) {
        $wpdb->insert( $art_table, [
            'kb_category_id' => $cid,
            'title'          => $title,
            'content'        => $content,
        ] );
    }
    wp_redirect( admin_url( 'admin.php?page=queues_kb_articles' ) );
    exit;
}

// Handle Update
if ( isset( $_POST['update_kb_art'] ) ) {
    check_admin_referer( 'update_kb_art_nonce' );
    $id      = intval( $_POST['kb_art_id'] );
    $cid     = intval( $_POST['kb_category_id'] );
    $title   = sanitize_text_field( $_POST['title'] );
    $content = wp_kses_post( $_POST['content'] );
    if ( $id && $cid && $title && $content ) {
        $wpdb->update( $art_table, [
            'kb_category_id' => $cid,
            'title'          => $title,
            'content'        => $content,
        ], [ 'id' => $id ] );
    }
    wp_redirect( admin_url( 'admin.php?page=queues_kb_articles' ) );
    exit;
}

// Handle Delete
if ( isset( $_GET['action'], $_GET['kb_art'] ) && $_GET['action'] === 'delete' ) {
    $id = intval( $_GET['kb_art'] );
    if ( wp_verify_nonce( $_GET['_wpnonce'], 'delete_kb_art_' . $id ) ) {
        $wpdb->delete( $art_table, [ 'id' => $id ] );
    }
    wp_redirect( admin_url( 'admin.php?page=queues_kb_articles' ) );
    exit;
}

// Fetch all articles
$arts = $wpdb->get_results( "
    SELECT a.*, c.name AS category_name
    FROM {$art_table} a
    LEFT JOIN {$cat_table} c ON a.kb_category_id = c.id
    ORDER BY a.id ASC
" );
?>
<div class="wrap">
    <h1><?php esc_html_e( 'KB Articles', 'queues' ); ?></h1>

    <h2><?php esc_html_e( 'Add New Article', 'queues' ); ?></h2>
    <form method="post">
        <?php wp_nonce_field( 'add_kb_art_nonce' ); ?>
        <table class="form-table">
            <tr>
                <th><label for="kb_category_id"><?php esc_html_e( 'Category', 'queues' ); ?></label></th>
                <td>
                    <select name="kb_category_id" id="kb_category_id" required>
                        <option value=""><?php esc_html_e( '— Select Category —', 'queues' ); ?></option>
                        <?php foreach ( $cats as $c ) : ?>
                            <option value="<?php echo esc_attr( $c->id ); ?>">
                                <?php echo esc_html( $c->name ); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </td>
            </tr>
            <tr>
                <th><label for="title"><?php esc_html_e( 'Title', 'queues' ); ?></label></th>
                <td><input name="title" id="title" type="text" class="regular-text" required></td>
            </tr>
            <tr>
                <th><label for="content"><?php esc_html_e( 'Content', 'queues' ); ?></label></th>
                <td><?php wp_editor( '', 'content', [ 'textarea_rows' => 10 ] ); ?></td>
            </tr>
        </table>
        <?php submit_button( __( 'Add Article', 'queues' ), 'primary', 'add_kb_art' ); ?>
    </form>

    <h2><?php esc_html_e( 'Existing Articles', 'queues' ); ?></h2>
    <table class="widefat fixed striped">
        <thead>
            <tr>
                <th><?php esc_html_e( 'ID', 'queues' ); ?></th>
                <th><?php esc_html_e( 'Category', 'queues' ); ?></th>
                <th><?php esc_html_e( 'Title', 'queues' ); ?></th>
                <th><?php esc_html_e( 'Actions', 'queues' ); ?></th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ( $arts as $art ) :
            $edit = ( isset( $_GET['action'], $_GET['kb_art'] ) 
                      && $_GET['action'] === 'edit' 
                      && intval( $_GET['kb_art'] ) === $art->id );
        ?>
            <tr>
                <td><?php echo esc_html( $art->id ); ?></td>
                <td>
                    <?php echo esc_html( $art->category_name ); ?>
                </td>
                <td>
                    <?php if ( $edit ) : ?>
                        <form method="post" style="display:inline;">
                            <?php wp_nonce_field( 'update_kb_art_nonce' ); ?>
                            <input type="hidden" name="kb_art_id" value="<?php echo esc_attr( $art->id ); ?>">
                            <input name="title" type="text" value="<?php echo esc_attr( $art->title ); ?>" required>
                            <select name="kb_category_id" required>
                                <?php foreach ( $cats as $c ) : ?>
                                    <option value="<?php echo esc_attr( $c->id ); ?>" <?php selected( $art->kb_category_id, $c->id ); ?>>
                                        <?php echo esc_html( $c->name ); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <?php wp_editor( $art->content, 'content_' . $art->id, [ 'textarea_rows' => 5 ] ); ?>
                            <?php submit_button( __( 'Save', 'queues' ), 'small', 'update_kb_art', false ); ?>
                            <a href="<?php echo esc_url( admin_url( 'admin.php?page=queues_kb_articles' ) ); ?>" class="button"><?php esc_html_e( 'Cancel', 'queues' ); ?></a>
                        </form>
                    <?php else : ?>
                        <?php echo esc_html( $art->title ); ?>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if ( ! $edit ) : ?>
                        <a href="<?php echo esc_url( add_query_arg( [
                            'page'   => 'queues_kb_articles',
                            'action' => 'edit',
                            'kb_art' => $art->id,
                        ], admin_url( 'admin.php' ) ) ); ?>"><?php esc_html_e( 'Edit', 'queues' ); ?></a>
                        |
                        <?php $del = wp_nonce_url(
                            add_query_arg( [
                                'page'   => 'queues_kb_articles',
                                'action' => 'delete',
                                'kb_art' => $art->id,
                            ], admin_url( 'admin.php' ) ),
                            'delete_kb_art_' . $art->id
                        ); ?>
                        <a href="<?php echo esc_url( $del ); ?>" onclick="return confirm('<?php esc_attr_e( 'Delete this article?', 'queues' ); ?>')"><?php esc_html_e( 'Delete', 'queues' ); ?></a>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if ( empty( $arts ) ) : ?>
            <tr><td colspan="4"><?php esc_html_e( 'No articles found.', 'queues' ); ?></td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>
