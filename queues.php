<?php
/**
 * Plugin Name: Queues
 * Description: Simple unified backend ticketing & knowledgebase system for WordPress
 * Version: 1.12
 * Author: Marius
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Include the installer
require_once plugin_dir_path( __FILE__ ) . 'includes/installer.php';

require_once plugin_dir_path( __FILE__ ) . 'includes/tickets-functions.php';

function render_tickets_admin() {
    if ( ! current_user_can( 'agent' ) ) {
        wp_die( 'Access denied' );
    }
    include plugin_dir_path( __FILE__ ) . 'admin/tickets.php';
}

// Register activation hook
register_activation_hook( __FILE__, [ 'Queues_Installer', 'activate' ] );

add_action('admin_menu', function() {
    add_menu_page('Queues', 'Queues', 'read', 'queues_main', 'queues_main_page');
    add_submenu_page('queues_main', 'Tickets', 'Tickets', 'agent', 'queues_tickets', 'render_tickets_admin');
});


class Queues_Plugin {
    const DB_VERSION = '1.11';
    private static $instance;

    public static function instance() {
        if ( ! isset( self::$instance ) ) {
            self::$instance = new self();
            self::$instance->init_hooks();
        }
        return self::$instance;
    }

    private function init_hooks() {
        // 1) Pre-procesează formularele KB înainte de orice output
        add_action( 'admin_init',   [ $this, 'handle_kb_crud' ] );
        // 2) Înregistrează meniul
        add_action( 'admin_menu',   [ $this, 'register_menus' ] );
        // 3) Shortcode pentru front-end
        add_shortcode( 'queues_kb', [ $this, 'render_kb_shortcode' ] );
    }

    /**
     * Procesează Add/Edit/Delete pentru categorii și articole KB
     * înainte ca WP să trimită headere.
     */
    public function handle_kb_crud() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        global $wpdb;
        $p         = $wpdb->prefix;
        $cat_table = "{$p}queues_kb_categories";
        $art_table = "{$p}queues_kb_articles";

        // — Categorii
        if ( isset( $_POST['kb_cat_action'] ) ) {
            check_admin_referer( 'kb_cat_nonce' );
            $action = $_POST['kb_cat_action'];
            $name   = sanitize_text_field( $_POST['kb_cat_name'] );
            $id     = intval( $_POST['kb_cat_id'] );
            if ( $action === 'add'    && $name )             { $wpdb->insert( $cat_table, [ 'name' => $name ] ); }
            if ( $action === 'edit'   && $id && $name )       { $wpdb->update( $cat_table, [ 'name' => $name ], [ 'id' => $id ] ); }
            if ( $action === 'delete' && $id )               { $wpdb->delete( $cat_table, [ 'id' => $id ] ); }
            wp_safe_redirect( admin_url( 'admin.php?page=queues_knowledgebase' ) );
            exit;
        }

        // — Articole
        if ( isset( $_POST['kb_art_action'] ) ) {
            check_admin_referer( 'kb_art_nonce' );
            $action  = $_POST['kb_art_action'];
            $title   = sanitize_text_field( $_POST['kb_art_title'] );
            $content = wp_kses_post( $_POST['kb_art_content'] );
            $cid     = intval( $_POST['kb_art_cat'] );
            $id      = intval( $_POST['kb_art_id'] );
            if ( $action === 'add'    && $title && $content && $cid ) {
                $wpdb->insert( $art_table, [
                    'kb_category_id' => $cid,
                    'title'          => $title,
                    'content'        => $content,
                ] );
            }
            if ( $action === 'edit'   && $id && $title && $content && $cid ) {
                $wpdb->update( $art_table, [
                    'kb_category_id' => $cid,
                    'title'          => $title,
                    'content'        => $content,
                ], [ 'id' => $id ] );
            }
            if ( $action === 'delete' && $id ) {
                $wpdb->delete( $art_table, [ 'id' => $id ] );
            }
            wp_safe_redirect( admin_url( 'admin.php?page=queues_knowledgebase' ) );
            exit;
        }
    }

    public function register_menus() {
        // Top-level
        add_menu_page( 'Queues', 'Queues', 'manage_options', 'queues_tickets', [$this, 'page_tickets'], 'dashicons-tickets', 6 );
        $base = 'queues_tickets';

        // Core
        add_submenu_page( $base, 'Tickets', 'Tickets', 'manage_options', 'queues_tickets', [ $this, 'page_tickets' ] );
        add_submenu_page( $base, 'Chat',    'Chat',    'manage_options', 'queues_chat',    [ $this, 'page_chat' ] );
        add_submenu_page( $base, 'Knowledgebase', 'Knowledgebase', 'manage_options', 'queues_knowledgebase', [ $this, 'page_knowledgebase' ] );
        add_submenu_page( $base, 'Help Topics',       'Help Topics',       'manage_options', 'queues_help_topics',       [ $this, 'page_help_topics' ] );
        add_submenu_page( $base, 'Canned Responses',  'Canned Responses',  'manage_options', 'queues_canned',            [ $this, 'page_canned' ] );
        add_submenu_page( $base, 'Report Categories', 'Report Categories', 'manage_options', 'queues_report_categories', [ $this, 'page_report_categories' ] );
        add_submenu_page( $base, 'Custom Fields',     'Custom Fields',     'manage_options', 'queues_fields',            [ $this, 'page_fields' ] );
        add_submenu_page( $base, 'Categories',    'Categories',    'manage_options', 'queues_categories',    [ $this, 'page_categories' ] );
        add_submenu_page( $base, 'Statuses',      'Statuses',      'manage_options', 'queues_statuses',      [ $this, 'page_statuses' ] );
        add_submenu_page( $base, 'Priorities',    'Priorities',    'manage_options', 'queues_priorities',    [ $this, 'page_priorities' ] );
        add_submenu_page( $base, 'Users',         'Users',         'manage_options', 'queues_users',         [ $this, 'page_users' ] );
        add_submenu_page( $base, 'Organizations', 'Organizations', 'manage_options', 'queues_organizations', [ $this, 'page_organizations' ] );
        add_submenu_page( $base, 'Agents',        'Agents',        'manage_options', 'queues_agents',        [ $this, 'page_agents' ] );
        add_submenu_page( $base, 'Settings',      'Settings',      'manage_options', 'queues_settings',      [ $this, 'page_settings' ] );
    }

    // Callbacks pentru pagini
    public function page_tickets()           { include plugin_dir_path( __FILE__ ) . 'admin/tickets.php'; }
    public function page_chat()              { echo '<h1>Chat (coming soon)</h1>'; }
    public function page_knowledgebase()     { include plugin_dir_path( __FILE__ ) . 'admin/knowledgebase.php'; }
    public function page_help_topics()       { include plugin_dir_path( __FILE__ ) . 'admin/help_topics.php'; }
    public function page_canned()            { include plugin_dir_path( __FILE__ ) . 'admin/canned.php'; }
    public function page_report_categories() { include plugin_dir_path( __FILE__ ) . 'admin/report_categories.php'; }
    public function page_fields()            { include plugin_dir_path( __FILE__ ) . 'admin/fields.php'; }
    public function page_categories()        { include plugin_dir_path( __FILE__ ) . 'admin/categories.php'; }
    public function page_statuses()          { include plugin_dir_path( __FILE__ ) . 'admin/statuses.php'; }
    public function page_priorities()        { include plugin_dir_path( __FILE__ ) . 'admin/priorities.php'; }
    public function page_users()             { include plugin_dir_path( __FILE__ ) . 'admin/users.php'; }
    public function page_organizations()     { include plugin_dir_path( __FILE__ ) . 'admin/organizations.php'; }
    public function page_agents()            { include plugin_dir_path( __FILE__ ) . 'admin/agents.php'; }
    public function page_settings()          { echo '<h1>Settings</h1>'; }

    /**
     * Shortcode handler: [queues_kb]
     */
    public function render_kb_shortcode( $atts ) {
        global $wpdb;
        $p         = $wpdb->prefix;
        $cat_table = "{$p}queues_kb_categories";
        $art_table = "{$p}queues_kb_articles";

        $cats = $wpdb->get_results( "SELECT id, name FROM {$cat_table} ORDER BY name ASC" );
        if ( ! $cats ) {
            return '<p>' . esc_html__( 'No knowledgebase content available.', 'queues' ) . '</p>';
        }

        ob_start();
        echo '<div class="queues-kb">';
        foreach ( $cats as $cat ) {
            echo '<h2>' . esc_html( $cat->name ) . '</h2>';
            $arts = $wpdb->get_results( $wpdb->prepare(
                "SELECT id, title, content 
                 FROM {$art_table} 
                 WHERE kb_category_id = %d 
                 ORDER BY id ASC",
                $cat->id
            ) );
            if ( $arts ) {
                echo '<ul class="queues-kb-articles">';
                foreach ( $arts as $art ) {
                    echo '<li>';
                    echo '<h3>' . esc_html( $art->title ) . '</h3>';
                    echo wpautop( wp_kses_post( $art->content ) );
                    echo '</li>';
                }
                echo '</ul>';
            } else {
                echo '<p>' . esc_html__( 'No articles in this category.', 'queues' ) . '</p>';
            }
        }
        echo '</div>';

        return ob_get_clean();
    }
}

// Initialize the plugin
Queues_Plugin::instance();
