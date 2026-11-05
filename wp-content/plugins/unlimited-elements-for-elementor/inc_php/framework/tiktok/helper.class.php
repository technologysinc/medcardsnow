<?php
/**
 * @package Unlimited Elements
 * @author unlimited-elements.com
 * @copyright (C) 2021 Unlimited Elements, All Rights Reserved.
 * @license GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 * */
if ( ! defined( 'ABSPATH' ) ) exit;


class UELM_HelperTikTok{

	const URL_APP_CONNECT = "https://unlimited-elements.com/tiktok-connect/connect.php";
	const RENEW_BEFORE_SECONDS = 7200;
	const REFRESH_LOCK = "uelm_tiktok_refresh_lock";
	const REFRESH_LOCK_SECONDS = 600;

	/**
	 * saved tokens from general settings
	 */
	public static function getSavedAccessData(){

		$settings = HelperProviderCoreUC_EL::getGeneralSettingsValues();

		$data = array();
		$data["access_token"] = UniteFunctionsUC::getVal($settings, "tiktok_access_token");
		$data["refresh_token"] = UniteFunctionsUC::getVal($settings, "tiktok_refresh_token");
		$data["open_id"] = UniteFunctionsUC::getVal($settings, "tiktok_open_id");
		$data["display_name"] = UniteFunctionsUC::getVal($settings, "tiktok_display_name");
		$data["avatar_url"] = UniteFunctionsUC::getVal($settings, "tiktok_avatar_url");
		$data["expires"] = UniteFunctionsUC::getVal($settings, "tiktok_expires");
		$data["refresh_expires"] = UniteFunctionsUC::getVal($settings, "tiktok_refresh_expires");
		$data["scope"] = UniteFunctionsUC::getVal($settings, "tiktok_scope");

		return($data);
	}

	/**
	 * redirect back to the TikTok settings tab
	 */
	public static function redirectToGeneralSettings($params = array()){

		$query = "";

		if(empty($params) == false)
			$query = http_build_query($params);

		$query .= "#tab=integrations";

		$urlRedirect = HelperUC::getViewUrl(GlobalsUnlimitedElements::VIEW_SETTINGS_ELEMENTOR, $query);
		UniteFunctionsUC::redirectToUrl($urlRedirect);

		exit();
	}

	/**
	 * save the tokens TikTok sent back through connect.php
	 */
	public static function saveConnectData($data){

		$error = UniteFunctionsUC::getVal($data, "error");

		if(empty($error) == false){
			$description = UniteFunctionsUC::getVal($data, "error_description");

			if(empty($description) == false)
				$error = $description;

			self::redirectToGeneralSettings(array(
				"tiktok_connect_error" => $error,
			));
		}

		$accessToken = UniteFunctionsUC::getVal($data, "access_token");
		$refreshToken = UniteFunctionsUC::getVal($data, "refresh_token");
		$openId = UniteFunctionsUC::getVal($data, "open_id");
		$displayName = UniteFunctionsUC::getVal($data, "display_name");
		$avatarUrl = UniteFunctionsUC::getVal($data, "avatar_url");
		$expiresIn = UniteFunctionsUC::getVal($data, "expires_in");
		$refreshExpiresIn = UniteFunctionsUC::getVal($data, "refresh_expires_in");
		$scope = UniteFunctionsUC::getVal($data, "scope");

		self::validateToken($accessToken, "access token");
		self::validateToken($refreshToken, "refresh token");

		if(empty($openId) == false && self::isTokenShape($openId) == false)
			$openId = "";

		if(is_numeric($expiresIn) == false)
			$expiresIn = 86400;

		if(is_numeric($refreshExpiresIn) == false)
			$refreshExpiresIn = 31536000;

		$arrUpdate = array();
		$arrUpdate["tiktok_access_token"] = $accessToken;
		$arrUpdate["tiktok_refresh_token"] = $refreshToken;
		$arrUpdate["tiktok_open_id"] = $openId;
		$arrUpdate["tiktok_display_name"] = sanitize_text_field($displayName);
		$arrUpdate["tiktok_avatar_url"] = esc_url_raw($avatarUrl);
		$arrUpdate["tiktok_expires"] = time() + intval($expiresIn);
		$arrUpdate["tiktok_refresh_expires"] = time() + intval($refreshExpiresIn);
		$arrUpdate["tiktok_scope"] = sanitize_text_field($scope);

		self::updateSettings($arrUpdate);
		self::clearVideoCache();

		self::redirectToGeneralSettings();
	}

	/**
	 * disconnect the account and drop the saved tokens
	 */
	public static function clearConnectData(){

		$data = self::getSavedAccessData();
		$accessToken = UniteFunctionsUC::getVal($data, "access_token");

		if(empty($accessToken) == false){
			try{
				self::revokeAccessToken($accessToken);
			}catch(Exception $e){
			}
		}

		$arrUpdate = array();
		$arrUpdate["tiktok_access_token"] = "";
		$arrUpdate["tiktok_refresh_token"] = "";
		$arrUpdate["tiktok_open_id"] = "";
		$arrUpdate["tiktok_display_name"] = "";
		$arrUpdate["tiktok_avatar_url"] = "";
		$arrUpdate["tiktok_expires"] = "";
		$arrUpdate["tiktok_refresh_expires"] = "";
		$arrUpdate["tiktok_scope"] = "";

		self::updateSettings($arrUpdate);
		self::clearVideoCache();

		self::redirectToGeneralSettings();
	}

	/**
	 * access token, refreshed when it is close to expiring
	 */
	public static function getFreshAccessData(){

		$data = self::getSavedAccessData();
		$accessToken = UniteFunctionsUC::getVal($data, "access_token");

		if(empty($accessToken))
			return($data);

		if(self::shouldRefresh($data) == false)
			return($data);

		$lock = UniteProviderFunctionsUC::getTransient(self::REFRESH_LOCK);

		if(empty($lock) == false)
			return($data);

		UniteProviderFunctionsUC::setTransient(self::REFRESH_LOCK, true, self::REFRESH_LOCK_SECONDS);

		try{
			$data = self::refreshAccessToken($data);
		}catch(Exception $e){
			$data["refresh_error"] = $e->getMessage();
		}

		return($data);
	}

	/**
	 * used by the catalog check so the token stays alive
	 */
	public static function checkRenewAccessToken(){

		self::getFreshAccessData();
	}

	/**
	 * connect button on the general settings Integrations tab
	 */
	public static function putConnectButton(){

		$urlReturn = HelperUC::getUrlAjax("save_tiktok_connect_data");
		$urlReturn = UniteFunctionsUC::encodeContent($urlReturn);
		$urlConnect = self::URL_APP_CONNECT . "?state=" . rawurlencode($urlReturn);

		$urlClear = HelperUC::getUrlAjax("clear_tiktok_connect_data");
		$urlTest = HelperUC::getViewUrl("tiktok-test");

		$error = UniteFunctionsUC::getGetVar("tiktok_connect_error", "", UniteFunctionsUC::SANITIZE_TEXT_FIELD);

		$data = self::getSavedAccessData();
		$accessToken = UniteFunctionsUC::getVal($data, "access_token");

		if(empty($accessToken) == false)
			$data = self::getFreshAccessData();

		$accessToken = UniteFunctionsUC::getVal($data, "access_token");
		$refreshError = UniteFunctionsUC::getVal($data, "refresh_error");

		if(empty($error) && empty($refreshError) == false)
			$error = $refreshError;

		if(empty($error) == false){
			?>
			<div class="tiktok-connect-error unite-color-red">
				<?php echo esc_html($error); ?>
			</div>
			<br>
			<?php
		}

		if(empty($accessToken)){
			?>
			<a href="<?php echo esc_url($urlConnect); ?>" class="unite-button-primary"><?php esc_html_e("Connect with TikTok", "unlimited-elements-for-elementor"); ?></a>
			<?php
			return;
		}

		$displayName = UniteFunctionsUC::getVal($data, "display_name");
		$text = __("TikTok is connected", "unlimited-elements-for-elementor");

		if(empty($displayName) == false)
			$text .= " " . sprintf(__("for %s", "unlimited-elements-for-elementor"), $displayName);
		?>
		<div class="tiktok-connect-message">
			<?php echo esc_html($text); ?>
		</div>
		<br>
		<a href="<?php echo esc_url($urlTest); ?>"><?php esc_html_e("View videos", "unlimited-elements-for-elementor"); ?></a>
		&nbsp;
		<a href="<?php echo esc_url($urlClear); ?>" class="unite-button-secondary"><?php esc_html_e("Clear Access Data", "unlimited-elements-for-elementor"); ?></a>
		<br><br>
		<div class="uc-tiktok-message-expire">
			<?php echo esc_html(self::getExpiresText($data)); ?>
		</div>
		<br>
		<a href="<?php echo esc_url($urlConnect); ?>"><?php esc_html_e("Connect a different account", "unlimited-elements-for-elementor"); ?></a>
		<?php
	}

	/**
	 * the access token lasts a day, so renew it before the feed runs
	 */
	private static function shouldRefresh($data){

		$refreshToken = UniteFunctionsUC::getVal($data, "refresh_token");

		if(empty($refreshToken))
			return(false);

		$refreshExpires = UniteFunctionsUC::getVal($data, "refresh_expires");

		if(empty($refreshExpires) == false && is_numeric($refreshExpires) && intval($refreshExpires) < time())
			return(false);

		$expires = UniteFunctionsUC::getVal($data, "expires");

		if(empty($expires) || is_numeric($expires) == false)
			return(true);

		$diff = intval($expires) - time();

		if($diff > self::RENEW_BEFORE_SECONDS)
			return(false);

		return(true);
	}

	/**
	 * ask connect.php for a new access token
	 */
	private static function refreshAccessToken($data){

		$refreshToken = UniteFunctionsUC::getVal($data, "refresh_token");

		$response = self::postConnect(array(
			"refresh_token" => $refreshToken,
		));

		$accessToken = UniteFunctionsUC::getVal($response, "access_token");
		self::validateToken($accessToken, "access token");

		$newRefreshToken = UniteFunctionsUC::getVal($response, "refresh_token");

		if(empty($newRefreshToken))
			$newRefreshToken = $refreshToken;

		$expiresIn = UniteFunctionsUC::getVal($response, "expires_in");
		$refreshExpiresIn = UniteFunctionsUC::getVal($response, "refresh_expires_in");

		if(is_numeric($expiresIn) == false)
			$expiresIn = 86400;

		$arrUpdate = array();
		$arrUpdate["tiktok_access_token"] = $accessToken;
		$arrUpdate["tiktok_refresh_token"] = $newRefreshToken;
		$arrUpdate["tiktok_expires"] = time() + intval($expiresIn);

		if(is_numeric($refreshExpiresIn))
			$arrUpdate["tiktok_refresh_expires"] = time() + intval($refreshExpiresIn);

		$openId = UniteFunctionsUC::getVal($response, "open_id");

		if(empty($openId) == false)
			$arrUpdate["tiktok_open_id"] = $openId;

		$scope = UniteFunctionsUC::getVal($response, "scope");

		if(empty($scope) == false)
			$arrUpdate["tiktok_scope"] = sanitize_text_field($scope);

		self::updateSettings($arrUpdate);

		return(self::getSavedAccessData());
	}

	/**
	 * tell TikTok this site no longer has access
	 */
	private static function revokeAccessToken($accessToken){

		self::postConnect(array(
			"revoke_token" => $accessToken,
		));
	}

	/**
	 * post to the connect endpoint that holds the client secret
	 */
	private static function postConnect($params){

		$request = UEHttp::make();
		$request->asForm();
		$request->acceptJson();

		$response = $request->post(self::URL_APP_CONNECT, $params);
		$body = $response->body();
		$data = json_decode($body, true);

		if(is_array($data) == false){
			$message = trim(wp_strip_all_tags($body));

			if(empty($message))
				$message = "TikTok connect request failed.";

			UniteFunctionsUC::throwError($message);
		}

		$error = UniteFunctionsUC::getVal($data, "error");

		if(empty($error) == false){
			if(is_string($error) == false)
				$error = "TikTok connect request failed.";

			UniteFunctionsUC::throwError($error);
		}

		return($data);
	}

	/**
	 * write token fields into general settings
	 */
	private static function updateSettings($arrUpdate){

		HelperUC::$operations->updateUnlimitedElementsGeneralSettings($arrUpdate);
	}

	/**
	 * cover urls expire, so drop the cached list when the account changes
	 */
	public static function clearVideoCache(){

		for($count = 1; $count <= 20; $count++)
			delete_transient("uelm_tiktok_videos_" . $count);
	}

	/**
	 * tokens are opaque strings from TikTok
	 */
	private static function validateToken($token, $label){

		UniteFunctionsUC::validateNotEmpty($token, "TikTok " . $label);

		if(self::isTokenShape($token) == false)
			UniteFunctionsUC::throwError("The TikTok " . $label . " is incorrect");
	}

	/**
	 * reject whitespace and markup, keep the rest of the token
	 */
	private static function isTokenShape($token){

		if(is_string($token) == false)
			return(false);

		if(strlen($token) > 2000)
			return(false);

		if(preg_match('/[\s<>"\']/', $token))
			return(false);

		return(true);
	}

	/**
	 * short status line under the connect button
	 */
	private static function getExpiresText($data){

		$expires = UniteFunctionsUC::getVal($data, "expires");
		$refreshExpires = UniteFunctionsUC::getVal($data, "refresh_expires");

		$text = __("The access token renews automatically.", "unlimited-elements-for-elementor");

		if(empty($refreshExpires) == false && is_numeric($refreshExpires)){
			$days = ceil((intval($refreshExpires) - time()) / 86400);

			if($days < 0)
				$text = __("The refresh token has expired. Connect TikTok again.", "unlimited-elements-for-elementor");
			else
				$text .= " " . sprintf(__("The refresh token expires in %d days.", "unlimited-elements-for-elementor"), $days);
		}

		if(empty($expires) == false && is_numeric($expires) && intval($expires) < time())
			$text = __("The access token has expired. It will renew the next time the feed loads.", "unlimited-elements-for-elementor");

		return($text);
	}

}
