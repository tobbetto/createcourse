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
 * File containing processor class.
 *
 * @package    local_createcourse
 * @copyright  2013 Frédéric Massart
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();
require_once($CFG->libdir . '/csvlib.class.php');
require_once $CFG->dirroot . '/course/externallib.php';

/**
 * Processor class.
 *
 * @package    local_createcourse
 * @copyright  2013 Frédéric Massart
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class local_createcourse_processor {

    /**
     * Create courses that do not exist yet.
     */
    const MODE_CREATE_NEW = 1;

    /**
     * Create all courses, appending a suffix to the shortname if the course exists.
     */
    const MODE_CREATE_ALL = 2;

    /**
     * Create courses, and update the ones that already exist.
     */
    const MODE_CREATE_OR_UPDATE = 3;

    /**
     * Only update existing courses.
     */
    const MODE_UPDATE_ONLY = 4;

    /**
     * During update, do not update anything... O_o Huh?!
     */
    const UPDATE_NOTHING = 0;

    /**
     * During update, only use data passed from the CSV.
     */
    const UPDATE_ALL_WITH_DATA_ONLY = 1;

    /**
     * During update, use either data from the CSV, or defaults.
     */
    const UPDATE_ALL_WITH_DATA_OR_DEFAUTLS = 2;

    /**
     * During update, update missing values from either data from the CSV, or defaults.
     */
    const UPDATE_MISSING_WITH_DATA_OR_DEFAUTLS = 3;

    /** @var int processor mode. */
    protected $mode;

    /** @var int upload mode. */
    protected $updatemode;

    /** @var bool are renames allowed. */
    protected $allowrenames = false;

    /** @var bool are deletes allowed. */
    protected $allowdeletes = false;

    /** @var bool are resets allowed. */
    protected $allowresets = false;

    /** @var string path to a restore file. */
    protected $restorefile;

    /** @var string shortname of the course to be restored. */
    protected $templatecourse;

    /** @var string reset courses after processing them. */
    protected $reset;

    /** @var string template to generate a course shortname. */
    protected $shortnametemplate;

    /** @var csv_import_reader */
    protected $cir;

    /** @var array default values. */
    protected $defaults = array();

    /** @var array CSV columns. */
    protected $columns = array();

    /** @var array of errors where the key is the line number. */
    protected $errors = array();

    /** @var int line number. */
    protected $linenb = 0;

    /** @var bool whether the process has been started or not. */
    protected $processstarted = false;

    /** @var int unique id for studentlog */
    protected $uniqueid;

    /**
     * Constructor
     *
     * @param csv_import_reader $cir import reader object
     * @param array $options options of the process
     * @param array $defaults default data value
     */
    public function __construct(csv_import_reader $cir, array $options, array $defaults = array()) {

        if (!isset($options['mode']) || !in_array($options['mode'], array(self::MODE_CREATE_NEW, self::MODE_CREATE_ALL,
                self::MODE_CREATE_OR_UPDATE, self::MODE_UPDATE_ONLY))) {
            throw new coding_exception('Unknown process mode');
        }

        // Force int to make sure === comparison work as expected.
        $this->mode = (int) $options['mode'];

        $this->updatemode = self::UPDATE_NOTHING;
        if (isset($options['updatemode'])) {
            // Force int to make sure === comparison work as expected.
            $this->updatemode = (int) $options['updatemode'];
        }
        if (isset($options['allowrenames'])) {
            $this->allowrenames = $options['allowrenames'];
        }
        if (isset($options['allowdeletes'])) {
            $this->allowdeletes = $options['allowdeletes'];
        }
        if (isset($options['allowresets'])) {
            $this->allowresets = $options['allowresets'];
        }

        if (isset($options['restorefile'])) {
            $this->restorefile = $options['restorefile'];
        }
        if (isset($options['templatecourse'])) {
            $this->templatecourse = $options['templatecourse'];
        }
        if (isset($options['reset'])) {
            $this->reset = $options['reset'];
        }
        if (isset($options['shortnametemplate'])) {
            $this->shortnametemplate = $options['shortnametemplate'];
        }else {
            //$this->shortnametemplate = 'fexpres-100';
        }
        $this->uniqueid = uniqid();
        $this->cir = $cir;
        $this->columns = $cir->get_columns();
        $this->defaults = $defaults;
        $this->validate();
        $this->reset();
    }

    /**
     * Execute the process.
     *
     * @param object $tracker the output tracker to use.
     * @return void
     */
    public function execute($tracker = null) {
        global $DB;
        if ($this->processstarted) {
            throw new coding_exception('Process has already been started');
        }
        $this->processstarted = true;

        if (empty($tracker)) {
            $tracker = new local_createcourse_tracker(local_createcourse_tracker::NO_OUTPUT);
        }
        $tracker->start();

        $total = 0;
        $created = 0;
        $updated = 0;
        $deleted = 0;
        $errors = 0;

        // We will most certainly need extra time and memory to process big files.
        core_php_time_limit::raise();
        raise_memory_limit(MEMORY_EXTRA);

        $logfileid = uniqid();

        // Loop over the CSV lines.
        while ($line = $this->cir->next()) {
            $this->linenb++;
            $total++;

            $data = $this->parse_line($line);
            $makecategory = $this->set_course_category($data);

            $data['category'] = $makecategory->id;
            $course = $this->get_course($data, $this->uniqueid);
            if ($course->prepare()) {
                $course->proceed();

                $status = $course->get_statuses();
                if (array_key_exists('coursecreated', $status)) {
                    $created++;
                } else if (array_key_exists('courseupdated', $status)) {
                    $updated++;
                } else if (array_key_exists('coursedeleted', $status)) {
                    $deleted++;
                }

                $data = array_merge($data, $course->get_data(), array('id' => $course->get_id()));
                $course->write_csv($data, $this->uniqueid);
                $tracker->output($this->linenb, true, $status, $data);
            } else {
                $errors++;
                $tracker->output($this->linenb, false, $course->get_errors(), $data);
            }
        }

        $this->email_csv_file();

        $tracker->finish();
        $tracker->results($total, $created, $updated, $deleted, $errors);
    }

    /**
     * Return a course import object.
     *
     * @param array $data data to import the course with.
     * @return local_createcourse_course
     */
    protected function get_course($data, $uniqueid) {
        $importoptions = array(
            'candelete' => $this->allowdeletes,
            'canrename' => $this->allowrenames,
            'canreset' => $this->allowresets,
            'reset' => $this->reset,
            'restoredir' => $this->get_restore_content_dir(),
            'shortnametemplate' => $this->shortnametemplate
        );
        return new local_createcourse_course($this->mode, $this->updatemode, $data, $this->defaults, $importoptions, $uniqueid);
    }

    /**
     * Return the errors.
     *
     * @return array
     */
    public function get_errors() {
        return $this->errors;
    }

    /**
     * Get the directory of the object to restore.
     *
     * @return string subdirectory in $CFG->backuptempdir/...
     */
    protected function get_restore_content_dir() {
        $backupfile = null;
        $shortname = null;

        if (!empty($this->restorefile)) {
            $backupfile = $this->restorefile;
        } else if (!empty($this->templatecourse) || is_numeric($this->templatecourse)) {
            $shortname = $this->templatecourse;
        }

        $dir = local_createcourse_helper::get_restore_content_dir($backupfile, $shortname);
        return $dir;
    }

    /**
     * Log errors on the current line.
     *
     * @param array $errors array of errors
     * @return void
     */
    protected function log_error($errors) {
        if (empty($errors)) {
            return;
        }

        foreach ($errors as $code => $langstring) {
            if (!isset($this->errors[$this->linenb])) {
                $this->errors[$this->linenb] = array();
            }
            $this->errors[$this->linenb][$code] = $langstring;
        }
    }

    /**
     * Parse a line to return an array(column => value)
     *
     * @param array $line returned by csv_import_reader
     * @return array
     */
    protected function parse_line($line) {
        $data = array();
        foreach ($line as $keynum => $value) {
            if (!isset($this->columns[$keynum])) {
                // This should not happen.
                continue;
            }

            $key = $this->columns[$keynum];
            $data[$key] = $value;
        }
        return $data;
    }

    /**
     * Return a preview of the import.
     *
     * This only returns passed data, along with the errors.
     *
     * @param integer $rows number of rows to preview.
     * @param object $tracker the output tracker to use.
     * @return array of preview data.
     */
    public function preview($rows = 10, $tracker = null) {
        global $DB;
        if ($this->processstarted) {
            throw new coding_exception('Process has already been started');
        }
        $this->processstarted = true;

        if (empty($tracker)) {
            $tracker = new local_createcourse_tracker(local_createcourse_tracker::NO_OUTPUT);
        }
        $tracker->start();

        // We might need extra time and memory depending on the number of rows to preview.
        core_php_time_limit::raise();
        raise_memory_limit(MEMORY_EXTRA);

        // Loop over the CSV lines.
        $preview = array();
        while (($line = $this->cir->next()) && $rows > $this->linenb) {
            $this->linenb++;
            $data = $this->parse_line($line);

            $categorysql = array('criteria' => array(
                'key' => 'name',
                'value' => trim($data['applicative']) // name of category
            ));

            $searchcategory = $this->get_categories($categorysql, false);
            if (empty($searchcategory)) {
                $data['category'] = 65;
            } else {
                $data['category'] = $searchcategory[0]['id'];
            }

            $course = $this->get_course($data, $this->uniqueid);
            $result = $course->prepare();
            if (!$result) {
                $tracker->output($this->linenb, $result, $course->get_errors(), $data);
            } else {
                $tracker->output($this->linenb, $result, $course->get_statuses(), $data);
            }
            $row = $data;
            $preview[$this->linenb] = $row;
        }

        $tracker->finish();

        return $preview;
    }

    /**
     * Reset the current process.
     *
     * @return void.
     */
    public function reset() {
        $this->processstarted = false;
        $this->linenb = 0;
        $this->cir->init();
        $this->errors = array();
    }

    /**
     * Validation.
     *
     * @return void
     */
    protected function validate() {
        if (empty($this->columns)) {
            throw new moodle_exception('cannotreadtmpfile', 'error');
        } else if (count($this->columns) < 2) {
            throw new moodle_exception('csvfewcolumns', 'error');
        }
    }

    /**
     * Returns description of method parameters
     *
     * @return external_function_parameters
     * @since Moodle 2.3
     */
    public static function get_categories_parameters() {
        return new external_function_parameters(
            array(
                'criteria' => new external_multiple_structure(
                    new external_single_structure(
                        array(
                            'key' => new external_value(PARAM_ALPHA,
                                         'The category column to search, expected keys (value format) are:'.
                                         '"id" (int) the category id,'.
                                         '"ids" (string) category ids separated by commas,'.
                                         '"name" (string) the category name,'.
                                         '"parent" (int) the parent category id,'.
                                         '"idnumber" (string) category idnumber'.
                                         ' - user must have \'moodle/category:manage\' to search on idnumber,'.
                                         '"visible" (int) whether the returned categories must be visible or hidden. If the key is not passed,
                                             then the function return all categories that the user can see.'.
                                         ' - user must have \'moodle/category:manage\' or \'moodle/category:viewhiddencategories\' to search on visible,'.
                                         '"theme" (string) only return the categories having this theme'.
                                         ' - user must have \'moodle/category:manage\' to search on theme'),
                            'value' => new external_value(PARAM_RAW, 'the value to match')
                        )
                    ), 'criteria', VALUE_DEFAULT, array()
                ),
                'addsubcategories' => new external_value(PARAM_BOOL, 'return the sub categories infos
                                          (1 - default) otherwise only the category info (0)', VALUE_DEFAULT, 1)
            )
        );
    }

/**
     * Get categories
     *
     * @param array $criteria Criteria to match the results
     * @param booln $addsubcategories obtain only the category (false) or its subcategories (true - default)
     * @return array list of categories
     * @since Moodle 2.3
     */
    public static function get_categories($criteria = array(), $addsubcategories = true) {
        global $CFG, $DB;
        require_once($CFG->dirroot . "/course/lib.php");

        // Validate parameters.
        $params = array('criteria' => $criteria, 'addsubcategories' => $addsubcategories);

        // Retrieve the categories.
        $categories = array();
        if (!empty($params['criteria'])) {

            $conditions = array();
            $wheres = array();
            foreach ($params['criteria'] as $crit) {
                $key = trim($crit['key']);

                // Trying to avoid duplicate keys.
                if (!isset($conditions[$key])) {

                    $context = context_system::instance();
                    $value = null;
                    switch ($key) {
                        case 'id':
                            $value = clean_param($crit['value'], PARAM_INT);
                            $conditions[$key] = $value;
                            $wheres[] = $key . " = :" . $key;
                            break;

                        case 'ids':
                            $value = clean_param($crit['value'], PARAM_SEQUENCE);
                            $ids = explode(',', $value);
                            list($sqlids, $paramids) = $DB->get_in_or_equal($ids, SQL_PARAMS_NAMED);
                            $conditions = array_merge($conditions, $paramids);
                            $wheres[] = 'id ' . $sqlids;
                            break;

                        case 'idnumber':
                            if (has_capability('moodle/category:manage', $context)) {
                                $value = clean_param($crit['value'], PARAM_RAW);
                                $conditions[$key] = $value;
                                $wheres[] = $key . " = :" . $key;
                            } else {
                                // We must throw an exception.
                                // Otherwise the dev client would think no idnumber exists.
                                throw new moodle_exception('criteriaerror',
                                        'webservice', '', null,
                                        'You don\'t have the permissions to search on the "idnumber" field.');
                            }
                            break;

                        case 'name':
                            $value = clean_param($crit['value'], PARAM_TEXT);
                            $conditions[$key] = $value;
                            $wheres[] = $key . " = :" . $key;
                            break;

                        case 'parent':
                            $value = clean_param($crit['value'], PARAM_INT);
                            $conditions[$key] = $value;
                            $wheres[] = $key . " = :" . $key;
                            break;

                        case 'visible':
                            if (has_capability('moodle/category:viewhiddencategories', $context)) {
                                $value = clean_param($crit['value'], PARAM_INT);
                                $conditions[$key] = $value;
                                $wheres[] = $key . " = :" . $key;
                            } else {
                                throw new moodle_exception('criteriaerror',
                                        'webservice', '', null,
                                        'You don\'t have the permissions to search on the "visible" field.');
                            }
                            break;

                        case 'theme':
                            if (has_capability('moodle/category:manage', $context)) {
                                $value = clean_param($crit['value'], PARAM_THEME);
                                $conditions[$key] = $value;
                                $wheres[] = $key . " = :" . $key;
                            } else {
                                throw new moodle_exception('criteriaerror',
                                        'webservice', '', null,
                                        'You don\'t have the permissions to search on the "theme" field.');
                            }
                            break;

                        default:
                            throw new moodle_exception('criteriaerror',
                                    'webservice', '', null,
                                    'You can not search on this criteria: ' . $key);
                    }
                }
            }

            if (!empty($wheres)) {
                $wheres = implode(" AND ", $wheres);

                $categories = $DB->get_records_select('course_categories', $wheres, $conditions);

                // Retrieve its sub subcategories (all levels).
                if ($categories and !empty($params['addsubcategories'])) {
                    $newcategories = array();

                    // Check if we required visible/theme checks.
                    $additionalselect = '';
                    $additionalparams = array();
                    if (isset($conditions['visible'])) {
                        $additionalselect .= ' AND visible = :visible';
                        $additionalparams['visible'] = $conditions['visible'];
                    }
                    if (isset($conditions['theme'])) {
                        $additionalselect .= ' AND theme= :theme';
                        $additionalparams['theme'] = $conditions['theme'];
                    }

                    foreach ($categories as $category) {
                        $sqlselect = $DB->sql_like('path', ':path') . $additionalselect;
                        $sqlparams = array('path' => $category->path.'/%') + $additionalparams; // It will NOT include the specified category.
                        $subcategories = $DB->get_records_select('course_categories', $sqlselect, $sqlparams);
                        $newcategories = $newcategories + $subcategories;   // Both arrays have integer as keys.
                    }
                    $categories = $categories + $newcategories;
                }
            }

        } else {
            // Retrieve all categories in the database.
            $categories = $DB->get_records('course_categories');
        }

        // The not returned categories. key => category id, value => reason of exclusion.
        $excludedcats = array();

        // The returned categories.
        $categoriesinfo = array();

        // We need to sort the categories by path.
        // The parent cats need to be checked by the algo first.
        //usort($categories, "core_course_external::compare_categories_by_path");

        foreach ($categories as $category) {

            // Check if the category is a child of an excluded category, if yes exclude it too (excluded => do not return).
            $parents = explode('/', $category->path);
            unset($parents[0]); // First key is always empty because path start with / => /1/2/4.
            foreach ($parents as $parentid) {
                // Note: when the parent exclusion was due to the context,
                // the sub category could still be returned.
                if (isset($excludedcats[$parentid]) and $excludedcats[$parentid] != 'context') {
                    $excludedcats[$category->id] = 'parent';
                }
            }

            // Check the user can use the category context.
            $context = context_coursecat::instance($category->id);
            try {
                //self::validate_context($context);
            } catch (Exception $e) {
                $excludedcats[$category->id] = 'context';

                // If it was the requested category then throw an exception.
                if (isset($params['categoryid']) && $category->id == $params['categoryid']) {
                    $exceptionparam = new stdClass();
                    $exceptionparam->message = $e->getMessage();
                    $exceptionparam->catid = $category->id;
                    throw new moodle_exception('errorcatcontextnotvalid', 'webservice', '', $exceptionparam);
                }
            }

            // Return the category information.
            if (!isset($excludedcats[$category->id])) {

                // Final check to see if the category is visible to the user.
                if (core_course_category::can_view_category($category)) {

                    $categoryinfo = array();
                    $categoryinfo['id'] = $category->id;
                    $categoryinfo['name'] = external_format_string($category->name, $context);
                    list($categoryinfo['description'], $categoryinfo['descriptionformat']) =
                        external_format_text($category->description, $category->descriptionformat,
                                $context->id, 'coursecat', 'description', null);
                    $categoryinfo['parent'] = $category->parent;
                    $categoryinfo['sortorder'] = $category->sortorder;
                    $categoryinfo['coursecount'] = $category->coursecount;
                    $categoryinfo['depth'] = $category->depth;
                    $categoryinfo['path'] = $category->path;

                    // Some fields only returned for admin.
                    if (has_capability('moodle/category:manage', $context)) {
                        $categoryinfo['idnumber'] = $category->idnumber;
                        $categoryinfo['visible'] = $category->visible;
                        $categoryinfo['visibleold'] = $category->visibleold;
                        $categoryinfo['timemodified'] = $category->timemodified;
                        $categoryinfo['theme'] = clean_param($category->theme, PARAM_THEME);
                    }

                    $categoriesinfo[] = $categoryinfo;
                } else {
                    $excludedcats[$category->id] = 'visibility';
                }
            }
        }

        // Sorting the resulting array so it looks a bit better for the client developer.
       // usort($categoriesinfo, "core_course_external::compare_categories_by_sortorder");

        return $categoriesinfo;
    }

/**
     * Summary of set_course_category
     * @param mixed $categoryname
     * @return mixed
     */
    public function set_course_category($csvdata)
    {
        global $DB;
        $applicative = $csvdata['applicative'];
        $scope = $csvdata['scope'];
        $mode = $csvdata['mode'];


        if ($mode == 'EXPRES') {
            $categorymode = $DB->get_record(
                'course_categories',
                array('name' => 'FORMACIÓN EXPRÉS')
                );
            $categoryapplicative = $DB->get_record(
                'course_categories',
                array('name' => $applicative,
                'parent' => $categorymode->id)
            );
            $categorysql = array('criteria' => array(
                'key' => 'id',
                'value' => $categoryapplicative->id) // nombre de la categoria. Si está vacía, se creará una categoria nueva.
            );
            $searchcategory = $this->get_categories($categorysql, false);
        } else {
            $categoryscope = $DB->get_record(
                'course_categories',
                array( 'name' => $scope)
            );
            $categorymode = $DB->get_record(
                'course_categories',
                array( 'name' => $mode, 'parent' => $categoryscope->id)
            );
            $categoryapplicative = $DB->get_record(
                'course_categories',
                array('name' => $applicative,
                'parent' => $categorymode->id)
            );
            $categorysql = array('criteria' => array(
                'key' => 'id',
                'value' => $categoryapplicative->id) // nombre de la categoria. Si está vacía, se creará una categoria nueva.
            );
            $searchcategory = $this->get_categories($categorysql, false);

        }
        if (empty($searchcategory)) {
            $categoryparams = array('categories' => array(
                'name' => $applicative, // nombre de la categoria
                'parent' => $categorymode->id, // id de la categoria padre
                'descriptionformat' => 1,
                'description' => $applicative .' (' . $categoryscope->name . ' / '  . $categorymode->name . ' )'
            ));
            $this->create_categories($categoryparams);
            $course_category = $DB->get_record(
                'course_categories',
                array('name' => $applicative,
                'parent' => $categorymode->id)
            );
        } else {
            $course_category = $DB->get_record(
                'course_categories',
                array('name' => $applicative,
                'parent' => $categorymode->id)
            );
        }

        return $course_category;

    }

    /**
     * Create categories
     *
     * @param array $categories - see create_categories_parameters() for the array structure
     * @return array - see create_categories_returns() for the array structure
     * @since Moodle 2.3
     */
    public static function create_categories($categories) {
        global $DB;

        $params = array('categories' => $categories);

        $transaction = $DB->start_delegated_transaction();

        $createdcategories = array();
        foreach ($params['categories'] as $category) {
            if ($category['parent']) {
                if (!$DB->record_exists('course_categories', array('id' => $category['parent']))) {
                    throw new moodle_exception('unknowcategory');
                }
                $context = context_coursecat::instance($category['parent']);
            } else {
                $context = context_system::instance();
            }
            //self::validate_context($context);
            require_capability('moodle/category:manage', $context);

            // this will validate format and throw an exception if there are errors
            //external_validate_format($category['descriptionformat']);

            $newcategory = core_course_category::create($category);
            $context = context_coursecat::instance($newcategory->id);

            $createdcategories[] = array(
                'id' => $newcategory->id,
                'name' => external_format_string($newcategory->name, $context),
            );
        }

        $transaction->allow_commit();

        return $createdcategories;
    }

    /**
     * Summary of email_csv_file
     * @return void
     */
    function email_csv_file() {
        global $DB;

        // Define email recipients
        $emails = ['thorvaldur.konradsson@empresas.justicia.es', 'rafael.gomezgarcia@empresas.justicia.es'];

        // Get support user
        $fromuser = core_user::get_support_user();

        // Define log files
        $courselog = 'log/courselog_' . $this->uniqueid . '_' . date('Ymd-G') .'.csv';
        $studentlog = 'log/studentlog_' . $this->uniqueid . '_' . date('Ymd-G') .'.txt';

        // Create message
        $messagecourse = 'Los cursos creados en esta tanda: <br />';
        $messagescourse = file_get_contents($courselog, false, null);
        $courselines = explode("\n", $messagescourse);

        foreach ($courselines as $course) {
            $messagecourse .= $course . '<br />';
        }

        if (file_exists($studentlog)) {
            $messagecourse .= '<p>🙁</p>'.PHP_EOL;
            $messagecourse .= '<br />Errores en matriculaciones: <br />';
            $messagesstudent = file_get_contents($studentlog, false, null);
            $studentlines = explode("\n", $messagesstudent);

            foreach ($studentlines as $student) {
                $messagecourse .= $student .PHP_EOL;
            }
            $subject = '🙁 Log de la creación de cursos y errores de matriculación hoy ' . date('d/m/Y') . '.';
            $attachment = $courselog;
        } else {
            $messagecourse .= '<p>🙂</p>'.PHP_EOL;
            $subject = '🙂 Log de la creación de cursos hoy ' . date('d/m/Y') . '.';
            $attachment = '';
        }

        // Send email to each recipient
        foreach ($emails as $email) {
            $touser = $DB->get_record('user', array('email' => $email));

            if (!empty($touser)) {
                try {
                    email_to_user($touser, $fromuser, $subject, '', $messagecourse, $attachment, 'courselog_' . $this->uniqueid . '_' . date('Ymd-G') .'.csv');
                } catch (Exception $ex) {
                    throw $ex;
                }
            }
        }
    }

}
