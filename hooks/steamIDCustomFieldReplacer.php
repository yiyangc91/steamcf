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
 * - Nothing if the field isn't required.
 *
 * @author Yiyang Chen <yiyangc91@gmail.com>
 */
class SteamIDCustomFieldReplacer
{
    const TEMPLATE_HOOK_NAME = 'nexus_payments';
    const TEMPLATE_HOOK_FUNCTION = 'viewItem';

    // Unfortunately this thing here requires something hooked onto BOTH inner.pre
    // and inner.post.
    const PRE_LOOP_TAG = '<!--hook.foreach.skin_nexus_payments.viewItem.fields.inner.pre-->';
    const POST_LOOP_TAG = '<!--hook.foreach.skin_nexus_payments.viewItem.fields.inner.post-->';

    const STEAM_ID_FIELD_NAME = 'SteamID';
    const STEAM_ID_FIELD_TYPE = 'text';
    const TEMPLATE_NAME = 'steamcf';

    const ENABLE_MOCK = false;
    const MOCK_STEAM_ID = '76561197997927385';

    const LANG_PACK_NAME = 'public_steamcf';
    const LANG_APP_KEY = 'nexus';

    private $lang;
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
     *
     * @param mixed Some sort of IP. Nexus custom field.
     * @return string Template output.
     */
    private function getSteamFieldTemplate($customField)
    {
        $steamFieldName = substr($customField->name, strlen(self::STEAM_ID_FIELD_NAME));
        $steamSignIn = $this->steamOAuth;
        $steamOAuthURL = NULL;
        $detailsClass = $this->detailsClass;
        $boardUrl = $this->settings['logins_over_https'] ? $this->settings['board_url_https'] : $this->settings['board_url'];
        $linkUrl = $boardUrl.'/interface/board/linksteam.php';

        // Get Steam Details
        $err = NULL;
        $steamDetails = NULL;
        try {
            if (self::ENABLE_MOCK) {
                $steamId = self::MOCK_STEAM_ID;
                $steamDetails = $this->controller->getSteamDetails($steamId);
            }
            else if ($this->steamOAuthExists) {
                // Steam OAuth installed
                $steamId = $this->member->getProperty('steamid');
                if (!$steamId) {
                    $steamOAuthURL = $steamSignIn::genUrl($linkUrl);
                    $steamDetails = $detailsClass::createEmptyDetails();
                }
                else {
                    $steamDetails = $this->controller->getSteamDetails($steamId);
                    if (!$steamDetails) {
                        $err = $this->lang->words['steamcf_player_not_found'];
                    }
                }
            }
        }
        catch (Exception $e) {
            $err = $e->getMessage();
        }

        return $this->registry->output->getTemplate(self::TEMPLATE_NAME)->showField($customField, $steamFieldName, $steamDetails, $err, $steamOAuthURL, $linkUrl);
    }

    public function __construct()
    {
        $this->lang = ipsRegistry::getClass('class_localization');
        $this->lang->loadLanguageFile(array(self::LANG_PACK_NAME), self::LANG_APP_KEY);

        $this->registry	= ipsRegistry::instance();
        $this->member = ipsRegistry::member();
        $this->settings =& $this->registry->fetchSettings();
        $this->DB = $this->registry->DB();

        $this->steamOAuthExists = file_exists(IPS_ROOT_PATH .  '/sources/loginauth/steam/lib/steam_openid.php') && $this->DB->checkForField('steamid', 'members');
        if ($this->steamOAuthExists) {
            $this->steamOAuth = IPSLib::loadLibrary(IPS_ROOT_PATH . '/sources/loginauth/steam/lib/steam_openid.php', 'SteamSignIn');
        }

        $this->detailsClass = IPSLib::loadLibrary(IPS_ROOT_PATH . '/sources/classes/steamcf.php', 'SteamIdCustomFieldDetails');

        $controllerClass = IPSLib::loadLibrary(IPS_ROOT_PATH . '/sources/classes/steamcf.php', 'SteamIdCustomFieldController');
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
        $originalParameters = $this->registry->output->getTemplate(self::TEMPLATE_HOOK_NAME)->functionData[self::TEMPLATE_HOOK_FUNCTION];
        if (!is_array($originalParameters) || !count($originalParameters)) {
            return $output;
        }

        // The idea here is to perform the same loop as the 'foreach'
        // we are hooked onto. We *SHOULD* get one tag per item in the loop.
        $offset = 0;

        foreach ($originalParameters as $parameter) {
            $customFields = $parameter['customfields'];

            foreach ($customFields as $customfield) {
                $posPre = strpos($output, self::PRE_LOOP_TAG, $offset);
                if (!$posPre) {
                    // Wtf? This is very strange.
                    // Guess we'll silently fail...
                    return $output;
                }
                $posPreEnd = $posPre+strlen(self::PRE_LOOP_TAG);
                $posPost = strpos($output, self::POST_LOOP_TAG, $posPreEnd);
                if (!$posPost) {
                    // Um..? So we're missing the post. Silently fail again.
                    return $output;
                }
                $dataLength = $posPost - $posPreEnd;

                // Everything between $posPreEnd and $posPost is dataz
                if (substr($customfield->name, 0, strlen(self::STEAM_ID_FIELD_NAME)) === self::STEAM_ID_FIELD_NAME && $customfield->type === self::STEAM_ID_FIELD_TYPE) {
                    $replacementData = $this->getSteamFieldTemplate($customfield);
                    $replacementDataLength = strlen($replacementData);
                    $output = substr_replace($output, $replacementData, $posPreEnd, $dataLength);
                    $offset = $posPreEnd + $replacementDataLength + strlen(self::POST_LOOP_TAG);
                }
                else {
                    $offset = $posPost + strlen(self::POST_LOOP_TAG);
                }
            }
        }

        return $output;
    }

}

?>
