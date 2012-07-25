<?php

/**
 * A base class for WordPress plugins
 * 
 * @version 0.1
 * @author Devin Humbert <devin.humbert@gmail.com>
 * @link https://bitbucket.org/dhumbert/dhwebco_plugin
 * @copyright 2011 Devin Humbert
 * @license  GPLv3
 * 
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 * 
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 * 
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

if (!class_exists('dhwebco_plugin')) {
	abstract class dhwebco_plugin {
		private $_this_file_url;
		private $_this_file_dir;

		private $_plugin_slug;
		private $_plugin_dir;
		private $_plugin_url;

		public $add_thesis_seo_to_cpts = TRUE;

		private $_cpts = array();

		public function __construct($plugin_file) {
			$this->_this_file_dir = trailingslashit(dirname(__FILE__));
			$this->_this_file_url = trailingslashit(str_replace(WP_PLUGIN_DIR, WP_PLUGIN_URL, $this->_this_file_dir));

			$this->_plugin_slug = ltrim(str_replace(WP_PLUGIN_DIR, '', dirname($plugin_file)), '/');
			$this->_plugin_dir = trailingslashit(dirname($plugin_file));
			$this->_plugin_url = trailingslashit(trailingslashit(WP_PLUGIN_URL) . $this->_plugin_slug);

			add_action('admin_enqueue_scripts', array(&$this, 'admin_enqueue_scripts'));

			add_action('init', array(&$this, 'hook_init'));
			add_action('admin_init', array(&$this, 'hook_admin_init'));
			add_action('add_meta_boxes', array(&$this, 'hook_add_meta_boxes'));
			add_action('add_meta_boxes', array(&$this, 'add_thesis_meta_to_cpts'));
			add_action('save_post', array(&$this, 'delegate_save_post_hook'));

			add_theme_support('post-thumbnails');
		}

		public function admin_enqueue_scripts() {
			wp_enqueue_script('dhwebco_form_js', $this->_this_file_url . 'js/form-admin.js');
			wp_localize_script('dhwebco_form_js', 'dhwebco_plugin', array(
				'base_url' => $this->_this_file_url,
			));

			wp_enqueue_style('dhwebco_jqui_css', $this->_this_file_url . 'js/jquery-ui/smoothness/jquery-ui-1.8.20.custom.css');
		}

		/* Utility methods */

		/**
		 * Get the directory path for the child plugin
		 * @param  string $file Add this file onto the path (optional)
		 * @return string       The directory path
		 */
		protected function plugin_dir($file = '') {
			$file = ltrim($file, '/');
			return $this->_plugin_dir . $file;
		}

		/**
		 * Get the URL for the child plugin
		 * @param  string $file Add this file onto the URL (optional)
		 * @return string       The URL
		 */
		protected function plugin_url($file = '') {
			$file = ltrim($file, '/');
			return $this->_plugin_url . $file;
		}

		/**
		 * Add a shortcode. Not much easier than add_shortcode, but
		 * could be extended to perform more functionality at some point.
		 * @param  string   $code     Shortcode
		 * @param  string 	$callback The callback that renders the shortcode
		 * @return void
		 */
		protected function shortcode($code, $callback = NULL) {
			if (!$callback) $callback = 'shortcode_' . $code;
			add_shortcode($code, array(&$this, $callback));
		}
		
		/**
		 * Create a basic custom post type.
		 * @param  string $slug     Machine-readable name of the CPT.
		 * @param  string $singular Singular name of the CPT (optional).
		 * @param  string $plural   Plural name of the CPT (optional).
		 * @param  array  $supports What features the CPT supports (optional).
		 * @param  string $menu_icon URL of the menu icon for the CPT (optional).
		 * @param  string $rewrite_slug The slug for the CPT. Defaults to slugified version of singular. "/" means no slug.
		 * @param  callable $meta_callback The callback to display a meta box. NOT to add_meta_box.
		 * @return void
		 */
		protected function create_cpt($slug, $singular = NULL, $plural = NULL, $supports = array(), $menu_icon = NULL, $rewrite_slug = NULL, $meta_callback = NULL) {
			if (!$singular) $singular = ucwords(str_replace('_', ' ', $slug));
			if (!$plural) $plural = $singular . 's';
			if (!is_array($supports) || count($supports) == 0) 
				$supports = array( 'title', 'editor', 'author', 'thumbnail', 'excerpt', 'comments' );

			$labels = array(
				'name' => _x($plural, 'post type general name'),
				'singular_name' => _x($singular, 'post type singular name'),
				'add_new' => __('Add New'),
				'add_new_item' => __('Add New ' . $singular),
				'edit_item' => __('Edit ' . $singular),
				'new_item' => __('New ' . $singular),
				'all_items' => __('All ' . $plural),
				'view_item' => __('View ' . $singular),
				'search_items' => __('Search ' . $plural),
				'not_found' =>  __('No ' . $plural . ' found'),
				'not_found_in_trash' => __('No ' . $plural . ' found in Trash'), 
				'parent_item_colon' => '',
				'menu_name' => $plural,
			);

			if (!$rewrite_slug) $rewrite_slug = strtolower(str_replace('_', '-', sanitize_title($singular)));

			$args = array(
				'labels' => $labels,
				'public' => true,
				'publicly_queryable' => true,
				'show_ui' => true, 
				'show_in_menu' => true, 
				'query_var' => true,
				'rewrite' => array(
					'slug' => $rewrite_slug,
				),
				'capability_type' => 'post',
				'has_archive' => true, 
				'hierarchical' => false,
				'menu_position' => null,
				'supports' => $supports,
				'menu_icon' => $menu_icon,
			);

			if ($meta_callback) {
				$args['register_meta_box_cb'] = function() use ($slug, $singular, $meta_callback) {
					add_meta_box($slug.'-meta', $singular . ' Settings', $meta_callback, $slug, 'normal', 'high');
				};
			}

			register_post_type($slug, $args);
			$this->_cpts[] = $slug;
		}

		/**
		 * If Thesis is installed, add the SEO box to custom post types.
		 */
		public function add_thesis_meta_to_cpts() {
			if ($this->add_thesis_seo_to_cpts) {
				if (class_exists('thesis_post_options')) {
			        foreach ($this->_cpts as $cpt) {
				        add_meta_box('thesis_seo_meta', 'SEO Details and Additional Style', array('thesis_post_options', 'output_seo_box'), $cpt, 'normal', 'high');
				    }
				}
			}
		}

		/**
		 * Create a custom taxonomy.
		 * @param  string $slug       Machine-readable name of the taxonomy.
		 * @param  array  $post_types Slugs of the post types this taxonomy will support.
		 * @param  string $singular   Singular name (optional).
		 * @param  string $plural     Plural name (optional).
		 * @return void
		 */
		public function create_tax($slug, array $post_types, $singular = NULL, $plural = NULL) {
			if (!$singular) $singular = ucwords(str_replace('_', ' ', $slug));
			if (!$plural) $plural = $singular . 's';

			$labels = array(
				'name' => _x( $plural, 'taxonomy general name' ),
				'singular_name' => _x( $singular, 'taxonomy singular name' ),
				'search_items' =>  __( 'Search ' . $plural ),
				'all_items' => __( 'All ' . $plural ),
				'parent_item' => __( 'Parent ' . $singular ),
				'parent_item_colon' => __( 'Parent ' . $singular . ':' ),
				'edit_item' => __( 'Edit ' . $singular ), 
				'update_item' => __( 'Update ' . $singular ),
				'add_new_item' => __( 'Add New ' . $singular ),
				'new_item_name' => __( 'New ' . $singular . ' Name' ),
				'menu_name' => __( $plural ),
			); 	

			if (!$rewrite_slug) $rewrite_slug = strtolower(str_replace('_', '-', sanitize_title($singular)));

			register_taxonomy($slug, $post_types, array(
				'hierarchical' => true,
				'labels' => $labels,
				'show_ui' => true,
				'query_var' => true,
				'rewrite' => array( 'slug' => $rewrite_slug ),
			));
		}

		/**
		 * Call an appropriate hook for save_post. If a method exists called
		 * hook_save_post_{posttype}, it will call it. Otherwise it will call
		 * hook_save_post.
		 * @param  [type] $post_id [description]
		 * @return [type]          [description]
		 */
		public function delegate_save_post_hook($post_id) {
			if ( wp_is_post_revision( $post_id ) ) return;
			if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
			
			$post_type = get_post_type();
			if (method_exists(&$this, 'hook_save_post_' . $post_type)) {
				call_user_func(array(&$this, 'hook_save_post_' . $post_type), $post_id);
			} else {
				$this->hook_save_post($post_id);
			}
		}

		/**
		 * Bulk update an array of post meta from $_POST.
		 * @param  int $post_id  The ID of the post.
		 * @param  string $key      The meta key.
		 * @param  array  $expected Expected keys from $_POST. Will also be used for indices in meta array.
		 * @return void
		 */
		protected function update_post_meta($post_id, $key, array $expected) {
			$meta = array();
			foreach ($expected as $val) {
				$meta[$val] = $_POST[$val];
			}

			update_post_meta($post_id, $key, $meta);
		}

		/**
		 * Add a shortcode. Takes care of buffering output to return
		 * it instead of echoing it.
		 * @param  string   $code     The shortcode.
		 * @param  function $callback Callback.
		 */
		public function shortcode($code, $callback) {
			add_shortcode($code, function($args, $content = '') use ($callback) {
				ob_start();
				call_user_func($callback, $args, $content);
				return ob_get_clean();
			});
		}

		/* End utility methods */

		/** Hooks and filters **/
		public function hook_init() { }
		public function hook_admin_init() { }
		public function hook_add_meta_boxes() { }
		public function hook_save_post($post_id) { }

		/** End hooks and filters **/
	}
}

if (!class_exists('dhwebco_form')) {
	class dhwebco_form {
		private $_this_file_url;
		private $_this_file_dir;

		private $_fields = array();

		const FIELD_TYPE_TEXT = 'text';
		const FIELD_TYPE_DATE = 'date';
		const FIELD_TYPE_WYSIWYG = 'wysiwyg';

		public $show_as_table = TRUE;

		public function __construct() {
			$this->_this_file_dir = trailingslashit(dirname(__FILE__));
			$this->_this_file_url = trailingslashit(str_replace(WP_PLUGIN_DIR, WP_PLUGIN_URL, $this->_this_file_dir));			
		}

		/**
		 * Add a field to the form.
		 * @param string $name    The name of the field. Will also be used for the element ID.
		 * @param string $label   Label for the field
		 * @param string $value   Value for the field
		 * @param string $type    	  Type of field (see class constants) (optional)
		 * @param array $attributes  Additional HTML attributes for the field (optional)
		 * @param array $options  Options for select, checkboxes, radio, etc. (optional)
		 */
		public function add_field($name, $label, $value, $type = self::FIELD_TYPE_TEXT, $attributes = array(), $options = NULL) {
			$this->_fields[] = array(
				'name' => $name,
				'label' => $label,
				'value' => $value,
				'type' => $type,
				'attributes' => $attributes,
				'options' => $options,
			);
		}

		/**
		 * Output a single field.
		 * @param  array  $field Field array.
		 * @return void
		 */
		public function output_field(array $field) {
			if (method_exists(&$this, '_render_field_' . $field['type'])) {
				call_user_func(array(&$this, '_render_field_' . $field['type']), $field);
			}
		}

		/**
		 * Render a text field.
		 * @param  array $field  Field array.
		 * @return  void
		 */
		private function _render_field_text($field) {
			if (!isset($field['attributes']['class'])) $field['attributes']['class'] = 'widefat';

			printf('<input type="text" name="%s" id="%s" value="%s" %s />',
				$field['name'],
				$field['name'],
				esc_html($field['value']),
				$this->_html_attributes($field['attributes'])
			);
		}

		/**
		 * Render a date field. Adds the "datepicker" class, which will automatically
		 * add the jQuery UI datepicker.
		 * @param  array $field Field array.
		 * @return void
		 */
		private function _render_field_date($field) {
			if (!isset($field['attributes']['class'])) $field['attributes']['class'] = '';
			$field['attributes']['class'] .= ' datepicker';

			printf('<input type="text" name="%s" id="%s" value="%s" %s />',
				$field['name'],
				$field['name'],
				esc_html($field['value']),
				$this->_html_attributes($field['attributes'])
			);
		}

		private function _render_field_wysiwyg($field) {
			wp_editor($field['value'], $field['name'], $field['attributes']);
		}

		/**
		 * Concatenate an array into HTML attributes.
		 * @param  array $attributes Key/value array for attributes
		 * @return string The HTML attribute string.
		 */
		private function _html_attributes($attributes) {
			$attr_string = '';
			foreach ($attributes as $name => $value) {
				$attr_string .= $name . '="' . esc_html($value) . '" ';
			}

			return $attr_string;
		}

		/**
		 * Output the form.
		 * @return void
		 */
		public function output() {
			$container_tag = ($this->show_as_table) ? 'table' : 'div';
			$row_tag = ($this->show_as_table) ? 'tr' : 'div';
			$first_col_tag = ($this->show_as_table) ? 'th' : 'div';
			$col_tag = ($this->show_as_table) ? 'td' : 'div';

			$class = 'dhwebco_form';
			if ($this->show_as_table) $class .= ' form-table';

			printf('<%s class="%s">', $container_tag, $class);

			foreach ($this->_fields as $field) {
				printf('<%s>', $row_tag);
				
				printf('<%s>', $first_col_tag);
				printf('<label for="%s">%s</label>', $field['name'], $field['label']);
				printf('</%s>', $first_col_tag);

				printf('<%s>', $col_tag);
				$this->output_field($field);
				printf('</%s>', $col_tag);
				
				printf('</%s>', $row_tag);
			}

			printf('</%s>', $container_tag);
		}

		/**
		 * Return the output (do not echo it)
		 * @return string The form output.
		 */
		public function get_output() {
			ob_start();
			$this->output();
			return ob_get_clean();
		}

		public function __toString() {
			return $this->get_output();
		}
	}
}