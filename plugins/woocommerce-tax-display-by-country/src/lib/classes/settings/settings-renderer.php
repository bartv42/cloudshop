<?php
namespace Aelia\WC\TaxDisplayByCountry;
if(!defined('ABSPATH')) exit; // Exit if accessed directly

/**
 * Implements a class that will render the settings page.
 */
class Settings_Renderer extends \Aelia\WC\Settings_Renderer {
	// @var string The URL to the support portal.
	const SUPPORT_URL = 'http://aelia.freshdesk.com/support/home';
	// @var string The URL to the contact form for general enquiries.
	const CONTACT_URL = 'http://aelia.co/contact/';

	/*** Settings Tabs ***/
	const TAB_TAX_DISPLAY = 'tax_display';
	const TAB_SUPPORT = 'support';
	const TAB_DOCUMENTATION = 'documentation';

	/*** Settings sections ***/
	const SECTION_TAX_DISPLAY = 'tax_display';
	const SECTION_SUPPORT = 'support_section';
	const SECTION_USAGE = 'usage_section';

	/**
	 * Returns a list of the options available to display prices (with our
	 * without tax).
	 *
	 * @return array
	 */
	protected function tax_display_options() {
		return array(
			'incl' => __('Including tax', 'woocommerce'),
			'excl'   => __( 'Excluding tax', 'woocommerce' ),
		);
	}

	/**
	 * Sets the tabs to be used to render the Settings page.
	 */
	protected function add_settings_tabs() {
		// General settings
		$this->add_settings_tab($this->_settings_key,
														self::TAB_TAX_DISPLAY,
														__('Tax Display', $this->_textdomain));
		// Documentation tab
		$this->add_settings_tab($this->_settings_key,
														self::TAB_DOCUMENTATION,
														__('Documentation', $this->_textdomain));
		// Support tab
		$this->add_settings_tab($this->_settings_key,
														self::TAB_SUPPORT,
														__('Support', $this->_textdomain));
	}

	/**
	 * Configures the plugin settings sections.
	 */
	protected function add_settings_sections() {
		// Add Tax Display settings section
		$this->add_settings_section(
				self::SECTION_TAX_DISPLAY,
				__('Tax Display', $this->_textdomain),
				array($this, 'tax_display_settings_section_callback'),
				$this->_settings_key,
				self::TAB_TAX_DISPLAY
		);

		// Add Documentation section
		$this->add_settings_section(
				self::SECTION_USAGE,
				'', // Title is not needed in this case, it will be displayed in the section callback
				array($this, 'usage_section_callback'),
				$this->_settings_key,
				self::TAB_DOCUMENTATION
		);

		// Add Support section
		$this->add_settings_section(
				self::SECTION_SUPPORT,
				__('Support Information', $this->_textdomain),
				array($this, 'support_section_callback'),
				$this->_settings_key,
				self::TAB_SUPPORT
		);
	}

	/**
	 * Configures the plugin settings fields.
	 */
	protected function add_settings_fields() {
		// Tax Display Settings

		// Add "Tax display settings" field
		$tax_display_settings_field_id = Settings::FIELD_TAX_DISPLAY_SETTINGS;
		$tax_display_settings = $this->current_settings($tax_display_settings_field_id, $this->default_settings($tax_display_settings_field_id, array()));
		// Add "tax display settings" table
		add_settings_field(
			$tax_display_settings_field_id,
			__('Set the tax display settings for the countries.', $this->_textdomain),
			array($this, 'render_tax_display_settings_options'),
			$this->_settings_key,
			self::SECTION_TAX_DISPLAY,
			array(
				'settings_key' => $this->_settings_key,
				'id' => $tax_display_settings_field_id,
				'label_for' => $tax_display_settings_field_id,
				// Input field attributes
				'attributes' => array(
					'class' => $tax_display_settings_field_id,
				),
			)
		);
	}

	/**
	 * Returns the title for the menu item that will bring to the plugin's
	 * settings page.
	 *
	 * @return string
	 */
	protected function menu_title() {
		return __('Tax Display by Country', $this->_textdomain);
	}

	/**
	 * Returns the slug for the menu item that will bring to the plugin's
	 * settings page.
	 *
	 * @return string
	 */
	protected function menu_slug() {
		return Definitions::MENU_SLUG;
	}

	/**
	 * Returns the title for the settings page.
	 *
	 * @return string
	 */
	protected function page_title() {
		return __('Tax Display by Country - Settings', $this->_textdomain) .
					 sprintf('&nbsp;(v. %s)', WC_Aelia_Tax_Display_By_Country::$version);
	}

	/**
	 * Returns the description for the settings page.
	 *
	 * @return string
	 */
	protected function page_description() {
		return __('In this page you can configure the settings for the Tax Display by ' .
							'Country plugin. ' .
							'Using the interface below, you will be able to specify how prices will ' .
							'be displayed, based on customer\'s country.',
							$this->_textdomain);
	}

	/*** Settings sections callbacks ***/
	public function tax_display_settings_section_callback() {
		echo __('In this section you can configure how the prices will be displayed ' .
						'(with our without taxes) for the various countries.', $this->_textdomain);
		echo '&nbsp;';
		echo __('You can reorder the settings by dragging the handle on the left. Please ' .
						'keep in mind that, if you add the same country to multiple rows, the settings ' .
						'will be retrieved <u>from the first matching one</u>.',
						$this->_textdomain);

		echo '<noscript>';
		echo __('This page requires JavaScript to work properly. Please enable JavaScript ' .
						'in your browser and refresh the page.</u>.',
						$this->_textdomain);
		echo '</noscript>';
	}

	public function usage_section_callback() {
		echo '<h3>';
		echo __('How to display the billing country selector widget', $this->_textdomain);
		echo '</h3>';
		echo '<p>';
		echo __('The billing country selector widget allows your visitors to choose the billing ' .
						'country before they reach the checkout. The Tax Display by Country plugin will ' .
						'detect the choice and display the prices with our without tax, depending on the ' .
						'setting you entered. To display the widget, ' .
						'you have the following options:', $this->_textdomain);
		echo '</p>';

		echo '<ol>';
		echo '<li>';
		echo '<h4>' . __('Using WordPress Widgets', $this->_textdomain) . '</h4>';
		echo '</h4>';
		echo '<p>';
		echo __('Go to <i>Appearance > Widgets</i>. There you will see a widget named ' .
						'"<strong>WooCommerce Tax Display by Country - Billing Country Selector'.
						'</strong>". Drag and drop it in a widget area, select a title and a ' .
						'widget type and click on "Save". ' .
						'The widget will now appear on the frontend of your shop, in the area where ' .
						'you dropped it.', $this->_textdomain);
		echo '</p>';
		echo '</li>';
		echo '<li>';

		echo '<h4>' . __('Using a shortcode', $this->_textdomain) . '</h4>';
		echo '</h4>';
		echo '<p>';
		echo __('You can display the country selector widget using the following shortcode ' .
						'anywhere in your pages: ', $this->_textdomain);
		echo '</p>';
		echo '<code>[aelia_tdbc_billing_country_selector_widget title="Widget title (optional)" widget_type="dropdown"]</code>';
		echo '<p>';
		echo __('The shortcode accepts the following parameters:', $this->_textdomain);
		echo '</p>';

		// Shortcode parameters
		echo '<ul>';
		echo '<li>';
		echo '<span class="label"><code>title</code></span>&nbsp;';
		echo '<span>' . __('The widget title (optional)', $this->_textdomain) . '</span>';
		echo '</li>';
		echo '<li>';
		echo '<span class="label"><code>widget_type</code></span>&nbsp;';
		echo '<span>' . __('The widget type. Out of the box, the widget supports only <code>dropdown</code>. ' .
											 'Further types can be added by implementing a filter ' .
											 'in your theme for <code>wc_aelia_tdbc_billing_country_selector_widget_types</code> hook. ' .
											 'If this parameter is not specified, <code>dropdown</code> widget type will be ' .
											 'rendered by default.', $this->_textdomain) . '</span>';
		echo '</li>';
		echo '</ul>';

		echo '</li>';
		echo '</ol>';

		echo '<h3>';
		echo __('How to customise the look and feel of the billing country selector widget', $this->_textdomain);
		echo '</h3>';
		echo '<p>';
		echo __('The country selector widget is rendered using template files that can be ' .
						'found in <code>' . WC_Aelia_Tax_Display_By_Country::instance()->path('plugin') .
						'/views</code> folder. The following standard templates are available:',
						$this->_textdomain);
		echo '</p>';

		echo '<ul>';
		echo '<li>';
		echo '<code>billing-country-selector-widget-dropdown.php</code>: ' . __('displays "dropdown" style selector.', $this->_textdomain);
		echo '</li>';
		echo '</ul>';

		echo '<p>';
		echo __('If you wish to alter the templates, simply copy them in your theme. ' .
						'They should be put in <code>{your theme folder}/' . WC_Aelia_Tax_Display_By_Country::$plugin_slug .
						'/</code> and have the same name of the original files. The Tax Display by Country ' .
						'plugin will then load them automatically instead of the default ones.', $this->_textdomain);
		echo '</p>';
		echo '<p>';
		echo __('The CSS styles that apply to the standard layouts for the Country Selector ' .
						'widget can be found in our knowledge base: ' .
						'<a href="https://aelia.freshdesk.com/solution/articles/3000007967-how-can-i-customise-the-look-and-feel-of-the-billing-country-selector-widget-">' .
						'How can I customise the look and feel of the Billing Country Selector widget?</a>.',
						$this->_textdomain);
		echo '</p>';
	}

	public function support_section_callback() {
		echo '<div class="support_information">';
		echo '<p>';
		echo __('We designed this plugin to be robust and effective, ' .
						'as well as intuitive and easy to use. However, we are aware that, despite ' .
						'all best efforts, issues can arise and that there is always room for ' .
						'improvement.',
						$this->_textdomain);
		echo '</p>';
		echo '<p>';
		echo __('Should you need assistance, or if you just would like to get in touch ' .
						'with us, please use one of the links below.',
						$this->_textdomain);
		echo '</p>';

		// Support links
		echo '<ul id="contact_links">';
		echo '<li>' . sprintf(__('<span class="label">To request support</span>, please use our <a href="%s">Support portal</a>. ' .
														 'The portal also contains a Knowledge Base, where you can find the ' .
														 'answers to the most common questions related to our products.',
														 $this->_textdomain),
													self::SUPPORT_URL) . '</li>';
		echo '<li>' . sprintf(__('<span class="label">To send us general feedback</span>, suggestions, or enquiries, please use ' .
														 'the <a href="%s">contact form on our website.</a>',
														 $this->_textdomain),
													self::CONTACT_URL) . '</li>';
		echo '</ul>';

		echo '</div>';
	}

	/*** Rendering methods ***/
	/**
	 * Renders a table containing several fields that Admins can use to configure
	 * how prices will be displayed for visitors from various countries.
	 *
	 * @param array args An array of arguments passed by add_settings_field().
	 * @see add_settings_field().
	 */
	public function render_tax_display_settings_options($args) {
		global $woocommerce;

		function render_actions_column($textdomain) {
			$result = '<td class="actions">';
			$result .= '<a href="#" class="button minus remove">';
			$result .= __('Remove', $textdomain);
			$result .= '</a>';
			$result .=  '</td>';

			return $result;
		}

		$this->get_field_ids($args, $tax_display_field_id, $tax_display_field_name);

		// TODO Add elements that can be cloned as new rows, with all the required fields
		$html = '<table id="tax_display_settings">';
		// Table header
		$html .= '<thead>';
		$html .= '<tr>';
		$html .= '<td class="sort">&nbsp;</td>';
		$html .= '<td>' . __('Countries', $this->_textdomain) . '</td>';
		$html .= '<td class="cart_prices">' . __('Show cart prices', $this->_textdomain) . '</td>';
		// WooCommerce 2.1+ settings
		if(version_compare($woocommerce->version, '2.1', '>=')) {
			$html .= '<td class="shop_prices">' . __('Show shop prices', $this->_textdomain) . '</td>';
			$html .= '<td class="price_suffix">' . __('Price suffix', $this->_textdomain) . '</td>';
		}
		$html .= '<td class="actions">&nbsp;</td>';
		$html .= '</tr>';
		$html .= '</thead>';
		$html .= '<tbody>';

		// Template row
		$html .= '<tr class="template">';
		$html .= '<td class="sort handle">&nbsp;</td>';
		// Render countries list field
		$html .= '<td class="countries">';
		$field_args = array(
			'id' => $tax_display_field_id . '_X[countries]',
			'name' => $tax_display_field_name . '[X][countries]',
			'selected' => array(),
			'options' => $woocommerce->countries->get_allowed_countries(),
			'attributes' => array(
				'class' => 'input',
				'multiple' => 'multiple',
			),
		);
		ob_start();
		$this->render_dropdown($field_args);
		$field_html = ob_get_contents();
		ob_end_clean();
		$html .= $field_html;
		$html .= '</td>';

		// Render the "cart prices" tax display field
		$html .= '<td class="cart_prices">';
		$field_args = array(
			'id' => $tax_display_field_id . '_X[cart_prices]',
			'name' => $tax_display_field_name . '[X][cart_prices]',
			'selected' => 'incl',
			'options' => $this->tax_display_options(),
			'attributes' => array(
				'class' => 'input tax_display_type',
			),
		);
		ob_start();
		$this->render_dropdown($field_args);
		$field_html = ob_get_contents();
		ob_end_clean();
		$html .= $field_html;
		$html .= '</td>';

		// WooCommerce 2.1+ settings
		if(version_compare($woocommerce->version, '2.1', '>=')) {
			// Render the "shop prices" tax display field
			$html .= '<td class="shop_prices">';
			$field_args = array(
				'id' => $tax_display_field_id . '_X[shop_prices]',
				'name' => $tax_display_field_name . '[X][shop_prices]',
				'selected' => 'incl',
				'options' => $this->tax_display_options(),
				'attributes' => array(
					'class' => 'input tax_display_type',
				),
			);
			ob_start();
			$this->render_dropdown($field_args);
			$field_html = ob_get_contents();
			ob_end_clean();
			$html .= $field_html;
			$html .= '</td>';

			// Render the "price suffix" field
			$html .= '<td class="price_suffix">';
			$field_args = array(
				'id' => $tax_display_field_id . '_X[price_suffix]',
				'name' => $tax_display_field_name . '[X][price_suffix]',
				'value' => '',
				'attributes' => array(
					'class' => 'input price_suffix',
					'placeholder' => __('Use default', $this->_textdomain),
				),
			);
			ob_start();
			$this->render_textbox($field_args);
			$field_html = ob_get_contents();
			ob_end_clean();
			$html .= $field_html;
			$html .= '</td>';
		}
		// Render action column
		$html .= render_actions_column($this->_textdomain);
		$html .= '</tr>';
		// Template row - End

		$tax_display_settings = $this->current_settings('tax_display_settings');

		if(!is_array($tax_display_settings)) {
			$tax_display_settings = array();
		}

		foreach($tax_display_settings as $index => $settings) {
			$html .= '<tr class="data">';
			$html .= '<td class="sort">&nbsp;</td>';

			// Render countries list field
			$html .= '<td class="countries">';
			$field_args = array(
				'id' => $tax_display_field_id . '_' . $index . '[countries]',
				'name' => $tax_display_field_name . '[' . $index . '][countries]',
				'selected' => get_value('countries', $tax_display_settings[$index], array()),
				'options' => $woocommerce->countries->get_allowed_countries(),
				'attributes' => array(
					'class' => 'input',
					'multiple' => 'multiple',
				),
			);
			ob_start();
			$this->render_dropdown($field_args);
			$field_html = ob_get_contents();
			ob_end_clean();
			$html .= $field_html;
			$html .= '</td>';

			// Render the "cart prices" tax display field
			$html .= '<td class="cart_prices">';
			$field_args = array(
				'id' => $tax_display_field_id . '_' . $index . '[cart_prices]',
				'name' => $tax_display_field_name . '[' . $index . '][cart_prices]',
				'selected' => get_value('cart_prices', $tax_display_settings[$index], array()),
				'options' => $this->tax_display_options(),
				'attributes' => array(
					'class' => 'input tax_display_type',
				),
			);
			ob_start();
			$this->render_dropdown($field_args);
			$field_html = ob_get_contents();
			ob_end_clean();
			$html .= $field_html;
			$html .= '</td>';

			// WooCommerce 2.1+ settings
			if(version_compare($woocommerce->version, '2.1', '>=')) {
				// Render the "shop prices" tax display field
				$html .= '<td class="shop_prices">';
				$field_args = array(
					'id' => $tax_display_field_id . '_' . $index . '[shop_prices]',
					'name' => $tax_display_field_name . '[' . $index . '][shop_prices]',
					'selected' => get_value('shop_prices', $tax_display_settings[$index], array()),
					'options' => $this->tax_display_options(),
					'attributes' => array(
						'class' => 'input tax_display_type',
					),
				);
				ob_start();
				$this->render_dropdown($field_args);
				$field_html = ob_get_contents();
				ob_end_clean();
				$html .= $field_html;
				$html .= '</td>';

				// Render the "price suffix"  field
				$html .= '<td class="price_suffix">';
				$field_args = array(
					'id' => $tax_display_field_id . '_' . $index . '[price_suffix]',
					'name' => $tax_display_field_name . '[' . $index . '][price_suffix]',
					'value' => get_value('price_suffix', $tax_display_settings[$index], ''),
					'attributes' => array(
						'class' => 'input price_suffix',
						'title' => __('This price suffix will override the one configured by default in ' .
													'WooCommerce settings. Leave it empty to use the default one.',
													$this->_textdomain),
						'placeholder' => __('Use default', $this->_textdomain),
					),
				);
				ob_start();
				$this->render_textbox($field_args);
				$field_html = ob_get_contents();
				ob_end_clean();
				$html .= $field_html;
				$html .= '</td>';			}
			// Render action column
			$html .= render_actions_column($this->_textdomain);
			$html .= '</tr>';
		}
		$html .= '</tbody>';

		// Table footer
		$html .= '
			<tfoot>
				<tr>
					<th colspan="5">
						<a href="#" class="button plus insert">Insert row</a>
					</th>
				</tr>
			</tfoot>
		';
		$html .= '</table>';

		echo $html;
	}
}
