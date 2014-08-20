/* JavaScript for Admin pages */
jQuery(document).ready(function($) {
	/**
	 * Adds a new row to the tax display settings table.
	 */
	function add_row() {
		// Create a clone of the row template, which will be inserted into the list
		var $new_row = $template_row.clone();
		var new_row_index = $tax_display_settings.find('tbody tr.data').length;
		// Replace the "X" placeholder in input field attributes with the index of
		// the new row
		$new_row.find('.input').each(function() {
			var $input = $(this);
			// Field ID
			var input_id = $input.attr('id').replace('_X', '_' + new_row_index);
			$input.attr('id', input_id);
			// Field name
			var input_name = $input.attr('name').replace('[X]', '[' + new_row_index + ']');
			$input.attr('name', input_name);
		});

		if(jQuery().chosen) {
			$new_row.find('.countries select').chosen();
		}
		$tax_display_settings.find('tbody').append($new_row);
	}

	var $settings_form = $('#wc_aelia_tax_display_by_country_form');
	// If form is not found, we are not on this plugin's setting page
	if(!$settings_form.length) {
		return;
	}

	// Display tabbed interface
	$settings_form.find('.tabs').tabs();

	// Create a clone of the template row, which will be used to add new rows to
	// the settings
	var $tax_display_settings = $('#tax_display_settings');
	var $template_row = $tax_display_settings.find('tbody .template')
		.clone()
		.removeClass('template')
		.addClass('data');
	$tax_display_settings.find('tbody .template').remove();

	// Handle insertion of new rows
	$tax_display_settings.find('tfoot .insert').on('click', function(){
		add_row();

		return false;
	});

	// Handle removal of rows
	$tax_display_settings.delegate('tbody .remove', 'click', function(){
		var $row = $(this).closest('tr');
		if($row.length <= 0) {
			return false;
		}
		// Remove the selected row
		$row.remove();

		if($tax_display_settings.find('tbody tr.data').length <=0) {
			add_row();
		}
		return false;
	});

	// Allow to sort the elements
	$('#tax_display_settings tbody').sortable({
		handle: '.handle',
		items: 'tr',
		cursor: 'move',
		axis: 'y',
		scrollSensitivity:40,
		forcePlaceholderSize: true,
		helper: 'clone',
		opacity: 0.65,
		placeholder: 'wc-metabox-sortable-placeholder',
		start:function(event,ui){
			ui.item.css('background-color','#f6f6f6');
		},
		stop:function(event,ui){
			ui.item.removeAttr('style');
		}
	});

	// Use Chosen plugin to replace standard multiselect
	if(jQuery().chosen) {
		// Multiselect for enabled currencies
		$settings_form
			.find('.countries select')
			.chosen();
	}

	// Add an empty row if no rows exist in the table. Such row will be useful for
	// the admin to get started
	if($tax_display_settings.find('tbody tr.data').length <=0) {
		add_row();
	}

	// TODO Add script to automatically populate a field with all European countries
});
