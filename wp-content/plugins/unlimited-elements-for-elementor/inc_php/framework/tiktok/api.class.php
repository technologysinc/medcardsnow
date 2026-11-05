<?php
/**
 * @package Unlimited Elements
 * @author unlimited-elements.com
 * @copyright (C) 2021 Unlimited Elements, All Rights Reserved.
 * @license GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 * */
if ( ! defined( 'ABSPATH' ) ) exit;


class UELM_TikTokAPI{

	const URL_VIDEO_LIST = "https://open.tiktokapis.com/v2/video/list/";
	const CACHE_SECONDS = 7200;
	const VIDEO_FIELDS = "id,title,video_description,cover_image_url,share_url,embed_link,duration";

	/**
	 * public videos for the connected account, newest first
	 */
	public function getVideos($maxCount = 12){

		$maxCount = self::normalizeMaxCount($maxCount);
		$cached = get_transient(self::getCacheKey($maxCount));

		if(is_array($cached))
			return($cached);

		$data = UELM_HelperTikTok::getFreshAccessData();
		$accessToken = UniteFunctionsUC::getVal($data, "access_token");

		if(empty($accessToken))
			UniteFunctionsUC::throwError("Connect TikTok in Unlimited Elements, General Settings, Integrations tab.");

		try{
			$videos = $this->requestVideos($accessToken, $maxCount);
		}catch(Exception $e){

			$refreshError = UniteFunctionsUC::getVal($data, "refresh_error");
			$message = $e->getMessage();

			if(empty($refreshError) == false)
				$message .= " " . $refreshError;

			UniteFunctionsUC::throwError($message);
		}

		set_transient(self::getCacheKey($maxCount), $videos, self::CACHE_SECONDS);

		return($videos);
	}

	/**
	 * one page from video.list, 20 is the TikTok maximum
	 */
	private function requestVideos($accessToken, $maxCount){

		$request = UEHttp::make();
		$request->asJson();
		$request->acceptJson();
		$request->withHeaders(array(
			"Authorization" => "Bearer " . $accessToken,
		));
		$request->withQuery(array(
			"fields" => self::VIDEO_FIELDS,
		));

		$response = $request->post(self::URL_VIDEO_LIST, array(
			"max_count" => $maxCount,
		));

		$body = $response->body();
		$data = json_decode($body, true);

		if(is_array($data) == false)
			UniteFunctionsUC::throwError("TikTok returned an unreadable video list.");

		self::throwOnApiError($data);

		$videos = array();

		if(isset($data["data"]["videos"]) && is_array($data["data"]["videos"]))
			$videos = $data["data"]["videos"];

		return($videos);
	}

	/**
	 * TikTok sends error.code = ok on success
	 */
	private static function throwOnApiError($data){

		$error = UniteFunctionsUC::getVal($data, "error");

		if(is_array($error) == false)
			return;

		$code = UniteFunctionsUC::getVal($error, "code");

		if(empty($code) || $code == "ok")
			return;

		$message = UniteFunctionsUC::getVal($error, "message");

		if(empty($message))
			$message = $code;

		UniteFunctionsUC::throwError("TikTok: " . $message);
	}

	/**
	 * cache key for this page size
	 */
	private static function getCacheKey($maxCount){

		return("uelm_tiktok_videos_" . intval($maxCount));
	}

	/**
	 * TikTok allows 1 to 20 videos per request
	 */
	public static function normalizeMaxCount($maxCount){

		$maxCount = intval($maxCount);

		if($maxCount < 1)
			$maxCount = 8;

		if($maxCount > 20)
			$maxCount = 20;

		return($maxCount);
	}

}
