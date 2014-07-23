<?php
namespace Aelia\WC;
if(!defined('ABSPATH')) exit; // Exit if accessed directly

use \InvalidArgumentException;

/**
 * Implements a class that will render the settings page.
 */
class Settings_Renderer {
	// @var Aelia\WC\Settings The settings controller which will handle the settings.
	protected $_settings_controller;
	// @var string The text domain to be used for localisation.
	protected $_textdomain = '';
	// @var string The key to identify plugin settings amongst WP options.
	protected $_settings_key;

	// @var string The default tab ID used if none is specified when class is instantiated.
	const DEFAULT_SETTINGS_TAB_ID = 'default';

	// @var string The ID of the WC menu in the admin section. Used to add submenus.
	const WC_MENU_ITEM_ID = 'woocommerce';

	// @var array A list of tabs used to organise the settings sections.
	protected $_settings_tabs = array();

	// @var string The ID of the default tab where to store the settings sections.
	protected $_default_tab;

	/*** Auxiliary functions ***/
	/**
	 * Returns current plugin settings, or the value a specific setting.
	 *
	 * @param string key If specified, method will return only the setting identified
	 * by the key.
	 * @param mixed default The default value to return if the setting requested
	 * via the "key" argument is not found.
	 * @return array|mixed The plugin settings, or the value of the specified
	 * setting.
	 *
	 * @see Aelia\WC\Settings::current_settings().
	 */
	public function current_settings($key = null, $default = null) {

		return $this->_settings_controller->current_settings($key, $default);
	}

	/**
	 * Returns the default settings for the plugin. Used mainly at first
	 * installation.
	 *
	 * @param string key If specified, method will return only the setting identified
	 * by the key.
	 * @param mixed default The default value to return if the setting requested
	 * via the "key" argument is not found.
	 * @return array|mixed The default settings, or the value of the specified
	 * setting.
	 *
	 * @see Aelia\WC\Settings::default_settings().
	 */
	protected function default_settings($key = null, $default = null) {
		return $this->_settings_controller->current_settings($key, $default);
	}

	/**
	 * Class constructor.
	 *
	 * @param string default_tab the default tab inside which sections should be
	 * rendered, unless a different tab is specified for them.
	 * @return Aelia\WC\Settings.
	 */
	public function __construct($default_tab = self::DEFAULT_SETTINGS_TAB_ID) {
		$this->_default_tab = $default_tab;

		add_action('admin_menu', array($this, 'add_settings_page'));
	}

	/**
	* Add a new section to a settings page. This method relies on standard
	* WordPress add_settings_section() function, with the difference that it takes
	* a "Tab" argument, which can be used to display settings divided into Tabs
	* without having to implement multiple validations or having to figure out
	* which page was posted and what data should be validated each time.
	*
	* @global $wp_settings_sections Storage array of all settings sections added to admin pages.
	*
	* @param string id Slug-name to identify the section. Used in the 'id' attribute of tags.
	* @param string title Formatted title of the section. Shown as the heading for the section.
	* @param string callback Function that echos out any content at the top of the section (between heading and fields).
	* @param string page The slug-name of the settings page on which to show the section. Built-in pages include 'general', 'reading', 'writing', 'discussion', 'media', etc. Create your own using add_options_page();
	* @param string tab_id Slug-name of the Tab where the settings section should be rendered.
	*/
	protected function add_settings_section($id, $title, $callback, $page, $tab_id = self::DEFAULT_SETTINGS_TAB_ID) {
		if(isset($this->_settings_tabs[$page][$tab_id])) {
			$tab = &$this->_settings_tabs[$page][$tab_id];
		}
		else {
			$tab = &$this->get_default_tab($page);
		}

		$tab['sections'][] = $id;
		//var_dump($tab, $this->_settings_tabs);

		add_settings_section($id, $title, $callback, $page);
	}

	/**
	 * Adds a settings tab, which will contain settings sections and fields.
	 *
	 * @param string page The slug-name of the settings page on which to show the section. Built-in pages include 'general', 'reading', 'writing', 'discussion', 'media', etc. Create your own using add_options_page();
	 * @param string tab_id Slug-name of the Tab where the settings section should be rendered.
	 */
	protected function add_settings_tab($page, $tab_id, $tab_label) {
		if(!isset($this->_settings_tabs[$page])) {
			$this->_settings_tabs[$page] = array();
		}

		$this->_settings_tabs[$page][$tab_id] = array(
			'label' => $tab_label,
			'sections' => array(),
		);
	}

	/**
	 * Returns the default tab where the settings sections will be put when they
	 * are not set to be displayed in a specific tab.
	 *
	 * @param string page The slug-name of the settings page on which to show the section. Built-in pages include 'general', 'reading', 'writing', 'discussion', 'media', etc. Create your own using add_options_page();
	 */
	protected function &get_default_tab($page) {
		if(!get_value(self::DEFAULT_SETTINGS_TAB_ID, $this->_settings_tabs[$page])) {
			$this->add_settings_tab($page,
															self::DEFAULT_SETTINGS_TAB_ID,
															__('Default', $this->_textdomain));
		}

		return $this->_settings_tabs[$page][self::DEFAULT_SETTINGS_TAB_ID];
	}

	/**
	 * Sets the tabs to be used to render the Settings page.
	 */
	protected function add_settings_tabs() {
		// To be implemented by descendant class
	}

	/**
	 * Configures the plugin settings sections.
	 */
	protected function add_settings_sections() {
		// To be implemented by descendant class
	}

	/**
	 * Configures the plugin settings fields.
	 */
	protected function add_settings_fields() {
		// To be implemented by descendant class
	}

	/**
	 * Returns the title for the menu item that will bring to the plugin's
	 * settings page.
	 *
	 * @return string
	 */
	protected function menu_title() {
		return __('WC - Aelia Foundation Classes', $this->_textdomain);
	}

	/**
	 * Returns the slug for the menu item that will bring to the plugin's
	 * settings page.
	 *
	 * @return string
	 */
	protected function menu_slug() {
		return 'wc-aelia-foundation-classes';
	}

	/**
	 * Returns the title for the settings page.
	 *
	 * @return string
	 */
	protected function page_title() {
		return __('Aelia Foundation Classes for WC - Settings', $this->_textdomain);
	}

	/**
	 * Returns the description for the settings page.
	 *
	 * @return string
	 */
	protected function page_description() {
		return __('Sample page description', $this->_textdomain);
	}

	/**
	 * Renders all settings sections added to a particular settings page. This
	 * method is an almost exact clone of global do_settings_sections(), the main
	 * difference is that each section is wrapped in its own <div>.
	 *
	 * Part of the Settings API. Use this in a settings page callback function
	 * to output all the sections and fields that were added to that $page with
	 * add_settings_section() and add_settings_field().
	 *
	 * @global $wp_settings_sections Storage array of all settings sections added to admin pages
	 * @global $wp_settings_fields Storage array of settings fields and info about their pages/sections
	 * @since 2.7.0
	 *
	 * @param string $page The slug name of the page whos settings sections you want to output
	 */
	// TODO Extract method. This method belongs to a View, not a Controller
	protected function render_settings_sections($page) {
		global $wp_settings_sections, $wp_settings_fields;

		$settings_sections = get_value($page, $wp_settings_sections);
		if(empty($settings_sections)) {
			return;
		}

		//foreach((array)$wp_settings_sections[$page] as $section) {
		$settings_tabs = get_value($page, $this->_settings_tabs);
		$output_tabs = count($settings_tabs) > 1;

		if($output_tabs) {
			echo '<div class="settings-page tabs">';
			echo "<ul>\n";
			foreach($settings_tabs as $tab_id => $tab_info) {
				echo "<li><a href=\"#tab-{$tab_id}\">{$tab_info['label']}</a></li>\n";
			}
			echo "</ul>\n";
		}
		else {
			echo '<div class="settings-page">';
		}

		foreach($settings_tabs as $tab_id => $tab_info) {
			$tab_label = get_value('label', $tab_info);
			$sections = get_value('sections', $tab_info, array());

			echo "<div id=\"tab-{$tab_id}\">";
			foreach($sections as $section_id) {
				$section = get_value($section_id, $wp_settings_sections[$page]);
				echo '<div id="section-'. $section_id .'" class="settings-section">';
				if($section['title']) {
					echo "<h3>{$section['title']}</h3>\n";
				}

				if($section['callback']) {
					call_user_func($section['callback'], $section);
				}

				$section_id = get_value('id', $section);
				if(get_value($section_id, $wp_settings_fields[$page], false) == true) {
					echo '<table class="form-table">';
					do_settings_fields($page, $section['id']);
					echo '</table>';
				}

				echo '</div>';
			}
			echo '</div>';
		}
		echo '</div>';
	}

	/**
	 * Renders the buttons at the bottom of the settings page.
	 */
	protected function render_buttons() {
		submit_button(__('Save Changes', $this->_textdomain),
							'primary',
							'submit',
							false);
	}

	/**
	 * Renders the Options page for the plugin.
	 */
	public function render_options_page() {
		echo '<div class="wrap">';
		echo '<div class="icon32" id="icon-options-general"></div>';
		echo '<h2>' . $this->page_title() . '</h2>';
		echo '<p>' . $this->page_description() . '</p>';

		settings_errors();
		echo '<form id="' . $this->_settings_key . '_form" method="post" action="options.php">';
		settings_fields($this->_settings_key);
		//do_settings_sections($this->_settings_key);
		$this->render_settings_sections($this->_settings_key);
		echo '<div class="buttons">';
		$this->render_buttons();
		echo '</div>';
		echo '</form>';
	}

	/**
	 * Adds a link to Settings Page in WC Admin menu.
	 */
	public function add_settings_page() {
		$settings_page = add_submenu_page(
			self::WC_MENU_ITEM_ID,
	    $this->page_title(),
	    $this->menu_title(),
			'manage_options',
			$this->menu_slug(),
			array($this, 'render_options_page')
		);

		add_action('load-' . $settings_page, array($this, 'options_page_load'));
	}

	/**
   * Takes an associative array of attributes and returns them as a string of
   * param="value" sets to be placed in an input, select, textarea, etc tag.
   *
   * @param array attributes An associative array of attribute key => value
   * pairs to be converted to a string. A number of "reserved" keys will be
   * ignored.
   * @return string
   */
	protected function attributes_to_string(array $attributes) {
		$reserved_attributes = array(
			'id',
			'name',
			'value',
			'method',
			'action',
			'type',
			'for',
			'multiline',
			'default',
			'textfield',
			'valuefield',
			'includenull',
			'yearrange',
			'fields',
			'inlineerrors');

		$result = array();
    // Build string from array
    if(is_array($attributes)) {
			foreach($attributes as $attribute => $value) {
				// Ignore reserved attributes
				if(!in_array(strtolower($attribute), $reserved_attributes)) {
					$result[] = $attribute . '="' . $value . '"';
				}
			}
		}
		return implode(' ', $result);
	}

	/**
	 * Extracts the Field ID and Field Name for an input element from the arguments
	 * originally passed to a rendering function.
	 *
	 * @param array field_args The arguments passed to the rendering function which
	 * is going to render the input field.
	 * @param string field_id Output argument. It will contain the ID of the field.
	 * @param string field_name Output argument. It will contain the name of the field.
	 */
	protected function get_field_ids(array $field_args, &$field_id, &$field_name) {
		// Determine field ID and Name
		$field_id = $field_args['id'];
		if(empty($field_id)) {
			throw new InvalidArgumentException(__('Field ID must be specified.', $this->_textdomain));
		}
		$field_name = get_value('name', $field_args, $field_id);

		// If a Settings Key has been passed, modify field ID and Name to make them
		// part of the Settings Key array
		$settings_key = get_value('settings_key', $field_args, null);
		$field_id = $this->group_field($field_id, $settings_key);
		$field_name = $this->group_field($field_name, $settings_key);
	}

	/**
	 * Takes a field ID and transforms it so that it becomes part of a field group.
	 * Example
	 * - Field ID: MyField
	 * - Group: SomeGroup
	 *
	 * Result: SomeGroup[MyField]
	 * This allows to group fields together and access them as an array.
	 *
	 * @param string id The Field ID.
	 * @param string group The group to which the field should be added.
	 * @return string The new field name.
	 */
	protected function group_field($id, $group) {
		return empty($group) ? $id : $group . '[' . $id . ']';
	}

	/*** Rendering methods ***/
	/**
	 * Renders a select box.
	 *
	 * @param array args An array of arguments passed by add_settings_field().
	 * @see add_settings_field().
	 */
	public function render_dropdown($args) {
		$this->get_field_ids($args, $field_id, $field_name);

		// Retrieve the options that will populate the dropdown
		$dropdown_options = $args['options'];
		if(!is_array($dropdown_options)) {
			throw new InvalidArgumentException(__('Argument "options" must be an array.', $this->_textdomain));
		}

		// Retrieve the selected Option elements
		$selected_options = get_value('selected', $args, array());
		if(!is_array($selected_options)) {
			$selected_options = array($selected_options);
		}

		// Retrieve the HTML attributes
		$attributes = get_value('attributes', $args, array());

		// If we are about to render a multi-select dropdown, add two square brackets
		// so that all selected values will be returned as an array
		// TODO Make search in array case-insensitive
		if(in_array('multiple', $attributes)) {
			$field_name .= '[]';
		}

		$html = '<select ' .
			'id="' . $field_id . '" ' .
			'name="' . $field_name . '" ' .
			$this->attributes_to_string($attributes) .
			'>';
		foreach($dropdown_options as $value => $label) {
			$selected_attr = in_array($value, $selected_options) ? 'selected="selected"' : '';
			$html .= '<option value="' . $value . '" ' . $selected_attr . '>' . $label . '</option>';
		}
		$html .= '</select>';
		echo $html;
	}

	/**
	 * Build the HTML to represent an <input> element.
	 *
	 * @param string type The type of input. It can be text, password, hidden,
	 * checkbox or radio.
	 * @param string field_id The ID of the field.
	 * @param string value The field value.
	 * @param array attribues Additional field attributes.
	 * @param string field_name The name of the field. If unspecified, the field
	 * ID will be taken.
	 * @return string The HTML representation of the field.
	 */
	protected function get_input_html($type, $field_id, $value, $attributes, $field_name = null) {
		$field_name = !empty($field_name) ? $field_name : $field_id;

		$html =
			'<input type="' . $type . '" ' .
			'id="' . $field_id . '" ' .
			'name="' . $field_name . '" ' .
			'value="' . $value . '" ' .
			$this->attributes_to_string($attributes) .
			' />';

		return $html;
	}

	/**
	 * Build the HTML to represent a <textarea> element.
	 *
	 * @param string field_id The ID of the field.
	 * @param string value The field value.
	 * @param array attribues Additional field attributes.
	 * @param string field_name The name of the field. If unspecified, the field
	 * ID will be taken.
	 * @return string The HTML representation of the field.
	 */
	protected function get_textarea_html($field_id, $value, $attributes, $field_name = null) {
		$field_name = !empty($field_name) ? $field_name : $field_id;

		$html =
			'<textarea ' .
			'id="' . $field_id . '" ' .
			'name="' . $field_name . '" ' .
			$this->attributes_to_string($attributes) .
			'>' . $value . '</textarea>';

		return $html;
	}

	/**
	 * Renders a hidden field.
	 *
	 * @param array args An array of arguments passed by add_settings_field().
	 * @see add_settings_field().
	 */
	public function render_hidden($args) {
		$this->get_field_ids($args, $field_id, $field_name);

		// Retrieve the HTML attributes
		$attributes = get_value('attributes', $args, array());
		$value = get_value('value', $args, '');

		echo $this->get_input_html('hidden', $field_id, $value, $attributes, $field_name);
	}

	/**
	 * Renders a text box (input or textarea). To render a textarea, pass an
	 * attribute named "multiline" set to true.
	 *
	 * @param array args An array of arguments passed by add_settings_field().
	 * @see add_settings_field().
	 */
	public function render_textbox($args) {
		$this->get_field_ids($args, $field_id, $field_name);

		// Retrieve the HTML attributes
		$attributes = get_value('attributes', $args, array());
		$value = get_value('value', $args, '');

		$multiline = get_value('multiline', $attributes);

		if($multiline) {
			echo $this->get_textarea_html($field_id, $value, $attributes, $field_name);
		}
		else {
			echo $this->get_input_html('text', $field_id, $value, $attributes, $field_name);
		}
	}

	/**
	 * Renders a checkbox.
	 *
	 * @param array args An array of arguments passed by add_settings_field().
	 * @see add_settings_field().
	 */
	public function render_checkbox($args) {
		$this->get_field_ids($args, $field_id, $field_name);

		// Retrieve the HTML attributes
		$attributes = get_value('attributes', $args, array());

		if(get_value('checked', $attributes, false) == true) {
			$attributes['checked'] = 'checked';
		}
		else {
			unset($attributes['checked']);
		}
		$value = get_value('value', $args, 1);

		echo $this->get_input_html('checkbox', $field_id, $value, $attributes, $field_name);
	}

	/**
	 * Event handler, fired when setting page is loaded.
	 */
	public function options_page_load() {
		if(get_value('settings-updated', $_GET)) {
      //plugin settings have been saved. Display a message, or do anything you like.
			//var_dump('Settings saved.');
		}
	}

	/**
	 * Initialises the settings page.
	 *
	 * @param Aelia\WC\Settings settings_controller The settings controller.
	 */
	public function init_settings_page(\Aelia\WC\Settings $settings_controller) {
		$this->_settings_controller = $settings_controller;
		$this->_settings_key = $this->_settings_controller->settings_key;
		$this->_textdomain = $this->_settings_controller->textdomain;

		$this->add_settings_tabs();
		$this->add_settings_sections();
		$this->add_settings_fields();
	}
}
