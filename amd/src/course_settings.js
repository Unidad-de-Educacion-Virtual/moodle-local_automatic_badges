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
 * AMD module for the course settings page (test-logic tab).
 *
 * Enhances the user selector with Moodle's form-autocomplete widget so that
 * a student can be found by typing a name and the form submits automatically
 * on selection.
 *
 * @module     local_automatic_badges/course_settings
 * @copyright  2026 Daniela Alexandra Patiño Dávila, Cristian Julian Lamus Lamus
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['core/form-autocomplete', 'jquery'], function(autocomplete, $) {
    'use strict';

    /**
     * Initialise the test-logic user selector autocomplete widget.
     *
     * @param {string} selectId The CSS selector of the <select> element to enhance.
     */
    var initUserSelect = function(selectId) {
        autocomplete.enhance(selectId, false, false, 'Escribe un nombre...');
        $(selectId).on('change', function() {
            var val = $(this).val();
            if (val !== '' && val !== '0') {
                $(this).closest('form').submit();
            }
        });
    };

    /**
     * Inject a "Back to Automatic Badges" button into the badge edit form header and footer.
     *
     * Called from the badge_edit_hook to add a navigation shortcut back to the plugin.
     *
     * @param {string} returnUrl Absolute URL of the badges tab in course settings.
     */
    var addBackButton = function(returnUrl) {
        var actionArea = $('#fitem_id_buttonar, .form-group .fsubmit, [data-groupname="buttonar"]');
        if (actionArea.length) {
            var backBtn = $('<a>')
                .attr('href', returnUrl)
                .addClass('btn btn-outline-secondary ml-2')
                .html('<i class="fa fa-arrow-left mr-1"></i> Volver a Automatic Badges');
            actionArea.find('.form-inline, .felement, .fdefaultcustom, div').last().append(backBtn);
        }

        var heading = $('#region-main h2, #region-main .h2').first();
        if (heading.length) {
            var topLink = $('<a>')
                .attr('href', returnUrl)
                .addClass('btn btn-sm btn-outline-secondary mb-3 d-inline-block')
                .html('<i class="fa fa-arrow-left mr-1"></i> Volver a Automatic Badges');
            heading.before(topLink);
        }
    };

    return {
        initUserSelect: initUserSelect,
        addBackButton: addBackButton,
    };
});
