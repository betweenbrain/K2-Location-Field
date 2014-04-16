<?php defined('_JEXEC') or die;

/**
 * File       location.php
 * Created    4/7/14 10:24 AM
 * Author     Matt Thomas | matt@betweenbrain.com | http://betweenbrain.com
 * Support    https://github.com/betweenbrain/
 * Copyright  Copyright (C) 2014 betweenbrain llc. All Rights Reserved.
 * License    GNU GPL v2 or later
 */
class JElementLocation extends JElement
{

	var $_name = 'Location';

	/**
	 * Construct
	 */
	public function __construct()
	{
		$this->app = JFactory::getApplication();
		$this->db  = JFactory::getDbo();
		$this->doc = JFactory::getDocument();
		$this->addScripts();
	}

	/**
	 * Fetched the element to display on the page
	 *
	 * @param $name
	 * @param $value
	 * @param $node
	 * @param $control_name
	 *
	 * @return null|string|void
	 */
	function fetchElement($name, $value, &$node, $control_name)
	{
		$class = $node->attributes('class') ? $node->attributes('class') : "text_area";

		$return = null;

		$query = ' SELECT locations' .
			' FROM #__k2_items_locations' .
			' WHERE itemId = ' . $this->db->Quote(JRequest::getVar('cid')) . '';
		$this->db->setQuery($query);

		if ($this->db->loadResult() != null)
		{
			$locations = json_decode($this->db->loadResult(), true);
		}
		else
		{
			$locations['birth'][]   = '';
			$locations['primary'][] = '';
		}

		if (!is_array($locations))
		{
			$locations = str_split($locations, strlen($locations));
		}

		$i = 0;

		foreach ($locations as $type => $locales)
		{

			foreach ($locales as $name => $latlng)
			{

				$return .= '<fieldset class="' . $class . '">';
				$return .= '<label>' . ucfirst($type) . '</label>';
				$return .= '<input type="text" style="display:block"' .
					'name="' . $control_name . '[' . $node->attributes('type') . '][' . $type . '][' . $i . ']"' .
					'value="' . $name . '" />';
				$return .= '</fieldset>';

				$i++;
			}
		}

		return $return;

	}

	/**
	 * Adds necessary JavaScript to page for repeatable field
	 *
	 * @return null
	 */
	private function addScripts()
	{

		$js = "<script>
// Clone last clonable fieldset and increment array value
(function ($) {
	$(document).ready(function() {

	$('.clonable:last').after('<input type=\"button\" class=\"clone\" data-type=\"primary\" value=\"Add Primary\" /><input type=\"button\" class=\"clone\" data-type=\"birth\" value=\"Add Birth\" />');

	var i = $('.clonable:last input').attr('name').match(/\d/);
	$('input.clone').on('click', function (event) {
		i++;
		$('.clonable:first').clone().insertAfter('.clonable:last');
		$('.clonable:last input').val('');
		var last = $('.clonable:last input').attr('name');
			var nodeValue = $('.clonable:last input').attr('name').match(/\d/),
				newValue = $('.clonable:last input').attr('name').replace(nodeValue, i);
		$('.clonable:last input').attr('name', newValue);

		if($(this).data('type') == 'birth'){
			$('.clonable:last label').text('Birth');
			var newValue = $('.clonable:last input').attr('name').replace(/primary/gi, 'birth');
			$('.clonable:last input').attr('name', newValue);
		}

		event.preventDefault();
		});

	}); // end document ready
})(jQuery) // End self-invoking anonymous function
</script>";

		$this->doc->addCustomTag($js);
	}
}
