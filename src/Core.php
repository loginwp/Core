<?php

namespace LoginWP\Core;

global $wpdb;

use LoginWP\Core\Redirections\Redirections;

define('PTR_LOGINWP_DB_TABLE', $wpdb->prefix . 'login_redirects');
define('PTR_LOGINWP_ADMIN_PAGE_SLUG', 'loginwp-settings');
define('PTR_LOGINWP_ADMIN_PAGE_URL', admin_url('admin.php?page=' . PTR_LOGINWP_ADMIN_PAGE_SLUG));

define('PTR_LOGINWP_URL', plugin_dir_url(PTR_LOGINWP_SYSTEM_FILE_PATH));
define('PTR_LOGINWP_ASSETS_DIR', wp_normalize_path(dirname(PTR_LOGINWP_SYSTEM_FILE_PATH) . '/assets/'));

if (strpos(__FILE__, 'peters-login-redirect/' . DIRECTORY_SEPARATOR . 'src') !== false) {
    // production url path to assets folder.
    define('PTR_LOGINWP_ASSETS_URL', PTR_LOGINWP_URL . 'src/core/assets/');
} else {
    // dev url path to assets folder.
    define('PTR_LOGINWP_ASSETS_URL', PTR_LOGINWP_URL . '../' . dirname(dirname(substr(__FILE__, strpos(__FILE__, 'peters-login-redirect')))) . '/assets/');
}

class Core
{
    public function __construct()
    {
        register_activation_hook(PTR_LOGINWP_SYSTEM_FILE_PATH, [__CLASS__, 'rul_activate_plugin']);
        register_uninstall_hook(PTR_LOGINWP_SYSTEM_FILE_PATH, [__CLASS__, 'rul_uninstall_plugin']);
        add_filter('wpmu_drop_tables', [$this, 'rul_drop_tables']);
        add_action('activate_blog', [$this, 'rul_site_added']);

        // Wpmu_new_blog has been deprecated in 5.1 and replaced by wp_insert_site.
        global $wp_version;
        if (version_compare($wp_version, '5.1', '<')) {
            add_action('wpmu_new_blog', [$this, 'rul_site_added']);
        } else {
            add_action('wp_initialize_site', [$this, 'rul_site_added'], 99);
        }

        add_action('admin_init', [$this, 'rul_upgrade']);

        Redirections::get_instance();
        Admin\RedirectionsSettingsPage::get_instance();
    }

    public static function rul_install()
    {
        global $wpdb;

        $rul_db_addresses = PTR_LOGINWP_DB_TABLE;

        // Add the table to hold group information and moderator rules
        if ($rul_db_addresses != $wpdb->get_var("SHOW TABLES LIKE '$rul_db_addresses'")) {
            $sql = "CREATE TABLE $rul_db_addresses (
            `rul_type` enum('user','role','level','all','register') NOT NULL,
            `rul_value` varchar(191) NULL default NULL,
            `rul_url` LONGTEXT NULL default NULL,
            `rul_url_logout` LONGTEXT NULL default NULL,
            `rul_order` int(2) NOT NULL default '0',
            UNIQUE KEY `rul_type` (`rul_type`,`rul_value`)
            )";

            $wpdb->query($sql);

            // Insert the "all" redirect entry
            $wpdb->insert($rul_db_addresses,
                array('rul_type' => 'all')
            );

            // Insert the "on-register" redirect entry
            $wpdb->insert($rul_db_addresses,
                array('rul_type' => 'register')
            );

            // Set the version number in the database
            add_option('rul_version', PTR_LOGINWP_VERSION_NUMBER, '', 'no');
        }

        self::rul_upgrade();
    }

    public static function rul_uninstall()
    {
        global $wpdb;

        // Remove the table we created
        if (PTR_LOGINWP_DB_TABLE == $wpdb->get_var('SHOW TABLES LIKE \'' . PTR_LOGINWP_DB_TABLE . '\'')) {
            $sql = 'DROP TABLE ' . PTR_LOGINWP_DB_TABLE;
            $wpdb->query($sql);
        }

        delete_option('rul_version');
        delete_option('rul_settings');
    }

    public static function rul_activate_plugin($networkwide)
    {
        // Executes when plugin is activated
        global $wpdb;

        if (function_exists('is_multisite') && is_multisite() && $networkwide) {
            $blogs = $wpdb->get_col("SELECT blog_id FROM $wpdb->blogs");
            foreach ($blogs as $blog) {
                switch_to_blog($blog);
                self::rul_install();
                restore_current_blog();
            }
        } else {
            self::rul_install();
        }
    }

    public static function rul_uninstall_plugin()
    {
        // Executes when plugin is deleted
        global $wpdb;
        if (function_exists('is_multisite') && is_multisite()) {
            $blogs = $wpdb->get_col("SELECT blog_id FROM $wpdb->blogs");
            foreach ($blogs as $blog) {
                switch_to_blog($blog);
                self::rul_uninstall();
                restore_current_blog();
            }
        } else {
            self::rul_uninstall();
        }
    }

    public function rul_site_added($blog)
    {
        if ( ! is_int($blog)) {
            $blog = $blog->id;
        }

        switch_to_blog($blog);
        self::rul_install();
        restore_current_blog();
    }

    public function rul_drop_tables($tables)
    {
        $tables[] = PTR_LOGINWP_DB_TABLE;

        return $tables;
    }

    // Perform upgrade functions
    // Some newer operations are duplicated from rul_install() as there's no guarantee that the user will follow a specific upgrade procedure
    public static function rul_upgrade()
    {
        global $wpdb;

        $rul_db_addresses = PTR_LOGINWP_DB_TABLE;

        // Turn version into an integer for comparisons
        $current_version = intval(str_replace('.', '', get_option('rul_version')));

        if ($current_version < 220) {
            $wpdb->query("ALTER TABLE `$rul_db_addresses` ADD `rul_url_logout` LONGTEXT NOT NULL default '' AFTER `rul_url`");
        }

        if ($current_version < 250) {

            $wpdb->query("ALTER TABLE `$rul_db_addresses` CHANGE `rul_type` `rul_type` ENUM( 'user', 'role', 'level', 'all', 'register' ) NOT NULL");
            $wpdb->insert($rul_db_addresses,
                array('rul_type' => 'register')
            );
        }

        if ($current_version < 253) {
            // Allow NULL values for non-essential fields
            $wpdb->query("ALTER TABLE `$rul_db_addresses` CHANGE `rul_value` `rul_value` varchar(255) NULL default NULL");
            $wpdb->query("ALTER TABLE `$rul_db_addresses` CHANGE `rul_url` `rul_url` LONGTEXT NULL default NULL");
            $wpdb->query("ALTER TABLE `$rul_db_addresses` CHANGE `rul_url_logout` `rul_url_logout` LONGTEXT NULL default NULL");
        }

        if ($current_version < 291) {
            // Reduce size of rul_value field to support utf8mb4 character encoding
            $wpdb->query("ALTER TABLE `$rul_db_addresses` CHANGE `rul_value` `rul_value` varchar(191) NULL default NULL");
        }

        if ($current_version < 300) {
            $wpdb->query("ALTER TABLE $rul_db_addresses ADD id BIGINT NOT NULL PRIMARY KEY AUTO_INCREMENT FIRST");
        }

        if ($current_version != intval(str_replace('.', '', PTR_LOGINWP_VERSION_NUMBER))) {
            // Add the version number to the database
            delete_option('rul_version');
            add_option('rul_version', PTR_LOGINWP_VERSION_NUMBER, '', 'no');
        }
    }

    public static function get_instance()
    {
        static $instance = null;

        if (is_null($instance)) {
            $instance = new self();
        }

        return $instance;
    }
}