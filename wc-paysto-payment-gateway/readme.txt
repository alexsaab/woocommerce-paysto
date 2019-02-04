=== Paysto for WooCommerce ===
Contributors: alexsaab
Tags: paysto, payment getaway, woo commerce, woocommerce, ecommerce
Requires at least: 3.0
Tested up to: 3.3.2
Stable tag: trunk

Allows you to use Paysto payment gateway with the WooCommerce plugin.

== Description ==

After activating the plugin through the control panel in WooCommerce, we will write
Login merchant (Store code), secret phrase and etc., you can find them in [Your Paysto Account](https://account.paysto.com/my/)

Get more information on [plugin page](https://github.com/alexsaab/woocommerce-paysto)

If you have any questions please contact dev@agaxx.ru

== Installation ==
1. Make sure that you heve last version of Woocommerce  [WooCommerce](/www.woothemes.com/woocommerce)
2. Unzip folder "paysto-for-woocommerce" in folder your-site.com/wp-content/plugins
3. Activate Plugin

In merchant payment system PaySto make:
<ul style="list-style:none;">
<li>Get merchant ID</li>
<li>Get secret</li>
<li>Set success URL: http://your_domain/?wc-api=wc_paysto&paysto=success</li>
<li>Set fail URL: http://your_domain/?wc-api=wc_paysto&paysto=fail</li>
</ul>

In Wordpress in plugin set page you must set:
<ul style="list-style:none;">
<li>Merchant ID</li>
<li>Secret word</li>
<li>VAT rate for products</li>
<li>VAT rate for delivery</li>
<li>Set all other nessessary fields</li>
</ul>


== Changelog ==
= 1.00 =
* Plugin relise