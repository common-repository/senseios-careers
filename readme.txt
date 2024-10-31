=== SenseiOS Careers ===
Contributors: senseilabs
Tags: sensei,labs,candidate360,careers
Requires at least: 4.0
Tested up to: 4.5
License: (c) 2016, Klick Inc. All rights reserved.
License URI: http://senseilabs.com

WordPress Plugin for SenseiOS' Candidate360 app.

== Description ==
This plugin will download the latest job postings from your SenseiOS Candidate360 app and populate them into a Custom Post Type for display on your site.

== Installation ==
1. From your Wordpress dashboard, visit 'Plugins' > 'Add new'
2. Search for 'SenseiOS Careers'
3. Install the plugin
4. Visit 'Plugins' > 'Installed Plugins'
5. Click on 'Activate' for 'SenseiOS'

== Changelog ==
**Version 1.1.0**
* Fixed a bug where location changes in the feed weren't reflected in Wordpress 
* Added a new function sensei_apply_now_buttons($postID) that should replace sensei_apply_now_button(). Function will print multiple apply now buttons if the current job description is attributed to multiple locations.

**Version 1.1.1**
* Fixed a bug where locationIDs in the apply now buttons were incorrect

**Version 1.2.0**
* Added new post attribute Job City to accommodate LinkedIn scraper

