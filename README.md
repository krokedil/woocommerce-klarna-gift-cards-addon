# woocommerce-klarna-gift-cards-connector
Connector for making WooCommerce - Gift Cards compatible with Klarna Checkout v2 Plugin

You need to have the the Klarna Checkout Widget on the checkout page in order to be able to use the gift cards on the checkout page.

== Changelog ==

= 2019.06.25  	- version 1.1 =
* Fix           - Added function_exists before calling rpgc_update_card() (to avoid errors if gift card plugin isn't activated).