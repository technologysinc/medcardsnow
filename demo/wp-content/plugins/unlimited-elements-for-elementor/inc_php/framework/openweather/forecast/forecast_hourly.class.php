<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class UELM_OpenWeatherAPIForecastHourly extends UELM_OpenWeatherAPIForecastAbstract{

	use UELM_OpenWeatherAPIForecastHasInlineTemperature;

}

class_alias( UELM_OpenWeatherAPIForecastHourly::class, 'UEOpenWeatherAPIForecastHourly' );
