<?php
/**
 * Class Queues_Installer
 * Handles plugin activation and database setup.
 */
class Queues_Installer {
    // Bump this when schema changes
    const DB_VERSION = '1.26';

    /**
     * Run on plugin activation: create/update all necessary tables.
     */
    public static function activate() {
        global $wpdb;
        $charset = $wpdb->get_charset_collate();
        $p       = $wpdb->prefix;

        $queries = [

            // 1. Queues (Categories)
            "CREATE TABLE IF NOT EXISTS `{$p}queues_categories` (
                `id`   BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                `name` VARCHAR(100)          NOT NULL,
                PRIMARY KEY (`id`)
            ) ENGINE=InnoDB {$charset}",

            // 2. Statuses
            "CREATE TABLE IF NOT EXISTS `{$p}queues_statuses` (
                `id`   BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                `name` VARCHAR(100)          NOT NULL,
                PRIMARY KEY (`id`)
            ) ENGINE=InnoDB {$charset}",

            // 3. Priorities
            "CREATE TABLE IF NOT EXISTS `{$p}queues_priorities` (
                `id`   BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                `name` VARCHAR(100)          NOT NULL,
                PRIMARY KEY (`id`)
            ) ENGINE=InnoDB {$charset}",

            // 4. Organizations
            "CREATE TABLE IF NOT EXISTS `{$p}queues_organizations` (
                `id`         BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                `name`       VARCHAR(255)            NOT NULL,
                `address`    TEXT                    NULL,
                `phone`      VARCHAR(50)             NULL,
                `manager_id` BIGINT(20) UNSIGNED     NULL,
                PRIMARY KEY (`id`)
            ) ENGINE=InnoDB {$charset}",

            // 5. Users
            "CREATE TABLE IF NOT EXISTS `{$p}queues_users` (
                `id`               BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                `wp_user_id`       BIGINT(20) UNSIGNED NOT NULL,
                `organization_id`  BIGINT(20) UNSIGNED NOT NULL,
                PRIMARY KEY (`id`),
                CONSTRAINT `fk_queues_user_org`
                  FOREIGN KEY (`organization_id`)
                  REFERENCES `{$p}queues_organizations` (`id`)
                  ON DELETE CASCADE
            ) ENGINE=InnoDB {$charset}",

            // 6. Agents
            "CREATE TABLE IF NOT EXISTS `{$p}queues_agents` (
                `id`         BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                `wp_user_id` BIGINT(20) UNSIGNED NOT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `unique_wp_user` (`wp_user_id`)
            ) ENGINE=InnoDB {$charset}",

            // 7. Agent–Queue relationship
            "CREATE TABLE IF NOT EXISTS `{$p}queues_agent_queues` (
                `agent_id` BIGINT(20) UNSIGNED NOT NULL,
                `queue_id` BIGINT(20) UNSIGNED NOT NULL,
                PRIMARY KEY (`agent_id`,`queue_id`),
                CONSTRAINT `fk_agentq_agent`
                  FOREIGN KEY (`agent_id`)
                  REFERENCES `{$p}queues_agents` (`id`)
                  ON DELETE CASCADE,
                CONSTRAINT `fk_agentq_queue`
                  FOREIGN KEY (`queue_id`)
                  REFERENCES `{$p}queues_categories` (`id`)
                  ON DELETE CASCADE
            ) ENGINE=InnoDB {$charset}",

            // 8. Canned Responses
            "CREATE TABLE IF NOT EXISTS `{$p}queues_canned` (
                `id`          BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                `name`        VARCHAR(100)         NOT NULL,
                `category_id` BIGINT(20) UNSIGNED   NULL,
                `response`    TEXT                 NOT NULL,
                PRIMARY KEY (`id`),
                CONSTRAINT `fk_queues_canned_cat`
                  FOREIGN KEY (`category_id`)
                  REFERENCES `{$p}queues_categories` (`id`)
                  ON DELETE CASCADE
            ) ENGINE=InnoDB {$charset}",

            // 9. Help Topics
            "CREATE TABLE IF NOT EXISTS `{$p}queues_help_topics` (
                `id` BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                `topic` VARCHAR(255) NOT NULL,
                `type` ENUM('incident','request') NOT NULL DEFAULT 'incident',
                PRIMARY KEY (`id`)
            ) ENGINE=InnoDB {$charset};",

            // 10. Custom Fields
            "CREATE TABLE IF NOT EXISTS `{$p}queues_fields` (
                `id`            BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                `field_label`   VARCHAR(255)        NOT NULL,
                `field_type`    VARCHAR(50)         NOT NULL DEFAULT 'text',
                `category_id`   BIGINT(20) UNSIGNED   NULL,
                `help_topic_id` BIGINT(20) UNSIGNED   NULL,
                PRIMARY KEY (`id`),
                CONSTRAINT `fk_queues_field_cat`
                  FOREIGN KEY (`category_id`)
                  REFERENCES `{$p}queues_categories` (`id`)
                  ON DELETE CASCADE,
                CONSTRAINT `fk_queues_field_help`
                  FOREIGN KEY (`help_topic_id`)
                  REFERENCES `{$p}queues_help_topics` (`id`)
                  ON DELETE CASCADE
            ) ENGINE=InnoDB {$charset}",

            // 11. Report Categories
            "CREATE TABLE IF NOT EXISTS `{$p}queues_report_categories` (
                `id`        BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                `name`      VARCHAR(150)         NOT NULL,
                `parent_id` BIGINT(20) UNSIGNED   NULL,
                `required`  TINYINT(1)           NOT NULL DEFAULT 0,
                PRIMARY KEY (`id`),
                CONSTRAINT `fk_queues_report_parent`
                  FOREIGN KEY (`parent_id`)
                  REFERENCES `{$p}queues_report_categories` (`id`)
                  ON DELETE SET NULL
            ) ENGINE=InnoDB {$charset}",

            // 12. KB Categories
            "CREATE TABLE IF NOT EXISTS `{$p}queues_kb_categories` (
                `id`   BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                `name` VARCHAR(150)            NOT NULL,
                PRIMARY KEY (`id`)
            ) ENGINE=InnoDB {$charset}",

            // 13. KB Articles
            "CREATE TABLE IF NOT EXISTS `{$p}queues_kb_articles` (
                `id`             BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                `kb_category_id` BIGINT(20) UNSIGNED NOT NULL,
                `title`          VARCHAR(255)            NOT NULL,
                `content`        TEXT                    NOT NULL,
                `created_at`     DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at`     DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                CONSTRAINT `fk_queues_kb_art_cat`
                  FOREIGN KEY (`kb_category_id`)
                  REFERENCES `{$p}queues_kb_categories` (`id`)
                  ON DELETE CASCADE
            ) ENGINE=InnoDB {$charset}",

            // 14. Tickets
            "CREATE TABLE IF NOT EXISTS `{$p}queues_tickets` (
                `id`           BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                `user_id`      BIGINT(20) UNSIGNED     NULL,
                `agent_id`     BIGINT(20) UNSIGNED     NULL,
                `priority_id`  BIGINT(20) UNSIGNED     NULL,
                `title`        VARCHAR(255)            NOT NULL,
                `content`      TEXT                    NOT NULL,
                `category_id`  BIGINT(20) UNSIGNED     NOT NULL,
                `status_id`    BIGINT(20) UNSIGNED     NOT NULL,
                `created_at`   DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at`   DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                CONSTRAINT `fk_queues_ticket_user`
                  FOREIGN KEY (`user_id`)
                  REFERENCES `{$p}queues_users` (`id`)
                  ON DELETE SET NULL,
                CONSTRAINT `fk_queues_ticket_agent`
                  FOREIGN KEY (`agent_id`)
                  REFERENCES `{$p}queues_agents` (`id`)
                  ON DELETE SET NULL,
                CONSTRAINT `fk_queues_ticket_priority`
                  FOREIGN KEY (`priority_id`)
                  REFERENCES `{$p}queues_priorities` (`id`)
                  ON DELETE SET NULL,
                CONSTRAINT `fk_queues_ticket_cat`
                  FOREIGN KEY (`category_id`)
                  REFERENCES `{$p}queues_categories` (`id`)
                  ON DELETE CASCADE,
                CONSTRAINT `fk_queues_ticket_status`
                  FOREIGN KEY (`status_id`)
                  REFERENCES `{$p}queues_statuses` (`id`)
                  ON DELETE CASCADE
            ) ENGINE=InnoDB {$charset}",

            // 15. Ticket Comments
            "CREATE TABLE IF NOT EXISTS `{$p}queues_ticket_comments` (
                `id`           BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                `ticket_id`    BIGINT(20) UNSIGNED NOT NULL,
                `user_id`      BIGINT(20) UNSIGNED NOT NULL,
                `comment_text` TEXT                    NOT NULL,
                `created_at`   DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                CONSTRAINT `fk_queues_tc_ticket`
                  FOREIGN KEY (`ticket_id`)
                  REFERENCES `{$p}queues_tickets` (`id`)
                  ON DELETE CASCADE,
                CONSTRAINT `fk_queues_tc_user`
                  FOREIGN KEY (`user_id`)
                  REFERENCES `{$p}queues_users` (`id`)
                  ON DELETE CASCADE
            ) ENGINE=InnoDB {$charset}",

            // 16. Ticket History
            "CREATE TABLE IF NOT EXISTS `{$p}queues_ticket_history` (
                `id`             BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                `ticket_id`      BIGINT(20) UNSIGNED NOT NULL,
                `field_changed`  VARCHAR(255)        NOT NULL,
                `old_value`      TEXT                NULL,
                `new_value`      TEXT                NULL,
                `changed_by`     BIGINT(20) UNSIGNED NOT NULL,
                `changed_at`     DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                CONSTRAINT `fk_queues_th_ticket`
                  FOREIGN KEY (`ticket_id`)
                  REFERENCES `{$p}queues_tickets` (`id`)
                  ON DELETE CASCADE,
                CONSTRAINT `fk_queues_th_agent`
                  FOREIGN KEY (`changed_by`)
                  REFERENCES `{$p}queues_agents` (`id`)
                  ON DELETE CASCADE
            ) ENGINE=InnoDB {$charset}",
        ];

        // Ensure dbDelta is available
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        foreach ( $queries as $sql ) {
            dbDelta( $sql );
        }

        // Add the "agent" role if it doesn't already exist
        if ( null === get_role( 'agent' ) ) {
            add_role(
                'agent',
                'Agent',
                [
                    'read'            => true,
                    'agent'           => true,
                    'edit_tickets'    => true,
                    'publish_tickets' => true,
                ]
            );
        }

        // Store/update the DB version
        add_option( 'queues_db_version', self::DB_VERSION );
    }
}
