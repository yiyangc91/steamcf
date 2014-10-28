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

class skin_steamcf_0 extends output {

//===========================================================================
// Name: showField
//===========================================================================
function showField($f, $fname, $steamDetails, $err, $steamSignInUrl, $linkUrl) {
$IPBHTML = "";

// We don't really know if the template is using <li/> inside the loop,
// neither do we know if they've moved out the script element.
// Don't really know how to work around this without having Nexus itself
// define a proper template method that is responsible for rendering the
// entire custom field row...

//--starthtml--//
$IPBHTML .= <<<EOF
<script type="text/javascript">
    // lol IPB
    options[options.length + 1] = {$f->id};
    steamCF[steamCF.length] = {$f->id};
</script>
<li class="field">
    <fieldset class="row1">
    <label for="f_{$f->id}">
        <strong><if test="fname:|:$fname">{IPSText::htmlspecialchars($fname)}<else />Steam</if></strong>
        <if test="isRequired:|:$f->required"><span class="required">{$this->lang->words['store_required']}</span></if>
    </label>
    <div id="errmsg_{$f->id}" class="message error" style="display: none;">
    </div>
    <ul>
        <if test="useSteamId:|:!is_null($steamDetails) || !is_null($err)">
            <li class="field checkbox">
                <if test="isLoggedInSteam:|:!is_null($err) || $steamDetails->id">
                    {parse template="showSteamInfo" group="steamcf" params="$f, $steamDetails, $err, $linkUrl"}
                <else />
                    {parse template="showSteamLogin" group="steamcf" params="$f, $steamSignInUrl"}
                </if>
            </li>
        </if>
        <li class="field checkbox">
            <input type="radio" class="input_radio" id="steam_use_{$f->id}_gift" name="steam_use_{$f->id}" value="use_gift" <if test="useSteamIdAlternateCheck:|:is_null($steamDetails)">checked="checked"</if> onchange="hideSteamInfo({$f->id})" />
            <label for="steam_use_{$f->id}_gift">{$this->lang->words['steamcf_steam_id']}: </label><input type="text" id="steam_gift_to_{$f->id}" name="steam_gift_to_{$f->id}" onclick="$('steam_use_{$f->id}_gift').click()" />
        </li>
        <if test="isRequiredOption:|:!$f->required">
            <li class="field checkbox">
                <input type="radio" class="input_radio" id="steam_use_{$f->id}_ignored" name="steam_use_{$f->id}" value="use_ignored" onchange="hideSteamInfo({$f->id})" />
                <label for="steam_use_{$f->id}_ignored">{$this->lang->words['steamcf_ignored']}</label>
            </li>
        </if>
    </ul>
    <input type="hidden" id="f_{$f->id}" name="field{$f->id}" value="" />
    </fieldset>
</li>
EOF;
//--endhtml--//

return $IPBHTML;
}

//===========================================================================
// Name: showSteamInfo
//===========================================================================
function showSteamInfo($f, $steamDetails, $err, $linkUrl) {
$IPBHTML = "";

//--starthtml--//
$IPBHTML .= <<<EOF
<if test="isError:|:$err">
    <div class="message error">
        <strong>{IPSText::htmlspecialchars($err)}</strong><br />
        {$this->lang->words['steamcf_contact_admin']}
    </div>
<else />
    <input type="radio" id="steam_use_{$f->id}_steam" class="input_radio" name="steam_use_{$f->id}" value="use_steam" checked="checked" onchange="showSteamInfo({$f->id})" />
    <label for="steam_use_{$f->id}_steam">
        {$this->lang->words['steamcf_my_account']}
    </label>
    <div class="ipsBox_container ipsMargin_top clearfix" id="steam_info_{$f->id}" style="transition: width 1s;">
        <div class="left ipsBox">
            <img src="{IPSText::htmlspecialchars($steamDetails->avatar)}" />
        </div>
        <div class="left ipsPad">
            <h3 class="ipsPad_top_bottom_half"><a href="{IPSText::htmlspecialchars($steamDetails->profile)}">{IPSText::htmlspecialchars($steamDetails->name)}</a> (<a href="{$linkUrl}">{$this->lang->words['steamcf_not_you']}</a>)</h3>
            {parse template="colorizeStatus" group="steamcf" params="$steamDetails->status"}
        </div>
    </div>
    <input type="hidden" name="steam_use_id_{$f->id}" id="steam_use_id_{$f->id}" value="{IPSText::htmlspecialchars($steamDetails->id)}" />
</if>
EOF;
//--endhtml--//

return $IPBHTML;
}

//===========================================================================
// Name: showSteamLogin
//===========================================================================
function showSteamLogin($f, $steamSignInUrl) {
$IPBHTML = "";

//--starthtml--//
$IPBHTML .= <<<EOF
<input type="radio" id="steam_use_{$f->id}_steam" class="input_radio" name="steam_use_{$f->id}" value="use_steam" disabled="disabled" />
<label for="steam_use_{$f->id}_steam">
<a href="{$steamSignInUrl}">
<img alt="Sign in through Steam" src="data:image/png;base64,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" />
</a>
</label>
<span class="desc lighter">{$this->lang->words['steamcf_login_description']}</span>
EOF;
//--endhtml--//

return $IPBHTML;
}

//===========================================================================
// Name: jsResources
//===========================================================================
function jsResources() {
$IPBHTML = "";

//--starthtml--//
$IPBHTML .= <<<EOF
<script type="text/javascript">
    var steamCF = Array();
    var steamCFRegex = [
        /^(https?:\/\/)?(www\.)?steamcommunity\.com\/id\/([a-z]+)\/?$/i,
        /^(https?:\/\/)?(www\.)?steamcommunity\.com\/profiles\/([0-9]+)\/?$/i,
        /^[0-9]{0,18}$/i,
        /^STEAM_[0-9]:[01]:[0-9]{0,9}$/,
        /^\[U:[0-9]:[0-9]{0,9}\]$/,
        /^[a-z0-9._-]+$/i
    ]

    function showSteamInfo(id) {
        var elem = $('steam_info_'+id);
        if (elem && elem.style.display === 'none') {
            new Effect.BlindDown(elem, { duration: 0.4 })
        }
    }

    function hideSteamInfo(id) {
        var elem = $('steam_info_'+id);
        if (elem && elem.style.display !== 'none') {
            new Effect.BlindUp(elem, { duration: 0.4 })
        }
    }

    function steamCFErrorCheckValue(value) {
        if (!value) {
            return "{$this->lang->words['steamcf_empty_error']}";
        }

        var hasMatch = false;
        for (var i = 0, l = steamCFRegex.length; i < l; i++) {
            if (steamCFRegex[i].match(value)) {
                hasMatch = true;
                break;
            }
        }

        if (!hasMatch) {
            var errMsg = "{$this->lang->words['steamcf_validation_error']}";
            return errMsg.replace("%s", value);
        }

        return '';
    }

    function steamCFFillSteamDetails() {
        var productForm = $('product-form');
        var numErrors = 0;

        for (var zomg = 0; zomg < steamCF.length; zomg++) {
            var finalValue;
            var cfId = steamCF[zomg];

            var formElems = productForm.elements['steam_use_'+cfId];
            var doWhat;
            for (var i = 0; i < formElems.length; i++) {
                if (formElems[i].checked) {
                    doWhat = formElems[i].value;
                    break;
                }
            }
    
            var requiredField = $('errmsg_'+cfId);

            if (doWhat === 'use_steam') {
                finalValue = $('steam_use_id_'+cfId).value;
            }
            else if (doWhat == 'use_gift') {
                finalValue = $('steam_gift_to_'+cfId).value;
            }
            else if (doWhat == 'use_ignored') {
                // None option
                $('f_'+cfId).value = '';
                requiredField.style.display = 'none';
                continue;
            }
            else {
                finalValue = '';
            }

            // Hacky. Skip everything below if not expanded.
            if (!optionsExpanded && $('options').style.display == 'none') {
                return true;
            }

            // Derpy error checking
            var error = steamCFErrorCheckValue(finalValue);
            $('f_'+cfId).value = finalValue;
            if (requiredField) {
                if (error) {
                    requiredField.style.display = '';
                    requiredField.innerText = error;
                    numErrors++;
                }
                else {
                    requiredField.style.display = 'none';
                }
            }
        }

        return numErrors == 0;
    }

    function steamCFMangleClick(element) {
        if (element.onclick) {
            var shibe = element.onclick;
            var awesomeShibe = function(e) {
                var success = steamCFFillSteamDetails();
                if (!success) {
                    return false;
                }
                shibe();
            };
            element.onclick = awesomeShibe;
        }
        else {
            element.onclick = function(e) {
                if (!steamCFFillSteamDetails()) {
                    return false;
                }
            };
        }

    }

    // Attach another click event to the "Add to cart" button.
    // Unfortunately no jquery here, so we manually get this to run on document load
    function steamCFMangleAddCart() {
        steamCFMangleClick($('expandOptionsButton'));
        steamCFMangleClick($('submitButton'));
    }

    // Ugh
    if (window.attachEvent) {
        window.attachEvent('onload', steamCFMangleAddCart);
    }
    else {
        if (window.onload) {
            var shibe = window.onload;
            var awesomeOnload = function() {
                steamCFMangleAddCart();
                shibe();
            };
            window.onload = awesomeOnload;
        }
        else {
            window.onload = steamCFMangleAddCart;
        }
    }
</script>
EOF;
//--endhtml--//

return $IPBHTML;
}

//===========================================================================
// Name: colorizeStatus
//===========================================================================
function colorizeStatus($statusName) {
$IPBHTML = '';

//--starthtml--//
$IPBHTML .= <<<EOF
<span class="ipsBadge
<if test="isOnline:|:$statusName === $this->lang->words['steamcf_status_online']">
ipsBadge_green
</if>
<if test="isOffline:|:$statusName === $this->lang->words['steamcf_status_offline']">
ipsBadge_grey
</if>
<if test="isBusy:|:$statusName === $this->lang->words['steamcf_status_busy']">
ipsBadge_red
</if>
<if test="isAway:|:$statusName === $this->lang->words['steamcf_status_away']">
ipsBadge_orange
</if>
<if test="isSnooze:|:$statusName === $this->lang->words['steamcf_status_snooze']">
ipsBadge_lightgrey
</if>
<if test="isLookingToTrade:|:$statusName === $this->lang->words['steamcf_status_looking_to_trade']">
ipsbadge_purple
</if>
<if test="isLookingToPlay:|:$statusName === $this->lang->words['steamcf_status_looking_to_play']">
ipsbadge_purple
</if>
">
{$statusName}
</span>
EOF;
//--endhtml--//

return $IPBHTML;
}

}

?>
