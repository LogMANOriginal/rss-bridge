<?php
// This bridge is designed for a German site. All parameters are therefore in
// German language.
class Immobilienscout24Bridge extends BridgeAbstract {
	const NAME = 'Immobilienscout 24';
	const URI = 'https://www.immobilienscout24.de';
	const DESCRIPTION = 'Returns arpartment offers from a German site';
	const MAINTAINER = 'logmanoriginal';
	const PARAMETERS = array(
		'Search' => array(
			'path' => array(
				'name' => 'Path',
				'type' => 'text',
				'required' => true,
				'title' => 'Insert path to search result (without domain)'
			),
			'private_only' => array(
				'name' => 'Private only',
				'type' => 'checkbox',
				'required' => false,
				'title' => 'Check if you only want private offers'
			)
		)
	);

	private $title = ''; // Holds the title

	public function getName(){
		switch($this->queriedContext){
			case 'Search': return $this->title . ' - ' . self::NAME;
			default: return parent::getName();
		}
	}

	public function getURI(){
		switch($this->queriedContext){
			case 'Search': return self::URI . $this->getInput('path');
			default: return parent::getURI();
		}
	}

	public function collectData(){
		$html = getSimpleHTMLDOM($this->getURI())
			or returnServerError('Could not load ' . $this->getURI());

		$html->find('h1 span', 0)->innertext = ''; // Clear number of matches
		$this->title = $html->find('h1', 0)->plaintext;

		//$results = $html->find('article[data-item=result]');
		foreach($html->find('article[data-item=result]') as $result){
			$item = array();
			$item['title'] = $result->find('h5.result-list-entry__brand-title', 0)->plaintext;
			$item['uri'] = self::URI . $result->find('a.result-list-entry__brand-title-container', 0)->href;

			$author = $result->find('div.result-list-entry__realtor-data div div.grid-item', 1);
			$item['author'] = count($author->find('span')) >= 2 ? trim($author->find('span', 1)->plaintext) : 'Privat';
			if($this->getInput('private_only') && $item['author'] !== 'Privat') continue;

			$item['enclosures'] = array();
			foreach($result->find('img.gallery__image') as $enclosure){
				if(isset($enclosure->src)) $item['enclosures'][] = $enclosure->src;
				if(isset($enclosure->{'data-lazy'})) $item['enclosures'][] = $enclosure->{'data-lazy'};
			}
			if(count($item['enclosures']) === 0) continue; // Don't care if no pictures

			$item['rent'] = trim($result->find('dl.result-list-entry__primary-criterion dd', 0)->plaintext);
			$item['rent_type'] = trim($result->find('dl.result-list-entry__primary-criterion dt', 0)->plaintext);
			$item['size'] = trim($result->find('dl.result-list-entry__primary-criterion dd', 1)->plaintext);
			$item['size_type'] = trim($result->find('dl.result-list-entry__primary-criterion dt', 1)->plaintext);

			// Rooms have a span in between (for small displays)
			$result->find('dl.result-list-entry__primary-criterion dd(3) span', 0)->innertext = '';

			$item['rooms'] = trim($result->find('dl.result-list-entry__primary-criterion dd', 2)->plaintext);
			$item['rooms_type'] = trim($result->find('dl.result-list-entry__primary-criterion dt', 2)->plaintext);
			$item['address'] = trim($result->find('div.result-list-entry__address', 0)->plaintext);

			$logo = $result->find('img.result-list-entry__brand-logo', 0);
			if(isset($logo->src)){
				$item['logo'] = $logo->src;
			} elseif(isset($logo->{'data-lazy-src'})){
				$item['logo'] = $logo->{'data-lazy-src'};
			}

			$item['content'] = <<<EOD
<p style="font-weight:bold; padding-bottom:1em;">{$item['title']}</p>
<table>
	<tr>
		<td>Rent</td>
		<td style="padding-left:1em; font-weight:bold; color:red;">{$item['rent']}</td>
	</tr>
	<tr>
		<td>Size</td>
		<td style="padding-left:1em;">{$item['size']}</td>
	</tr>
	<tr>
		<td>Rooms</td>
		<td style="padding-left:1em;">{$item['rooms']}</td>
	</tr>
</table>
<img src={$item['enclosures'][0]} style="display:block; padding-top:1em;"/>
EOD;

			$this->items[] = $item;
		}
	}
}
