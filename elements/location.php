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

	public function __construct()
	{
		$this->app = JFactory::getApplication();
		$this->db  = JFactory::getDbo();
		$this->doc = JFactory::getDocument();
		$this->addScripts();
	}

	function fetchElement($name, $value, &$node, $control_name)
	{
		$class = $node->attributes('class') ? $node->attributes('class') : "text_area";

		$return = null;

		if (is_array($value))
		{
			foreach ($value as $k => $v)
			{
				$return .= '<input type="text"' .
					'name="' . $control_name . '[' . $name . '][' . $k . ']"' .
					'id="' . $control_name . '[' . $name . ']"' .
					'value="' . $v . '"' .
					'class="' . $class . '" />';
			}

		}
		else
		{
			$return = '<input type="text"' .
				'name="' . $control_name . '[' . $name . '][0]"' .
				'id="' . $control_name . '[' . $name . ']"' .
				'value="' . $value . '"' .
				'class="' . $class . '" />';
		}

		return $return;

	}

	private function addScripts()
	{

		$js = "<script>
// Clone last clonable fieldset and increment array value
(function ($) {
	$(document).ready(function() {

	$('.clonable:last').after('<input type=\"button\" class=\"clone\" value=\"+\" />');

	var i = $('.clonable:last').attr('name').match(/\d/);
	$('input.clone').on('click', function (event) {
		i++;
		$('.clonable:first').clone().insertAfter('.clonable:last');
		$('.clonable:last').val('');
		var last = $('.clonable:last').attr('name');
			var nodeValue = $('.clonable:last').attr('name').match(/\d/),
				newValue = $('.clonable:last').attr('name').replace(nodeValue, i);
		$('.clonable:last').attr('name', newValue);

		event.preventDefault();
		});

	}); // end document ready
})(jQuery) // End self-invoking anonymous function
</script>";

		$this->doc->addCustomTag($js);
	}
}