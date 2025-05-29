<?php
// File: admin/report_categories.php
// Description: Admin page for Report Categories (3-level tree + required flag)

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
if ( ! current_user_can( 'manage_options' ) ) {
    wp_die( __( 'You do not have sufficient permissions to access this page.', 'queues' ) );
}

global $wpdb;
$table       = $wpdb->prefix . 'queues_report_categories';

// Handle Add
if ( isset( $_POST['add_rcat'] ) ) {
    check_admin_referer( 'add_rcat_nonce' );
    $name      = sanitize_text_field( $_POST['name'] );
    $parent_id = intval( $_POST['parent_id'] ) ?: null;
    // required only valid if parent selected
    $required  = $parent_id ? 1 : 0;
    if ( $name ) {
        $wpdb->insert( $table, [
            'name'      => $name,
            'parent_id' => $parent_id,
            'required'  => $required,
        ] );
    }
}

// Handle Update
if ( isset( $_POST['update_rcat'] ) ) {
    check_admin_referer( 'update_rcat_nonce' );
    $id        = intval( $_POST['rcat_id'] );
    $name      = sanitize_text_field( $_POST['name'] );
    $parent_id = intval( $_POST['parent_id'] ) ?: null;
    $required  = $parent_id ? ( isset( $_POST['required'] ) ? 1 : 0 ) : 0;
    if ( $id && $name ) {
        $wpdb->update( $table, [
            'name'      => $name,
            'parent_id' => $parent_id,
            'required'  => $required,
        ], [ 'id' => $id ] );
    }
}

// Handle Delete
if ( isset( $_GET['action'], $_GET['rcat'] ) && $_GET['action'] === 'delete' ) {
    $rcat_id = intval( $_GET['rcat'] );
    if ( wp_verify_nonce( $_GET['_wpnonce'], 'delete_rcat_' . $rcat_id ) ) {
        $wpdb->delete( $table, [ 'id' => $rcat_id ] );
    }
}

// Fetch all for dropdown & list
$all = $wpdb->get_results( "SELECT id, name, parent_id, required FROM {$table} ORDER BY parent_id ASC, name ASC" );
$parents = array_filter( $all, fn($r)=> $r->parent_id === null );
?>
<div class="wrap">
  <h1><?php esc_html_e( 'Report Categories', 'queues' ); ?></h1>

  <h2><?php esc_html_e( 'Add New Category', 'queues' ); ?></h2>
  <form method="post" action="">
    <?php wp_nonce_field( 'add_rcat_nonce' ); ?>
    <table class="form-table">
      <tr>
        <th scope="row"><label for="name"><?php esc_html_e( 'Name', 'queues' ); ?></label></th>
        <td><input name="name" id="name" type="text" class="regular-text" required></td>
      </tr>
      <tr>
        <th scope="row"><label for="parent_id"><?php esc_html_e( 'Parent Category', 'queues' ); ?></label></th>
        <td>
          <select name="parent_id" id="parent_id">
            <option value=""><?php esc_html_e( '— None —', 'queues' ); ?></option>
            <?php foreach ( $parents as $p ) : ?>
              <option value="<?php echo esc_attr( $p->id ); ?>">
                <?php echo esc_html( $p->name ); ?>
              </option>
            <?php endforeach; ?>
          </select>
        </td>
      </tr>
      <tr id="required-row" style="display:none;">
        <th scope="row"><label for="required"><?php esc_html_e( 'Required', 'queues' ); ?></label></th>
        <td><input name="required" id="required" type="checkbox" value="1"></td>
      </tr>
    </table>
    <?php submit_button( __( 'Add Category', 'queues' ), 'primary', 'add_rcat' ); ?>
  </form>

  <h2><?php esc_html_e( 'Existing Report Categories', 'queues' ); ?></h2>
  <table class="widefat fixed striped">
    <thead>
      <tr>
        <th><?php esc_html_e( 'ID', 'queues' ); ?></th>
        <th><?php esc_html_e( 'Name', 'queues' ); ?></th>
        <th><?php esc_html_e( 'Parent', 'queues' ); ?></th>
        <th><?php esc_html_e( 'Required', 'queues' ); ?></th>
        <th><?php esc_html_e( 'Actions', 'queues' ); ?></th>
      </tr>
    </thead>
    <tbody>
    <?php foreach ( $all as $r ) :
      $edit = ( isset( $_GET['action'], $_GET['rcat'] )
                && $_GET['action'] === 'edit'
                && intval( $_GET['rcat'] ) === $r->id );
    ?>
      <tr>
        <td><?php echo esc_html( $r->id ); ?></td>
        <td>
          <?php if ( $edit ) : ?>
            <form method="post" style="display:inline">
              <?php wp_nonce_field( 'update_rcat_nonce' ); ?>
              <input type="hidden" name="rcat_id" value="<?php echo esc_attr( $r->id ); ?>">
              <input name="name" type="text" value="<?php echo esc_attr( $r->name ); ?>" required>
              <select name="parent_id" onchange="document.getElementById('req_<?php echo $r->id;?>').style.display=this.value?'table-row':'none'">
                <option value=""><?php esc_html_e( '— None —', 'queues' ); ?></option>
                <?php foreach ( $parents as $p ) : ?>
                  <option value="<?php echo $p->id; ?>" <?php selected( $r->parent_id, $p->id ); ?>>
                    <?php echo esc_html( $p->name ); ?>
                  </option>
                <?php endforeach; ?>
              </select>
              <tr id="req_<?php echo $r->id;?>" style="display:<?php echo $r->parent_id?'table-row':'none';?>">
                <th><label for="required_<?php echo $r->id;?>"><?php esc_html_e( 'Required', 'queues' ); ?></label></th>
                <td>
                  <input
                    type="checkbox"
                    name="required"
                    id="required_<?php echo $r->id;?>"
                    value="1"
                    <?php checked( $r->required, 1 ); ?>>
                </td>
              </tr>
              <?php submit_button( __( 'Save', 'queues' ), 'small', 'update_rcat', false ); ?>
              <a href="<?php echo esc_url( admin_url( 'admin.php?page=queues_report_categories' ) ); ?>" class="button">
                <?php esc_html_e( 'Cancel', 'queues' ); ?>
              </a>
            </form>
          <?php else : ?>
            <?php echo esc_html( $r->name ); ?>
          <?php endif; ?>
        </td>
        <td>
          <?php
            $parent = array_filter( $all, fn($x)=> $x->id === $r->parent_id );
            echo $parent ? esc_html( array_values($parent)[0]->name ) : '—';
          ?>
        </td>
        <td><?php echo $r->required ? esc_html__('Yes','queues') : esc_html__('No','queues'); ?></td>
        <td>
          <?php if ( ! $edit ) : ?>
            <a href="<?php echo esc_url( add_query_arg([
               'page'=>'queues_report_categories',
               'action'=>'edit',
               'rcat'=>$r->id
            ], admin_url('admin.php'))); ?>">
              <?php esc_html_e( 'Edit', 'queues' ); ?>
            </a> |
            <?php $url = wp_nonce_url(
              add_query_arg([
                'page'=>'queues_report_categories',
                'action'=>'delete',
                'rcat'=>$r->id
              ], admin_url('admin.php')),
              'delete_rcat_'.$r->id
            ); ?>
            <a href="<?php echo esc_url( $url ); ?>" onclick="return confirm('<?php esc_attr_e('Delete this category?','queues');?>')">
              <?php esc_html_e( 'Delete', 'queues' ); ?>
            </a>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    <?php if ( empty( $all ) ) : ?>
      <tr><td colspan="5"><?php esc_html_e( 'No report categories found.', 'queues' ); ?></td></tr>
    <?php endif; ?>
    </tbody>
  </table>
</div>

<script>
// Show/hide Required checkbox on Add form
(function(){
  const sel = document.getElementById('parent_id'),
        row = document.getElementById('required-row');
  if(!sel||!row) return;
  sel.addEventListener('change',()=>{
    row.style.display = sel.value ? 'table-row' : 'none';
  });
})();
</script>
