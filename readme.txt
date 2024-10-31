=== pMailer Importer ===
Contributors: Pmailer
Donate link: http://www.pmailer.co.za/
Tags: pmailer, email, newsletter, import, export, plugin, widget
Requires at least: 3.0
Tested up to: 3.3.1
Stable tag: 0.3
Version: 0.3 

== Description ==

The pMailer import plugin allows one to quickly and easily import wordpress users and comments that have email addresses
into pMailer, this plugin should work on older versions of wordpress but it has not been tested on them.

== Installation ==

This section describes how to install the plugin and get it working.

1. Unzip our archive and upload the entire `pmailer_importer` directory to your `/wp-content/plugins/` directory
1. Activate the plugin through the 'Plugins' menu in WordPress
1. Go to Settings and look for "pMailer Importer" in the menu
1. Enter your pMailer API URL and API Key and let the plugin verify it.
1. Select one or more of your lists to have users/comments imported into (select multiple lists by holding down the control key on windows and command key on mac while clicking on a list).
1. Select what you would like imported: users, comments (comments that have email addresses will be imported).
1. Click the "import to pMailer" button and wait for the import progress to reach 100%. Please dont navigate away from the page as the import will stop.

== Frequently Asked Questions ==

= Can I run the import multiple times? =

Yes, email addresses that already exist on a list will be updated (ignored).

= Why dont I see my newly created lists? =

When you enter in your api details the importer will take a snapshot of the current lists existing in pMailer at that point in time.
To get your latest lists just press the refresh lists button.

== Screenshots ==

1. Entering your API info
2. Setting your import options 
3. Displaying the import process

== Upgrade Notice ==

= 0.3 =
* No upgrade information available.

= 0.2 =
* No upgrade information available.

= 0.1 =
* Initial release, no upgrade information available.

== Change Log ==

= 0.3 =
* Renamed potentially conflicting class names.

= 0.2 =
* Renamed potentially conflicting class names.

= 0.1 =
* Initial realease.