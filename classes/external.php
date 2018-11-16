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
 * Ddtaquiz external API
 *
 * @package    mod_ddtaquiz
 * @category   external
 * @copyright  2016 Juan Leyva <juan@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since      Moodle 3.1
 */

defined('MOODLE_INTERNAL') || die;

require_once($CFG->libdir . '/externallib.php');
require_once($CFG->dirroot . '/mod/ddtaquiz/locallib.php');

/**
 * Ddtaquiz external functions
 *
 * @package    mod_ddtaquiz
 * @category   external
 * @copyright  2016 Juan Leyva <juan@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since      Moodle 3.1
 */
class mod_ddtaquiz_external extends external_api {

    /**
     * Describes the parameters for get_ddtaquizzes_by_courses.
     *
     * @return external_function_parameters
     * @since Moodle 3.1
     */
    public static function get_ddtaquizzes_by_courses_parameters() {
        return new external_function_parameters (
            array(
                'courseids' => new external_multiple_structure(
                    new external_value(PARAM_INT, 'course id'), 'Array of course ids', VALUE_DEFAULT, array()
                ),
            )
        );
    }

    /**
     * Returns a list of ddtaquizzes in a provided list of courses,
     * if no list is provided all ddtaquizzes that the user can view will be returned.
     *
     * @param array $courseids Array of course ids
     * @return array of ddtaquizzes details
     * @since Moodle 3.1
     */
    public static function get_ddtaquizzes_by_courses($courseids = array()) {
        global $USER;

        $warnings = array();
        $returnedddtaquizzes = array();

        $params = array(
            'courseids' => $courseids,
        );
        $params = self::validate_parameters(self::get_ddtaquizzes_by_courses_parameters(), $params);

        $mycourses = array();
        if (empty($params['courseids'])) {
            $mycourses = enrol_get_my_courses();
            $params['courseids'] = array_keys($mycourses);
        }

        // Ensure there are courseids to loop through.
        if (!empty($params['courseids'])) {

            list($courses, $warnings) = external_util::validate_courses($params['courseids'], $mycourses);

            // Get the ddtaquizzes in this course, this function checks users visibility permissions.
            // We can avoid then additional validate_context calls.
            $ddtaquizzes = get_all_instances_in_courses("ddtaquiz", $courses);
            foreach ($ddtaquizzes as $ddtaquiz) {
                $context = context_module::instance($ddtaquiz->coursemodule);

                // Update ddtaquiz with override information.
                $ddtaquiz = ddtaquiz_update_effective_access($ddtaquiz, $USER->id);

                // Entry to return.
                $ddtaquizdetails = array();
                // First, we return information that any user can see in the web interface.
                $ddtaquizdetails['id'] = $ddtaquiz->id;
                $ddtaquizdetails['coursemodule']      = $ddtaquiz->coursemodule;
                $ddtaquizdetails['course']            = $ddtaquiz->course;
                $ddtaquizdetails['name']              = external_format_string($ddtaquiz->name, $context->id);

                if (has_capability('mod/ddtaquiz:view', $context)) {
                    // Format intro.
                    list($ddtaquizdetails['intro'], $ddtaquizdetails['introformat']) = external_format_text($ddtaquiz->intro,
                                                                    $ddtaquiz->introformat, $context->id, 'mod_ddtaquiz', 'intro', null);

                    $ddtaquizdetails['introfiles'] = external_util::get_area_files($context->id, 'mod_ddtaquiz', 'intro', false, false);
                    $viewablefields = array('timeopen', 'timeclose', 'grademethod', 'section', 'visible', 'groupmode',
                                            'groupingid', 'attempts', 'timelimit', 'grademethod', 'decimalpoints',
                                            'questiondecimalpoints', 'sumgrades', 'grade', 'preferredbehaviour');
                    // Some times this function returns just empty.
                    $hasfeedback = ddtaquiz_has_feedback($ddtaquiz);
                    $ddtaquizdetails['hasfeedback'] = (!empty($hasfeedback)) ? 1 : 0;

                    $timenow = time();
                    $ddtaquizobj = ddtaquiz::create($ddtaquiz->id, $USER->id);
                    $accessmanager = new ddtaquiz_access_manager($ddtaquizobj, $timenow, has_capability('mod/ddtaquiz:ignoretimelimits',
                                                                $context, null, false));

                    // Fields the user could see if have access to the ddtaquiz.
                    if (!$accessmanager->prevent_access()) {
                        $ddtaquizdetails['hasquestions'] = (int) $ddtaquizobj->has_questions();
                        $ddtaquizdetails['autosaveperiod'] = get_config('ddtaquiz', 'autosaveperiod');

                        $additionalfields = array('attemptonlast', 'reviewattempt', 'reviewcorrectness', 'reviewmarks',
                                                    'reviewspecificfeedback', 'reviewgeneralfeedback', 'reviewrightanswer',
                                                    'reviewoverallfeedback', 'questionsperpage', 'navmethod',
                                                    'browsersecurity', 'delay1', 'delay2', 'showuserpicture', 'showblocks',
                                                    'completionattemptsexhausted', 'completionpass', 'overduehandling',
                                                    'graceperiod', 'canredoquestions', 'allowofflineattempts');
                        $viewablefields = array_merge($viewablefields, $additionalfields);
                    }

                    // Fields only for managers.
                    if (has_capability('moodle/course:manageactivities', $context)) {
                        $additionalfields = array('shuffleanswers', 'timecreated', 'timemodified', 'password', 'subnet');
                        $viewablefields = array_merge($viewablefields, $additionalfields);
                    }

                    foreach ($viewablefields as $field) {
                        $ddtaquizdetails[$field] = $ddtaquiz->{$field};
                    }
                }
                $returnedddtaquizzes[] = $ddtaquizdetails;
            }
        }
        $result = array();
        $result['ddtaquizzes'] = $returnedddtaquizzes;
        $result['warnings'] = $warnings;
        return $result;
    }

    /**
     * Describes the get_ddtaquizzes_by_courses return value.
     *
     * @return external_single_structure
     * @since Moodle 3.1
     */
    public static function get_ddtaquizzes_by_courses_returns() {
        return new external_single_structure(
            array(
                'ddtaquizzes' => new external_multiple_structure(
                    new external_single_structure(
                        array(
                            'id' => new external_value(PARAM_INT, 'Standard Moodle primary key.'),
                            'course' => new external_value(PARAM_INT, 'Foreign key reference to the course this ddtaquiz is part of.'),
                            'coursemodule' => new external_value(PARAM_INT, 'Course module id.'),
                            'name' => new external_value(PARAM_RAW, 'Ddtaquiz name.'),
                            'intro' => new external_value(PARAM_RAW, 'Ddtaquiz introduction text.', VALUE_OPTIONAL),
                            'introformat' => new external_format_value('intro', VALUE_OPTIONAL),
                            'introfiles' => new external_files('Files in the introduction text', VALUE_OPTIONAL),
                            'timeopen' => new external_value(PARAM_INT, 'The time when this ddtaquiz opens. (0 = no restriction.)',
                                                                VALUE_OPTIONAL),
                            'timeclose' => new external_value(PARAM_INT, 'The time when this ddtaquiz closes. (0 = no restriction.)',
                                                                VALUE_OPTIONAL),
                            'timelimit' => new external_value(PARAM_INT, 'The time limit for ddtaquiz attempts, in seconds.',
                                                                VALUE_OPTIONAL),
                            'overduehandling' => new external_value(PARAM_ALPHA, 'The method used to handle overdue attempts.
                                                                    \'autosubmit\', \'graceperiod\' or \'autoabandon\'.',
                                                                    VALUE_OPTIONAL),
                            'graceperiod' => new external_value(PARAM_INT, 'The amount of time (in seconds) after the time limit
                                                                runs out during which attempts can still be submitted,
                                                                if overduehandling is set to allow it.', VALUE_OPTIONAL),
                            'preferredbehaviour' => new external_value(PARAM_ALPHANUMEXT, 'The behaviour to ask questions to use.',
                                                                        VALUE_OPTIONAL),
                            'canredoquestions' => new external_value(PARAM_INT, 'Allows students to redo any completed question
                                                                        within a ddtaquiz attempt.', VALUE_OPTIONAL),
                            'attempts' => new external_value(PARAM_INT, 'The maximum number of attempts a student is allowed.',
                                                                VALUE_OPTIONAL),
                            'attemptonlast' => new external_value(PARAM_INT, 'Whether subsequent attempts start from the answer
                                                                    to the previous attempt (1) or start blank (0).',
                                                                    VALUE_OPTIONAL),
                            'grademethod' => new external_value(PARAM_INT, 'One of the values DDTAQUIZ_GRADEHIGHEST, DDTAQUIZ_GRADEAVERAGE,
                                                                    DDTAQUIZ_ATTEMPTFIRST or DDTAQUIZ_ATTEMPTLAST.', VALUE_OPTIONAL),
                            'decimalpoints' => new external_value(PARAM_INT, 'Number of decimal points to use when displaying
                                                                    grades.', VALUE_OPTIONAL),
                            'questiondecimalpoints' => new external_value(PARAM_INT, 'Number of decimal points to use when
                                                                            displaying question grades.
                                                                            (-1 means use decimalpoints.)', VALUE_OPTIONAL),
                            'reviewattempt' => new external_value(PARAM_INT, 'Whether users are allowed to review their ddtaquiz
                                                                    attempts at various times. This is a bit field, decoded by the
                                                                    mod_ddtaquiz_display_options class. It is formed by ORing together
                                                                    the constants defined there.', VALUE_OPTIONAL),
                            'reviewcorrectness' => new external_value(PARAM_INT, 'Whether users are allowed to review their ddtaquiz
                                                                        attempts at various times.
                                                                        A bit field, like reviewattempt.', VALUE_OPTIONAL),
                            'reviewmarks' => new external_value(PARAM_INT, 'Whether users are allowed to review their ddtaquiz attempts
                                                                at various times. A bit field, like reviewattempt.',
                                                                VALUE_OPTIONAL),
                            'reviewspecificfeedback' => new external_value(PARAM_INT, 'Whether users are allowed to review their
                                                                            ddtaquiz attempts at various times. A bit field, like
                                                                            reviewattempt.', VALUE_OPTIONAL),
                            'reviewgeneralfeedback' => new external_value(PARAM_INT, 'Whether users are allowed to review their
                                                                            ddtaquiz attempts at various times. A bit field, like
                                                                            reviewattempt.', VALUE_OPTIONAL),
                            'reviewrightanswer' => new external_value(PARAM_INT, 'Whether users are allowed to review their ddtaquiz
                                                                        attempts at various times. A bit field, like
                                                                        reviewattempt.', VALUE_OPTIONAL),
                            'reviewoverallfeedback' => new external_value(PARAM_INT, 'Whether users are allowed to review their ddtaquiz
                                                                            attempts at various times. A bit field, like
                                                                            reviewattempt.', VALUE_OPTIONAL),
                            'questionsperpage' => new external_value(PARAM_INT, 'How often to insert a page break when editing
                                                                        the ddtaquiz, or when shuffling the question order.',
                                                                        VALUE_OPTIONAL),
                            'navmethod' => new external_value(PARAM_ALPHA, 'Any constraints on how the user is allowed to navigate
                                                                around the ddtaquiz. Currently recognised values are
                                                                \'free\' and \'seq\'.', VALUE_OPTIONAL),
                            'shuffleanswers' => new external_value(PARAM_INT, 'Whether the parts of the question should be shuffled,
                                                                    in those question types that support it.', VALUE_OPTIONAL),
                            'sumgrades' => new external_value(PARAM_FLOAT, 'The total of all the question instance maxmarks.',
                                                                VALUE_OPTIONAL),
                            'grade' => new external_value(PARAM_FLOAT, 'The total that the ddtaquiz overall grade is scaled to be
                                                            out of.', VALUE_OPTIONAL),
                            'timecreated' => new external_value(PARAM_INT, 'The time when the ddtaquiz was added to the course.',
                                                                VALUE_OPTIONAL),
                            'timemodified' => new external_value(PARAM_INT, 'Last modified time.',
                                                                    VALUE_OPTIONAL),
                            'password' => new external_value(PARAM_RAW, 'A password that the student must enter before starting or
                                                                continuing a ddtaquiz attempt.', VALUE_OPTIONAL),
                            'subnet' => new external_value(PARAM_RAW, 'Used to restrict the IP addresses from which this ddtaquiz can
                                                            be attempted. The format is as requried by the address_in_subnet
                                                            function.', VALUE_OPTIONAL),
                            'browsersecurity' => new external_value(PARAM_ALPHANUMEXT, 'Restriciton on the browser the student must
                                                                    use. E.g. \'securewindow\'.', VALUE_OPTIONAL),
                            'delay1' => new external_value(PARAM_INT, 'Delay that must be left between the first and second attempt,
                                                            in seconds.', VALUE_OPTIONAL),
                            'delay2' => new external_value(PARAM_INT, 'Delay that must be left between the second and subsequent
                                                            attempt, in seconds.', VALUE_OPTIONAL),
                            'showuserpicture' => new external_value(PARAM_INT, 'Option to show the user\'s picture during the
                                                                    attempt and on the review page.', VALUE_OPTIONAL),
                            'showblocks' => new external_value(PARAM_INT, 'Whether blocks should be shown on the attempt.php and
                                                                review.php pages.', VALUE_OPTIONAL),
                            'completionattemptsexhausted' => new external_value(PARAM_INT, 'Mark ddtaquiz complete when the student has
                                                                                exhausted the maximum number of attempts',
                                                                                VALUE_OPTIONAL),
                            'completionpass' => new external_value(PARAM_INT, 'Whether to require passing grade', VALUE_OPTIONAL),
                            'allowofflineattempts' => new external_value(PARAM_INT, 'Whether to allow the ddtaquiz to be attempted
                                                                            offline in the mobile app', VALUE_OPTIONAL),
                            'autosaveperiod' => new external_value(PARAM_INT, 'Auto-save delay', VALUE_OPTIONAL),
                            'hasfeedback' => new external_value(PARAM_INT, 'Whether the ddtaquiz has any non-blank feedback text',
                                                                VALUE_OPTIONAL),
                            'hasquestions' => new external_value(PARAM_INT, 'Whether the ddtaquiz has questions', VALUE_OPTIONAL),
                            'section' => new external_value(PARAM_INT, 'Course section id', VALUE_OPTIONAL),
                            'visible' => new external_value(PARAM_INT, 'Module visibility', VALUE_OPTIONAL),
                            'groupmode' => new external_value(PARAM_INT, 'Group mode', VALUE_OPTIONAL),
                            'groupingid' => new external_value(PARAM_INT, 'Grouping id', VALUE_OPTIONAL),
                        )
                    )
                ),
                'warnings' => new external_warnings(),
            )
        );
    }


    /**
     * Utility function for validating a ddtaquiz.
     *
     * @param int $ddtaquizid ddtaquiz instance id
     * @return array array containing the ddtaquiz, course, context and course module objects
     * @since  Moodle 3.1
     */
    protected static function validate_ddtaquiz($ddtaquizid) {
        global $DB;

        // Request and permission validation.
        $ddtaquiz = $DB->get_record('ddtaquiz', array('id' => $ddtaquizid), '*', MUST_EXIST);
        list($course, $cm) = get_course_and_cm_from_instance($ddtaquiz, 'ddtaquiz');

        $context = context_module::instance($cm->id);
        self::validate_context($context);

        return array($ddtaquiz, $course, $cm, $context);
    }

    /**
     * Describes the parameters for view_ddtaquiz.
     *
     * @return external_function_parameters
     * @since Moodle 3.1
     */
    public static function view_ddtaquiz_parameters() {
        return new external_function_parameters (
            array(
                'ddtaquizid' => new external_value(PARAM_INT, 'ddtaquiz instance id'),
            )
        );
    }

    /**
     * Trigger the course module viewed event and update the module completion status.
     *
     * @param int $ddtaquizid ddtaquiz instance id
     * @return array of warnings and status result
     * @since Moodle 3.1
     * @throws moodle_exception
     */
    public static function view_ddtaquiz($ddtaquizid) {
        global $DB;

        $params = self::validate_parameters(self::view_ddtaquiz_parameters(), array('ddtaquizid' => $ddtaquizid));
        $warnings = array();

        list($ddtaquiz, $course, $cm, $context) = self::validate_ddtaquiz($params['ddtaquizid']);

        // Trigger course_module_viewed event and completion.
        ddtaquiz_view($ddtaquiz, $course, $cm, $context);

        $result = array();
        $result['status'] = true;
        $result['warnings'] = $warnings;
        return $result;
    }

    /**
     * Describes the view_ddtaquiz return value.
     *
     * @return external_single_structure
     * @since Moodle 3.1
     */
    public static function view_ddtaquiz_returns() {
        return new external_single_structure(
            array(
                'status' => new external_value(PARAM_BOOL, 'status: true if success'),
                'warnings' => new external_warnings(),
            )
        );
    }

    /**
     * Describes the parameters for get_user_attempts.
     *
     * @return external_function_parameters
     * @since Moodle 3.1
     */
    public static function get_user_attempts_parameters() {
        return new external_function_parameters (
            array(
                'ddtaquizid' => new external_value(PARAM_INT, 'ddtaquiz instance id'),
                'userid' => new external_value(PARAM_INT, 'user id, empty for current user', VALUE_DEFAULT, 0),
                'status' => new external_value(PARAM_ALPHA, 'ddtaquiz status: all, finished or unfinished', VALUE_DEFAULT, 'finished'),
                'includepreviews' => new external_value(PARAM_BOOL, 'whether to include previews or not', VALUE_DEFAULT, false),

            )
        );
    }

    /**
     * Return a list of attempts for the given ddtaquiz and user.
     *
     * @param int $ddtaquizid ddtaquiz instance id
     * @param int $userid user id
     * @param string $status ddtaquiz status: all, finished or unfinished
     * @param bool $includepreviews whether to include previews or not
     * @return array of warnings and the list of attempts
     * @since Moodle 3.1
     * @throws invalid_parameter_exception
     */
    public static function get_user_attempts($ddtaquizid, $userid = 0, $status = 'finished', $includepreviews = false) {
        global $DB, $USER;

        $warnings = array();

        $params = array(
            'ddtaquizid' => $ddtaquizid,
            'userid' => $userid,
            'status' => $status,
            'includepreviews' => $includepreviews,
        );
        $params = self::validate_parameters(self::get_user_attempts_parameters(), $params);

        list($ddtaquiz, $course, $cm, $context) = self::validate_ddtaquiz($params['ddtaquizid']);

        if (!in_array($params['status'], array('all', 'finished', 'unfinished'))) {
            throw new invalid_parameter_exception('Invalid status value');
        }

        // Default value for userid.
        if (empty($params['userid'])) {
            $params['userid'] = $USER->id;
        }

        $user = core_user::get_user($params['userid'], '*', MUST_EXIST);
        core_user::require_active_user($user);

        // Extra checks so only users with permissions can view other users attempts.
        if ($USER->id != $user->id) {
            require_capability('mod/ddtaquiz:viewreports', $context);
        }

        $attempts = ddtaquiz_get_user_attempts($ddtaquiz->id, $user->id, $params['status'], $params['includepreviews']);

        $result = array();
        $result['attempts'] = $attempts;
        $result['warnings'] = $warnings;
        return $result;
    }

    /**
     * Describes a single attempt structure.
     *
     * @return external_single_structure the attempt structure
     */
    private static function attempt_structure() {
        return new external_single_structure(
            array(
                'id' => new external_value(PARAM_INT, 'Attempt id.', VALUE_OPTIONAL),
                'ddtaquiz' => new external_value(PARAM_INT, 'Foreign key reference to the ddtaquiz that was attempted.',
                                                VALUE_OPTIONAL),
                'userid' => new external_value(PARAM_INT, 'Foreign key reference to the user whose attempt this is.',
                                                VALUE_OPTIONAL),
                'attempt' => new external_value(PARAM_INT, 'Sequentially numbers this students attempts at this ddtaquiz.',
                                                VALUE_OPTIONAL),
                'uniqueid' => new external_value(PARAM_INT, 'Foreign key reference to the question_usage that holds the
                                                    details of the the question_attempts that make up this ddtaquiz
                                                    attempt.', VALUE_OPTIONAL),
                'layout' => new external_value(PARAM_RAW, 'Attempt layout.', VALUE_OPTIONAL),
                'currentpage' => new external_value(PARAM_INT, 'Attempt current page.', VALUE_OPTIONAL),
                'preview' => new external_value(PARAM_INT, 'Whether is a preview attempt or not.', VALUE_OPTIONAL),
                'state' => new external_value(PARAM_ALPHA, 'The current state of the attempts. \'inprogress\',
                                                \'overdue\', \'finished\' or \'abandoned\'.', VALUE_OPTIONAL),
                'timestart' => new external_value(PARAM_INT, 'Time when the attempt was started.', VALUE_OPTIONAL),
                'timefinish' => new external_value(PARAM_INT, 'Time when the attempt was submitted.
                                                    0 if the attempt has not been submitted yet.', VALUE_OPTIONAL),
                'timemodified' => new external_value(PARAM_INT, 'Last modified time.', VALUE_OPTIONAL),
                'timemodifiedoffline' => new external_value(PARAM_INT, 'Last modified time via webservices.', VALUE_OPTIONAL),
                'timecheckstate' => new external_value(PARAM_INT, 'Next time ddtaquiz cron should check attempt for
                                                        state changes.  NULL means never check.', VALUE_OPTIONAL),
                'sumgrades' => new external_value(PARAM_FLOAT, 'Total marks for this attempt.', VALUE_OPTIONAL),
            )
        );
    }

    /**
     * Describes the get_user_attempts return value.
     *
     * @return external_single_structure
     * @since Moodle 3.1
     */
    public static function get_user_attempts_returns() {
        return new external_single_structure(
            array(
                'attempts' => new external_multiple_structure(self::attempt_structure()),
                'warnings' => new external_warnings(),
            )
        );
    }

    /**
     * Describes the parameters for get_user_best_grade.
     *
     * @return external_function_parameters
     * @since Moodle 3.1
     */
    public static function get_user_best_grade_parameters() {
        return new external_function_parameters (
            array(
                'ddtaquizid' => new external_value(PARAM_INT, 'ddtaquiz instance id'),
                'userid' => new external_value(PARAM_INT, 'user id', VALUE_DEFAULT, 0),
            )
        );
    }

    /**
     * Get the best current grade for the given user on a ddtaquiz.
     *
     * @param int $ddtaquizid ddtaquiz instance id
     * @param int $userid user id
     * @return array of warnings and the grade information
     * @since Moodle 3.1
     */
    public static function get_user_best_grade($ddtaquizid, $userid = 0) {
        global $DB, $USER;

        $warnings = array();

        $params = array(
            'ddtaquizid' => $ddtaquizid,
            'userid' => $userid,
        );
        $params = self::validate_parameters(self::get_user_best_grade_parameters(), $params);

        list($ddtaquiz, $course, $cm, $context) = self::validate_ddtaquiz($params['ddtaquizid']);

        // Default value for userid.
        if (empty($params['userid'])) {
            $params['userid'] = $USER->id;
        }

        $user = core_user::get_user($params['userid'], '*', MUST_EXIST);
        core_user::require_active_user($user);

        // Extra checks so only users with permissions can view other users attempts.
        if ($USER->id != $user->id) {
            require_capability('mod/ddtaquiz:viewreports', $context);
        }

        $result = array();
        $grade = ddtaquiz_get_best_grade($ddtaquiz, $user->id);

        if ($grade === null) {
            $result['hasgrade'] = false;
        } else {
            $result['hasgrade'] = true;
            $result['grade'] = $grade;
        }
        $result['warnings'] = $warnings;
        return $result;
    }

    /**
     * Describes the get_user_best_grade return value.
     *
     * @return external_single_structure
     * @since Moodle 3.1
     */
    public static function get_user_best_grade_returns() {
        return new external_single_structure(
            array(
                'hasgrade' => new external_value(PARAM_BOOL, 'Whether the user has a grade on the given ddtaquiz.'),
                'grade' => new external_value(PARAM_FLOAT, 'The grade (only if the user has a grade).', VALUE_OPTIONAL),
                'warnings' => new external_warnings(),
            )
        );
    }

    /**
     * Describes the parameters for get_combined_review_options.
     *
     * @return external_function_parameters
     * @since Moodle 3.1
     */
    public static function get_combined_review_options_parameters() {
        return new external_function_parameters (
            array(
                'ddtaquizid' => new external_value(PARAM_INT, 'ddtaquiz instance id'),
                'userid' => new external_value(PARAM_INT, 'user id (empty for current user)', VALUE_DEFAULT, 0),

            )
        );
    }

    /**
     * Combines the review options from a number of different ddtaquiz attempts.
     *
     * @param int $ddtaquizid ddtaquiz instance id
     * @param int $userid user id (empty for current user)
     * @return array of warnings and the review options
     * @since Moodle 3.1
     */
    public static function get_combined_review_options($ddtaquizid, $userid = 0) {
        global $DB, $USER;

        $warnings = array();

        $params = array(
            'ddtaquizid' => $ddtaquizid,
            'userid' => $userid,
        );
        $params = self::validate_parameters(self::get_combined_review_options_parameters(), $params);

        list($ddtaquiz, $course, $cm, $context) = self::validate_ddtaquiz($params['ddtaquizid']);

        // Default value for userid.
        if (empty($params['userid'])) {
            $params['userid'] = $USER->id;
        }

        $user = core_user::get_user($params['userid'], '*', MUST_EXIST);
        core_user::require_active_user($user);

        // Extra checks so only users with permissions can view other users attempts.
        if ($USER->id != $user->id) {
            require_capability('mod/ddtaquiz:viewreports', $context);
        }

        $attempts = ddtaquiz_get_user_attempts($ddtaquiz->id, $user->id, 'all', true);

        $result = array();
        $result['someoptions'] = [];
        $result['alloptions'] = [];

        list($someoptions, $alloptions) = ddtaquiz_get_combined_reviewoptions($ddtaquiz, $attempts);

        foreach (array('someoptions', 'alloptions') as $typeofoption) {
            foreach ($$typeofoption as $key => $value) {
                $result[$typeofoption][] = array(
                    "name" => $key,
                    "value" => (!empty($value)) ? $value : 0
                );
            }
        }

        $result['warnings'] = $warnings;
        return $result;
    }

    /**
     * Describes the get_combined_review_options return value.
     *
     * @return external_single_structure
     * @since Moodle 3.1
     */
    public static function get_combined_review_options_returns() {
        return new external_single_structure(
            array(
                'someoptions' => new external_multiple_structure(
                    new external_single_structure(
                        array(
                            'name' => new external_value(PARAM_ALPHANUMEXT, 'option name'),
                            'value' => new external_value(PARAM_INT, 'option value'),
                        )
                    )
                ),
                'alloptions' => new external_multiple_structure(
                    new external_single_structure(
                        array(
                            'name' => new external_value(PARAM_ALPHANUMEXT, 'option name'),
                            'value' => new external_value(PARAM_INT, 'option value'),
                        )
                    )
                ),
                'warnings' => new external_warnings(),
            )
        );
    }

    /**
     * Describes the parameters for start_attempt.
     *
     * @return external_function_parameters
     * @since Moodle 3.1
     */
    public static function start_attempt_parameters() {
        return new external_function_parameters (
            array(
                'ddtaquizid' => new external_value(PARAM_INT, 'ddtaquiz instance id'),
                'preflightdata' => new external_multiple_structure(
                    new external_single_structure(
                        array(
                            'name' => new external_value(PARAM_ALPHANUMEXT, 'data name'),
                            'value' => new external_value(PARAM_RAW, 'data value'),
                        )
                    ), 'Preflight required data (like passwords)', VALUE_DEFAULT, array()
                ),
                'forcenew' => new external_value(PARAM_BOOL, 'Whether to force a new attempt or not.', VALUE_DEFAULT, false),

            )
        );
    }

    /**
     * Starts a new attempt at a ddtaquiz.
     *
     * @param int $ddtaquizid ddtaquiz instance id
     * @param array $preflightdata preflight required data (like passwords)
     * @param bool $forcenew Whether to force a new attempt or not.
     * @return array of warnings and the attempt basic data
     * @since Moodle 3.1
     * @throws moodle_ddtaquiz_exception
     */
    public static function start_attempt($ddtaquizid, $preflightdata = array(), $forcenew = false) {
        global $DB, $USER;

        $warnings = array();
        $attempt = array();

        $params = array(
            'ddtaquizid' => $ddtaquizid,
            'preflightdata' => $preflightdata,
            'forcenew' => $forcenew,
        );
        $params = self::validate_parameters(self::start_attempt_parameters(), $params);
        $forcenew = $params['forcenew'];

        list($ddtaquiz, $course, $cm, $context) = self::validate_ddtaquiz($params['ddtaquizid']);

        $ddtaquizobj = ddtaquiz::create($cm->instance, $USER->id);

        // Check questions.
        if (!$ddtaquizobj->has_questions()) {
            throw new moodle_ddtaquiz_exception($ddtaquizobj, 'noquestionsfound');
        }

        // Create an object to manage all the other (non-roles) access rules.
        $timenow = time();
        $accessmanager = $ddtaquizobj->get_access_manager($timenow);

        // Validate permissions for creating a new attempt and start a new preview attempt if required.
        list($currentattemptid, $attemptnumber, $lastattempt, $messages, $page) =
            ddtaquiz_validate_new_attempt($ddtaquizobj, $accessmanager, $forcenew, -1, false);

        // Check access.
        if (!$ddtaquizobj->is_preview_user() && $messages) {
            // Create warnings with the exact messages.
            foreach ($messages as $message) {
                $warnings[] = array(
                    'item' => 'ddtaquiz',
                    'itemid' => $ddtaquiz->id,
                    'warningcode' => '1',
                    'message' => clean_text($message, PARAM_TEXT)
                );
            }
        } else {
            if ($accessmanager->is_preflight_check_required($currentattemptid)) {
                // Need to do some checks before allowing the user to continue.

                $provideddata = array();
                foreach ($params['preflightdata'] as $data) {
                    $provideddata[$data['name']] = $data['value'];
                }

                $errors = $accessmanager->validate_preflight_check($provideddata, [], $currentattemptid);

                if (!empty($errors)) {
                    throw new moodle_ddtaquiz_exception($ddtaquizobj, array_shift($errors));
                }

                // Pre-flight check passed.
                $accessmanager->notify_preflight_check_passed($currentattemptid);
            }

            if ($currentattemptid) {
                if ($lastattempt->state == ddtaquiz_attempt::OVERDUE) {
                    throw new moodle_ddtaquiz_exception($ddtaquizobj, 'stateoverdue');
                } else {
                    throw new moodle_ddtaquiz_exception($ddtaquizobj, 'attemptstillinprogress');
                }
            }
            $offlineattempt = WS_SERVER ? true : false;
            $attempt = ddtaquiz_prepare_and_start_new_attempt($ddtaquizobj, $attemptnumber, $lastattempt, $offlineattempt);
        }

        $result = array();
        $result['attempt'] = $attempt;
        $result['warnings'] = $warnings;
        return $result;
    }

    /**
     * Describes the start_attempt return value.
     *
     * @return external_single_structure
     * @since Moodle 3.1
     */
    public static function start_attempt_returns() {
        return new external_single_structure(
            array(
                'attempt' => self::attempt_structure(),
                'warnings' => new external_warnings(),
            )
        );
    }

    /**
     * Utility function for validating a given attempt
     *
     * @param  array $params array of parameters including the attemptid and preflight data
     * @param  bool $checkaccessrules whether to check the ddtaquiz access rules or not
     * @param  bool $failifoverdue whether to return error if the attempt is overdue
     * @return  array containing the attempt object and access messages
     * @throws moodle_ddtaquiz_exception
     * @since  Moodle 3.1
     */
    protected static function validate_attempt($params, $checkaccessrules = true, $failifoverdue = true) {
        global $USER;

        $attemptobj = ddtaquiz_attempt::create($params['attemptid']);

        $context = context_module::instance($attemptobj->get_cm()->id);
        self::validate_context($context);

        // Check that this attempt belongs to this user.
        if ($attemptobj->get_userid() != $USER->id) {
            throw new moodle_ddtaquiz_exception($attemptobj->get_ddtaquizobj(), 'notyourattempt');
        }

        // General capabilities check.
        $ispreviewuser = $attemptobj->is_preview_user();
        if (!$ispreviewuser) {
            $attemptobj->require_capability('mod/ddtaquiz:attempt');
        }

        // Check the access rules.
        $accessmanager = $attemptobj->get_access_manager(time());
        $messages = array();
        if ($checkaccessrules) {
            // If the attempt is now overdue, or abandoned, deal with that.
            $attemptobj->handle_if_time_expired(time(), true);

            $messages = $accessmanager->prevent_access();
            if (!$ispreviewuser && $messages) {
                throw new moodle_ddtaquiz_exception($attemptobj->get_ddtaquizobj(), 'attempterror');
            }
        }

        // Attempt closed?.
        if ($attemptobj->is_finished()) {
            throw new moodle_ddtaquiz_exception($attemptobj->get_ddtaquizobj(), 'attemptalreadyclosed');
        } else if ($failifoverdue && $attemptobj->get_state() == ddtaquiz_attempt::OVERDUE) {
            throw new moodle_ddtaquiz_exception($attemptobj->get_ddtaquizobj(), 'stateoverdue');
        }

        // User submitted data (like the ddtaquiz password).
        if ($accessmanager->is_preflight_check_required($attemptobj->get_attemptid())) {
            $provideddata = array();
            foreach ($params['preflightdata'] as $data) {
                $provideddata[$data['name']] = $data['value'];
            }

            $errors = $accessmanager->validate_preflight_check($provideddata, [], $params['attemptid']);
            if (!empty($errors)) {
                throw new moodle_ddtaquiz_exception($attemptobj->get_ddtaquizobj(), array_shift($errors));
            }
            // Pre-flight check passed.
            $accessmanager->notify_preflight_check_passed($params['attemptid']);
        }

        if (isset($params['page'])) {
            // Check if the page is out of range.
            if ($params['page'] != $attemptobj->force_page_number_into_range($params['page'])) {
                throw new moodle_ddtaquiz_exception($attemptobj->get_ddtaquizobj(), 'Invalid page number');
            }

            // Prevent out of sequence access.
            if (!$attemptobj->check_page_access($params['page'])) {
                throw new moodle_ddtaquiz_exception($attemptobj->get_ddtaquizobj(), 'Out of sequence access');
            }

            // Check slots.
            $slots = $attemptobj->get_slots($params['page']);

            if (empty($slots)) {
                throw new moodle_ddtaquiz_exception($attemptobj->get_ddtaquizobj(), 'noquestionsfound');
            }
        }

        return array($attemptobj, $messages);
    }

    /**
     * Describes a single question structure.
     *
     * @return external_single_structure the question data. Some fields may not be returned depending on the ddtaquiz display settings.
     * @since  Moodle 3.1
     * @since Moodle 3.2 blockedbyprevious parameter added.
     */
    private static function question_structure() {
        return new external_single_structure(
            array(
                'slot' => new external_value(PARAM_INT, 'slot number'),
                'type' => new external_value(PARAM_ALPHANUMEXT, 'question type, i.e: multichoice'),
                'page' => new external_value(PARAM_INT, 'page of the ddtaquiz this question appears on'),
                'html' => new external_value(PARAM_RAW, 'the question rendered'),
                'sequencecheck' => new external_value(PARAM_INT, 'the number of real steps in this attempt', VALUE_OPTIONAL),
                'lastactiontime' => new external_value(PARAM_INT, 'the timestamp of the most recent step in this question attempt',
                                                        VALUE_OPTIONAL),
                'hasautosavedstep' => new external_value(PARAM_BOOL, 'whether this question attempt has autosaved data',
                                                            VALUE_OPTIONAL),
                'flagged' => new external_value(PARAM_BOOL, 'whether the question is flagged or not'),
                'number' => new external_value(PARAM_INT, 'question ordering number in the ddtaquiz', VALUE_OPTIONAL),
                'state' => new external_value(PARAM_ALPHA, 'the state where the question is in.
                    It will not be returned if the user cannot see it due to the ddtaquiz display correctness settings.',
                    VALUE_OPTIONAL),
                'status' => new external_value(PARAM_RAW, 'current formatted state of the question', VALUE_OPTIONAL),
                'blockedbyprevious' => new external_value(PARAM_BOOL, 'whether the question is blocked by the previous question',
                    VALUE_OPTIONAL),
                'mark' => new external_value(PARAM_RAW, 'the mark awarded.
                    It will be returned only if the user is allowed to see it.', VALUE_OPTIONAL),
                'maxmark' => new external_value(PARAM_FLOAT, 'the maximum mark possible for this question attempt.
                    It will be returned only if the user is allowed to see it.', VALUE_OPTIONAL),
            ),
            'The question data. Some fields may not be returned depending on the ddtaquiz display settings.'
        );
    }

    /**
     * Return questions information for a given attempt.
     *
     * @param  ddtaquiz_attempt  $attemptobj  the ddtaquiz attempt object
     * @param  bool  $review  whether if we are in review mode or not
     * @param  mixed  $page  string 'all' or integer page number
     * @return array array of questions including data
     */
    private static function get_attempt_questions_data(ddtaquiz_attempt $attemptobj, $review, $page = 'all') {
        global $PAGE;

        $questions = array();
        $contextid = $attemptobj->get_ddtaquizobj()->get_context()->id;
        $displayoptions = $attemptobj->get_display_options($review);
        $renderer = $PAGE->get_renderer('mod_ddtaquiz');

        foreach ($attemptobj->get_slots($page) as $slot) {

            $question = array(
                'slot' => $slot,
                'type' => $attemptobj->get_question_type_name($slot),
                'page' => $attemptobj->get_question_page($slot),
                'flagged' => $attemptobj->is_question_flagged($slot),
                'html' => $attemptobj->render_question($slot, $review, $renderer) . $PAGE->requires->get_end_code(),
                'sequencecheck' => $attemptobj->get_question_attempt($slot)->get_sequence_check_count(),
                'lastactiontime' => $attemptobj->get_question_attempt($slot)->get_last_step()->get_timecreated(),
                'hasautosavedstep' => $attemptobj->get_question_attempt($slot)->has_autosaved_step()
            );

            if ($attemptobj->is_real_question($slot)) {
                $question['number'] = $attemptobj->get_question_number($slot);
                $showcorrectness = $displayoptions->correctness && $attemptobj->get_question_attempt($slot)->has_marks();
                if ($showcorrectness) {
                    $question['state'] = (string) $attemptobj->get_question_state($slot);
                }
                $question['status'] = $attemptobj->get_question_status($slot, $displayoptions->correctness);
                $question['blockedbyprevious'] = $attemptobj->is_blocked_by_previous_question($slot);
            }
            if ($displayoptions->marks >= question_display_options::MAX_ONLY) {
                $question['maxmark'] = $attemptobj->get_question_attempt($slot)->get_max_mark();
            }
            if ($displayoptions->marks >= question_display_options::MARK_AND_MAX) {
                $question['mark'] = $attemptobj->get_question_mark($slot);
            }

            $questions[] = $question;
        }
        return $questions;
    }

    /**
     * Describes the parameters for get_attempt_data.
     *
     * @return external_function_parameters
     * @since Moodle 3.1
     */
    public static function get_attempt_data_parameters() {
        return new external_function_parameters (
            array(
                'attemptid' => new external_value(PARAM_INT, 'attempt id'),
                'page' => new external_value(PARAM_INT, 'page number'),
                'preflightdata' => new external_multiple_structure(
                    new external_single_structure(
                        array(
                            'name' => new external_value(PARAM_ALPHANUMEXT, 'data name'),
                            'value' => new external_value(PARAM_RAW, 'data value'),
                        )
                    ), 'Preflight required data (like passwords)', VALUE_DEFAULT, array()
                )
            )
        );
    }

    /**
     * Returns information for the given attempt page for a ddtaquiz attempt in progress.
     *
     * @param int $attemptid attempt id
     * @param int $page page number
     * @param array $preflightdata preflight required data (like passwords)
     * @return array of warnings and the attempt data, next page, message and questions
     * @since Moodle 3.1
     * @throws moodle_ddtaquiz_exceptions
     */
    public static function get_attempt_data($attemptid, $page, $preflightdata = array()) {

        $warnings = array();

        $params = array(
            'attemptid' => $attemptid,
            'page' => $page,
            'preflightdata' => $preflightdata,
        );
        $params = self::validate_parameters(self::get_attempt_data_parameters(), $params);

        list($attemptobj, $messages) = self::validate_attempt($params);

        if ($attemptobj->is_last_page($params['page'])) {
            $nextpage = -1;
        } else {
            $nextpage = $params['page'] + 1;
        }

        $result = array();
        $result['attempt'] = $attemptobj->get_attempt();
        $result['messages'] = $messages;
        $result['nextpage'] = $nextpage;
        $result['warnings'] = $warnings;
        $result['questions'] = self::get_attempt_questions_data($attemptobj, false, $params['page']);

        return $result;
    }

    /**
     * Describes the get_attempt_data return value.
     *
     * @return external_single_structure
     * @since Moodle 3.1
     */
    public static function get_attempt_data_returns() {
        return new external_single_structure(
            array(
                'attempt' => self::attempt_structure(),
                'messages' => new external_multiple_structure(
                    new external_value(PARAM_TEXT, 'access message'),
                    'access messages, will only be returned for users with mod/ddtaquiz:preview capability,
                    for other users this method will throw an exception if there are messages'),
                'nextpage' => new external_value(PARAM_INT, 'next page number'),
                'questions' => new external_multiple_structure(self::question_structure()),
                'warnings' => new external_warnings(),
            )
        );
    }

    /**
     * Describes the parameters for get_attempt_summary.
     *
     * @return external_function_parameters
     * @since Moodle 3.1
     */
    public static function get_attempt_summary_parameters() {
        return new external_function_parameters (
            array(
                'attemptid' => new external_value(PARAM_INT, 'attempt id'),
                'preflightdata' => new external_multiple_structure(
                    new external_single_structure(
                        array(
                            'name' => new external_value(PARAM_ALPHANUMEXT, 'data name'),
                            'value' => new external_value(PARAM_RAW, 'data value'),
                        )
                    ), 'Preflight required data (like passwords)', VALUE_DEFAULT, array()
                )
            )
        );
    }

    /**
     * Returns a summary of a ddtaquiz attempt before it is submitted.
     *
     * @param int $attemptid attempt id
     * @param int $preflightdata preflight required data (like passwords)
     * @return array of warnings and the attempt summary data for each question
     * @since Moodle 3.1
     */
    public static function get_attempt_summary($attemptid, $preflightdata = array()) {

        $warnings = array();

        $params = array(
            'attemptid' => $attemptid,
            'preflightdata' => $preflightdata,
        );
        $params = self::validate_parameters(self::get_attempt_summary_parameters(), $params);

        list($attemptobj, $messages) = self::validate_attempt($params, true, false);

        $result = array();
        $result['warnings'] = $warnings;
        $result['questions'] = self::get_attempt_questions_data($attemptobj, false, 'all');

        return $result;
    }

    /**
     * Describes the get_attempt_summary return value.
     *
     * @return external_single_structure
     * @since Moodle 3.1
     */
    public static function get_attempt_summary_returns() {
        return new external_single_structure(
            array(
                'questions' => new external_multiple_structure(self::question_structure()),
                'warnings' => new external_warnings(),
            )
        );
    }

    /**
     * Describes the parameters for save_attempt.
     *
     * @return external_function_parameters
     * @since Moodle 3.1
     */
    public static function save_attempt_parameters() {
        return new external_function_parameters (
            array(
                'attemptid' => new external_value(PARAM_INT, 'attempt id'),
                'data' => new external_multiple_structure(
                    new external_single_structure(
                        array(
                            'name' => new external_value(PARAM_RAW, 'data name'),
                            'value' => new external_value(PARAM_RAW, 'data value'),
                        )
                    ), 'the data to be saved'
                ),
                'preflightdata' => new external_multiple_structure(
                    new external_single_structure(
                        array(
                            'name' => new external_value(PARAM_ALPHANUMEXT, 'data name'),
                            'value' => new external_value(PARAM_RAW, 'data value'),
                        )
                    ), 'Preflight required data (like passwords)', VALUE_DEFAULT, array()
                )
            )
        );
    }

    /**
     * Processes save requests during the ddtaquiz. This function is intended for the ddtaquiz auto-save feature.
     *
     * @param int $attemptid attempt id
     * @param array $data the data to be saved
     * @param  array $preflightdata preflight required data (like passwords)
     * @return array of warnings and execution result
     * @since Moodle 3.1
     */
    public static function save_attempt($attemptid, $data, $preflightdata = array()) {
        global $DB;

        $warnings = array();

        $params = array(
            'attemptid' => $attemptid,
            'data' => $data,
            'preflightdata' => $preflightdata,
        );
        $params = self::validate_parameters(self::save_attempt_parameters(), $params);

        // Add a page, required by validate_attempt.
        list($attemptobj, $messages) = self::validate_attempt($params);

        $transaction = $DB->start_delegated_transaction();
        // Create the $_POST object required by the question engine.
        $_POST = array();
        foreach ($data as $element) {
            $_POST[$element['name']] = $element['value'];
        }
        $timenow = time();
        // Update the timemodifiedoffline field.
        $attemptobj->set_offline_modified_time($timenow);
        $attemptobj->process_auto_save($timenow);
        $transaction->allow_commit();

        $result = array();
        $result['status'] = true;
        $result['warnings'] = $warnings;
        return $result;
    }

    /**
     * Describes the save_attempt return value.
     *
     * @return external_single_structure
     * @since Moodle 3.1
     */
    public static function save_attempt_returns() {
        return new external_single_structure(
            array(
                'status' => new external_value(PARAM_BOOL, 'status: true if success'),
                'warnings' => new external_warnings(),
            )
        );
    }

    /**
     * Describes the parameters for process_attempt.
     *
     * @return external_function_parameters
     * @since Moodle 3.1
     */
    public static function process_attempt_parameters() {
        return new external_function_parameters (
            array(
                'attemptid' => new external_value(PARAM_INT, 'attempt id'),
                'data' => new external_multiple_structure(
                    new external_single_structure(
                        array(
                            'name' => new external_value(PARAM_RAW, 'data name'),
                            'value' => new external_value(PARAM_RAW, 'data value'),
                        )
                    ),
                    'the data to be saved', VALUE_DEFAULT, array()
                ),
                'finishattempt' => new external_value(PARAM_BOOL, 'whether to finish or not the attempt', VALUE_DEFAULT, false),
                'timeup' => new external_value(PARAM_BOOL, 'whether the WS was called by a timer when the time is up',
                                                VALUE_DEFAULT, false),
                'preflightdata' => new external_multiple_structure(
                    new external_single_structure(
                        array(
                            'name' => new external_value(PARAM_ALPHANUMEXT, 'data name'),
                            'value' => new external_value(PARAM_RAW, 'data value'),
                        )
                    ), 'Preflight required data (like passwords)', VALUE_DEFAULT, array()
                )
            )
        );
    }

    /**
     * Process responses during an attempt at a ddtaquiz and also deals with attempts finishing.
     *
     * @param int $attemptid attempt id
     * @param array $data the data to be saved
     * @param bool $finishattempt whether to finish or not the attempt
     * @param bool $timeup whether the WS was called by a timer when the time is up
     * @param array $preflightdata preflight required data (like passwords)
     * @return array of warnings and the attempt state after the processing
     * @since Moodle 3.1
     */
    public static function process_attempt($attemptid, $data, $finishattempt = false, $timeup = false, $preflightdata = array()) {

        $warnings = array();

        $params = array(
            'attemptid' => $attemptid,
            'data' => $data,
            'finishattempt' => $finishattempt,
            'timeup' => $timeup,
            'preflightdata' => $preflightdata,
        );
        $params = self::validate_parameters(self::process_attempt_parameters(), $params);

        // Do not check access manager rules.
        list($attemptobj, $messages) = self::validate_attempt($params, false);

        // Create the $_POST object required by the question engine.
        $_POST = array();
        foreach ($params['data'] as $element) {
            $_POST[$element['name']] = $element['value'];
        }
        $timenow = time();
        $finishattempt = $params['finishattempt'];
        $timeup = $params['timeup'];

        $result = array();
        // Update the timemodifiedoffline field.
        $attemptobj->set_offline_modified_time($timenow);
        $result['state'] = $attemptobj->process_attempt($timenow, $finishattempt, $timeup, 0);

        $result['warnings'] = $warnings;
        return $result;
    }

    /**
     * Describes the process_attempt return value.
     *
     * @return external_single_structure
     * @since Moodle 3.1
     */
    public static function process_attempt_returns() {
        return new external_single_structure(
            array(
                'state' => new external_value(PARAM_ALPHANUMEXT, 'state: the new attempt state:
                                                                    inprogress, finished, overdue, abandoned'),
                'warnings' => new external_warnings(),
            )
        );
    }

    /**
     * Validate an attempt finished for review. The attempt would be reviewed by a user or a teacher.
     *
     * @param  array $params Array of parameters including the attemptid
     * @return  array containing the attempt object and display options
     * @since  Moodle 3.1
     * @throws  moodle_exception
     * @throws  moodle_ddtaquiz_exception
     */
    protected static function validate_attempt_review($params) {

        $attemptobj = ddtaquiz_attempt::create($params['attemptid']);
        $attemptobj->check_review_capability();

        $displayoptions = $attemptobj->get_display_options(true);
        if ($attemptobj->is_own_attempt()) {
            if (!$attemptobj->is_finished()) {
                throw new moodle_ddtaquiz_exception($attemptobj->get_ddtaquizobj(), 'attemptclosed');
            } else if (!$displayoptions->attempt) {
                throw new moodle_exception($attemptobj->cannot_review_message());
            }
        } else if (!$attemptobj->is_review_allowed()) {
            throw new moodle_ddtaquiz_exception($attemptobj->get_ddtaquizobj(), 'noreviewattempt');
        }
        return array($attemptobj, $displayoptions);
    }

    /**
     * Describes the parameters for get_attempt_review.
     *
     * @return external_function_parameters
     * @since Moodle 3.1
     */
    public static function get_attempt_review_parameters() {
        return new external_function_parameters (
            array(
                'attemptid' => new external_value(PARAM_INT, 'attempt id'),
                'page' => new external_value(PARAM_INT, 'page number, empty for all the questions in all the pages',
                                                VALUE_DEFAULT, -1),
            )
        );
    }

    /**
     * Returns review information for the given finished attempt, can be used by users or teachers.
     *
     * @param int $attemptid attempt id
     * @param int $page page number, empty for all the questions in all the pages
     * @return array of warnings and the attempt data, feedback and questions
     * @since Moodle 3.1
     * @throws  moodle_exception
     * @throws  moodle_ddtaquiz_exception
     */
    public static function get_attempt_review($attemptid, $page = -1) {
        global $PAGE;

        $warnings = array();

        $params = array(
            'attemptid' => $attemptid,
            'page' => $page,
        );
        $params = self::validate_parameters(self::get_attempt_review_parameters(), $params);

        list($attemptobj, $displayoptions) = self::validate_attempt_review($params);

        if ($params['page'] !== -1) {
            $page = $attemptobj->force_page_number_into_range($params['page']);
        } else {
            $page = 'all';
        }

        // Prepare the output.
        $result = array();
        $result['attempt'] = $attemptobj->get_attempt();
        $result['questions'] = self::get_attempt_questions_data($attemptobj, true, $page, true);

        $result['additionaldata'] = array();
        // Summary data (from behaviours).
        $summarydata = $attemptobj->get_additional_summary_data($displayoptions);
        foreach ($summarydata as $key => $data) {
            // This text does not need formatting (no need for external_format_[string|text]).
            $result['additionaldata'][] = array(
                'id' => $key,
                'title' => $data['title'], $attemptobj->get_ddtaquizobj()->get_context()->id,
                'content' => $data['content'],
            );
        }

        // Feedback if there is any, and the user is allowed to see it now.
        $grade = ddtaquiz_rescale_grade($attemptobj->get_attempt()->sumgrades, $attemptobj->get_ddtaquiz(), false);

        $feedback = $attemptobj->get_overall_feedback($grade);
        if ($displayoptions->overallfeedback && $feedback) {
            $result['additionaldata'][] = array(
                'id' => 'feedback',
                'title' => get_string('feedback', 'ddtaquiz'),
                'content' => $feedback,
            );
        }

        $result['grade'] = $grade;
        $result['warnings'] = $warnings;
        return $result;
    }

    /**
     * Describes the get_attempt_review return value.
     *
     * @return external_single_structure
     * @since Moodle 3.1
     */
    public static function get_attempt_review_returns() {
        return new external_single_structure(
            array(
                'grade' => new external_value(PARAM_RAW, 'grade for the ddtaquiz (or empty or "notyetgraded")'),
                'attempt' => self::attempt_structure(),
                'additionaldata' => new external_multiple_structure(
                    new external_single_structure(
                        array(
                            'id' => new external_value(PARAM_ALPHANUMEXT, 'id of the data'),
                            'title' => new external_value(PARAM_TEXT, 'data title'),
                            'content' => new external_value(PARAM_RAW, 'data content'),
                        )
                    )
                ),
                'questions' => new external_multiple_structure(self::question_structure()),
                'warnings' => new external_warnings(),
            )
        );
    }

    /**
     * Describes the parameters for view_attempt.
     *
     * @return external_function_parameters
     * @since Moodle 3.1
     */
    public static function view_attempt_parameters() {
        return new external_function_parameters (
            array(
                'attemptid' => new external_value(PARAM_INT, 'attempt id'),
                'page' => new external_value(PARAM_INT, 'page number'),
                'preflightdata' => new external_multiple_structure(
                    new external_single_structure(
                        array(
                            'name' => new external_value(PARAM_ALPHANUMEXT, 'data name'),
                            'value' => new external_value(PARAM_RAW, 'data value'),
                        )
                    ), 'Preflight required data (like passwords)', VALUE_DEFAULT, array()
                )
            )
        );
    }

    /**
     * Trigger the attempt viewed event.
     *
     * @param int $attemptid attempt id
     * @param int $page page number
     * @param array $preflightdata preflight required data (like passwords)
     * @return array of warnings and status result
     * @since Moodle 3.1
     */
    public static function view_attempt($attemptid, $page, $preflightdata = array()) {

        $warnings = array();

        $params = array(
            'attemptid' => $attemptid,
            'page' => $page,
            'preflightdata' => $preflightdata,
        );
        $params = self::validate_parameters(self::view_attempt_parameters(), $params);
        list($attemptobj, $messages) = self::validate_attempt($params);

        // Log action.
        $attemptobj->fire_attempt_viewed_event();

        // Update attempt page, throwing an exception if $page is not valid.
        if (!$attemptobj->set_currentpage($params['page'])) {
            throw new moodle_ddtaquiz_exception($attemptobj->get_ddtaquizobj(), 'Out of sequence access');
        }

        $result = array();
        $result['status'] = true;
        $result['warnings'] = $warnings;
        return $result;
    }

    /**
     * Describes the view_attempt return value.
     *
     * @return external_single_structure
     * @since Moodle 3.1
     */
    public static function view_attempt_returns() {
        return new external_single_structure(
            array(
                'status' => new external_value(PARAM_BOOL, 'status: true if success'),
                'warnings' => new external_warnings(),
            )
        );
    }

    /**
     * Describes the parameters for view_attempt_summary.
     *
     * @return external_function_parameters
     * @since Moodle 3.1
     */
    public static function view_attempt_summary_parameters() {
        return new external_function_parameters (
            array(
                'attemptid' => new external_value(PARAM_INT, 'attempt id'),
                'preflightdata' => new external_multiple_structure(
                    new external_single_structure(
                        array(
                            'name' => new external_value(PARAM_ALPHANUMEXT, 'data name'),
                            'value' => new external_value(PARAM_RAW, 'data value'),
                        )
                    ), 'Preflight required data (like passwords)', VALUE_DEFAULT, array()
                )
            )
        );
    }

    /**
     * Trigger the attempt summary viewed event.
     *
     * @param int $attemptid attempt id
     * @param array $preflightdata preflight required data (like passwords)
     * @return array of warnings and status result
     * @since Moodle 3.1
     */
    public static function view_attempt_summary($attemptid, $preflightdata = array()) {

        $warnings = array();

        $params = array(
            'attemptid' => $attemptid,
            'preflightdata' => $preflightdata,
        );
        $params = self::validate_parameters(self::view_attempt_summary_parameters(), $params);
        list($attemptobj, $messages) = self::validate_attempt($params);

        // Log action.
        $attemptobj->fire_attempt_summary_viewed_event();

        $result = array();
        $result['status'] = true;
        $result['warnings'] = $warnings;
        return $result;
    }

    /**
     * Describes the view_attempt_summary return value.
     *
     * @return external_single_structure
     * @since Moodle 3.1
     */
    public static function view_attempt_summary_returns() {
        return new external_single_structure(
            array(
                'status' => new external_value(PARAM_BOOL, 'status: true if success'),
                'warnings' => new external_warnings(),
            )
        );
    }

    /**
     * Describes the parameters for view_attempt_review.
     *
     * @return external_function_parameters
     * @since Moodle 3.1
     */
    public static function view_attempt_review_parameters() {
        return new external_function_parameters (
            array(
                'attemptid' => new external_value(PARAM_INT, 'attempt id'),
            )
        );
    }

    /**
     * Trigger the attempt reviewed event.
     *
     * @param int $attemptid attempt id
     * @return array of warnings and status result
     * @since Moodle 3.1
     */
    public static function view_attempt_review($attemptid) {

        $warnings = array();

        $params = array(
            'attemptid' => $attemptid,
        );
        $params = self::validate_parameters(self::view_attempt_review_parameters(), $params);
        list($attemptobj, $displayoptions) = self::validate_attempt_review($params);

        // Log action.
        $attemptobj->fire_attempt_reviewed_event();

        $result = array();
        $result['status'] = true;
        $result['warnings'] = $warnings;
        return $result;
    }

    /**
     * Describes the view_attempt_review return value.
     *
     * @return external_single_structure
     * @since Moodle 3.1
     */
    public static function view_attempt_review_returns() {
        return new external_single_structure(
            array(
                'status' => new external_value(PARAM_BOOL, 'status: true if success'),
                'warnings' => new external_warnings(),
            )
        );
    }

    /**
     * Describes the parameters for view_ddtaquiz.
     *
     * @return external_function_parameters
     * @since Moodle 3.1
     */
    public static function get_ddtaquiz_feedback_for_grade_parameters() {
        return new external_function_parameters (
            array(
                'ddtaquizid' => new external_value(PARAM_INT, 'ddtaquiz instance id'),
                'grade' => new external_value(PARAM_FLOAT, 'the grade to check'),
            )
        );
    }

    /**
     * Get the feedback text that should be show to a student who got the given grade in the given ddtaquiz.
     *
     * @param int $ddtaquizid ddtaquiz instance id
     * @param float $grade the grade to check
     * @return array of warnings and status result
     * @since Moodle 3.1
     * @throws moodle_exception
     */
    public static function get_ddtaquiz_feedback_for_grade($ddtaquizid, $grade) {
        global $DB;

        $params = array(
            'ddtaquizid' => $ddtaquizid,
            'grade' => $grade,
        );
        $params = self::validate_parameters(self::get_ddtaquiz_feedback_for_grade_parameters(), $params);
        $warnings = array();

        list($ddtaquiz, $course, $cm, $context) = self::validate_ddtaquiz($params['ddtaquizid']);

        $result = array();
        $result['feedbacktext'] = '';
        $result['feedbacktextformat'] = FORMAT_MOODLE;

        $feedback = ddtaquiz_feedback_record_for_grade($params['grade'], $ddtaquiz);
        if (!empty($feedback->feedbacktext)) {
            list($text, $format) = external_format_text($feedback->feedbacktext, $feedback->feedbacktextformat, $context->id,
                                                        'mod_ddtaquiz', 'feedback', $feedback->id);
            $result['feedbacktext'] = $text;
            $result['feedbacktextformat'] = $format;
            $feedbackinlinefiles = external_util::get_area_files($context->id, 'mod_ddtaquiz', 'feedback', $feedback->id);
            if (!empty($feedbackinlinefiles)) {
                $result['feedbackinlinefiles'] = $feedbackinlinefiles;
            }
        }

        $result['warnings'] = $warnings;
        return $result;
    }

    /**
     * Describes the get_ddtaquiz_feedback_for_grade return value.
     *
     * @return external_single_structure
     * @since Moodle 3.1
     */
    public static function get_ddtaquiz_feedback_for_grade_returns() {
        return new external_single_structure(
            array(
                'feedbacktext' => new external_value(PARAM_RAW, 'the comment that corresponds to this grade (empty for none)'),
                'feedbacktextformat' => new external_format_value('feedbacktext', VALUE_OPTIONAL),
                'feedbackinlinefiles' => new external_files('feedback inline files', VALUE_OPTIONAL),
                'warnings' => new external_warnings(),
            )
        );
    }

    /**
     * Describes the parameters for get_ddtaquiz_access_information.
     *
     * @return external_function_parameters
     * @since Moodle 3.1
     */
    public static function get_ddtaquiz_access_information_parameters() {
        return new external_function_parameters (
            array(
                'ddtaquizid' => new external_value(PARAM_INT, 'ddtaquiz instance id')
            )
        );
    }

    /**
     * Return access information for a given ddtaquiz.
     *
     * @param int $ddtaquizid ddtaquiz instance id
     * @return array of warnings and the access information
     * @since Moodle 3.1
     * @throws  moodle_ddtaquiz_exception
     */
    public static function get_ddtaquiz_access_information($ddtaquizid) {
        global $DB, $USER;

        $warnings = array();

        $params = array(
            'ddtaquizid' => $ddtaquizid
        );
        $params = self::validate_parameters(self::get_ddtaquiz_access_information_parameters(), $params);

        list($ddtaquiz, $course, $cm, $context) = self::validate_ddtaquiz($params['ddtaquizid']);

        $result = array();
        // Capabilities first.
        $result['canattempt'] = has_capability('mod/ddtaquiz:attempt', $context);;
        $result['canmanage'] = has_capability('mod/ddtaquiz:manage', $context);;
        $result['canpreview'] = has_capability('mod/ddtaquiz:preview', $context);;
        $result['canreviewmyattempts'] = has_capability('mod/ddtaquiz:reviewmyattempts', $context);;
        $result['canviewreports'] = has_capability('mod/ddtaquiz:viewreports', $context);;

        // Access manager now.
        $ddtaquizobj = ddtaquiz::create($cm->instance, $USER->id);
        $ignoretimelimits = has_capability('mod/ddtaquiz:ignoretimelimits', $context, null, false);
        $timenow = time();
        $accessmanager = new ddtaquiz_access_manager($ddtaquizobj, $timenow, $ignoretimelimits);

        $result['accessrules'] = $accessmanager->describe_rules();
        $result['activerulenames'] = $accessmanager->get_active_rule_names();
        $result['preventaccessreasons'] = $accessmanager->prevent_access();

        $result['warnings'] = $warnings;
        return $result;
    }

    /**
     * Describes the get_ddtaquiz_access_information return value.
     *
     * @return external_single_structure
     * @since Moodle 3.1
     */
    public static function get_ddtaquiz_access_information_returns() {
        return new external_single_structure(
            array(
                'canattempt' => new external_value(PARAM_BOOL, 'Whether the user can do the ddtaquiz or not.'),
                'canmanage' => new external_value(PARAM_BOOL, 'Whether the user can edit the ddtaquiz settings or not.'),
                'canpreview' => new external_value(PARAM_BOOL, 'Whether the user can preview the ddtaquiz or not.'),
                'canreviewmyattempts' => new external_value(PARAM_BOOL, 'Whether the users can review their previous attempts
                                                                or not.'),
                'canviewreports' => new external_value(PARAM_BOOL, 'Whether the user can view the ddtaquiz reports or not.'),
                'accessrules' => new external_multiple_structure(
                                    new external_value(PARAM_TEXT, 'rule description'), 'list of rules'),
                'activerulenames' => new external_multiple_structure(
                                    new external_value(PARAM_PLUGIN, 'rule plugin names'), 'list of active rules'),
                'preventaccessreasons' => new external_multiple_structure(
                                            new external_value(PARAM_TEXT, 'access restriction description'), 'list of reasons'),
                'warnings' => new external_warnings(),
            )
        );
    }

    /**
     * Describes the parameters for get_attempt_access_information.
     *
     * @return external_function_parameters
     * @since Moodle 3.1
     */
    public static function get_attempt_access_information_parameters() {
        return new external_function_parameters (
            array(
                'ddtaquizid' => new external_value(PARAM_INT, 'ddtaquiz instance id'),
                'attemptid' => new external_value(PARAM_INT, 'attempt id, 0 for the user last attempt if exists', VALUE_DEFAULT, 0),
            )
        );
    }

    /**
     * Return access information for a given attempt in a ddtaquiz.
     *
     * @param int $ddtaquizid ddtaquiz instance id
     * @param int $attemptid attempt id, 0 for the user last attempt if exists
     * @return array of warnings and the access information
     * @since Moodle 3.1
     * @throws  moodle_ddtaquiz_exception
     */
    public static function get_attempt_access_information($ddtaquizid, $attemptid = 0) {
        global $DB, $USER;

        $warnings = array();

        $params = array(
            'ddtaquizid' => $ddtaquizid,
            'attemptid' => $attemptid,
        );
        $params = self::validate_parameters(self::get_attempt_access_information_parameters(), $params);

        list($ddtaquiz, $course, $cm, $context) = self::validate_ddtaquiz($params['ddtaquizid']);

        $attempttocheck = 0;
        if (!empty($params['attemptid'])) {
            $attemptobj = ddtaquiz_attempt::create($params['attemptid']);
            if ($attemptobj->get_userid() != $USER->id) {
                throw new moodle_ddtaquiz_exception($attemptobj->get_ddtaquizobj(), 'notyourattempt');
            }
            $attempttocheck = $attemptobj->get_attempt();
        }

        // Access manager now.
        $ddtaquizobj = ddtaquiz::create($cm->instance, $USER->id);
        $ignoretimelimits = has_capability('mod/ddtaquiz:ignoretimelimits', $context, null, false);
        $timenow = time();
        $accessmanager = new ddtaquiz_access_manager($ddtaquizobj, $timenow, $ignoretimelimits);

        $attempts = ddtaquiz_get_user_attempts($ddtaquiz->id, $USER->id, 'finished', true);
        $lastfinishedattempt = end($attempts);
        if ($unfinishedattempt = ddtaquiz_get_user_attempt_unfinished($ddtaquiz->id, $USER->id)) {
            $attempts[] = $unfinishedattempt;

            // Check if the attempt is now overdue. In that case the state will change.
            $ddtaquizobj->create_attempt_object($unfinishedattempt)->handle_if_time_expired(time(), false);

            if ($unfinishedattempt->state != ddtaquiz_attempt::IN_PROGRESS and $unfinishedattempt->state != ddtaquiz_attempt::OVERDUE) {
                $lastfinishedattempt = $unfinishedattempt;
            }
        }
        $numattempts = count($attempts);

        if (!$attempttocheck) {
            $attempttocheck = $unfinishedattempt ? $unfinishedattempt : $lastfinishedattempt;
        }

        $result = array();
        $result['isfinished'] = $accessmanager->is_finished($numattempts, $lastfinishedattempt);
        $result['preventnewattemptreasons'] = $accessmanager->prevent_new_attempt($numattempts, $lastfinishedattempt);

        if ($attempttocheck) {
            $endtime = $accessmanager->get_end_time($attempttocheck);
            $result['endtime'] = ($endtime === false) ? 0 : $endtime;
            $attemptid = $unfinishedattempt ? $unfinishedattempt->id : null;
            $result['ispreflightcheckrequired'] = $accessmanager->is_preflight_check_required($attemptid);
        }

        $result['warnings'] = $warnings;
        return $result;
    }

    /**
     * Describes the get_attempt_access_information return value.
     *
     * @return external_single_structure
     * @since Moodle 3.1
     */
    public static function get_attempt_access_information_returns() {
        return new external_single_structure(
            array(
                'endtime' => new external_value(PARAM_INT, 'When the attempt must be submitted (determined by rules).',
                                                VALUE_OPTIONAL),
                'isfinished' => new external_value(PARAM_BOOL, 'Whether there is no way the user will ever be allowed to attempt.'),
                'ispreflightcheckrequired' => new external_value(PARAM_BOOL, 'whether a check is required before the user
                                                                    starts/continues his attempt.', VALUE_OPTIONAL),
                'preventnewattemptreasons' => new external_multiple_structure(
                                                new external_value(PARAM_TEXT, 'access restriction description'),
                                                                    'list of reasons'),
                'warnings' => new external_warnings(),
            )
        );
    }

    /**
     * Describes the parameters for get_ddtaquiz_required_qtypes.
     *
     * @return external_function_parameters
     * @since Moodle 3.1
     */
    public static function get_ddtaquiz_required_qtypes_parameters() {
        return new external_function_parameters (
            array(
                'ddtaquizid' => new external_value(PARAM_INT, 'ddtaquiz instance id')
            )
        );
    }

    /**
     * Return the potential question types that would be required for a given ddtaquiz.
     * Please note that for random question types we return the potential question types in the category choosen.
     *
     * @param int $ddtaquizid ddtaquiz instance id
     * @return array of warnings and the access information
     * @since Moodle 3.1
     * @throws  moodle_ddtaquiz_exception
     */
    public static function get_ddtaquiz_required_qtypes($ddtaquizid) {
        global $DB, $USER;

        $warnings = array();

        $params = array(
            'ddtaquizid' => $ddtaquizid
        );
        $params = self::validate_parameters(self::get_ddtaquiz_required_qtypes_parameters(), $params);

        list($ddtaquiz, $course, $cm, $context) = self::validate_ddtaquiz($params['ddtaquizid']);

        $ddtaquizobj = ddtaquiz::create($cm->instance, $USER->id);
        $ddtaquizobj->preload_questions();
        $ddtaquizobj->load_questions();

        // Question types used.
        $result = array();
        $result['questiontypes'] = $ddtaquizobj->get_all_question_types_used(true);
        $result['warnings'] = $warnings;
        return $result;
    }

    /**
     * Describes the get_ddtaquiz_required_qtypes return value.
     *
     * @return external_single_structure
     * @since Moodle 3.1
     */
    public static function get_ddtaquiz_required_qtypes_returns() {
        return new external_single_structure(
            array(
                'questiontypes' => new external_multiple_structure(
                                    new external_value(PARAM_PLUGIN, 'question type'), 'list of question types used in the ddtaquiz'),
                'warnings' => new external_warnings(),
            )
        );
    }

}
