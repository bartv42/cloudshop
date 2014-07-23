<?php if(!defined('ABSPATH')) exit; // Exit if accessed directly

// $text_domain is loaded in the calling view

echo '<h3>';
echo __('Subscription price in specific Currencies', $text_domain);
echo '</h3>';
echo '<div>';
echo '<span class="description">';
echo __('Here you can manually specify prices for specific currencies. If you do so, the prices ' .
				'entered will be used instead of converting the base price using exchange rates. To use ' .
				'exchange rates for one or more prices, simply leave the field empty (the "Auto" value will ' .
				'appear to indicate that price for that currency will be calculated automatically).',
			 $text_domain);
echo '<br />';
echo __('<strong>Note</strong>: the billing period will each subscription will not change. ' .
				'For example, if you specify "per day" above, the same rule will apply to every ' .
				'price in specific currencies (e.g. 10 USD per day, 7 EUR per day, 5 GBP per day, etc).',
			 $text_domain);
echo '</span>';
echo '</div>';
