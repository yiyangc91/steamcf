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
 * This hook is in charge of verifying that the Steam IDs written by the user
 * are somewhat valid. It does so by asking Steam if the ID is valid.
 *
 * @author Yiyang Chen <yiyangc91@gmail.com>
 */
class SteamIDCustomFieldVerify extends public_nexus_payments_store
{
    const STEAM_ID_FIELD_NAME = 'SteamID';
    const STEAM_ID_FIELD_TYPE = 'text';

    private $steamCFController;

    /**
     * Sets the steam CF controller.
     */
    private function initializeSteamCFController()
    {
        if (!$this->steamCFController) {
            $controllerClass = IPSLib::loadLibrary(IPS_ROOT_PATH . '/sources/classes/steamcf.php', 'SteamIdCustomFieldController');
            $this->steamCFController = new $controllerClass($this->registry);
        }
    }

    /**
     * Used by array_map to pull the ID from steam details.
     *
     * @return string ID.
     */
    private static function getIdFromDetails($details)
    {
        return $details->id;
    }

    /**
     * Overrides the addItem() method from nexus payments to also perform
     * checking of things.
     */
    public function addItem()
    {
        $this->initializeSteamCFController();

        $steamIds = $this->getSteamFieldValues();

        $steamId64s = array();
        foreach ($steamIds as $steamId) {
            // TODO: Convert values to SteamID64 to allow permissive steam IDs
            // ...['input'] = <64>
            $steamId64s[$steamId] = $steamId;
        }

        // Attempt to validate Steam IDs just by asking Steam
        $steamDetails = $this->steamCFController->getSteamDetailsBatch($steamId64s);
        $validSteamIds = array_map('self::getIdFromDetails', $steamDetails);

        // Since $steamId64s is a map of <input> => <steam64>, we filter out all the
        // valid <steam64>. Then we flip the array to get <input> on the other side.
        $invalidSteamIds = array_diff($steamId64s, $validSteamIds);
        if (count($invalidSteamIds)) {
            $invalidInputs = array_flip($invalidSteamIds);

            $this->registry->output->showError(sprintf($this->lang->words['steamcf_invalid_steam_ids'], implode(', ', $invalidInputs)), 0, FALSE, '', 400);
        }
        
        return parent::addItem();
    }

    /**
     * Had to do some serious digging to figure out how to do this.
     * Please IPS, open source nexus. This is ridiculously hard to do.
     *
     * @return array Field ID to value in request
     */
    private function getSteamFieldValues()
    {
        $fields = $this->cache->getCache('package_fields');
        $results = array();

        // So we'll basically get stuff like cf_id, cf_name, cf_type
        // Fortunately these are all custom fields (?) and so we can apply
        // the same sort of logic we did in the "Replacer".
        // 
        // This is somewhat duplicate code
        foreach ($fields as $field) {
            $fieldId = $field['cf_id'];
            $fieldName = $field['cf_name'];
            $fieldType = $field['cf_type'];

            if (substr($fieldName, 0, strlen(self::STEAM_ID_FIELD_NAME)) === self::STEAM_ID_FIELD_NAME && $fieldType === self::STEAM_ID_FIELD_TYPE) {
                // "Unfortunately" the value is not also in the cache
                // Grab it straight from the request
                $results[$fieldId] = $this->request['field' . $fieldId];
            }
        }

        return $results;
    }
}

?>
