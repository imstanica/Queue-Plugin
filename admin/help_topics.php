<?php
// File: admin/help_topics.php
// Description: Admin page for managing Help Topics (Add, Edit, Delete)

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! current_user_can( 'manage_options' ) ) {
    wp_die( __( 'You do not have sufficient permissions to access this page.', 'queues' ) );
}

global $wpdb;
$table_name = $wpdb->prefix . 'queues_help_topics';

// Handle Add New Topic
if ( isset( $_POST['add_help_topic'] ) ) {
    check_admin_referer( 'add_help_topic_nonce' );
    $topic = sanitize_text_field( $_POST['name'] );
    $type  = $_POST['type'] === 'request' ? 'request' : 'incident';
    if ( ! empty( $topic ) ) {
        $wpdb->insert( $table_name, [ 'topic' => $topic, 'type' => $type ] );
    }
}

// Handle Update Topic
if ( isset( $_POST['update_help_topic'] ) ) {
    check_admin_referer( 'update_help_topic_nonce' );
    $id    = intval( $_POST['help_topic_id'] );
    $topic = sanitize_text_field( $_POST['name'] );
    $type  = $_POST['type'] === 'request' ? 'request' : 'incident';
    if ( $id && ! empty( $topic ) ) {
        $wpdb->update( $table_name, [ 'topic' => $topic, 'type' => $type ], [ 'id' => $id ] );
    }
}

// Handle Delete Topic
if ( isset( $_GET['action'], $_GET['topic'] ) && $_GET['action'] === 'delete' ) {
    $topic_id = intval( $_GET['topic'] );
    if ( isset( $_GET['_wpnonce'] ) && wp_verify_nonce( $_GET['_wpnonce'], 'delete_help_topic_' . $topic_id ) ) {
        $wpdb->delete( $table_name, [ 'id' => $topic_id ] );
    }
}

// Fetch all topics
$items = $wpdb->get_results( "SELECT * FROM {$table_name} ORDER BY id ASC" );
?>
<div class="wrap">
    <h1><?php esc_html_e( 'Help Topics', 'queues' ); ?></h1>

    <h2><?php esc_html_e( 'Add New Help Topic', 'queues' ); ?></h2>
    <form method="post" action="">
        <?php wp_nonce_field( 'add_help_topic_nonce' ); ?>
        <table class="form-table">
            <tr>
                <th scope="row"><label for="name"><?php esc_html_e( 'Topic', 'queues' ); ?></label></th>
                <td><input name="name" id="name" type="text" class="regular-text" required></td>
            </tr>
            <tr>
                <th scope="row"><label for="type"><?php esc_html_e( 'Type', 'queues' ); ?></label></th>
                <td>
                    <select name="type" id="type" required>
                        <option value="incident">Incident</option>
                        <option value="request">Request</option>
                    </select>
                </td>
            </tr>
        </table>
        <?php submit_button( __( 'Add Help Topic', 'queues' ), 'primary', 'add_help_topic' ); ?>
    </form>

    <h2><?php esc_html_e( 'Existing Help Topics', 'queues' ); ?></h2>
    <table class="widefat fixed striped">
        <thead>
            <tr>
                <th><?php esc_html_e( 'ID', 'queues' ); ?></th>
                <th><?php esc_html_e( 'Topic', 'queues' ); ?></th>
                <th><?php esc_html_e( 'Type', 'queues' ); ?></th>
                <th><?php esc_html_e( 'Actions', 'queues' ); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ( $items as $item ) :
                $edit_mode = (
                    isset( $_GET['action'], $_GET['topic'] )
                    && $_GET['action'] === 'edit'
                    && intval( $_GET['topic'] ) === intval( $item->id )
                );
            ?>
                <tr>
                    <td><?php echo esc_html( $item->id ); ?></td>
                    <td>
                        <?php if ( $edit_mode ) : ?>
                            <form method="post" style="display:inline;">
                                <?php wp_nonce_field( 'update_help_topic_nonce' ); ?>
                                <input type="hidden" name="help_topic_id" value="<?php echo esc_attr( $item->id ); ?>">
                                <input name="name" type="text" value="<?php echo esc_attr( $item->topic ); ?>" required>
                                <select name="type" required>
                                    <option value="incident" <?php selected( $item->type, 'incident' ); ?>>Incident</option>
                                    <option value="request" <?php selected( $item->type, 'request' ); ?>>Request</option>
                                </select>
                                <?php submit_button( __( 'Save', 'queues' ), 'small', 'update_help_topic', false ); ?>
                                <a href="<?php echo esc_url( admin_url( 'admin.php?page=queues_help_topics' ) ); ?>" class="button">
                                    <?php esc_html_e( 'Cancel', 'queues' ); ?>
                                </a>
                            </form>
                        <?php else : ?>
                            <?php echo esc_html( $item->topic ); ?>
                        <?php endif; ?>
                    </td>
                    <td><?php echo esc_html( ucfirst( $item->type ) ); ?></td>
                    <td>
                        <?php if ( ! $edit_mode ) : ?>
                            <a href="<?php
                                echo esc_url( add_query_arg( [
                                    'page'    => 'queues_help_topics',
                                    'action'  => 'edit',
                                    'topic'   => $item->id,
                                ], admin_url( 'admin.php' ) ) );
                            ?>"><?php esc_html_e( 'Edit', 'queues' ); ?></a>
                            |
                            <?php
                            $del_url = wp_nonce_url(
                                add_query_arg( [
                                    'page'    => 'queues_help_topics',
                                    'action'  => 'delete',
                                    'topic'   => $item->id,
                                ], admin_url( 'admin.php' ) ),
                                'delete_help_topic_' . $item->id
                            );
                            ?>
                            <a href="<?php echo esc_url( $del_url ); ?>" onclick="return confirm('<?php esc_attr_e( 'Delete this topic?', 'queues' ); ?>')">
                                <?php esc_html_e( 'Delete', 'queues' ); ?>
                            </a>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>

            <?php if ( empty( $items ) ) : ?>
                <tr>
                    <td colspan="4"><?php esc_html_e( 'No help topics found.', 'queues' ); ?></td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>
