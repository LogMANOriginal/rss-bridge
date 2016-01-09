<?php
class YoutubeApiBridge extends BridgeAbstract {
	private $name;

	const NAME = 'YouTube API Bridge';
	const URI = 'https://www.youtube.com/';
	const MAINTAINER = 'logmanoriginal';
	const DESCRIPTION = 'Returns the newest videos by channel name or playlist id using the Youtube Data API';
	const PARAMETERS = array(
		'global' => array(
			'key' => array(
				'name' => 'YouTube Data API key',
				'type' => 'text',
				'required' => true,
				'title' => 'Insert your Data API key here!'
			),
			'limit' => array(
				'name' => 'Limit',
				'type' => 'number',
				'required' => false,
				'title' => 'Specifies the number of items to return for each request',
				'defaultValue' => 10
			)
		),
		'Get channel with limit' => array(
			'channel' => array(
				'name' => 'Channel',
				'type' => 'text',
				'required' => true,
				'title' => 'Insert channel name here!',
				'exampleValue' => 'YouTube'
			)
		),
		'Get playlist' => array(
			'playlist_id' => array(
				'name' => 'Playlist ID',
				'type' => 'text',
				'required' => 'true',
				'title' => 'Insert playlist ID here!',
				'exampleValue' => 'PLbpi6ZahtOH5v1L8oiDSetlj5TTM7tY7N'
			)
		)
	);

	public function getName(){
		switch($this->queriedContext){
		case 'Get channel with limit':
			return $this->getInput('channel') . ' - YouTube API Bridge';
			break;
		case 'Get playlist':
			return $this->name . ' - YouTube API Bridge';
			break;
		default:
			return parent::getName();
		}
	}

	public function collectData(){
		$apiKey = $this->getInput('key');
		$limit = $this->getInput('limit');

		switch($this->queriedContext){
		case 'Get channel with limit':
			$channel = $this->getInput('channel');
			$this->add_channel($channel, $apiKey, $limit);
			break;
		case 'Get playlist':
			$playlistId = $this->getInput('playlist_id');
			$this->add_playlist($playlistId, $apiKey, $limit);
			break;
		default:
			returnClientError('Unknown context \'' . $this->queriedContext . '\'');
		}
	}

	private function add_channel($channel, $apiKey, $limit = 10){
		$request = 'https://www.googleapis.com/youtube/v3/channels?part=contentDetails&forUsername='
		. urlencode($channel)
		. '&key='
		. $apiKey;

		$json = file_get_contents($request);
		if($json === false)
			returnServerError('Request failed for request: ' . $request . '!');

		$channels = json_decode($json);
		$playlistId = $channels->items[0]->contentDetails->relatedPlaylists->uploads;
		$this->add_playlist($playlistId, $apiKey, $limit);
	}

	private function add_playlist($playlistId, $apiKey, $limit = -1){
		// The title is not part of the playlist request, but can be requested separately.
		// We have to display the correct playlist name properly.
		$request = 'https://www.googleapis.com/youtube/v3/playlists?part=snippet&id='
		. $playlistId
		. '&key='
		. $apiKey;

		$json = file_get_contents($request);
		if($json === false)
			returnServerError('Request failed for request: ' . $request . '!');

		$playlists = json_decode($json);
		$this->name = htmlspecialchars($playlists->items[0]->snippet->title);

		$pageToken = '';

		do {
			$request = 'https://www.googleapis.com/youtube/v3/playlistItems?'
			. implode('&', array(
				'part=snippet%2CcontentDetails%2Cstatus',
				'maxResults=50',
				'playlistId='
			))
			. $playlistId
			. '&pageToken='
			. $pageToken
			. '&key='
			. $apiKey;

			$json = file_get_contents($request);
			if($json === false)
				returnServerError('Request failed for request: ' . $request . '!');

			$playlistItems = json_decode($json);

			$done = $this->add_playlist_items($playlistItems, $limit);

			if (!$done && isset($playlistItems->nextPageToken))
				$pageToken = $playlistItems->nextPageToken;
			else
				break;
		} while ($pageToken !== '');
	}

	private function add_playlist_items($playlistItems, $limit = -1){
		foreach($playlistItems->items as $element) {
			// Store description in a temporary variable, the description might
			// consist of multiple paragraphs and can include hyperlinks:
			$description = htmlspecialchars($element->snippet->description);
			$description = nl2br($description);
			// Todo: This regex does not cover the RFC3987, but it works for basic ones (no data and such)
			$description = preg_replace(
				'/(http[s]{0,1}\:\/\/[a-zA-Z0-9.\/]{4,})/ims',
				'<a href="$1" target="_blank">$1</a> ',
				$description
			);

			$thumbnail = $element->snippet->thumbnails->{'default'}->url;

			if($element->status->privacyStatus === "public"){ // It's public
				$item = array();
				$item['author'] = $element->snippet->channelTitle;
				$item['uri'] = 'https://www.youtube.com/watch?v=' . $element->contentDetails->videoId;
				$item['title'] = htmlspecialchars($element->snippet->title);
				$item['timestamp'] = strtotime($element->snippet->publishedAt);
				$item['content'] = '<a href="'
				. $item['uri']
				. '"><img src="'
				. $thumbnail
				. '" /></a><br><p>'
				. $description
				. '</p>';

				$this->items[] = $item;
			}

			// Stop once the number of requested items is reached (<= 0: all), return true if done
			if(count($this->items) >= $limit && $limit >= 1)
				return true;
		}
	}
}
