<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Rule catalogue.
 *
 * Pattern rules live in a nowdoc, one per line, fields separated by " ::: ":
 *   ID ::: severity ::: view ::: lang ::: category ::: title ::: regex ::: description ::: fix
 *
 * view: "nostr" = comments AND string contents blanked (safe for function-call patterns)
 *       "code"  = only comments blanked (string contents kept, for SQL / keys / URLs / secrets)
 * lang: php | js | ht | any (php + js + cfg)
 */
class CS_Rules {

	/** @var array|null */
	private static $parsed = null;

	private static function source() {
		return <<<'RULES'
# ---------------------------------------------------------------- PHP: code execution
PHP-EVAL ::: critical ::: nostr ::: php ::: Code execution ::: eval() call ::: ~(?<![\w>:$])eval\s*\(~i ::: eval() executes arbitrary PHP. It is the backbone of most backdoors and a direct code-injection risk. ::: Remove eval(); use real functions, closures or data structures.
PHP-ASSERT ::: high ::: nostr ::: php ::: Code execution ::: assert() on a variable ::: ~(?<![\w>:$])assert\s*\(\s*\$~i ::: Before PHP 8, assert() evaluates string arguments as code. ::: Never pass dynamic strings to assert().
PHP-CREATE-FUNCTION ::: high ::: nostr ::: php ::: Code execution ::: create_function() (removed in PHP 8) ::: ~(?<![\w>:$])create_function\s*\(~i ::: create_function() is an eval wrapper and was removed in PHP 8.0, so it is a fatal error on current hosts. ::: Replace with an anonymous function (closure).
PHP-PREG-E ::: high ::: code ::: php ::: Code execution ::: preg_replace() with /e modifier ::: ~preg_replace\s*\(\s*(['"])[^'"]*[^\w\s'"][imsxuADSUXJ]*e[imsxuADSUXJ]*\1~ ::: The /e modifier evaluates the replacement as PHP and was removed in PHP 7. ::: Use preg_replace_callback().
PHP-CMD-INPUT ::: critical ::: nostr ::: php ::: Code execution ::: Shell command built from request data ::: ~(?<![\w>:$])(?:system|exec|shell_exec|passthru|popen|proc_open|pcntl_exec)\s*\((?:(?!;).){0,300}?\$_(?:GET|POST|REQUEST|COOKIE|FILES)\b~is ::: Visitor-controlled input reaches a shell command, which allows remote command execution. ::: Never pass request data to a shell. Avoid shell calls; if unavoidable use fixed commands and escapeshellarg().
PHP-CMD-EXEC ::: medium ::: nostr ::: php ::: Code execution ::: Shell / process execution function ::: ~(?<![\w>:$])(?:system|shell_exec|passthru|popen|proc_open|pcntl_exec|exec)\s*\(~i ::: Process execution is often disabled by hosts (fatal "undefined function") and is a favourite post-exploitation tool. ::: Make sure no argument is attacker-influenced; prefer native PHP / WordPress APIs.
PHP-BACKTICK ::: high ::: nostr ::: php ::: Code execution ::: Backtick shell operator with a variable ::: ~`[^`;\n]{0,200}\$\w+[^`;\n]{0,200}`~ ::: Backticks run a shell command with interpolated data. ::: Remove, or use a fixed command with escapeshellarg().
PHP-INPUT-CALLED ::: high ::: nostr ::: php ::: Code execution ::: Request value called as a function ::: ~(?<![\w>:$])\$_(?:GET|POST|REQUEST|COOKIE)\s*\[[^\]]*\]\s*\(~ ::: Classic webshell pattern: the visitor chooses which function runs. ::: Remove it.
PHP-CALLBACK-INPUT ::: high ::: nostr ::: php ::: Code execution ::: Callback chosen from request data ::: ~(?<![\w>:$])(?:call_user_func(?:_array)?|array_map|array_filter|array_walk|usort|uasort|uksort|register_shutdown_function|forward_static_call(?:_array)?|ob_start)\s*\(\s*\$_(?:GET|POST|REQUEST|COOKIE)~i ::: The visitor decides which PHP function is executed. ::: Map input to an allow-list of fixed callbacks.
PHP-UNSERIALIZE-INPUT ::: critical ::: nostr ::: php ::: Object injection ::: unserialize() on request data ::: ~(?<![\w>:$])unserialize\s*\((?:(?!;).){0,200}?\$_(?:GET|POST|REQUEST|COOKIE)\b~is ::: PHP object injection can lead to remote code execution through gadget chains. ::: Use json_decode(), or unserialize($x, ['allowed_classes' => false]).
PHP-UNSERIALIZE ::: medium ::: nostr ::: php ::: Object injection ::: unserialize() call ::: ~(?<![\w>:$])unserialize\s*\(~i ::: If the data is ever user-influenced, unserialize() enables object injection. ::: Use JSON, or pass allowed_classes => false.
PHP-EXTRACT-INPUT ::: high ::: nostr ::: php ::: Variable injection ::: extract() on request data ::: ~(?<![\w>:$])extract\s*\(\s*\$_(?:GET|POST|REQUEST|COOKIE|SERVER|FILES)~i ::: Lets a visitor overwrite any local variable. ::: Read only the specific keys you need.
PHP-PARSE-STR ::: medium ::: nostr ::: php ::: Variable injection ::: parse_str() without a result array ::: ~(?<![\w>:$])parse_str\s*\(\s*[^,;()]+\)~i ::: The one-argument form was removed in PHP 8 (fatal error) and created variables from input. ::: Pass a second argument: parse_str($str, $result).
PHP-VARVAR ::: low ::: nostr ::: php ::: Code quality ::: Variable variable ($$name) ::: ~(?<![\w$])\$\$\w+~ ::: Hard to audit and commonly used to hide malicious code. ::: Use an array instead.
PHP-AUTOLOAD ::: high ::: nostr ::: php ::: PHP compatibility ::: __autoload() (removed in PHP 8) ::: ~function\s+__autoload\s*\(~i ::: __autoload() was removed in PHP 8.0 – fatal error. ::: Use spl_autoload_register().
# ---------------------------------------------------------------- PHP: files / includes
PHP-INCLUDE-INPUT ::: critical ::: nostr ::: php ::: File inclusion ::: include/require using request data ::: ~(?<![\w>:$])(?:include|require)(?:_once)?\b(?:(?!;).){0,200}?\$_(?:GET|POST|REQUEST|COOKIE|FILES)\b~is ::: Local / remote file inclusion leads to code execution. ::: Whitelist the allowed files; never build include paths from input.
PHP-INCLUDE-REMOTE ::: critical ::: code ::: php ::: File inclusion ::: include/require of a remote URL ::: ~(?<![\w>:$])(?:include|require)(?:_once)?\s*\(?\s*['"]https?://~i ::: Remote code inclusion. ::: Ship the file with the plugin.
PHP-FILE-INPUT ::: high ::: nostr ::: php ::: File access ::: Filesystem function using request data ::: ~(?<![\w>:$])(?:unlink|rmdir|rename|copy|file_put_contents|fopen|readfile|file_get_contents|file|fwrite|mkdir|touch|chmod|scandir|glob|highlight_file|show_source|parse_ini_file|simplexml_load_file|zip_open)\s*\((?:(?!;).){0,200}?\$_(?:GET|POST|REQUEST|COOKIE)\b~is ::: Path traversal: arbitrary file read, write or delete. ::: Validate against an allow-list, use basename()/realpath(), and check capabilities and nonce.
PHP-UPLOAD ::: medium ::: nostr ::: php ::: File upload ::: move_uploaded_file() used directly ::: ~(?<![\w>:$])move_uploaded_file\s*\(~i ::: Hand-rolled upload handling frequently misses extension / MIME validation. ::: Use wp_handle_upload() with wp_check_filetype_and_ext(), and verify nonce + capability.
# ---------------------------------------------------------------- PHP: database
SQL-INPUT ::: critical ::: nostr ::: php ::: SQL injection ::: $wpdb query with request data and no prepare() ::: ~\$wpdb\s*->\s*(?:query|get_results|get_var|get_row|get_col)\s*\(\s*(?!\$wpdb\s*->\s*prepare\b)(?:(?!;).){0,500}?\$_(?:GET|POST|REQUEST|COOKIE)\b~is ::: Request data is placed straight into a SQL statement. ::: Use $wpdb->prepare() with %s / %d placeholders.
SQL-CONCAT ::: high ::: nostr ::: php ::: SQL injection ::: $wpdb query built by concatenation / interpolation ::: ~\$wpdb\s*->\s*(?:query|get_results|get_var|get_row|get_col)\s*\(\s*(?!\$wpdb\s*->\s*prepare\b)(?:(?!;).){0,500}?(?:\.\s*\$(?!wpdb\b|table|tbl|this\s*->\s*(?:table|prefix))|"[^"]*\$(?!wpdb\b|table|tbl|this\s*->\s*(?:table|prefix))\w)~is ::: A variable is concatenated into SQL without prepare(). Safe only if the value is provably an integer or escaped. ::: Switch to $wpdb->prepare() placeholders.
SQL-BARE-VAR ::: medium ::: nostr ::: php ::: SQL injection ::: $wpdb query passed as a bare variable ::: ~\$wpdb\s*->\s*(?:query|get_results|get_var|get_row|get_col)\s*\(\s*\$(?!wpdb\b)\w+\s*[,)]~ ::: The SQL string is built elsewhere – verify it was produced with prepare(). ::: Trace the variable; build it with $wpdb->prepare().
SQL-PREPARE-INTERP ::: high ::: code ::: php ::: SQL injection ::: Variable interpolated into a prepare() query ::: ~->\s*prepare\s*\(\s*"[^"]*(?<!\\)\$(?!wpdb\b|table|tbl|this\s*->\s*(?:table|prefix))\w~ ::: prepare() only protects the placeholders; interpolated variables are still injectable. ::: Replace every interpolated value with a placeholder.
SQL-DDL ::: medium ::: code ::: php ::: Database ::: Schema-changing SQL executed through $wpdb->query() ::: ~\$wpdb\s*->\s*query\s*\(\s*["']\s*(?:DROP|TRUNCATE|ALTER)\b~i ::: Schema changes at runtime are risky and may fail on restricted DB users. ::: Run schema changes once on activation with dbDelta().
WPDB-ESCAPE ::: medium ::: nostr ::: php ::: Deprecated ::: $wpdb->escape() (deprecated) ::: ~\$wpdb\s*->\s*escape\s*\(~i ::: Deprecated since WP 3.6 and not a safe substitute for prepare(). ::: Use $wpdb->prepare() / esc_sql().
# ---------------------------------------------------------------- PHP: XSS / input handling
XSS-ECHO-INPUT ::: high ::: nostr ::: php ::: Cross-site scripting ::: Request data echoed without escaping ::: ~(?:\becho\b|\bprint\b|<\?=)(?:(?!esc_\w+|sanitize_\w+|intval|absint|wp_kses\w*|htmlspecialchars|htmlentities|\(\s*int\s*\)|floatval|wp_json_encode|;).){0,200}?\$_(?:GET|POST|REQUEST|COOKIE)\b~is ::: Reflected XSS. ::: Escape on output: esc_html(), esc_attr(), esc_url(), wp_kses_post().
XSS-ECHO-SERVER ::: medium ::: code ::: php ::: Cross-site scripting ::: $_SERVER value echoed without escaping ::: ~(?:\becho\b|\bprint\b|<\?=)(?:(?!esc_\w+|sanitize_\w+|htmlspecialchars|htmlentities|wp_kses\w*|;).){0,200}?\$_SERVER\s*\[\s*['"](?:PHP_SELF|REQUEST_URI|QUERY_STRING|HTTP_REFERER|HTTP_USER_AGENT|HTTP_HOST|SERVER_NAME)['"]~is ::: These values are attacker-controlled. ::: Wrap in esc_url() / esc_attr(), or use admin_url()/ get_permalink().
INPUT-RAW ::: low ::: nostr ::: php ::: Input handling ::: Request value assigned without sanitising ::: ~=\s*\$_(?:GET|POST|REQUEST|COOKIE)\s*\[[^\]]*\]\s*;~ ::: Raw input is stored in a variable; check it is unslashed and sanitised before use. ::: wp_unslash() then sanitize_text_field() / absint() / esc_url_raw() etc.
INPUT-STORED ::: medium ::: nostr ::: php ::: Input handling ::: Raw request data saved to the database ::: ~(?<![\w>:$])(?:update_option|add_option|update_site_option|update_user_meta|update_post_meta|add_post_meta|update_term_meta|set_transient|wp_insert_post|wp_update_post)\s*\((?:(?!esc_\w+|sanitize_\w+|intval|absint|wp_kses\w*|;).){0,250}?\$_(?:GET|POST|REQUEST)\b~is ::: Unsanitised input is persisted (stored XSS / option tampering). ::: Sanitise before saving and check nonce + capability.
REDIRECT-INPUT ::: medium ::: nostr ::: php ::: Redirect ::: Redirect target taken from request data ::: ~(?<![\w>:$])(?:wp_redirect|header)\s*\((?:(?!esc_url\w*|;).){0,150}?\$_(?:GET|POST|REQUEST)\b~is ::: Open redirect. ::: Use wp_safe_redirect() with an allow-list.
SPOOFABLE-HEADER ::: low ::: code ::: php ::: Input handling ::: Spoofable client-IP header trusted ::: ~\$_SERVER\s*\[\s*['"]HTTP_(?:X_FORWARDED_FOR|CLIENT_IP|X_REAL_IP|X_FORWARDED_HOST)['"]~ ::: These headers can be forged by visitors. ::: Only trust them behind a known proxy; otherwise use REMOTE_ADDR.
# ---------------------------------------------------------------- PHP: auth / WordPress specifics
AUTH-CALLS ::: medium ::: nostr ::: php ::: Authentication ::: Login / identity switching function ::: ~(?<![\w>:$])(?:wp_set_auth_cookie|wp_set_current_user|wp_signon)\s*\(~i ::: Backdoors and auth-bypass bugs live here. Confirm the call is protected by real credential checks. ::: Review the surrounding logic carefully.
USER-CREATE-INPUT ::: high ::: nostr ::: php ::: Authentication ::: User created/updated from request data ::: ~(?<![\w>:$])(?:wp_insert_user|wp_create_user|wp_update_user)\s*\((?:(?!;).){0,300}?\$_(?:GET|POST|REQUEST)\b~is ::: Privilege escalation if role / caps can be supplied by the visitor. ::: Force a safe role and require a capability + nonce.
ADMIN-ROLE-HARDCODE ::: medium ::: code ::: php ::: Authentication ::: Code assigns the administrator role ::: ~['"]role['"]\s*=>\s*['"]administrator['"]~i ::: Creating admins programmatically is a typical backdoor behaviour. ::: Verify it is intentional and gated.
SSL-VERIFY-OFF ::: medium ::: code ::: php ::: Transport security ::: TLS certificate verification disabled ::: ~(?:['"]sslverify['"]\s*=>\s*false|CURLOPT_SSL_VERIFY(?:PEER|HOST)\s*,\s*(?:false|0)\b|https_ssl_verify['"]\s*,\s*['"]__return_false)~i ::: Allows man-in-the-middle attacks on remote requests. ::: Remove; fix the server CA bundle instead.
SSRF-INPUT ::: high ::: nostr ::: php ::: SSRF ::: Remote request to a URL from request data ::: ~(?<![\w>:$])(?:wp_remote_(?:get|post|request|head)|curl_init|file_get_contents|fsockopen)\s*\(\s*\$_(?:GET|POST|REQUEST)~i ::: Server-side request forgery. ::: Allow-list hosts and use wp_safe_remote_*().
CORS-STAR ::: medium ::: code ::: php ::: Transport security ::: Access-Control-Allow-Origin: * ::: ~header\s*\(\s*['"]Access-Control-Allow-Origin:\s*\*~i ::: Any website can read the response from a visitor's browser. ::: Echo back an allow-listed origin.
SESSION-START ::: medium ::: nostr ::: php ::: Compatibility ::: session_start() in plugin code ::: ~(?<![\w>:$])session_start\s*\(~i ::: PHP sessions break page caching, REST API loopbacks and trigger the Site Health "active PHP session" warning. ::: Use transients / cookies / user meta.
ALLOW-UNFILTERED ::: high ::: code ::: php ::: Configuration ::: ALLOW_UNFILTERED_UPLOADS enabled ::: ~define\s*\(\s*['"]ALLOW_UNFILTERED_UPLOADS['"]\s*,\s*true~i ::: Disables upload type filtering sitewide. ::: Remove.
WP-DEBUG-FORCED ::: medium ::: code ::: php ::: Configuration ::: Plugin forces a debug constant ::: ~define\s*\(\s*['"](?:WP_DEBUG|WP_DEBUG_DISPLAY|SCRIPT_DEBUG|SAVEQUERIES)['"]\s*,\s*true~i ::: Debug constants belong in wp-config.php, and can leak paths / slow the site. ::: Remove from plugin code.
ERR-HIDE ::: low ::: code ::: php ::: Error handling ::: Errors are silenced globally ::: ~(?<![\w>:$])error_reporting\s*\(\s*0\s*\)|ini_set\s*\(\s*['"]display_errors['"]\s*,\s*(?:0|false|['"](?:0|off|false)['"])~i ::: Hides real problems from every other plugin as well. ::: Remove; log errors instead.
DISPLAY-ERRORS ::: medium ::: code ::: php ::: Error handling ::: display_errors switched on in code ::: ~ini_set\s*\(\s*['"]display_errors['"]\s*,\s*(?:1|true|['"](?:1|on|true|stderr)['"])~i ::: Leaks file paths to visitors and breaks AJAX/REST JSON. ::: Remove.
ERR-SUPPRESS ::: low ::: nostr ::: php ::: Error handling ::: @ error-suppression operator ::: ~(?<![\w@])@\s*(?:\$\w|[a-z_\\][\w\\]*\s*\(|(?:include|require)(?:_once)?\b)~i ::: Suppressed errors hide the exact active problems this scanner tries to surface. ::: Check the return value instead of using @.
DEBUG-LEFTOVER ::: low ::: nostr ::: php ::: Debug leftovers ::: Debug output call (var_dump / print_r) ::: ~(?<![\w>:$])(?:var_dump|debug_zval_dump|debug_print_backtrace|xdebug_\w+)\s*\(|(?<![\w>:$])print_r\s*\(\s*[^,;()]+\)\s*;~i ::: Leftover debug output corrupts responses and leaks data. ::: Remove or guard with WP_DEBUG.
PHPINFO ::: medium ::: nostr ::: php ::: Information disclosure ::: phpinfo() call ::: ~(?<![\w>:$])phpinfo\s*\(~i ::: Reveals server configuration. ::: Remove.
SLEEP ::: low ::: nostr ::: php ::: Performance ::: sleep() / usleep() ::: ~(?<![\w>:$])(?:sleep|usleep|time_nanosleep)\s*\(~i ::: Blocks the PHP worker. ::: Use async jobs / cron.
TIME-LIMIT-0 ::: low ::: code ::: php ::: Performance ::: Unlimited execution time ::: ~set_time_limit\s*\(\s*0\s*\)|ini_set\s*\(\s*['"]max_execution_time['"]\s*,\s*['"]?0~i ::: Runaway requests can exhaust workers. ::: Process in batches.
# ---------------------------------------------------------------- PHP: performance
PERF-QUERY-POSTS ::: medium ::: nostr ::: php ::: Performance ::: query_posts() ::: ~(?<![\w>:$])query_posts\s*\(~i ::: Overrides the main query and breaks pagination and other plugins. ::: Use pre_get_posts or WP_Query.
PERF-NOLIMIT ::: medium ::: code ::: php ::: Performance ::: Unbounded query (posts_per_page -1) ::: ~['"](?:posts_per_page|numberposts)['"]\s*=>\s*-1|['"]nopaging['"]\s*=>\s*true~i ::: Loads every row into memory – fatal "memory exhausted" on big sites. ::: Paginate or set a sane limit.
PERF-RAND-ORDER ::: medium ::: code ::: php ::: Performance ::: ORDER BY RAND() ::: ~['"]orderby['"]\s*=>\s*['"]rand['"]~i ::: Extremely slow on large tables. ::: Pick random IDs in PHP from a cached list.
PERF-FLUSH ::: low ::: nostr ::: php ::: Performance ::: flush_rewrite_rules() / wp_cache_flush() ::: ~(?<![\w>:$])(?:flush_rewrite_rules|wp_cache_flush)\s*\(~i ::: Expensive; harmful if it runs on every request. ::: Only call on activation / settings change.
# ---------------------------------------------------------------- PHP: crypto
WEAK-HASH ::: medium ::: nostr ::: php ::: Cryptography ::: Password hashed with md5 / sha1 ::: ~(?<![\w>:$])(?:md5|sha1)\s*\(\s*\$\w*(?:pass|pwd)~i ::: Trivially crackable. ::: Use wp_hash_password() / password_hash().
WEAK-RANDOM ::: medium ::: nostr ::: php ::: Cryptography ::: Predictable value used for a security token ::: ~\$\w*(?:token|nonce|secret|salt|passw\w*|otp|csrf|api_?key)\w*\s*=\s*(?:(?!;).){0,80}?(?<![\w>:$])(?:rand|mt_rand|uniqid|microtime|time)\s*\(~is ::: Guessable tokens can be brute-forced. ::: Use wp_generate_password() / random_bytes().
# ---------------------------------------------------------------- PHP: compatibility
LEGACY-MYSQL ::: high ::: nostr ::: php ::: PHP compatibility ::: mysql_* functions (removed in PHP 7) ::: ~(?<![\w>:$])mysql_(?:connect|pconnect|query|fetch_\w+|real_escape_string|escape_string|num_rows|close|select_db|error|errno|result|insert_id|affected_rows|free_result|data_seek|list_\w+|set_charset)\s*\(~i ::: Fatal "Call to undefined function" on PHP 7+. ::: Use $wpdb or mysqli/PDO.
REMOVED-FUNCS ::: high ::: nostr ::: php ::: PHP compatibility ::: Function removed in modern PHP ::: ~(?<![\w>:$])(?:each|ereg|eregi|ereg_replace|eregi_replace|split|spliti|sql_regcase|mcrypt_\w+|get_magic_quotes_gpc|get_magic_quotes_runtime|set_magic_quotes_runtime|money_format|restore_include_path|convert_cyr_string|hebrevc|fgetss|gzgetss|image2wbmp|jpeg2wbmp|png2wbmp|is_real|ezmlm_hash|read_exif_data|odbc_result_all)\s*\(~i ::: This function no longer exists in PHP 7/8 and causes a fatal error when reached. ::: Replace with the modern equivalent (preg_*, explode, openssl_*, foreach...).
REMOVED-CAST ::: medium ::: nostr ::: php ::: PHP compatibility ::: (real) / (unset) cast ::: ~\(\s*(?:real|unset)\s*\)\s*[\$\w(]~i ::: Removed in PHP 8.0 – parse error. ::: Use (float) / null.
DEPRECATED-8 ::: medium ::: nostr ::: php ::: PHP compatibility ::: Function deprecated in PHP 8.1 / 8.2 ::: ~(?<![\w>:$])(?:utf8_encode|utf8_decode|strftime|gmstrftime|strptime|date_sunrise|date_sunset|mhash\w*)\s*\(~i ::: Emits deprecation notices on PHP 8.1+ and will be removed. ::: Use mb_convert_encoding(), IntlDateFormatter / date() etc.
DEPRECATED-CONST ::: medium ::: nostr ::: php ::: PHP compatibility ::: Constant deprecated in PHP 8.1 ::: ~\b(?:FILTER_SANITIZE_STRING|FILTER_SANITIZE_STRIPPED|FILTER_FLAG_SCHEME_REQUIRED|FILTER_FLAG_HOST_REQUIRED|INTL_IDNA_VARIANT_2003)\b~ ::: Emits deprecation notices on PHP 8.1+. ::: Use sanitize_text_field() / FILTER_SANITIZE_FULL_SPECIAL_CHARS.
INTERP-DOLLAR-BRACE ::: low ::: nostr ::: php ::: PHP compatibility ::: "${var}" string interpolation ::: ~\$\{\s*\w+\s*[}\[]~ ::: Deprecated in PHP 8.2. ::: Use "{$var}".
HTTP-ASSET ::: low ::: code ::: php ::: Mixed content ::: Insecure http:// script / style URL ::: ~['"]http://[^'"\s]+\.(?:js|css)(?:\?[^'"\s]*)?['"]~i ::: Blocked by browsers on HTTPS sites. ::: Use https:// or a protocol-relative / local URL.
# ---------------------------------------------------------------- malware / obfuscation indicators
MAL-EVAL-DECODE ::: critical ::: nostr ::: php ::: Malware indicator ::: eval of decoded payload ::: ~(?<![\w>:$])(?:eval|assert|create_function)\s*\(\s*(?:@\s*)?(?:base64_decode|gzinflate|gzuncompress|gzdecode|str_rot13|convert_uudecode|hex2bin|rawurldecode|urldecode|strrev)\s*\(~i ::: Executes an encoded blob – the standard shape of PHP malware. ::: Treat the file as compromised: compare with a clean copy, remove, rotate credentials.
MAL-DECODE-CHAIN ::: high ::: nostr ::: php ::: Malware indicator ::: Chained decode functions ::: ~(?<![\w>:$])(?:base64_decode|gzinflate|gzuncompress|str_rot13|strrev)\s*\(\s*(?:base64_decode|gzinflate|gzuncompress|str_rot13|strrev)\s*\(~i ::: Multi-layer decoding is used to hide payloads. ::: Decode it safely offline and review, or replace the file with a clean one.
MAL-CHR-CHAIN ::: high ::: nostr ::: php ::: Malware indicator ::: Function name built from chr() calls ::: ~(?:(?<![\w>:$])chr\s*\(\s*(?:0x[0-9a-f]+|\d+)\s*\)\s*\.\s*){5,}~i ::: Builds strings character by character to evade scanners. ::: Review the file for hidden code.
MAL-HEX-STRING ::: medium ::: code ::: php ::: Malware indicator ::: Long \x hex-escaped string ::: ~(?:\\x[0-9a-f]{2}){12,}~i ::: Obfuscated string. ::: Decode and review.
MAL-LONG-BASE64 ::: medium ::: code ::: php ::: Malware indicator ::: Very long encoded string literal ::: ~['"][A-Za-z0-9+/]{600,}={0,2}['"]~ ::: Possible embedded payload. ::: Decode and review.
MAL-WEBSHELL ::: critical ::: code ::: php ::: Malware indicator ::: Known web-shell signature ::: ~(?:c99shell|r57shell|WSO\s*\d\.\d|b374k|FilesMan|Weevely|Mini\s+Shell|PHPShell|IndoXploit|Alfa\s*Shell|C100\s*Shell|Priv8\s+shell)~i ::: Matches a known web-shell family name. (Security scanners legitimately contain such strings - ignore if this is one.) ::: Quarantine and investigate.
MAL-CLOAK ::: medium ::: code ::: php ::: Malware indicator ::: Search-engine user-agent cloaking ::: ~HTTP_USER_AGENT['"]\s*\](?:(?!;).){0,150}(?:googlebot|bingbot|yandex|baiduspider|slurp)~is ::: Serving different content to crawlers is typical SEO spam injection. ::: Review immediately.
MAL-HIDDEN-LINK ::: medium ::: code ::: php ::: Malware indicator ::: Hidden off-screen link ::: ~<a\s[^>]*href=['"]?https?://[^'">]+['"]?[^>]*style=['"][^'"]*(?:display\s*:\s*none|left\s*:\s*-\d{3,}|text-indent\s*:\s*-\d{3,})~i ::: Hidden spam links. ::: Remove.
# ---------------------------------------------------------------- secrets (php + js + config)
SECRET-AWS ::: critical ::: code ::: any ::: Hard-coded secret ::: AWS access key ID ::: ~\bAKIA[0-9A-Z]{16}\b~ ::: A live-looking AWS key is embedded in code. ::: Revoke it, move to environment / wp-config.php.
SECRET-STRIPE ::: critical ::: code ::: any ::: Hard-coded secret ::: Stripe live secret key ::: ~\b(?:sk|rk)_live_[0-9a-zA-Z]{16,}\b~ ::: Live payment key in code. ::: Roll the key and store it outside the code.
SECRET-GOOGLE ::: high ::: code ::: any ::: Hard-coded secret ::: Google API key ::: ~\bAIza[0-9A-Za-z_\-]{35}\b~ ::: API key embedded in code (restrict by referrer/IP). ::: Rotate and restrict.
SECRET-GITHUB ::: critical ::: code ::: any ::: Hard-coded secret ::: GitHub token ::: ~\bgh[pousr]_[A-Za-z0-9]{36,}\b~ ::: Source-control token in code. ::: Revoke immediately.
SECRET-SLACK ::: high ::: code ::: any ::: Hard-coded secret ::: Slack token ::: ~\bxox[abprs]-[0-9A-Za-z\-]{10,}\b~ ::: Messaging token in code. ::: Revoke and rotate.
SECRET-SENDGRID ::: high ::: code ::: any ::: Hard-coded secret ::: SendGrid API key ::: ~\bSG\.[A-Za-z0-9_\-]{16,}\.[A-Za-z0-9_\-]{16,}\b~ ::: Mail API key in code. ::: Revoke and rotate.
SECRET-PRIVKEY ::: critical ::: code ::: any ::: Hard-coded secret ::: Private key block ::: ~-----BEGIN (?:RSA |EC |DSA |OPENSSH |PGP )?PRIVATE KEY(?: BLOCK)?-----~ ::: Private key material in a web-accessible directory. ::: Remove and regenerate the key pair.
SECRET-GENERIC ::: medium ::: code ::: any ::: Hard-coded secret ::: Possible hard-coded password / API key ::: ~['"]?\b(?:db_?pass(?:word)?|passwd|password|pwd|secret_?key|client_?secret|api_?key|apikey|access_?token|auth_?token|private_?key)\b['"]?\s*(?:=>|=|:)\s*['"](?=[^'"]*\d)(?=[^'"]*[A-Za-z])(?!(?:your|enter|change|xxx|example|placeholder|test|dummy|sample|password|secret|api|token|none|null|true|false)[^'"]*['"])[A-Za-z0-9_\-+=/.!@#$%^&*]{10,}['"]~i ::: A credential appears to be written into the source. ::: Move secrets to wp-config.php / environment variables or the options table.
# ---------------------------------------------------------------- JavaScript
JS-EVAL ::: high ::: nostr ::: js ::: Code execution ::: eval() ::: ~(?<![\w$.])eval\s*\(~ ::: Executes strings as code (XSS amplifier, blocked by strict CSP). ::: Parse data with JSON.parse; avoid eval.
JS-NEW-FUNCTION ::: high ::: nostr ::: js ::: Code execution ::: new Function() ::: ~\bnew\s+Function\s*\(~ ::: Same risk as eval(). ::: Avoid dynamic code generation.
JS-DOCUMENT-WRITE ::: medium ::: nostr ::: js ::: Performance / XSS ::: document.write() ::: ~\bdocument\.write(?:ln)?\s*\(~ ::: Blocks parsing, is ignored for async scripts and is an XSS sink. ::: Create DOM nodes instead.
JS-INNERHTML ::: medium ::: nostr ::: js ::: Cross-site scripting ::: innerHTML / outerHTML assignment ::: ~\.(?:innerHTML|outerHTML)\s*\+?=(?!=)(?!\s*(?:'[^'\n]*'|"[^"\n]*"|`[^`]*`)\s*[;,)}\n])~ ::: DOM XSS sink when the value contains untrusted data. ::: Use textContent, or sanitise / escape the value.
JS-INSERT-ADJACENT ::: medium ::: nostr ::: js ::: Cross-site scripting ::: insertAdjacentHTML() ::: ~\.insertAdjacentHTML\s*\(~ ::: DOM XSS sink. ::: Sanitise the HTML or build nodes.
JS-JQUERY-HTML ::: low ::: nostr ::: js ::: Cross-site scripting ::: jQuery HTML insertion with dynamic data ::: ~\.(?:html|append|prepend|after|before|replaceWith)\s*\(\s*(?:['"`][^'"`\n]*['"`]\s*\+|[A-Za-z_$][\w$]*(?:\.[\w$]+)*\s*[)+])~ ::: Concatenated HTML is a DOM XSS sink. ::: Escape values or use .text().
JS-SETTIMEOUT-STRING ::: medium ::: nostr ::: js ::: Code execution ::: setTimeout / setInterval with a string ::: ~\b(?:setTimeout|setInterval)\s*\(\s*['"`]~ ::: Implicit eval(). ::: Pass a function.
JS-LOCATION-SINK ::: medium ::: nostr ::: js ::: Cross-site scripting ::: location set from URL data ::: ~\b(?:window\.|document\.)?location(?:\.href)?\s*=\s*(?:(?!;).){0,120}?(?:location\.(?:hash|search)|document\.(?:URL|referrer)|window\.name)~s ::: Open redirect / javascript: URL injection. ::: Validate against an allow-list.
JS-POSTMESSAGE-STAR ::: medium ::: code ::: js ::: Messaging ::: postMessage() with "*" origin ::: ~\.postMessage\s*\((?:(?!;).)*,\s*['"]\*['"]\s*\)~s ::: Any window can receive the message. ::: Specify the exact target origin.
JS-MESSAGE-LISTENER ::: info ::: code ::: js ::: Messaging ::: "message" event listener ::: ~addEventListener\s*\(\s*['"]message['"]~ ::: Confirm event.origin is validated. ::: Check event.origin against an allow-list.
JS-CONSOLE ::: low ::: nostr ::: js ::: Debug leftovers ::: console.log / debug call ::: ~\bconsole\.(?:log|debug|dir|trace|table)\s*\(~ ::: Leaves debug output in production. ::: Remove or strip in build.
JS-DEBUGGER ::: medium ::: nostr ::: js ::: Debug leftovers ::: debugger statement ::: ~(?<![\w$.])debugger\s*;~ ::: Pauses execution for anyone with dev-tools open. ::: Remove.
JS-STORAGE-SECRET ::: medium ::: code ::: js ::: Data exposure ::: Token / password stored in web storage ::: ~(?:localStorage|sessionStorage)\.setItem\s*\(\s*['"][^'"]*(?:token|password|passwd|secret|jwt|auth)[^'"]*['"]~i ::: Readable by any script on the page (XSS = account takeover). ::: Use HttpOnly cookies.
JS-HTTP-URL ::: low ::: code ::: js ::: Mixed content ::: Insecure http:// URL ::: ~['"]http://(?!localhost|127\.0\.0\.1|www\.w3\.org)[\w.-]+\.[a-z]{2,}[^'"\s]*['"]~i ::: Blocked as mixed content on HTTPS sites. ::: Use https://.
JS-JQ-REMOVED ::: high ::: nostr ::: js ::: JavaScript compatibility ::: jQuery API removed in jQuery 3 (WordPress 5.6+) ::: ~(?:\.(?:live|die|andSelf)\s*\(|\$\.browser\b|jQuery\.browser\b|\.(?:load|unload|error)\s*\(\s*function\b)~ ::: Throws "is not a function" now that jQuery Migrate no longer ships with core. ::: Use .on(), .addBack(), feature detection.
JS-JQ-DEPRECATED ::: low ::: nostr ::: js ::: JavaScript compatibility ::: Deprecated jQuery API ::: ~(?:\.size\s*\(\s*\)|\$\.(?:isFunction|isArray|isNumeric|isWindow|parseJSON|trim|now|camelCase|type|proxy|holdReady)\s*\(|jQuery\.(?:isFunction|isArray|isNumeric|isWindow|parseJSON|trim|now|camelCase|type)\s*\(|\.(?:bind|unbind|delegate|undelegate)\s*\()~ ::: Deprecated in jQuery 3.x and logged by jQuery Migrate. ::: Use native JS equivalents / .on() / .off().
JS-SYNC-XHR ::: medium ::: code ::: js ::: Performance ::: Synchronous XMLHttpRequest ::: ~\basync\s*:\s*(?:false|0)\b|\.open\s*\(\s*['"]\w+['"]\s*,[^,)]+,\s*false\s*\)~ ::: Freezes the browser tab and is deprecated. ::: Use async requests.
JS-DOCUMENT-COOKIE ::: low ::: nostr ::: js ::: Data exposure ::: document.cookie write ::: ~document\.cookie\s*=(?!=)~ ::: Check flags (Secure, SameSite) and that no secrets are stored. ::: Set Secure; SameSite attributes.
# ---------------------------------------------------------------- .htaccess / .user.ini
HT-PREPEND ::: high ::: code ::: ht ::: Server configuration ::: auto_prepend_file / auto_append_file ::: ~auto_(?:prepend|append)_file~i ::: Common malware persistence trick. ::: Verify it is intentional.
HT-PHP-HANDLER ::: critical ::: code ::: ht ::: Server configuration ::: PHP handler mapped to a non-PHP extension ::: ~(?:AddHandler|AddType|SetHandler)[^\n]*(?:php|x-httpd)[^\n]*\.(?:jpe?g|png|gif|ico|txt|css|js|svg|pdf|zip)~i ::: Lets uploaded "images" run as PHP. ::: Remove.
HT-CLOAK-REDIRECT ::: high ::: code ::: ht ::: Server configuration ::: Conditional redirect for search engines / referrers ::: ~RewriteCond\s+%\{HTTP_(?:USER_AGENT|REFERER)\}[^\n]*(?:google|bing|yahoo|yandex|duckduckgo|baidu)[^\n]*\n(?:[^\n]*\n){0,3}?[^\n]*RewriteRule[^\n]*https?://~i ::: Typical spam redirect infection. ::: Remove and clean the site.
HT-INDEXES ::: low ::: code ::: ht ::: Server configuration ::: Directory listing enabled ::: ~Options\s+(?:[+\w-]+\s+)*\+?Indexes~i ::: Exposes the file list. ::: Use Options -Indexes.
HT-ENGINE ::: high ::: code ::: ht ::: Server configuration ::: php_flag engine on ::: ~php_flag\s+engine\s+on~i ::: Re-enables PHP execution in a folder that should be static. ::: Remove.
RULES;
	}

	/** @return array<int,array> parsed pattern rules. */
	public static function rules() {
		if ( null !== self::$parsed ) {
			return self::$parsed;
		}
		$out = array();
		foreach ( preg_split( '/\r?\n/', self::source() ) as $line ) {
			$line = trim( $line );
			if ( '' === $line || '#' === $line[0] ) {
				continue;
			}
			$f = explode( ' ::: ', $line );
			if ( count( $f ) < 9 ) {
				continue;
			}
			$out[] = array(
				'id'    => $f[0],
				'sev'   => $f[1],
				'view'  => $f[2],
				'lang'  => $f[3],
				'cat'   => $f[4],
				'title' => $f[5],
				're'    => $f[6],
				'desc'  => $f[7],
				'fix'   => $f[8],
			);
		}
		self::$parsed = $out;
		return $out;
	}

	/** Rules for a file kind (php / js / cfg / ht). */
	public static function for_kind( $kind ) {
		$list = array();
		foreach ( self::rules() as $r ) {
			$lang = $r['lang'];
			if ( 'any' === $lang ) {
				if ( in_array( $kind, array( 'php', 'js', 'cfg' ), true ) ) {
					$list[] = $r;
				}
			} elseif ( $lang === $kind ) {
				$list[] = $r;
			}
		}
		return $list;
	}

	/** Deprecated WordPress functions => replacement. */
	public static function wp_deprecated() {
		return array(
			'get_currentuserinfo'               => 'wp_get_current_user()',
			'get_user_by_email'                 => "get_user_by( 'email', ... )",
			'get_userdatabylogin'               => "get_user_by( 'login', ... )",
			'user_pass_ok'                      => 'wp_authenticate()',
			'get_settings'                      => 'get_option()',
			'get_themes'                        => 'wp_get_themes()',
			'get_theme'                         => 'wp_get_theme()',
			'get_current_theme'                 => 'wp_get_theme()',
			'get_theme_data'                    => 'wp_get_theme()',
			'wp_specialchars'                   => 'esc_html()',
			'attribute_escape'                  => 'esc_attr()',
			'clean_url'                         => 'esc_url()',
			'js_escape'                         => 'esc_js()',
			'sanitize_url'                      => 'esc_url_raw()',
			'wp_htmledit_pre'                   => 'format_for_editor()',
			'wp_richedit_pre'                   => 'format_for_editor()',
			'is_taxonomy'                       => 'taxonomy_exists()',
			'is_term'                           => 'term_exists()',
			'get_author_name'                   => "get_the_author_meta( 'display_name' )",
			'like_escape'                       => '$wpdb->esc_like()',
			'wp_get_http'                       => 'wp_remote_get()',
			'wp_get_http_headers'               => 'wp_remote_head()',
			'screen_icon'                       => '(removed – no replacement)',
			'the_editor'                        => 'wp_editor()',
			'wp_tiny_mce'                       => 'wp_editor()',
			'get_users_of_blog'                 => 'get_users()',
			'get_all_category_ids'              => 'get_terms()',
			'image_resize'                      => 'wp_get_image_editor()',
			'set_current_user'                  => 'wp_set_current_user()',
			'get_page_by_title'                 => 'WP_Query (title / post_title)',
			'wp_make_content_images_responsive' => 'wp_filter_content_tags()',
			'wp_no_robots'                      => 'wp_robots_no_robots()',
			'wp_blacklist_check'                => 'wp_check_comment_disallowed_list()',
			'wp_get_sites'                      => 'get_sites()',
			'get_blog_list'                     => 'get_sites()',
			'wp_get_attachment_thumb_url'       => 'wp_get_attachment_image_url()',
			'wp_get_attachment_thumb_file'      => 'wp_get_attachment_image_src()',
			'get_the_attachment_link'           => 'wp_get_attachment_link()',
			'is_comments_popup'                 => '(removed – no replacement)',
			'comments_popup_link'               => 'comments_link()',
			'get_post_to_edit'                  => 'get_post()',
			'get_catname'                       => 'get_cat_name()',
			'get_category_children'             => 'get_term_children()',
			'get_category_parents'              => 'get_term_parents_list()',
			'dropdown_cats'                     => 'wp_dropdown_categories()',
			'wp_dropdown_cats'                  => 'wp_dropdown_categories()',
			'user_can_edit_post'                => "current_user_can( 'edit_post', ... )",
			'user_can_create_post'              => 'current_user_can()',
			'user_can_delete_post'              => "current_user_can( 'delete_post', ... )",
			'get_usermeta'                      => 'get_user_meta()',
			'update_usermeta'                   => 'update_user_meta()',
			'delete_usermeta'                   => 'delete_user_meta()',
			'get_profile'                       => 'get_the_author_meta()',
			'get_postdata'                      => 'get_post()',
			'start_wp'                          => 'the_post()',
			'get_links'                         => 'wp_list_bookmarks()',
			'wp_convert_bytes_to_hr'            => 'size_format()',
			'force_ssl_login'                   => 'force_ssl_admin()',
			'is_site_admin'                     => 'is_super_admin()',
			'graceful_fail'                     => 'wp_die()',
			'wp_clearcookie'                    => 'wp_clear_auth_cookie()',
			'wp_setcookie'                      => 'wp_set_auth_cookie()',
			'wp_get_post_cats'                  => 'wp_get_post_categories()',
			'wp_set_post_cats'                  => 'wp_set_post_categories()',
			'add_option_update_handler'         => 'register_setting()',
			'remove_option_update_handler'      => 'unregister_setting()',
		);
	}

	/**
	 * Meta for findings produced by code rather than a regex (file, plugin, runtime and cross-file checks).
	 */
	public static function builtin() {
		$m = function ( $sev, $cat, $title, $desc, $fix ) {
			return array( 'sev' => $sev, 'cat' => $cat, 'title' => $title, 'desc' => $desc, 'fix' => $fix );
		};
		return array(
			'PHP-SYNTAX'           => $m( 'critical', 'Active error', 'PHP syntax / parse error', 'The file cannot be parsed by the PHP version running this site. Including it causes a fatal error (white screen).', 'Fix the syntax at the reported line, or upgrade / replace the plugin.' ),
			'PHP-BOM'              => $m( 'high', 'Active error', 'UTF-8 BOM at start of file', 'The invisible byte-order-mark is sent as output, causing "headers already sent", broken feeds, AJAX and REST responses.', 'Re-save the file as UTF-8 without BOM.' ),
			'PHP-LEADING-OUTPUT'   => $m( 'high', 'Active error', 'Output before <?php', 'Whitespace or text before the opening tag is sent to the browser ("headers already sent").', 'Remove everything before <?php.' ),
			'PHP-TRAILING-OUTPUT'  => $m( 'medium', 'Active error', 'Whitespace after closing ?>', 'Trailing blank lines after ?> are output and can break headers, feeds and JSON responses.', 'Remove the closing ?> tag (recommended) or the trailing whitespace.' ),
			'PHP-NO-GUARD'         => $m( 'low', 'Hardening', 'Direct file access not blocked', 'The file runs code at top level and has no ABSPATH guard, so it can be executed by requesting its URL directly.', "Add: if ( ! defined( 'ABSPATH' ) ) { exit; }" ),
			'PHP-DUP-DECL'         => $m( 'critical', 'Conflict', 'Duplicate function / class declaration', 'The same function or class is declared in more than one file. If both files are loaded PHP raises "Cannot redeclare" – a fatal error.', 'Rename / namespace one declaration, wrap in function_exists()/class_exists(), or remove the duplicate library.' ),
			'PHP-MISSING-INCLUDE'  => $m( 'high', 'Active error', 'include/require target does not exist', 'The statically-resolvable include path points to a missing file. require = fatal error, include = warning.', 'Restore the missing file or correct the path.' ),
			'PHP-DEPRECATED-WP'    => $m( 'medium', 'Deprecated', 'Deprecated WordPress function', 'The function is deprecated and logs notices; it may be removed in a future release.', 'Use the replacement shown in the finding.' ),
			'WP-AJAX-NOPRIV'       => $m( 'medium', 'Access control', 'Unauthenticated AJAX endpoint', 'A wp_ajax_nopriv_ action is reachable by any visitor. Verify it only does harmless work and validates input.', 'Remove the nopriv hook, or enforce a nonce, rate-limit and strict validation.' ),
			'WP-AJAX-NOCAP'        => $m( 'medium', 'Access control', 'AJAX handler without capability check', 'The file registers wp_ajax_ handlers but never calls current_user_can(), so any logged-in user (e.g. subscriber) may be able to trigger them.', 'Add current_user_can() with the right capability inside each handler.' ),
			'WP-AJAX-NONONCE'      => $m( 'medium', 'Access control', 'AJAX handler without nonce check', 'The file registers wp_ajax_ handlers but never verifies a nonce (check_ajax_referer / wp_verify_nonce) – CSRF risk.', 'Verify a nonce at the top of each handler.' ),
			'WP-NONCE-MISSING'     => $m( 'medium', 'Access control', 'Request data processed without nonce verification', 'The file reads $_POST / $_GET / $_REQUEST and has no nonce verification anywhere (CSRF).', 'Use wp_nonce_field() + check_admin_referer() / wp_verify_nonce().' ),
			'WP-REST-NOPERM'       => $m( 'high', 'Access control', 'REST route without permission_callback', 'register_rest_route() was called without permission_callback; WordPress 5.5+ warns and the route is fully public.', "Add 'permission_callback' => a capability check, or '__return_true' only for genuinely public data." ),
			'WP-REST-PUBLIC'       => $m( 'medium', 'Access control', 'REST route is public (__return_true)', "permission_callback is '__return_true' – anyone can call the route. Confirm it exposes or changes nothing sensitive.", 'Use a capability check for anything not strictly public.' ),
			'FILE-BACKUP'          => $m( 'high', 'Exposed file', 'Backup / dump file in a web-accessible folder', 'Database dumps and backup copies are downloadable by URL and often contain credentials or source code.', 'Delete the file or move it outside the web root.' ),
			'FILE-SHELL-NAME'      => $m( 'critical', 'Malware indicator', 'File name matches a known web-shell', 'File names like shell.php, c99.php, wso.php are typical backdoors.', 'Inspect, quarantine and clean the site.' ),
			'FILE-DOUBLE-EXT'      => $m( 'high', 'Malware indicator', 'Double extension (.php.jpg / .jpg.php)', 'A PHP script disguised as an image, a common upload-bypass trick.', 'Inspect and delete.' ),
			'FILE-LOG'             => $m( 'medium', 'Exposed file', 'Log file in a web-accessible folder', 'Logs reveal paths, queries and sometimes credentials and may be downloaded by URL.', 'Delete or block access, log outside the web root.' ),
			'FILE-SECRET'          => $m( 'high', 'Exposed file', 'Secret / credential file', '.env, .git-credentials, .htpasswd and similar files must never be web-accessible.', 'Delete or move above the web root.' ),
			'FILE-DEV-SCRIPT'      => $m( 'medium', 'Exposed file', 'Development / diagnostic script left behind', 'phpinfo.php, info.php, test.php, adminer.php etc. expose information or admin functionality.', 'Delete the file.' ),
			'FILE-ARCHIVE'         => $m( 'medium', 'Exposed file', 'Archive file in a web-accessible folder', 'Zip / tar archives may contain a full copy of the site and are downloadable.', 'Delete the file.' ),
			'FILE-HIDDEN-PHP'      => $m( 'high', 'Malware indicator', 'Hidden PHP file (name starts with a dot)', 'Dot-files are not listed by many tools and are used to hide backdoors.', 'Inspect and delete.' ),
			'FILE-UPLOADS-PHP'     => $m( 'high', 'Malware indicator', 'PHP file inside the uploads folder', 'The uploads folder should contain media only; PHP there is almost always a backdoor.', 'Inspect and delete; block PHP execution in uploads.' ),
			'FILE-WORLD-WRITABLE'  => $m( 'medium', 'Permissions', 'World-writable file', 'Anyone on a shared server can modify the file (permissions o+w).', 'chmod 644 files / 755 directories.' ),
			'FILE-UNREADABLE'      => $m( 'low', 'Scan coverage', 'File could not be read', 'The scanner was not permitted to read this file, so it was not analysed.', 'Check permissions / ownership.' ),
			'FILE-TOO-BIG'         => $m( 'info', 'Scan coverage', 'File skipped (larger than size limit)', 'Contents were not analysed because the file is larger than the configured limit.', 'Raise the limit in Settings if needed.' ),
			'PLUGIN-REQ-PHP'       => $m( 'high', 'Active error', 'Plugin requires a newer PHP version', "The plugin's 'Requires PHP' header is higher than this server's PHP version.", 'Upgrade PHP or use a compatible plugin version.' ),
			'PLUGIN-REQ-WP'        => $m( 'medium', 'Compatibility', 'Plugin requires a newer WordPress', "The plugin's 'Requires at least' header is higher than this WordPress version.", 'Update WordPress or use a compatible plugin version.' ),
			'PLUGIN-UPDATE'        => $m( 'medium', 'Outdated', 'Plugin update available', 'A newer version is available. Updates frequently contain security fixes.', 'Update the plugin (after a backup).' ),
			'THEME-UPDATE'         => $m( 'medium', 'Outdated', 'Theme update available', 'A newer version is available.', 'Update the theme.' ),
			'PLUGIN-INACTIVE'      => $m( 'info', 'Passive risk', 'Inactive plugin still installed', 'Files of inactive plugins stay reachable by URL, so their vulnerabilities remain exploitable.', 'Delete plugins you do not use.' ),
			'INTEGRITY-MODIFIED'   => $m( 'high', 'Integrity', 'File differs from the official wordpress.org copy', 'The checksum does not match the published release – the file was edited or infected.', 'Reinstall the plugin / compare the diff.' ),
			'INTEGRITY-EXTRA'      => $m( 'medium', 'Integrity', 'File not part of the official release', 'The file does not exist in the published plugin package.', 'Inspect; delete if not needed.' ),
			'INTEGRITY-MISSING'    => $m( 'low', 'Integrity', 'Official file is missing', 'A file from the published release is absent, which may cause errors.', 'Reinstall the plugin.' ),
			'CORE-MODIFIED'        => $m( 'high', 'Integrity', 'WordPress core file modified', 'Core file checksum mismatch.', 'Run: wp core verify-checksums, then reinstall core.' ),
			'CORE-EXTRA'           => $m( 'medium', 'Integrity', 'Unknown file in WordPress core directory', 'File does not belong to this WordPress release.', 'Inspect; delete if not needed.' ),
			'RUNTIME-FATAL'        => $m( 'critical', 'Active error', 'Fatal error (runtime)', 'A fatal error actually occurred while serving a request.', 'Fix the code at the reported file and line.' ),
			'RUNTIME-ERROR'        => $m( 'high', 'Active error', 'PHP error (runtime)', 'A PHP error was raised at runtime.', 'Fix the code at the reported file and line.' ),
			'RUNTIME-WARNING'      => $m( 'medium', 'Active error', 'PHP warning (runtime)', 'A PHP warning was raised at runtime.', 'Fix the code at the reported file and line.' ),
			'RUNTIME-NOTICE'       => $m( 'low', 'Active error', 'PHP notice (runtime)', 'A PHP notice was raised at runtime.', 'Fix the code at the reported file and line.' ),
			'RUNTIME-DEPRECATED'   => $m( 'low', 'Active deprecation', 'PHP deprecation (runtime)', 'Code that triggers a deprecation on this PHP version ran.', 'Update the code at the reported file and line.' ),
			'RUNTIME-WP-DEPRECATED' => $m( 'low', 'Active deprecation', 'WordPress deprecation (runtime)', 'A deprecated WordPress function / hook / argument / file was called.', 'Use the replacement mentioned.' ),
			'RUNTIME-DOING-IT-WRONG' => $m( 'low', 'Active error', '_doing_it_wrong notice (runtime)', 'WordPress API used incorrectly.', 'Follow the message.' ),
			'RUNTIME-JS-ERROR'     => $m( 'medium', 'Active error', 'JavaScript error (runtime)', 'A JavaScript error occurred in a logged-in user\'s browser.', 'Fix the script at the reported URL and line.' ),
		);
	}

	/** Metadata for any rule id (pattern or built-in). */
	public static function meta_map() {
		static $map = null;
		if ( null === $map ) {
			$map = self::builtin();
			foreach ( self::rules() as $r ) {
				$map[ $r['id'] ] = array( 'sev' => $r['sev'], 'cat' => $r['cat'], 'title' => $r['title'], 'desc' => $r['desc'], 'fix' => $r['fix'] );
			}
		}
		return $map;
	}

	/**
	 * Suspicious file NAME check, independent of content. Returns a rule id or ''.
	 */
	public static function suspicious_name( $name ) {
		$l = strtolower( $name );
		if ( preg_match( '/\.php\d?\.(?:jpe?g|png|gif|ico|txt|svg|pdf|css|js)$|\.(?:jpe?g|png|gif|ico)\.php\d?$/', $l ) ) {
			return 'FILE-DOUBLE-EXT';
		}
		if ( preg_match( '/^(?:shell|cmd|c99\w*|r57\w*|wso\w*|b374k\w*|webshell|backdoor|bypass|mini|up|alfa\w*|indoxploit\w*|priv8\w*|filesman|leaf|sym|killer)\.(?:php\d?|phtml|pht)$/', $l ) ) {
			return 'FILE-SHELL-NAME';
		}
		if ( preg_match( '/^\.[^.].*\.(?:php\d?|phtml|pht)$/', $l ) ) {
			return 'FILE-HIDDEN-PHP';
		}
		if ( preg_match( '/^(?:\.env.*|\.git-credentials|\.htpasswd|\.npmrc|\.netrc|id_rsa|id_dsa|credentials\.json|service-account.*\.json)$/', $l ) ) {
			return 'FILE-SECRET';
		}
		if ( preg_match( '/^(?:phpinfo|info|test|tests?|adminer(?:-[\d.]+)?|pma|phpmyadmin|probe|php-info)\.php$/', $l ) ) {
			return 'FILE-DEV-SCRIPT';
		}
		if ( preg_match( '/\.(?:sql|sql\.gz|dump|bak|old|orig|save|swp|swo|tmp|backup)$|~$|^backup.*\.(?:zip|tar|gz)$/', $l ) ) {
			return 'FILE-BACKUP';
		}
		if ( preg_match( '/^(?:error_log|debug\.log|php_errorlog)$|\.log$/', $l ) ) {
			return 'FILE-LOG';
		}
		if ( preg_match( '/\.(?:zip|tar|tgz|tar\.gz|rar|7z)$/', $l ) ) {
			return 'FILE-ARCHIVE';
		}
		return '';
	}
}
