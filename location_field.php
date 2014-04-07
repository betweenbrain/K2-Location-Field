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

	function plgK2Location_field(& $subject, $params)
	{
		parent::__construct($subject, $params);
	}

	/**
	 * Function to return video data based on detected video provider
	 *
	 * @param $row
	 *
	 * @internal param $item
	 * @return mixed
	 */
	private function getVideoData($row)
	{
		// Get the K2 plugin fields
		$fields        = new K2Parameter($row->plugins, '', $this->pluginName);
		$videoProvider = $fields->get('videoProvider');
		$videoID       = $fields->get('videoID');

		// Get the K2 plugin parameters
		$plugin          =& JPluginHelper::getPlugin('k2', $this->pluginName);
		$params          = new JParameter($plugin->params);
		$brightcovetoken = htmlspecialchars($params->get('brightcovetoken'));

		// Check if Brightcove is in the K2 provider field or Media source field embed code
		if ((strtolower($videoProvider) === 'brightcove') || strstr($row->video, 'brightcove'))
		{
			if (!$videoProvider)
			{
				preg_match('/@videoPlayer" value="([0-9]*)"/', $row->video, $match);
				$videoID = $match[1];
			}

			$json    = file_get_contents('http://api.brightcove.com/services/library?command=find_video_by_id&video_id=' . $videoID . '&video_fields=name,shortDescription,longDescription,publishedDate,lastModifiedDate,videoStillURL,length,playsTotal&token=' . $brightcovetoken);
			$results = json_decode($json);

			if ($results)
			{
				$videoData['title']             = $results->name;
				$videoData['description_long']  = $results->longDescription;
				$videoData['description_short'] = $results->shortDescription;
				$videoData['image']             = $results->videoStillURL;
				$videoData['views']             = $results->playsTotal;
				$videoData['duration']          = floor($results->length / (1000 * 60)) . ':' . sprintf("%02d", round(($results->length % (1000 * 60) / 1000)));
				// Convert Brightcove date (milliseconds since UNIX epoch) to MySQL datetime format
				$videoData['date_published'] = date('Y-m-d H:i:s', $results->publishedDate / 1000);
				$videoData['date_modified']  = date('Y-m-d H:i:s', $results->lastModifiedDate / 1000);

				//die('<pre>' . print_r($videoData, TRUE) . '</pre>');

				return $videoData;
			}
		}

		// If YouTube is enabled and in the Media source field embed code
		// TODO: Test use of embed code
		if ((strtolower($videoProvider) === 'youtube') || strstr($row->video, 'youtube'))
		{
			if (!$videoProvider)
			{
				preg_match('/\/embed\/([a-zA-Z0-9_-]*)(\?|")/', $row->video, $match);
				$videoID = $match[1];
			}

			$json = file_get_contents('https://gdata.youtube.com/feeds/api/videos/' . $videoID . '?v=2&alt=json');
			// https://developers.google.com/youtube/v3/docs/videos/list
			// i.e. https://www.googleapis.com/youtube/v3/videos/?id=QfOF0bRBFJ4&part=contentDetails
			$results = json_decode($json, true);

			// Build the videoData array from the data from YouTube
			if ($results)
			{
				$videoData['title']            = $results['entry']['title']['$t'];
				$videoData['description_long'] = $results['entry']['media$group']['media$description']['$t'];
				$videoData['image']            = 'http://i.ytimg.com/vi/' . $videoID . '/sddefault.jpg';
				$videoData['views']            = $results['entry']['yt$statistics']['viewCount'];
				// TODO: Check number formatting
				$videoData['duration'] = $results['entry']['media$group']['media$duration'];
				// Convert YouTube date (UTC) to MySQL datetime format
				$videoData['date_published'] = date('Y-m-d H:i:s', strtotime($results['entry']['published']['$t']));
				$videoData['date_modified']  = date('Y-m-d H:i:s', strtotime($results['entry']['updated']['$t']));

				return $videoData;
			}
		}

		return false;
	}

	/**
	 * Function to update the current, newly created K2 item with data retrieved from the detected video provider
	 *
	 * @param $row
	 * @param $isNew
	 */
	function onBeforeK2Save(&$row, $isNew)
	{

		$app =& JFactory::getApplication();

		if ($app->isAdmin() && $isNew)
		{

			// Retrieve video data from the provider
			$videoData = $this->getVideoData($row);

			// If data is retrieved, update K2 item
			if ($videoData)
			{
				$row->title     = $videoData['title'];
				$row->alias     = JFilterOutput::stringURLSafe($videoData['title']);
				$row->introtext = $videoData['description_short'];
				$row->fulltext  = $videoData['description_long'];
				$row->created   = $videoData['date_published'];
				$row->modified  = $videoData['date_modified'];
				$row->hits      = $videoData['views'];
			}
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