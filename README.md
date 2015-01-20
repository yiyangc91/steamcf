Steam Custom Field Hook for IP. Nexus
=======

The main purpose of this hook is to replace your item's specified
"text fields" with a slightly more user friendly field. It does so
by scanning for any text fields starting with "SteamID". The reason
it does this, is because at the time of writing, Nexus does not
provide an API to add custom fields.

This optionally requires a [Steam OAuth plugin](https://github.com/Lavoaster/IP.Board-Steam-Authentication-Method),
which is automatically detected and used.

## Features

* Customisable settings, field name, steam API key
* Optional steam oauth integration
* Save field as Steam ID, Steam ID 32, Steam ID 64
* Accepts any input.
* Validation of user input
* Cached steam IDs for performance on large forums
* Uninstallable without side effects

## Installation

IPB >3.4.7
IP Nexus >1.5.8
pcre
bcmath
simplexml

To create the "Steam" field:

1. Create a _Custom Package Field_ in IP. Nexus. Make the type
   "Textbox" and start the name with "SteamID*X*". The name of the
   field will be either "Steam", or "*X*" if *X* is not empty.
2. Assign this field to your packages, configure everything else,
   and save it.
3. You are done!

## Contributing

Turn on [development mode](https://www.invisionpower.com/support/guides/_/advanced-and-developers/miscellaneous/developers-mode-r147).

There doesn't seem to be a great way to distribute and develop hooks
for Invision Power Board - I just symlink the files from this repo
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
