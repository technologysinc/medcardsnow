Authorize.Net PHP SDK 2.0.4 (official release, github.com/AuthorizeNet/sdk-php, Sept 2024)

Where it goes:
  wp-content/themes/hello-elementor-child/custom-authorize-payment/vendor/authorizenet/authorizenet/

MCN Core 1.2.0+ loads it through the SDK's own autoload.php when Composer's
autoloader does not provide it. Check: Tools > MCN Modules > Checks >
"Payment library loads" should say OK.
