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
 * Finds an instance of a field type 'text' and named 'SteamID'.
 * Replaces that text field with a choice between:
 * - Selecting the user's currently authenticated Steam account
 * - Providing a steam account
 *
 * This should populate the field wth the user's Profile ID. The
 * 'steamIDCFParser' hook will take this profile ID and convert it
 * into a suitable format - or throw an error back to the user
 * indicating that something is wrong with the steam they provided.
 * 
 * @author Yiyang Chen <yiyangc91@gmail.com>
 */
class steamIDCustomFieldReplacer
{
    // Unfortunately this thing here requires something hooked onto BOTH inner.pre
    // and inner.post.
    const STEAMCF_PRE_LOOP_TAG = '<!--hook.foreach.skin_nexus_payments.viewItem.fields.inner.pre-->';
    const STEAMCF_POST_LOOP_TAG = '<!--hook.foreach.skin_nexus_payments.viewItem.fields.inner.post-->';

    const STEAMCF_STEAM_ID_FIELD_NAME = 'SteamID';
    const STEAMCF_STEAM_ID_FIELD_TYPE = 'text';
    const STEAMCF_HOOK_TEMPLATE_NAME = 'steamcf';
    const STEAMCF_SHOULD_MOCK_STEAM = false;

    private $registry;
    private $member;
    private $settings;
    private $DB;
    private $steamOAuthExists;
    private $steamOAuth;

    private $detailsClass;
    private $controller;

    /**
     * Helper method to get the steam field output.
     * @return string Template output.
     */
    private function getSteamFieldTemplate($customField)
    {
        $steamFieldName = substr($customField->name, strlen(self::STEAMCF_STEAM_ID_FIELD_NAME));
        $steamSignIn = $this->steamOAuth;
        $steamOAuthURL = NULL;
        $detailsClass = $this->detailsClass;
        $board_url = $this->settings['logins_over_https'] ? $this->settings['board_url_https'] : $this->settings['board_url'];
        $linkUrl = $board_url.'/interface/board/linksteam.php';

        // Mock things
        if (self::STEAMCF_SHOULD_MOCK_STEAM)
        {
            $steamId = "76561197997927385";
            $steamDetails = $this->controller->getSteamDetails($steamId);
        }
        else
        {
            // Check if that Steam OAuth plugin is installed
            if ($this->steamOAuthExists)
            {
                // Steam OAuth installed
                $steamId = $this->member->getProperty('steamid');
                if (!$steamId)
                {
                    $steamOAuthURL = $steamSignIn::genUrl($linkUrl);
                    $steamDetails = $detailsClass::createEmptyDetails();
                }
                else
                {
                    $steamDetails = $this->controller->getSteamDetails($steamId);
                }
            }
            else
            {
                // Steam OAuth not installed
                $steamDetails = NULL;
            }
        }

        return $this->registry->output->getTemplate(self::STEAMCF_HOOK_TEMPLATE_NAME)->showField($customField, $steamFieldName, $steamDetails, $steamOAuthURL, $linkUrl);
    }

    public function __construct()
    {
        $this->registry	= ipsRegistry::instance();
        $this->member = ipsRegistry::member();
        $this->settings =& $this->registry->fetchSettings();
        $this->DB = $this->registry->DB();

        $this->steamOAuthExists = file_exists(IPS_ROOT_PATH . '/sources/loginauth/steam/lib/steam_openid.php') && $this->DB->checkForField('steamid', 'members');
        if ($this->steamOAuthExists)
        {
            $this->steamOAuth = IPSLib::loadLibrary(IPS_ROOT_PATH . '/sources/loginauth/steam/lib/steam_openid.php', 'SteamSignIn');
        }

        $this->detailsClass = IPSLib::loadLibrary(IPS_ROOT_PATH . '/sources/classes/steamcf.php', 'steamIDCustomFieldDetails');

        $controllerClass = IPSLib::loadLibrary(IPS_ROOT_PATH . '/sources/classes/steamcf.php', 'steamIDCustomFieldController');
        $this->controller = new $controllerClass($this->registry);
    }
    
    public function getOutput() {}

    /**
     * In this replace hook, we loop over every field, looking for the
     * "SteamID" field. We then retrieve the template for the SteamID
     * field and *replace* the ENTIRE block with our HTML.
     *
     * @param	string		Output
     * @param	string		Hook key
     * @return	string		Output parsed
     */
    public function replaceOutput($output, $key)
    {
        $original_parameters = $this->registry->output->getTemplate('nexus_payments')->functionData['viewItem'];
        if (!is_array($original_parameters) || !count($original_parameters))
        {
            return $output;
        }

        // The idea here is to perform the same loop as the 'foreach'
        // we are hooked onto. We *SHOULD* get one tag per item in the loop.
        $offset = 0;

        foreach ($original_parameters as $parameter)
        {
            $customfields = $parameter['customfields'];

            foreach ($customfields as $customfield)
            {
                $pos_pre = strpos($output, self::STEAMCF_PRE_LOOP_TAG, $offset);
                if (!$pos_pre) {
                    // Wtf? This is very strange.
                    // Guess we'll silently fail...
                    return $output;
                }
                $pos_pre_end = $pos_pre+strlen(self::STEAMCF_PRE_LOOP_TAG);
                $pos_post = strpos($output, self::STEAMCF_POST_LOOP_TAG, $pos_pre_end);
                if (!$pos_post) {
                    // Um..? So we're missing the post. Silently fail again.
                    return $output;
                }
                $data_length = $pos_post - $pos_pre_end;

                // Everything between $pos_pre_end and $pos_post is dataz
                if (substr($customfield->name, 0, strlen(self::STEAMCF_STEAM_ID_FIELD_NAME)) === self::STEAMCF_STEAM_ID_FIELD_NAME && $customfield->type === self::STEAMCF_STEAM_ID_FIELD_TYPE)
                {
                    $replacement_data = $this->getSteamFieldTemplate($customfield);
                    $replacement_data_len = strlen($replacement_data);
                    $output = substr_replace($output, $replacement_data, $pos_pre_end, $data_length);
                    $offset = $pos_pre_end + $replacement_data_len + strlen(self::STEAMCF_POST_LOOP_TAG);
                }
                else
                {
                    $offset = $pos_post + strlen(self::STEAMCF_POST_LOOP_TAG);
                }
            }
        }

        return $output;
    }

}

?>
