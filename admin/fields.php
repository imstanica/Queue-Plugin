<?php
// File: admin/fields.php
// Description: Admin page for managing Custom Fields (Add, Edit, Delete)

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
if ( ! current_user_can( 'manage_options' ) ) {
    wp_die( __( 'You do not have sufficient permissions to access this page.', 'queues' ) );
}

global $wpdb;
$table       = $wpdb->prefix . 'queues_fields';
$cat_table   = $wpdb->prefix . 'queues_categories';
$help_table  = $wpdb->prefix . 'queues_help_topics';

// Handle Add New Field
if ( isset( $_POST['add_field'] ) ) {
    check_admin_referer( 'add_field_nonce' );
    $label = sanitize_text_field( $_POST['field_label'] );
    $type  = sanitize_text_field( $_POST['field_type'] );
    $cat   = intval( $_POST['category_id'] ) ?: null;
    $help  = intval( $_POST['help_topic_id'] ) ?: null;
    if ( $label && $type && ( $cat || $help ) ) {
        $wpdb->insert( $table, [
            'field_label'   => $label,
            'field_type'    => $type,
            'category_id'   => $cat,
            'help_topic_id' => $help,
        ] );
    }
}

// Handle Update Field (label & type)
if ( isset( $_POST['update_field'] ) ) {
    check_admin_referer( 'update_field_nonce' );
    $id    = intval( $_POST['field_id'] );
    $label = sanitize_text_field( $_POST['field_label'] );
    $type  = sanitize_text_field( $_POST['field_type'] );
    if ( $id && $label && $type ) {
        $wpdb->update( $table, [
            'field_label' => $label,
            'field_type'  => $type,
        ], [ 'id' => $id ] );
    }
}

// Handle Delete Field
if ( isset( $_GET['action'], $_GET['field'] ) && $_GET['action'] === 'delete' ) {
    $field_id = intval( $_GET['field'] );
    if ( isset( $_GET['_wpnonce'] ) && wp_verify_nonce( $_GET['_wpnonce'], 'delete_field_' . $field_id ) ) {
        $wpdb->delete( $table, [ 'id' => $field_id ] );
    }
}

// Fetch all fields, joined and ordered by association name
$items = $wpdb->get_results( "
    SELECT f.id, f.field_label, f.field_type,
           f.category_id, c.name   AS category_name,
           f.help_topic_id, h.topic AS help_topic
    FROM {$table} f
    LEFT JOIN {$cat_table}  c ON f.category_id   = c.id
    LEFT JOIN {$help_table} h ON f.help_topic_id = h.id
    ORDER BY COALESCE(c.name, h.topic) ASC, f.id ASC
" );
?>
<div class="wrap">
    <h1><?php esc_html_e( 'Custom Fields', 'queues' ); ?></h1>

    <h2><?php esc_html_e( 'Add New Field', 'queues' ); ?></h2>
    <form method="post" action="">
        <?php wp_nonce_field( 'add_field_nonce' ); ?>
        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="field_label"><?php esc_html_e( 'Field Label', 'queues' ); ?></label>
                </th>
                <td>
                    <input name="field_label" id="field_label" type="text" class="regular-text" required>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="field_type"><?php esc_html_e( 'Type', 'queues' ); ?></label>
                </th>
                <td>
                    <select name="field_type" id="field_type">
                        <option value="text"><?php esc_html_e( 'Text', 'queues' ); ?></option>
                        <option value="email"><?php esc_html_e( 'Email', 'queues' ); ?></option>
                        <option value="checkbox"><?php esc_html_e( 'Checkbox', 'queues' ); ?></option>
                        <option value="radio"><?php esc_html_e( 'Radio', 'queues' ); ?></option>
                        <option value="textarea"><?php esc_html_e( 'Textarea', 'queues' ); ?></option>
                        <option value="select"><?php esc_html_e( 'Select', 'queues' ); ?></option>
                    </select>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e( 'Associate with', 'queues' ); ?></th>
                <td>
                    <select name="category_id">
                        <option value=""><?php esc_html_e( 'Select Category', 'queues' ); ?></option>
                        <?php foreach ( $wpdb->get_results( "SELECT id,name FROM {$cat_table} ORDER BY name ASC" ) as $cat ) : ?>
                            <option value="<?php echo esc_attr( $cat->id ); ?>"><?php echo esc_html( $cat->name ); ?></option>
                        <?php endforeach; ?>
                    </select>
                    &nbsp;
                    <select name="help_topic_id">
                        <option value=""><?php esc_html_e( 'Select Help Topic', 'queues' ); ?></option>
                        <?php foreach ( $wpdb->get_results( "SELECT id,topic FROM {$help_table} ORDER BY topic ASC" ) as $ht ) : ?>
                            <option value="<?php echo esc_attr( $ht->id ); ?>"><?php echo esc_html( $ht->topic ); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <p class="description"><?php esc_html_e( 'Choose a Category OR a Help Topic, or both.', 'queues' ); ?></p>
                </td>
            </tr>
        </table>
        <?php submit_button( __( 'Add Field', 'queues' ), 'primary', 'add_field' ); ?>
    </form>

    <h2><?php esc_html_e( 'Existing Custom Fields', 'queues' ); ?></h2>
    <table class="widefat fixed striped">
        <thead>
            <tr>
                <th><?php esc_html_e( 'ID', 'queues' ); ?></th>
                <th><?php esc_html_e( 'Label', 'queues' ); ?></th>
                <th><?php esc_html_e( 'Type', 'queues' ); ?></th>
                <th><?php esc_html_e( 'Association', 'queues' ); ?></th>
                <th><?php esc_html_e( 'Actions', 'queues' ); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ( $items as $item ) :
                $edit_mode = (
                    isset( $_GET['action'], $_GET['field'] ) &&
                    $_GET['action'] === 'edit' &&
                    intval( $_GET['field'] ) === intval( $item->id )
                );
            ?>
                <tr>
                    <td><?php echo esc_html( $item->id ); ?></td>
                    <td>
                        <?php if ( $edit_mode ) : ?>
                            <form method="post" style="display:inline;">
                                <?php wp_nonce_field( 'update_field_nonce' ); ?>
                                <input type="hidden" name="field_id" value="<?php echo esc_attr( $item->id ); ?>">
                                <input name="field_label" type="text" value="<?php echo esc_attr( $item->field_label ); ?>" required>
                                <select name="field_type">
                                    <option value="text"    <?php selected( $item->field_type, 'text' ); ?>><?php esc_html_e( 'Text', 'queues' ); ?></option>
                                    <option value="email"   <?php selected( $item->field_type, 'email' ); ?>><?php esc_html_e( 'Email', 'queues' ); ?></option>
                                    <option value="checkbox"<?php selected( $item->field_type, 'checkbox' ); ?>><?php esc_html_e( 'Checkbox', 'queues' ); ?></option>
                                    <option value="radio"   <?php selected( $item->field_type, 'radio' ); ?>><?php esc_html_e( 'Radio', 'queues' ); ?></option>
                                    <option value="textarea"<?php selected( $item->field_type, 'textarea' ); ?>><?php esc_html_e( 'Textarea', 'queues' ); ?></option>
                                    <option value="select"  <?php selected( $item->field_type, 'select' ); ?>><?php esc_html_e( 'Select', 'queues' ); ?></option>
                                </select>
                                <?php submit_button( __( 'Save', 'queues' ), 'small', 'update_field', false ); ?>
                                <a href="<?php echo esc_url( admin_url( 'admin.php?page=queues_fields' ) ); ?>" class="button"><?php esc_html_e( 'Cancel', 'queues' ); ?></a>
                            </form>
                        <?php else : ?>
                            <?php echo esc_html( $item->field_label ); ?>
                        <?php endif; ?>
                    </td>
                    <td><?php echo esc_html( $item->field_type ); ?></td>
                    <td>
                        <?php
                        $assocs = [];
                        if ( $item->category_id ) {
                            $assocs[] = esc_html( $item->category_name );
                        }
                        if ( $item->help_topic_id ) {
                            $assocs[] = esc_html( $item->help_topic );
                        }
                        echo implode( ' & ', $assocs );
                        ?>
                    </td>
                    <td>
                        <?php if ( ! $edit_mode ) : ?>
                            <a href="<?php echo esc_url( add_query_arg( [
                                'page'  => 'queues_fields',
                                'action'=> 'edit',
                                'field' => $item->id,
                            ], admin_url( 'admin.php' ) ) ); ?>"><?php esc_html_e( 'Edit', 'queues' ); ?></a>
                            |
                            <?php
                            $del_url = wp_nonce_url(
                                add_query_arg( [
                                    'page'  => 'queues_fields',
                                    'action'=> 'delete',
                                    'field' => $item->id,
                                ], admin_url( 'admin.php' ) ),
                                'delete_field_' . $item->id
                            );
                            ?>
                            <a href="<?php echo esc_url( $del_url ); ?>" onclick="return confirm('<?php esc_attr_e( 'Delete this field?', 'queues' ); ?>')"><?php esc_html_e( 'Delete', 'queues' ); ?></a>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>

            <?php if ( empty( $items ) ) : ?>
                <tr>
                    <td colspan="5"><?php esc_html_e( 'No custom fields found.', 'queues' ); ?></td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>
