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
 * Dumb struct to hold a cache entry.
 *
 * @author Yiyang Chen <yiyangc91@gmail.com>
 */
class SteamIdCacheEntry
{
    public $details;
    public $expiryDate;

    public function __construct($details, $expiryDate)
    {
        $this->details = $details;
        $this->expiryDate = $expiryDate;
    }
}

/**
 * Dumb struct to handle steam ID details, to output to the user.
 *
 * @author Yiyang Chen <yiyangc91@gmail.com>
 */
class SteamIdCustomFieldDetails
{
    public $id;
    public $profile;
    public $avatar;
    public $name;
    public $status;

    public $error;

    /**
     * Creates the steam details, except with NULLS EVERYWHERE.
     * If you are reading this code, I am sorry.
     * 
     * @return SteamIdCustomFieldDetails NULL details.
     */
    public static function createEmptyDetails()
    {
        // Oh god, nulls everywhere.
        // This could probably be done better
        return new SteamIdCustomFieldDetails(NULL, NULL, NULL, NULL, NULL);
    }

    /**
     * Creates the steam details, except with nothing but an error.
     * This could seriously be done better, this is a giant hack.
     *
     * @return SteamIdCustomFieldDetails ERROR details.
     */
    public static function createErrorDetails($errorMsg)
    {
        return new SteamIdCustomFieldDetails(NULL, NULL, NULL, NULL, NULL, $errorMsg);
    }

    public function __construct($steamId, $steamProfile, $steamAvatar, $steamName, $steamStatus, $error = NULL)
    {
        $this->id = $steamId;
        $this->profile = $steamProfile;
        $this->avatar = $steamAvatar;
        $this->name = $steamName;
        $this->status = $steamStatus;
        $this->error = $error;
    }
}

/**
 * Contains lots of useful stuff.
 *
 * @author Yiyang Chen <yiyangc91@gmail.com>
 */
class SteamIdCustomFieldController
{
    const STEAM_API_GET_PLAYER_SUMMARIES = "http://api.steampowered.com/ISteamUser/GetPlayerSummaries/v0002/";

    const LANG_PACK_NAME = 'public_steamcf';
    const LANG_APP_KEY = 'nexus';

    const CACHE_KEY = 'steamcf_cache';
    const CACHE_EXPIRY_SECONDS = 300;

    private $registry;
    private $settings;
    private $lang;
    private $cache;

    private $steamApiKey;
    private $classFileManagement;

    private $states;

    public function __construct(ipsRegistry $registry)
    {
        $this->registry = $registry;
        $this->settings =& $this->registry->fetchSettings();

        $this->lang = $this->registry->getClass('class_localization');
        $this->lang->loadLanguageFile(array(self::LANG_PACK_NAME), self::LANG_APP_KEY);

        $this->cache = $this->registry->cache();

        $this->steamApiKey = $this->settings['steamcf_api_key'];

        $classFileManagementClass = IPSLib::loadLibrary( IPS_KERNEL_PATH . 'classFileManagement.php', 'classFileManagement' );
        $this->classFileManagement = new $classFileManagementClass();

        $this->states = array(
            $this->lang->words['steamcf_status_offline'],
            $this->lang->words['steamcf_status_online'],
            $this->lang->words['steamcf_status_busy'],
            $this->lang->words['steamcf_status_away'],
            $this->lang->words['steamcf_status_snooze'],
            $this->lang->words['steamcf_status_looking_to_trade'],
            $this->lang->words['steamcf_status_looking_to_play']
        );
    }

    /**
     * Returns Steam details.
     *
     * @return SteamIdCustomFieldDetails Steam details.
     */
    public function getSteamDetails($steamId)
    {
        $steamId = urlencode($steamId);

        // Check API keys
        if (!$this->steamApiKey) {
            return SteamIdCustomFieldDetails::createErrorDetails($this->lang->words['steamcf_apikey_error']);
        }

        // Ask the steam
        try {
            $results = $this->querySteamCached(array($steamId), $this->steamApiKey);
            if (!array_key_exists($steamId, $results)) {
                return SteamIdCustomFieldDetails::createErrorDetails($this->lang->words['steamcf_player_not_found']);
            }
        }
        catch (Exception $e) {
            return SteamIdCustomFieldDetails::createErrorDetails($e->getMessage());
        }

        return $results[$steamId];
    }

    private function querySteamCached($steamIds, $apiKey)
    {
        $untouchedIds = array();
        $results = array();
        $cache = $this->cache->getCache(self::CACHE_KEY);
        if (!$cache) {
            $cache = array();
        }

        foreach ($steamIds as $steamId) {
            if (array_key_exists($steamId, $cache) && time() < $cache[$steamId]->expiryDate) {
                $results[$steamId] = $cache[$steamId]->details;
            }
            else {
                $untouchedIds[] = $steamId;
            }
        }

        $queriedResults = $this->querySteam($untouchedIds, $apiKey);
        foreach ($queriedResults as $result) {
            $cache[$steamId] = new SteamIdCacheEntry($result, time() + self::CACHE_EXPIRY_SECONDS);
        }

        $this->cache->setCache(self::CACHE_KEY, $cache, array('array'=>1, 'donow'=>1));
        return $results + $queriedResults;
    }

    private function querySteam($steamIds, $apiKey)
    {
        $results = array();
        if (!count($steamIds)) {
            return $results;
        }

        // Get the Steam IDs from Steam
        $joinedIds = implode(',', $steamIds);
        $playerSummaryJson = $this->classFileManagement->getFileContents(self::STEAM_API_GET_PLAYER_SUMMARIES . "?key={$apiKey}&steamids={$joinedIds}&format=json");

        // Check status code
        $status_code = $this->classFileManagement->http_status_code;
				if ($status_code != 200) {
            $errors = $this->classFileManagement->errors;
            if (count($errors)) {
                $error = $errors[0];
                $this->classFileManagement->errors = array();
            }
            else {
                $error = $this->lang->words['steamcf_api_error'] . $status_code;
            }
            throw new Exception($error);
        }

        $playerSummary = json_decode($playerSummaryJson, true);

        // Return an map of steamId => details
        foreach ($playerSummary['response']['players'] as $player) {
            $results[$player['steamid']] = new SteamIdCustomFieldDetails(
                $player['steamid'],
                $player['profileurl'],
                $player['avatarmedium'],
                $player['personaname'],
                $this->personaStateToString($player['personastate']));
        }
        return $results;
    }

    /**
     * Converts Steam Persona State to a String
     *
     * @return string Persona State
     */
    public function personaStateToString($state)
    {
        return $this->states[$state];
    }
}

?>
