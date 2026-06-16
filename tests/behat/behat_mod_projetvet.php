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
 * Behat steps for mod_projetvet
 *
 * @package    mod_projetvet
 * @category   test
 * @copyright  2025 Bas Brands <bas@sonsbeekmedia.nl>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// NOTE: no MOODLE_INTERNAL test here, this file may be required by behat before including /config.php.

require_once(__DIR__ . '/../../../../lib/behat/behat_base.php');

use Behat\Mink\Exception\ElementNotFoundException;
use Behat\Mink\Exception\ExpectationException;

/**
 * Behat steps definitions for mod_projetvet
 *
 * @package    mod_projetvet
 * @category   test
 * @copyright  2025 Bas Brands <bas@sonsbeekmedia.nl>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class behat_mod_projetvet extends behat_base {
    /**
     * Return a list of exact named selectors for recurring ProjetVet areas.
     *
     * @return behat_component_named_selector[]
     */
    public static function get_exact_named_selectors(): array {
        return [
            new behat_component_named_selector('Tutor assignments students report', [
                "//*[@id='students-report-container']",
            ]),
            new behat_component_named_selector('Tutor assignments teachers report', [
                "//*[@id='teachers-report-container']",
            ]),
            new behat_component_named_selector('Tutor assignments teachers section', [
                "//*[@id='collapseteachers-section']",
            ]),
            new behat_component_named_selector('Dashboard report container', [
                "//*[@id='dashboard-report-container']",
            ]),
        ];
    }

    /**
     * Opens the activity entry form for creating a new entry
     *
     * @Given /^I click on create new activity entry for "(?P<formsetidnumber_string>(?:[^"]|\\")*)"$/
     * @param string $formsetidnumber The form set idnumber (activities, facetoface, etc.)
     * @throws ExpectationException
     */
    public function i_click_on_create_new_activity_entry($formsetidnumber) {
        $button = $this->find(
            'css',
            '[data-action="activity-entry-form"][data-formsetidnumber="' . $formsetidnumber . '"][data-entryid="0"]'
        );

        if (!$button) {
            throw new ElementNotFoundException(
                $this->getSession(),
                'Create new entry button',
                'css',
                '[data-action="activity-entry-form"][data-formsetidnumber="' . $formsetidnumber . '"]'
            );
        }

        $button->click();
        $this->wait_for_pending_js();
    }

    /**
     * Opens the tagselect popup for a specific field
     *
     * @Given /^I open tagselect for field "(?P<fieldidnumber_string>(?:[^"]|\\")*)"$/
     * @param string $fieldidnumber The field idnumber
     * @throws ExpectationException
     */
    public function i_open_tagselect_for_field($fieldidnumber) {
        // Find the field wrapper by data-fieldidnumber attribute.
        $fieldwrapper = $this->find(
            'css',
            '[data-fieldidnumber="' . $fieldidnumber . '"]'
        );

        if (!$fieldwrapper) {
            throw new ElementNotFoundException(
                $this->getSession(),
                'Field wrapper',
                'css',
                '[data-fieldidnumber="' . $fieldidnumber . '"]'
            );
        }

        // Find the open-tagselect button within this field.
        $button = $this->find(
            'css',
            '[data-action="open-tagselect"]',
            new ExpectationException('Tagselect button not found for field ' . $fieldidnumber, $this->getSession()),
            $fieldwrapper
        );

        $button->click();
        $this->wait_for_pending_js();
    }

    /**
     * Opens the tagselect popup for a field by element name
     *
     * @Given /^I open tagselect for "(?P<elementname_string>(?:[^"]|\\")*)"$/
     * @param string $elementname The element name (e.g., "Category")
     * @throws ExpectationException
     */
    public function i_open_tagselect_for_element_name($elementname) {
        // Find the open-tagselect button by data-element-name attribute.
        $button = $this->find(
            'css',
            '[data-action="open-tagselect"][data-element-name="' . $elementname . '"]'
        );

        if (!$button) {
            throw new ElementNotFoundException(
                $this->getSession(),
                'Tagselect button for ' . $elementname,
                'css',
                '[data-action="open-tagselect"][data-element-name="' . $elementname . '"]'
            );
        }

        $button->click();
        $this->wait_for_pending_js();
    }

    /**
     * Selects a tag in the tagselect popup by clicking on it
     *
     * @Given /^I select tag "(?P<tagname_string>(?:[^"]|\\")*)" in tagselect popup$/
     * @param string $tagname The tag name or part of it
     * @throws ExpectationException
     */
    public function i_select_tag_in_tagselect_popup($tagname) {
        // Find the tagselect popup.
        $popup = $this->find('css', '[data-region="tagselect-popup"]:not(.d-none)');

        if (!$popup) {
            throw new ElementNotFoundException(
                $this->getSession(),
                'Tagselect popup',
                'css',
                '[data-region="tagselect-popup"]'
            );
        }

        // Find all tags and look for one containing the text.
        $tags = $popup->findAll('css', '[data-action="select-tag"]');
        $found = false;

        foreach ($tags as $tag) {
            $text = $tag->getText();
            if (stripos($tag->getText(), $tagname) !== false) {
                $tag->click();
                $found = true;
                break;
            }
        }

        if (!$found) {
            throw new ExpectationException(
                'Tag "' . $tagname . '" not found in tagselect popup',
                $this->getSession()
            );
        }

        $this->wait_for_pending_js();
    }

    /**
     * Saves the selected tags in the tagselect popup
     *
     * @Given /^I save tags in tagselect popup$/
     * @throws ExpectationException
     */
    public function i_save_tags_in_tagselect_popup() {
        $popup = $this->find('css', '[data-region="tagselect-popup"]:not(.d-none)');

        if (!$popup) {
            throw new ElementNotFoundException(
                $this->getSession(),
                'Tagselect popup',
                'css',
                '[data-region="tagselect-popup"]'
            );
        }

        $savebutton = $this->find(
            'css',
            '[data-action="save-tags"]',
            new ExpectationException('Save tags button not found', $this->getSession()),
            $popup
        );

        $savebutton->click();
        $this->wait_for_pending_js();
    }

    /**
     * Submits the projetvet form
     *
     * @Given /^I submit the projetvet form$/
     * @throws ExpectationException
     */
    public function i_submit_the_projetvet_form() {
        $submitbutton = $this->find(
            'css',
            'button.btn-primary.projetvet-form-button',
            new ExpectationException('Submit button not found', $this->getSession())
        );

        $submitbutton->click();
        $this->wait_for_pending_js();
    }

    /**
     * Clicks on a button with specific data-action within a region
     * // phpcs:disable moodle.Files.LineLength.TooLong
     * @Given /^I click on "(?P<action_string>(?:[^"]|\\")*)" buttonaction in the "(?P<regionname_string>(?:[^"]|\\")*)" "(?P<selectortype_string>(?:[^"]|\\")*)"$/
     * // phpcs:enable moodle.Files.LineLength.TooLong
     * @param string $action The data-action attribute value
     * @param string $regionname The region identifier
     * @param string $selectortype The type of selector (region, css_element, etc.)
     * @throws ExpectationException
     */
    public function i_click_on_buttonaction_in_region($action, $regionname, $selectortype) {
        $container = $this->get_selected_node($selectortype, $regionname);

        // Find all matching buttons and click the first visible one.
        $buttons = $container->findAll('css', '[data-action="' . $action . '"]');
        if (empty($buttons)) {
            throw new ElementNotFoundException(
                $this->getSession(),
                'Button with data-action="' . $action . '"',
                'css',
                '[data-action="' . $action . '"]'
            );
        }

        $button = null;
        foreach ($buttons as $candidate) {
            if ($candidate->isVisible()) {
                $button = $candidate;
                break;
            }
        }

        if (!$button) {
            // Fallback for reportbuilder actions hidden until hover/menu interaction.
            $containerid = $container->getAttribute('id');
            if (!empty($containerid)) {
                $script = '(function() {' .
                    'const container = document.getElementById(' . json_encode($containerid) . ');' .
                    'if (!container) { return false; }' .
                    'const button = container.querySelector(\'[data-action="' . $action . '"]\');' .
                    'if (!button) { return false; }' .
                    'button.click();' .
                    'return true;' .
                '})();';

                $clicked = $this->getSession()->evaluateScript($script);
                if ($clicked) {
                    $this->wait_for_pending_js();
                    return;
                }
            }

            throw new ExpectationException(
                'No clickable button with data-action="' . $action . '" was found',
                $this->getSession()
            );
        }

        $button->click();
        $this->wait_for_pending_js();
    }

    /**
     * Checks if an entry with specific title exists in the entry list
     *
     * @Then /^I should see entry with title "(?P<title_string>(?:[^"]|\\")*)" in the entry list$/
     * @param string $title The entry title
     * @throws ExpectationException
     */
    public function i_should_see_entry_with_title($title) {
        $entrylist = $this->find('css', '[data-region="entry-list"]');

        if (!$entrylist) {
            throw new ElementNotFoundException(
                $this->getSession(),
                'Entry list',
                'css',
                '[data-region="entry-list"]'
            );
        }

        $text = $entrylist->getText();
        if (stripos($text, $title) === false) {
            throw new ExpectationException(
                'Entry with title "' . $title . '" not found in entry list',
                $this->getSession()
            );
        }
    }

    /**
     * Clicks the edit button for a specific entry
     *
     * @Given /^I click edit for entry with title "(?P<title_string>(?:[^"]|\\")*)"$/
     * @param string $title The entry title
     * @throws ExpectationException
     */
    public function i_click_edit_for_entry($title) {
        // Find the entry row containing the title.
        $entrylist = $this->find('css', '[data-region="entry-list"]');

        if (!$entrylist) {
            throw new ElementNotFoundException(
                $this->getSession(),
                'Entry list',
                'css',
                '[data-region="entry-list"]'
            );
        }

        // Find all rows.
        $rows = $entrylist->findAll('css', 'tr');
        $found = false;

        foreach ($rows as $row) {
            if (stripos($row->getText(), $title) !== false) {
                // Find edit button in this row.
                $editbutton = $row->find('css', '[data-action="activity-entry-form"]');
                if ($editbutton) {
                    $editbutton->click();
                    $this->wait_for_pending_js();
                    $found = true;
                    break;
                }
            }
        }

        if (!$found) {
            throw new ExpectationException(
                'Entry with title "' . $title . '" not found or edit button not available',
                $this->getSession()
            );
        }
    }

    /**
     * Clicks the view activities link for a specific student
     *
     * @Given /^I view activities for student "(?P<studentname_string>(?:[^"]|\\")*)"$/
     * @param string $studentname The student's full name
     * @throws ExpectationException
     */
    public function i_view_activities_for_student($studentname) {
        // Find the table row containing the student name.
        $table = $this->find('css', 'table.table');

        if (!$table) {
            throw new ElementNotFoundException(
                $this->getSession(),
                'Student list table',
                'css',
                'table.table'
            );
        }

        // Find all rows.
        $rows = $table->findAll('css', 'tbody tr');
        $found = false;

        foreach ($rows as $row) {
            if (stripos($row->getText(), $studentname) !== false) {
                // Find the "View activities" link in this row.
                $link = $row->find('css', 'a[title="Actions"]');
                if ($link) {
                    $link->click();
                    $this->wait_for_pending_js();
                    $view = $row->find('css', 'a[aria-label="View Activities"]');
                    $view->click();
                    $found = true;
                    break;
                }
            }
        }

        if (!$found) {
            throw new ExpectationException(
                'Student "' . $studentname . '" not found or view activities link not available',
                $this->getSession()
            );
        }
    }

    /**
     * Clicks a form button by its text (ignoring icons)
     *
     * @Given /^I click on form button "(?P<buttontext_string>(?:[^"]|\\")*)"$/
     * @param string $buttontext The button text
     * @throws ExpectationException
     */
    public function i_click_on_form_button($buttontext) {
        // Find all submit buttons.
        $buttons = $this->find_all('css', 'button.projetvet-form-button');

        $found = false;
        foreach ($buttons as $button) {
            $value = $button->getValue();
            $text = $button->getText();

            // Check if button text contains our search text (strips HTML/icons).
            if (stripos($value, $buttontext) !== false || stripos(strip_tags($text), $buttontext) !== false) {
                $button->click();
                $this->wait_for_pending_js();
                $found = true;
                break;
            }
        }

        if (!$found) {
            throw new ExpectationException(
                'Form button with text "' . $buttontext . '" not found',
                $this->getSession()
            );
        }
    }

    /**
     * Clicks on a table row containing specific text (for clickable rows in reportbuilder)
     *
     * @Given /^I click on row with text "(?P<text_string>(?:[^"]|\\")*)"$/
     * @param string $text The text to find in the row
     * @throws ExpectationException
     */
    public function i_click_on_row_with_text($text) {
        // Find all table rows.
        $rows = $this->find_all('css', 'table tbody tr');

        $found = false;
        foreach ($rows as $row) {
            if (stripos($row->getText(), $text) !== false) {
                $row->click();
                $this->wait_for_pending_js();
                $found = true;
                break;
            }
        }

        if (!$found) {
            throw new ExpectationException(
                'Row containing text "' . $text . '" not found',
                $this->getSession()
            );
        }
    }

    /**
     * Waits for and closes a notification alert modal
     *
     * @Given /^I close the notification alert$/
     * @throws ExpectationException
     */
    public function i_close_the_notification_alert() {
        // Wait for the modal to appear.
        $this->wait_for_pending_js();

        // Find the modal footer with the OK button.
        $modalfooter = $this->find(
            'css',
            '.modal.show .modal-footer'
        );

        if (!$modalfooter) {
            throw new ElementNotFoundException(
                $this->getSession(),
                'Modal footer',
                'css',
                '.modal.show .modal-footer'
            );
        }

        // Find the cancel button (which is the OK button in alert modals).
        $button = $modalfooter->find('css', 'button[data-action="cancel"]');

        if (!$button) {
            throw new ElementNotFoundException(
                $this->getSession(),
                'Alert OK button',
                'css',
                'button[data-action="cancel"]'
            );
        }

        // Ensure button is visible and clickable.
        $this->ensure_node_is_visible($button);
        $button->click();
        $this->wait_for_pending_js();
    }

    /**
     * Checks if an element contains specific text
     * // phpcs:disable moodle.Files.LineLength.TooLong
     * @Then /^"(?P<selector_string>(?:[^"]|\\")*)" "(?P<selectortype_string>(?:[^"]|\\")*)" should contain "(?P<text_string>(?:[^"]|\\")*)"$/
     * // phpcs:enable moodle.Files.LineLength.TooLong
     * @param string $selector The selector locator
     * @param string $selectortype The type of selector (css_element, named selector, etc.)
     * @param string $text The text to search for
     * @throws ExpectationException
     */
    public function element_should_contain_text($selector, $selectortype, $text) {
        $element = $this->get_selected_node($selectortype, $selector);
        if (!$element) {
            throw new ExpectationException(
                'Element "' . $selector . '" with selector type "' . $selectortype . '" was not found.',
                $this->getSession()
            );
        }

        $elementtext = $element->getText();
        if (stripos($elementtext, $text) === false) {
            throw new ExpectationException(
                'Element "' . $selector . '" does not contain text "' . $text . '". Found: ' . substr($elementtext, 0, 100),
                $this->getSession()
            );
        }
    }

    /**
     * Convert page names to URLs for steps like 'When I am on the "[identifier]" "[page type]" page'.
     *
     * Recognised page names are:
     * | pagetype          | name meaning | description                    |
     * | View              | Activity name | The activity view page        |
     * | Tutor assignments | Activity name | The assignments page (assignments.php)    |
     * | Dashboard         | Activity name | The dashboard page (dashboard.php)        |
     *
     * @param string $type identifies which type of page this is, e.g. 'Tutor assignments'.
     * @param string $identifier identifies the particular page, e.g. 'ProjetVet 1'.
     * @return moodle_url the corresponding URL.
     * @throws Exception with a meaningful error message if the specified page cannot be found.
     */
    protected function resolve_page_instance_url(string $type, string $identifier): moodle_url {
        switch (strtolower($type)) {
            case 'view':
                return new moodle_url(
                    '/mod/projetvet/view.php',
                    ['id' => $this->get_cm_by_projetvet_name($identifier)->id]
                );

            case 'tutor assignments':
                return new moodle_url(
                    '/mod/projetvet/assignments.php',
                    ['id' => $this->get_cm_by_projetvet_name($identifier)->id]
                );

            case 'dashboard':
                return new moodle_url(
                    '/mod/projetvet/dashboard.php',
                    ['id' => $this->get_cm_by_projetvet_name($identifier)->id]
                );

            default:
                throw new Exception('Unrecognised projetvet page type "' . $type . '."');
        }
    }

    /**
     * Get a projetvet activity course module by activity name.
     *
     * @param string $name the activity name.
     * @return stdClass cm from get_coursemodule_from_instance.
     */
    protected function get_cm_by_projetvet_name(string $name): stdClass {
        global $DB;
        $projetvet = $DB->get_record('projetvet', ['name' => $name], '*', MUST_EXIST);
        return get_coursemodule_from_instance('projetvet', $projetvet->id, $projetvet->course, false, MUST_EXIST);
    }
}
