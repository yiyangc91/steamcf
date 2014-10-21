Steam Custom Field Hook for IP. Nexus
=======

The main purpose of this hook is to replace your item's specified "text fields" with a slightly more user friendly field. It does so by scanning for any text fields starting with "SteamID". The reason it does this, is because at the time of writing, Nexus does not provide an API to add custom fields.

This optionally requires a [Steam OAuth plugin](https://github.com/Lavoaster/IP.Board-Steam-Authentication-Method), which is automatically detected and used.

## Usage

To create the "Steam" field:

1. Create a _Custom Package Field_ in IP. Nexus. Make the type "Textbox" and start the name with "SteamID*X*". The name of the field will be either "Steam", or "*X*" if *X* is not empty.
2. Assign this field to your packages, configure everything else, and save it.
3. You are done!

## Images
![Fields](https://raw.githubusercontent.com/yiyangc91/yiyangc91.github.io/master/images/steamcf_example_1.png)

