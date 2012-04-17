<?php

	class WUHistory {
		
		private $airport_code;
		private $date;
		
		public $daily;
		public $hourly;
		
		private $csv_header_translation = array(
			'TimeEDT' => 'time',
			'TemperatureF' => 'temp',
			'Dew PointF' => 'dew_point',
			'Humidity' => 'humidity',
			'Sea Level PressureIn' => 'sea_level_pressure',
			'VisibilityMPH' => 'visibility',
			'Wind Direction' => 'wind_direction_text',
			'Wind SpeedMPH' => 'wind_speed',
			'Gust SpeedMPH' => 'gust_speed',
			'PrecipitationIn' => 'precipitation',
			'Events' => 'events',
			'Conditions' => 'conditions',
			'WindDirDegrees' => 'wind_direction',
			'DateUTC' => 'date',
		);
		
		/**
		 * Constructor, just pass in a DateTime-format date and we do the rest.
		 * 
		 * @param string $airport_code The ICAO airport code for the location.
		 * @param int|string|DateTime $date Any format supported by the PHP DateTime class.
		 */
		public function __construct ( $airport_code, $date = 'now' ) {
			
			// if it looks like a unix timestamp, add the @ prefix DT requires
			if ( is_numeric( $date ) ) {
				$date = '@' . $date;
			}
			
			// if it's not a DT, make it one
			if ( !$date instanceof DateTime ) {
				$date = new DateTime( $date );
			}
			
			$this->airport_code = $airport_code;
			$this->date = $date;
			
			// parsity parse parse
			$this->parse();
			
		}
		
		/**
		 * Generate the URL to fetch.
		 * 
		 * @param string $type 'html' for full HTML page, 'csv' for the hourly stats in CSV format.
		 */
		private function create_url ( $type = 'html' ) {
			
			$year = $this->date->format('Y');
			$month = $this->date->format('m');
			$day = $this->date->format('d');
			
			$slug = implode( '/', array( $this->airport_code, $year, $month, $day ) );
			
			$url = 'http://www.wunderground.com/history/airport/' . $slug . '/DailyHistory.html';
			
			if ( $type == 'csv' ) {
				$url = $url . '?format=1';
			}
			
			return $url;
			
		}
		
		private function parse ( ) {
			
			// parse out the hourly csv file
			$this->parse_csv();
			
			// parse out the daily html
			$this->parse_html();
			
		}
		
		private function parse_csv ( ) {
			
			$url = $this->create_url( 'csv' );
			
			$options = array(
				'http' => array(
					'timeout' => '10',
					// pretend to be a normal browser, they seem to do some UA sniffing
					//'user_agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_6_8) AppleWebKit/535.1 (KHTML, like Gecko) Chrome/13.0.782.41 Safari/535.1',
				),
			);
			
			$context = stream_context_create( $options );
			
			// get the contents
			$contents = file_get_contents( $url, false, $context );
			
			// there is a comment at the end that seems to indicate load time
			$contents = preg_replace( '/<!--.*-->/', '', $contents );
			
			// HTML line breaks in a csv file? damn you!
			$contents = str_replace( '<br />', '', $contents );
			
			// there are blank lines at the ends, trim them up
			$contents = trim( $contents );
			
			// split it by lines
			$lines = explode( "\n", $contents );
			
			// pull off the headers from the first row
			$headers = array_shift( $lines );
			
			// explode them
			$headers = explode( ',', $headers );
						
			$content = array();
			foreach ( $lines as $line ) {
				
				$line = trim( $line );
				
				if ( $line == '' ) {
					continue;
				}
				
				$pieces = explode( ',', $line );
				
				$line = array_combine( $headers, $pieces );
				
				// switch any headers we know about
				foreach ( $this->csv_header_translation as $k => $v ) {
					if ( isset( $line[ $k ] ) ) {
						$line[ $v ] = $line[ $k ];
						
						unset( $line[ $k ] );
					}
					else {
						// if it's not set, make sure our expected values are always there
						$line[ $v ] = null;
					}
				}
				
				// convert the date
				$line['date'] = new DateTime( $line['date'], new DateTimeZone('UTC') );
				
				// switch the timezone
				$line['date']->setTimezone( new DateTimeZone( 'America/New_York' ) );
								
				// figure out the hour
				$hour = $line['date']->format( 'H' );
				
				// save everything
				$content[ $hour ] = $line;
			}
			
			$this->hourly = $content;
						
		}

		private function parse_html ( ) {
			
			$url = $this->create_url( 'html' );
			
			$options = array(
				'http' => array(
					'timeout' => '10',
					// pretend to be a normal browser, they seem to do some UA sniffing
					'user_agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_6_8) AppleWebKit/535.1 (KHTML, like Gecko) Chrome/13.0.782.41 Safari/535.1',
				),
			);
			
			$context = stream_context_create( $options );
			
			// get the contents
			$contents = file_get_contents( $url, false, $context );
			
			// load it up in the DOM
			$doc = new DOMDocument( '1.0', 'UTF-8' );
			$doc->validateOnParse = false;
			$doc->strictErrorChecking = false;
			
			// @ because they have invalid HTML that makes DOM cry
			@$doc->loadHTML( $contents );
			
			$xpath = new DOMXPath( $doc );
			
			// get the history table we can use for context
			$history = $xpath->query( '//table[@id="historyTable"]' );
			$history = $history->item(0);
			
			//$temps = $xpath->query( '//td[contains( @class, "br3" ) and text()="Temperature"]', $history );
			
			$attributes = array(
				'Mean Temperature' => array( 'key' => 'mean_temp', 'query' => 'bold' ),
				'Max Temperature' => array( 'key' => 'max_temp', 'query' => 'bold' ),
				'Min Temperature' => array( 'key' => 'min_temp', 'query' => 'bold' ),
				'Dew Point' => array( 'key' => 'dew_point', 'query' => 'bold' ),
				'Average Humidity' => array( 'key' => 'avg_humidity', 'query' => 'non' ),
				'Maximum Humidity' => array( 'key' => 'max_humidity', 'query' => 'non' ),
				'Minimum Humidity' => array( 'key' => 'min_humidity', 'query' => 'non' ),
				'Sea Level Pressure' => array( 'key' => 'sea_level_pressure', 'query' => './following-sibling::td/b' ),
				'Visibility' => array( 'key' => 'visibility', 'query' => 'bold' ),
				'Max Wind Speed' => array( 'key' => 'max_wind_speed', 'query' => 'bold' ),
				'Max Gust Speed' => array( 'key' => 'max_gust_speed', 'query' => 'bold' ),
				'Events' => array( 'key' => 'events', 'query' => 'non' ),
				'Precipitation' => array( 'key' => 'precipitation', 'query' => 'bold' ),
			);
			
			$values = array();
			foreach ( $attributes as $attribute_name => $attribute ) {
				
				$parent_query = '//td[ contains( @class, "indent" ) ]/span[ text() = "' . $attribute_name . '" ]/..';
				
				if ( $attribute['query'] == 'bold' ) {
					$value_query = '../td/span[ contains( @class, "nobr" ) ]/span[ contains( @class, "b" ) ]';
				}
				else if ( $attribute['query'] == 'non' ) {
					$value_query = './following-sibling::td';
				}
				else {
					$value_query = $attribute['query'];
				}
				
				$parent = $xpath->query( $parent_query );
				$value = $xpath->query( $value_query, $parent->item(0) );
								
				if ( $value->length < 1 ) {
					$value = 'Unavailable';
				}
				else {
					$value = $value->item(0)->nodeValue;
				}
				
				$value = trim( $value );
				
				$values[ $attribute['key'] ] = $value;
				
			}
			
			$this->daily = $values;
			
		}
		
	}

?>