Steam Custom Package Field
=======
Provides a friendly way for entering Steam account information in
IP.Nexus packages.

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
bcmath (if on a 32-bit system)
simplexml

To create the "Steam" field:

1. Create a _Custom Package Field_ in IP. Nexus. Make the type
   "Textbox" and start the name with "SteamID*X*". The name of the
   field will be either "Steam", or "*X*" if *X* is not empty.
2. Assign this field to your packages, configure everything else,
   and save it.
3. You are done!

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
