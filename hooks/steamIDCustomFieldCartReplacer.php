<?php

/*
 * Steam Custom Field Nexus Hook
 * Copyright (C) 2014  Yiyang Chen
 * 
 * This file is part of Steam Custom Field Nexus Hook.
 * 
 * Steam Custom Field Nexus Hook is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or (at your
 * option) any later version.
 * 
 * Steam Custom Field Nexus Hook is distributed in the hope that it will be
 * useful, but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU General
 * Public License for more details.
 * 
 * You should have received a copy of the GNU General Public License along with
 * this program; if not, see <http://www.gnu.org/licenses/>
 */

/**
 * Replaces the Steam ID on the cart page with a more user friendly Steam ID.
 * 
 * @author Yiyang Chen <yiyangc91@gmail.com>
 */
class steamIDCustomFieldCartReplacer
{
    const PRE_LOOP_TAG = '<!--hook.foreach.skin_nexus_payments.viewCart.cfields.inner.pre-->';
    const POST_LOOP_TAG = '<!--hook.foreach.skin_nexus_payments.viewCart.cfields.inner.post-->';

    const EM_TAG = '<em>';
    const EM_CLOSE_TAG = '</em>';
    const BR_TAG = '<br />';

    // Private use
    const STEAM_ID = 0;
    const STEAM_NAME = 1;

    const LANG_PACK_NAME = 'public_steamcf';
    const LANG_APP_KEY = 'nexus';

    private $registry;
    private $lang;

    private $controller;

    public function __construct()
    {
        $this->lang = ipsRegistry::getClass('class_localization');
        $this->lang->loadLanguageFile(array(self::LANG_PACK_NAME), self::LANG_APP_KEY);

        $this->registry	= ipsRegistry::instance();

        $controllerClass = IPSLib::loadLibrary(IPS_ROOT_PATH . '/sources/classes/steamcf.php', 'SteamIdCustomFieldController');
        $this->controller = new $controllerClass($this->registry);
    }
    
    public function getOutput() {}

    /**
     * Creates a new customfield output to put in the cart details area.
     *
     * @param mixed Data from matching Steam ID.
     * @return string New output.
     */
    private function createNewSteamOutput($data)
    {
        $steamName = $data[self::STEAM_NAME] ? $data[self::STEAM_NAME] : $this->lang->words['steamcf_steam_id'];
        $steamId = $data[self::STEAM_ID];
        $extra = '';

        // Attempt to retrieve dude
        $details = $this->controller->getSteamDetails($steamId);
        if ($details->error) {
            $extra = ' ERROR (' . $details->error . ')';
        }
        else {
            $steamId = $details->name;
            $extra = ' (' . $details->profile . ')';
        }

        // If everything good
        return $steamName . ': ' . $steamId . $extra;
    }

    /**
     * If we can find the steam name and the steam ID, then return it.
     *
     * @param string The extracted custom field data.
     * @return mixed An array containing the steam name and steam ID,
     *               or NULL if we could not find either.
     */
    private static function getSteamDataIfMatch($part)
    {
        $success = preg_match('/^SteamID([a-zA-Z]*): (.+)$/', $part, $matches);
        if ($success) {
            return array(
                self::STEAM_NAME => $matches[1],
                self::STEAM_ID => $matches[2]
            );
        }
        else {
            return NULL;
        }
    }

    /**
     * In this replace hook, we loop over every potential steam ID in
     * each cart item details, and replace it with a steam name.
     *
     * @param	string		Output
     * @param	string		Hook key
     * @return	string		Output parsed
     */
    public function replaceOutput($output, $key)
    {
        $offset = 0;

        while (true) {
            $posPre = strpos($output, self::PRE_LOOP_TAG, $offset);
            if (!$posPre) {
                return $output;
            }
            $posPreEnd = $posPre+strlen(self::PRE_LOOP_TAG);
            $posPost = strpos($output, self::POST_LOOP_TAG, $posPreEnd);
            if (!$posPost) {
                // Unexpected...
                return $output;
            }
            $data_length = $posPost - $posPreEnd;

            // Find contents of emphasis tag
            $emPos = strpos($output, self::EM_TAG, $posPreEnd) + strlen(self::EM_TAG);
            $emEndPos = strpos($output, self::EM_CLOSE_TAG, $emPos);
            if (!$emPos || !$emEndPos
                || $emPos >= $posPost
                || $emEndPos+strlen(self::EM_CLOSE_TAG) >= $posPost) {
                // Some shit broke with finding <em>
                return $output;
            }

            // I call this the yolo replace
            $emDataLength = $emEndPos - $emPos;
            $emData = substr($output, $emPos, $emDataLength);
            $splitEmData = preg_split('/<br *\\/?>/', $emData);
            $innerOutput = array();
            foreach ($splitEmData as $part) {
                $data = self::getSteamDataIfMatch($part);
                if ($data != NULL) {
                    $innerOutput[] = $this->createNewSteamOutput($data);
                }
                else {
                    $innerOutput[] = $part;
                }
            }

            $innerOutputStr = implode(self::BR_TAG, $innerOutput);
            $newDataLength = strlen($innerOutputStr);
            $output = substr_replace($output, $innerOutputStr, $emPos, $emDataLength);
            $offset = $posPost
                + strlen(self::POST_LOOP_TAG)
                - $emDataLength
                + $newDataLength;
        }

        return $output;
    }

}

?>
