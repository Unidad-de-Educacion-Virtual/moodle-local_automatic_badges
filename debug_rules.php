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
 * This file is part of local_automatic_badges
 *
 * local_automatic_badges is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * local_automatic_badges is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with local_automatic_badges.  If not, see <http://www.gnu.org/licenses/>.
 *
 * @package    local_automatic_badges
 * @author     Daniela Alexandra Patiño Dávila
 * @author     Cristian Julian Lamus Lamus
 * @copyright  2026 Daniela Alexandra Patiño Dávila, Cristian Julian Lamus Lamus
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
// TEMPORAL DEBUG — eliminar después.
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
    echo "id={$r->id} courseid={$r->courseid} activityid={$r->activityid} ";
    echo "criterion={$r->criterion_type} badgeid={$r->badgeid} enabled={$r->enabled}\n";
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
        echo "id={$b->id} name={$b->name} type={$b->type} ";
        echo "courseid={$b->courseid} status={$b->status} usercreated={$b->usercreated}\n";
    }
}

echo "\n=== TODAS LAS INSIGNIAS EN mdl_badge ===\n";
$allbadges = $DB->get_records('badge', null, 'id ASC', 'id,name,type,courseid,status');
foreach ($allbadges as $b) {
    echo "id={$b->id} name={$b->name} type={$b->type} courseid={$b->courseid} status={$b->status}\n";
}

echo '</pre>';
