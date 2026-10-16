<?php
/*
    This file is part of the eQual framework <http://www.github.com/equalframework/equal>
    Some Rights Reserved, eQual framework, 2010-2026
    Original author(s): Cedric FRANCOYS
    Licensed under GNU GPL 3 license <http://www.gnu.org/licenses/>
*/

use discope\setting\Setting;

Setting::assert_value('sale', 'booking', 'cubilis.api.unreachable.count', 0);
Setting::assert_value('sale', 'booking', 'cubilis.api.unreachable.threshold', 3);
