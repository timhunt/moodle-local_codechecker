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
 * This file contains helper testcase for testing "moodle" CS Sniffs.
 *
 * @package    local_codechecker
 * @subpackage phpunit
 * @category   phpunit
 * @copyright  2013 onwards Eloy Lafuente (stronk7) {@link http://stronk7.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die(); // Remove this to use me out from Moodle.

// Add the phpcs machinery.
if (is_file(__DIR__ . '/../pear/PHP/CodeSniffer.php') === true) {
    require_once(__DIR__ .'/../pear/PHP/CodeSniffer.php');
} else {
    require_once('PHP/CodeSniffer.php');
}

// Interim classes providing conditional extension, so I can run
// these plugin tests against different Moodle branches that are
// using different phpunit (namespaced or no) clases.
// @codingStandardsIgnoreStart
if (class_exists('PHPUnit_Framework_TestCase')) {
    abstract class conditional_PHPUnit_Framework_TestCase extends PHPUnit_Framework_TestCase {
    }
} else {
    abstract class conditional_PHPUnit_Framework_TestCase extends PHPUnit\Framework\TestCase {
    }
}
// @codingStandardsIgnoreEnd

/**
 * Specialized test case for easy testing of "moodle" CS Sniffs.
 *
 * If you want to run the tests for the Moodle sniffs, you need to
 * use the specific command-line:
 *     vendor/bin/phpunit local/codechecker/moodle/tests/moodlestandard_test.php
 * no tests for this plugin are run as part of a full Moodle PHPunit run.
 * (This may be a bug?)
 *
 * This class mimics {@link AbstractSniffUnitTest} way to test Sniffs
 * allowing easy process of examples and assertion of result expectations.
 *
 * Should work for any Sniff part of a given standard (custom or core).
 *
 * Note extension & overriding was impossible because of some "final" stuff.
 */
abstract class local_codechecker_testcase extends conditional_PHPUnit_Framework_TestCase {

    /**
     * @var PHP_CodeSniffer The unique CS instance shared by all test cases.
     */
    protected static $phpcs = null;

    /**
     * @var string name of the standard to be tested.
     */
    protected $standard = null;

    /**
     * @var string code of the sniff to be tested. Must be part of the standard definition.
     *             See {@link ::set_sniff()} for more information.
     */
    protected $sniff = null;

    /**
     * @var string full path to the file used as input (fixture).
     */
    protected $fixture = null;

    /**
     * @var array error expectations to ve verified against execution results.
     */
    protected $errors = null;

    /**
     * @var array warning expectations to ve verified against execution results.
     */
    protected $warnings = null;

    /**
     * Set the name of the standard to be tested.
     *
     * @param string $standard name of the standard to be tested.
     */
    protected function set_standard($standard) {

        // Since 2.9 arbitrary standard directories are not allowed by default,
        // only those under the CodeSniffer/Standards dir are detected. Other base
        // dirs containing standards can be added using CodeSniffer.conf or the
        // PHP_CODESNIFFER_CONFIG_DATA global (installed_paths setting).
        // We are using the global way here to avoid changes in the phpcs import.
        // @codingStandardsIgnoreStart
        if (!isset($GLOBALS['PHP_CODESNIFFER_CONFIG_DATA']['installed_paths'])) {
            $localcodecheckerpath = realpath(__DIR__ . '/../');
            $GLOBALS['PHP_CODESNIFFER_CONFIG_DATA'] = ['installed_paths' => $localcodecheckerpath];
        }
        // @codingStandardsIgnoreEnd

        // Basic search of standards in the allowed directories.
        $stdsearch = array(
            __DIR__ . '/../pear/PHP/CodeSniffer/Standards', // PHPCS standards dir.
            __DIR__ . '/..',                                // local_codechecker dir, allowed above via global.
        );

        foreach ($stdsearch as $stdpath) {
            $stdpath = realpath($stdpath . '/' . $standard);
            $stdfile = $stdpath . '/ruleset.xml';
            if (file_exists($stdfile)) {
                $this->standard = $stdpath; // Need to pass the path here.
                break;
            }
        }
        // Standard not found, fail.
        if ($this->standard === null) {
            $this->fail('Standard "' . $standard . '" not found.');
        }
    }

    /**
     * Set the name of the sniff to be tested.
     *
     * @param string $sniff code of the sniff to be tested. Must be part of the standard definition.
     *                      Since CodeSniffer 1.5 they are not the Sniff (class) names anymore but
     *                      the called Sniff "code" that is a 3 elements, dot separated, structure
     *                      with format: standard.group.name. Examples:
     *                        - Generic.PHP.LowerCaseConstant
     *                        - moodle.Commenting.InlineComment
     *                        - PEAR.WhiteSpace.ScopeIndent
     */
    protected function set_sniff($sniff) {
        $this->sniff = $sniff;
    }

    /**
     * Set the full path to the file used as input.
     *
     * @param string $fixture full path to the file used as input (fixture).
     */
    protected function set_fixture($fixture) {
        $this->fixture = $fixture;
    }

    /**
     * Set the error expectations to ve verified against execution results.
     *
     * @param array $errors error expectations to ve verified against execution results.
     */
    protected function set_errors(array $errors) {
        $this->errors = $errors;
        // Let's normalize numeric, empty and string errors.
        foreach ($this->errors as $line => $errordef) {
            if (is_int($errordef) and $errordef > 0) {
                $this->errors[$line] = array_fill(0, $errordef, null);
            } else if (empty($errordef)) {
                $this->errors[$line] = array();
            } else if (is_string($errordef)) {
                $this->errors[$line] = array($errordef);
            }
        }
    }

    /**
     * Set the warning expectations to ve verified against execution results.
     *
     * @param array $warnings warning expectations to ve verified against execution results.
     */
    protected function set_warnings(array $warnings) {
        $this->warnings = $warnings;
        // Let's normalize numeric, empty and string warnings.
        foreach ($this->warnings as $line => $warningdef) {
            if (is_int($warningdef) and $warningdef > 0) {
                $this->warnings[$line] = array_fill(0, $warningdef, null);
            } else if (empty($warningdef)) {
                $this->warnings[$line] = array();
            } else if (is_string($warningdef)) {
                $this->warnings[$line] = array($warningdef);
            }
        }
    }

    /**
     * Code to be executed before each test case (method) is run.
     *
     * In charge of initializing the CS and reset all the internal
     * properties.
     */
    protected function setUp() {
        if (self::$phpcs === null) {
            // Note that the plugin workarounds this by using
            // {@link local_codechecker_codesniffer_cli} with overridden method, but I want
            // to keep this testcase independent of Moodle.
            //
            // Nasty hack, used by CS tests themselves (see AllTests.php), to
            // avoid the underlying PHP_CodeSniffer_CLI to fail when it is called
            // from any parametrized script (phpStorm, phpunit...)
            // (more info {@link https://pear.php.net/bugs/bug.php?id=18247}).
            if (defined('PHP_CODESNIFFER_IN_TESTS') === false) {
                define('PHP_CODESNIFFER_IN_TESTS', true);
            }

            // Instantiate the CS safely now.
            self::$phpcs = new PHP_CodeSniffer();
        }
        $this->standard = null;
        $this->sniff = null;
        $this->errors = null;
        $this->warnings = null;
    }

    /**
     * Run the CS and verify all the expected errors and warnings.
     *
     * This method must be called after defining everything (the standard,
     * the sniff, the fixture and the error and warning expectations). Then,
     * the CS is called and finally its results are tested against the
     * defined expectations.
     */
    protected function verify_cs_results() {

        self::$phpcs->initStandard($this->standard, array($this->sniff));
        self::$phpcs->processRuleset($this->standard . '/ruleset.xml');
        self::$phpcs->populateTokenListeners();
        self::$phpcs->setIgnorePatterns(array());

        // The passed sniff is incorrect.
        if (self::$phpcs->getSniffs() === array()) {
            $this->fail('Sniff "' . $this->sniff . '" not found.');
        }

        // We don't accept undefined errors and warnings.
        if (is_null($this->errors) and is_null($this->warnings)) {
            $this->fail('Error and warning expectations undefined. You must define at least one.');
        }

        // Let's process the fixture.
        try {
            $phpcsfile = self::$phpcs->processFile($this->fixture);
        } catch (Exception $e) {
            $this->fail('An unexpected exception has been caught: '. $e->getMessage());
        }

        // Capture results.
        if (empty($phpcsfile) === true) {
            $this->markTestSkipped();
        }

        // Let's compare expected errors with returned ones.
        $this->verify_errors($phpcsfile->getErrors());
        $this->verify_warnings($phpcsfile->getWarnings());
    }

    /**
     * Normalize result errors and verify them against error expectations.
     *
     * @param array $errors error results produced by the CS execution.
     */
    private function verify_errors($errors) {
        if (!is_array($errors)) {
            $this->fail('Unexpected errors structure received from CS execution.');
        }
        $errors = $this->normalize_cs_results($errors);
        $this->assert_results($this->errors, $errors, 'errors');
    }

    /**
     * Normalize result warnings and verify them against warning expectations.
     *
     * @param array $warnings warning results produced by the CS execution
     */
    private function verify_warnings($warnings) {
        if (!is_array($warnings)) {
            $this->fail('Unexpected warnings structure received from CS execution.');
        }
        $warnings = $this->normalize_cs_results($warnings);
        $this->assert_results($this->warnings, $warnings, 'warnings');
    }

    /**
     * Perform all the assertions needed to verify results math expectations.
     *
     * @param array $expectations error|warning defined expectations
     * @param array $results error|warning generated results.
     * @param string $type results being asserted (errors, warnings). Used for output only.
     */
    private function assert_results($expectations, $results, $type) {
        foreach ($expectations as $line => $expectation) {
            // Build some information to be shown in case of problems.
            $info = '';
            if (count($expectation)) {
                $info .= PHP_EOL . 'Expected: ' . json_encode($expectation);
            }
            $countresults = isset($results[$line]) ? count($results[$line]) : 0;
            if ($countresults) {
                $info .= PHP_EOL . 'Actual: ' . json_encode($results[$line]);
            }
            // Verify counts for a line are the same.
            $this->assertSame(count($expectation), $countresults,
                    'Failed number of ' . $type . ' for line ' . $line . '.' . $info);
            // Now verify every expectation requiring matching.
            foreach ($expectation as $key => $expectedcontent) {
                if (is_string($expectedcontent)) {
                    $this->assertContains($expectedcontent, $results[$line][$key],
                            'Failed contents matching of ' . $type . ' for element ' . ($key + 1) . ' of line ' . $line . '.');
                }
            }
            // Delete this line from results.
            unset($results[$line]);
        }
        // Ended looping, verify there aren't remaining results (errors, warnings).
        $this->assertSame(array(), $results,
                'Failed to verify that all the ' . $type . ' have been defined by expectations.');
    }

    /**
     * Transforms the raw results from CS into a simpler array structure.
     *
     * The raw results are a more complex structure of nested arrays, with
     * information that we don't need. This method transforms that structure
     * into a simpler alternative, for easier asserts against the expectations.
     *
     * @param array $results raw CS results (errors or warnings),
     * @return array normalized array.
     */
    private function normalize_cs_results($results) {
        $normalized = array();
        foreach ($results as $line => $lineerrors) {
            foreach ($lineerrors as $errors) {
                foreach ($errors as $error) {
                    if (isset($normalized[$line])) {
                        $normalized[$line][] = '@Message: ' . $error['message'] . ' @Source: ' . $error['source'];
                    } else {
                        $normalized[$line] = array('@Message: ' . $error['message'] . ' @Source: ' . $error['source']);
                    }
                }
            }
        }
        return $normalized;
    }
}
