<?php
/**
 * @package Unlimited Elements
 * @author unlimited-elements.com
 * @copyright (C) 2021 Unlimited Elements, All Rights Reserved.
 * @license GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 * */
if ( ! defined( 'ABSPATH' ) ) exit;


class UELM_TikTokFeed{

	private static $stylePrinted = false;

	/**
	 * videos for the catalog widget template
	 */
	public static function getTemplateData($maxCount = 8){

		$data = array();
		$data["videos"] = array();
		$data["message"] = "";

		try{
			$api = new UELM_TikTokAPI();
			$videos = $api->getVideos($maxCount);
		}catch(Exception $e){
			$message = $e->getMessage();

			if(current_user_can("manage_options") == false)
				$message = __("TikTok feed is not available.", "unlimited-elements-for-elementor");

			$data["message"] = $message;

			return($data);
		}

		foreach($videos as $video){
			if(is_array($video) == false)
				continue;

			$item = array();
			$item["title"] = self::formatTitle($video);
			$item["cover_image_url"] = self::httpsUrl(UniteFunctionsUC::getVal($video, "cover_image_url"));
			$item["share_url"] = self::httpsUrl(UniteFunctionsUC::getVal($video, "share_url"));
			$item["duration"] = self::formatDuration(UniteFunctionsUC::getVal($video, "duration"));

			if(empty($item["cover_image_url"]) && empty($item["share_url"]))
				continue;

			$data["videos"][] = $item;
		}

		if(empty($data["videos"]))
			$data["message"] = __("No TikTok videos found.", "unlimited-elements-for-elementor");

		return($data);
	}

	/**
	 * print the video grid
	 */
	public static function render($maxCount = 12){

		self::printStyle();

		try{
			$api = new UELM_TikTokAPI();
			$videos = $api->getVideos($maxCount);
		}catch(Exception $e){
			$message = $e->getMessage();

			if(current_user_can("manage_options") == false)
				$message = __("TikTok feed is not available.", "unlimited-elements-for-elementor");

			echo '<p class="uelm-tiktok-feed-message">' . esc_html($message) . '</p>';
			return;
		}

		if(empty($videos)){
			echo '<p class="uelm-tiktok-feed-message">' . esc_html__("No TikTok videos found.", "unlimited-elements-for-elementor") . '</p>';
			return;
		}

		echo '<div class="uelm-tiktok-feed">';

		foreach($videos as $video){
			if(is_array($video) == false)
				continue;

			self::renderVideo($video);
		}

		echo '</div>';
	}

	/**
	 * one tile
	 */
	private static function renderVideo($video){

		$title = self::formatTitle($video);
		$cover = self::httpsUrl(UniteFunctionsUC::getVal($video, "cover_image_url"));
		$shareUrl = self::httpsUrl(UniteFunctionsUC::getVal($video, "share_url"));
		$duration = self::formatDuration(UniteFunctionsUC::getVal($video, "duration"));

		$inner = '<span class="uelm-tiktok-cover">';

		if(empty($cover) == false)
			$inner .= '<img src="' . esc_url($cover) . '" alt="' . esc_attr($title) . '" loading="lazy">';

		if(empty($duration) == false)
			$inner .= '<span class="uelm-tiktok-duration">' . esc_html($duration) . '</span>';

		$inner .= '</span>';

		if(empty($title) == false)
			$inner .= '<span class="uelm-tiktok-title">' . esc_html($title) . '</span>';

		if(empty($shareUrl)){
			echo '<div class="uelm-tiktok-item">' . $inner . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			return;
		}

		echo '<a class="uelm-tiktok-item" href="' . esc_url($shareUrl) . '" target="_blank" rel="noopener noreferrer">' . $inner . '</a>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	/**
	 * title, or the description when TikTok leaves the title empty
	 */
	private static function formatTitle($video){

		$title = UniteFunctionsUC::getVal($video, "title");

		if(empty($title))
			$title = UniteFunctionsUC::getVal($video, "video_description");

		$title = wp_strip_all_tags($title);
		$title = trim(preg_replace('/\s+/', ' ', $title));

		if(function_exists("mb_strlen") && mb_strlen($title) > 90)
			$title = mb_substr($title, 0, 87) . "...";
		elseif(strlen($title) > 90)
			$title = substr($title, 0, 87) . "...";

		return($title);
	}

	/**
	 * seconds to m:ss
	 */
	private static function formatDuration($seconds){

		$seconds = intval($seconds);

		if($seconds < 1)
			return("");

		$minutes = floor($seconds / 60);
		$remain = $seconds % 60;

		return($minutes . ":" . str_pad((string) $remain, 2, "0", STR_PAD_LEFT));
	}

	/**
	 * only keep https links TikTok returned
	 */
	private static function httpsUrl($url){

		if(is_string($url) == false)
			return("");

		if(strpos($url, "https://") !== 0)
			return("");

		return($url);
	}

	/**
	 * grid styles, once per page
	 */
	private static function printStyle(){

		if(self::$stylePrinted == true)
			return;

		self::$stylePrinted = true;
		?>
		<style>
			.uelm-tiktok-feed{display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:16px}
			.uelm-tiktok-item{display:block;color:inherit;text-decoration:none}
			.uelm-tiktok-cover{display:block;position:relative}
			.uelm-tiktok-item img{width:100%;aspect-ratio:9/16;object-fit:cover;display:block;border-radius:8px;background:#111}
			.uelm-tiktok-title{display:block;margin-top:8px;font-size:14px;line-height:1.35}
			.uelm-tiktok-duration{position:absolute;right:8px;bottom:8px;background:rgba(0,0,0,.72);color:#fff;font-size:12px;line-height:1;padding:4px 6px;border-radius:4px}
			.uelm-tiktok-feed-message{margin:0}
		</style>
		<?php
	}

}
