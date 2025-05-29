<?php
// File: admin/agents.php
// Description: Admin page for managing Agents via WP users, multi-queue assignment

if ( ! defined( 'ABSPATH' ) ) exit;
if ( ! current_user_can( 'manage_options' ) ) wp_die();

global $wpdb;
$p            = $wpdb->prefix;
$agent_table  = "{$p}queues_agents";
$map_table    = "{$p}queues_agent_queues";
$qtable       = "{$p}queues_categories";

// Fetch all WP users for dropdown
$wp_users = get_users( [ 'fields' => [ 'ID', 'display_name' ] ] );

// Fetch all queues for checkboxes
$queues = $wpdb->get_results( "SELECT id, name FROM {$qtable} ORDER BY name ASC" );

// Handle Add
if ( isset( $_POST['add_agent'] ) ) {
    check_admin_referer( 'add_agent_nonce' );
    $user_id = intval( $_POST['wp_user_id'] );
    $sel     = array_map( 'intval', (array) $_POST['queues'] );
    if ( $user_id && $sel ) {
        $wpdb->insert( $agent_table, [ 'wp_user_id' => $user_id ] );
        $aid = $wpdb->insert_id;
        foreach ( $sel as $qid ) {
            $wpdb->insert( $map_table, [ 'agent_id' => $aid, 'queue_id' => $qid ] );
        }
    }
}

// Handle Update
if ( isset( $_POST['update_agent'] ) ) {
    check_admin_referer( 'update_agent_nonce' );
    $aid     = intval( $_POST['agent_id'] );
    $sel     = array_map( 'intval', (array) $_POST['queues'] );
    if ( $aid && $sel ) {
        // no change to wp_user_id
        $wpdb->delete( $map_table, [ 'agent_id' => $aid ] );
        foreach ( $sel as $qid ) {
            $wpdb->insert( $map_table, [ 'agent_id' => $aid, 'queue_id' => $qid ] );
        }
    }
}

// Handle Delete
if ( isset( $_GET['action'], $_GET['agent'] ) && $_GET['action'] === 'delete' ) {
    $aid = intval( $_GET['agent'] );
    if ( wp_verify_nonce( $_GET['_wpnonce'], 'delete_agent_' . $aid ) ) {
        $wpdb->delete( $agent_table, [ 'id' => $aid ] );
        // mapping entries cascade
    }
}

// Fetch agents + their assigned queues
$agents = $wpdb->get_results( "SELECT * FROM {$agent_table} ORDER BY id ASC" );
foreach ( $agents as &$a ) {
    $a->queues = $wpdb->get_col( $wpdb->prepare(
        "SELECT queue_id FROM {$map_table} WHERE agent_id = %d", $a->id
    ) );
}
unset( $a );
?>
<div class="wrap">
  <h1><?php esc_html_e( 'Agents', 'queues' ); ?></h1>

  <h2><?php esc_html_e( 'Add New Agent', 'queues' ); ?></h2>
  <form method="post">
    <?php wp_nonce_field( 'add_agent_nonce' ); ?>
    <table class="form-table">
      <tr>
        <th><label for="wp_user_id"><?php esc_html_e( 'Select existing user', 'queues' ); ?></label></th>
        <td>
          <select name="wp_user_id" id="wp_user_id" class="regular-text" style="width: 300px" required>
            <option value=""><?php esc_html_e( '— Select User —', 'queues' ); ?></option>
            <?php foreach ( $wp_users as $u ) : ?>
              <option value="<?php echo esc_attr( $u->ID ); ?>">
                <?php echo esc_html( $u->display_name ); ?>
              </option>
            <?php endforeach; ?>
          </select>
        </td>
      </tr>
      <tr>
        <th><?php esc_html_e( 'Assigned Queues (categories)', 'queues' ); ?></th>
        <td>
          <?php foreach ( $queues as $q ) : ?>
            <label>
              <input
                type="checkbox"
                name="queues[]"
                value="<?php echo esc_attr( $q->id ); ?>"
              > <?php echo esc_html( $q->name ); ?>
            </label><br>
          <?php endforeach; ?>
        </td>
      </tr>
    </table>
    <?php submit_button( __( 'Add Agent', 'queues' ), 'primary', 'add_agent' ); ?>
  </form>

  <h2><?php esc_html_e( 'Existing Agents', 'queues' ); ?></h2>
  <table class="widefat fixed striped">
    <thead>
      <tr>
        <th><?php esc_html_e( 'ID', 'queues' ); ?></th>
        <th><?php esc_html_e( 'User', 'queues' ); ?></th>
        <th><?php esc_html_e( 'Assigned Queues', 'queues' ); ?></th>
        <th><?php esc_html_e( 'Actions', 'queues' ); ?></th>
      </tr>
    </thead>
    <tbody>
    <?php foreach ( $agents as $a ) :
      $edit = (
        isset( $_GET['action'], $_GET['agent'] )
        && $_GET['action'] === 'edit'
        && intval( $_GET['agent'] ) === $a->id
      );
      $user = get_user_by( 'ID', $a->wp_user_id );
    ?>
      <tr>
        <td><?php echo esc_html( $a->id ); ?></td>
        <td>
          <?php echo esc_html( $user ? $user->display_name : __( 'Unknown', 'queues' ) ); ?>
        </td>
        <td>
          <?php if ( $edit ) : ?>
            <form method="post" style="display:inline">
              <?php wp_nonce_field( 'update_agent_nonce' ); ?>
              <input type="hidden" name="agent_id" value="<?php echo $a->id; ?>">
              <?php foreach ( $queues as $q ) : ?>
                <label>
                  <input
                    type="checkbox"
                    name="queues[]"
                    value="<?php echo esc_attr( $q->id ); ?>"
                    <?php checked( in_array( $q->id, $a->queues ), true ); ?>
                  > <?php echo esc_html( $q->name ); ?>
                </label><br>
              <?php endforeach; ?>
              <?php submit_button( __( 'Save', 'queues' ), 'small', 'update_agent', false ); ?>
              <a href="<?php echo admin_url( 'admin.php?page=queues_agents' ); ?>" class="button"><?php esc_html_e( 'Cancel', 'queues' ); ?></a>
            </form>
          <?php else : ?>
            <?php
              $names = array_map(
                fn( $id ) => esc_html( array_values( array_filter( $queues, fn($x)=>$x->id===$id ) )[0]->name ),
                $a->queues
              );
              echo implode( ', ', $names );
            ?>
          <?php endif; ?>
        </td>
        <td>
          <?php if ( ! $edit ) : ?>
            <a href="<?php echo esc_url( add_query_arg([
              'page'=>'queues_agents',
              'action'=>'edit',
              'agent'=>$a->id
            ], admin_url('admin.php'))); ?>"><?php esc_html_e('Edit','queues'); ?></a> |
            <?php $del = wp_nonce_url( add_query_arg([
                'page'=>'queues_agents',
                'action'=>'delete',
                'agent'=>$a->id
            ], admin_url('admin.php')), 'delete_agent_'.$a->id ); ?>
            <a href="<?php echo esc_url( $del ); ?>" onclick="return confirm('<?php esc_attr_e('Delete this agent?','queues'); ?>')"><?php esc_html_e('Delete','queues'); ?></a>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    <?php if ( empty( $agents ) ) : ?>
      <tr><td colspan="4"><?php esc_html_e( 'No agents found.', 'queues' ); ?></td></tr>
    <?php endif; ?>
    </tbody>
  </table>
</div>
<!-- Select2 assets -->
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
jQuery(document).ready(function($) {
  $('#wp_user_id').select2({
    placeholder: "<?php esc_attr_e( 'Search user...', 'queues' ); ?>",
    width: 'resolve'
  });
});
</script>
