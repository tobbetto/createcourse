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
 * File containing the course class.
 *
 * @package    local_createcourse
 * @copyright  2013 Frédéric Massart
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();
require_once($CFG->dirroot . '/backup/util/includes/restore_includes.php');
require_once($CFG->dirroot . '/course/lib.php');
require_once $CFG->dirroot . '/enrol/manual/externallib.php';

/**
 * Course class.
 *
 * @package    local_createcourse
 * @copyright  2013 Frédéric Massart
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class local_createcourse_course {

    /** Outcome of the process: creating the course */
    const DO_CREATE = 1;

    /** Outcome of the process: updating the course */
    const DO_UPDATE = 2;

    /** Outcome of the process: deleting the course */
    const DO_DELETE = 3;

    /** @var array final import data. */
    protected $data = array();

    /** @var array default values. */
    protected $defaults = array();

    /** @var array enrolment data. */
    protected $enrolmentdata;

    /** @var array errors. */
    protected $errors = array();

    /** @var int the ID of the course that had been processed. */
    protected $id;

    /** @var array containing options passed from the processor. */
    protected $importoptions = array();

    /** @var int import mode. Matches local_createcourse_processor::MODE_* */
    protected $mode;

    /** @var array course import options. */
    protected $options = array();

    /** @var int constant value of self::DO_*, what to do with that course */
    protected $do;

    /** @var bool set to true once we have prepared the course */
    protected $prepared = false;

    /** @var bool set to true once we have started the process of the course */
    protected $processstarted = false;

    /** @var array course import data. */
    protected $rawdata = array();

    /** @var array restore directory. */
    protected $restoredata;

    /** @var array starttime of FE courses. */
    //protected $festarttime = [ '8' => '08:00', '9' => '09:00', '10' => '10:00', '11' => '11:00', '12' => '12:00', '13' => '13:00', '14' => '14:00', '15' => '15:00', '16' => '16:00', '17' => '17:00', '18' => '18:00'];
    protected $festarttime = [ '1' => '10:00', '2' => '12:00', '3' => '14:00', '4' => '09:00', '5' => '11:00', '6' => '13:00', '7' => '16:00', '8' => '17:00', '9' => '10:00', '10' => '12:00', '11' => '14:00', '12' => '09:00', '13' => '11:00', '14' => '13:00', '15' => '16:00', '16' => '17:00', '17' => '10:00', '18' => '12:00', '19' => '14:00'];

    /** @var array fefeatured. */
    protected $fefeatured;

    /** @var string course shortname. */
    protected $shortname;

    /** @var array errors. */
    protected $statuses = array();

    /** @var int update mode. Matches local_createcourse_processor::UPDATE_* */
    protected $updatemode;

    /** @var int unique id for studentlog */
    protected $cache_var;

    /** @var array fields allowed as course data. */
    static protected $validfields = array('fullname', 'shortname', 'applicative', 'category', 'visible', 'startdate', 'enddate',
        'summary', 'format', 'theme', 'lang', 'newsitems', 'showgrades', 'showreports', 'legacyfiles', 'maxbytes',
        'groupmode', 'groupmodeforce', 'enablecompletion', 'downloadcontent');



    /** @var array fields required on course creation. */
    static protected $mandatoryfields = array('fullname', 'category');

    /** @var array fields which are considered as options. */
    static protected $optionfields = array('delete' => false, 'rename' => null, 'backupfile' => null,
        'templatecourse' => null, 'reset' => false);

    /** @var array options determining what can or cannot be done at an import level. */
    static protected $importoptionsdefaults = array('canrename' => false, 'candelete' => false, 'canreset' => false,
        'reset' => false, 'restoredir' => null, 'shortnametemplate' => null);

    /**
     * Constructor
     *
     * @param int $mode import mode, constant matching local_createcourse_processor::MODE_*
     * @param int $updatemode update mode, constant matching local_createcourse_processor::UPDATE_*
     * @param array $rawdata raw course data.
     * @param array $defaults default course data.
     * @param array $importoptions import options.
     */
    public function __construct($mode, $updatemode, $rawdata, $defaults = array(), $importoptions = array(), $uniqueid) {


        $rawdata['startdate'] = \local_createcourse_helper::change_start_enddate_format($rawdata['startdate']);
        $rawdata['enddate'] = \local_createcourse_helper::change_start_enddate_format($rawdata['enddate']);

        if ($mode !== local_createcourse_processor::MODE_CREATE_NEW &&
                $mode !== local_createcourse_processor::MODE_CREATE_ALL &&
                $mode !== local_createcourse_processor::MODE_CREATE_OR_UPDATE &&
                $mode !== local_createcourse_processor::MODE_UPDATE_ONLY) {
            throw new coding_exception('Incorrect mode.');
        } else if ($updatemode !== local_createcourse_processor::UPDATE_NOTHING &&
                $updatemode !== local_createcourse_processor::UPDATE_ALL_WITH_DATA_ONLY &&
                $updatemode !== local_createcourse_processor::UPDATE_ALL_WITH_DATA_OR_DEFAUTLS &&
                $updatemode !== local_createcourse_processor::UPDATE_MISSING_WITH_DATA_OR_DEFAUTLS) {
            throw new coding_exception('Incorrect update mode.');
        }
        $this->cache_var = $uniqueid;
        $this->mode = $mode;

        // Tk: Queremos sacar el último shortname utilizado en este aplicativo
        if ($this->mode == 2 || $this->mode == 1) {
            global $DB;

            switch ($rawdata['mode']) {
            case 'EXPRES':
                $rawdata['format'] = 'topics';
                break;
            case 'WEBINAR';
                $rawdata['format'] = 'tiles';
                $rawdata['enablecompletion'] = 1;
                //$rawdata['templatecourse'] = 'plantillawebinartiles';
                break;
            case 'PRESENCIAL';
                $rawdata['format'] = 'tiles';
                $rawdata['enablecompletion'] = 1;
                //$rawdata['templatecourse'] = 'plantillapresencialtiles';
                break;
            case 'ONLINE SINCRONA';
                $rawdata['format'] = 'tiles';
                $rawdata['enablecompletion'] = 1;
                //$rawdata['templatecourse'] = 'planillaonlinesinctiles';
            }
        }

        $this->updatemode = $updatemode;

        if (isset($rawdata['shortname'])) {
            $this->shortname = $rawdata['shortname'];
        }
        $this->rawdata = $rawdata;
        $this->defaults = $defaults;

        // Extract course options.
        foreach (self::$optionfields as $option => $default) {
            $this->options[$option] = isset($rawdata[$option]) ? $rawdata[$option] : $default;
        }

        // Import options.
        foreach (self::$importoptionsdefaults as $option => $default) {
            $this->importoptions[$option] = isset($importoptions[$option]) ? $importoptions[$option] : $default;
        }
    }

    /**
     * Does the mode allow for course creation?
     *
     * @return bool
     */
    public function can_create() {
        return in_array($this->mode, array(local_createcourse_processor::MODE_CREATE_ALL,
            local_createcourse_processor::MODE_CREATE_NEW,
            local_createcourse_processor::MODE_CREATE_OR_UPDATE)
        );
    }

    /**
     * Does the mode allow for course deletion?
     *
     * @return bool
     */
    public function can_delete() {
        return $this->importoptions['candelete'];
    }

    /**
     * Does the mode only allow for course creation?
     *
     * @return bool
     */
    public function can_only_create() {
        return in_array($this->mode, array(local_createcourse_processor::MODE_CREATE_ALL,
            local_createcourse_processor::MODE_CREATE_NEW));
    }

    /**
     * Does the mode allow for course rename?
     *
     * @return bool
     */
    public function can_rename() {
        return $this->importoptions['canrename'];
    }

    /**
     * Does the mode allow for course reset?
     *
     * @return bool
     */
    public function can_reset() {
        return $this->importoptions['canreset'];
    }

    /**
     * Does the mode allow for course update?
     *
     * @return bool
     */
    public function can_update() {
        return in_array($this->mode,
                array(
                    local_createcourse_processor::MODE_UPDATE_ONLY,
                    local_createcourse_processor::MODE_CREATE_OR_UPDATE)
                ) && $this->updatemode != local_createcourse_processor::UPDATE_NOTHING;
    }

    /**
     * Can we use default values?
     *
     * @return bool
     */
    public function can_use_defaults() {
        return in_array($this->updatemode, array(local_createcourse_processor::UPDATE_MISSING_WITH_DATA_OR_DEFAUTLS,
            local_createcourse_processor::UPDATE_ALL_WITH_DATA_OR_DEFAUTLS));
    }

    /**
     * Delete the current course.
     *
     * @return bool
     */
    protected function delete() {
        global $DB;
        $this->id = $DB->get_field_select('course', 'id', 'shortname = :shortname',
            array('shortname' => $this->shortname), MUST_EXIST);
        return delete_course($this->id, false);
    }

    /**
     * Log an error
     *
     * @param string $code error code.
     * @param lang_string $message error message.
     * @return void
     */
    protected function error($code, lang_string $message) {
        if (array_key_exists($code, $this->errors)) {
            throw new coding_exception('Error code already defined');
        }
        $this->errors[$code] = $message;
    }

    /**
     * Return whether the course exists or not.
     *
     * @param string $shortname the shortname to use to check if the course exists. Falls back on $this->shortname if empty.
     * @return bool
     */
    protected function exists($shortname = null) {
        global $DB;
        if (is_null($shortname)) {
            $shortname = $this->shortname;
        }
        if (!empty($shortname) || is_numeric($shortname)) {
            return $DB->record_exists('course', array('shortname' => $shortname));
        }
        return false;
    }

    /**
     * Return the data that will be used upon saving.
     *
     * @return null|array
     */
    public function get_data() {
        return $this->data;
    }

    /**
     * Return the errors found during preparation.
     *
     * @return array
     */
    public function get_errors() {
        return $this->errors;
    }

    /**
     * Return array of valid fields for default values
     *
     * @return array
     */
    protected function get_valid_fields() {
        return array_merge(self::$validfields, \local_createcourse_helper::get_custom_course_field_names());
    }

    /**
     * Assemble the course data based on defaults.
     *
     * This returns the final data to be passed to create_course().
     *
     * @param array $data current data.
     * @return array
     */
    protected function get_final_create_data($data) {
        foreach ($this->get_valid_fields() as $field) {
            if (!isset($data[$field]) && isset($this->defaults[$field])) {
                $data[$field] = $this->defaults[$field];
            }
        }
        $data['shortname'] = $this->shortname;
        return $data;
    }

    /**
     * Assemble the course data based on defaults.
     *
     * This returns the final data to be passed to update_course().
     *
     * @param array $data current data.
     * @param bool $usedefaults are defaults allowed?
     * @param bool $missingonly ignore fields which are already set.
     * @return array
     */
    protected function get_final_update_data($data, $usedefaults = false, $missingonly = false) {
        global $DB;
        $newdata = array();
        $existingdata = $DB->get_record('course', array('shortname' => $this->shortname));
        foreach ($this->get_valid_fields() as $field) {
            if ($missingonly) {
                if (isset($existingdata->$field) and $existingdata->$field !== '') {
                    continue;
                }
            }
            if (isset($data[$field])) {
                $newdata[$field] = $data[$field];
            } else if ($usedefaults && isset($this->defaults[$field])) {
                $newdata[$field] = $this->defaults[$field];
            }
        }
        $newdata['id'] =  $existingdata->id;
        return $newdata;
    }

    /**
     * Return the ID of the processed course.
     *
     * @return int|null
     */
    public function get_id() {
        if (!$this->processstarted) {
            throw new coding_exception('The course has not been processed yet!');
        }
        return $this->id;
    }

    /**
     * Get the directory of the object to restore.
     *
     * @return string|false|null subdirectory in $CFG->backuptempdir/..., false when an error occured
     *                           and null when there is simply nothing.
     */
    protected function get_restore_content_dir() {

        //global $DB;
        //$sql = $DB->get_record_sql('SELECT MAX(id) as id FROM {course} WHERE '  . $DB->sql_like('shortname', ':shortname'), ['shortname' => '%EXPRES%']);

        //$lastlast = $DB->get_record('course', [ 'id' => $sql->id], 'shortname', IGNORE_MISSING);

        $backupfile = null;
        $shortname = null;

        if (!empty($this->options['backupfile'])) {
            $backupfile = $this->options['backupfile'];
        } else if (!empty($this->options['templatecourse']) || is_numeric($this->options['templatecourse'])) {
            $shortname = $this->options['templatecourse'];
        } else {
            if (strstr($this->data['shortname'], 'EXPRES')) {
                $shortname = 'plantillaexpres';
                //$shortname = $lastlast->shortname; // TK: Queremos asegurarnos que restauramos de un curso formación exprés sí o sí 'fexpres100';
            } elseif (strstr($this->data['shortname'], 'WEBINAR')) {
                $shortname = 'plantillawebinartiles';
            } elseif (strstr($this->data['shortname'], 'ONLINE SINC')) {
                $shortname = 'planillaonlinesinctiles';
            }else {
                $shortname = 'plantillapresencialtiles';
            }
        }

        $errors = array();
        $dir = local_createcourse_helper::get_restore_content_dir($backupfile, $shortname, $errors);
        if (!empty($errors)) {
            foreach ($errors as $key => $message) {
                $this->error($key, $message);
            }
            return false;
        } else if ($dir === false) {
            // We want to return null when nothing was wrong, but nothing was found.
            $dir = null;
        }

        if (empty($dir) && !empty($this->importoptions['restoredir'])) {
            $dir = $this->importoptions['restoredir'];
        }

        return $dir;
    }

    /**
     * Return the errors found during preparation.
     *
     * @return array
     */
    public function get_statuses() {
        return $this->statuses;
    }

    /**
     * Return whether there were errors with this course.
     *
     * @return boolean
     */
    public function has_errors() {
        return !empty($this->errors);
    }

    /**
     * Validates and prepares the data.
     *
     * @return bool false is any error occured.
     */
    public function prepare() {
        global $DB, $SITE, $CFG;

        $this->prepared = true;

        // Validate the shortname.
        if (!empty($this->shortname) || is_numeric($this->shortname)) {
            if ($this->shortname !== clean_param($this->shortname, PARAM_TEXT)) {
                $this->error('invalidshortname', new lang_string('invalidshortname', 'local_createcourse'));
                return false;
            }

            // Ensure we don't overflow the maximum length of the shortname field.
            if (core_text::strlen($this->shortname) > 255) {
                $this->error('invalidshortnametoolong', new lang_string('invalidshortnametoolong', 'local_createcourse', 255));
                return false;
            }
        }

        $exists = $this->exists();

        // Do we want to delete the course?
        if ($this->options['delete']) {
            if (!$exists) {
                $this->error('cannotdeletecoursenotexist', new lang_string('cannotdeletecoursenotexist', 'local_createcourse'));
                return false;
            } else if (!$this->can_delete()) {
                $this->error('coursedeletionnotallowed', new lang_string('coursedeletionnotallowed', 'local_createcourse'));
                return false;
            }

            $this->do = self::DO_DELETE;
            return true;
        }

        // Can we create/update the course under those conditions?
        if ($exists) {
            if ($this->mode === local_createcourse_processor::MODE_CREATE_NEW) {
                $this->error('courseexistsanduploadnotallowed',
                    new lang_string('courseexistsanduploadnotallowed', 'local_createcourse'));
                return false;
            } else if ($this->can_update()) {
                // We can never allow for any front page changes!
                if ($this->shortname == $SITE->shortname) {
                    $this->error('cannotupdatefrontpage', new lang_string('cannotupdatefrontpage', 'local_createcourse'));
                    return false;
                }
            }
        } else {
            if (!$this->can_create()) {
                $this->error('coursedoesnotexistandcreatenotallowed',
                    new lang_string('coursedoesnotexistandcreatenotallowed', 'local_createcourse'));
                return false;
            }
        }

        // Basic data.
        $coursedata = array();
        foreach ($this->rawdata as $field => $value) {
            if (!in_array($field, self::$validfields)) {
                continue;
            } else if ($field == 'shortname') {
                // Let's leave it apart from now, use $this->shortname only.
                continue;
            }
            $coursedata[$field] = $value;
        }

        $mode = $this->mode;
        $updatemode = $this->updatemode;
        $usedefaults = $this->can_use_defaults();

        // Resolve the category, and fail if not found.
        $errors = array();
        $catid = local_createcourse_helper::resolve_category($this->rawdata, $errors);
        if (empty($errors)) {
            $coursedata['category'] = $catid;
        } else {
            foreach ($errors as $key => $message) {
                $this->error($key, $message);
            }
            return false;
        }

        // Ensure we don't overflow the maximum length of the fullname field.
        if (!empty($coursedata['fullname']) && core_text::strlen($coursedata['fullname']) > 254) {
            $this->error('invalidfullnametoolong', new lang_string('invalidfullnametoolong', 'local_createcourse', 254));
            return false;
        }

        // If the course does not exist, or will be forced created.
        if (!$exists || $mode === local_createcourse_processor::MODE_CREATE_ALL) {

            // Mandatory fields upon creation.
            $errors = array();
            foreach (self::$mandatoryfields as $field) {
                if ((!isset($coursedata[$field]) || $coursedata[$field] === '') &&
                        (!isset($this->defaults[$field]) || $this->defaults[$field] === '')) {
                    $errors[] = $field;
                }
            }
            if (!empty($errors)) {
                $this->error('missingmandatoryfields', new lang_string('missingmandatoryfields', 'local_createcourse',
                    implode(', ', $errors)));
                return false;
            }
        }

        // Should the course be renamed?
        if (!empty($this->options['rename']) || is_numeric($this->options['rename'])) {
            if (!$this->can_update()) {
                $this->error('canonlyrenameinupdatemode', new lang_string('canonlyrenameinupdatemode', 'local_createcourse'));
                return false;
            } else if (!$exists) {
                $this->error('cannotrenamecoursenotexist', new lang_string('cannotrenamecoursenotexist', 'local_createcourse'));
                return false;
            } else if (!$this->can_rename()) {
                $this->error('courserenamingnotallowed', new lang_string('courserenamingnotallowed', 'local_createcourse'));
                return false;
            } else if ($this->options['rename'] !== clean_param($this->options['rename'], PARAM_TEXT)) {
                $this->error('invalidshortname', new lang_string('invalidshortname', 'local_createcourse'));
                return false;
            } else if ($this->exists($this->options['rename'])) {
                $this->error('cannotrenameshortnamealreadyinuse',
                    new lang_string('cannotrenameshortnamealreadyinuse', 'local_createcourse'));
                return false;
            } else if (isset($coursedata['idnumber']) &&
                    $DB->count_records_select('course', 'idnumber = :idn AND shortname != :sn',
                    array('idn' => $coursedata['idnumber'], 'sn' => $this->shortname)) > 0) {
                $this->error('cannotrenameidnumberconflict', new lang_string('cannotrenameidnumberconflict', 'local_createcourse'));
                return false;
            }
            $coursedata['shortname'] = $this->options['rename'];
            $this->status('courserenamed', new lang_string('courserenamed', 'local_createcourse',
                array('from' => $this->shortname, 'to' => $coursedata['shortname'])));
        }

        // Should we generate a shortname?
        if (empty($this->shortname) && !is_numeric($this->shortname)) {
            if (empty($this->importoptions['shortnametemplate'])) {
                $this->error('missingshortnamenotemplate', new lang_string('missingshortnamenotemplate', 'local_createcourse'));
                return false;
            } else if (!$this->can_only_create()) {
                $this->error('cannotgenerateshortnameupdatemode',
                    new lang_string('cannotgenerateshortnameupdatemode', 'local_createcourse'));
                return false;
            } else {
                $newshortname = local_createcourse_helper::generate_shortname($coursedata,
                    $this->importoptions['shortnametemplate']);
                if (is_null($newshortname)) {
                    $this->error('generatedshortnameinvalid', new lang_string('generatedshortnameinvalid', 'local_createcourse'));
                    return false;
                } else if ($this->exists($newshortname)) {
                    if ($mode === local_createcourse_processor::MODE_CREATE_NEW) {
                        $this->error('generatedshortnamealreadyinuse',
                            new lang_string('generatedshortnamealreadyinuse', 'local_createcourse')
                        );
                        return false;
                    }
                    $exists = true;
                }
                $this->status('courseshortnamegenerated', new lang_string('courseshortnamegenerated', 'local_createcourse',
                    $newshortname));
                $this->shortname = $newshortname;
            }
        }

        // If exists, but we only want to create courses, increment the shortname.
        if ($mode === local_createcourse_processor::MODE_CREATE_ALL) {
            $original = $this->shortname;

            // Set last 3 digits to 100
            $this->shortname = substr($this->shortname, 0, -3) . '100';

            // Asegurar que se usa el shortname siempre hasta el número
            // Utilizamos length para no incrementar shortnames que son más largos cuando el shortname a incrementar se más corto
            $prefix = substr($this->shortname, 0, -3);
            $lengthoriginal = mb_strlen($original, 'UTF-8');
            $lengthprefix = mb_strlen($prefix, 'UTF-8');

            $highestshortname = $DB->get_record_sql("
                SELECT MAX(shortname) AS lastusedshortname
                FROM {course}
                WHERE SUBSTR(shortname, 1, $lengthprefix) = :shortname
                AND LENGTH(shortname) = $lengthoriginal",
                ['shortname' => $prefix]
            );

            // Increment shortname if necessary
            if (substr($this->shortname, -3) <= substr($highestshortname->lastusedshortname, -3) && intval(substr($highestshortname->lastusedshortname, -3)) >= 100) {
                $this->shortname = local_createcourse_helper::increment_shortname($highestshortname->lastusedshortname);
                $coursedata['fullname'] = substr($coursedata['fullname'], 0, -3) . substr($this->shortname, -3);
            } else {
                $this->shortname = $original;
            }
            $exists = false;

            // Display messages if shortname or idnumber was incremented
            if ($this->shortname != $original) {
                $this->status('courseshortnameincremented', new lang_string('courseshortnameincremented', 'local_createcourse'));
                if (isset($coursedata['idnumber'])) {
                    $originalidn = $coursedata['idnumber'];
                    $coursedata['idnumber'] = local_createcourse_helper::increment_idnumber($coursedata['idnumber']);
                    if ($originalidn != $coursedata['idnumber']) {
                        $this->status('courseidnumberincremented', new lang_string('courseidnumberincremented', 'local_createcourse',
                        array('from' => $originalidn, 'to' => $coursedata['idnumber'])));
                    }
                }
            }
        }


        // If the course does not exist, ensure that the ID number is not taken.
        if (!$exists && isset($coursedata['idnumber'])) {
            if ($DB->count_records_select('course', 'idnumber = :idn', array('idn' => $coursedata['idnumber'])) > 0) {
                $this->error('idnumberalreadyinuse', new lang_string('idnumberalreadyinuse', 'local_createcourse'));
                return false;
            }
        }

        // Course start date.
        if (!empty($coursedata['startdate'])) {
            $coursedata['startdate'] = strtotime($coursedata['startdate']);
        }

        // Course end date.
        if (!empty($coursedata['enddate'])) {
            $coursedata['enddate'] = strtotime($coursedata['enddate']);
        }

        // If lang is specified, check the user is allowed to set that field.
        if (!empty($coursedata['lang'])) {
            if ($exists) {
                $courseid = $DB->get_field('course', 'id', ['shortname' => $this->shortname]);
                if (!has_capability('moodle/course:setforcedlanguage', context_course::instance($courseid))) {
                    $this->error('cannotforcelang', new lang_string('cannotforcelang', 'local_createcourse'));
                    return false;
                }
            } else {
                $catcontext = context_coursecat::instance($coursedata['category']);
                if (!guess_if_creator_will_have_course_capability('moodle/course:setforcedlanguage', $catcontext)) {
                    $this->error('cannotforcelang', new lang_string('cannotforcelang', 'local_createcourse'));
                    return false;
                }
            }
        }
        // TK: Queremos activar finalización para configurar las restricciones y asegurarnos que no hay noticia
        if (empty($coursedata['enablecompletion'])) {
            $coursedata['enablecompletion'] = 1;
            $coursedata['newsitems'] = 0;
        }

        // Ultimate check mode vs. existence.
        switch ($mode) {
            case local_createcourse_processor::MODE_CREATE_NEW:
            case local_createcourse_processor::MODE_CREATE_ALL:
                if ($exists) {
                    $this->error('courseexistsanduploadnotallowed',
                        new lang_string('courseexistsanduploadnotallowed', 'local_createcourse'));
                    return false;
                }
                break;
            case local_createcourse_processor::MODE_UPDATE_ONLY:
                if (!$exists) {
                    $this->error('coursedoesnotexistandcreatenotallowed',
                        new lang_string('coursedoesnotexistandcreatenotallowed', 'local_createcourse'));
                    return false;
                }
                // No break!
            case local_createcourse_processor::MODE_CREATE_OR_UPDATE:
                if ($exists) {
                    if ($updatemode === local_createcourse_processor::UPDATE_NOTHING) {
                        $this->error('updatemodedoessettonothing',
                            new lang_string('updatemodedoessettonothing', 'local_createcourse'));
                        return false;
                    }
                }
                break;
            default:
                // O_o Huh?! This should really never happen here!
                $this->error('unknownimportmode', new lang_string('unknownimportmode', 'local_createcourse'));
                return false;
        }

        // Get final data.
        if ($exists) {
            $missingonly = ($updatemode === local_createcourse_processor::UPDATE_MISSING_WITH_DATA_OR_DEFAUTLS);
            $coursedata = $this->get_final_update_data($coursedata, $usedefaults, $missingonly);

            // Make sure we are not trying to mess with the front page, though we should never get here!
            if ($coursedata['id'] == $SITE->id) {
                $this->error('cannotupdatefrontpage', new lang_string('cannotupdatefrontpage', 'local_createcourse'));
                return false;
            }

            $this->do = self::DO_UPDATE;
        } else {
            $coursedata['summaryformat'] = 1; // TK: Necesitamos que el formato de la descripción sea HTML
            $coursedata = $this->get_final_create_data($coursedata);
            $this->do = self::DO_CREATE;
        }

        // Validate course start and end dates.
        if ($exists) {
            // We also check existing start and end dates if we are updating an existing course.
            $existingdata = $DB->get_record('course', array('shortname' => $this->shortname));
            if (empty($coursedata['startdate'])) {
                $coursedata['startdate'] = $existingdata->startdate;
            }
            if (empty($coursedata['enddate'])) {
                $coursedata['enddate'] = $existingdata->enddate;
            }
        }
        if ($errorcode = course_validate_dates($coursedata)) {
            $this->error($errorcode, new lang_string($errorcode, 'error'));
            return false;
        }

        // Add role renaming.
        $errors = array();
        $rolenames = local_createcourse_helper::get_role_names($this->rawdata, $errors);
        if (!empty($errors)) {
            foreach ($errors as $key => $message) {
                $this->error($key, $message);
            }
            return false;
        }
        foreach ($rolenames as $rolekey => $rolename) {
            $coursedata[$rolekey] = $rolename;
        }

        // Custom fields. If the course already exists and mode isn't set to force creation, we can use its context.
        if ($exists && $mode !== local_createcourse_processor::MODE_CREATE_ALL) {
            $context = context_course::instance($coursedata['id']);
        } else {
            // The category ID is taken from the defaults if it exists, otherwise from course data.
            $context = context_coursecat::instance($this->defaults['category'] ?? $coursedata['category']);
        }
        $customfielddata = local_createcourse_helper::get_custom_course_field_data($this->rawdata, $this->defaults, $context,
            $errors);
        if (!empty($errors)) {
            foreach ($errors as $key => $message) {
                $this->error($key, $message);
            }

            return false;
        }

        foreach ($customfielddata as $name => $value) {
            $coursedata[$name] = $value;
        }

        // Some validation.
        if (!empty($coursedata['format']) && !in_array($coursedata['format'], local_createcourse_helper::get_course_formats())) {
            $this->error('invalidcourseformat', new lang_string('invalidcourseformat', 'local_createcourse'));
            return false;
        }

        // Add data for course format options.
        if (isset($coursedata['format']) || $exists) {
            if (isset($coursedata['format'])) {
                if (!strstr($coursedata['shortname'], 'EXPRES')) {
                    $coursedata['format'] = 'tiles';
                }
                $courseformat = course_get_format((object)['format' => $coursedata['format']]);
            } else {
                $courseformat = course_get_format($existingdata);
            }
            $coursedata += $courseformat->validate_course_format_options($this->rawdata);
        }

        // Special case, 'numsections' is not a course format option any more but still should apply from the template course,
        // if any, and otherwise from defaults.
        if (!$exists || !array_key_exists('numsections', $coursedata)) {
            if (isset($this->rawdata['numsections']) && is_numeric($this->rawdata['numsections'])) {
                $coursedata['numsections'] = (int)$this->rawdata['numsections'];
            } else if (isset($this->options['templatecourse'])) {
                $numsections = local_createcourse_helper::get_coursesection_count($this->options['templatecourse']);
                if ($numsections != 0) {
                    $coursedata['numsections'] = $numsections;
                } else {
                    $coursedata['numsections'] = get_config('moodlecourse', 'numsections');
                }
            } else {
                $coursedata['numsections'] = get_config('moodlecourse', 'numsections');
            }
        }

        // Visibility can only be 0 or 1.
        if (!empty($coursedata['visible']) AND !($coursedata['visible'] == 0 OR $coursedata['visible'] == 1)) {
            $this->error('invalidvisibilitymode', new lang_string('invalidvisibilitymode', 'local_createcourse'));
            return false;
        }

        // Ensure that user is allowed to configure course content download and the field contains a valid value.
        if (isset($coursedata['downloadcontent'])) {
            if (!$CFG->downloadcoursecontentallowed ||
                    !has_capability('moodle/course:configuredownloadcontent', $context)) {

                $this->error('downloadcontentnotallowed', new lang_string('downloadcontentnotallowed', 'local_createcourse'));
                return false;
            }

            $downloadcontentvalues = [
                DOWNLOAD_COURSE_CONTENT_DISABLED,
                DOWNLOAD_COURSE_CONTENT_ENABLED,
                DOWNLOAD_COURSE_CONTENT_SITE_DEFAULT,
            ];
            if (!in_array($coursedata['downloadcontent'], $downloadcontentvalues)) {
                $this->error('invaliddownloadcontent', new lang_string('invaliddownloadcontent', 'local_createcourse'));
                return false;
            }
        }

        // Saving data.
        $this->data = $coursedata;

        // Get enrolment data. Where the course already exists, we can also perform validation.
        $this->enrolmentdata = local_createcourse_helper::get_enrolment_data($this->rawdata);
        if ($exists) {
            $errors = $this->validate_enrolment_data($coursedata['id'], $this->enrolmentdata);

            if (!empty($errors)) {
                foreach ($errors as $key => $message) {
                    $this->error($key, $message);
                }

                return false;
            }
        }

        if (isset($this->rawdata['tags']) && strval($this->rawdata['tags']) !== '') {
            $this->data['tags'] = preg_split('/\s*,\s*/', trim($this->rawdata['tags']), -1, PREG_SPLIT_NO_EMPTY);
        }

        // Restore data.
        // TODO Speed up things by not really extracting the backup just yet, but checking that
        // the backup file or shortname passed are valid. Extraction should happen in proceed().
        $this->restoredata = $this->get_restore_content_dir();
        if ($this->restoredata === false) {
            return false;
        }

        // We can only reset courses when allowed and we are updating the course.
        if ($this->importoptions['reset'] || $this->options['reset']) {
            if ($this->do !== self::DO_UPDATE) {
                $this->error('canonlyresetcourseinupdatemode',
                    new lang_string('canonlyresetcourseinupdatemode', 'local_createcourse'));
                return false;
            } else if (!$this->can_reset()) {
                $this->error('courseresetnotallowed', new lang_string('courseresetnotallowed', 'local_createcourse'));
                return false;
            }
        }

        return true;
    }

    /**
     * Proceed with the import of the course.
     *
     * @return void
     */
    public function proceed() {
        global $CFG, $USER, $DB;

        if (!$this->prepared) {
            throw new coding_exception('The course has not been prepared.');
        } else if ($this->has_errors()) {
            throw new moodle_exception('Cannot proceed, errors were detected.');
        } else if ($this->processstarted) {
            throw new coding_exception('The process has already been started.');
        }
        $this->processstarted = true;

        if ($this->do === self::DO_DELETE) {
            if ($this->delete()) {
                $this->status('coursedeleted', new lang_string('coursedeleted', 'local_createcourse'));
            } else {
                $this->error('errorwhiledeletingcourse', new lang_string('errorwhiledeletingcourse', 'local_createcourse'));
            }
            return true;
        } else if ($this->do === self::DO_CREATE) {

            $course = create_course((object) $this->data);
            $this->id = $course->id;

            $this->status('coursecreated', new lang_string('coursecreated', 'local_createcourse'));
        } else if ($this->do === self::DO_UPDATE) {
            $course = (object) $this->data;
            $course->summaryformat = 1; // TK: No queremos que el update nos come el formato html de la descripción
            update_course($course);
            $this->id = $course->id;
            // TK: Actualizamos la información del course, ajustes, sectiones, labels, etc
            if (strstr($course->fullname, 'EXPRES')) {
                $this->update_expres_course_info($course);
                $this->get_teacher($course);
                $this->get_student($course);
                $this->update_expres_self_enrol($course);
                $this->update_expres_course_section($course);
                $this->update_expres_course_labels($course);
                $this->update_expres_course_lti($course);
                $this->update_expres_course_feedback($course);
                $this->delete_forum_rebuild_cache($course);
            } elseif (strstr($course->fullname, 'WEBINAR')) {
                $this->update_normal_course_info($course);
                $this->get_teacher($course);
                $this->get_student($course);
                $this->update_webinar_self_enrol($course);
                $this->update_normal_course_feedback($course);
                $this->update_course_section_certificate($course);
                if (strstr($course->fullname, 'Formadores CCAATT') || strstr($course->fullname, 'Formación de formadores')) {
                    $this->update_teacher_certificate_section($course);
                } else {
                    $this->update_normal_course_certificate($course);
                }
                $this->create_forum($course);
            } elseif (strstr($course->fullname, 'ONLINE SINC')) {
                $this->update_normal_course_info($course);
                $this->get_teacher($course);
                $this->get_student($course);
                $this->update_normal_course_feedback($course);
                $this->update_course_section_certificate($course);
                $this->update_normal_course_certificate($course);
                $this->create_forum($course);
            } else {
                $this->update_normal_course_info($course);
                $this->get_teacher($course);
                $this->get_student($course);
                $this->update_normal_course_feedback($course);
                $this->update_course_section_certificate($course);
                $this->update_normal_course_certificate($course);
                $this->rebuild_cache($course);
            }

            $this->status('courseupdated', new lang_string('courseupdated', 'local_createcourse'));
        } else {
            // Strangely the outcome has not been defined, or is unknown!
            throw new coding_exception('Unknown outcome!');
        }

        // Restore a course.
        if (!empty($this->restoredata)) {
            $rc = new restore_controller($this->restoredata, $course->id, backup::INTERACTIVE_NO,
                backup::MODE_IMPORT, $USER->id, backup::TARGET_CURRENT_ADDING);

            // Check if the format conversion must happen first.
            if ($rc->get_status() == backup::STATUS_REQUIRE_CONV) {
                $rc->convert();
            }
            if ($rc->execute_precheck()) {
                $rc->execute_plan();
                // TK: Aquí actualizamos el curso formación exprés
                if (strstr($course->fullname, 'EXPRES')) {
                    $this->update_expres_course_info($course);
                    $this->get_teacher($course);
                    $this->get_student($course);
                    $this->update_expres_self_enrol($course);
                    $this->update_expres_course_section($course);
                    $this->update_expres_course_labels($course);
                    $this->update_expres_course_lti($course);
                    $this->update_expres_course_feedback($course);
                    $this->delete_forum_rebuild_cache($course);
                } elseif (strstr($course->fullname, 'WEBINAR')) {
                    $this->update_normal_course_info($course);
                    $this->get_teacher($course);
                    $this->get_student($course);
                    $this->update_webinar_self_enrol($course);
                    $this->update_normal_course_feedback($course);
                    $this->update_course_section_certificate($course);
                    if (strstr($course->fullname, 'Formadores CCAATT') || strstr($course->fullname, 'Formación de formadores')) {
                        $this->update_teacher_certificate_section($course);
                    } else {
                        $this->update_normal_course_certificate($course);
                    }
                    $this->create_forum($course);
                } elseif (strstr($course->fullname, 'ONLINE SINC')) {
                    $this->update_normal_course_info($course);
                    $this->get_teacher($course);
                    $this->get_student($course);
                    $this->update_normal_course_feedback($course);
                    $this->update_course_section_certificate($course);
                    $this->update_normal_course_certificate($course);
                    $this->create_forum($course);
                } else {
                    $this->update_normal_course_info($course);
                    $this->get_teacher($course);
                    $this->get_student($course);
                    $this->update_normal_course_feedback($course);
                    $this->update_course_section_certificate($course);
                    $this->update_normal_course_certificate($course);
                    $this->rebuild_cache($course);
                }
                $this->status('courserestored', new lang_string('courserestored', 'local_createcourse'));
            } else {
                $this->error('errorwhilerestoringcourse', new lang_string('errorwhilerestoringthecourse', 'local_createcourse'));
            }
            $rc->destroy();
        }

        // Proceed with enrolment data.
        $this->process_enrolment_data($course);

        // Reset the course.
        if ($this->importoptions['reset'] || $this->options['reset']) {
            if ($this->do === self::DO_UPDATE && $this->can_reset()) {
                $this->reset($course);
                $this->status('coursereset', new lang_string('coursereset', 'local_createcourse'));
            }
        }

        // Mark context as dirty.
        $context = context_course::instance($course->id);
        $context->mark_dirty();
    }

    /**
     * Validate passed enrolment data against an existing course
     *
     * @param int $courseid
     * @param array[] $enrolmentdata
     * @return lang_string[] Errors keyed on error code
     */
    protected function validate_enrolment_data(int $courseid, array $enrolmentdata): array {
        // Nothing to validate.
        if (empty($enrolmentdata)) {
            return [];
        }

        $errors = [];

        $enrolmentplugins = local_createcourse_helper::get_enrolment_plugins();
        $instances = enrol_get_instances($courseid, false);

        foreach ($enrolmentdata as $method => $options) {
            $plugin = $enrolmentplugins[$method];

            // Find matching instances by enrolment method.
            $methodinstances = array_filter($instances, static function(stdClass $instance) use ($method) {
                return (strcmp($instance->enrol, $method) == 0);
            });

            if (!empty($options['delete'])) {
                // Ensure user is able to delete the instances.
                foreach ($methodinstances as $methodinstance) {
                    if (!$plugin->can_delete_instance($methodinstance)) {
                        $errors['errorcannotdeleteenrolment'] = new lang_string('errorcannotdeleteenrolment', 'local_createcourse',
                            $plugin->get_instance_name($methodinstance));

                        break;
                    }
                }
            } else if (!empty($options['disable'])) {
                // Ensure user is able to toggle instance statuses.
                foreach ($methodinstances as $methodinstance) {
                    if (!$plugin->can_hide_show_instance($methodinstance)) {
                        $errors['errorcannotdisableenrolment'] =
                            new lang_string('errorcannotdisableenrolment', 'local_createcourse',
                                $plugin->get_instance_name($methodinstance));

                        break;
                    }
                }
            } else {
                // Ensure user is able to create/update instance.
                $methodinstance = empty($methodinstances) ? null : reset($methodinstances);
                if ((empty($methodinstance) && !$plugin->can_add_instance($courseid)) ||
                        (!empty($methodinstance) && !$plugin->can_edit_instance($methodinstance))) {

                    $errors['errorcannotcreateorupdate_self_enrolment'] =
                        new lang_string('errorcannotcreateorupdate_self_enrolment', 'local_createcourse',
                            $plugin->get_instance_name($methodinstance));

                    break;
                }
            }
        }

        return $errors;
    }

    /**
     * Add the enrolment data for the course.
     *
     * @param object $course course record.
     * @return void
     */
    protected function process_enrolment_data($course) {
        global $DB;

        $enrolmentdata = $this->enrolmentdata;
        if (empty($enrolmentdata)) {
            return;
        }

        $enrolmentplugins = local_createcourse_helper::get_enrolment_plugins();
        $instances = enrol_get_instances($course->id, false);
        foreach ($enrolmentdata as $enrolmethod => $method) {

            $instance = null;
            foreach ($instances as $i) {
                if ($i->enrol == $enrolmethod) {
                    $instance = $i;
                    break;
                }
            }

            $todelete = isset($method['delete']) && $method['delete'];
            $todisable = isset($method['disable']) && $method['disable'];
            unset($method['delete']);
            unset($method['disable']);

            if ($todelete) {
                // Remove the enrolment method.
                if ($instance) {
                    $plugin = $enrolmentplugins[$instance->enrol];

                    // Ensure user is able to delete the instance.
                    if ($plugin->can_delete_instance($instance)) {
                        $plugin->delete_instance($instance);
                    } else {
                        $this->error('errorcannotdeleteenrolment',
                            new lang_string('errorcannotdeleteenrolment', 'local_createcourse',
                                $plugin->get_instance_name($instance)));
                    }
                }
            } else {
                // Create/update enrolment.
                $plugin = $enrolmentplugins[$enrolmethod];

                $status = ($todisable) ? ENROL_INSTANCE_DISABLED : ENROL_INSTANCE_ENABLED;

                // Create a new instance if necessary.
                if (empty($instance) && $plugin->can_add_instance($course->id)) {
                    $instanceid = $plugin->add_default_instance($course);
                    $instance = $DB->get_record('enrol', ['id' => $instanceid]);
                    $instance->roleid = $plugin->get_config('roleid');
                    // On creation the user can decide the status.
                    $plugin->update_status($instance, $status);
                }

                // Check if the we need to update the instance status.
                if ($instance && $status != $instance->status) {
                    if ($plugin->can_hide_show_instance($instance)) {
                        $plugin->update_status($instance, $status);
                    } else {
                        $this->error('errorcannotdisableenrolment',
                            new lang_string('errorcannotdisableenrolment', 'local_createcourse',
                                $plugin->get_instance_name($instance)));
                        break;
                    }
                }

                if (empty($instance) || !$plugin->can_edit_instance($instance)) {
                    $this->error('errorcannotcreateorupdate_self_enrolment',
                        new lang_string('errorcannotcreateorupdate_self_enrolment', 'local_createcourse',
                            $plugin->get_instance_name($instance)));

                    break;
                }

                // Now update values.
                foreach ($method as $k => $v) {
                    $instance->{$k} = $v;
                }

                // Sort out the start, end and date.
                $instance->enrolstartdate = (isset($method['startdate']) ? strtotime($method['startdate']) : 0);
                $instance->enrolenddate = (isset($method['enddate']) ? strtotime($method['enddate']) : 0);

                // Is the enrolment period set?
                if (isset($method['enrolperiod']) && ! empty($method['enrolperiod'])) {
                    if (preg_match('/^\d+$/', $method['enrolperiod'])) {
                        $method['enrolperiod'] = (int) $method['enrolperiod'];
                    } else {
                        // Try and convert period to seconds.
                        $method['enrolperiod'] = strtotime('1970-01-01 GMT + ' . $method['enrolperiod']);
                    }
                    $instance->enrolperiod = $method['enrolperiod'];
                }
                if ($instance->enrolstartdate > 0 && isset($method['enrolperiod'])) {
                    $instance->enrolenddate = $instance->enrolstartdate + $method['enrolperiod'];
                }
                if ($instance->enrolenddate > 0) {
                    $instance->enrolperiod = $instance->enrolenddate - $instance->enrolstartdate;
                }
                if ($instance->enrolenddate < $instance->enrolstartdate) {
                    $instance->enrolenddate = $instance->enrolstartdate;
                }

                // Sort out the given role. This does not filter the roles allowed in the course.
                if (isset($method['role'])) {
                    $roleids = local_createcourse_helper::get_role_ids();
                    if (isset($roleids[$method['role']])) {
                        $instance->roleid = $roleids[$method['role']];
                    }
                }

                $instance->timemodified = time();
                $DB->update_record('enrol', $instance);
            }
        }
    }

    /**
     * Reset the current course.
     *
     * This does not reset any of the content of the activities.
     *
     * @param stdClass $course the course object of the course to reset.
     * @return array status array of array component, item, error.
     */
    protected function reset($course) {
        global $DB;

        $resetdata = new stdClass();
        $resetdata->id = $course->id;
        $resetdata->reset_start_date = time();
        $resetdata->reset_events = true;
        $resetdata->reset_notes = true;
        $resetdata->delete_blog_associations = true;
        $resetdata->reset_completion = true;
        $resetdata->reset_roles_overrides = true;
        $resetdata->reset_roles_local = true;
        $resetdata->reset_groups_members = true;
        $resetdata->reset_groups_remove = true;
        $resetdata->reset_groupings_members = true;
        $resetdata->reset_groupings_remove = true;
        $resetdata->reset_gradebook_items = true;
        $resetdata->reset_gradebook_grades = true;
        $resetdata->reset_comments = true;

        if (empty($course->startdate)) {
            $course->startdate = $DB->get_field_select('course', 'startdate', 'id = :id', array('id' => $course->id));
        }
        $resetdata->reset_start_date_old = $course->startdate;

        if (empty($course->enddate)) {
            $course->enddate = $DB->get_field_select('course', 'enddate', 'id = :id', array('id' => $course->id));
        }
        $resetdata->reset_end_date_old = $course->enddate;

        // Add roles.
        $roles = local_createcourse_helper::get_role_ids();
        $resetdata->unenrol_users = array_values($roles);
        $resetdata->unenrol_users[] = 0;    // Enrolled without role.

        return reset_course_userdata($resetdata);
    }

    /**
     * Log a status
     *
     * @param string $code status code.
     * @param lang_string $message status message.
     * @return void
     */
    protected function status($code, lang_string $message) {
        if (array_key_exists($code, $this->statuses)) {
            throw new coding_exception('Status code already defined');
        }
        $this->statuses[$code] = $message;
    }

    /**
     * Summary of update_expres_course_info
     * @return void
     */
    private function update_expres_course_info($course){
        global $DB;
        $enroldata = $DB->get_record('enrol', [ 'enrol' => 'self' , 'courseid' => $course->id]);
        $fesummary = $DB->get_record('fe_description', [ 'description' => trim($this->rawdata['description'])]);
        $feaudience = $DB->get_record('fe_target', [ 'rol' => trim($this->rawdata['role'])]);
        $featured = '';
        if (strtolower($this->rawdata['featured']) == 'true') {
            $featured = 'DESTACADO';
        } else {
            $featured = '';
        }

        $summarytext =  '<p>' . $this->rawdata['description'] . '

        <p>' . $fesummary->summarytext . '

        <p> ' . $this->rawdata['applicative'] . '

        <p> ' . $this->festarttime[$this->rawdata['hour']] . '

        <p> ' . $fesummary->summaryhours . '

        <p> ' . $feaudience->target_audience . '

        <p> ' . $featured;

        try{
            $updatecourse = new stdClass();
            $updatecourse->id = $course->id;
            $updatecourse->summary = $summarytext;
            $updatecourse->summaryformat = FORMAT_HTML;
            $updatecourse->startdate = strtotime($this->rawdata['startdate']);
            $updatecourse->enddate = strtotime($this->rawdata['enddate'] . ' 23:59:00');
            $updatecourse->newsitems = 0;
            $updatecourse->customfield_nu_cau =  $this->rawdata['incident'];
            //$DB->update_record('course', $updatecourse);
            update_course($updatecourse);


            // TK: Habilitar la automatriculación
            $updateenrol = new stdClass();
            $updateenrol->id = $enroldata->id;
            $updateenrol->status = 0;
            $DB->update_record('enrol', $updateenrol);
        } catch (Exception $ex) {
            throw $ex;
        }
    }

    /**
     * Summary of update_normal_course_info
     * @return void
     */
    private function update_normal_course_info($course){
        global $DB;

        $enroldata = $DB->get_record('enrol', [ 'enrol' => 'self' , 'courseid' => $course->id]);

        $summarytext = '<p>Capacitación en ' . $this->rawdata['applicative'] . ' en formato ' . $this->rawdata['mode'];

        try{
            // TK: Actualizamos la información y configuración del curso
            $updatecourse = new stdClass();
            $updatecourse->id = $course->id;
            $updatecourse->summary = $summarytext;
            $updatecourse->summaryformat = FORMAT_HTML;
            $updatecourse->newsitems = 0;
            $updatecourse->customfield_nu_cau =  $this->rawdata['incident'];
            $updatecourse->startdate = strtotime($this->rawdata['startdate']);
            $updatecourse->enddate = strtotime($this->rawdata['enddate'] . ' 23:59:00');
            update_course($updatecourse);

            // TK: Deshabilitar la automatriculación
            $cleannumber = trim($this->rawdata['enrol_id_customint3']);
            $updateenrol = new stdClass();
            $updateenrol->id = $enroldata->id;
            if (strstr($course->fullname, 'WEBINAR')) {
                $updateenrol->status = 0;
                $updateenrol->customtext1 = get_string('mensajeautomatriculacionwebinar', 'local_createcourse') ;
                if (empty($cleannumber)) {
                    $updateenrol->customint3 = '0';
                } else {
                    $updateenrol->customint3 = $cleannumber;
                }
            } else {
                $updateenrol->status = 1;
            }
            $DB->update_record('enrol', $updateenrol);
        } catch (Exception $ex) {
            throw $ex;
        }
    }

    /**
     * Hay que matricular al profesor
     *
     * @param object $DB     database object
     * @param object $course course object
     *
     * @return void
     */
    function get_teacher($course){
        global $DB;
        /*
        * Sacar el prof desde la BBDD con nombre del excel
        */
        //$sql = "SELECT * FROM {user} WHERE CONCAT(CONCAT(UPPER(firstname), ' '), UPPER(lastname)) = :teachers";
        $sql = "SELECT * FROM {user} WHERE UPPER(username) = :teachersusername";
                $params = array('teachersusername' => trim(strtoupper($this->rawdata['teachersusername'])));

                $teacher = $DB->get_record_sql($sql, $params);

        try{
            $enrolmentparams = array('enrolments' => array(
                'roleid' => 3, // role to enrol in
                'userid' => $teacher->id, // user to enrol
                'courseid' => $course->id, // course to enrol in
                'timestart' => time(), // Enrol starttime
                'timeend' => 0,
                'suspend' => 0
            ));
            \local_createcourse_helper::enrol_users($enrolmentparams);

        } catch (Exception $ex) {
            throw $ex;
        }
    }

        /**
     * Hay que matricular al estudiante
     *
     * @param object $DB     database object
     * @param object $course course object
     *
     * @return void
     */
    function get_student($course){
        global $DB;

        if (!empty($this->rawdata['idnumber']) || !empty($this->rawdata['email'])) {

            $dniarray = explode( ',', $this->rawdata['idnumber']);
            $emailarray = explode( ',', $this->rawdata['email']);

            $enrolstudent = new MultipleIterator();
            $enrolstudent->attachIterator(new ArrayIterator($dniarray));
            $enrolstudent->attachIterator(new ArrayIterator($emailarray));

            $newArray = array();
            foreach ( $enrolstudent as [$studentdni, $studentemail] ) {

                $studentdni2 = $DB->get_record_sql(
                    'SELECT * FROM {user} WHERE idnumber = ? AND deleted = ? AND idnumber != ?',
                    [
                        trim(strtoupper($studentdni)),
                        0,
                        '',
                    ]
                );

                $studentemail2 = $DB->get_record( 'user', ['email' => trim(strtolower($studentemail)), 'deleted' => 0]);

                if (!empty($studentdni2)) {
                    try{
                        $enrolmentparams = array('enrolments' => array(
                            'roleid' => 5, // role to enrol in
                            'userid' => $studentdni2->id, // user to enrol
                            'courseid' => $course->id, // course to enrol in
                            'timestart' => time(), // Enrol starttime
                            'timeend' => 0,
                            'suspend' => 0
                        ));
                        \local_createcourse_helper::enrol_users($enrolmentparams);

                    } catch (Exception $ex) {
                        throw $ex;
                    }
                } elseif (!empty($studentemail2)) {
                    try{
                        $enrolmentparams = array('enrolments' => array(
                            'roleid' => 5, // role to enrol in
                            'userid' => $studentemail2->id, // user to enrol
                            'courseid' => $course->id, // course to enrol in
                            'timestart' => time(), // Enrol starttime
                            'timeend' => 0,
                            'suspend' => 0
                        ));
                        \local_createcourse_helper::enrol_users($enrolmentparams);

                    } catch (Exception $ex) {
                        throw $ex;
                    }
                }
                else {

                    $studentlog = 'log/studentlog_' . $this->cache_var . '_' . date('Ymd-G') . '.txt'; // include unique id in filename

                    $line =  '👩‍⚖️ Usuario/a con dni 👨‍⚖️ <b>' . $studentdni . '</b> y/o email 📧 <b>' . $studentemail . '</b> no encontrado en curso <b>' . $course->fullname . '</b> <br />'.PHP_EOL;

                    file_put_contents($studentlog, $line, FILE_APPEND);

                    // cache unique id as string variable
                }

            }

        }
    }

    /**
     * Hay que actulizar matriculación manual
     *
     * @param object $DB     database object
     * @param object $course course object
     *
     * @return void
     */
    function update_expres_self_enrol($course){
        global $DB;

        $enrol = $DB->get_record(
            'enrol', array('courseid' => $course->id,
            'enrol' => 'self')
        );
        if ($DB->record_exists(
            'enrol', array('courseid' => $course->id,
            'enrol' => 'self')
        )

        ) {
            try{
                $update_self_enrol = new stdClass();
                $update_self_enrol->id = $enrol->id;
                $cleannumber = trim($this->rawdata['enrol_id_customint3']);
                // poner restricción a matricularse antes que empiece el curso
                $update_self_enrol->enrolenddate = strtotime($this->rawdata['enddate'] . $this->festarttime[$this->rawdata['hour']] . ':00') - 7200;
                // Número de alumnos permitidos en el curso (por defecto 7)
                if (empty($cleannumber)) {
                    $update_self_enrol->customint3 = '7';
                } else {
                    $update_self_enrol->customint3 = $cleannumber;
                }
                // el mensaje a enviar a los alumnos cuando se matriculan
                $update_self_enrol->customtext1 = get_string('mensajeautomatriculacion', 'local_createcourse') ;
                $DB->update_record('enrol', $update_self_enrol);
            } catch (Exception $ex) {
                throw $ex;
            }
        } else {
            echo 'El método de matriculación no existe';
        }

    }

    /**
     * Undocumented function
     *
     * @param [type] $course
     * @return void
     */
    function update_webinar_self_enrol($course) {
        global $DB;
        $enrol = $DB->get_record(
            'enrol', array('courseid' => $course->id,
            'enrol' => 'self')
        );
        if ($DB->record_exists(
            'enrol', array('courseid' => $course->id,
            'enrol' => 'self')
        )

        )
    {
            try{
                $update_self_enrol = new stdClass();
                $update_self_enrol->id = $enrol->id;
                $cleannumber = trim($this->rawdata['enrol_id_customint3']);
                // poner restricción a matricularse antes que empiece el curso
                $update_self_enrol->enrolenddate = strtotime($this->rawdata['startdate'] . '12:00:01');
                // Número de alumnos permitidos en el curso (por defecto 7)
                if (empty($cleannumber)) {
                    $update_self_enrol->customint3 = '0';
                } else {
                    $update_self_enrol->customint3 = $cleannumber;
                }
                // el mensaje a enviar a los alumnos cuando se matriculan
                // $update_self_enrol->customtext1 = get_string('mensajeautomatriculacionwebinar', 'local_createcourse') ; TODAVÍA NO EXISTE ESTE STRING EN LOS LANG
                $DB->update_record('enrol', $update_self_enrol);
            } catch (Exception $ex) {
                throw $ex;
            }
        } else {
            echo 'El método de matriculación no existe';
        }
    }

    /**
     * Hay que actulizar la sección
     * en FE sólo hay una
     *
     * @param object $DB     database object
     * @param object $course course object
     *
     * @return void
     */
    function update_expres_course_section($course)
    {
        global $DB;
        $section = $DB->get_record(
            'course_sections', array('course' => ($course->id),
            'section' => '0')
        );

        if ($DB->record_exists(
            'course_sections', array('course' => $course->id, 'section' => '0')
        )
        ) {
            try{
                $update_expres_course_section = new stdClass();
                $update_expres_course_section->id = $section->id;
                $update_expres_course_section->name = 'Formación Exprés';
                $update_expres_course_section->summary = '<p style="font-family: Arial, sans-serif; color: #555555; font-size: 20px; text-align: center;">'.$this->rawdata['description'].'</p><p style="text-align: center;">El enlace a la sala virtual sólo estará disponible el día de realización del curso.</p>';

                course_update_section($course->id, $section, $update_expres_course_section);
            } catch (Exception $ex) {
                throw $ex;
            }
        } else {
            echo 'La sección no existe';
        }

    }

    function update_course_section_certificate($course)
    {
        global $DB;
        $section = $DB->get_record(
            'course_sections', array('course' => ($course->id),
            'name' => 'Certificado del curso')
        );
        $feedbackname = $DB->get_record('modules', array('name' => 'feedback'));
        $feedbacks = $DB->get_record(
            'course_modules', array('course' => $course->id,
            'module' => $feedbackname->id )
        );
        $gradeitem = $DB->get_record(
            'grade_items', array('courseid' => $course->id,
            'itemtype' => 'course')
        );

        if ($DB->record_exists(
            'course_sections', array('course' => $course->id, 'name' => 'Certificado del curso')
        )
        ) {
            try{
                $update_expres_course_section = new stdClass();
                $update_expres_course_section->id = $section->id;
                $update_expres_course_section->availability = '{"op":"&","c":[{"type":"completion","cm":'.$feedbacks->id.',"e":1},{"type":"grade","id":'.$gradeitem->id.',"min":100}],"showc":[true,true]}';
                course_update_section($course->id, $section, $update_expres_course_section);
            } catch (Exception $ex) {
                throw $ex;
            }
        } else {
            echo 'La sección no existe';
        }
        $forums = $DB->get_records('course_modules', array('module' => '9', 'course' => $course->id));
        foreach ($forums as $forum) {
            course_delete_module($forum->id);
        }

    }

        /**
     * Hay que actulizar los modulos de label
     * el primero tiene las restricciones distintas
     *
     * @param object $DB     database object
     * @param object $course course object
     *
     * @return void
     */
    function update_expres_course_labels($course)
    {
        global $DB;
        $labelname = $DB->get_record('modules', array('name' => 'label'));
        $labels = $DB->get_records(
            'course_modules', array('course' => $course->id,
            'module' => $labelname->id)
        );

        /*
        * ESTE FOREACH FUNCIONA PARA PHP 7.3 Y ARRIBA
        */
        foreach ($labels as $key => $label) {

            $updatelabel = new stdClass();
            $updatelabel->modulename = 'label';
            $updatelabel->coursemodule = $label->id;
            $updatelabel->section = 0; // This is the section number in the course. Not the section id in the database.
            $updatelabel->course = $course->id;
            $updatelabel->visible = true;

            $updatelabel->id = $label->id;
            if ($key === array_key_first($labels)) {
                $updatelabel->introeditor = array('text' => '<img src="/banners_gifs/banner_aula_virtual_2020.png" alt="Accede al aula virtual" class="img-fluid">', 'format' => FORMAT_HTML, 'itemid' => '666');
                $updatelabel->availability = '{"op":"&","c":[{"type":"date","d":">=","t":'.strtotime($this->rawdata['startdate']).'},{"type":"date","d":"<","t":'.strtotime($this->rawdata['enddate'] . ' 23:00:00').'}],"showc":[false,false]}';
            } elseif ($key === array_key_last($labels)) {
                $updatelabel->introeditor = array('text' => '<img src="/banners_gifs/banner_certificados_2020.png" alt="Descarga tu certificado" class="img-fluid">', 'format' => FORMAT_HTML, 'itemid' => '666');
                $updatelabel->availability = '{"op":"&","showc":[false],"c":[{"type":"date","d":">=","t":'.strtotime($this->rawdata['startdate']).'}]}';
            } else {
                $updatelabel->introeditor = array('text' => '<img src="/banners_gifs/banner_encuesta_2020.png" alt="Realiza la encuesta de satisfacción" class="img-fluid">', 'format' => FORMAT_HTML, 'itemid' => '666');
                $updatelabel->availability = '{"op":"&","showc":[false],"c":[{"type":"date","d":">=","t":'.strtotime($this->rawdata['startdate']).'}]}';
            }
            update_module($updatelabel);
        }

    }

    /**
     * Hay que actulizar el modulo de lti.
     *
     * @param object $DB     database object
     * @param object $course course object
     *
     * @return void
     */
    function update_expres_course_lti($course)
    {
        global $DB;
        $ltiname = $DB->get_record('modules', array('name' => 'lti'));
        $ltis = $DB->get_records('course_modules', array('course' => $course->id, 'module' => $ltiname->id));

        try{
            foreach ($ltis as $lti) {
                $update_expres_course_lti = new stdClass();
                $update_expres_course_lti->modulename = 'lti';
                $update_expres_course_lti->name = 'Aula virtual';
                $update_expres_course_lti->coursemodule = $lti->id;
                $update_expres_course_lti->section = 0; // This is the section number in the course. Not the section id in the database.
                $update_expres_course_lti->course = $course->id;
                $update_expres_course_lti->visible = true;
                $update_expres_course_lti->introeditor = array('text' => '<p>*** CLIC AQUÍ PARA CONECTAR CON LA VIDEOCONFERENCIA ***</p>', 'format' => FORMAT_HTML, 'itemid' => '666');
                $update_expres_course_lti->toolurl = '';
                $update_expres_course_lti->typeid = 1;
                $update_expres_course_lti->availability = '{"op":"&","c":[{"type":"date","d":">=","t":'.strtotime($this->rawdata['startdate']).'},{"type":"date","d":"<","t":'.strtotime($this->rawdata['enddate'] . ' 23:00:00').'}],"showc":[false,false]}';
                update_module($update_expres_course_lti);
            }
        } catch (Exception $ex) {
            throw $ex;
        }

    }

        /**
     * Hay que actulizar el modulo de feedback.
     *
     * @param object $DB     database object
     * @param object $course course object
     *
     * @return void
     */
    function update_expres_course_feedback($course)
    {
        global $DB;
        $feedbackname = $DB->get_record('modules', array('name' => 'feedback'));
        $feedbacks = $DB->get_records(
            'course_modules', array('course' => $course->id,
            'module' => $feedbackname->id )
        );        
        $feedbackclose = $DB->get_record('feedback', array('course' => $course->id));
        $gradeitem = $DB->get_record(
            'grade_items', array('courseid' => $course->id,
            'itemtype' => 'course')
        );
        try{
            foreach ($feedbacks as $feedback) {
                $update_expres_course_feedback = new stdClass();
                $update_expres_course_feedback->id = $feedback->id;
                $update_expres_course_feedback->coursemodule = $feedback->id;
                $update_expres_course_feedback->modulename = 'feedback';
                $update_expres_course_feedback->course = $course->id;
                $update_expres_course_feedback->section = 0;
                $update_expres_course_feedback->visible = true;
                $update_expres_course_feedback->introeditor = array('text' => '<p>Encuesta de satisfacción</p>', 'format' => FORMAT_HTML, 'itemid' => '666');
                $update_expres_course_feedback->name = 'Encuesta de satisfacción';
                $update_expres_course_feedback->module = $feedbackname->id;
                $update_expres_course_feedback->page_after_submit = false;
                $update_expres_course_feedback->page_after_submit_editor['itemid'] = false; // Hack to bypass draft processing of feedback_add_instance.
                $update_expres_course_feedback->availability = '{"op":"&","c":[{"type":"date","d":">=","t":'.strtotime($this->rawdata['startdate']).'},{"type":"grade","id":'.$gradeitem->id.',"min":100}],"showc":[false,false]}';
                // Aquí abajo es el antiguo availability que cerraba el acceso a la encuesta. Ahora usamos timeclose para cerrar la encuesta.
                //$update_expres_course_feedback->availability = '{"op":"&","c":[{"type":"date","d":">=","t":'.strtotime($this->rawdata['startdate']).'},{"type":"date","d":"<","t":'.strtotime('+9 days', strtotime('first day of next month', strtotime($this->rawdata['enddate']  . ' 23:59:00'))).'}],"showc":[false,false]}';
                update_module($update_expres_course_feedback);

            }
            $update_expres_course_feedbackclose = new stdClass();
            $update_expres_course_feedbackclose->id = $feedbackclose->id;
            $update_expres_course_feedbackclose->timeclose = strtotime('+9 days', strtotime('first day of next month', strtotime($this->rawdata['enddate'] . ' 23:59:00')));
            $DB->update_record('feedback', $update_expres_course_feedbackclose);
        } catch (Exception $ex) {
            throw $ex;
        }
        $this->update_expres_course_certificate($course, $feedbackname);
    }

    /**
     * Hay que actulizar el modulo de feedback para cerrarlo 15 días despues del enddate.
     *
     * @param object $DB     database object
     * @param object $course course object
     *
     * @return void
     */
    function update_normal_course_feedback($course)
    {
        global $DB;
        $feedbackclose = $DB->get_record('feedback', array('course' => $course->id));

        try{
            $update_normal_course_feedback = new stdClass();
            $update_normal_course_feedback->id = $feedbackclose->id;
//          $update_normal_course_feedback->timeclose = strtotime('+9 days', strtotime('first day of next month', strtotime($this->rawdata['enddate'] . ' 23:59:00')));
		    $update_normal_course_feedback->timeclose = strtotime($this->rawdata['enddate']."+ 15 days");
            $DB->update_record('feedback', $update_normal_course_feedback);
        } catch (Exception $ex) {
            throw $ex;
        }
    }

    /**
     * Hay que actulizar el modulo de certificado.
     *
     * @param object $DB           database object
     * @param object $course       course object
     * @param object $feedbackname feedbackname object
     *
     * @return void
     */
    function update_expres_course_certificate($course, $feedbackname)
    {
        global $DB;
        $certificatename = $DB->get_record(
            'modules', array('name' => 'certificate')
        );
        $certificates = $DB->get_records(
            'course_modules', array('course' => $course->id,
            'module' => $certificatename->id)
        );
        $feedback = $DB->get_record(
            'course_modules', array('course' => $course->id,
            'module' => $feedbackname->id)
        );
        $gradeitem = $DB->get_record(
            'grade_items', array('courseid' => $course->id,
            'itemtype' => 'course')
        );
        $cours_cert = $DB->get_record('certificate', array('course' => $course->id));
        $printhours = $DB->get_record('fe_description', [ 'description' => trim($this->rawdata['description'])]);

        try{
            foreach ($certificates as $certificate) {
                $update_expres_course_certificate = new stdClass();
                $update_expres_course_certificate->id = $certificate->id;

                $update_expres_course_certificate->coursemodule = $certificate->id;
                $update_expres_course_certificate->modulename = 'certificate';
                $update_expres_course_certificate->course = $course->id;
                $update_expres_course_certificate->section = 0;
                $update_expres_course_certificate->visible = true;
                $update_expres_course_certificate->introeditor = array('text' => '<p>Certificado de realización del curso</p>', 'format' => FORMAT_HTML, 'itemid' => '666');
                $update_expres_course_certificate->name = 'Certificado de realización del curso';
                $update_expres_course_certificate->module = $certificatename->id;
                $update_expres_course_certificate->availability = '{"op":"&","c":[{"type":"completion","cm":'.$feedback->id.',"e":1},{"type":"date","d":">=","t":'.strtotime($this->rawdata['startdate']).'},{"type":"grade","id":'.$gradeitem->id.',"min":100}],"showc":[false,false,false]}';
                update_module($update_expres_course_certificate);


                $updatehours = new stdClass();
                $updatehours->id = $cours_cert->id;
                $updatehours->printhours = $printhours->printhours;
                $DB->update_record('certificate', $updatehours);

            }
        } catch (Exception $ex) {
            throw $ex;
        }

        set_config('fecourse', $course->id, 'local_createcourse');

    }

    /**
     * Hay que actulizar el modulo de certificado.
     *
     * @param object $DB           database object
     * @param object $course       course object
     * @param object $feedbackname feedbackname object
     *
     * @return void
     */
    function update_normal_course_certificate($course)
    {
        global $DB;

        $cours_cert = $DB->get_record('certificate', array('course' => $course->id));

        try{
                $updatehours = new stdClass();
                $updatehours->id = $cours_cert->id;
                //$updatehours->printhours = round($this->rawdata['duration']);
		$updatehours->printhours = str_replace(',', '.', $this->rawdata['duration']);
                $DB->update_record('certificate', $updatehours);
        } catch (Exception $ex) {
            throw $ex;
        }

        set_config('fecourse', $course->id, 'local_createcourse');

    }

    /**
     * summary of update_teacher_certificate_section
     *
     * @param mixed $course
     * @return void
     */
    function update_teacher_certificate_section($course)
    {
        global $DB;
        $section = $DB->get_record(
            'course_sections',
            array('name' => 'Certificado del curso', 'course' => $course->id));

        if ($DB->record_exists(
            'course_sections', array('course' => $course->id, 'section' => '0')
        )
        ) {
            try{
                $update_teacher_certificate_section = new stdClass();
                $update_teacher_certificate_section->id = $section->id;
                $update_teacher_certificate_section->name = 'Certificado del curso';
                $update_teacher_certificate_section->summary = '<p style="font-family: Arial, sans-serif; color: #555555; font-size: 20px; text-align: center;">'.$this->rawdata['description'].'</p><p style="text-align: center;">El enlace a la sala virtual sólo estará disponible el día de realización del curso.</p>';
                $update_teacher_certificate_section->visible = 0;

                course_update_section($course->id, $section, $update_teacher_certificate_section);
            } catch (Exception $ex) {
                throw $ex;
            }
        } else {
            echo 'La sección no existe';
        }
    }

    /**
     * Summary of delete_forum_rebuild_cache
     * @param mixed $course
     * @return void
     */
    function delete_forum_rebuild_cache($course)
    {
        global $DB;
        $forums = $DB->get_records('course_modules', array('module' => '9', 'course' => $course->id));

        foreach ($forums as $forum) {
            course_delete_module($forum->id);
        }

        rebuild_course_cache($course->id, true);
    }

    /**
     * Summary of rebuild_cache
     * @param mixed $course
     * @return void
     */
    function create_forum($course)
    {
        // Module test values.
        $moduleinfo = new stdClass();

        // Always mandatory generic values to any module.
        $moduleinfo->modulename = 'forum';
        $moduleinfo->name = 'Tablón del tutor';
        $moduleinfo->type = 'general';
        $moduleinfo->section = 0; // This is the section number in the course. Not the section id in the database.
        $moduleinfo->course = $course->id;
        $moduleinfo->visible = true;
        $moduleinfo->grade_forum = 0;
        $moduleinfo->visibleoncoursepage = true;
        $moduleinfo->cmidnumber = 'IDNUM';
        $moduleinfo->introeditor = array('text' => 'El foro del curso ' . $course->fullname, 'format' => FORMAT_HTML, 'itemid' => '666');

        create_module($moduleinfo);
        rebuild_course_cache($course->id, true);
    }

    /**
     * Summary of rebuild_cache
     * @param mixed $course
     * @return void
     */
    function rebuild_cache($course)
    {
        rebuild_course_cache($course->id, true);
    }

    /**
     * Summary of change_start_enddate_format
     * @param mixed $datetochange
     * @return string
     */
    private function change_start_enddate_format($datetochange)
    {
        //$temp = DateTime::createFromFormat('d/m/y', $datetochange);
        //$realtime = $temp->format('m/d/Y');

        if (DateTime::createFromFormat('d/m/y', $datetochange) !== false) {
            $temp = DateTime::createFromFormat('d/m/y', $datetochange);
            $realtime = $temp->format('m/d/Y');
        } else {
            $temp = DateTime::createFromFormat('d/m/Y', $datetochange);
            $realtime = $temp->format('m/d/Y');
        }

        return $realtime;

    }

    /**
     * Imprimir los cursos creados para Rafa.
     *
     * @param object $course object passed.
     *
     * @return void
     */
    function write_csv($data, $logfileid) {
        global $DB;
        $course = $DB->get_record('course', ['shortname' => $data['shortname']], 'id', IGNORE_MISSING);
        if ($course) {
            $url = new moodle_url('/course/view.php', ['id' => $course->id]);
            $line = $url . ' ;' . $data['fullname'] . ';' . $data['shortname'] . ';' . substr($data['shortname'], -3) . ';' . $data['suffix'] . PHP_EOL;
            $filename = 'log/courselog_' . $logfileid . '_' . date('Ymd-G') . '.csv';
            $header = 'URL;Full Name;Short Name;Suffix Web;Suffix Excel' . PHP_EOL;
            if (!file_exists($filename)) {
                file_put_contents($filename, $header);
            }
            file_put_contents($filename, $line, FILE_APPEND);
        } else {
            throw new Exception('Course not found.');
        }
    }
}
