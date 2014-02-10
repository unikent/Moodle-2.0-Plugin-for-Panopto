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
 * Panopto Block Install Script
 *
 * @package    block_panopto
 * @copyright  2014 Skylar Kelty <S.Kelty@kent.ac.uk>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

function xmldb_block_panopto_install() {
    global $CFG;

    create_role(
    	"Academic (Panopto)",
    	"panopto_academic",
    	"Panopto Academic User",
        "panopto_academic"
    );

    create_role(
    	"Non-Academic (Panopto)",
    	"panopto_non_academic",
    	"Panopto Non-Academic User",
        "panopto_non_academic"
    );

    create_role(
        "Course Creator (Panopto)",
        "panopto_creator",
        "Panopto Course Creator",
        "panopto_creator"
    );
}