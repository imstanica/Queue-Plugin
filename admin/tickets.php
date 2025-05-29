<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

global $wpdb;
$current_user = wp_get_current_user();
$action       = isset( $_GET['action'] ) ? $_GET['action'] : '';

// ——— Handle ticket update (editing meta only) ——————————————————————
if ( 'view' === $action
  && isset( $_POST['update_ticket_nonce'] )
  && wp_verify_nonce( $_POST['update_ticket_nonce'], 'update_ticket' )
) {
    $fields = [
        'category_id' => intval( $_POST['category_id'] ),
        'status_id'   => intval( $_POST['status_id'] ),
        'priority_id' => intval( $_POST['priority_id'] ),
    ];
    updateTicket( $current_user->ID, intval( $_GET['id'] ), $fields );
    echo '<div class="notice notice-success"><p>Ticket updated successfully.</p></div>';
}

// ——— Handle add comment —————————————————————————————————————
if ( 'view' === $action
  && isset( $_POST['add_comment_nonce'] )
  && wp_verify_nonce( $_POST['add_comment_nonce'], 'add_comment' )
) {
    addComment( $current_user->ID, intval( $_GET['id'] ), sanitize_textarea_field( $_POST['comment_text'] ) );
    echo '<div class="notice notice-success"><p>Comment added successfully.</p></div>';
}

// ——— Handle creating new ticket ——————————————————————————————
if ( isset( $_POST['create_ticket_nonce'] )
  && wp_verify_nonce( $_POST['create_ticket_nonce'], 'create_ticket' )
) {
    $ticket_id = createTicket(
        $current_user->ID,
        intval( $_POST['new_user_id'] ),
        sanitize_text_field( $_POST['new_title'] ),
        sanitize_textarea_field( $_POST['new_content'] ),
        intval( $_POST['new_category_id'] ),
        intval( $_POST['new_priority_id'] )
    );
    if ( is_wp_error( $ticket_id ) ) {
        echo '<div class="notice notice-error"><p>Error: ' . esc_html( $ticket_id->get_error_message() ) . '</p></div>';
    } else {
        echo '<div class="notice notice-success"><p>Ticket #' . esc_html( $ticket_id ) . ' created successfully.</p></div>';
    }
}
?>
<div class="wrap">
  <h1 class="wp-heading-inline">Tickets</h1>
  <hr class="wp-header-end">

  <?php if ( 'view' === $action && isset( $_GET['id'] ) ) :

    // ——— VIEW / EDIT TICKET ——————————————————————————————
    $ticket = getTicketById( $current_user->ID, intval( $_GET['id'] ) );
    if ( ! $ticket ) {
      wp_die( 'Access denied or ticket not found.' );
    }

    // Map internal → WP IDs
    $agent_wp_id = $wpdb->get_var( $wpdb->prepare(
      "SELECT wp_user_id FROM {$wpdb->prefix}queues_agents WHERE id=%d",
      intval( $ticket->agent_id )
    ) );
    $opened_by   = $agent_wp_id ? get_userdata( $agent_wp_id ) : false;

    $user_wp_id  = $wpdb->get_var( $wpdb->prepare(
      "SELECT wp_user_id FROM {$wpdb->prefix}queues_users WHERE id=%d",
      intval( $ticket->user_id )
    ) );
    $opened_for  = $user_wp_id  ? get_userdata( $user_wp_id )  : false;
    ?>
    <h2>Ticket #<?php echo esc_html( $ticket->id ); ?></h2>
    <form method="post" class="wp-clearfix">
      <?php wp_nonce_field( 'update_ticket', 'update_ticket_nonce' ); ?>

      <table class="widefat" style="border:none;">
        <tr>
          <!-- LEFT: Title & Content -->
          <td style="width:60%; vertical-align:top; padding-right:20px; border:none;">
            <h3>Main Details</h3>
            <table class="form-table">
              <tr>
                <th scope="row"><label for="title">Title</label></th>
                <td>
                  <input name="title" id="title" type="text"
                         value="<?php echo esc_attr( $ticket->title ); ?>"
                         class="regular-text" readonly disabled>
                </td>
              </tr>
              <tr>
                <th scope="row"><label for="content">Content</label></th>
                <td>
                  <textarea name="content" id="content" rows="5"
                            class="large-text" readonly disabled><?php echo esc_textarea( $ticket->content ); ?></textarea>
                </td>
              </tr>
            </table>
          </td>
          <!-- RIGHT: Meta, Opened by/for -->
          <td style="width:40%; vertical-align:top; border:none;">
            <h3>Meta</h3>
            <table class="form-table">
              <tr>
                <th scope="row"><label for="category_id">Category</label></th>
                <td>
                <select name="category_id" id="category_id" class="select2" style="width: 100%;">
                  <?php
                    foreach ( get_all_categories() as $cat ) {
                      printf(
                        '<option value="%d"%s>%s</option>',
                        esc_attr( $cat->id ),
                        selected( $ticket->category_id, $cat->id, false ),
                        esc_html( $cat->name )
                      );
                    }
                  ?>
                </select>
                </td>
              </tr>
              <tr>
                <th scope="row"><label for="status_id">Status</label></th>
                <td>
                <select name="status_id" id="status_id" class="select2" style="width: 100%;">
                  <?php
                    $statuses = get_all_statuses();
                    foreach ( $statuses as $status ) {
                      printf(
                        '<option value="%d"%s>%s</option>',
                        esc_attr( $status->id ),
                        selected( $ticket->status_id, $status->id, false ),
                        esc_html( $status->name )
                      );
                    }
                  ?>
                </select>
                </td>
              </tr>
              <tr>
                <th scope="row"><label for="priority_id">Priority</label></th>
                <td>
                <select name="priority_id" id="priority_id" class="select2" style="width: 100%;">
                  <?php
                    foreach ( get_all_priorities() as $prio ) {
                      printf(
                        '<option value="%d"%s>%s</option>',
                        esc_attr( $prio->id ),
                        selected( $ticket->priority_id, $prio->id, false ),
                        esc_html( $prio->name )
                      );
                    }
                  ?>
                </select>
                </td>
              </tr>
              <tr>
                <th scope="row">Opened by</th>
                <td><?php echo $opened_by instanceof WP_User ? esc_html( $opened_by->display_name ) : '<em>Unknown</em>'; ?></td>
              </tr>
              <tr>
                <th scope="row">Opened for</th>
                <td><?php echo $opened_for instanceof WP_User ? esc_html( $opened_for->display_name ) : '<em>Unknown</em>'; ?></td>
              </tr>
            </table>
          </td>
        </tr>
      </table>

      <?php submit_button( 'Save Changes' ); ?>
    </form>

    <section class="mt-4">
      <h2>Comments</h2>
      <?php
      $comments = getComments( $ticket->id );
      if ( $comments ) :
        foreach ( $comments as $c ) :
          // Map internal → WP user and guard
          $wuid = $wpdb->get_var( $wpdb->prepare(
            "SELECT wp_user_id FROM {$wpdb->prefix}queues_users WHERE id=%d",
            intval( $c->user_id )
          ) );
          $u    = $wuid ? get_userdata( $wuid ) : false;
          $name = $u instanceof WP_User ? $u->display_name : __( 'Unknown', 'queues' );
      ?>
        <div class="comment-block">
          <p><strong><?php echo esc_html( $name ); ?></strong>
             <em><?php echo esc_html( $c->created_at ); ?></em></p>
          <p><?php echo esc_html( $c->comment_text ); ?></p>
        </div>
      <?php
        endforeach;
      else :
        echo '<p>' . esc_html__( 'No comments yet.', 'queues' ) . '</p>';
      endif;
      ?>

      <form method="post" class="mt-2">
        <?php wp_nonce_field( 'add_comment', 'add_comment_nonce' ); ?>
        <textarea name="comment_text" rows="3" class="large-text" placeholder="<?php esc_attr_e( 'Add a comment…', 'queues' ); ?>" required></textarea>
        <?php submit_button( __( 'Add Comment', 'queues' ), 'secondary', 'add_comment' ); ?>
      </form>
    </section>

    <section class="mt-4">
      <h2>History</h2>
      <table class="widefat fixed striped">
        <thead><tr>
          <th>When</th><th>User</th><th>Field</th><th>Old</th><th>New</th>
        </tr></thead>
        <tbody>
          <?php foreach ( getHistory( $ticket->id ) as $h ) : 
            $ch_user = get_userdata( intval( $h->changed_by ) );
            $ch_name = $ch_user instanceof WP_User ? $ch_user->display_name : __( 'Unknown', 'queues' );
          ?>
          <tr>
            <td><?php echo esc_html( $h->changed_at ); ?></td>
            <td><?php echo esc_html( $ch_name ); ?></td>
            <td><?php echo esc_html( $h->field_changed ); ?></td>
            <td><?php echo esc_html( $h->old_value ); ?></td>
            <td><?php echo esc_html( $h->new_value ); ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </section>

  <?php else :

    // ——— LIST + CREATE ——————————————————————————————————————
    $stats   = countActiveTicketsByCategory( $current_user->ID );
    $tickets = listTicketsForAgent( $current_user->ID );
  ?>
    <div class="tickets-wrapper">
      <section class="ticket-create">
        <h2>Add New Ticket</h2>
        <form method="post">
          <?php wp_nonce_field( 'create_ticket', 'create_ticket_nonce' ); ?>

          <table class="widefat" style="border:none;">
            <tr>
              <!-- LEFT: Main Details -->
              <td style="width:60%; vertical-align:top; padding-right:20px; border:none;">
                <h3>Main Details</h3>
                <table class="form-table">
                  <tr>
                    <th scope="row"><label for="new_title">Title</label></th>
                    <td><input name="new_title" id="new_title" type="text" class="regular-text" required></td>
                  </tr>
                  <tr>
                    <th scope="row"><label for="new_content">Content</label></th>
                    <td><textarea name="new_content" id="new_content" rows="4" class="large-text" required></textarea></td>
                  </tr>
                </table>
              </td>

              <!-- RIGHT: Meta -->
              <td style="width:40%; vertical-align:top; border:none;">
                <table class="form-table">
                  <tr>
                    <th scope="row"><label for="new_category_id">Category</label></th>
                    <td>
                      <select name="new_category_id" id="new_category_id" class="select2" style="width: 100%;" data-placeholder="Select category…" required>
                        <option value="" selected disabled hidden></option>
                        <?php foreach ( get_all_categories() as $c ) : ?>
                          <option value="<?php echo esc_attr( $c->id ); ?>"><?php echo esc_html( $c->name ); ?></option>
                        <?php endforeach; ?>
                      </select>
                    </td>
                  </tr>

                  <tr>
                    <th scope="row"><label for="new_status_id">Status</label></th>
                    <td>
                      <select name="new_status_id" id="new_status_id" class="select2" style="width: 100%;" data-placeholder="Select status…" required>
                        <option value="" selected disabled hidden></option>
                        <?php
                          foreach ( get_all_statuses() as $s ) {
                            printf(
                              '<option value="%d">%s</option>',
                              esc_attr( $s->id ),
                              esc_html( $s->name )
                            );
                          }
                        ?>
                      </select>
                    </td>
                  </tr>

                  <tr>
                    <th scope="row"><label for="new_priority_id">Priority</label></th>
                    <td>
                      <select name="new_priority_id" id="new_priority_id" class="select2" style="width: 100%;" data-placeholder="Select priority…" required>
                        <option value="" selected disabled hidden></option>
                        <?php foreach ( get_all_priorities() as $p ) : ?>
                          <option value="<?php echo esc_attr( $p->id ); ?>"><?php echo esc_html( $p->name ); ?></option>
                        <?php endforeach; ?>
                      </select>
                    </td>
                  </tr>

                  <tr>
                    <th scope="row"><label for="new_user_id">Open for</label></th>
                    <td>
                      <select name="new_user_id" id="new_user_id" class="select2" style="width: 100%;" data-placeholder="Search user…" required>
                        <option value="" selected disabled hidden></option>
                        <?php
                        $users = $wpdb->get_results(
                          "SELECT u.wp_user_id AS wp_user_id, w.display_name
                          FROM {$wpdb->prefix}queues_users u
                          JOIN {$wpdb->users}       w ON u.wp_user_id = w.ID
                          ORDER BY w.display_name"
                        );
                        foreach ( $users as $u ) :
                        ?>
                          <option value="<?php echo esc_attr( $u->wp_user_id ); ?>">
                            <?php echo esc_html( $u->display_name ); ?>
                          </option>
                        <?php endforeach; ?>
                      </select>
                    </td>
                  </tr>
                </table>
              </td>
            </tr>
          </table>

          <?php submit_button( 'Create Ticket' ); ?>
        </form>
      </section>

      <section class="ticket-stats">
        <h2>Active Tickets by Category</h2>
        <?php if ( ! empty( $stats ) ) : ?>
          <ul>
            <?php
            $cats_map = wp_list_pluck( get_all_categories(), 'name', 'id' );
            foreach ( $stats as $row ) :
              $name  = $cats_map[ $row['category_id'] ] ?? '(Unknown)';
              $count = intval( $row['active_count'] );
            ?>
              <li><strong><?php echo esc_html( $name ); ?>:</strong> <?php echo esc_html( $count ); ?></li>
            <?php endforeach; ?>
          </ul>
        <?php else : ?>
          <p>No active tickets.</p>
        <?php endif; ?>
      </section>

      <section class="ticket-list">
        <h2>Your Tickets</h2>
        <table class="widefat fixed striped">
          <thead>
            <tr>
              <th>ID</th>
              <th>Title</th>
              <th>Status</th>
              <th>Priority</th>
              <th>Created</th>
              <th>Action</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ( $tickets as $t ) : ?>
              <tr>
                <td><?php echo esc_html( $t->id ); ?></td>
                <td><?php echo esc_html( $t->title ); ?></td>
                <td><?php echo esc_html( get_status_name( $t->status_id ) ); ?></td>
                <td><?php echo esc_html( get_priority_name( $t->priority_id ) ); ?></td>
                <td><?php echo esc_html( $t->created_at ); ?></td>
                <td><a href="<?php echo esc_url( add_query_arg( [ 'action' => 'view', 'id' => $t->id ] ) ); ?>">View</a></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </section>
    </div>

  <?php endif; ?>
</div>

<!-- Select2 CSS -->
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />

<!-- jQuery (deja este în WP admin) -->
<!-- Select2 JS -->
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<!-- Inițializare Select2 -->
<script>
document.addEventListener("DOMContentLoaded", function() {
  jQuery('select.select2').select2({
    width: 'resolve',
    placeholder: function() {
      return jQuery(this).data('placeholder');
    },
    allowClear: true
  });
});
</script>
