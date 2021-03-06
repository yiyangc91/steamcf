<?php

/*
 * Steam Custom Package Field
 * Copyright (C) 2014  Yiyang Chen
 * 
 * This file is part of Steam Custom Package Field.
 * 
 * Steam Custom Package Field is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or (at your
 * option) any later version.
 * 
 * Steam Custom Package Field is distributed in the hope that it will be
 * useful, but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU General
 * Public License for more details.
 * 
 * You should have received a copy of the GNU General Public License along with
 * this program; if not, see <http://www.gnu.org/licenses/>
 */

/**
 * Responsible for making sure IPB gives us the hook point in the other class.
 * Simply by existing as hook. Aww yiss.
 *
 * This is actually used in two locations:
 * Template Hook: foreach inner.pre skin_nexus_payments.viewItem.fields
 * Template Hook: foreach inner.post skin_nexus_payments.viewCart.cfields
 *
 * @author Yiyang Chen <yiyangc91@gmail.com>
 */
class SteamIDCustomFieldNoop
{
    public function getOutput() {}
}

?>
