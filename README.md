Steam Custom Package Field
=======
Provides a friendly way for entering Steam account information in
IP.Nexus packages.

![Steam Auth Integration](http://yiyangc91.github.io/images/steamcf.png)

## Features
* Optional [Steam Authentication](https://github.com/Lavoaster/IP.Board-Steam-Authentication-Method)
  integration.
* Easy user input of Steam IDs for shop items.
* Validates user input, and contacts Steam to ensure the ID is valid.
* Stores data as SteamID, SteamID3, or SteamID64.
* Caching of Steam IDs for high performance on large forums.

## Installation
### Requirements
* Invision Power Board >3.4.7
* IP.Nexus >1.5.8
* (Highly Recommended) [Steam Authentication](https://github.com/Lavoaster/IP.Board-Steam-Authentication-Method)
* PHP 5.3.0
 * pcre
 * bcmath (if on a 32-bit system)
 * simplexml

### Setup

1. Copy everything *inside* the "uploads" directory to your forum.
2. Open the Admin CP. Go to Applications & Modules > Manage Hooks, and
   choose "Install Hook". Select **steamcf.xml** and install it.
   ![Installation](http://yiyangc91.github.io/images/steamcf_install.png)


### Usage
1. Request an API key from [Steam](http://steamcommunity.com/dev/apikey)
2. Configure the hook with your API key in the system settings.
   ![Configuration](http://yiyangc91.github.io/images/steamcf_1.png)
3. Create a custom field in the IP.Nexus "Custom Package Fields"
   settings. It's name must begin with the prefix set in the
   configuration (default is "SteamID").
   ![Custom Field](http://yiyangc91.github.io/images/steamcf_2.png)
4. Use this field in one of your packages! You're done! Take a look
   at your store and check if the field is displayed properly.

## Contributing
You need to turn on development mode for IPB. See the buildInDev.php
script that comes with the IPB distribution.

There doesn't seem to be a great way to distribute and develop hooks
for Invision Power Board - just symlink the files from this repo
into the forum source code. There's a script in the scripts folder
which will do this for you. Otherwise, you'll want to manually sync:

| Repository Folder | Forum Folder                  |
|-------------------|-------------------------------|
| /hooks            | /hooks                        |
| /uploads          | /                             |
| /lang             | /cache/lang_cache/master_lang |
| /skin             | /cache/skin_cache/master_skin |

Then you should:

1. Import the settings in the /settings folder
2. Set up each of the hooks. See the documentation in the files.
