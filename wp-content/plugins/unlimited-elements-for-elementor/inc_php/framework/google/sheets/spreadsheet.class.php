<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class UELM_GoogleAPISpreadsheet extends UELM_GoogleAPIModel{

	/**
	 * Get the identifier.
	 *
	 * @return string
	 */
	public function getId(){

		$id = $this->getAttribute("spreadsheetId");

		return $id;
	}

	/**
	 * Get the sheets.
	 *
	 * @return UELM_GoogleAPISheet[]
	 */
	public function getSheets(){

		$sheets = $this->getAttribute("sheets");
		$sheets = UELM_GoogleAPISheet::transformAll($sheets);

		return $sheets;
	}

}

class_alias( UELM_GoogleAPISpreadsheet::class, 'UEGoogleAPISpreadsheet' );
