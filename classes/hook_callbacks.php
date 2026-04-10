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
 * Hook callbacks for local_automatic_badges.
 *
 * @package    local_automatic_badges
 * @author     Daniela Alexandra Patiño Dávila
 * @author     Cristian Julian Lamus Lamus
 * @copyright  2026 Daniela Alexandra Patiño Dávila, Cristian Julian Lamus Lamus
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_automatic_badges;

/**
 * Hook callbacks for local_automatic_badges.
 *
 * @package    local_automatic_badges
 * @copyright  2026 Daniela Alexandra Patiño Dávila, Cristian Julian Lamus Lamus
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class hook_callbacks {
    /**
     * Inject a "Return to Automatic Badges" button on Moodle badge pages.
     *
     * @param \core\hook\output\before_footer_html_generation $hook The hook object.
     */
    public static function before_footer(\core\hook\output\before_footer_html_generation $hook): void {
        global $PAGE;

        // Only act on badge pages within a course context.
        $pagepath = $PAGE->url->get_path();
        if (strpos($pagepath, '/badges/') === false) {
            return;
        }

        $courseid = $PAGE->url->get_param('courseid');
        if (!$courseid && $PAGE->context && $PAGE->context->contextlevel == CONTEXT_COURSE) {
            $courseid = $PAGE->context->instanceid;
        }
        if (!$courseid) {
            return;
        }

        $coursecontext = \context_course::instance($courseid, IGNORE_MISSING);
        if (!$coursecontext || !has_capability('moodle/badges:configurecriteria', $coursecontext)) {
            return;
        }

        $returnurl = new \moodle_url('/local/automatic_badges/course_settings.php', ['id' => $courseid, 'tab' => 'badges']);

        $PAGE->requires->js_call_amd(
            'local_automatic_badges/course_settings',
            'addBackButton',
            [$returnurl->out(false)]
        );
    }
}
