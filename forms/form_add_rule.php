<?php

defined('MOODLE_INTERNAL') || die();
require_once($CFG->libdir . '/formslib.php');

class local_automatic_badges_add_rule_form extends moodleform {
    /** @var array<int,string> Eligible activities cache */
    protected $eligibleactivities = [];

    public function definition() {
        global $CFG, $PAGE;

        require_once($CFG->dirroot . '/lib/badgeslib.php');

        $mform = $this->_form;
        $courseid = $this->_customdata['courseid'];
        $ruleid   = $this->_customdata['ruleid'] ?? 0;

        // --- Hidden fields ---
        $mform->addElement('hidden', 'courseid', $courseid);
        $mform->setType('courseid', PARAM_INT);
        $mform->addElement('hidden', 'ruleid', $ruleid);
        $mform->setType('ruleid', PARAM_INT);

        // =====================================================================
        // SECTION 1: Criterio de evaluación
        // =====================================================================
        $mform->addElement('header', 'criteriahdr',
            get_string('criteriontype', 'local_automatic_badges'));
        $mform->setExpanded('criteriahdr', true);

        $options = [
            'grade'      => get_string('criterion_grade', 'local_automatic_badges'),
            'forum'      => get_string('criterion_forum', 'local_automatic_badges'),
            'submission' => get_string('criterion_submission', 'local_automatic_badges'),
            'section'    => get_string('criterion_section', 'local_automatic_badges'),
        ];
        $criteriondefault = $this->_customdata['criterion_type'] ?? 'grade';
        $criterion = optional_param('criterion_type', $criteriondefault, PARAM_ALPHA);
        if (!array_key_exists($criterion, $options)) {
            $criterion = 'grade';
        }

        $mform->addElement('select', 'criterion_type',
            get_string('criteriontype', 'local_automatic_badges'), $options);
        $mform->addHelpButton('criterion_type', 'criteriontype', 'local_automatic_badges');
        $mform->setDefault('criterion_type', $criterion);
        $mform->addRule('criterion_type', null, 'required', null, 'client');

        // --- Calificación ---
        $operators = [
            '>=' => get_string('operator_gte', 'local_automatic_badges'),
            '>'  => get_string('operator_gt', 'local_automatic_badges'),
            '==' => get_string('operator_eq', 'local_automatic_badges'),
            'range' => get_string('operator_range', 'local_automatic_badges'),
        ];
        $mform->addElement('select', 'grade_operator',
            get_string('gradeoperator', 'local_automatic_badges'), $operators);
        $mform->addHelpButton('grade_operator', 'gradeoperator', 'local_automatic_badges');
        $mform->setType('grade_operator', PARAM_TEXT);
        $mform->setDefault('grade_operator', '>=');
        $mform->hideIf('grade_operator', 'criterion_type', 'neq', 'grade');

        $mform->addElement('text', 'grade_min',
            get_string('grademin', 'local_automatic_badges'));
        $mform->addHelpButton('grade_min', 'grademin', 'local_automatic_badges');
        $mform->setType('grade_min', PARAM_FLOAT);
        $mform->setDefault('grade_min', 60);
        $mform->hideIf('grade_min', 'criterion_type', 'neq', 'grade');

        $mform->addElement('text', 'grade_max',
            get_string('grademax', 'local_automatic_badges'));
        $mform->addHelpButton('grade_max', 'grademax', 'local_automatic_badges');
        $mform->setType('grade_max', PARAM_FLOAT);
        $mform->setDefault('grade_max', 100);
        $mform->hideIf('grade_max', 'criterion_type', 'neq', 'grade');
        $mform->hideIf('grade_max', 'grade_operator', 'neq', 'range');

        // --- Foro ---
        $forumcounttypes = [
            'all'     => get_string('forumcounttype_all', 'local_automatic_badges'),
            'replies' => get_string('forumcounttype_replies', 'local_automatic_badges'),
            'topics'  => get_string('forumcounttype_topics', 'local_automatic_badges'),
        ];
        $mform->addElement('select', 'forum_count_type',
            get_string('forumcounttype', 'local_automatic_badges'), $forumcounttypes);
        $mform->addHelpButton('forum_count_type', 'forumcounttype', 'local_automatic_badges');
        $mform->setType('forum_count_type', PARAM_ALPHA);
        $mform->setDefault('forum_count_type', 'all');
        $mform->hideIf('forum_count_type', 'criterion_type', 'neq', 'forum');

        $mform->addElement('text', 'forum_post_count',
            get_string('forumpostcount', 'local_automatic_badges'));
        $mform->addHelpButton('forum_post_count', 'forumpostcount', 'local_automatic_badges');
        $mform->setType('forum_post_count', PARAM_INT);
        $mform->setDefault('forum_post_count', 5);
        $mform->addRule('forum_post_count', null, 'numeric', null, 'client');
        $mform->hideIf('forum_post_count', 'criterion_type', 'neq', 'forum');

        // --- Entrega ---
        $mform->addElement('advcheckbox', 'require_submitted',
            get_string('requiresubmitted', 'local_automatic_badges'));
        $mform->setType('require_submitted', PARAM_INT);
        $mform->setDefault('require_submitted', 1);
        $mform->hideIf('require_submitted', 'criterion_type', 'neq', 'submission');

        $mform->addElement('advcheckbox', 'require_graded',
            get_string('requiregraded', 'local_automatic_badges'));
        $mform->setType('require_graded', PARAM_INT);
        $mform->setDefault('require_graded', 0);
        $mform->hideIf('require_graded', 'criterion_type', 'neq', 'submission');

        $submissiontypes = [
            'any'    => get_string('submissiontype_any', 'local_automatic_badges'),
            'ontime' => get_string('submissiontype_ontime', 'local_automatic_badges'),
            'early'  => get_string('submissiontype_early', 'local_automatic_badges'),
        ];
        $mform->addElement('select', 'submission_type',
            get_string('submissiontype', 'local_automatic_badges'), $submissiontypes);
        $mform->addHelpButton('submission_type', 'submissiontype', 'local_automatic_badges');
        $mform->setType('submission_type', PARAM_ALPHA);
        $mform->setDefault('submission_type', 'any');
        $mform->hideIf('submission_type', 'criterion_type', 'neq', 'submission');

        $mform->addElement('text', 'early_hours',
            get_string('earlyhours', 'local_automatic_badges'));
        $mform->addHelpButton('early_hours', 'earlyhours', 'local_automatic_badges');
        $mform->setType('early_hours', PARAM_INT);
        $mform->setDefault('early_hours', 24);
        $mform->hideIf('early_hours', 'criterion_type', 'neq', 'submission');
        $mform->hideIf('early_hours', 'submission_type', 'neq', 'early');

        // --- Sección (acumulativo) ---
        $mform->addElement('text', 'section_min_grade',
            get_string('section_min_grade', 'local_automatic_badges'));
        $mform->addHelpButton('section_min_grade', 'section_min_grade', 'local_automatic_badges');
        $mform->setType('section_min_grade', PARAM_FLOAT);
        $mform->setDefault('section_min_grade', 60);
        $mform->hideIf('section_min_grade', 'criterion_type', 'neq', 'section');

        // =====================================================================
        // SECTION 2: Actividad vinculada
        // =====================================================================
        $mform->addElement('header', 'activityhdr',
            get_string('activitylinked', 'local_automatic_badges'));
        $mform->setExpanded('activityhdr', true);

        $criteriaactivities = [
            'grade'      => \local_automatic_badges\helper::get_eligible_activities($courseid, 'grade'),
            'forum'      => \local_automatic_badges\helper::get_eligible_activities($courseid, 'forum'),
            'submission' => \local_automatic_badges\helper::get_eligible_activities($courseid, 'submission'),
            'section'    => \local_automatic_badges\helper::get_course_sections($courseid),
        ];
        $this->eligibleactivities = $criteriaactivities[$criterion] ?? [];

        $mform->addElement('html', '<div id="local_automatic_badges_activity_container">');
        $mform->addElement('select', 'activityid',
            get_string('activitylinked', 'local_automatic_badges'), $this->eligibleactivities);
        $mform->addHelpButton('activityid', 'activitylinked', 'local_automatic_badges');
        $mform->setType('activityid', PARAM_INT);
        $mform->addElement('html',
            '<div id="local_automatic_badges_activity_warning" class="alert alert-warning" style="display:none;">' .
            get_string('noeligibleactivities', 'local_automatic_badges') . '</div>');
        $mform->addElement('html', '</div>');

        // =====================================================================
        // SECTION 3: Insignia a otorgar
        // =====================================================================
        $mform->addElement('header', 'badgehdr',
            get_string('selectbadge', 'local_automatic_badges'));
        $mform->setExpanded('badgehdr', true);

        $badges = badges_get_badges(BADGE_TYPE_COURSE, $courseid);
        $badgeoptions = [];
        foreach ($badges as $badge) {
            $badgeoptions[$badge->id] = $badge->name;
        }

        if (empty($badgeoptions)) {
            $createbadgeurl = new moodle_url('/badges/newbadge.php', ['type' => BADGE_TYPE_COURSE, 'id' => $courseid]);
            $alerthtml = '
            <div class="alert alert-warning d-flex align-items-start" role="alert" style="margin: 1rem 0; padding: 1rem 1.25rem; border-left: 4px solid #ffc107;">
                <i class="fa fa-exclamation-triangle fa-2x mr-3" style="color: #856404;"></i>
                <div>
                    <h5 class="alert-heading mb-2" style="font-weight: 600; color: #856404;">' .
                        get_string('nobadgesavailable', 'local_automatic_badges') . '
                    </h5>
                    <p class="mb-2" style="color: #856404;">
                        ' . get_string('nobadges_createfirst', 'local_automatic_badges') . '
                    </p>
                    <a href="' . $createbadgeurl->out() . '" class="btn btn-warning btn-sm">
                        <i class="fa fa-plus mr-1"></i> ' . get_string('newbadge', 'badges') . '
                    </a>
                </div>
            </div>';
            $mform->addElement('html', $alerthtml);
        } else {
            $mform->addElement('select', 'badgeid',
                get_string('selectbadge', 'local_automatic_badges'), $badgeoptions);
            $mform->addHelpButton('badgeid', 'selectbadge', 'local_automatic_badges');
            $mform->addRule('badgeid', null, 'required', null, 'client');
        }

        // =====================================================================
        // SECTION 4: Opciones avanzadas
        // =====================================================================
        $mform->addElement('header', 'optionshdr',
            get_string('advancedoptions', 'local_automatic_badges'));

        $mform->addElement('advcheckbox', 'enabled',
            get_string('ruleenabledlabel', 'local_automatic_badges'));
        $mform->addHelpButton('enabled', 'ruleenabledlabel', 'local_automatic_badges');
        $mform->setType('enabled', PARAM_INT);
        $mform->setDefault('enabled', 1);

        $mform->addElement('advcheckbox', 'enable_bonus',
            get_string('enablebonus', 'local_automatic_badges'));
        $mform->addHelpButton('enable_bonus', 'enablebonus', 'local_automatic_badges');
        $mform->addElement('text', 'bonus_points',
            get_string('bonusvalue', 'local_automatic_badges'));
        $mform->addHelpButton('bonus_points', 'bonusvalue', 'local_automatic_badges');
        $mform->setType('bonus_points', PARAM_FLOAT);
        $mform->setDefault('bonus_points', 0);
        $mform->hideIf('bonus_points', 'enable_bonus', 'notchecked');

        $mform->addElement('textarea', 'notify_message',
            get_string('notifymessage', 'local_automatic_badges'),
            'wrap="virtual" rows="3" cols="50"');
        $mform->addHelpButton('notify_message', 'notifymessage', 'local_automatic_badges');
        $mform->setType('notify_message', PARAM_TEXT);

        $mform->addElement('advcheckbox', 'dry_run',
            get_string('dryrun', 'local_automatic_badges'));
        $mform->setType('dry_run', PARAM_INT);
        $mform->setDefault('dry_run', 0);

        // =====================================================================
        // SECTION 5: Vista previa de la regla
        // =====================================================================
        $mform->addElement('header', 'rulepreviewhdr',
            get_string('rulepreview', 'local_automatic_badges'));

        $mform->addElement('html', '
        <div id="local_automatic_badges_rule_preview" class="alert alert-info" style="border-left: 4px solid #0f6cbf;">
            <div id="local_automatic_badges_rule_preview_text" style="background: rgba(255,255,255,0.6); padding: 12px; border-radius: 4px; margin-top: 10px;"></div>
        </div>
        ');

        // =====================================================================
        // Botones de acción
        // =====================================================================
        $mform->addElement('submit', 'testrule',
            get_string('testrule', 'local_automatic_badges'));

        $this->add_action_buttons(true,
            get_string('saverule', 'local_automatic_badges'));

        // =====================================================================
        // JavaScript: actividades dinámicas + preview
        // =====================================================================
        $activityjson = json_encode($criteriaactivities, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);
        $noactivities = json_encode(get_string('noeligibleactivities', 'local_automatic_badges'), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);
        $PAGE->requires->js_init_code(<<<JS
require(['jquery'], function($) {
    $(function() {
        var activityMap = {$activityjson};
        var noActivitiesText = {$noactivities};
        var container = $('#local_automatic_badges_activity_container');
        var select = $('#id_activityid');
        var warning = $('#local_automatic_badges_activity_warning');

        function setOptions(criterion) {
            var activities = activityMap[criterion] || {};
            var current = select.val();
            select.empty();

            var hasOptions = false;
            $.each(activities, function(id, name) {
                hasOptions = true;
                select.append($('<option></option>').val(id).text(name));
            });

            if (current && Object.prototype.hasOwnProperty.call(activities, current)) {
                select.val(current);
            }

            if (!hasOptions) {
                warning.show();
                select.prop('disabled', true);
            } else {
                warning.hide();
                select.prop('disabled', false);
            }
        }

        var forumCountLabels = {
            'all': 'Publicaciones necesarias (temas o respuestas)',
            'replies': 'Respuestas necesarias',
            'topics': 'Temas necesarios'
        };

        function updateForumCountLabel() {
            var countType = $('#id_forum_count_type').val() || 'all';
            var label = forumCountLabels[countType] || forumCountLabels['all'];
            var labelEl = $('label[for="id_forum_post_count"]');
            if (labelEl.length) {
                labelEl.text(label);
            }
            $('#id_forum_post_count').closest('.form-group, .fitem').find('label').first().text(label);
        }

        function buildPreviewText() {
            var criterion = $('#id_criterion_type').val();
            var criterionLabel = $('#id_criterion_type option:selected').text();
            var enabled = $('#id_enabled').is(':checked');
            var activityVal = $('#id_activityid').val();
            var activityName = $('#id_activityid option:selected').text();
            var badgeName = $('#id_badgeid option:selected').text();
            var gradeMin = $('#id_grade_min').val();
            var gradeOperatorRaw = $('#id_grade_operator').val();
            var countType = $('#id_forum_count_type').val();
            var forumPosts = $('#id_forum_post_count').val() || '5';
            var enableBonus = $('#id_enable_bonus').is(':checked');
            var bonusPoints = $('#id_bonus_points').val();
            var dryRun = $('#id_dry_run').is(':checked');
            var reqSubmitted = $('#id_require_submitted').is(':checked');
            var reqGraded = $('#id_require_graded').is(':checked');
            var submissionType = $('#id_submission_type').val() || 'any';
            var earlyHours = $('#id_early_hours').val() || '24';
            var gradeMax = $('#id_grade_max').val();
            var notifyMessage = $('#id_notify_message').val();

            var parts = [];

            if (dryRun) {
                parts.push('<span class="badge badge-warning"><i class="fa fa-flask"></i> MODO PRUEBA</span>');
            } else if (!enabled) {
                parts.push('<span class="badge badge-secondary"><i class="fa fa-pause"></i> Deshabilitada</span>');
            } else {
                parts.push('<span class="badge badge-success"><i class="fa fa-check-circle"></i> Activa</span>');
            }

            parts.push('<div class="mt-2">');
            parts.push('<i class="fa fa-filter text-muted"></i> <strong>Criterio:</strong> ' + criterionLabel);

            if (activityVal && activityName) {
                parts.push('<br><i class="fa fa-link text-muted"></i> <strong>Actividad:</strong> ' + activityName);
            } else {
                parts.push('<br><i class="fa fa-link text-muted"></i> <strong>Actividad:</strong> <em class="text-danger">Sin seleccionar</em>');
            }
            parts.push('</div>');

            var conditionHtml = '';
            if (criterion === 'grade') {
                var op = gradeOperatorRaw || '>=';
                var min = gradeMin || '0';
                if (op === 'range' && gradeMax) {
                    conditionHtml = 'Calificación entre <strong class="text-primary">' + min + '%</strong> y <strong class="text-primary">' + gradeMax + '%</strong>';
                } else if (op === 'range') {
                    conditionHtml = 'Calificación entre <strong class="text-primary">' + min + '%</strong> y <strong class="text-primary">100%</strong>';
                } else {
                    conditionHtml = 'Calificación ' + op + ' <strong class="text-primary">' + min + '%</strong>';
                }
            } else if (criterion === 'forum') {
                var posts = forumPosts || '5';
                var typeLabel = countType === 'replies' ? 'respuesta(s)' : (countType === 'topics' ? 'tema(s) nuevo(s)' : 'publicación(es)');
                conditionHtml = 'Mínimo <strong class="text-primary">' + posts + '</strong> ' + typeLabel + ' en el foro';
            } else if (criterion === 'submission') {
                var conds = [];
                if (reqSubmitted) conds.push('entrega realizada');
                if (reqGraded) conds.push('calificación publicada');
                if (submissionType === 'ontime') {
                    conds.push('<strong class="text-success">a tiempo</strong>');
                } else if (submissionType === 'early') {
                    conds.push('<strong class="text-success">' + earlyHours + 'h antes del plazo</strong>');
                }
                conditionHtml = conds.length > 0 ? conds.join(' y ') : '<em class="text-warning">Sin requisitos extra</em>';
            }

            if (conditionHtml) {
                parts.push('<div class="mt-1"><i class="fa fa-tasks text-muted"></i> <strong>Condición:</strong> ' + conditionHtml + '</div>');
            }

            var rewardHtml = '<div class="mt-2 py-2 px-2 bg-white rounded border">';
            if (badgeName) {
                rewardHtml += '<i class="fa fa-trophy text-warning"></i> <strong>Insignia:</strong> ' + badgeName;
            } else {
                rewardHtml += '<i class="fa fa-trophy text-muted"></i> <strong>Insignia:</strong> <em class="text-danger">Sin seleccionar</em>';
            }
            if (enableBonus && bonusPoints && parseFloat(bonusPoints) > 0) {
                rewardHtml += '<br><i class="fa fa-gift text-success"></i> <strong>Bonificación:</strong> +' + bonusPoints + ' punto(s)';
            }
            rewardHtml += '</div>';
            parts.push(rewardHtml);

            if (notifyMessage && notifyMessage.trim() !== '') {
                var preview = notifyMessage.substring(0, 80);
                if (notifyMessage.length > 80) preview += '...';
                var safePreview = $('<div>').text(preview).html();
                parts.push('<div class="mt-2 text-muted small"><i class="fa fa-envelope"></i> <em>' + safePreview + '</em></div>');
            }

            if (dryRun) {
                parts.push('<div class="alert alert-warning mt-2 mb-0 py-1"><small><i class="fa fa-exclamation-triangle"></i> Esta regla no otorgará insignias realmente.</small></div>');
            }

            $('#local_automatic_badges_rule_preview_text').html(parts.join(''));
        }

        var inputs = [
            '#id_criterion_type', '#id_enabled', '#id_activityid', '#id_badgeid',
            '#id_grade_operator', '#id_enable_bonus', '#id_dry_run',
            '#id_require_submitted', '#id_require_graded', '#id_forum_count_type',
            '#id_submission_type'
        ].join(', ');
        var textInputs = '#id_grade_min, #id_grade_max, #id_forum_post_count, #id_bonus_points, #id_notify_message, #id_early_hours';

        $(document).on('change', inputs, function() {
            updateForumCountLabel();
            buildPreviewText();
        });
        $(document).on('keyup', textInputs, buildPreviewText);

        $(document).on('change', '#id_criterion_type', function() {
            setOptions($(this).val());
            updateForumCountLabel();
            buildPreviewText();
        });

        setOptions('{$criterion}');
        updateForumCountLabel();
        buildPreviewText();
    });
});
JS
        );
    }

    public function validation($data, $files) {
        $errors = parent::validation($data, $files);
        $courseid = isset($data['courseid']) ? (int)$data['courseid'] : 0;
        $criterion = $data['criterion_type'] ?? 'grade';
        $this->eligibleactivities = \local_automatic_badges\helper::get_eligible_activities($courseid, $criterion);
        $activityid = isset($data['activityid']) ? (int)$data['activityid'] : 0;

        if (empty($activityid)) {
            $errors['activityid'] = get_string('required');
        } else if (empty($this->eligibleactivities)) {
            $errors['activityid'] = get_string('noeligibleactivities', 'local_automatic_badges');
        } else if (!array_key_exists($activityid, $this->eligibleactivities)) {
            $errors['activityid'] = get_string('activitynoteligible', 'local_automatic_badges');
        }

        if ($criterion === 'forum') {
            $requiredposts = isset($data['forum_post_count']) ? (int)$data['forum_post_count'] : 0;
            if ($requiredposts <= 0) {
                $errors['forum_post_count'] = get_string('forumpostcounterror', 'local_automatic_badges');
            }
        }

        return $errors;
    }
}
