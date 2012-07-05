<?php

/**
 * Copyright (c) Enalean, 2012. All Rights Reserved.
 *
 * This file is a part of Tuleap.
 *
 * Tuleap is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * Tuleap is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Tuleap. If not, see <http://www.gnu.org/licenses/>.
 */

require_once dirname(__FILE__).'/../../../include/View/AdminView.class.php';

class Cardwall_OnTop_Config_Trackers_getNonMappedTrackersTest extends TuleapTestCase {
    
    public function itReturnsAllTrackersWhenNothingIsMapped() {
        $trackers = array(10 => aTracker()->withId(10)->build(),
                          11 => aTracker()->withId(11)->build());
        $mappings = new Cardwall_OnTop_Config_MappimgFields(array());
        $config_trackers = new Cardwall_OnTop_Config_Trackers($trackers, aTracker()->withId(77)->build(), $mappings);
        $this->assertEqual($trackers, $config_trackers->getNonMappedTrackers());
    }
    
    public function itStripsTheCurrentTracker() {
        $current_tracker = aTracker()->withId(10)->build();
        $other_tracker   = aTracker()->withId(99)->build();
        $trackers = array(10 => $current_tracker,
                          99 => $other_tracker);
        $mappings = new Cardwall_OnTop_Config_MappimgFields(array());
        $config_trackers = new Cardwall_OnTop_Config_Trackers($trackers, $current_tracker, $mappings);
        $this->assertEqual(array(99 => $other_tracker), $config_trackers->getNonMappedTrackers());
    }
    
    public function itdoesSomething() {
    }
}
?>
