<?php
// This file is part of local_automatic_badges - https://moodle.org/.
//
// local_automatic_badges is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// local_automatic_badges is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with local_automatic_badges.  If not, see <https://www.gnu.org/licenses/>.

/**
 * External services definition for local_automatic_badges.
 *
 * @package    local_automatic_badges
 * @author     Daniela Alexandra Patiño Dávila
 * @author     Cristian Julian Lamus Lamus
 * @copyright  2026 Daniela Alexandra Patiño Dávila, Cristian Julian Lamus Lamus
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$functions = [

    'local_automatic_badges_save_badge_design' => [
        'classname'    => 'local_automatic_badges\external\save_badge_design',
        'methodname'   => 'execute',
        'description'  => 'Save a badge design created in the badge designer and create the badge record.',
        'type'         => 'write',
        'ajax'         => true,
        'loginrequired' => true,
        'capabilities' => 'moodle/badges:createbadge',
    ],

    'local_automatic_badges_load_activities' => [
        'classname'    => 'local_automatic_badges\external\load_activities',
        'methodname'   => 'execute',
        'description'  => 'Load eligible activities for a given criterion type.',
        'type'         => 'read',
        'ajax'         => true,
        'loginrequired' => true,
    ],

];
