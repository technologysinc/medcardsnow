<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class UELM_OpenWeatherAPIForecastCurrent extends UELM_OpenWeatherAPIForecastAbstract{

	use UELM_OpenWeatherAPIForecastHasInlineTemperature, UELM_OpenWeatherAPIForecastHasSunTime;

}

class_alias( UELM_OpenWeatherAPIForecastCurrent::class, 'UEOpenWeatherAPIForecastCurrent' );
