<?php

namespace LoginWP\Core\Admin;

use LoginWP\Core\Helpers;

class RedirectionsSettingsPage extends AbstractSettingsPage
{
    /**
     * @var RedirectWPList
     */
    protected $wplist_instance;

    public function __construct()
    {
        ProfilePress::get_instance();

        parent::__construct();

        add_action('admin_init', [$this, 'save_redirect_rule_changes']);
        add_action('admin_init', [$this, 'save_other_settings_changes']);
    }

    public static function get_rule_conditions()
    {
        return apply_filters('rul_rule_conditions', [
            [
                'id'            => 'user',
                'label'         => esc_html__('Usernames', 'peters-login-redirect'),
                'options'       => Helpers::username_list(),
                'order_support' => false
            ],
            [
                'id'            => 'role',
                'label'         => esc_html__('User Roles', 'peters-login-redirect'),
                'options'       => Helpers::user_role_list(),
                'order_support' => true
            ],
            [
                'id'            => 'level',
                'label'         => esc_html__('User Capabilities', 'peters-login-redirect'),
                'options'       => Helpers::capability_list(),
                'order_support' => true
            ]
        ]);
    }

    public function register_settings_page()
    {
        $hook = add_submenu_page(
            PTR_LOGINWP_ADMIN_PAGE_SLUG,
            __('Redirections - LoginWP', 'peters-login-redirect'),
            __('Redirections', 'peters-login-redirect'),
            'manage_options',
            PTR_LOGINWP_ADMIN_PAGE_SLUG,
            [$this, 'admin_page_callback']
        );

        do_action('loginwp_admin_hooks');

        add_action("load-$hook", array($this, 'screen_option'));
    }

    public function header_menu_tabs($tabs)
    {
        $tabs['rules'] = esc_html__('Rules', 'peters-login-redirect');

        return $tabs;
    }

    /**
     * Screen options
     */
    public function screen_option()
    {
        if (isset($_GET['new']) || isset($_GET['action'])) {
            add_filter('screen_options_show_screen', '__return_false');
        }

        $option = 'per_page';

        $args = array(
            'label'   => __('Rules', 'peters-login-redirect'),
            'default' => 10,
            'option'  => 'redirections_per_page',
        );

        if ( ! isset($_GET['new'], $_GET['action'])) {
            add_screen_option($option, $args);
        }

        $this->wplist_instance = RedirectWPList::get_instance();
    }

    public function add_new_button()
    {
        $url   = add_query_arg('new', '1', PTR_LOGINWP_ADMIN_PAGE_URL);
        $label = __('Add New Rule', 'peters-login-redirect');

        if (isset($_GET['new']) || isset($_GET['action'])) {
            $url   = PTR_LOGINWP_ADMIN_PAGE_URL;
            $label = __('Go Back', 'peters-login-redirect');
        }

        printf('<a class="add-new-h2" style=:margin-left:15px;" href="%s">%s</a>', esc_url($url), $label);
    }

    public function admin_page_callback()
    {
        add_action('wp_cspa_before_closing_header', [$this, 'add_new_button']);
        add_action('wp_cspa_main_content_area', array($this, 'wp_list_table'), 10, 2);

        $instance = SettingsPageApi::instance();
        $instance->option_name('loginwp_settings');
        $instance->page_header(__('Redirections', 'peters-login-redirect'));
        $this->settings_page_header();
        echo '<div class="loginwp-data-listing">';
        $instance->build(true);
        echo '</div>';
    }

    public function bottom_admin_page_settings()
    {
        require dirname(__FILE__) . '/views/view.bottom-settings.php';
    }

    /**
     * Callback to output content of OptinCampaign_List table.
     *
     * @param string $content
     * @param string $option_name settings Custom_Settings_Page_Api option name.
     *
     * @return string|void
     */
    public function wp_list_table($content, $option_name)
    {
        if ($option_name != 'loginwp_settings') {
            return $content;
        }

        add_thickbox();

        if ((isset($_GET['new']) && $_GET['new'] == '1') || (isset($_GET['action']) && $_GET['action'] == 'edit')) {
            add_action('admin_footer', [$this, 'js_template']);
            require_once dirname(__FILE__) . '/views/include.view.php';
            self::available_placeholders_structure();

            return;
        }

        ob_start();

        $this->wplist_instance->prepare_items();

        $this->wplist_instance->display();

        $this->bottom_admin_page_settings();

        self::available_placeholders_structure();

        return ob_get_clean();
    }

    public static function condition_value_dropdown($condition_id, $db_condition_value = '')
    {
        $condition = wp_list_filter(self::get_rule_conditions(), ['id' => $condition_id]);

        if ( ! empty($condition)) : $condition = array_values($condition)[0]; ?>
            <label>
                <select name="rul_condition_value">
                    <option value=""><?php esc_html_e('Select...', 'peters-login-redirect'); ?></option>
                    <?php foreach ($condition['options'] as $key => $label) : ?>
                        <option value="<?= esc_attr($key) ?>" <?php selected($key, $db_condition_value) ?>><?= $label ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
        <?php
        endif;
    }

    public static function order_support_conditions()
    {
        $order_support_conditions = wp_list_filter(self::get_rule_conditions(), ['order_support' => true]);

        if ( ! empty($order_support_conditions)) {
            $order_support_conditions = array_reduce($order_support_conditions, function ($carry, $item) {
                $carry[] = $item['id'];

                return $carry;
            });
        }

        return $order_support_conditions;
    }

    public function js_template()
    {
        foreach (self::get_rule_conditions() as $condition) : ?>
            <script type="text/html" id="tmpl-loginwp-condition-<?php echo esc_attr($condition['id']) ?>">
                <?php self::condition_value_dropdown($condition['id']); ?>
            </script>
        <?php endforeach; ?>

        <script>
            var rul_conditions_order_support = <?php echo json_encode(self::order_support_conditions()) ?>;
        </script>
        <?php
    }

    public function save_redirect_rule_changes()
    {
        if (isset($_GET['page'], $_GET['saved']) && PTR_LOGINWP_ADMIN_PAGE_SLUG == $_GET['page']) {

            $message = esc_html__('Redirect rule saved successfully', 'peters-login-redirect');

            if ('settings' == $_GET['saved']) {
                $message = esc_html__('Changes saved', 'peters-login-redirect');
            }

            $this->trigger_admin_notices($message, 'success');
        }

        if (isset($_GET['page'], $_GET['deleted']) && PTR_LOGINWP_ADMIN_PAGE_SLUG == $_GET['page']) {

            $this->trigger_admin_notices(
                esc_html__('Redirect rule deleted', 'peters-login-redirect'),
                'success');
        }

        if (empty($_POST['loginwp_save_rule'])) return;

        if ( ! $this->security_check('loginwp_save_rule', 'rul-loginwp-nonce')) return;

        global $wpdb;

        $type      = sanitize_text_field($_POST['rul_condition']);
        $typeValue = sanitize_text_field($_POST['rul_condition_value']);
        $order     = absint($_POST['rul_order']);

        $error_message = '';

        if (empty($_POST['rul_login_url']) && empty($_POST['rul_logout_url'])) {
            $error_message = __('ERROR: No Login or Logout URL specified', 'peters-login-redirect');
        }

        if ($type == 'user' && ! username_exists($typeValue)) {
            $error_message = __('ERROR: Non-existent username submitted', 'peters-login-redirect');
        }

        if ($type == 'role' && ! in_array($typeValue, array_keys(Helpers::user_role_list()))) {
            $error_message = __('ERROR: Non-existent role submitted', 'peters-login-redirect');
        }

        if ($type == 'level' && ! in_array($typeValue, Helpers::capability_list())) {
            $error_message = __('ERROR: Non-existent level submitted', 'peters-login-redirect');
        }

        if ( ! empty($error_message)) {

            $this->trigger_admin_notices($error_message);

            return;
        }

        if ($order > 99) $order = 0;

        if ( ! empty($_GET['id'])) {

            $result = $wpdb->update(
                PTR_LOGINWP_DB_TABLE,
                [
                    'rul_type'       => $type,
                    'rul_value'      => $typeValue,
                    'rul_order'      => $order,
                    'rul_url'        => sanitize_text_field($_POST['rul_login_url']),
                    'rul_url_logout' => sanitize_text_field($_POST['rul_logout_url'])
                ],
                [
                    'id' => absint($_GET['id'])
                ],
                ['%s', '%s', '%d', '%s', '%s'],
                ['%d']
            );

            if (false === $result) {

                $this->trigger_admin_notices(
                    esc_html__('ERROR: Unknown error editing redirect rule', 'peters-login-redirect')
                );

                return;
            }
        }

        if ( ! isset($_GET['id'])) {

            $result = $wpdb->insert(
                PTR_LOGINWP_DB_TABLE,
                [
                    'rul_url'        => esc_url_raw($_POST['rul_login_url']),
                    'rul_url_logout' => esc_url_raw($_POST['rul_logout_url']),
                    'rul_type'       => $type,
                    'rul_value'      => $typeValue,
                    'rul_order'      => $order
                ],
                ['%s', '%s', '%s', '%s', '%d']
            );

            if (false === $result) {

                $this->trigger_admin_notices(
                    __('ERROR: Unknown error when adding the redirect rule', 'peters-login-redirect')
                );
            }

            wp_safe_redirect(add_query_arg('saved', 'true', RedirectWPList::edit_rule_url($wpdb->insert_id)));
            exit;
        }

        wp_safe_redirect(esc_url_raw(add_query_arg('saved', 'true')));
        exit;
    }

    public function save_other_settings_changes()
    {
        if ( ! empty($_POST['rul_allupdatesubmit'])) {

            if ( ! $this->security_check('rul_allupdatesubmit')) return;

            global $wpdb;

            $address        = esc_url_raw($_POST['rul_all']);
            $address_logout = esc_url_raw($_POST['rul_all_logout']);

            $update = $wpdb->update(
                PTR_LOGINWP_DB_TABLE,
                ['rul_url' => $address, 'rul_url_logout' => $address_logout],
                ['rul_type' => 'all']
            );

            if ($update === false) {
                $this->trigger_admin_notices(
                    __('ERROR: Unknown problem updating URL', 'peters-login-redirect')
                );
            }

            wp_safe_redirect(esc_url_raw(add_query_arg('saved', 'true')));
            exit;
        }

        if ( ! empty($_POST['rul_registerupdatesubmit'])) {

            if ( ! $this->security_check('rul_registerupdatesubmit')) return;

            global $wpdb;

            $update = $wpdb->update(
                PTR_LOGINWP_DB_TABLE,
                ['rul_url' => esc_url_raw($_POST['rul_register'])],
                ['rul_type' => 'register']
            );

            if ($update === false) {

                $this->trigger_admin_notices(
                    __('ERROR: Unknown problem updating URL', 'peters-login-redirect')
                );
            }

            wp_safe_redirect(esc_url_raw(add_query_arg('saved', 'true')));
            exit;
        }

        if ( ! empty($_POST['rul_settingssubmit'])) {

            if ( ! $this->security_check('rul_settingssubmit')) return;

            $rul_settings = Helpers::redirectFunctionCollection_get_settings();

            foreach ($rul_settings as $key => $value) {
                if (isset($_POST[$key])) {
                    $rul_settings[$key] = sanitize_text_field($_POST[$key]);
                }
            }

            update_option('rul_settings', $rul_settings);

            wp_safe_redirect(esc_url_raw(add_query_arg('saved', 'settings')));
            exit;
        }
    }

    public function trigger_admin_notices($message, $type = 'error')
    {
        $class = "notice-$type";
        add_action('admin_notices', function () use ($message, $class) {
            printf('<div class="notice %2$s is-dismissible"><p>%1$s</p></div>', $message, $class);
        });
    }

    public function security_check($nonce_action, $query_arg = 'rul-security')
    {
        if ( ! current_user_can('manage_options')) return false;

        check_admin_referer($nonce_action, $query_arg);

        return true;
    }

    public static function available_placeholders_structure()
    {
        $available_placeholders = [
            'username'     => esc_html__('Username of user', 'peters-login-redirect'),
            'user_slug'    => esc_html__('Author URL slug or user nicename', 'peters-login-redirect'),
            'website_url'  => esc_html__('Website URL', 'peters-login-redirect'),
            'http_referer' => esc_html__('HTTP referer URL', 'peters-login-redirect')
        ];
        ?>
        <div id="loginwp-view-placeholders" style="display:none;">
            <div class="loginwp-view-placeholders-wrap">
                <?php foreach ($available_placeholders as $placeholder => $description) : ?>
                    <div class="loginwp-placeholder">
                        <strong>{{<?= esc_html($placeholder) ?>}}:</strong> <?= esc_html($description) ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php
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