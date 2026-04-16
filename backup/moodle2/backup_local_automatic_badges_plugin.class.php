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
 * Backup plugin for local_automatic_badges.
 *
 * Hooks into the course backup at the course level and exports all plugin
 * tables keyed by courseid: course config, award rules, key/value settings,
 * award history log, and legacy criteria.
 *
 * @package    local_automatic_badges
 * @author     Daniela Alexandra Patiño Dávila
 * @author     Cristian Julian Lamus Lamus
 * @copyright  2026 Daniela Alexandra Patiño Dávila, Cristian Julian Lamus Lamus
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Backup plugin class for local_automatic_badges.
 *
 * Attaches to the course element in the backup XML tree and exports all
 * plugin-owned tables scoped to the course being backed up.
 */
class backup_local_automatic_badges_plugin extends backup_local_plugin {
    /**
     * Returns the plugin structure anchored at the course element.
     *
     * Called automatically by the backup engine via define_plugin_structure()
     * when building the course backup archive.
     */
    protected function define_course_plugin_structure() {

        // Root optigroup element — sits under the <course> element.
        $plugin = $this->get_plugin_element();
        $pluginwrapper = new backup_nested_element($this->get_recommended_name());
        $plugin->add_child($pluginwrapper);

        // 1. Course enable/disable configuration (0 or 1 row per course).
        $coursecfg = new backup_nested_element('automatic_badges_coursecfg', ['id'], [
            'enabled', 'timecreated', 'timemodified',
        ]);

        // 2. Award rules.
        $rules = new backup_nested_element('automatic_badges_rules');
        $rule  = new backup_nested_element('automatic_badges_rule', ['id'], [
            'badgeid',
            'criterion_type', 'enabled', 'is_global_rule',
            'activity_type', 'activityid',
            'grade_min', 'grade_max', 'grade_operator',
            'forum_post_count', 'forum_count_type',
            'enable_bonus', 'bonus_points', 'bonus_target_activityid',
            'notify_enabled', 'notify_message',
            'require_submitted', 'require_graded', 'dry_run',
            'submission_type', 'early_hours',
            'workshop_submission', 'workshop_assessments',
            'section_id', 'section_min_grade',
            'timecreated', 'timemodified',
        ]);

        // 3. Per-course key/value settings.
        $settings = new backup_nested_element('automatic_badges_settings');
        $setting  = new backup_nested_element('automatic_badges_setting', ['id'], [
            'setting_name', 'setting_value',
        ]);

        // 4. Award history log (user data).
        $logentries = new backup_nested_element('automatic_badges_log_entries');
        $logentry   = new backup_nested_element('automatic_badges_log_entry', ['id'], [
            'userid', 'badgeid', 'ruleid',
            'timeissued', 'bonus_applied', 'bonus_value',
        ]);

        // 5. Legacy criteria.
        $criteria  = new backup_nested_element('automatic_badges_criteria_entries');
        $criterion = new backup_nested_element('automatic_badges_criteria_entry', ['id'], [
            'badgeid', 'grademin', 'enabled', 'timecreated', 'timemodified',
        ]);

        // Build the XML tree.
        $pluginwrapper->add_child($coursecfg);
        $pluginwrapper->add_child($rules);
        $rules->add_child($rule);
        $pluginwrapper->add_child($settings);
        $settings->add_child($setting);
        $pluginwrapper->add_child($logentries);
        $logentries->add_child($logentry);
        $pluginwrapper->add_child($criteria);
        $criteria->add_child($criterion);

        // Set data sources (each table is scoped to the backed-up course).
        $coursecfg->set_source_table(
            'local_automatic_badges_coursecfg',
            ['courseid' => backup::VAR_COURSEID]
        );
        $rule->set_source_table(
            'local_automatic_badges_rules',
            ['courseid' => backup::VAR_COURSEID]
        );
        $setting->set_source_table(
            'local_automatic_badges_settings',
            ['courseid' => backup::VAR_COURSEID]
        );
        $criterion->set_source_table(
            'local_automatic_badges_criteria',
            ['courseid' => backup::VAR_COURSEID]
        );

        // Log entries are user data — only include when users are being backed up.
        if ($this->get_setting_value('users')) {
            $logentry->set_source_table(
                'local_automatic_badges_log',
                ['courseid' => backup::VAR_COURSEID]
            );
        }

        // Annotate cross-references so the backup engine registers them.
        // Rules reference badges, course modules, and course sections.
        $rule->annotate_ids('badge', 'badgeid');
        $rule->annotate_ids('course_module', 'activityid');
        $rule->annotate_ids('course_module', 'bonus_target_activityid');
        $rule->annotate_ids('course_section', 'section_id');

        // Log entries reference users and badges.
        $logentry->annotate_ids('user', 'userid');
        $logentry->annotate_ids('badge', 'badgeid');

        // Legacy criteria reference badges.
        $criterion->annotate_ids('badge', 'badgeid');
    }
}
