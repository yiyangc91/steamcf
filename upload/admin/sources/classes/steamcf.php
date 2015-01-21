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

    public function __construct($steamId, $steamProfile, $steamAvatar, $steamName, $steamStatus)
    {
        $this->id = $steamId;
        $this->profile = $steamProfile;
        $this->avatar = $steamAvatar;
        $this->name = $steamName;
        $this->status = $steamStatus;
    }
}

/**
 * Contains lots of useful stuff.
 *
 * @author Yiyang Chen <yiyangc91@gmail.com>
 */
class SteamIdCustomFieldController
{
    const STEAM_API_GET_PLAYER_SUMMARIES = "http://api.steampowered.com/ISteamUser/GetPlayerSummaries/v0002/?key=%s&steamids=%s&format=json";
    const STEAM_GET_PLAYER_XML = "http://steamcommunity.com/id/%s?xml=1";

    const LANG_PACK_NAME = 'public_steamcf';
    const LANG_APP_KEY = 'nexus';

    const CACHE_KEY = 'steamcf_cache';
    const CACHE_EXPIRY_SETTING = 'steamcf_cache_expiry';
    const API_KEY_SETTING = 'steamcf_api_key';
    const TEST_MODE_SETTING = 'steamcf_test_mode';

    const CUSTOMURL_CACHE_KEY = 'steamcf_customurl_cache';
    // No expiry, because it never changes.

    // Specified by Steam API
    const MAX_BATCH_SIZE = 100;

    const STEAMID64_BASE = '76561197960265728';

    private $registry;
    private $settings;
    private $lang;
    private $cache;

    private $classFileManagement;

    private $states;

    public function __construct(ipsRegistry $registry)
    {
        $this->registry = $registry;
        $this->settings =& $this->registry->fetchSettings();

        $this->lang = $this->registry->getClass('class_localization');
        $this->lang->loadLanguageFile(array(self::LANG_PACK_NAME), self::LANG_APP_KEY);

        $this->cache = $this->registry->cache();

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
     * Converts any sort of Steam ID to Steam ID 64.
     * Throws an exception if it can't be converted.
     *
     * @param string Some sort of Steam thing
     * @param string Steam ID 64
     */
    public function convertMultiSteamIDToSteamID64($steamId)
    {
        // Check ID, ID3, ID64, CustomID, Profile URL, Custom 'URL'
        // Match the URLs first because we can easily change those
        if (preg_match('#^(https?://)?(www\.)?steamcommunity\.com/id/([a-z]+)/?$#i', $steamId, $matches)) {
            $steamId = $matches[3];
        }
        else if (preg_match('#^(https?://)?(www\.)?steamcommunity\.com/profiles/([0-9]+)/?$#i', $steamId, $matches)) {
            $steamId = $matches[3];
        }

        // Already SteamID64?
        if (preg_match('/^[0-9]{0,18}$/i', $steamId)) {
            return $steamId;
        } 
        else if (preg_match('/^STEAM_[0-9]:[01]:[0-9]{0,9}$/', $steamId)) {
            return $this->convertSteamIDToSteamID64($steamId);
        }
        else if (preg_match('/^\[U:[0-9]:[0-9]{0,9}\]$/', $steamId)) {
            return $this->convertSteamID3ToSteamID64($steamId);
        }
        else if (preg_match('/^[a-z0-9._-]+$/i', $steamId)) {
            return $this->convertCustomIDToSteamID64($steamId);
        }
        else {
            throw new Exception(sprintf($this->lang->words['steamcf_validation_error'], $steamId));
        }
    }

    /**
     * Converts a SteamID64 to a SteamID.
     *
     * @param string SteamID64
     * @return string SteamID
     */
    public function convertSteamID64ToSteamID($steamId64)
    {
        if (PHP_INT_SIZE == 8) {
            $steamId32 = intval($steamId64) - intval(self::STEAMID64_BASE);
            $part1 = $steamId32 & 1;
            $part2 = $steamId32 / 2;
            return sprintf('STEAM_0:%u:%u', $part1, $part2);
        }
        else if (function_exists('bcadd')) {
            $steamId32 = bcsub($steamId64, self::STEAMID64_BASE);
            $part1 = int(substr($steamId32, -1)) % 1;
            $part2 = bcdiv($steamId32, 2);
            return sprintf('STEAM_0:%u:%s', $part1, $part2);
        }
        else {
            throw new Exception(sprintf($this->lang->words['steamcf_library_missing'], 'bcmath'));
        }
    }

    /**
     * Converts a SteamID64 to a SteamID3.
     *
     * @param string SteamID64
     * @return string SteamID3
     */
    public function convertSteamID64ToSteamID3($steamId64)
    {
        if (PHP_INT_SIZE == 8) {
            $steamId32 = intval($steamId64) - intval(self::STEAMID64_BASE);
            return sprintf('[U:1:%u]', $steamId32);
        }
        else if (function_exists('bcadd')) {
            $steamId32 = bcsub($steamId64, self::STEAMID64_BASE);
            return sprintf('[U:1:%s]', $steamId32);
        }
        else {
            throw new Exception(sprintf($this->lang->words['steamcf_library_missing'], 'bcmath'));
        }
    }

    /**
     * Converts a customURL e.g. "hylianloach" to a steamID64. Only
     * pass in the ID section - do not use the full URL!
     *
     * @param string The custom ID to convert
     * @return string SteamID64
     */
    private function convertCustomIDToSteamID64($customId)
    {
        if ($this->settings[self::TEST_MODE_SETTING]) {
            return '76561198087654321';
        }

        if (!function_exists('simplexml_load_string')) {
            throw new Exception(sprintf($this->lang->words['steamcf_library_missing'], 'SimpleXML'));
        }

        $cache = $this->cache->getCache(self::CUSTOMURL_CACHE_KEY);
        if (!$cache) {
            $cache = array();
        }

        // Try and grab it from the cache first!
        if (array_key_exists($customId, $cache)) {
            // Hurray!
            return $cache[$customId];
        }

        // This is slightly more bitchy, but doesn't require the API
        // key. Pull data directly from steamcommunity.
        $customId = urlencode($customId);
        $xmlData = $this->classFileManagement->getFileContents(sprintf(self::STEAM_GET_PLAYER_XML, $customId));

        // Check status code
        $status_code = $this->classFileManagement->http_status_code;
				if ($status_code != 200) {
            $errors = $this->classFileManagement->errors;
            if (count($errors)) {
                $error = $errors[0];
                $this->classFileManagement->errors = array();
            }
            else {
                $error = sprintf($this->lang->words['steamcf_api_error'], $status_code);
            }
            throw new Exception($error);
        }

        $steamProfileDetails = simplexml_load_string($xmlData);
        if (!$steamProfileDetails) {
            throw new Exception($this->lang->words['steamcf_api_bad_response']);
        }
        if ($steamProfileDetails->error) {
            throw new Exception(strval($steamProfileDetails->error));
        }
        $result = $steamProfileDetails->steamID64;
        if (!$result) {
            throw new Exception($this->lang->words['steamcf_api_bad_response']);
        }

        // write the cache value
        $result = strval($result);
        $cache[$customId] = $result;
        $this->cache->setCache(self::CUSTOMURL_CACHE_KEY, $cache, array('array'=>0, 'donow'=>1));

        return $result;
    }

    /**
     * Converts a SteamID23 (e.g. [U:1:XXXXXXX]) to a SteamID64.
     * Assumes that you give it a valid SteamID3.
     *
     * @param string SteamID3
     * @return string SteamID64
     */
    private function convertSteamID3ToSteamID64($steamId)
    {
        // Simply add the base. Easy.
        $exploded = explode(':', $steamId);
        $idPortion = trim($exploded[2], ']');

        if (PHP_INT_SIZE == 8) {
            return strval(intval(self::STEAMID64_BASE) + intval($idPortion));
        }
        else if (function_exists('bcadd')) {
            return bcadd(self::STEAMID64_BASE, $idPortion);
        }
        else {
            throw new Exception(sprintf($this->lang->words['steamcf_library_missing'], 'bcmath'));
        }
    }

    /**
     * Converts SteamID to SteamID64. This assumes that the Steam ID is
     * a syntactically valid SteamID, and the Steam ID belongs to an
     * individual.
     *
     * @param string SteamID.
     * @return string SteamID64, as a string.
     */
    private function convertSteamIDToSteamID64($steamId)
    {
        // STEAM_0:1:XXXXXXXX
        $exploded = explode(':', $steamId);

        if (PHP_INT_SIZE == 8) {
            return strval(intval(self::STEAMID64_BASE) + intval($exploded[2])*2 + intval($exploded[1]));
        }
        else if (function_exists('bcadd')) {
            // bc must be available on php!
            return bcadd(bcadd(self::STEAMID64_BASE, bcmul($exploded[2], 2)), $exploded[1]);
        }
        else {
            // ... um.
            throw new Exception(sprintf($this->lang->words['steamcf_library_missing'], 'bcmath'));
        }
    }

    /**
     * Returns Steam details.
     *
     * @param string Steam ID.
     * @return SteamIdCustomFieldDetails Steam details, or NULL if not found.
     */
    public function getSteamDetails($steamId)
    {
        $steamId = urlencode($steamId);

        // Check API keys
        if (!$this->settings[self::API_KEY_SETTING]) {
            throw new Exception($this->lang->words['steamcf_apikey_error']);
        }

        // Ask the steam
        $results = $this->querySteamCached(array($steamId), $this->settings[self::API_KEY_SETTING]);
        if (!array_key_exists($steamId, $results)) {
            return NULL;
        }

        return $results[$steamId];
    }

    /**
     * Encodes the entire array.
     *
     * @param array Array
     * @return array Encoded array
     */
    private static function urlEncodeArray($arr)
    {
        $newArray = array();
        foreach ($arr as $v) {
            $newArray[] = urlencode($v);
        }
        return $newArray;
    }

    /**
     * Returns Steam details as an array.
     *
     * @param array Array of steam IDs.
     * @return array Array of Steam details.
     */
    public function getSteamDetailsBatch($steamIds)
    {
        // unfuck IDs
        $steamIds = self::urlEncodeArray($steamIds);

        // Check API keys
        if (!$this->settings[self::API_KEY_SETTING]) {
            throw new Exception($this->lang->words['steamcf_apikey_error']);
        }

        $chunkedSteamIds = array_chunk($steamIds, self::MAX_BATCH_SIZE);
        $results = array();

        foreach ($chunkedSteamIds as $chunk) {
            $results += $this->querySteamCached($chunk, $this->settings[self::API_KEY_SETTING]);
        }

        return $results;
    }

    private function mockSteamData($steamIds)
    {
        $results = array();

        foreach ($steamIds as $steamId) {
            $results[$steamId] = new SteamIdCustomFieldDetails(
                $steamId,
                'http://www.example.com',
                'http://www.example.com/example.png',
                'example',
                'Online');
        }

        return $results;
    }

    private function querySteamCached($steamIds, $apiKey)
    {
        if ($this->settings[self::TEST_MODE_SETTING]) {
            return $this->mockSteamData($steamIds);
        }

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
            $cache[$result->id] = new SteamIdCacheEntry($result, time() + $this->settings[self::CACHE_EXPIRY_SETTING]);
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
        $playerSummaryJson = $this->classFileManagement->getFileContents(sprintf(self::STEAM_API_GET_PLAYER_SUMMARIES, $apiKey, $joinedIds));

        // Check status code
        $status_code = $this->classFileManagement->http_status_code;
				if ($status_code != 200) {
            $errors = $this->classFileManagement->errors;
            if (count($errors)) {
                $error = $errors[0];
                $this->classFileManagement->errors = array();
            }
            else {
                $error = sprintf($this->lang->words['steamcf_api_error'], $status_code);
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
