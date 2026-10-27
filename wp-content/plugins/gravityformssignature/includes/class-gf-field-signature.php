<?php

if ( ! class_exists( 'GFForms' ) ) {
	die();
}

class GF_Field_Signature extends GF_Field {

	public $type = 'signature';

	// # FORM EDITOR & FIELD MARKUP -------------------------------------------------------------------------------------

	/**
	 * Return the field title, for use in the form editor.
	 *
	 * @return string
	 */
	public function get_form_editor_field_title() {
		return esc_attr__( 'Signature', 'gravityformssignature' );
	}

	/**
	 * Returns the field's form editor icon.
	 *
	 * This could be an icon url or a dashicons class.
	 *
	 * @since 3.9.1
	 *
	 * @return string
	 */
	public function get_form_editor_field_icon() {
		return gf_signature()->is_gravityforms_supported( '2.5-beta-4' ) ? 'gform-icon--signature' : gf_signature()->get_base_url() . '/images/menu-icon.svg';
	}

	/**
	 * Returns the field's form editor description.
	 *
	 * @since 3.9.1
	 *
	 * @return string
	 */
	public function get_form_editor_field_description() {
		return esc_attr__( 'Allows users to sign online using a mouse or stylus.', 'gravityformssignature' );
	}

	/**
	 * Assign the Signature button to the Advanced Fields group.
	 *
	 * @return array
	 */
	public function get_form_editor_button() {
		return array(
			'group' => 'advanced_fields',
			'text'  => $this->get_form_editor_field_title()
		);
	}

	/**
	 * Return the settings which should be available on the field in the form editor.
	 *
	 * @return array
	 */
	function get_form_editor_field_settings() {
		return array(
			'pen_size_setting',
			'border_width_setting',
			'border_style_setting',
			'box_width_setting',
			'pen_color_setting',
			'border_color_setting',
			'background_color_setting',
			'conditional_logic_field_setting',
			'error_message_setting',
			'label_setting',
			'label_placement_setting',
			'admin_label_setting',
			'rules_setting',
			'visibility_setting',
			'description_setting',
			'css_class_setting'
		);
	}

	/**
	 * Returns the scripts to be included for this field type in the form editor.
	 *
	 * @return string
	 */
	public function get_form_editor_inline_script_on_page_render() {

		// set the default field label
		$script = sprintf( "function SetDefaultValues_signature(field) {field.label = '%s';}", $this->get_form_editor_field_title() ) . PHP_EOL;

		// initialize the fields custom settings
		$script .= "jQuery(document).bind('gform_load_field_settings', function (event, field, form) {" .

		           "var backColor = field.backgroundColor == undefined ? '' : field.backgroundColor;" .
		           "jQuery('#field_signature_background_color').val(backColor);" .
		           "SetColorPickerColor('field_signature_background_color', backColor);" .

		           "var borderColor = field.borderColor == undefined ? '' : field.borderColor;" .
		           "jQuery('#field_signature_border_color').val(borderColor);" .
		           "SetColorPickerColor('field_signature_border_color', borderColor);" .

		           "var penColor = field.penColor == undefined ? '' : field.penColor;" .
		           "jQuery('#field_signature_pen_color').val(penColor);" .
		           "SetColorPickerColor('field_signature_pen_color', penColor);" .

		           "var boxWidth = field.boxWidth == undefined || field.boxWidth.trim().length == 0 ? '300' : field.boxWidth;" .
		           "jQuery('#field_signature_box_width').val(boxWidth);" .

		           "var borderStyle = field.borderStyle == undefined ? '' : field.borderStyle.toLowerCase();" .
		           "jQuery('#field_signature_border_style').val(borderStyle);" .

		           "var borderWidth = field.borderWidth == undefined ? '' : field.borderWidth;" .
		           "jQuery('#field_signature_border_width').val(borderWidth);" .

		           "var penSize = field.penSize == undefined ? '' : field.penSize;" .
		           "jQuery('#field_signature_pen_size').val(penSize);" .

		           "});" . PHP_EOL;

		// initialize the input mask for the width setting
		$script .= "jQuery(document).ready(function () {jQuery('#field_signature_box_width').mask('?9999', {placeholder: ' '});});" . PHP_EOL;

		// saving the backgroundColor property and updating the UI to match
		$script .= "function SetSignatureBackColor(color) {SetFieldProperty('backgroundColor', color);jQuery('.field_selected .gf_signature_container').css('background-color', color);}" . PHP_EOL;

		// saving the borderColor property and updating the UI to match
		$script .= "function SetSignatureBorderColor(color) {SetFieldProperty('borderColor', color);jQuery('.field_selected .gf_signature_container').css('border-color', color);}" . PHP_EOL;

		// saving the penColor property
		$script .= "function SetSignaturePenColor(color) {SetFieldProperty('penColor', color);}" . PHP_EOL;

		// saving the boxWidth property
		$script .= "function SetSignatureBoxWidth(size) {SetFieldProperty('boxWidth', size);}" . PHP_EOL;

		// saving the borderStyle property and updating the UI to match
		$script .= "function SetSignatureBorderStyle(style) {SetFieldProperty('borderStyle', style);jQuery('.field_selected .gf_signature_container').css('border-style', style);}" . PHP_EOL;

		// saving the borderWidth property and updating the UI to match
		$script .= "function SetSignatureBorderWidth(size) {SetFieldProperty('borderWidth', size);jQuery('.field_selected .gf_signature_container').css('border-width', size + 'px');}" . PHP_EOL;

		// saving the penSize property
		$script .= "function SetSignaturePenSize(size) {SetFieldProperty('penSize', size);}";

		return $script;
	}

	/**
	 * Returns the field inner markup.
	 *
	 * @since 3.0
	 * @since 4.7 Switched from Super Signature to Signature Pad.
	 * @since 4.9 Removed the "{prefix}_valid" input that was left behind following the switch to Signature Pad.
	 *
	 * @param array        $form  The Form Object currently being processed.
	 * @param string|array $value The field value. From default/dynamic population, $_POST, or a resumed incomplete submission.
	 * @param null|array   $entry Null or the Entry Object currently being edited.
	 *
	 * @return string
	 */
	public function get_field_input( $form, $value = '', $entry = null ) {
		$is_entry_detail = $this->is_entry_detail();
		$is_form_editor  = $this->is_form_editor();
		$is_block_editor = $this->is_block_editor();
		$is_admin        = $is_entry_detail || $is_form_editor;

		$form_id = absint( $form['id'] );
		$id      = $this->id;
		$field_id = $form_id == 0 ? "input_$id" : 'input_' . $form_id . "_$id";

		$init_options = $this->get_signaturepad_init_options( $form );

		$bgcolor     = rgar( $init_options, 'backgroundColor', '#FFFFFF' );
		$bordercolor = empty( $this->borderColor ) ? '#DDDDDD' : $this->borderColor;
		$boxheight   = '180';
		$boxwidth    = rgblank( $this->boxWidth ) ? '300' : $this->boxWidth;
		$borderstyle = empty( $this->borderStyle ) ? 'Dashed' : $this->borderStyle;
		$borderwidth = rgblank( $this->borderWidth ) ? '2px' : $this->borderWidth . 'px';

		if ( $is_form_editor || $is_block_editor ) {
			if ( gf_signature()->is_gravityforms_supported( '2.5-beta' ) ) {
				$input = "<div style='zoom: 1;'><div class='gf_signature_container' style='height:180px; border: {$borderwidth} {$borderstyle} {$bordercolor}; background-color:{$bgcolor};'></div></div>";
			} else {
				//box width is hardcoded in the admin
				$input = '<style>' .
				         '.top_label .gf_signature_container {width: 460px;} ' .
				         '.left_label .gf_signature_container, .right_label .gf_signature_container {width: 300px;} ' .
				         '</style>' .
				         "<div style='display:-moz-inline-stack; display: inline-block; zoom: 1; *display: inline;'><div class='gf_signature_container' style='height:180px; border: {$borderwidth} {$borderstyle} {$bordercolor}; background-color:{$bgcolor};'></div></div>";
			}
		} else {

			$input = '';

			$signature_filename = $value;
			$container_style = rgar( $form, 'labelPlacement', 'top_label' ) == 'top_label' ? '' : "style='display:-moz-inline-stack; display: inline-block; zoom: 1; *display: inline;'";

			if ( ! empty( $signature_filename ) ) {

				$signature_url = $this->get_value_url( $signature_filename );

				$input .= sprintf( "<div id='%s_signature_image' {$container_style}><div class='gfield_signature_image gform-theme__no-reset--el gform-theme__no-reset--children' style='width: %spx;'><img src='%s' width='%spx' style='border-style: %s; border-width: %s; border-color: %s;' alt='%s' /><div>", $field_id, $boxwidth, $signature_url, $boxwidth, $borderstyle, $borderwidth, $bordercolor, esc_attr__( 'Signature Image', 'gravityformssignature' ) );

				if ( $is_entry_detail && $value ) {

					// include the download link
					$download_icon = gf_signature()->is_gravityforms_supported( '2.5' ) ? '<i class="gform-icon gform-icon--circle-arrow-down gform-c-hunter"></i>' : '<img src="' . GFCommon::get_base_url() . '/images/download.png"/>';
					$input        .= sprintf(
						"<a href='%s' target='_blank' title='%s' class='gform-signature-action'>%s</a>",
						$signature_url,
						esc_attr__( 'Download file', 'gravityformssignature' ),
						$download_icon
					);

					// include the delete link
					$delete_icon = gf_signature()->is_gravityforms_supported( '2.5.5.4' ) ? '<i class="gform-icon gform-icon--circle-delete gform-c-red"></i>' : '<img src="' . GFCommon::get_base_url() . '/images/delete.png" style="margin: 0 8px"/>';
					$input      .= sprintf(
						"<a href='javascript:void(0);' title='%s' onclick='window.gform.signature.deleteSignature( %d, %d, %d, this );' class='gform-signature-action'>%s</a>",
						esc_attr__( 'Delete file', 'gravityformssignature' ),
						rgar( $entry, 'id' ),
						$form_id,
						$id,
						$delete_icon
					);

				}

				$input .= "</div></div></div>";

				$input .= "<style type='text/css'>#{$field_id}_resetbutton {display:none}</style>";

			}

			$input .= sprintf( "<input type='hidden' value='%s' name='input_%s' id='%s_signature_filename'/>", esc_attr( $signature_filename ), $id, $field_id );

			$display = ! empty( $signature_filename ) ? 'display:none;' : '';

			$input .= "<div class='gfield_signature_ui_container gform-theme__no-reset--children' {$container_style}><div id='{$field_id}_Container' class='gfield_signature_container ginput_container' style='height:{$boxheight}px; width:{$boxwidth}px; {$display}' >";

			$icon_url = gf_signature()->get_base_url() . '/assets/img/pen.cur';
			$input .= "<canvas id='" . esc_attr( $field_id ) . "' width='" . esc_attr( $boxwidth ) . "' height='" . esc_attr( $boxheight ) . "' style='border-style: " . esc_attr( $borderstyle ) . "; border-width: " . esc_attr( $borderwidth ) . "; border-color: " . esc_attr( $bordercolor ) . "; background-color:" . esc_attr( $bgcolor ) . "; cursor: url(" . esc_url( $icon_url ) . "), pointer;'></canvas>";

			$refresh_image = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABgAAAAYCAYAAADgdz34AAAAGXRFWHRTb2Z0d2FyZQBBZG9iZSBJbWFnZVJlYWR5ccllPAAAAtRJREFUeNrsld9rklEYx32nc7i2GulGtZg6XJbJyBeJzbGZJJVuAyFD7D8QumiG7nLXQuw6dtHN7oYwFtIgDG+2CGQtGf1grBpWIkPHaDpJZvZ95F2cqfPHRTfRgY/H85znfb7nPc85z8sVi0XR32zcf4GmBTiOk8GWY8YSdEpwHpwG7eAA/ABJsA3/w5MEJOUGi8VyCUFFeCiGvlcsFvOFQqGtzK1d4Bzmr8DvDfy/NyTgcDj6I5GIGA91YdiN4CW7RqNp83g8fZ2dna17e3v5ubm5r1tbWz8F8WH4v4PIh7oCTOumH4VCIQkGg6axsTElgkRhyoJTXq/33srKStzpdL5KpVK0RVcxvw+Rb40KlNr09LTSbDZH8HcJ/DqyY2sksE9Go1GHVqsN5fP5Yk9Pz3WIJNmctNQT8Pl8n/DQZza40CjIokqlerywsMCTYWdnpwVjTb0kF1dXVy2sLR6Pn4HIJnu6mLZht9s3KUeUE7VarYPt459ZOqZlKMFEFRRVfI+QzMzMeBHOOTAw4GbnKt4AK6Vte0/nHA6pBu/T4ejoqAgnS4dTlT82U74aJOourYTn+ds1VlyNm+AReMjaK5LsdrvpxoqSyWSX8DbVSwDHtYJ+hi9gETxl/SoCWK1WGfWJRKLQ0dGhO0kAq5MGAoFB/OVZXC6XtqYAzvamwWCgMiDK5XKXsSL5CRpZv98vnp+fH2SNJpPpYk0BlIIXSJaB/lOZkEqlNyCi4ahAHd8iajGUj41a2a+2xzmj0fgsFAoN0QA3lAJfAxMISDeVpx7jSbJnMplSOZ6amuptVIBaZHx8/G0sFruj1+tlgo2KWh/oF3opGWl+bW3t1uzsrHJ5eXm42Q+OGW/wADc7gYe3w+Fwen19/YByhMMgt9lsqpGRkQvYxifwfQnup9PprFwuX2rmi0ZvYAdDwurPgl1A9ek1eE7byqYR7P873+TfAgwATQiKdubVli0AAAAASUVORK5CYII=';
			$input .= "</div>";
			$input .= "<div id='" . esc_attr( $field_id ) . "_toolbar' style='margin:5px 0;position:relative;height:20px;width:" . esc_attr( $boxwidth ) . "px;max-width:100%;'>";
			if ( ! empty( $signature_filename ) && ( ! $is_entry_detail || ! $value ) ) {
				$input .= '<button type="button" id="' . esc_attr( $field_id ) . '_lockedReset" class="gform_signature_locked_reset gform-theme-no-framework" style="color: var(--gform-theme-control-description-color);height:24px;cursor:pointer;padding: 0 0 0 1.8em;opacity:0.75;font-size:0.813em;border:0;background: transparent url(data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHZpZXdCb3g9IjAgMCA0NDggNTEyIiBjbGFzcz0idW5kZWZpbmVkIj48cGF0aCBkPSJNNDAwIDIyNGgtMjR2LTcyQzM3NiA2OC4yIDMwNy44IDAgMjI0IDBTNzIgNjguMiA3MiAxNTJ2NzJINDhjLTI2LjUgMC00OCAyMS41LTQ4IDQ4djE5MmMwIDI2LjUgMjEuNSA0OCA0OCA0OGgzNTJjMjYuNSAwIDQ4LTIxLjUgNDgtNDhWMjcyYzAtMjYuNS0yMS41LTQ4LTQ4LTQ4em0tMTA0IDBIMTUydi03MmMwLTM5LjcgMzIuMy03MiA3Mi03MnM3MiAzMi4zIDcyIDcydjcyeiIgY2xhc3M9InVuZGVmaW5lZCIvPjwvc3ZnPg==) no-repeat left center;background-size:16px;direction:ltr;">' . esc_html__('Reset to sign again', 'gravityformssignature') . '</button>';

			}
			$input .= "<img id = '" . esc_attr( $field_id ) . "_resetbutton' src='" . esc_attr( $refresh_image ) . "' style='cursor:pointer;float:right;height:24px;width:24px;border:0px solid transparent' alt='Clear Signature' / >";
			$input .= "</div>";
			$input .= "<input type='hidden' id='{$field_id}_data' name='{$field_id}_data' value=''>";
			$input .= '</div>';

			if ( $this->is_entry_detail_edit() || ! gf_signature()->is_gravityforms_supported( '2.9' ) ) {
				// Theme config is not available in the entry detail edit view or in order versions of Gravity Forms, so we need to inline the configuration.
				$unique_id    = "input_{$form['id']}_{$this->id}";
				$init_options = $this->get_signaturepad_init_options( $form );

				$script = "window.gform_signature_settings_{$unique_id} = " . json_encode( $init_options ) . ";";

				$input .= "<script type='text/javascript'>" . $script . '</script>';
			}
		}

		return $input;
	}

	/**
	 * Determines if the current page has the block editor.
	 *
	 * @since 4.4
	 *
	 * @return bool Returns true if the current page is a post or page edit with block editor enabled. Returns false otherwise.
	 */
	public function is_block_editor() {
		$is_rest_request = defined( 'REST_REQUEST' ) && REST_REQUEST;
		$is_form_preview = rgget( 'context' ) == 'edit' && boolval( $_GET['attributes']['formPreview'] );

		return $is_rest_request && $is_form_preview;
	}

	// # SUBMISSION -----------------------------------------------------------------------------------------------------

	/**
	 * Used to determine the required validation result.
	 *
	 * @since 4.9 Updated for compatibility with the switch to Signature Pad, which doesn't perform JS-based validation.
	 *
	 * @param int $form_id The ID of the form currently being processed.
	 *
	 * @return bool
	 */
	public function is_value_submission_empty( $form_id ) {
		$input_prefix     = "input_{$this->id}";
		$data_input_value = rgpost( $input_prefix . '_data' );
		$is_invalid       = rgempty( $input_prefix ) && ( empty( $data_input_value ) || ! is_string( $data_input_value ) || ! str_starts_with( $data_input_value, 'data:image/png;base64,' ) );

		if ( $is_invalid && empty( $this->errorMessage ) ) {
			$this->errorMessage = __( 'Please enter your signature.', 'gravityformssignature' );
		}

		return $is_invalid;
	}

	/**
	 * Validates that the provided signature filename ends with .png and that the file exists.
	 *
	 * @since 4.9
	 *
	 * @param string|array $value The signature filename.
	 * @param array        $form  The form currently being processed.
	 *
	 * @return void
	 */
	public function validate( $value, $form ) {
		if ( empty( $value ) ) {
			if ( $this->isRequired ) {
				$this->failed_validation = true;
			}
		} elseif ( ! is_string( $value ) || ! str_ends_with( $value, '.png' ) || ! file_exists( GFSignature::get_signatures_folder() . $value ) ) {
			$this->failed_validation = true;
		}

		if ( $this->failed_validation ) {
			$this->validation_message = $this->errorMessage ?: __( 'Please enter your signature.', 'gravityformssignature' );
		}
	}

	/**
	 * Save the signature on submission; includes form validation or when an incomplete submission is being saved.
	 *
	 * @param array $field_values The dynamic population parameter names with their corresponding values to be populated.
	 * @param bool|true $get_from_post_global_var Whether to get the value from the $_POST array as opposed to $field_values.
	 *
	 * @return string
	 */
	public function get_value_submission( $field_values, $get_from_post_global_var = true ) {
		if ( empty( $_POST[ 'is_submit_' . $this->formId ] ) ) {
			return '';
		}

		return $this->maybe_save_signature();
	}


	// # ENTRY RELATED --------------------------------------------------------------------------------------------------

	/**
	 * Get the signature filename for saving to the Entry Object.
	 *
	 * @param array|string $value The value to be saved.
	 * @param array $form The Form Object currently being processed.
	 * @param string $input_name The input name used when accessing the $_POST.
	 * @param int $lead_id The ID of the Entry currently being processed.
	 * @param array $lead The Entry Object currently being processed.
	 *
	 * @return array|string
	 */
	public function get_value_save_entry( $value, $form, $input_name, $lead_id, $lead ) {

		if ( ! empty( $value ) ) {
			return $value;
		}
		return $this->maybe_save_signature();
	}

	/**
	 * Format the entry value for when the field/input merge tag is processed. Not called for the {all_fields} merge tag.
	 *
	 * @param string|array $value The field value. Depending on the location the merge tag is being used the following functions may have already been applied to the value: esc_html, nl2br, and urlencode.
	 * @param string $input_id The field or input ID from the merge tag currently being processed.
	 * @param array $entry The Entry Object currently being processed.
	 * @param array $form The Form Object currently being processed.
	 * @param string $modifier The merge tag modifier. e.g. value
	 * @param string|array $raw_value The raw field value from before any formatting was applied to $value.
	 * @param bool $url_encode Indicates if the urlencode function may have been applied to the $value.
	 * @param bool $esc_html Indicates if the esc_html function may have been applied to the $value.
	 * @param string $format The format requested for the location the merge is being used. Possible values: html, text or url.
	 * @param bool $nl2br Indicates if the nl2br function may have been applied to the $value.
	 *
	 * @return string
	 */
	public function get_value_merge_tag( $value, $input_id, $entry, $form, $modifier, $raw_value, $url_encode, $esc_html, $format, $nl2br ) {
		if ( ! empty( $value ) ) {
			$signature_url = $this->get_value_url( $value );

			return $url_encode ? urlencode( $signature_url ) : $signature_url;
		}

		return $value;
	}

	/**
	 * Format the entry value for display on the entries list page.
	 *
	 * @param string|array $value The field value.
	 * @param array $entry The Entry Object currently being processed.
	 * @param string $field_id The field or input ID currently being processed.
	 * @param array $columns The properties for the columns being displayed on the entry list page.
	 * @param array $form The Form Object currently being processed.
	 *
	 * @return string
	 */
	public function get_value_entry_list( $value, $entry, $field_id, $columns, $form ) {
		if ( ! empty( $value ) ) {
			$signature_url = $this->get_value_url( $value );
			$thumb         = gf_signature()->is_gravityforms_supported( '2.5' ) ? '<i class="gform-icon gform-icon--signature"></i>' : '<img src="' . GFCommon::get_base_url() . '/images/doctypes/icon_image.gif">';
			$value         = sprintf( "<a href='%s' target='_blank' title='%s'>%s</a>", $signature_url, esc_attr__( 'Click to view', 'gravityformssignature' ), $thumb );
		}

		return $value;
	}

	/**
	 * Format the entry value for display on the entry detail page and for the {all_fields} merge tag.
	 *
	 * @param string|array $value The field value.
	 * @param string $currency The entry currency code.
	 * @param bool|false $use_text When processing choice based fields should the choice text be returned instead of the value.
	 * @param string $format The format requested for the location the merge is being used. Possible values: html, text or url.
	 * @param string $media The location where the value will be displayed. Possible values: screen or email.
	 *
	 * @return string
	 */
	public function get_value_entry_detail( $value, $currency = '', $use_text = false, $format = 'html', $media = 'screen' ) {
		if ( ! empty( $value ) ) {
			$signature_url = $this->get_value_url( $value );

			if ( $format == 'html' ) {
				$value = sprintf( "<a href='%s' target='_blank' title='%s'><img src='%s' width='100' alt='%s' /></a>", $signature_url, esc_attr__( 'Click to view', 'gravityformssignature' ), $signature_url, esc_attr__( 'Signature Image', 'gravityformssignature' ) );
			} else {
				$value = $signature_url;
			}

		}

		return $value;
	}

	/**
	 * Format the entry value before it is used in entry exports and by framework add-ons using GFAddOn::get_field_value().
	 *
	 * @param array $entry The entry currently being processed.
	 * @param string $input_id The field or input ID.
	 * @param bool|false $use_text When processing choice based fields should the choice text be returned instead of the value.
	 * @param bool|false $is_csv Is the value going to be used in the .csv entries export?
	 *
	 * @return string
	 */
	public function get_value_export( $entry, $input_id = '', $use_text = false, $is_csv = false ) {
		if ( empty( $input_id ) ) {
			$input_id = $this->id;
		}

		$value = rgar( $entry, $input_id );

		return ! empty( $value ) ? $this->get_value_url( $value ) : '';
	}

	/**
	 * Returns the signature URL.
	 *
	 * @since 4.0
	 *
	 * @param string $value The field value; the signature filename including extension.
	 *
	 * @return string
	 */
	public function get_value_url( $value ) {
		if ( is_array( $value ) ) {
			$urls = array();
			foreach ( $value as $val ) {
				$modifiers = $this->get_modifiers();
				$signature = new GF_Signature_Image( gf_signature(), pathinfo( $val, PATHINFO_FILENAME ), $this->formId, $this->id, in_array( 'transparent', $modifiers ), in_array( 'download', $modifiers ) );
				$urls[] = $signature->get_url();
			}
			return $urls;
		} else {
			$modifiers = $this->get_modifiers();
			$signature = new GF_Signature_Image( gf_signature(), pathinfo( $value, PATHINFO_FILENAME ), $this->formId, $this->id, in_array( 'transparent', $modifiers ), in_array( 'download', $modifiers ) );
			return $signature->get_url();
		}
	}


	// # HELPERS --------------------------------------------------------------------------------------------------------

	/**
	 * Save the signature if it hasn't already been saved. Delete the old signature if they used the sign again link.
	 *
	 * @return string The filename.
	 */
	public function maybe_save_signature() {
		$form_id = $this->formId;
		$id      = $this->id;

		$input_name   = "input_{$id}";
		$input_prefix = "input_{$form_id}_{$id}";
		$input_data   = "{$input_prefix}_data";

		$signature_data = rgpost( $input_data );
		$filenames = array();
		$output = null;

		$filenames = rgpost( $input_name );

		if ( is_array( $signature_data ) ) {
			foreach ( $signature_data as $index => $data ) {
				$current_filename = $filenames[$index];

				if ( ! empty( $current_filename ) && ! empty( $data ) ) {
					gf_signature()->delete_signature_file( $current_filename );
					$current_filename = false;
				}

				if ( empty( $current_filename ) && ! empty( $data ) ) {
					$current_filename = gf_signature()->save_signature( $input_data, '', $index );
				}

				$filenames[ $index ] = $current_filename;
			}
			$_POST[ $input_name ] = $filenames;
			$output = $filenames;
		} else {
			$filename = $filenames;

			if ( ! empty( $filename ) && ! empty( $signature_data ) ) {
				gf_signature()->delete_signature_file( $filename );
				$filename = false;
			}

			if ( empty( $filename ) && ! empty( $signature_data ) ) {
				$filename = gf_signature()->save_signature( $input_data );
				$_POST["{$input_name}"] = $filename;
			}
			$output = $filename;
		}

		unset( $_POST[ $input_data ] );

		return $output;
	}

	/**
	 * Retrieve the options to be used when initializing SuperSignature for this field.
	 *
	 * @param array $form The current form object.
	 *
	 * @return array
	 */
	public function get_signaturepad_init_options( $form ) {

		$init_options = array(
			'backgroundColor'      => empty( $this->backgroundColor ) ? '#FFFFFF' : $this->backgroundColor,
			'dotSize'              => rgblank( $this->penSize ) ? '2' : $this->penSize,
			'penColor'             => empty( $this->penColor ) ? '#000000' : $this->penColor,
			'throttle'             => 16,
			'minDistance'          => 2,
			'velocityFilterWeight' => 0.7,
		);

		// Along with dotSize, these options define the stroke width.
		$init_options[ 'minWidth' ] = $init_options[ 'dotSize' ] / 2;
		$init_options[ 'maxWidth' ] = $init_options[ 'dotSize' ] * 2;

		$valid_keys = array_keys($init_options);

		/**
		 * Allow the Signature Pad initialization options to be customized.
		 *
		 * @param array $init_options The initialization options.
		 * @param GF_Field_Signature $field The current field object.
		 * @param array $form The current form object.
		 *
		 * @since 3.0.2
		 */
		$init_options = apply_filters( 'gform_signature_init_options', $init_options, $this, $form );

		// Backwards compatibility.
		$init_options['backgroundColor'] = rgar( $init_options, 'BackColor', rgar( $init_options, 'backgroundColor', '#FFFFFF' ) );
		$init_options['dotSize']         = rgar( $init_options, 'PenSize', rgar( $init_options, 'dotSize', '2' ) );
		$init_options['penColor']        = rgar( $init_options, 'PenColor', rgar( $init_options, 'penColor', '#000000' ) );

		$init_options = array_intersect_key( $init_options, array_flip( $valid_keys ) );

		return $init_options;
	}



}

GF_Fields::register( new GF_Field_Signature() );
