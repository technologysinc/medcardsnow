<?php
/**
 * @package Unlimited Elements
 * @author unlimited-elements.com
 * @copyright (C) 2021 Unlimited Elements, All Rights Reserved.
 * @license GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class UniteCreatorAbly{

	const MASK = "********";
	const TOKEN_TTL_MS = 3600000;

	public function __construct(){

		add_action("uc_custom_front_ajax_action", array($this, "onFrontAjax"));
	}

	/**
	 * front ajax: mint an Ably token. the api key stays in WordPress.
	 */
	public function onFrontAjax(){

		$action = UniteFunctionsUC::getPostGetVariable("ably_action", "", UniteFunctionsUC::SANITIZE_KEY);

		if($action !== "token")
			return;

		try{
			$token = $this->createToken();
			nocache_headers();
			wp_send_json($token);
		}catch(Exception $e){
			HelperUC::ajaxResponseError($e->getMessage());
		}
	}

	/**
	 * ask Ably for a one-hour token limited to this site's live channels.
	 * the same token can publish objects (Live Poll) or enter presence (Live Viewers).
	 */
	private function createToken(){

		$key = HelperProviderCoreUC_EL::getGeneralSetting("ably_api_key");
		$key = trim($key);

		if($key === "" || $key === self::MASK)
			UniteFunctionsUC::throwError("Ably API key is not set. Add it in Unlimited Elements settings, Integrations.");

		$parts = explode(":", $key, 2);
		$keyName = $parts[0];

		if(count($parts) !== 2 || strpos($keyName, ".") === false)
			UniteFunctionsUC::throwError("Ably API key is not valid. Paste the full key from ably.com.");

		$clientId = UniteFunctionsUC::getPostGetVariable("client_id", "", UniteFunctionsUC::SANITIZE_KEY);

		if(preg_match("/^v-[a-z0-9]{4,40}$/", $clientId) !== 1)
			UniteFunctionsUC::throwError("Ably client id is not valid.");

		$host = wp_parse_url(home_url(), PHP_URL_HOST);
		$host = preg_replace("/[^A-Za-z0-9.-]/", "", (string) $host);

		if($host === "")
			UniteFunctionsUC::throwError("Could not resolve the site host for the Ably channel.");

		$channel = "live-poll:" . $host . ":*";
		$capability = array(
			$channel => array("publish", "subscribe", "presence", "history", "object-subscribe", "object-publish"),
		);

		$url = "https://rest.ably.io/keys/" . rawurlencode($keyName) . "/requestToken";

		$response = wp_remote_post($url, array(
			"timeout" => 15,
			"headers" => array(
				"Authorization" => "Basic " . base64_encode($key),
				"Content-Type" => "application/json",
			),
			"body" => wp_json_encode(array(
				"keyName" => $keyName,
				"ttl" => self::TOKEN_TTL_MS,
				"clientId" => $clientId,
				"capability" => wp_json_encode($capability),
				"timestamp" => (int) round(microtime(true) * 1000),
			)),
		));

		if(is_wp_error($response))
			UniteFunctionsUC::throwError("Could not reach Ably.");

		$code = wp_remote_retrieve_response_code($response);
		$body = json_decode(wp_remote_retrieve_body($response), true);

		if($code < 200 || $code >= 300 || empty($body["token"])){
			$message = "Ably refused the token request.";

			if(is_array($body) && isset($body["error"]["message"]))
				$message = $body["error"]["message"];

			UniteFunctionsUC::throwError($message);
		}

		return $body;
	}

	/**
	 * javascript printed by {{ putAblyConnectFunction() }} inside a widget script.
	 * the widget then calls ueAblyConnect({ room, mode, onReady, onError }).
	 * mode defaults to "objects". pass "presence" for a viewers counter.
	 * room must be live-poll:{hostname}:something, which is what the token allows.
	 */
	public static function getConnectFunctionJs(){

		return <<<'JS'
if(typeof window.ueAblyConnect != "function"){
window.ueAblyConnect = function(options){
	options = options || {};

	var room = options.room;
	var mode = options.mode || "objects";
	var onReady = options.onReady || function(){};
	var onError = options.onError || function(){};

	if(mode !== "objects" && mode !== "presence"){
		onError("Unknown Ably mode");
		return;
	}

	function fail(err){
		var message = (err && err.message) ? err.message : String(err || "Ably connection failed");
		onError(message);
	}

	function loadScript(src){
		return new Promise(function(resolve, reject){
			var existing = document.querySelector("script[data-ue-ably='" + src + "']");

			if(existing){
				if(existing.getAttribute("data-ue-loaded") == "1")
					resolve();
				else
					existing.addEventListener("load", function(){ resolve(); });
				return;
			}

			var script = document.createElement("script");
			script.src = src;
			script.async = false;
			script.setAttribute("data-ue-ably", src);
			script.onload = function(){
				script.setAttribute("data-ue-loaded", "1");
				resolve();
			};
			script.onerror = function(){
				reject(new Error("Could not load " + src));
			};
			document.head.appendChild(script);
		});
	}

	function openChannel(client){
		if(mode === "presence"){
			var presenceChannel = client.channels.get(room, { modes: ["PRESENCE", "PRESENCE_SUBSCRIBE"] });

			presenceChannel.presence.enter(options.data || {}).then(function(){
				return presenceChannel.presence.get();
			}).then(function(members){
				onReady(members, presenceChannel, client);
			}).catch(fail);
			return;
		}

		var channel = client.channels.get(room, { modes: ["OBJECT_SUBSCRIBE", "OBJECT_PUBLISH"] });

		channel.object.get().then(function(root){
			onReady(root, channel, client);
		}).catch(fail);
	}

	function connect(){
		if(window.ueAblyClient){
			openChannel(window.ueAblyClient);
			return;
		}

		if(window.ueAblyClientPromise){
			window.ueAblyClientPromise.then(openChannel).catch(fail);
			return;
		}

		var clientId = "v-" + Math.random().toString(36).substring(2, 12);

		window.ueAblyClientPromise = Promise.resolve().then(function(){
			var client = new Ably.Realtime({
				clientId: clientId,
				authCallback: function(tokenParams, callback){
					jQuery.post(window.location.pathname + window.location.search, {
						ucfrontajaxaction: "custom",
						ably_action: "token",
						client_id: tokenParams.clientId || clientId
					}).done(function(token){
						if(token && token.token)
							callback(null, token);
						else
							callback((token && token.message) ? token.message : "Token request failed", null);
					}).fail(function(){
						callback("Token request failed", null);
					});
				},
				plugins: { LiveObjects: AblyLiveObjectsPlugin.LiveObjects }
			});

			window.ueAblyClient = client;
			return client;
		});

		window.ueAblyClientPromise.then(openChannel).catch(fail);
	}

	loadScript("https://cdn.jsdelivr.net/npm/ably@2.29.0/build/ably.min.js").then(function(){
		return loadScript("https://cdn.jsdelivr.net/npm/ably@2.29.0/build/liveobjects.umd.min.js");
	}).then(connect).catch(fail);
};
}
JS;
	}

}

new UniteCreatorAbly();
