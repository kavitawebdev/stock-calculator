=== Plugin Name: Google Sheet Based Calculator ===
Contributors: Kavita Kumari
Tags: calculator, google sheets, dynamic data, wordpress plugin
Requires at least: 5.0
Tested up to: 6.x
Stable tag: 1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

== Description ==

This is a custom WordPress plugin that displays a calculator on your website using data fetched dynamically from a Google Sheet.

The plugin allows you to:
- Connect to a Google Sheet
- Fetch and use sheet data dynamically
- Display a calculator interface on the frontend
- Perform calculations based on real-time or predefined sheet values

This is useful for financial tools, stock calculators, pricing estimators, or any scenario where calculation logic depends on external sheet data.

== Features ==

- Dynamic data integration from Google Sheets
- Easy-to-use calculator interface
- Lightweight and fast
- Customizable logic via Google Sheets
- Shortcode support for easy embedding

== Installation ==

1. Download the plugin files.
2. Upload the plugin folder to the `/wp-content/plugins/` directory.
3. Activate the plugin through the 'Plugins' menu in WordPress.
4. Configure the plugin settings (if applicable).
5. Add the shortcode to any page or post where you want the calculator to appear.

== Usage ==

Use the shortcode below to display the calculator:

[stock_calculator]

(Note: Replace with your actual shortcode if different.)

== Configuration ==

- Connect your Google Sheet (ensure it is publicly accessible or properly authenticated).
- Update the Sheet ID and range in the plugin settings or code.
- Customize calculation logic as per your requirements.

== Requirements ==

- WordPress 5.0 or higher
- PHP 7.0 or higher
- Google Sheet with proper access permissions

== Frequently Asked Questions ==

= How do I connect my Google Sheet? =
Ensure your Google Sheet is publicly accessible or use API credentials if required. Add the Sheet ID in the plugin settings.

= Can I customize the calculator? =
Yes, you can modify the plugin code or update the Google Sheet logic to change calculations.

== Screenshots ==

1. Calculator frontend view
2. Example Google Sheet structure

== Changelog ==

= 1.0 =
* Initial release

== Upgrade Notice ==

= 1.0 =
Initial version of the plugin.

== Author ==

Kavita Kumari

== License ==

This plugin is licensed under the GPLv2 or later.

