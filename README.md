Description
===========
A quick and dirty library for "obtaining" historic (and, really, current too) weather information from the [Weather Underground](http://wunderground.com) site.

The class works by accepting an [ICAO airport code](http://en.wikipedia.org/wiki/ICAO_airport_code) for the location to get data for and an optional date parameter for getting historical information. It then scrapes the CSV file of hourly data and HTML page of summary information on the Weather Underground site.

Don't Be Evil
-------------
For the record, this is very mean and you should never **ever** use this in any production capacity. If you use it *anywhere* you should be sure not to make too many requests. Not only would that make you a bad netizen, but you might also get your IP banned by their admins. You've been warned.

License
-------

	Copyright 2011 Chris Meller
	
	Licensed under the Apache License, Version 2.0 (the "License");
	you may not use this file except in compliance with the License.
	You may obtain a copy of the License at
	
	    http://www.apache.org/licenses/LICENSE-2.0
	
	Unless required by applicable law or agreed to in writing, software
	distributed under the License is distributed on an "AS IS" BASIS,
	WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
	See the License for the specific language governing permissions and
	limitations under the License.

Usage
=====
Include the class in your code and instantiate the ``WUHistory`` class with the ICAO airport code and optional date you want to get weather data for:

	$wu = new WUHistory( 'KCUB', '2011-06-17' );

The parsing is done immediately and the returned object will have two parameters of prime importance, the daily and hourly information that was found:

	print_r( $wu->daily );
	print_r( $wu->hourly );