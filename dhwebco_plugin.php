<?php

/* Base class for plugins */

if (!class_exists('dhwebco_plugin')) {
	abstract class dhwebco_plugin {
		private $_plugin_slug;
		private $_plugin_dir;
		private $_plugin_url;

		/* Ctor. */
		public function __construct($plugin_file) {
			$this->_plugin_slug = ltrim(str_replace(WP_PLUGIN_DIR, '', dirname($plugin_file)), '/');

			$this->_plugin_dir = dirname($plugin_file);

			$this->_plugin_url = trailingslashit(WP_PLUGIN_URL) . $this->_plugin_slug;

			add_action('init', array(&$this, 'hook_init'));
			add_action('add_meta_boxes', array(&$this, 'hook_add_meta_boxes'));
			add_action('save_post', array(&$this, 'delegate_save_post_hook'));
		}

		/* Utility methods */

		/**
		 * Get the directory path for the child plugin
		 * @param  string $file Add this file onto the path (optional)
		 * @return string       The directory path
		 */
		protected function plugin_dir($file = '') {
			$file = ltrim($file, '/');
			return trailingslashit($this->_plugin_dir) . $file;
		}

		/**
		 * Get the URL for the child plugin
		 * @param  string $file Add this file onto the URL (optional)
		 * @return string       The URL
		 */
		protected function plugin_url($file = '') {
			$file = ltrim($file, '/');
			return trailingslashit($this->_plugin_url) . $file;
		}
		
		/**
		 * Create a basic custom post type.
		 * @param  string $slug     Machine-readable name of the CPT.
		 * @param  string $singular Singular name of the CPT (optional).
		 * @param  string $plural   Plural name of the CPT (optional).
		 * @param  array  $supports What features the CPT supports (optional).
		 * @param  string $menu_icon URL of the menu icon for the CPT
		 * @return void
		 */
		protected function create_basic_cpt($slug, $singular = NULL, $plural = NULL, $supports = array(), $menu_icon = NULL) {
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

			$args = array(
				'labels' => $labels,
				'public' => true,
				'publicly_queryable' => true,
				'show_ui' => true, 
				'show_in_menu' => true, 
				'query_var' => true,
				'rewrite' => true,
				'capability_type' => 'post',
				'has_archive' => true, 
				'hierarchical' => false,
				'menu_position' => null,
				'supports' => $supports,
				'menu_icon' => $menu_icon,
			); 

			register_post_type($slug, $args);
		}

		/**
		 * Call an appropriate hook for save_post. If a method exists called
		 * hook_save_post_{posttype}, it will call it. Otherwise it will call
		 * hook_save_post.
		 * @param  [type] $post_id [description]
		 * @return [type]          [description]
		 */
		public function delegate_save_post_hook($post_id) {
			$post_type = get_post_type();
			if (method_exists(&$this, 'hook_save_post_' . $post_type)) {
				call_user_func(array(&$this, 'hook_save_post_' . $post_type), $post_id);
			} else {
				$this->hook_save_post($post_id);
			}
		}

		protected function update_post_meta($post_id, $key, array $expected) {
			$meta = array();
			foreach ($expected as $val) {
				$meta[$val] = $_POST[$val];
			}

			update_post_meta($post_id, $key, $meta);
		}

		/* End utility methods */

		/** Hooks and filters **/
		public function hook_init() { }
		public function hook_add_meta_boxes() { }
		public function hook_save_post($post_id) { }

		/** End hooks and filters **/
	}
}

if (!class_exists('dhwebco_form')) {
	class dhwebco_form {
		private $_fields = array();

		const FIELD_TYPE_TEXT = 'text';

		public $show_as_table = TRUE;

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
			$this->output();
		}
	}
}