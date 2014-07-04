<?php if(!defined('ABSPATH')) exit; // Exit if accessed directly

echo '<h3>';
echo __('Price in specific Currencies', AELIA_CS_PLUGIN_TEXTDOMAIN);
echo '</h3>';
echo '<div>';
echo '<span class="description">';
echo __('Here you can manually specify prices for specific Currencies. If you do so, the prices ' .
				'entered will be used instead of converting the base price using exchange rates. To use ' .
				'exchange rates for one or more prices, simply leave the field empty (the "Auto" value will ' .
				'appear to indicate that price for that currency will be calculated automatically).',
			 AELIA_CS_PLUGIN_TEXTDOMAIN);
echo '</span>';
echo '</div>';
