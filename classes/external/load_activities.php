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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * External service to load eligible activities for a given criterion type.
 *
 * @package    local_automatic_badges
 * @author     Daniela Alexandra Patiño Dávila
 * @author     Cristian Julian Lamus Lamus
 * @copyright  2026 Daniela Alexandra Patiño Dávila, Cristian Julian Lamus Lamus
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_automatic_badges\external;

use external_api;
use external_function_parameters;
use external_multiple_structure;
use external_single_structure;
use external_value;

/**
 * External function to load eligible activities filtered by criterion type and module name.
 */
class load_activities extends external_api {
    /**
     * Defines the parameters accepted by this external function.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course ID'),
            'criterion_type' => new external_value(PARAM_ALPHANUMEXT, 'Criterion type'),
            'modname' => new external_value(PARAM_ALPHA, 'Module name filter', VALUE_DEFAULT, ''),
        ]);
    }

    /**
     * Returns eligible activities for the given course and criterion type.
     *
     * @param int $courseid Course ID.
     * @param string $criteriontype Criterion type.
     * @param string $modname Optional module name filter.
     * @return array
     */
    public static function execute(int $courseid, string $criteriontype, string $modname = ''): array {
        $params = self::validate_parameters(self::execute_parameters(), [
            'courseid'       => $courseid,
            'criterion_type' => $criteriontype,
            'modname'        => $modname,
        ]);

        $context = \context_course::instance($params['courseid']);
        self::validate_context($context);
        require_login($params['courseid']);

        $activities = \local_automatic_badges\helper::get_eligible_activities(
            $params['courseid'],
            $params['criterion_type']
        );

        // Filter by modname if provided.
        if (!empty($params['modname'])) {
            $modinfo = get_fast_modinfo($params['courseid']);
            foreach ($activities as $cmid => $name) {
                $cm = $modinfo->get_cm($cmid);
                if ($cm->modname !== $params['modname']) {
                    unset($activities[$cmid]);
                }
            }
        }

        // Convert associative array to indexed array of {id, name} objects.
        $result = [];
        foreach ($activities as $id => $name) {
            $result[] = ['id' => (int)$id, 'name' => $name];
        }

        return $result;
    }

    /**
     * Defines the return structure of this external function.
     *
     * @return external_multiple_structure
     */
    public static function execute_returns(): external_multiple_structure {
        return new external_multiple_structure(
            new external_single_structure([
                'id' => new external_value(PARAM_INT, 'Activity course module ID'),
                'name' => new external_value(PARAM_TEXT, 'Activity name'),
            ])
        );
    }
}
