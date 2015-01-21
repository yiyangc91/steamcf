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
 * Action Overloader: public_nexus_payments_store
 *
 * @author Yiyang Chen <yiyangc91@gmail.com>
 */
class SteamIDCustomFieldVerify extends public_nexus_payments_store
{
    const STEAM_ID_FIELD_NAME_SETTING = 'steamcf_field_prefix';
    const STEAM_ID_FIELD_TYPE = 'text';

    const STORAGE_FORMAT_SETTING = 'steamcf_storage_format';

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

        $packageId = intval($this->request['id']);
        $steamIds = $this->getSteamFieldValues($packageId);

        $steamId64s = array(); // Internal representation
        try {
            foreach ($steamIds as $fieldId => $steamId) {
                $converted = $this->steamCFController->convertMultiSteamIDToSteamID64($steamId); 
                $steamId64s[$steamId] = $converted;

                // This is the thing that's actually stored, and depends on
                // user preference.
                if ($this->settings[self::STORAGE_FORMAT_SETTING] === 'steamId') {
                    $this->request['field' . $fieldId] = $this->steamCFController->convertSteamID64ToSteamID($converted);
                }
                else if ($this->settings[self::STORAGE_FORMAT_SETTING] === 'steamId3') {
                    $this->request['field' . $fieldId] = $this->steamCFController->convertSteamID64ToSteamID3($converted);
                }
                else {
                    $this->request['field' . $fieldId] = $converted;
                }
            }
        }
        catch (Exception $e) {
            $this->registry->output->showError($e->getMessage(), 0, FALSE, '', 400);
        }

        // Attempt to validate Steam IDs just by asking Steam
        try {
            $steamDetails = $this->steamCFController->getSteamDetailsBatch($steamId64s);
        }
        catch (Exception $e) {
            $this->registry->output->showError($e->getMessage(), 0, FALSE, '', 500);
        }
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
     * Get request field values.
     *
     * @return array Field ID to value in request
     */
    private function getSteamFieldValues($packageId)
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
            $packages = $field['packages'];

            // Skip if the field isn't part of our package
            if (!is_array($packages) || !in_array(strval($packageId), $packages)
                || !($field['cf_purchase'] || $field['cf_required'])) {
                continue;
            }
            
            // Skip if the field isn't required, and it's empty
            $fieldValue = $this->request['field' . $fieldId];
            if (!$field['cf_required'] && !$fieldValue) {
                continue;
            }

            // Do only if the field is our Steam field
            if (substr($fieldName, 0, strlen($this->settings[self::STEAM_ID_FIELD_NAME_SETTING])) === $this->settings[self::STEAM_ID_FIELD_NAME_SETTING] && $fieldType === self::STEAM_ID_FIELD_TYPE) {
                // "Unfortunately" the value is not also in the cache
                // Grab it straight from the request
                $results[$fieldId] = $fieldValue;
            }
        }

        return $results;
    }
}

?>
