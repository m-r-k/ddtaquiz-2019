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
 * Unit tests for the privacy legacy polyfill for ddtaquiz access rules.
 *
 * @package     mod_ddtaquiz
 * @category    test
 * @copyright   2018 Andrew Nicols <andrew@nicols.co.uk>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/ddtaquiz/attemptlib.php');

/**
 * Unit tests for the privacy legacy polyfill for ddtaquiz access rules.
 *
 * @copyright   2018 Andrew Nicols <andrew@nicols.co.uk>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class core_privacy_legacy_ddtaquizaccess_polyfill_test extends advanced_testcase {
    /**
     * Test that the core_ddtaquizaccess\privacy\legacy_polyfill works and that the static _export_ddtaquizaccess_user_data can
     * be called.
     */
    public function test_export_ddtaquizaccess_user_data() {
        $ddtaquiz = $this->createMock(ddtaquiz::class);
        $user = (object) [];
        $returnvalue = (object) [];

        $mock = $this->createMock(test_privacy_legacy_ddtaquizaccess_polyfill_mock_wrapper::class);
        $mock->expects($this->once())
            ->method('get_return_value')
            ->with('_export_ddtaquizaccess_user_data', [$ddtaquiz, $user])
            ->willReturn($returnvalue);

        test_privacy_legacy_ddtaquizaccess_polyfill_provider::$mock = $mock;
        $result = test_privacy_legacy_ddtaquizaccess_polyfill_provider::export_ddtaquizaccess_user_data($ddtaquiz, $user);
        $this->assertSame($returnvalue, $result);
    }

    /**
     * Test the _delete_ddtaquizaccess_for_context shim.
     */
    public function test_delete_ddtaquizaccess_for_context() {
        $context = context_system::instance();

        $ddtaquiz = $this->createMock(ddtaquiz::class);

        $mock = $this->createMock(test_privacy_legacy_ddtaquizaccess_polyfill_mock_wrapper::class);
        $mock->expects($this->once())
            ->method('get_return_value')
            ->with('_delete_ddtaquizaccess_data_for_all_users_in_context', [$ddtaquiz]);

        test_privacy_legacy_ddtaquizaccess_polyfill_provider::$mock = $mock;
        test_privacy_legacy_ddtaquizaccess_polyfill_provider::delete_ddtaquizaccess_data_for_all_users_in_context($ddtaquiz);
    }

    /**
     * Test the _delete_ddtaquizaccess_for_context shim.
     */
    public function test_delete_ddtaquizaccess_for_user() {
        $context = context_system::instance();

        $ddtaquiz = $this->createMock(ddtaquiz::class);
        $user = (object) [];

        $mock = $this->createMock(test_privacy_legacy_ddtaquizaccess_polyfill_mock_wrapper::class);
        $mock->expects($this->once())
            ->method('get_return_value')
            ->with('_delete_ddtaquizaccess_data_for_user', [$ddtaquiz, $user]);

        test_privacy_legacy_ddtaquizaccess_polyfill_provider::$mock = $mock;
        test_privacy_legacy_ddtaquizaccess_polyfill_provider::delete_ddtaquizaccess_data_for_user($ddtaquiz, $user);
    }
}

/**
 * Legacy polyfill test class for the ddtaquizaccess_provider.
 *
 * @copyright   2018 Andrew Nicols <andrew@nicols.co.uk>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class test_privacy_legacy_ddtaquizaccess_polyfill_provider implements
        \core_privacy\local\metadata\provider,
        \mod_ddtaquiz\privacy\ddtaquizaccess_provider {

    use \mod_ddtaquiz\privacy\legacy_ddtaquizaccess_polyfill;
    use \core_privacy\local\legacy_polyfill;

    /**
     * @var test_privacy_legacy_ddtaquizaccess_polyfill_provider $mock.
     */
    public static $mock = null;

    /**
     * Export all user data for the ddtaquizaccess plugin.
     *
     * @param \ddtaquiz $ddtaquiz
     * @param \stdClass $user
     */
    protected static function _export_ddtaquizaccess_user_data($ddtaquiz, $user) {
        return static::$mock->get_return_value(__FUNCTION__, func_get_args());
    }

    /**
     * Deletes all user data for the given context.
     *
     * @param \ddtaquiz $ddtaquiz
     */
    protected static function _delete_ddtaquizaccess_data_for_all_users_in_context($ddtaquiz) {
        static::$mock->get_return_value(__FUNCTION__, func_get_args());
    }

    /**
     * Delete personal data for the given user and context.
     *
     * @param   \ddtaquiz           $ddtaquiz The ddtaquiz being deleted
     * @param   \stdClass       $user The user to export data for
     */
    protected static function _delete_ddtaquizaccess_data_for_user($ddtaquiz, $user) {
        static::$mock->get_return_value(__FUNCTION__, func_get_args());
    }

    /**
     * Returns metadata about this plugin.
     *
     * @param   \core_privacy\local\metadata\collection $collection The initialised collection to add items to.
     * @return  \core_privacy\local\metadata\collection     A listing of user data stored through this system.
     */
    protected static function _get_metadata(\core_privacy\local\metadata\collection $collection) {
        return $collection;
    }
}

/**
 * Called inside the polyfill methods in the test polyfill provider, allowing us to ensure these are called with correct params.
 *
 * @copyright   2018 Andrew Nicols <andrew@nicols.co.uk>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class test_privacy_legacy_ddtaquizaccess_polyfill_mock_wrapper {
    /**
     * Get the return value for the specified item.
     */
    public function get_return_value() {
    }
}
