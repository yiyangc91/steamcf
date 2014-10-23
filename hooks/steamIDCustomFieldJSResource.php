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
 * A hook to inject our custom field JS resources
 */
class SteamIDCustomFieldJSResource
{
    const STEAMCF_HOOK_TEMPLATE_NAME = 'steamcf';

    const LANG_PACK_NAME = 'public_steamcf';
    const LANG_APP_KEY = 'nexus';

    private $registry;

    public function getOutput()
    {
        return $this->registry->output->getTemplate(self::STEAMCF_HOOK_TEMPLATE_NAME)->jsResources();
    }

    public function __construct()
    {
        ipsRegistry::getClass('class_localization')->loadLanguageFile(array(self::LANG_PACK_NAME), self::LANG_APP_KEY);

        $this->registry = ipsRegistry::instance();
    }
}

?>
