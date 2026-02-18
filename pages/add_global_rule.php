<?php
// local/automatic_badges/pages/add_global_rule.php

require(__DIR__ . '/../../../config.php');
require_once($CFG->dirroot . '/lib/badgeslib.php');
require_once($CFG->dirroot . '/local/automatic_badges/forms/form_add_global_rule.php');

use local_automatic_badges\rule_manager;

// === Parámetros requeridos ===
$courseid = optional_param('id', 0, PARAM_INT);
if ($courseid == 0) {
    $courseid = required_param('courseid', PARAM_INT);
}

// === Contexto del curso y validaciones ===
$course  = get_course($courseid);
$context = context_course::instance($courseid);

require_login($course);
require_capability('moodle/badges:configurecriteria', $context);

// === Configuración de la página ===
$PAGE->set_url(new moodle_url('/local/automatic_badges/add_global_rule.php', ['id' => $courseid]));
$PAGE->set_context($context);
$PAGE->set_pagelayout('course');
$PAGE->set_title(get_string('addglobalrule', 'local_automatic_badges'));
$PAGE->set_heading(format_string($course->fullname));

// === Construcción del formulario ===
$mform = new local_automatic_badges_add_global_rule_form(null, [
    'courseid'       => $courseid,
    'criterion_type' => optional_param('criterion_type', 'grade', PARAM_ALPHA),
]);

// Redirección si se cancela
if ($mform->is_cancelled()) {
    redirect(new moodle_url('/local/automatic_badges/course_settings.php', ['id' => $courseid]));
}

// === Procesamiento del envío del formulario ===
if ($data = $mform->get_data()) {
    // Read selected activity IDs injected by JS as individual hidden inputs
    $selectedids = optional_param_array('selected_act', [], PARAM_INT);
    $data->selected_activities = array_values(array_filter($selectedids));

    // Marcar como regla global
    $data->is_global_rule = 1;

    list($newruleid, $message, $notificationtype, $shouldTest) = rule_manager::process_rule_submission(
        $data,
        $courseid,
        0, // Nueva regla
        false
    );

    redirect(
        new moodle_url('/local/automatic_badges/course_settings.php', ['id' => $courseid]),
        $message,
        2,
        $notificationtype
    );
}

// === Encabezado de la página ===
echo $OUTPUT->header();

// Banner informativo sobre reglas globales
echo html_writer::div(
    html_writer::tag('i', '', ['class' => 'fa fa-globe fa-2x mr-3', 'style' => 'color: #0f6cbf;']) .
    html_writer::div(
        html_writer::tag('h5', get_string('globalrule_info_title', 'local_automatic_badges'),
            ['class' => 'alert-heading mb-1', 'style' => 'font-weight: 600;']) .
        html_writer::tag('p', get_string('globalrule_info_body', 'local_automatic_badges'),
            ['class' => 'mb-0']),
        'flex-grow-1'
    ),
    'alert alert-info d-flex align-items-center mb-4'
);

echo $OUTPUT->heading(get_string('addglobalrule', 'local_automatic_badges'), 2);

// === Renderizado del formulario y cierre ===
$mform->display();

echo $OUTPUT->footer();
