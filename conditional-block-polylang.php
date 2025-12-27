<?php
/**
 * Plugin Name: Conditional Block Polylang
 * Description: A addon for Conditional Blocks plugin with Polylang integration.
 * Version: 1.0.0
 * Author: Dhakshina
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: conditional-block-polylang
 * Domain Path: /languages
 * Requires at least: 5.0
 * Requires PHP: 7.2
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('CBP_VERSION', '1.0.0');
define('CBP_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('CBP_PLUGIN_URL', plugin_dir_url(__FILE__));
define('CBP_PLUGIN_BASENAME', plugin_basename(__FILE__));

/**
 * Main plugin class
 */
class Conditional_Block_Polylang {
    
    /**
     * Instance of this class
     *
     * @var object
     */
    private static $instance = null;
    
    /**
     * Get instance of this class
     *
     * @return object
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        $this->init_hooks();
    }
    
    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // Activation hook
        register_activation_hook(__FILE__, array($this, 'activate'));
        
        // Deactivation hook
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
        
        // Initialize plugin
        add_action('plugins_loaded', array($this, 'init'));
    }
    
    /**
     * Plugin activation
     */
    public function activate() {
        // Check if Polylang is active
        if (!function_exists('pll_current_language')) {
            // Optionally show admin notice
            add_action('admin_notices', array($this, 'polylang_missing_notice'));
        }
        
        // Flush rewrite rules if needed
        flush_rewrite_rules();
    }
    
    /**
     * Plugin deactivation
     */
    public function deactivate() {
        // Clean up if needed
        flush_rewrite_rules();
    }
    
    /**
     * Admin notice if Polylang is not active
     */
    public function polylang_missing_notice() {
        ?>
        <div class="notice notice-warning is-dismissible">
            <p><?php esc_html_e('Conditional Block Polylang requires Polylang plugin to be installed and activated.', 'conditional-block-polylang'); ?></p>
        </div>
        <?php
    }
    
    /**
     * Initialize plugin
     */
    public function init() {
        // Load text domain for translations
        load_plugin_textdomain('conditional-block-polylang', false, dirname(CBP_PLUGIN_BASENAME) . '/languages');
        
        // Initialize plugin features
        $this->load_features();
    }
    
    /**
     * Load plugin features
     */
    private function load_features() {
        // Check if both Polylang and Conditional Blocks are active
        if (function_exists('pll_current_language') && $this->is_conditional_blocks_active()) {
            // Register Conditional Blocks hooks
            add_filter('conditional_blocks_register_condition_categories', array($this, 'register_polylang_condition_category'), 10, 1);
            add_filter('conditional_blocks_register_condition_types', array($this, 'register_polylang_language_condition'), 10, 1);
            add_filter('conditional_blocks_register_check_polylang_language', array($this, 'check_polylang_language_condition'), 10, 2);
        } elseif (!function_exists('pll_current_language')) {
            add_action('admin_notices', array($this, 'polylang_missing_notice'));
        } elseif (!$this->is_conditional_blocks_active()) {
            add_action('admin_notices', array($this, 'conditional_blocks_missing_notice'));
        }
    }
    
    /**
     * Check if Conditional Blocks plugin is active
     *
     * @return bool
     */
    private function is_conditional_blocks_active() {
        if ( defined( 'CONDITIONAL_BLOCKS_VERSION' ) && version_compare( '3.0.0', CONDITIONAL_BLOCKS_VERSION, '<=' ) ) {
            return true;
        }   
        return false;
    }
    
    /**
     * Admin notice if Conditional Blocks is not active
     */
    public function conditional_blocks_missing_notice() {
        ?>
        <div class="notice notice-warning is-dismissible">
            <p><?php esc_html_e('Conditional Block Polylang requires Conditional Blocks plugin to be installed and activated.', 'conditional-block-polylang'); ?></p>
        </div>
        <?php
    }
    
    /**
     * Register Polylang condition category
     *
     * @param array $categories Existing condition categories
     * @return array Modified categories
     */
    public function register_polylang_condition_category($categories) {
        $categories[] = array(
            'value' => 'polylang',
            'label' => __('Polylang', 'conditional-block-polylang'),
        );

        return $categories;
    }
    
    /**
     * Register Polylang language condition with operator
     *
     * @param array $conditions Existing conditions
     * @return array Modified conditions
     */
    public function register_polylang_language_condition($conditions) {
        if (!function_exists('pll_languages_list')) {
            return $conditions;
        }

        // Build language options
        $options = array();
        $languages = pll_languages_list(array('fields' => array()));

        foreach ($languages as $lang) {
            $options[] = array(
                'value' => $lang->slug, // en, de, fr
                'label' => $lang->name,
            );
        }

        $conditions[] = array(
            'type'        => 'polylang_language',
            'label'       => __('Language (Polylang)', 'conditional-block-polylang'),
            'description' => __('Control block visibility based on the current language.', 'conditional-block-polylang'),
            'category'    => 'polylang',
            'fields'      => array(
                array(
                    'key'  => 'operator',
                    'type' => 'radio',
                    'attributes' => array(
                        'label' => __('Condition', 'conditional-block-polylang'),
                        'value' => 'equals',
                    ),
                    'options' => array(
                        array(
                            'value' => 'equals',
                            'label' => __('Equals', 'conditional-block-polylang'),
                        ),
                        array(
                            'value' => 'not_equals',
                            'label' => __('Not equals', 'conditional-block-polylang'),
                        ),
                    ),
                ),
                array(
                    'key'  => 'languages',
                    'type' => 'select',
                    'attributes' => array(
                        'label'       => __('Languages', 'conditional-block-polylang'),
                        'help'        => __('Select one or more languages.', 'conditional-block-polylang'),
                        'multiple'    => true,
                        'placeholder' => __('Select language(s)', 'conditional-block-polylang'),
                    ),
                    'options' => $options,
                ),
                array(
                    'key'  => 'blockAction',
                    'type' => 'blockAction',
                ),
            ),
        );

        return $conditions;
    }
    
    /**
     * Condition check for Polylang language with equals / not equals
     *
     * @param bool  $should_block_render Whether the block should render
     * @param array $condition Condition configuration
     * @return bool Whether the block should render
     */
    public function check_polylang_language_condition($should_block_render, $condition) {
        if (!function_exists('pll_current_language')) {
            return $should_block_render;
        }

        $current_lang = pll_current_language('slug');

        // Normalize languages
        $languages_raw = $condition['languages'] ?? array();
        $languages = array();

        foreach ((array) $languages_raw as $lang) {
            if (is_array($lang) && isset($lang['value'])) {
                $languages[] = $lang['value'];
            } elseif (is_string($lang)) {
                $languages[] = $lang;
            }
        }

        // Defaults
        $operator     = $condition['operator'] ?? 'equals';
        $block_action = $condition['blockAction'] ?? 'showBlock';

        // Safety fallback
        if (empty($languages)) {
            return $block_action === 'showBlock';
        }

        $is_in_list = in_array($current_lang, $languages, true);

        $has_match = ($operator === 'equals')
            ? $is_in_list
            : !$is_in_list;

        if (
            ($has_match && $block_action === 'showBlock') ||
            (!$has_match && $block_action === 'hideBlock')
        ) {
            return true;
        }

        return false;
    }
}

/**
 * Initialize the plugin
 */
function cbp_init() {
    return Conditional_Block_Polylang::get_instance();
}

// Start the plugin
cbp_init();

