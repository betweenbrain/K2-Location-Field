<?php defined('_JEXEC') or die;

/**
 * File       location_field.php
 * Created    4/7/14 10:01 AM
 * Author     Matt Thomas | matt@betweenbrain.com | http://betweenbrain.com
 * Support    https://github.com/betweenbrain/
 * Copyright  Copyright (C) 2014 betweenbrain llc. All Rights Reserved.
 * License    GNU GPL v2 or later
 */

// Load the K2 Plugin API
JLoader::register('K2Plugin', JPATH_ADMINISTRATOR . '/components/com_k2/lib/k2plugin.php');

// Initiate class to hold plugin events
class plgK2Location_field extends K2Plugin
{

	// Some params
	var $pluginName = 'location_field';
	var $pluginNameHumanReadable = 'K2 - Location Field';

	function __construct(&$subject, $params)
	{
		parent::__construct($subject, $params);
		$this->app = JFactory::getApplication();
		$this->db  = JFactory::getDbo();
		$this->doc = JFactory::getDocument();

		if ($this->app->isAdmin())
		{
			$this->createLocationsTable();
		}

	}

	/**
	 * Creates the #__k2_items_locations table if it doesn't already exist
	 *
	 * @return bool
	 */
	private function createLocationsTable()
	{
		$prefix = $this->app->getCfg('dbprefix');
		$query  = 'CREATE TABLE IF NOT EXISTS `' . $prefix . 'k2_items_locations` (
						`id`           INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
						`itemId`       INT(11)          NOT NULL,
						`locations`    text             NOT NULL,
						PRIMARY KEY (`id`),
						UNIQUE KEY `itemId` (itemId)
					)
						ENGINE =InnoDB
						AUTO_INCREMENT =0
						DEFAULT CHARSET =utf8;';

		$this->db->setQuery($query);
		$this->db->query();

		return true;
	}

	/**
	 * Function to update the current, newly created K2 item with data retrieved from the detected video provider
	 *
	 * @param $row
	 * @param $isNew
	 */
	function onAfterK2Save(&$row, $isNew)
	{

		if (array_key_exists('location', JRequest::getVar('plugins')))
		{
			$plugins = JRequest::getVar('plugins');

			$locations = $this->getLatandLong($plugins['location']);

			// Update item's plugins data
			$query = 'INSERT INTO ' . $this->db->nameQuote('#__k2_items_locations') . '
					(' . $this->db->nameQuote('itemId') . ',
					' . $this->db->nameQuote('locations') . ')
					VALUES (' . $this->db->Quote($row->id) . ',
					' . $this->db->Quote(json_encode($locations)) . ')
					ON DUPLICATE KEY UPDATE
					' . $this->db->nameQuote('locations') . ' = ' . $this->db->Quote(json_encode($locations));

			$this->db->setQuery($query);
			$this->db->query();

		}
	}

	/**
	 * Fetch latitude and longitude of human readable locations
	 *
	 * @param $locations
	 *
	 * @return array
	 */
	private function getLatandLong($locations)
	{
		$result = array();

		foreach ($locations as $location)
		{

			if ($location == '')
			{
				continue;
			}

			$result[$location] = array();

			// Create curl resource
			$ch = curl_init();

			// Set url
			curl_setopt($ch, CURLOPT_URL, "http://maps.googleapis.com/maps/api/geocode/json?address=" . urlencode($location) . "&sensor=true");

			// Return the transfer as a string
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

			// $output contains the output string
			$output = json_decode(curl_exec($ch));

			// Close curl resource to free up system resources
			curl_close($ch);

			$result[$location]['lat'] = $output->results[0]->geometry->location->lat;
			$result[$location]['lng'] = $output->results[0]->geometry->location->lng;
		}

		return $result;
	}

	/**
	 * Add plugin fields to the item object for direct access in the template
	 *
	 * @param $item
	 * @param $params
	 * @param $limitstart
	 */
	function onK2PrepareContent(&$item, &$params, $limitstart)
	{
		// Get the K2 plugin fields
		$fields = new K2Parameter($item->plugins, '', $this->pluginName);

		// Add plugin fields to $item object
		$item->videoProvider = htmlspecialchars($fields->get('videoProvider'));
		$item->videoID       = htmlspecialchars($fields->get('videoID'));
		$item->videoImage    = htmlspecialchars($fields->get('videoImageUrl'));
		$item->videoDuration = htmlspecialchars($fields->get('videoDuration'));
		$item->videoWidth    = htmlspecialchars($fields->get('videoWidth'));
		$item->videoHeight   = htmlspecialchars($fields->get('videoHeight'));
		$item->videoPlayer   = htmlspecialchars($fields->get('videoPlayer'));
	}
}
