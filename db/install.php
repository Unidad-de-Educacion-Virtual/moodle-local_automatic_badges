<?php
/**
 * install.php
 *
 * Este hook se ejecuta justo después de que Moodle procese install.xml
 * (creación de tablas). Aquí creamos automáticamente el campo personalizado
 * de curso “automaticbadges_enabled”.
 *
 * @package   local_automaticbadges
 */

defined('MOODLE_INTERNAL') || die();

function xmldb_local_automaticbadges_install() {
    global $CFG;

 // 1) Intentamos incluir la librería de Custom Fields solo si existe
    $customfieldlib = $CFG->dirroot . '/lib/customfieldlib.php';
    if (file_exists($customfieldlib)) {
        require_once($customfieldlib);
    } else {
        // Si no existe, simplemente saltamos la parte de Custom Field.
        // (Podrías loguear un aviso o lanzar excepción, según prefieras).
        debugging('No se encontró lib/customfieldlib.php. 
                  Quizá tu versión de Moodle ya no usa Custom Fields de esta forma.', DEBUG_DEVELOPER);
        return; 
        // Si devuelves, la función termina aquí y NO se crea el campo. 
        // Ajusta esto según tu necesidad: quizá prefieras lanzar error
        // throw new moodle_exception('customfieldlibmissing', 'local_automaticbadges');
    }

    // 2) Creamos el handler de campos de curso.
    $categoryhandler = \core_course\customfield\course_handler::create();

    // 3) Recuperamos la categoría estándar (la sección "General" donde van los campos básicos).
    $standardcategory = $categoryhandler->get_standard_category();
    if (!$standardcategory) {
        debugging('No se encontró la categoría estándar de campos de curso.', DEBUG_NORMAL);
        return;
    }

    // 4) Comprobamos si ya existe un campo con shortname = 'automaticbadges_enabled'.
    $fields = $standardcategory->get_fields(); // array de field_controller
    foreach ($fields as $fcontroller) {
        if ($fcontroller->get('shortname') === 'automaticbadges_enabled') {
            // Si ya existe, no hacemos nada más.
            return;
        }
    }

    // 5) Preparamos los datos para crear el nuevo campo
    $fielddata = new stdClass();
    $fielddata->shortname   = 'automaticbadges_enabled';
    $fielddata->name        = 'Habilitar Insignias Automáticas';
    $fielddata->datatype    = 'bool';   // “Checkbox” (booleano)
    $fielddata->description = 'Marca esta casilla para que las insignias se otorguen automáticamente en este curso.';
    $fielddata->required    = 0;        // No obligatorio
    // sortorder lo podemos dejar en 1 o el valor que prefieras
    $fielddata->sortorder   = 1;

    // La categoría estándar tiene un ID que obtenemos así:
    $categoryid = $standardcategory->get('id');

    // 6) Creamos el controlador del campo y lo guardamos
    $fieldcontroller = \core_customfield\field_controller::create(0, $fielddata, $categoryid);
    $fieldcontroller->create();
}
