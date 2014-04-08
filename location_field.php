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
	function onBeforeK2Save(&$row, $isNew)
	{

		if (array_key_exists('location', JRequest::getVar('plugins')))
		{
			$plugins = JRequest::getVar('plugins');

			// Update item's plugins data
			$query = 'INSERT INTO ' . $this->db->nameQuote('#__k2_items_locations') . '
					(' . $this->db->nameQuote('itemId') . ',
					' . $this->db->nameQuote('locations') . ')
					VALUES (' . $this->db->Quote($row->id) . ',
					' . $this->db->Quote(json_encode($plugins['location'])) . ')
					ON DUPLICATE KEY UPDATE
					' . $this->db->nameQuote('locations') . ' = ' . $this->db->Quote(json_encode($plugins['location']));

			$this->db->setQuery($query);
			$this->db->query();

		}
	}

	/**
	 * Function to progrmatically populate the plugin's videoImageUrl and videoDuration fields after first save
	 *
	 * @param $row
	 * @param $isNew
	 */
	function onAfterK2Save(&$row, $isNew)
	{

		$app =& JFactory::getApplication();

		if ($app->isAdmin() && $isNew)
		{

			// Retrieve video data from the provider
			$videoData = $this->getVideoData($row);

			if (is_null($videoData))
			{
				//die('<pre>' . print_r($row, TRUE) . '</pre>');
			}

			// Fetch item's exisitng plugins data
			$db    = JFactory::getDbo();
			$query = ' SELECT plugins' .
				' FROM #__k2_items' .
				' WHERE id = ' . $db->Quote($row->id) . '';
			$db->setQuery($query);
			$params = $db->loadResult();

			// Check for no user entry of videoImageUrl on first creation
			if (preg_match('/location_fieldvideoImageUrl=\s/', $params))
			{
				// Strip empty entry on first save
				$params = str_replace('location_fieldvideoImageUrl=', '', $params);
				$params = 'location_fieldvideoImageUrl=' . $videoData['image'] . "\n" . $params;
			}

			// Check for no user entry of videoDuration on first creation
			if (preg_match('/location_fieldvideoDuration=\s/', $params))
			{
				// Strip empty entry on first save
				$params = str_replace('location_fieldvideoDuration=', '', $params);
				$params = 'location_fieldvideoDuration=' . $videoData['duration'] . "\n" . $params;
			}

			// Update item's plugins data
			$query = 'UPDATE #__k2_items' .
				' SET plugins=' . $db->Quote($params) .
				' WHERE id = ' . $db->Quote($row->id) . '';
			$db->setQuery($query);
			$db->query();
		}
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