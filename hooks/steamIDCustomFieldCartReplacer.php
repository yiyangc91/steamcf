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
    const STEAMCF_PRE_LOOP_TAG = '<!--hook.foreach.skin_nexus_payments.viewCart.cfields.inner.pre-->';
    const STEAMCF_POST_LOOP_TAG = '<!--hook.foreach.skin_nexus_payments.viewCart.cfields.inner.post-->';

    const STEAMCF_STEAM_ID_FIELD_NAME = 'SteamID';
    const STEAMCF_STEAM_ID_FIELD_TYPE = 'text';
    const STEAMCF_HOOK_TEMPLATE_NAME = 'steamcf';

    const EM_TAG = '<em>';
    const EM_CLOSE_TAG = '</em>';
    const BR_TAG = '<br />';

    private $registry;

    private $detailsClass;
    private $controller;

    public function __construct()
    {
        $this->registry	= ipsRegistry::instance();

        $this->detailsClass = IPSLib::loadLibrary(IPS_ROOT_PATH . '/sources/classes/steamcf.php', 'steamIDCustomFieldDetails');

        $controllerClass = IPSLib::loadLibrary(IPS_ROOT_PATH . '/sources/classes/steamcf.php', 'steamIDCustomFieldController');
        $this->controller = new $controllerClass($this->registry);
    }
    
    public function getOutput() {}

    private function replaceSteam($data)
    {
        $steamName = $data['steamName'] ? $data['steamName'] : 'Steam';
        $steamId = $data['steamId'];
        $extra = '';

        // Attempt to retrieve dude
        $details = $this->controller->getSteamDetails($steamId);
        if ($details->error)
        {
            $extra = ' ERROR (' . $details->error . ')';
        }
        else
        {
            $steamId = $details->name;
            $extra = ' (' . $details->profile . ')';
        }

        // If everything good
        return $steamName . ': ' . $steamId . $extra;
    }

    private static function isPartSteamID($part)
    {
        $success = preg_match('/^SteamID([a-zA-Z]*): (.+)$/', $part, $matches);
        if ($success)
        {
            return array(
                'steamName' => $matches[1],
                'steamId' => $matches[2]
            );
        }
        else
        {
            return NULL;
        }
    }

    /**
     *
     * @param	string		Output
     * @param	string		Hook key
     * @return	string		Output parsed
     */
    public function replaceOutput($output, $key)
    {
        $original_parameters = $this->registry->output->getTemplate('nexus_payments')->functionData['viewCart'];
        if (!is_array($original_parameters) || !count($original_parameters))
        {
            return $output;
        }

        $offset = 0;

        foreach ($original_parameters as $parameter)
        {
            $cart = $parameter['cart'];

            foreach ($cart['items'] as $id => $i)
            {
                foreach ($i['_cfields'] as $k => $v)
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

                    // Also find the fuckin <em> bullshit. Dis gon b fun
                    $em_pos = strpos($output, self::EM_TAG, $pos_pre_end) + strlen(self::EM_TAG);
                    $em_end_pos = strpos($output, self::EM_CLOSE_TAG, $em_pos);
                    if ($em_pos >= $pos_post || $em_end_pos+strlen(self::EM_CLOSE_TAG) >= $pos_post)
                    {
                        // Some shit broke with finding <em>
                        return $output;
                    }

                    // I call this the yolo replace
                    $em_data_len = $em_end_pos - $em_pos;
                    $em_data = substr($output, $em_pos, $em_data_len);
                    $split_em_data = preg_split('/<br *\\/?>/', $em_data);
                    $inner_output = array();
                    foreach ($split_em_data as $part)
                    {
                        $data = self::isPartSteamID($part);
                        if ($data != NULL)
                        {
                            $inner_output[] = $this->replaceSteam($data);
                        }
                        else
                        {
                            $inner_output[] = $part;
                        }
                    }

                    $inner_output_str = implode('<br />', $inner_output);
                    $new_data_len = strlen($inner_output_str);
                    $output = substr_replace($output, $inner_output_str, $em_pos, $em_data_len);
                    $offset = $pos_post + strlen(self::STEAMCF_POST_LOOP_TAG) - $em_data_len + $new_data_len;
                }
            }
        }

        return $output;
    }

}

?>
