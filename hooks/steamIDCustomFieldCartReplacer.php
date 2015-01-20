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
 * Template Hook: foreach inner.pre skin_nexus_payments.viewCart.cfields
 * 
 * @author Yiyang Chen <yiyangc91@gmail.com>
 */
class SteamIDCustomFieldCartReplacer
{
    const PRE_LOOP_TAG = '<!--hook.foreach.skin_nexus_payments.viewCart.cfields.inner.pre-->';
    const POST_LOOP_TAG = '<!--hook.foreach.skin_nexus_payments.viewCart.cfields.inner.post-->';

    const EM_TAG = '<em>';
    const EM_CLOSE_TAG = '</em>';
    const BR_TAG = '<br />';

    // Private use
    const STEAM_ID = 0;
    const STEAM_NAME = 1;

    const EM_POS = 0;
    const EM_LEN = 1;

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
    private function createNewSteamOutput($data, $steamDetails)
    {
        $steamName = $data[self::STEAM_NAME] ? $data[self::STEAM_NAME] : $this->lang->words['steamcf_steam_id'];
        $steamId = $data[self::STEAM_ID];
        $extra = '';

        // Attempt to retrieve dude
        if (array_key_exists($steamId, $steamDetails)) {
            $details = $steamDetails[$steamId];
            $steamId = $details->name;
            $extra = ' (' . $details->profile . ')';
        }
        else {
            $extra = ' WARNING (' . $this->lang->words['steamcf_player_not_found'] . ')';
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
        $success = preg_match('/^SteamID([^:]*): (.+)$/', $part, $matches);
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

    private static function getReplacementPositions($output)
    {
        $offset = 0;
        $positions = array();

        while (true) {
            $posPre = strpos($output, self::PRE_LOOP_TAG, $offset);
            if (!$posPre) {
                return $positions;
            }
            $posPreEnd = $posPre+strlen(self::PRE_LOOP_TAG);
            $posPost = strpos($output, self::POST_LOOP_TAG, $posPreEnd);
            if (!$posPost) {
                // Unexpected...
                return $positions;
            }
            $data_length = $posPost - $posPreEnd;

            // Find contents of emphasis tag
            $emPos = strpos($output, self::EM_TAG, $posPreEnd) + strlen(self::EM_TAG);
            $emEndPos = strpos($output, self::EM_CLOSE_TAG, $emPos);
            if (!$emPos || !$emEndPos
                || $emPos >= $posPost
                || $emEndPos+strlen(self::EM_CLOSE_TAG) >= $posPost) {
                // Some shit broke with finding <em>
                return $positions;
            }
            $emDataLength = $emEndPos - $emPos;

            // Track positions of Steam IDs
            $positions[] = array(
                self::EM_POS => $emPos,
                self::EM_LEN => $emDataLength,
            );

            $offset = $posPost + strlen(self::POST_LOOP_TAG);
        }

        return $positions;
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
        $positions = self::getReplacementPositions($output);
        $steamDatas = array();

        foreach ($positions as $position) {
            $emPos = $position[self::EM_POS];
            $emDataLength = $position[self::EM_LEN];

            $emData = substr($output, $emPos, $emDataLength);
            $splitEmData = preg_split('/<br *\\/?>/', $emData);
            
            foreach ($splitEmData as $part) {
                $steamDatas[] = self::getSteamDataIfMatch($part);
            }
        }

        $steamIds = array();
        $omgErrors = NULL;
        foreach ($steamDatas as $steamData) {
            $steamIds[] = $steamData[self::STEAM_ID];
        }
        try {
            $steamDetails = $this->controller->getSteamDetailsBatch($steamIds);
        }
        catch (Exception $e) {
            $omgErrors = $e->getMessage();
        }

        // Loop over the positions again
        $i = 0;
        $correction = 0;
        foreach ($positions as $position) {
            $emPos = $position[self::EM_POS];
            $emDataLength = $position[self::EM_LEN];

            $emData = substr($output, $emPos+$correction, $emDataLength);
            $splitEmData = preg_split('/<br *\\/?>/', $emData);
            
            $innerOutput = array();
            foreach ($splitEmData as $part) {
                if ($steamDatas[$i] != NULL && !$omgErrors) {
                    $innerOutput[] = htmlentities($this->createNewSteamOutput($steamDatas[$i], $steamDetails));
                }
                else if ($omgErrors) {
                    $innerOutput[] = $part . ' ERROR (' . htmlentities($omgErrors) . ')';
                }
                else {
                    $innerOutput[] = $part;
                }
                $i++;
            }
            $innerOutputStr = implode(self::BR_TAG, $innerOutput);
            $newDataLength = strlen($innerOutputStr);
            $output = substr_replace($output, $innerOutputStr, $emPos+$correction, $emDataLength);

            $correction += $newDataLength - $emDataLength;
        }

        return $output;
    }
}

?>
