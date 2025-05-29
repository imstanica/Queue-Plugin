<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Prevent double inclusion
if ( defined( 'QUEUES_TICKETS_FUNCTIONS_LOADED' ) ) {
    return;
}
define( 'QUEUES_TICKETS_FUNCTIONS_LOADED', true );

global $wpdb;

// Table name helpers
function queues_tickets_table() {
    global $wpdb;
    return $wpdb->prefix . 'queues_tickets';
}
function queues_ticket_comments_table() {
    global $wpdb;
    return $wpdb->prefix . 'queues_ticket_comments';
}
function queues_ticket_history_table() {
    global $wpdb;
    return $wpdb->prefix . 'queues_ticket_history';
}

// Map WP user ID to internal agent ID
function get_internal_agent_id( $wp_user_id ) {
    global $wpdb;
    return $wpdb->get_var( $wpdb->prepare(
        "SELECT id FROM {$wpdb->prefix}queues_agents WHERE wp_user_id = %d",
        intval( $wp_user_id )
    ) );
}

// Map WP user ID to internal user ID
function get_internal_user_id( $wp_user_id ) {
    global $wpdb;
    return $wpdb->get_var( $wpdb->prepare(
        "SELECT id FROM {$wpdb->prefix}queues_users WHERE wp_user_id = %d",
        intval( $wp_user_id )
    ) );
}

// 1. List tickets for a given WP agent
function listTicketsForAgent( $wp_agent_id ) {
    global $wpdb;
    return $wpdb->get_results(
        "SELECT * 
         FROM " . queues_tickets_table() . " 
         ORDER BY created_at DESC"
    );
}


// 2. Count active tickets by category for a given WP agent
function countActiveTicketsByCategory( $wp_agent_id ) {
    global $wpdb;
    $agent_int = get_internal_agent_id( $wp_agent_id );
    if ( ! $agent_int ) {
        return [];
    }
    $cats = $wpdb->get_col( $wpdb->prepare(
        "SELECT queue_id FROM {$wpdb->prefix}queues_agent_queues WHERE agent_id = %d",
        $agent_int
    ) );
    if ( empty( $cats ) ) {
        return [];
    }
    $placeholders = implode( ',', array_fill( 0, count( $cats ), '%d' ) );
    $sql = "
        SELECT category_id, COUNT(*) AS active_count
        FROM " . queues_tickets_table() . "
        WHERE status_id != 3
          AND category_id IN ($placeholders)
        GROUP BY category_id
    ";
    return $wpdb->get_results( $wpdb->prepare( $sql, ...$cats ), ARRAY_A );
}

// 3. Get ticket by ID, with permission check
function getTicketById( $wp_agent_id, $ticket_id ) {
    global $wpdb;
    return $wpdb->get_row(
        $wpdb->prepare(
            "SELECT * 
             FROM " . queues_tickets_table() . " 
             WHERE id = %d",
            intval( $ticket_id )
        )
    );
}

// 4. Create ticket (maps WP IDs internally)
function createTicket( $wp_agent_id, $wp_user_id, $title, $content, $category_id, $priority_id ) {
	global $wpdb;
    error_log("[Queues] createTicket() called: WP agent=$wp_agent_id, WP user=$wp_user_id, cat=$category_id, prio=$priority_id");
    $agent_int = get_internal_agent_id( $wp_agent_id );
    error_log("[Queues] get_internal_agent_id({$wp_agent_id}) returned " . var_export($agent_int, true));
    if ( ! $agent_int ) {
        return new WP_Error( 'no_agent', 'Agent record not found.' );
    }
    $user_int = get_internal_user_id( $wp_user_id );
    error_log("[Queues] get_internal_user_id({$wp_user_id}) returned " . var_export($user_int, true));
    if ( ! $user_int ) {
        return new WP_Error( 'no_user', 'User record not found.' );
    }
    $now = current_time( 'mysql' );
    $wpdb->insert(
        queues_tickets_table(),
        [
            'agent_id'    => $agent_int,
            'user_id'     => $user_int,
            'priority_id' => intval( $priority_id ),
            'title'       => sanitize_text_field( $title ),
            'content'     => sanitize_textarea_field( $content ),
            'category_id' => intval( $category_id ),
            'status_id'   => 1,
            'created_at'  => $now,
            'updated_at'  => $now,
        ],
        [ '%d','%d','%d','%s','%s','%d','%d','%s','%s' ]
    );
    $ticket_id = $wpdb->insert_id;
    recordTicketHistory( $ticket_id, 'status', '', '1', $agent_int );
    return $ticket_id;
}

// 5. Update ticket
function updateTicket( $wp_agent_id, $ticket_id, $fields ) {
    global $wpdb;
    $agent_int = get_internal_agent_id( $wp_agent_id );
    if ( ! $agent_int ) {
        return new WP_Error( 'unauthorized', 'Agent not found.' );
    }
    $ticket = getTicketById( $wp_agent_id, $ticket_id );
    if ( ! $ticket ) {
        return new WP_Error( 'unauthorized', 'No access or ticket not found.' );
    }
    $allowed = [ 'category_id', 'status_id', 'priority_id', 'title', 'content' ];
    $updates = [];
    $formats = [];
    foreach ( $fields as $key => $value ) {
        if ( in_array( $key, $allowed, true ) ) {
            $updates[ $key ] = sanitize_text_field( $value );
            $formats[]     = '%s';
            recordTicketHistory( $ticket_id, $key, $ticket->$key, $value, $agent_int );
        }
    }
    if ( empty( $updates ) ) {
        return 0;
    }
    $updates['updated_at'] = current_time( 'mysql' );
    $formats[]             = '%s';
    return $wpdb->update(
        queues_tickets_table(),
        $updates,
        [ 'id' => $ticket_id ],
        $formats,
        [ '%d' ]
    );
}

// 6. Commenting
function addComment( $wp_user_id, $ticket_id, $text ) {
    global $wpdb;
    $user_int = get_internal_user_id( $wp_user_id );
    if ( ! $user_int ) {
        return new WP_Error( 'no_user', 'User record not found.' );
    }
    $now = current_time( 'mysql' );
    $wpdb->insert(
        queues_ticket_comments_table(),
        [
            'ticket_id'   => intval( $ticket_id ),
            'user_id'     => $user_int,
            'comment_text'=> sanitize_textarea_field( $text ),
            'created_at'  => $now,
        ],
        [ '%d','%d','%s','%s' ]
    );
    return $wpdb->insert_id;
}
function getComments( $ticket_id ) {
    global $wpdb;
    return $wpdb->get_results( $wpdb->prepare(
        "SELECT * FROM " . queues_ticket_comments_table() . " WHERE ticket_id = %d ORDER BY created_at ASC",
        intval( $ticket_id )
    ) );
}

// 7. History logging
function recordTicketHistory( $ticket_id, $field, $old, $new, $changed_by_int ) {
    global $wpdb;
    $wpdb->insert(
        queues_ticket_history_table(),
        [
            'ticket_id'     => intval( $ticket_id ),
            'field_changed' => sanitize_text_field( $field ),
            'old_value'     => sanitize_text_field( $old ),
            'new_value'     => sanitize_text_field( $new ),
            'changed_by'    => intval( $changed_by_int ),
            'changed_at'    => current_time( 'mysql' ),
        ],
        [ '%d','%s','%s','%s','%d','%s' ]
    );
}
function getHistory( $ticket_id ) {
    global $wpdb;
    return $wpdb->get_results( $wpdb->prepare(
        "SELECT * FROM " . queues_ticket_history_table() . " WHERE ticket_id = %d ORDER BY changed_at ASC",
        intval( $ticket_id )
    ) );
}

// 8. Lookup helpers for dropdowns
function get_all_categories() {
    global $wpdb;
    return $wpdb->get_results( "SELECT id,name FROM {$wpdb->prefix}queues_categories ORDER BY name" );
}
function get_all_statuses() {
    global $wpdb;
    return $wpdb->get_results( "SELECT id,name FROM {$wpdb->prefix}queues_statuses ORDER BY id" );
}
function get_all_priorities() {
    global $wpdb;
    return $wpdb->get_results( "SELECT id,name FROM {$wpdb->prefix}queues_priorities ORDER BY id" );
}
function get_status_name( $id ) {
    foreach ( get_all_statuses() as $s ) {
        if ( $s->id === intval( $id ) ) {
            return $s->name;
        }
    }
    return '';
}
function get_priority_name( $id ) {
    foreach ( get_all_priorities() as $p ) {
        if ( $p->id === intval( $id ) ) {
            return $p->name;
        }
    }
    return '';
}

