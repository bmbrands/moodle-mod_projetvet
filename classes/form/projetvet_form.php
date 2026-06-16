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

namespace mod_projetvet\form;

defined('MOODLE_INTERNAL') || die();

global $CFG;

use context;
use context_module;
use core_form\dynamic_form;
use mod_projetvet\local\api\entries;
use mod_projetvet\local\persistent\form_entry;
use mod_projetvet\local\persistent\form_field;
use mod_projetvet\local\persistent\field_data;
use moodle_exception;
use moodle_url;

require_once($CFG->libdir . '/formslib.php');

/**
 * Class activity_entry_form
 *
 * @package    mod_projetvet
 * @copyright  2025 Bas Brands <bas@sonsbeekmedia.nl>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class projetvet_form extends dynamic_form {
    /**
     * Process the form submission
     *
     * @return array
     * @throws moodle_exception
     */
    public function process_dynamic_submission(): array {
        global $USER;
        $data = $this->get_data();

        // Use the entrystatus from form data (set by button clicks).
        // If no specific status is set, use the current status from the entry.
        $currententrystatus = $data->entrystatus ?? 0;

        // Check if this is a button submission by looking for button_action in form data.
        if (isset($data->button_entrystatus)) {
            // Button was clicked, use the button's entrystatus.
            $entrystatus = $data->button_entrystatus;
        } else {
            // Regular form submission, progress to next status.
            $nextstatus = $currententrystatus + 1;
            $entrystatus = $nextstatus;
        }

        // Extract field values from form data - only for categories user can edit.
        $fields = [];
        $formsetidnumber = $data->formsetidnumber ?? 'activities';
        $context = $this->get_context_for_dynamic_submission();
        $structure = entries::get_form_structure($formsetidnumber, $currententrystatus, $context);

        foreach ($structure as $category) {
            // Check if user can edit this category using the hydrated property.
            if ($category->canedit) {
                // Only collect fields from categories the user can edit.
                foreach ($category->fields as $field) {
                    $fieldname = 'field_' . $field->idnumber;
                    if (isset($data->$fieldname)) {
                        $fields[$field->id] = $data->$fieldname;
                    }
                }
            }
        }

        // Save files for filemanager fields - only for editable categories.
        foreach ($structure as $category) {
            // Check if user can edit this category using the hydrated property.
            if ($category->canedit) {
                foreach ($category->fields as $field) {
                    if ($field->type === 'filemanager' && !empty($data->entryid)) {
                        $fieldname = 'field_' . $field->idnumber;
                        if (isset($data->$fieldname)) {
                            // The itemid in the entry becomes the permanent storage location.
                            // We use the field->id combined with entryid to create a unique itemid.
                            $itemid = $data->entryid * 1000 + $field->id;

                            file_save_draft_area_files(
                                $data->$fieldname, // Draft itemid.
                                $context->id,
                                'mod_projetvet',
                                'entry_files',
                                $itemid,
                                [
                                    'subdirs' => 0,
                                    'maxbytes' => 0,
                                    'maxfiles' => 50,
                                ]
                            );

                            // Update the field value to store the permanent itemid.
                            $fields[$field->id] = $itemid;
                        }
                    }
                }
            }
        }

        if (!empty($data->entryid)) {
            // Update existing entry.
            entries::update_entry($data->entryid, $fields, $entrystatus);
            $entryid = $data->entryid;
        } else {
            // Create new entry.
            $studentid = $data->studentid ?? $USER->id;
            $parententryid = $data->parententryid ?? 0;
            $entryid = entries::create_entry(
                $data->projetvetid,
                $studentid,
                $fields,
                $entrystatus,
                $formsetidnumber,
                $parententryid
            );
        }

        $result = [
            'result' => true,
            'entryid' => $entryid,
        ];

        // Include submitpopup if it was set.
        if (isset($data->button_submitpopup)) {
            $result['submitpopup'] = $data->button_submitpopup;
        }

        // Send notification if teachermessage is configured.
        if (isset($data->button_teachermessage) && !empty($data->button_teachermessage)) {
            $studentid = $data->studentid ?? $USER->id;
            $cmid = $data->cmid;
            $messagekey = $data->button_teachermessage;

            // Send notification to tutor.
            \mod_projetvet\local\notifications::send_tutor_notification(
                $entryid,
                $studentid,
                $cmid,
                $messagekey
            );
        }

        // Send notification if studentmessage is configured.
        if (isset($data->button_studentmessage) && !empty($data->button_studentmessage)) {
            $studentid = $data->studentid ?? $USER->id;
            $cmid = $data->cmid;
            $messagekey = $data->button_studentmessage;

            // Send notification to student.
            \mod_projetvet\local\notifications::send_student_notification(
                $entryid,
                $studentid,
                $cmid,
                $messagekey
            );
        }

        return $result;
    }

    /**
     * Get context
     *
     * @return context
     */
    protected function get_context_for_dynamic_submission(): context {
        $cmid = $this->optional_param('cmid', null, PARAM_INT);
        $context = context_module::instance($cmid);
        return $context;
    }

    /**
     * Check access for dynamic submission
     *
     * @return void
     * @throws moodle_exception
     */
    protected function check_access_for_dynamic_submission(): void {
        global $USER;
        $context = $this->get_context_for_dynamic_submission();
        $entryid = $this->optional_param('entryid', 0, PARAM_INT);

        if ($entryid) {
            // Check if user can edit this entry.
            $entry = entries::get_entry($entryid);
            if ($entry->studentid != $USER->id && !has_capability('mod/projetvet:edit', $context)) {
                throw new moodle_exception('invalidaccess');
            }
        } else {
            // Check if user can create entries.
            if (!has_capability('mod/projetvet:view', $context)) {
                throw new moodle_exception('invalidaccess');
            }
        }
    }

    /**
     * Get page URL
     *
     * @return moodle_url
     */
    protected function get_page_url_for_dynamic_submission(): moodle_url {
        $cmid = $this->optional_param('cmid', null, PARAM_INT);
        return new moodle_url('/mod/projetvet/view.php', ['id' => $cmid]);
    }

    /**
     * Form definition
     *
     * @return void
     */
    protected function definition() {
        global $CFG, $USER;
        $mform = $this->_form;

        // Set vertical display mode.
        $this->set_display_vertical();

        // Register custom form elements.
        \MoodleQuickForm::registerElementType(
            'tagselect',
            "$CFG->dirroot/mod/projetvet/classes/form/tagselect_element.php",
            'mod_projetvet\form\tagselect_element'
        );
        \MoodleQuickForm::registerElementType(
            'tagconfirm',
            "$CFG->dirroot/mod/projetvet/classes/form/tagconfirm_element.php",
            'mod_projetvet\form\tagconfirm_element'
        );
        \MoodleQuickForm::registerElementType(
            'switch',
            "$CFG->dirroot/mod/projetvet/classes/form/switch_element.php",
            'mod_projetvet\form\switch_element'
        );
        \MoodleQuickForm::registerElementType(
            'number',
            "$CFG->dirroot/mod/projetvet/classes/form/number_element.php",
            'mod_projetvet\form\number_element'
        );
        \MoodleQuickForm::registerElementType(
            'subset',
            "$CFG->dirroot/mod/projetvet/classes/form/subset_element.php",
            'mod_projetvet\form\subset_element'
        );
        \MoodleQuickForm::registerElementType(
            'html',
            "$CFG->dirroot/mod/projetvet/classes/form/html_element.php",
            'mod_projetvet\form\html_element'
        );

        $cmid = $this->optional_param('cmid', null, PARAM_INT);
        $projetvetid = $this->optional_param('projetvetid', null, PARAM_INT);
        $studentid = $this->optional_param('studentid', null, PARAM_INT);
        $entryid = $this->optional_param('entryid', 0, PARAM_INT);
        $parententryid = $this->optional_param('parententryid', 0, PARAM_INT);
        $formsetidnumber = $this->optional_param('formsetidnumber', 'activities', PARAM_ALPHANUMEXT);
        $readonly = $this->optional_param('readonly', 0, PARAM_INT);

        // Get student email for contact button.
        $studentemail = '';
        if ($studentid) {
            $student = \core_user::get_user($studentid);
            if ($student) {
                $studentemail = $student->email;
            }
        }

        $mform->addElement('hidden', 'cmid', $cmid);
        $mform->addElement('hidden', 'projetvetid', $projetvetid);
        $mform->addElement('hidden', 'studentid', $studentid);
        $mform->addElement('hidden', 'studentemail', $studentemail);
        $mform->addElement('hidden', 'entryid', $entryid);
        $mform->addElement('hidden', 'parententryid', $parententryid);
        $mform->setType('parententryid', PARAM_INT);
        $mform->addElement('hidden', 'formsetidnumber', $formsetidnumber);
        $mform->setType('formsetidnumber', PARAM_ALPHANUMEXT);
        $mform->setType('studentemail', PARAM_EMAIL);
        $mform->addElement('hidden', 'entrystatus');
        $mform->setType('entrystatus', PARAM_INT);
        $mform->addElement('hidden', 'button_entrystatus');
        $mform->setType('button_entrystatus', PARAM_INT);
        $mform->addElement('hidden', 'button_submitpopup');
        $mform->setType('button_submitpopup', PARAM_ALPHANUMEXT);
        $mform->addElement('hidden', 'button_teachermessage');
        $mform->setType('button_teachermessage', PARAM_ALPHANUMEXT);
        $mform->addElement('hidden', 'button_studentmessage');
        $mform->setType('button_studentmessage', PARAM_ALPHANUMEXT);
        $mform->addElement('hidden', 'rang_value');
        $mform->setType('rang_value', PARAM_INT);

        // Get the context for capability checking.
        $context = context_module::instance($cmid);

        // Get current entry status if editing.
        $currententrystatus = 0;
        if ($entryid) {
            $entry = entries::get_entry($entryid);
            $currententrystatus = $entry->entrystatus;
        }

        // Get the activity structure with hydrated permissions and add fields.
        $structure = entries::get_form_structure($formsetidnumber, $currententrystatus, $context);
        $hasexpanded = false;
        $addedcategoryheaders = [];
        foreach ($structure as $category) {
            if (!$category->canview) {
                // Skip this category as user cannot view it.
                continue;
            }

            if ($category->entrystatus == $currententrystatus && !$category->canedit && !$readonly) {
                // Skip this category as user cannot edit it in current status.
                continue;
            }

            // Add category header.
            $mform->addElement('header', 'category_' . $category->id, $category->name);
            $addedcategoryheaders[] = 'category_' . $category->id;

            // Expand header if category entrystatus matches current entry status.
            if ($category->entrystatus == $currententrystatus || $category->canedit) {
                $mform->setExpanded('category_' . $category->id, true, true);
                $hasexpanded = true;
            } else {
                $mform->setExpanded('category_' . $category->id, false, true);
            }

            // Array to collect button elements for grouping.
            $buttonelements = [];

            foreach ($category->fields as $field) {
                $fieldname = 'field_' . $field->idnumber;

                // Check if user can view this field.
                $canviewfield = entries::can_view_field($field, $studentid, $context);
                if (!$canviewfield) {
                    // Skip this field if user cannot view it.
                    continue;
                }

                // Use category-level edit permission for all fields in the category.
                $caneditfield = entries::can_edit_field($category, $context);

                $configdata = (array) $field->configdata;

                switch ($field->type) {
                    case 'text':
                        $mform->addElement('text', $fieldname, $field->name);
                        $mform->setType($fieldname, PARAM_TEXT);
                        break;

                    case 'date':
                        $dateoptions = [
                            'startyear' => date('Y') - 5,
                            'stopyear' => date('Y') + 1,
                        ];
                        $mform->addElement('date_selector', $fieldname, $field->name, $dateoptions);
                        $mform->setDefault($fieldname, time());
                        break;

                    case 'datetime':
                        $datetimeoptions = [
                            'startyear' => date('Y') - 5,
                            'stopyear' => date('Y') + 1,
                        ];
                        $mform->addElement('date_time_selector', $fieldname, $field->name, $datetimeoptions);
                        $mform->setDefault($fieldname, time());
                        break;

                    case 'textarea':
                        $rows = $configdata['rows'] ?? 4;
                        $textareaattributes = [
                            'rows' => $rows,
                            'placeholder' => $field->description,
                        ];
                        $mform->addElement('textarea', $fieldname, $field->name, $textareaattributes);
                        $mform->setType($fieldname, PARAM_TEXT);
                        break;

                    case 'number':
                        $attributes = [];

                        // Add data-action attribute if present in configdata.
                        if (!empty($configdata['data-action'])) {
                            $attributes['data-action'] = $configdata['data-action'];
                        }
                        if (!empty($configdata['data-string'])) {
                            $attributes['data-string'] = $configdata['data-string'];
                        }

                        $mform->addElement('number', $fieldname, $field->name, $attributes);
                        $mform->setType($fieldname, PARAM_FLOAT);
                        break;

                    case 'select':
                        $options = $configdata['options'] ?? [];
                        $optionstrings = $configdata['optionstrings'] ?? [];

                        // If optionstrings are provided, use language strings instead of hardcoded values.
                        if (!empty($optionstrings)) {
                            $translatedoptions = [];
                            foreach ($optionstrings as $key => $stringkey) {
                                $translatedoptions[$key] = get_string($stringkey, 'mod_projetvet');
                            }
                            $selectoptions = ['' => get_string('choose')] + $translatedoptions;
                        } else {
                            // Fall back to hardcoded options if no optionstrings.
                            $selectoptions = ['' => get_string('choose')] + $options;
                        }

                        $mform->addElement('select', $fieldname, $field->name, $selectoptions);
                        break;

                    case 'autocomplete':
                        $options = $configdata['options'] ?? [];
                        $mform->addElement('autocomplete', $fieldname, $field->name, $options, [
                            'multiple' => true,
                            'noselectionstring' => get_string('choose'),
                        ]);
                        break;

                    case 'tagselect':
                        // Load grouped options from field_data table.
                        $groupedoptions = field_data::get_grouped_options($field->id);
                        $helptext = '';
                        if (!empty($configdata['helptext'])) {
                            $helptext = get_string('field_' . $field->idnumber . '_help', 'mod_projetvet');
                        }

                        $mform->addElement('tagselect', $fieldname, $field->name, [], [
                            'groupedoptions' => $groupedoptions,
                            'rowname' => $field->name,
                            'maxtags' => $configdata['maxtags'] ?? 0,
                            'helptext' => $helptext,
                        ]);
                        break;

                    case 'tagconfirm':
                        // Get the source field idnumber from configdata.
                        $sourcefielidnumber = $configdata['tagselect'] ?? '';
                        $sourcetags = [];
                        $lookupfieldid = 0;

                        // Find the source field and get its selected values.
                        if ($sourcefielidnumber && $entryid) {
                            $entry = entries::get_entry($entryid);
                            foreach ($entry->categories as $entrycategory) {
                                foreach ($entrycategory->fields as $entryfield) {
                                    if ($entryfield->idnumber === $sourcefielidnumber) {
                                        // Decode the JSON value to get selected tags.
                                        $decoded = json_decode($entryfield->value, true);
                                        $sourcetags = is_array($decoded) ? $decoded : [];
                                        // Get the field ID for lookup data.
                                        $lookupfieldid = $entryfield->id;
                                        break 2;
                                    }
                                }
                            }
                        }

                        $mform->addElement('tagconfirm', $fieldname, $field->name, [
                            'sourcefielidnumber' => $sourcefielidnumber,
                            'sourcetags' => $sourcetags,
                            'lookupfieldid' => $lookupfieldid,
                        ]);
                        break;

                    case 'checkbox':
                        $checkboxlabel = $configdata['label'] ?? '';
                        $labelstring = $configdata['labelstring'] ?? '';
                        if (!empty($labelstring)) {
                            try {
                                $checkboxlabel = get_string('checkbox_' . $labelstring, 'mod_projetvet');
                            } catch (moodle_exception $e) {
                                // String not found, fall back to configured label.
                                unset($e);
                            }
                        }
                        $mform->addElement('advcheckbox', $fieldname, $field->name, $checkboxlabel, null, [0, 1]);
                        break;

                    case 'filemanager':
                        // Get configuration for file manager options.
                        $maxfiles = $configdata['maxfiles'] ?? 50;
                        $maxbytes = $configdata['maxbytes'] ?? 0; // 0 means use course/site limit.
                        $acceptedtypes = $configdata['accepted_types'] ?? '*';
                        $subdirs = $configdata['subdirs'] ?? 0;

                        $filemanageroptions = [
                            'subdirs' => $subdirs,
                            'maxbytes' => $maxbytes,
                            'areamaxbytes' => 10485760, // 10MB.
                            'maxfiles' => $maxfiles,
                            'accepted_types' => $acceptedtypes,
                        ];

                        // If field cannot be edited, show files as static HTML instead of frozen filemanager.
                        if (!$caneditfield) {
                            $mform->addElement('static', $fieldname . '_static', $field->name, '');
                        } else {
                            $mform->addElement('filemanager', $fieldname, $field->name, null, $filemanageroptions);
                        }
                        break;

                    case 'hidden':
                        // Handle hidden fields for dates.
                        $action = $configdata['action'] ?? '';

                        if ($action === 'setcurrentdate') {
                            // Only add this hidden field if category entrystatus matches current entry status.
                            if ($category->entrystatus == $currententrystatus && $category->canedit) {
                                $mform->addElement('hidden', $fieldname);
                                $mform->setType($fieldname, PARAM_INT);
                                $mform->setDefault($fieldname, time());
                            } else {
                                // If not current entrystatus, it will be displayed as static text in set_data.
                                // Add a static element placeholder that will be populated with formatted date.
                                $mform->addElement('static', $fieldname . '_static', $field->name, '');
                            }
                        } else if ($action === 'setcurrentuser') {
                            // Only add this hidden field if category entrystatus matches current entry status.
                            if ($category->entrystatus == $currententrystatus && $category->canedit) {
                                $mform->addElement('hidden', $fieldname);
                                $mform->setType($fieldname, PARAM_INT);
                                $mform->setDefault($fieldname, $USER->id);
                            } else {
                                // If not current entrystatus, it will be displayed as static text in set_data.
                                // Add a static element placeholder that will be populated with formatted date.
                                $mform->addElement('static', $fieldname . '_static', $field->name, '');
                            }
                        } else {
                            // For other hidden fields, add them normally.
                            $mform->addElement('hidden', $fieldname);
                            $mform->setType($fieldname, PARAM_INT);
                        }
                        break;

                    case 'button':
                        // Get button configuration.
                        $buttontext = get_string($configdata['text'], 'projetvet');
                        $buttonicon = $configdata['icon'] ?? '';
                        $buttonstatus = $configdata['entrystatus'] ?? 0;
                        $buttonstyle = $configdata['style'] ?? 'secondary';
                        $buttonaction = $configdata['action'] ?? '';

                        // Create button label with icon if specified.
                        $buttonlabel = $buttontext;
                        if (!empty($buttonicon)) {
                            $buttonlabel = '<i class="fa ' . $buttonicon . '"></i> ' . $buttontext;
                        }

                        // Create button attributes.
                        $buttonattributes = [
                            'class' => 'projetvet-form-button',
                            'data-entrystatus' => $buttonstatus,
                            'data-fieldid' => $field->id,
                        ];

                        if (!empty($buttonicon)) {
                            $buttonattributes['data-icon'] = $buttonicon;
                        }

                        if (!empty($buttonaction)) {
                            $buttonattributes['data-action-type'] = $buttonaction;
                        }

                        if (!empty($configdata['emailaction'])) {
                            $buttonattributes['data-emailaction'] = $configdata['emailaction'];
                        }

                        if (!empty($configdata['submitpopup'])) {
                            $buttonattributes['data-submitpopup'] = $configdata['submitpopup'];
                        }

                        if (!empty($configdata['teachermessage'])) {
                            $buttonattributes['data-teachermessage'] = $configdata['teachermessage'];
                        }

                        if (!empty($configdata['studentmessage'])) {
                            $buttonattributes['data-studentmessage'] = $configdata['studentmessage'];
                        }

                        // Determine button styling based on style configuration.
                        $styleclass = 'btn-' . $buttonstyle;
                        $customclass = 'btn ' . $styleclass;

                        // Collect button element for grouping (don't add immediately).
                        $buttonelements[] = $mform->createElement('button', $fieldname, $buttonlabel, $buttonattributes, [
                            'customclassoverride' => $customclass . ' projetvet-form-button',
                        ]);
                        break;

                    case 'subset':
                        // Get subset configuration.
                        $subsetformsetidnumber = $configdata['formsetidnumber'] ?? '';
                        $buttontext = $configdata['buttontext'] ?? 'Add entry';

                        $mform->addElement('subset', $fieldname, $field->name, '', [
                            'subsetformsetidnumber' => $subsetformsetidnumber,
                            'parententryid' => $entryid,
                            'studentid' => $studentid,
                            'cmid' => $cmid,
                            'projetvetid' => $projetvetid,
                            'buttontext' => $buttontext,
                        ]);
                        break;

                    case 'html':
                        // Get HTML configuration.
                        $stringkey = $configdata['string'] ?? '';

                        $attributes = [
                            'stringkey' => $stringkey,
                            'studentid' => $studentid,
                            'cmid' => $cmid,
                        ];

                        // Add data-action attribute if present in configdata.
                        if (!empty($configdata['data-action'])) {
                            $attributes['data-action'] = $configdata['data-action'];
                        }
                        if (!empty($configdata['data-string'])) {
                            $attributes['data-string'] = $configdata['data-string'];
                        }
                        if (!empty($configdata['filter'])) {
                            $attributes['filter'] = $configdata['filter'];
                        }

                        $mform->addElement('html', $fieldname, $field->name, '', $attributes);
                        break;
                }
                $isrequired = !empty($configdata['required']) && $configdata['required'] == true;

                $hashelp = !empty($configdata['helpbutton']);
                if ($hashelp) {
                    $mform->addHelpButton($fieldname, 'field_' . $field->idnumber, 'mod_projetvet');
                }

                if ($isrequired && $caneditfield && $field->type !== 'button') {
                    $mform->addRule($fieldname, null, 'required', null, 'client');
                }

                if (!empty($field->description) && $field->type !== 'button') {
                    $mform->addHelpButton($fieldname, $field->idnumber, 'mod_projetvet');
                }

                if (!$caneditfield && $field->type !== 'filemanager' && $field->type !== 'hidden') {
                    if ($mform->elementExists($fieldname)) {
                        $mform->freeze($fieldname);
                    }
                }
                if ($readonly && $field->type !== 'filemanager' && $field->type !== 'hidden') {
                    if ($mform->elementExists($fieldname)) {
                        $mform->freeze($fieldname);
                    }
                }
            }
            // Add all button elements as a group if any were found.
            if (!empty($buttonelements) && ($category->entrystatus == $currententrystatus) && $category->canedit) {
                $mform->addGroup($buttonelements, 'buttongroup', '', [' '], false);
            }
        }
        if (!$hasexpanded && !empty($addedcategoryheaders)) {
            // If no category was expanded, expand the first visible category that was actually added.
            $mform->setExpanded(reset($addedcategoryheaders));
        }
    }

    /**
     * Set data for dynamic submission
     *
     * @return void
     */
    public function set_data_for_dynamic_submission(): void {
        global $USER;

        $formsetidnumber = $this->optional_param('formsetidnumber', 'activities', PARAM_ALPHANUMEXT);

        $data = [
            'cmid' => $this->optional_param('cmid', 0, PARAM_INT),
            'projetvetid' => $this->optional_param('projetvetid', 0, PARAM_INT),
            'studentid' => $this->optional_param('studentid', $USER->id, PARAM_INT),
            'entryid' => $this->optional_param('entryid', 0, PARAM_INT),
            'formsetidnumber' => $formsetidnumber,
            'readonly' => $this->optional_param('readonly', 0, PARAM_INT),
            'entrystatus' => 0, // Default to draft for new entries.
        ];

        // If editing an existing entry, load its data.
        if (!empty($data['entryid'])) {
            $entry = entries::get_entry($data['entryid']);
            // Set the entry status switch from the entry.
            $data['entrystatus'] = $entry->entrystatus;
            $context = $this->get_context_for_dynamic_submission();
            $currententrystatus = $entry->entrystatus;
            $structure = entries::get_form_structure($formsetidnumber, $currententrystatus, $context);

            foreach ($entry->categories as $category) {
                // Get the category object from structure to check permissions.
                $categoryobj = $this->get_category_by_id($structure, $category->id);

                foreach ($category->fields as $field) {
                    $fieldname = 'field_' . $field->idnumber;
                    $fieldobj = $this->get_field_by_id($structure, $field->id);

                    if (!$fieldobj) {
                        continue;
                    }

                    // Check if user can view this field.
                    $canviewfield = entries::can_view_field($fieldobj, $entry->studentid, $context);
                    if (!$canviewfield) {
                        // Skip this field if user cannot view it.
                        continue;
                    }

                    // Skip button fields - they don't store values and shouldn't have their labels overwritten.
                    if ($fieldobj->type === 'button') {
                        continue;
                    }

                    // Handle hidden fields with special date formatting.
                    if ($fieldobj->type === 'hidden') {
                        $fieldconfigdata = (array) $fieldobj->configdata;

                        $action = $fieldconfigdata['action'] ?? '';
                        $dateformat = $fieldconfigdata['dateformat'] ?? 'strftimedatetime';

                        if ($action === 'setcurrentdate') {
                            // Only set value if category entrystatus matches current entry status.
                            if ($categoryobj && $categoryobj->entrystatus == $currententrystatus) {
                                // Use existing value if set, otherwise use current time.
                                $fieldvalue = !empty($field->value) ? $field->value : time();
                                $data[$fieldname] = $fieldvalue;
                                $data[$fieldname . '_user'] = $USER->id;
                            } else if (!empty($field->value)) {
                                // Display as static formatted date for past entrystatus.
                                $data[$fieldname . '_static'] = userdate($field->value, get_string($dateformat, 'langconfig'));
                            }
                            // Skip further processing.
                            continue;
                        } else if ($action === 'setcurrentuser') {
                            // Only set value if category entrystatus matches current entry status.
                            if ($categoryobj && $categoryobj->entrystatus == $currententrystatus) {
                                // Use existing value if set, otherwise use current user.
                                $fieldvalue = !empty($field->value) ? $field->value : $USER->id;
                                $data[$fieldname] = $fieldvalue;
                            } else if (!empty($field->value)) {
                                // Display as static user fullname for past entrystatus.
                                $user = \core_user::get_user($field->value);
                                $data[$fieldname . '_static'] = fullname($user);
                            }
                            // Skip further processing.
                            continue;
                        } else if (!empty($field->value)) {
                            // For other hidden fields with a value.
                            $data[$fieldname] = $field->value;
                            continue;
                        } else {
                            // No value, skip this field.
                            continue;
                        }
                    }

                    // For autocomplete, tagselect, and tagconfirm fields, decode JSON to array.
                    if (in_array($fieldobj->type, ['autocomplete', 'tagselect', 'tagconfirm'])) {
                        $decoded = json_decode($field->value, true);
                        $fieldvalue = is_array($decoded) ? $decoded : [];
                    } else if ($fieldobj->type === 'filemanager') {
                        // Check if user can edit this field using the category object from structure.
                        $caneditfield = $categoryobj ? entries::can_edit_category($categoryobj, $currententrystatus, $context) :
                            true;

                        if (!$caneditfield && !empty($field->value)) {
                            // For frozen filemanager, show list of files as static HTML.
                            $data[$fieldname . '_static'] = $this->get_filemanager_display_html($context, $field->value);
                            // Don't set fieldvalue - we don't need the filemanager element.
                            continue;
                        }

                        // For editable filemanager, prepare the draft area with existing files.
                        // Get a new draft itemid for this specific field in this specific entry.
                        $draftitemid = file_get_submitted_draft_itemid($fieldname . '_' . $data['entryid']);

                        // If there's no submitted draft (i.e., we're loading the form),
                        // and there are existing files, copy them to a new draft area.
                        if (!empty($field->value)) {
                            file_prepare_draft_area(
                                $draftitemid,
                                $context->id,
                                'mod_projetvet',
                                'entry_files',
                                $field->value, // The permanent itemid from the database.
                                [
                                    'subdirs' => 0,
                                    'maxbytes' => 0,
                                    'maxfiles' => 50,
                                ]
                            );
                        }
                        $fieldvalue = $draftitemid;
                    } else {
                        if ($fieldobj->type == 'date' && $field->value == '') {
                            $fieldvalue = time();
                        } else {
                            $fieldvalue = $field->value;
                        }
                    }

                    // Check if this is a number field with prefillfrom config and no value yet.
                    if ($fieldobj->type === 'number' && empty($field->value)) {
                        $fieldconfigdata = (array) $fieldobj->configdata;

                        if (!empty($fieldconfigdata['prefillfrom'])) {
                            $prefillvalue = $this->get_field_value_by_idnumber($entry, $fieldconfigdata['prefillfrom']);
                            if ($prefillvalue !== null) {
                                $fieldvalue = $prefillvalue;
                            }
                        }
                    }

                    $data[$fieldname] = $fieldvalue;
                }
            }

            // Store rang value in hidden field for ECTS calculation.
            $rangvalue = $this->get_field_value_by_idnumber($entry, 'rang');
            if ($rangvalue !== null) {
                $data['rang_value'] = $rangvalue;
            }
        }

        parent::set_data((object) $data);
    }

    /**
     * Get field object by id from structure
     *
     * @param array $structure
     * @param int $fieldid
     * @return object|null
     */
    private function get_field_by_id($structure, $fieldid) {
        foreach ($structure as $category) {
            foreach ($category->fields as $field) {
                if ($field->id == $fieldid) {
                    return $field;
                }
            }
        }
        return null;
    }

    /**
     * Get category object by id from structure
     *
     * @param array $structure
     * @param int $categoryid
     * @return object|null
     */
    private function get_category_by_id($structure, $categoryid) {
        foreach ($structure as $category) {
            if ($category->id == $categoryid) {
                return $category;
            }
        }
        return null;
    }

    /**
     * Get field value by idnumber from entry object
     *
     * @param object $entry The entry object with categories and fields
     * @param string $idnumber The field idnumber to search for
     * @return mixed|null The field value if found, null otherwise
     */
    private function get_field_value_by_idnumber($entry, $idnumber) {
        foreach ($entry->categories as $category) {
            foreach ($category->fields as $field) {
                if ($field->idnumber === $idnumber) {
                    return $field->value;
                }
            }
        }
        return null;
    }

    /**
     * Get HTML display for frozen filemanager showing list of files
     *
     * @param context $context The context
     * @param int $itemid The file area itemid
     * @return string HTML string with file list
     */
    private function get_filemanager_display_html($context, $itemid) {
        global $OUTPUT;

        $fs = get_file_storage();
        $files = $fs->get_area_files(
            $context->id,
            'mod_projetvet',
            'entry_files',
            $itemid,
            'filename',
            false
        );

        $filesdata = [];
        foreach ($files as $file) {
            $filesdata[] = [
                'filename' => $file->get_filename(),
                'filesize' => display_size($file->get_filesize()),
                'url' => \moodle_url::make_pluginfile_url(
                    $context->id,
                    'mod_projetvet',
                    'entry_files',
                    $itemid,
                    $file->get_filepath(),
                    $file->get_filename()
                )->out(),
                'mimetype' => $file->get_mimetype(),
            ];
        }

        return $OUTPUT->render_from_template('mod_projetvet/filemanager_files', [
            'hasfiles' => !empty($filesdata),
            'files' => $filesdata,
        ]);
    }
}
