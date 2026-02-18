<?php
// TEMPORAL DEBUG — eliminar después
require(__DIR__ . '/../../config.php');
require_login();
require_capability('moodle/site:config', context_system::instance());

$courseid = optional_param('id', 2, PARAM_INT);

echo '<pre>';

echo "=== REGLAS EN BD (courseid=$courseid) ===\n";
$rules = $DB->get_records('local_automatic_badges_rules', ['courseid' => $courseid]);
if (empty($rules)) {
    echo "NO HAY REGLAS para courseid=$courseid\n";
} else {
    foreach ($rules as $r) {
        echo "id={$r->id} activityid={$r->activityid} criterion={$r->criterion_type} badgeid={$r->badgeid} enabled={$r->enabled}\n";
    }
}

echo "\n=== TODAS LAS REGLAS EN BD ===\n";
$allrules = $DB->get_records('local_automatic_badges_rules');
foreach ($allrules as $r) {
    echo "id={$r->id} courseid={$r->courseid} activityid={$r->activityid} criterion={$r->criterion_type} badgeid={$r->badgeid} enabled={$r->enabled}\n";
}

echo "\n=== LIMPIANDO REGLAS HUÉRFANAS ===\n";
$allrules2 = $DB->get_records('local_automatic_badges_rules');
$deleted = 0;
foreach ($allrules2 as $r) {
    if (!$DB->record_exists('badge', ['id' => $r->badgeid])) {
        echo "Borrando regla id={$r->id} (badgeid={$r->badgeid} no existe en mdl_badge)\n";
        $DB->delete_records('local_automatic_badges_rules', ['id' => $r->id]);
        $deleted++;
    }
}
echo "Reglas huérfanas eliminadas: $deleted\n";

echo "\n=== INSIGNIAS CLONADAS (id IN 12,13,14,15) — RAW DB ===\n";
$cloned = $DB->get_records_select('badge', 'id IN (12,13,14,15)');
if (empty($cloned)) {
    echo "NO EXISTEN en mdl_badge\n";
} else {
    foreach ($cloned as $b) {
        echo "id={$b->id} name={$b->name} type={$b->type} courseid={$b->courseid} status={$b->status} usercreated={$b->usercreated}\n";
    }
}

echo "\n=== TODAS LAS INSIGNIAS EN mdl_badge ===\n";
$allbadges = $DB->get_records('badge', null, 'id ASC', 'id,name,type,courseid,status');
foreach ($allbadges as $b) {
    echo "id={$b->id} name={$b->name} type={$b->type} courseid={$b->courseid} status={$b->status}\n";
}

echo '</pre>';
