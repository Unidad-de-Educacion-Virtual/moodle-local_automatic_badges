<?php
// This file is part of Moodle - http://moodle.org/
// local/automatic_badges/classes/rule_manager.php
namespace local_automatic_badges;

defined('MOODLE_INTERNAL') || die();

/**
 * Manages rule creation, updates, and badge activation for the automatic badges plugin.
 * Centralizes logic previously duplicated between add_rule.php and edit_rule.php.
 */
class rule_manager {

    /**
     * Build a rule record from form data.
     *
     * @param object $data Form data from moodleform.
     * @param int $courseid Course ID.
     * @param int $ruleid Rule ID (0 for new rules).
     * @return object Rule record ready for database.
     */
    public static function build_rule_record(object $data, int $courseid, int $ruleid = 0): object {
        $criterion = $data->criterion_type ?? 'grade';
        $enablebonus = empty($data->enable_bonus) ? 0 : 1;
        $ruleenabled = empty($data->enabled) ? 0 : 1;
        $isglobalrule = empty($data->is_global_rule) ? 0 : 1;

        $record = new \stdClass();
        
        // Identificadores base
        $record->courseid = $courseid;
        if ($ruleid > 0) {
            $record->id = $ruleid;
        }
        
        // Campos principales
        $record->badgeid = isset($data->badgeid) ? (int)$data->badgeid : 0;
        $record->criterion_type = $criterion;
        $record->enabled = $ruleenabled;
        $record->is_global_rule = $isglobalrule;
        
        // Actividad
        $record->activity_type = ($isglobalrule && isset($data->activity_type)) ? $data->activity_type : null;
        $record->activityid = (!$isglobalrule && isset($data->activityid)) ? (int)$data->activityid : null;
        
        // Campos de calificación (solo para criterion = 'grade')
        $record->grade_min = ($criterion === 'grade' && isset($data->grade_min))
            ? (float)$data->grade_min
            : null;
        $record->grade_operator = ($criterion === 'grade' && isset($data->grade_operator))
            ? $data->grade_operator
            : '>=';
            
        // Campos de foro (solo para criterion = 'forum')
        $requiredposts = isset($data->forum_post_count) ? (int)$data->forum_post_count : 5;
        $record->forum_post_count = ($criterion === 'forum' && $requiredposts > 0)
            ? max(1, $requiredposts)
            : null;
        $record->forum_count_type = ($criterion === 'forum' && !empty($data->forum_count_type))
            ? $data->forum_count_type
            : 'all';
            
        // Campos de bonificación
        $record->enable_bonus = $enablebonus;
        $record->bonus_points = ($enablebonus && isset($data->bonus_points))
            ? (float)$data->bonus_points
            : null;
            
        // Mensaje de notificación
        $record->notify_message = isset($data->notify_message)
            ? trim((string)$data->notify_message)
            : null;
            
        // Campos de submission (solo para criterion = 'submission')
        $record->require_submitted = isset($data->require_submitted) ? (int)$data->require_submitted : 1;
        $record->require_graded = isset($data->require_graded) ? (int)$data->require_graded : 0;
        
        // Modo dry-run
        $record->dry_run = isset($data->dry_run) ? (int)$data->dry_run : 0;
        
        // Timestamps
        $record->timemodified = time();
        if ($ruleid === 0) {
            $record->timecreated = time();
        }
        
        return $record;
    }

    /**
     * Save a rule to the database (insert or update).
     *
     * @param object $record Rule record.
     * @return int The rule ID.
     */
    public static function save_rule(object $record): int {
        global $DB;
        
        if (!empty($record->id) && $record->id > 0) {
            $DB->update_record('local_automatic_badges_rules', $record);
            return (int)$record->id;
        } else {
            return (int)$DB->insert_record('local_automatic_badges_rules', $record);
        }
    }

    /**
     * Activate a badge if it's not already active.
     *
     * @param int $badgeid Badge ID.
     * @return bool True if the badge was activated, false if already active.
     */
    public static function activate_badge_if_needed(int $badgeid): bool {
        require_once(\core_component::get_component_directory('core_badges') . '/lib.php');
        
        $badge = new \core_badges\badge($badgeid);
        
        if (method_exists($badge, 'is_active') && !$badge->is_active()) {
            $badge->set_status(BADGE_STATUS_ACTIVE);
            return true;
        }
        
        return false;
    }

    /**
     * Get the notification message and type based on rule state.
     *
     * @param bool $ruleenabled Whether the rule is enabled.
     * @param bool $badgeactivated Whether the badge was just activated.
     * @param string $badgename The badge name.
     * @return array [message, notification_type]
     */
    public static function get_notification(bool $ruleenabled, bool $badgeactivated, string $badgename): array {
        if (!$ruleenabled) {
            return [
                get_string('ruledisabledsaved', 'local_automatic_badges'),
                \core\output\notification::NOTIFY_INFO
            ];
        }
        
        $notificationkey = $badgeactivated ? 'rulebadgeactivated' : 'rulebadgealreadyactive';
        return [
            get_string($notificationkey, 'local_automatic_badges', $badgename),
            \core\output\notification::NOTIFY_SUCCESS
        ];
    }

    /**
     * Process the form submission for adding or editing a rule.
     * 
     * @param object $data Form data.
     * @param int $courseid Course ID.
     * @param int $ruleid Rule ID (0 for new rules).
     * @param bool $istestrun Whether this is a test run request.
     * @return array [ruleid, message, notification_type, should_redirect_to_test]
     */
    public static function process_rule_submission(object $data, int $courseid, int $ruleid = 0, bool $istestrun = false): array {
        // Build and save the rule
        $record = self::build_rule_record($data, $courseid, $ruleid);
        $savedRuleId = self::save_rule($record);
        
        // Try to activate badge if rule is enabled
        $badgeactivated = false;
        if ($record->enabled && $record->badgeid > 0) {
            $badgeactivated = self::activate_badge_if_needed((int)$record->badgeid);
        }
        
        // Get badge name for notification
        $badge = new \core_badges\badge((int)$record->badgeid);
        $badgename = format_string($badge->name);
        
        // Get notification
        list($message, $notificationtype) = self::get_notification(
            (bool)$record->enabled,
            $badgeactivated,
            $badgename
        );
        
        return [
            $savedRuleId,
            $message,
            $notificationtype,
            $istestrun
        ];
    }

    /**
     * Prepare default values for the edit form from an existing rule.
     *
     * @param object $rule The rule record from database.
     * @param int $courseid Course ID.
     * @return object Defaults object for moodleform.
     */
    public static function get_form_defaults(object $rule, int $courseid): object {
        return (object)[
            'courseid' => $courseid,
            'ruleid' => $rule->id,
            'badgeid' => $rule->badgeid,
            'criterion_type' => $rule->criterion_type,
            'activityid' => $rule->activityid ?? 0,
            'grade_min' => $rule->grade_min,
            'grade_operator' => $rule->grade_operator ?? '>=',
            'enabled' => isset($rule->enabled) ? (int)$rule->enabled : 1,
            'forum_post_count' => $rule->forum_post_count ?? 5,
            'forum_count_type' => $rule->forum_count_type ?? 'all',
            'enable_bonus' => (int)!empty($rule->enable_bonus),
            'bonus_points' => $rule->bonus_points ?? '',
            'notify_message' => $rule->notify_message ?? '',
            'require_submitted' => $rule->require_submitted ?? 1,
            'require_graded' => $rule->require_graded ?? 0,
            'dry_run' => $rule->dry_run ?? 0,
        ];
    }
}
