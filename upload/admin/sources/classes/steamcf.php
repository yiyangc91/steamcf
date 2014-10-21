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
 * Dumb struct to handle steam ID details
 *
 * @author Yiyang Chen <yiyangc91@gmail.com>
 */
class steamIDCustomFieldDetails
{
    public $id;
    public $profile;
    public $avatar;
    public $name;
    public $status;

    public $error;

    /**
     * Creates the steam details, except with NULLS EVERYWHERE.
     * 
     * @return steamIDCustomFieldDetails NULL details.
     */
    public static function createEmptyDetails()
    {
        // Oh god, nulls everywhere.
        // This could probably be done better
        return new steamIDCustomFieldDetails(NULL, NULL, NULL, NULL, NULL);
    }

    /**
     * Creates the steam details, except with nothing but an error.
     * This could seriously be done better, this is a giant hack.
     *
     * @return steamIDCustomFieldDetails ERROR details.
     */
    public static function createErrorDetails($errorMsg)
    {
        return new steamIDCustomFieldDetails(NULL, NULL, NULL, NULL, NULL, $errorMsg);
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
class steamIDCustomFieldController
{
    const STEAM_API_GET_PLAYER_SUMMARIES = "http://api.steampowered.com/ISteamUser/GetPlayerSummaries/v0002/";

    private $registry;
    private $settings;
    private $lang;

    private $steamApiKey;
    private $classFileManagement;

    private $states;

    public function __construct(ipsRegistry $registry)
    {
        $this->registry = $registry;
        $this->settings =& $this->registry->fetchSettings();
        $this->lang = $this->registry->getClass('class_localization');

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
     * @return steamIDCustomFieldDetails Steam details.
     */
    public function getSteamDetails($steamid)
    {
        $steamid = urlencode($steamid);

        // Check stupid API keys first
        if (!$this->steamApiKey)
        {
            return steamIDCustomFieldDetails::createErrorDetails($this->lang->words['steamcf_apikey_error']);
        }
        $playerSummaryJson = $this->classFileManagement->getFileContents(self::STEAM_API_GET_PLAYER_SUMMARIES . "?key={$this->steamApiKey}&steamids={$steamid}&format=json");

        // Check that we are not 200, and error out
        $status_code = $this->classFileManagement->http_status_code;
				if ($status_code != 200)
        {
            // Great. API key is fucked. Error everything?
            $errors = $this->classFileManagement->errors;
            if (count($errors))
            {
                $error = $errors[0];
                $this->classFileManagement->errors = array();
            }
            else
            {
                $error = $this->lang->words['steamcf_api_error'] . $status_code;
            }
            return steamIDCustomFieldDetails::createErrorDetails($error);
        }

        $playerSummary = json_decode($playerSummaryJson, true);

        if (count($playerSummary['response']['players']) != 1)
        {
            return steamIDCustomFieldDetails::createErrorDetails($this->lang->words['steamcf_player_not_found']);
        }
        $player = $playerSummary['response']['players'][0];
        return new steamIDCustomFieldDetails($steamid, $player['profileurl'], $player['avatarmedium'], $player['personaname'], $this->personaStateToString($player['personastate']));
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
