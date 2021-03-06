<?php
/**
 * @package TSF_Extension_Manager\Classes
 */
namespace TSF_Extension_Manager;

defined( 'ABSPATH' ) or die;

/**
 * The SEO Framework - Extension Manager plugin
 * Copyright (C) 2017-2018 Sybre Waaijer, CyberWire (https://cyberwire.nl/)
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License version 3 as published
 * by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */

/**
 * Sets up class loader as file is loaded.
 * This is done asynchronously, because static calls are handled prior and after.
 * @see EOF. Because of the autoloader and trait calling, we can't do it before the class is read.
 * @link https://bugs.php.net/bug.php?id=75771
 */
$_load_inpostgui_class = function() {
	new InpostGUI();
};

/**
 * Registers and outputs inpost GUI elements. Auto-invokes everything the moment
 * this file is required.
 *
 * The SEO Framework 2.9.0 or later is required. All earlier versions will let this
 * remain dormant.
 *
 * @since 1.5.0
 * @requires TSF 2.9.0||^
 * @access private
 * @uses trait TSF_Extension_Manager\Enclose_Core_Final
 * @uses trait TSF_Extension_Manager\Construct_Master_Once_Final_Interface
 *       This means you shouldn't invoke new yourself.
 * @see package TSF_Extension_Manager\Traits\Overload
 *
 * @final Can't be extended.
 */
final class InpostGUI {
	use Enclose_Core_Final,
		Construct_Master_Once_Final_Interface;

	/**
	 * @since 1.5.0
	 * @param string NONCE_ACTION The nonce action.
	 * @param string NONCE_NAME   The nonce name.
	 */
	const NONCE_ACTION = 'tsfem-e-save-inpost-nonce';
	const NONCE_NAME = 'tsfem-e-inpost-settings';

	/**
	 * @since 1.5.0
	 * @param string META_PREFIX The meta prefix to be stored in the database.
	 */
	const META_PREFIX = 'tsfem-pm';

	/**
	 * @since 1.5.0
	 * @see static::_verify_nonce()
	 * @param string $save_access_state The state the save is in.
	 */
	public static $save_access_state = 0;

	/**
	 * @since 1.5.0
	 * @param string $include_secret The inclusion secret generated on tab load.
	 */
	private static $include_secret;

	/**
	 * @since 1.5.0
	 * @param array $tabs The registered tabs.
	 * @param array $active_tab_keys The activate tab keys of static::$tabs
	 * @param array $views The registered view files for the tabs.
	 */
	private static $tabs = [];
	private static $active_tab_keys = [];
	private static $views = [];

	/**
	 * @since 1.5.0
	 * @param array $tabs The registered scripts.
	 * @param array $templates The registered templates.
	 * @param array $colors The registered colors.
	 * @param array $color_deps The registered color dependencies.
	 */
	private static $scripts = [];
	private static $templates = [];
	private static $colors = [];
	private static $color_dep_last;

	/**
	 * Prepares the class and loads constructor.
	 *
	 * Use this if the actions need to be registered early, but nothing else of
	 * this class is needed yet.
	 *
	 * @since 1.5.0
	 */
	public static function prepare() {}

	/**
	 * Constructor. Loads all appropriate actions asynchronously.
	 */
	private function construct() {

		$this->register_tabs();

		//= Scripts.
		\add_action( 'admin_enqueue_scripts', [ $this, '_prepare_admin_scripts' ], 1 );
		\add_action( 'admin_footer', [ $this, '_output_templates' ] );

		\add_filter( 'the_seo_framework_admin_color_css', [ $this, '_add_colors' ], 10, 2 );
		\add_action( 'admin_print_styles', [ $this, '_shift_color_deps' ] );

		//= Saving.
		\add_action( 'the_seo_framework_pre_page_inpost_box', [ $this, '_output_nonce' ], 9 );
		\add_action( 'save_post', static::class . '::_verify_nonce', 1, 2 );

		//= Output.
		\add_filter( 'the_seo_framework_inpost_settings_tabs', [ $this, '_load_tabs' ], 10, 2 );
	}

	/**
	 * Registers available tabs.
	 *
	 * Any more than 6 tabs will cause GUI incompatibilities. Therefore, it's
	 * recommended to only use these assigned tabs.
	 *
	 * @since 1.5.0
	 * @uses static::$tabs The registered tabs that are written.
	 */
	private function register_tabs() {
		static::$tabs = [
			'structure' => [
				'name' => \__( 'Structure', 'the-seo-framework-extension-manager' ),
				'callback' => [ $this, '_output_tab_content' ],
				'dashicon' => 'layout',
				'args' => [ 'structure' ],
			],
			'audit' => [
				'name' => \__( 'Audit', 'the-seo-framework-extension-manager' ),
				'callback' => [ $this, '_output_tab_content' ],
				'dashicon' => 'analytics',
				'args' => [ 'audit' ],
			],
			'advanced' => [
				'name' => \__( 'Advanced', 'the-seo-framework-extension-manager' ),
				'callback' => [ $this, '_output_tab_content' ],
				'dashicon' => 'list-view',
				'args' => [ 'advanced' ],
			],
		];
	}

	/**
	 * Prepares scripts for output on post edit screens.
	 *
	 * @since 1.5.0
	 *
	 * @param string $hook The current admin hook.
	 */
	public function _prepare_admin_scripts( $hook ) {

		if ( ! in_array( $hook, [ 'post.php', 'post-new.php' ], true ) )
			return;

		/**
		 * Does action 'tsfem_inpostgui_enqueue_scripts'
		 *
		 * Use this hook to enqueue scripts on the post edit screens.
		 *
		 * @since 1.5.0
		 * @param string $class The static class caller name.
		 * @param string $hook  The current page hook.
		 */
		\do_action_ref_array( 'tsfem_inpostgui_enqueue_scripts', [ static::class, $hook ] );

		$this->enqueue_scripts();
	}

	/**
	 * Registers script to be enqueued.
	 *
	 * @since 1.5.0
	 * @uses static::$scripts
	 * @see $this->enqueue_scripts()
	 *
	 * @param array $script The script : {
	 *   'type' => string 'css|js',
	 *   'name' => string The unique script name, which is also the file name,
	 *   'deps' => array  Dependencies,
	 *   'ver'  => string Script version,
	 *   'l10n' => array : {
	 *      'name' => string The JavaScript variable,
	 *      'data' => mixed  The l10n properties,
	 *   }
	 *   'tmpl' => array Either multidimensional or single : {
	 *      'file' => string $file. The full file location,
	 *      'args' => array $args. Optional,
	 *    }
	 * }
	 */
	public static function register_script( array $script ) {
		static::$scripts[] = $script;
	}

	/**
	 * Enqueues scripts, l10n and templates.
	 *
	 * @since 1.5.0
	 * @uses static::$scripts
	 * @uses $this->generate_file_url()
	 * @uses $this->register_template()
	 */
	private function enqueue_scripts() {

		//= Register them first to accomodate for dependencies.
		foreach ( static::$scripts as $s ) {
			switch ( $s['type'] ) {
				case 'css' :
					\wp_register_style( $s['name'], $this->generate_file_url( $s, 'css' ), $s['deps'], $s['ver'], 'all' );
					isset( $s['colors'] )
						and $this->register_colors( $s['name'], $s['colors'] );
					break;
				case 'js' :
					\wp_register_script( $s['name'], $this->generate_file_url( $s, 'js' ), $s['deps'], $s['ver'], true );
					isset( $s['l10n'] )
						and \wp_localize_script( $s['name'], $s['l10n']['name'], $s['l10n']['data'] );
					isset( $s['tmpl'] )
						and $this->register_template( $s['tmpl'] );
					break;
			}
		}

		foreach ( static::$scripts as $s ) {
			switch ( $s['type'] ) {
				case 'css' :
					\wp_enqueue_style( $s['name'] );
					break;
				case 'js' :
					\wp_enqueue_script( $s['name'] );
					break;
			}
		}
	}

	/**
	 * Registers colors for dynamic CSS based on admin color schemes.
	 *
	 * Using the following strings will replace them with the scheme colors :
	 * - {{$bg}}
	 * - {{$bg_accent}}
	 * - {{$color}}
	 * - {{$color_accent}}
	 *
	 * @since 1.5.0
	 * @uses static::$color_dep_last
	 * @uses static::$colors
	 * @todo add support for alt colors?
	 * @see $this->_add_colors();
	 * @NOTE Input must be escaped!
	 *
	 * @param string $name The script name handle.
	 * @param array $colors, multi-dimensional : {
	 *   string querySelector => incremental array : {
	 *      string css
	 *   }
	 * }
	 */
	private function register_colors( $name, array $colors ) {
		static::$color_dep_last = $name;
		static::$colors = array_merge( static::$colors, $colors );
	}

	/**
	 * Adds colors for CSS output.
	 *
	 * @since 1.5.0
	 * @uses filter `the_seo_framework_admin_color_css`
	 *       @since TSF 3.0.0
	 *
	 * @param array $css The current CSS adjustments.
	 * @param array $scheme The current color scheme.
	 * @return array $css
	 */
	public function _add_colors( $css, $scheme ) {

		if ( empty( static::$colors ) )
			return $css;

		//= Index access is handled in `the_seo_framework()->get_admin_color_css()`
		$_scheme = $GLOBALS['_wp_admin_css_colors'][ $scheme ]->colors;

		$bg = $_scheme[0];
		$bg_accent = $_scheme[1];
		$color = $_scheme[2];
		$color_accent = $_scheme[3];

		$table = [
			'{{$bg}}' => $bg,
			'{{$bg_accent}}' => $bg_accent,
			'{{$color}}' => $color,
			'{{$color_accent}}' => $color_accent,
		];

		foreach ( static::$colors as $_selector => $_css ) {
			$css[ $_selector ] = str_replace(
				array_keys( $table ),
				array_values( $table ),
				$_css
			);
		}

		return $css;
	}

	/**
	 * Shifts inline styles from The SEO Framework after the last dependency.
	 *
	 * @since 1.5.0
	 * @TODO this is hacky. It's best that each style has its own color handler.
	 */
	public function _shift_color_deps() {

		if ( ! static::$color_dep_last )
			return;

		$wp_styles = \wp_styles();
		$tsf_key = \the_seo_framework()->css_name;
		$after = $wp_styles->get_data( $tsf_key, 'after' );

		if ( ! $after )
			return;

		$wp_styles->add_data( static::$color_dep_last, 'after', $after );

		//! The hack part.
		//* Clean up old vars. Not necessary because duplicated styles are OK.
		isset( $wp_styles->registered['tsf']->extra )
			and $wp_styles->registered['tsf']->extra = [];
	}

	/**
	 * Registers template for output in the admin footer.
	 *
	 * Set a multidimensional array to register multiple views.
	 *
	 * @since 1.5.0
	 *
	 * @param array $templates, single or multi-dimensional : {
	 *   'file' => string $file. The full file location,
	 *   'args' => array $args. Optional,
	 * }
	 */
	private function register_template( array $templates ) {
		//= Wrap template if it's only one on the base.
		if ( isset( $templates['file'] ) )
			$templates = [ $templates ];

		foreach ( $templates as $t )
			static::$templates[] = [ $t['file'], \tsf_extension_manager()->coalesce_var( $t['args'], [] ) ];
	}

	/**
	 * Outputs template views.
	 *
	 * The loop will only run when templates are registered.
	 * @see $this->enqueue_scripts()
	 *
	 * @since 1.5.0
	 */
	public function _output_templates() {
		foreach ( static::$templates as $template )
			$this->output_view( $template[0], $template[1] );
	}

	/**
	 * Generates file URL.
	 *
	 * @since 1.5.0
	 * @staticvar string $min
	 * @staticvar string $rtl
	 * @NOTE smell: dupe from UI.trait
	 *
	 * @param array $script The script arguments.
	 * @param array $type Either 'js' or 'css'.
	 * @return string The file URL.
	 */
	final private function generate_file_url( array $script, $type = 'js' ) {

		static $min, $rtl;

		if ( ! isset( $min, $rtl ) ) {
			$min = \the_seo_framework()->script_debug ? '' : '.min';
			$rtl = \is_rtl() ? '.rtl' : '';
		}

		if ( 'js' === $type )
			return $script['base'] . "lib/js/{$script['name']}{$min}.js";

		return $script['base'] . "lib/css/{$script['name']}{$rtl}{$min}.css";
	}

	/**
	 * Outputs nonces to be verified at POST.
	 *
	 * Doesn't output a referer field, as that's already outputted by WordPress,
	 * including a duplicate by The SEO Framework.
	 *
	 * @since 1.5.0
	 * @access private
	 * @uses static::NONCE_NAME
	 * @uses static::NONCE_NAME
	 * @see @package The_SEO_Framework\Classes
	 *    method singular_inpost_box() [...] add_inpost_seo_box()
	 */
	public function _output_nonce() {
		\current_user_can( 'edit_post', $GLOBALS['post']->ID )
			and \wp_nonce_field( static::NONCE_ACTION, static::NONCE_NAME, false );
	}

	/**
	 * Verifies nonce on POST and writes the class $save_access_state variable.
	 *
	 * @since 1.5.0
	 * @access private
	 *
	 * @param integer  $post_id Post ID.
	 * @param \WP_Post $post    Post object.
	 * @return void Early when nonce or user can't be verified.
	 */
	public static function _verify_nonce( $post_id, $post ) {

		if ( ( empty( $_POST[ static::NONCE_NAME ] ) )
		|| ( ! \wp_verify_nonce( \wp_unslash( $_POST[ static::NONCE_NAME ] ), static::NONCE_ACTION ) )
		|| ( ! \current_user_can( 'edit_post', $post->ID ) )
		   ) return;

		static::$save_access_state = 0b0001;

		if ( ! defined( 'DOING_AUTOSAVE' ) || ! DOING_AUTOSAVE )
			static::$save_access_state |= 0b0010;
		if ( ! defined( 'DOING_AJAX' ) || ! DOING_AJAX )
			static::$save_access_state |= 0b0100;
		if ( ! defined( 'DOING_CRON' ) || ! DOING_CRON )
			static::$save_access_state |= 0b1000;

		$data = ! empty( $_POST[ static::META_PREFIX ] ) ? $_POST[ static::META_PREFIX ] : null;

		/**
		 * Runs after nonce and possibly interfering actions have been verified.
		 *
		 * @since 1.5.0
		 *
		 * @param \WP_Post      $post              The post object.
		 * @param array|null    $data              The meta data, set through `pm_index` keys.
		 * @param int (bitwise) $save_access_state The state the save is in.
		 *    Any combination of : {
		 *      1 = 0001 : Passed nonce and capability checks. Always set at this point.
		 *      2 = 0010 : Not doing autosave.
		 *      4 = 0100 : Not doing AJAX.
		 *      8 = 1000 : Not doing WP Cron.
		 *      |
		 *     15 = 1111 : Post is manually published or updated.
		 *    }
		 */
		\do_action_ref_array( 'tsfem_inpostgui_verified_nonce', [ $post, $data, static::$save_access_state ] );
	}

	/**
	 * Determines whether POST data can be safely written.
	 *
	 * @since 1.5.0
	 *
	 * @return bool True if user verification passed, and not doing autosave, cron, or ajax.
	 */
	public static function can_safely_write() {
		return ! ( static::$save_access_state ^ 0b1111 );
	}

	/**
	 * Adds registered active tabs to The SEO Framework inpost metabox.
	 *
	 * @since 1.5.0
	 * @access private
	 *
	 * @param array  $tabs  The registered tabs.
	 * @param string $label The post type label.
	 * @return array $tabs The SEO Framework's tabs.
	 */
	public function _load_tabs( array $tabs, $label ) {

		$registered_tabs = static::$tabs;
		$active_tab_keys = static::$active_tab_keys;

		foreach ( $registered_tabs as $index => $args ) :
			empty( $active_tab_keys[ $index ] ) or
				$tabs[ $index ] = $this->append_type_arg( $args, $label );
		endforeach;

		return $tabs;
	}

	/**
	 * Appends post type label argument to the tab arguments.
	 *
	 * @since 1.5.0
	 *
	 * @param array  $tab_args The current tab arguments.
	 * @param string $label    The post type label.
	 * @return array The extended tab arguments.
	 */
	private function append_type_arg( array $tab_args, $label ) {
		$tab_args['args'] += [ 'post_type_label' => $label ];
		return $tab_args;
	}

	/**
	 * Output tabs content through loading registered tab views in order of
	 * priority or registration time.
	 *
	 * @since 1.5.0
	 * @access private
	 *
	 * @param string $tab The tab that invoked this method call.
	 */
	public function _output_tab_content( $tab ) {

		if ( isset( static::$views[ $tab ] ) ) {
			$views = static::$views[ $tab ];
			//= Sort by the priority indexes. Priority values get lost in this process.
			sort( $views );

			foreach ( $views as $view )
				$this->output_view( $view[0], $view[1] );
		}
	}

	/**
	 * Outputs tab view, whilst trying to prevent 3rd party interference on views.
	 *
	 * There's a secret key generated on each tab load. This key can be accessed
	 * in the view through `$_secret`, and be sent back to this class.
	 * @see \TSF_Extension_Manager\InpostGUI::verify( $secret )
	 *
	 * @since 1.5.0
	 * @uses static::$include_secret
	 *
	 * @param string $file The file location.
	 * @param array  $args The registered view arguments.
	 */
	private function output_view( $file, array $args ) {
		foreach ( $args as $_key => $_val )
			$$_key = $_val;

		unset( $_key, $_val, $args );

		//= Prevent private includes hijacking.
		static::$include_secret = $_secret = mt_rand() . uniqid();
		include $file;
		static::$include_secret = null;
	}

	/**
	 * Verifies view inclusion secret.
	 *
	 * @since 1.5.0
	 * @see static::output_view()
	 * @uses static::$include_secret
	 *
	 * @param string $secret The passed secret.
	 * @return bool True on success, false on failure.
	 */
	public static function verify( $secret ) {
		return static::$include_secret === $secret;
	}

	/**
	 * Activates registered tab for display.
	 *
	 * Structure: Rich/structured data controls.
	 * Audit:     Monitoring, reviewing content, analytics, etc.
	 * Advanced:  Everything else.
	 *
	 * @since 1.5.0
	 * @see static::register_tabs()
	 * @uses static::$active_tab_keys
	 *
	 * @param string $tab The tab to activate.
	 *               Either 'structure', 'audit' or 'advanced'.
	 */
	public static function activate_tab( $key ) {
		static::$active_tab_keys[ $key ] = true;
	}

	/**
	 * Registers view for tab.
	 *
	 * @since 1.5.0
	 * @see static::activate_tab();
	 * @uses static::$views
	 *
	 * @param string $file The file to include.
	 * @param array  $args The arguments to pass to the file. Each array index is
	 *               converted to a respectively named variable.
	 * @param string $tab  The tab the view is outputted in.
	 * @param int|float $priority The priority of the view. A lower value results in an earlier output.
	 */
	public static function register_view( $file, array $args = [], $tab = 'advanced', $priority = 10 ) {
		//= Prevent excessive static calls and write directly to var.
		$_views =& static::$views;

		if ( ! isset( $_views[ $tab ] ) )
			$_views[ $tab ] = [];
		if ( ! isset( $_views[ $tab ][ $priority ] ) )
			$_views[ $tab ][ $priority ] = [];

		$_views[ $tab ][ $priority ] += [ $file, $args ];
	}

	/**
	 * Builds option key index, which can later be retrieved in POST.
	 *
	 * @since 1.5.0
	 * @see trait \TSF_Extension_Manager\Extension_Post_Meta
	 * @see static::_verify_nonce();
	 *
	 * @param string $option The option.
	 * @param string $index  The post meta index (pm_index).
	 * @return string The option prefix.
	 */
	public static function get_option_key( $option, $index ) {
		return sprintf( '%s[%s][%s]', static::META_PREFIX, $index, $option );
	}

	/**
	 * Wraps and outputs content in common flex wrap for tabs.
	 *
	 * @since 1.5.0
	 * @uses static::construct_flex_wrap();
	 * @see documentation static::construct_flex_wrap();
	 *
	 * @param string $what    The type of wrap to use.
	 * @param string $content The content to wrap. Should be escaped.
	 * @param string $id      The wrap ID.
	 * @param string $for     The input ID an input label is for. Should be escaped.
	 */
	public static function wrap_flex( $what, $content, $id = '', $for = '' ) {
		//= Input should already be escaped.
		echo static::construct_flex_wrap( $what, $content, $id, $for );
	}

	/**
	 * Wraps and outputs and array of content in common flex wrap for tabs.
	 *
	 * Mainly used to wrap blocks and checkboxes.
	 * Does not accept title labels directly.
	 *
	 * @since 1.5.0
	 * @uses static::construct_flex_wrap();
	 * @see documentation static::construct_flex_wrap();
	 *
	 * @param string $what     The type of wrap to use.
	 * @param array  $contents The contents to wrap. Should be escaped.
	 * @param string $id       The wrap ID.
	 */
	public static function wrap_flex_multi( $what, array $contents, $id = '' ) {
		//= Input should already be escaped.
		echo static::contruct_flex_wrap_multi( $what, $contents, $id );
	}

	/**
	 * Wraps an array content in common flex wrap for tabs.
	 *
	 * Mainly used to wrap blocks and checkboxes.
	 * Does not accept title labels directly.
	 *
	 * @since 1.5.0
	 * @uses static::construct_flex_wrap();
	 * @see documentation static::construct_flex_wrap();
	 *
	 * @param string $what     The type of wrap to use.
	 * @param array  $contents The contents to wrap. Should be escaped.
	 * @param string $id       The wrap ID.
	 */
	public static function contruct_flex_wrap_multi( $what, array $contents, $id = '' ) {
		return static::construct_flex_wrap( $what, implode( PHP_EOL, $contents ), $id );
	}

	/**
	 * Wraps content in common flex wrap for tabs.
	 *
	 * @since 1.5.0
	 * @see static::wrap_flex();
	 *
	 * @param string $what The type of wrap to use. Accepts:
	 *               'block'        : The main wrap. Wraps a label and input/content block.
	 *               'label'        : Wraps a label.
	 *                                Be sure to wrap parts in `<div>` for alignment.
	 *               'label-input'  : Wraps an input label.
	 *                                Be sure to assign the $for parameter.
	 *                                Be sure to wrap parts in `<div>` for alignment.
	 *               'input'        : Wraps input content fields, plainly.
	 *               'content'      : Same as 'input'.
	 *               'checkbox'     : Wraps a checkbox and its label.
	 * @param string $content The content to wrap. Should be escaped.
	 * @param string $id      The wrap ID.
	 * @param string $for     The input ID an input label is for. Should be escaped.
	 */
	public static function construct_flex_wrap( $what, $content, $id = '', $for = '' ) {

		$id = $id ? "id=$id" : '';

		switch ( $what ) :
			case 'block' :
				$content = sprintf( '<div class="tsf-flex-setting tsf-flex" %s>%s</div>', $id, $content );
				break;

			case 'label' :
				$content = sprintf(
					'<div class="tsf-flex-setting-label tsf-flex" %s>
						<div class="tsf-flex-setting-label-inner-wrap tsf-flex">
							<div class="tsf-flex-setting-label-item tsf-flex">
								%s
							</div>
						</div>
					</div>',
					$id,
					$content
				);
				break;

			case 'label-input' :
				$for or \the_seo_framework()->_doing_it_wrong( __METHOD__, 'Set the <code>$for</code> (3rd) parameter.' );
				$content = sprintf(
					'<div class="tsf-flex-setting-label tsf-flex" %s>
						<div class="tsf-flex-setting-label-inner-wrap tsf-flex">
							<label for="%s" class="tsf-flex-setting-label-item tsf-flex">
								%s
							</label>
						</div>
					</div>',
					$id,
					$for,
					$content
				);
				break;

			case 'input' :
			case 'content' :
				$content = sprintf( '<div class="tsf-flex-setting-input tsf-flex" %s>%s</div>', $id, $content );
				break;

			case 'block-open' :
				$content = sprintf( '<div class="tsf-flex-setting tsf-flex" %s>%s', $id, $content );
				break;

			case 'input-open' :
			case 'content-open' :
				$content = sprintf( '<div class="tsf-flex-setting-input tsf-flex" %s>%s', $id, $content );
				break;

			case 'block-close' :
			case 'input-close' :
			case 'content-close' :
				$content = '</div>';
				break;

			//! Not used.
			// case 'checkbox' :
			// 	$content = sprintf( '<div class="tsf-checkbox-wrapper">%s</div>', $content );
			// 	break;

			default :
				break;
		endswitch;

		return $content;
	}
}

$_load_inpostgui_class();
