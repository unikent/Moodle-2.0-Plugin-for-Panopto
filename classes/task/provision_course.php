<?php

// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * @package block_panopto
 * @copyright Panopto 2009 - 2015 with contributions from Spenser Jones (sjones@ambrose.edu) and by Skylar Kelty <S.Kelty@kent.ac.uk>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_panopto\task;

defined('MOODLE_INTERNAL') || die();

require_once(dirname(__FILE__) . '/../../lib/panopto_data.php');

/**
 * Panopto "provision course" task.
 */
class provision_course extends \core\task\adhoc_task {

    public function get_component() {
        return 'block_panopto';
    }

    public function execute() {
        $eventdata = (array) $this->get_custom_data();

        $panopto = new \panopto_data($eventdata['courseid']);
        $panopto->servername = $eventdata['servername'];
        $panopto->applicationkey = $eventdata['appkey'];

        $provisioninginfo = $panopto->get_provisioning_info();
        $provisioneddata = $panopto->provision_course($provisioninginfo);
    }
}
