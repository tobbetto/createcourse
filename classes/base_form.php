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
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * File containing the base import form.
 *
 * @package    local_createcourse
 * @copyright  2013 Frédéric Massart
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();
require_once($CFG->libdir.'/formslib.php');

/**
 * Base import form.
 *
 * @package    local_createcourse
 * @copyright  2013 Frédéric Massart
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class local_createcourse_base_form extends moodleform {

    /**
     * Empty definition.
     *
     * @return void
     */
    public function definition() {
    }

    /**
     * Adds the import settings part.
     *
     * @return void
     */
    public function add_import_options() {
        $mform = $this->_form;

        // Upload settings and file.
        $mform->addElement('header', 'importoptionshdr', get_string('importoptions', 'local_createcourse'));
        $mform->setExpanded('importoptionshdr', true);

        $choices = array(
            //local_createcourse_processor::MODE_CREATE_NEW => get_string('createnew', 'local_createcourse'),
            local_createcourse_processor::MODE_CREATE_ALL => get_string('createall', 'local_createcourse'),
            //local_createcourse_processor::MODE_CREATE_OR_UPDATE => get_string('createorupdate', 'local_createcourse'),
            local_createcourse_processor::MODE_UPDATE_ONLY => get_string('updateonly', 'local_createcourse')
        );
        $mform->addElement('select', 'options[mode]', get_string('mode', 'local_createcourse'), $choices);
        $mform->addHelpButton('options[mode]', 'mode', 'local_createcourse');

        $choices = array(
            local_createcourse_processor::UPDATE_NOTHING => get_string('nochanges', 'local_createcourse'),
            local_createcourse_processor::UPDATE_ALL_WITH_DATA_ONLY => get_string('updatewithdataonly', 'local_createcourse'),
            local_createcourse_processor::UPDATE_ALL_WITH_DATA_OR_DEFAUTLS =>
                get_string('updatewithdataordefaults', 'local_createcourse'),
            local_createcourse_processor::UPDATE_MISSING_WITH_DATA_OR_DEFAUTLS => get_string('updatemissing', 'local_createcourse')
        );
        $mform->addElement('select', 'options[updatemode]', get_string('updatemode', 'local_createcourse'), $choices);
        $mform->setDefault('options[updatemode]', local_createcourse_processor::UPDATE_NOTHING);
        $mform->hideIf('options[updatemode]', 'options[mode]', 'eq', local_createcourse_processor::MODE_CREATE_NEW);
        $mform->hideIf('options[updatemode]', 'options[mode]', 'eq', local_createcourse_processor::MODE_CREATE_ALL);
        $mform->addHelpButton('options[updatemode]', 'updatemode', 'local_createcourse');

        $mform->addElement('selectyesno', 'options[allowdeletes]', get_string('allowdeletes', 'local_createcourse'));
        $mform->setDefault('options[allowdeletes]', 0);
        $mform->hideIf('options[allowdeletes]', 'options[mode]', 'eq', local_createcourse_processor::MODE_CREATE_NEW);
        $mform->hideIf('options[allowdeletes]', 'options[mode]', 'eq', local_createcourse_processor::MODE_CREATE_ALL);
        $mform->addHelpButton('options[allowdeletes]', 'allowdeletes', 'local_createcourse');

        $mform->addElement('selectyesno', 'options[allowrenames]', get_string('allowrenames', 'local_createcourse'));
        $mform->setDefault('options[allowrenames]', 0);
        $mform->hideIf('options[allowrenames]', 'options[mode]', 'eq', local_createcourse_processor::MODE_CREATE_NEW);
        $mform->hideIf('options[allowrenames]', 'options[mode]', 'eq', local_createcourse_processor::MODE_CREATE_ALL);
        $mform->addHelpButton('options[allowrenames]', 'allowrenames', 'local_createcourse');

        $mform->addElement('selectyesno', 'options[allowresets]', get_string('allowresets', 'local_createcourse'));
        $mform->setDefault('options[allowresets]', 0);
        $mform->hideIf('options[allowresets]', 'options[mode]', 'eq', local_createcourse_processor::MODE_CREATE_NEW);
        $mform->hideIf('options[allowresets]', 'options[mode]', 'eq', local_createcourse_processor::MODE_CREATE_ALL);
        $mform->addHelpButton('options[allowresets]', 'allowresets', 'local_createcourse');
    }

}
