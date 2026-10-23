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
 * Callback implementations for Projetvet
 *
 * Documentation: {@link https://moodledev.io/docs/apis/plugintypes/mod}
 *
 * @package    mod_projetvet
 * @copyright  2025 Bas Brands <bas@sonsbeekmedia.nl>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * List of features supported in module
 *
 * @param string $feature FEATURE_xx constant for requested feature
 * @return mixed True if module supports feature, false if not, null if doesn't know or string for the module purpose.
 */
function projetvet_supports($feature) {
    switch ($feature) {
        case FEATURE_GROUPS:
            return true;
        case FEATURE_GROUPINGS:
            return true;
        case FEATURE_MOD_INTRO:
            return true;
        case FEATURE_SHOW_DESCRIPTION:
            return true;
        case FEATURE_BACKUP_MOODLE2:
            return true;
        case FEATURE_MOD_PURPOSE:
            return MOD_PURPOSE_CONTENT;
        default:
            return null;
    }
}

/**
 * Add Projetvet instance
 *
 * Given an object containing all the necessary data, (defined by the form in mod_form.php)
 * this function will create a new instance and return the id of the instance
 *
 * @param stdClass $moduleinstance form data
 * @param mod_projetvet_mod_form $form the form
 * @return int new instance id
 */
function projetvet_add_instance($moduleinstance, $form = null) {
    global $DB;

    $moduleinstance->timecreated = time();
    $moduleinstance->timemodified = time();

    $id = $DB->insert_record('projetvet', $moduleinstance);
    $completiontimeexpected = !empty($moduleinstance->completionexpected) ? $moduleinstance->completionexpected : null;
    \core_completion\api::update_completion_date_event(
        $moduleinstance->coursemodule,
        'projetvet',
        $id,
        $completiontimeexpected
    );
    return $id;
}

/**
 * Updates an instance of the Projetvet in the database.
 *
 * Given an object containing all the necessary data (defined in mod_form.php),
 * this function will update an existing instance with new data.
 *
 * @param stdClass $moduleinstance An object from the form in mod_form.php
 * @param mod_projetvet_mod_form $form The form
 * @return bool True if successful, false otherwis
 */
function projetvet_update_instance($moduleinstance, $form = null) {
    global $DB;

    $moduleinstance->timemodified = time();
    $moduleinstance->id = $moduleinstance->instance;

    $DB->update_record('projetvet', $moduleinstance);

    $completiontimeexpected = !empty($moduleinstance->completionexpected) ? $moduleinstance->completionexpected : null;
    \core_completion\api::update_completion_date_event(
        $moduleinstance->coursemodule,
        'projetvet',
        $moduleinstance->id,
        $completiontimeexpected
    );

    return true;
}

/**
 * Removes an instance of the Projetvet from the database.
 *
 * @param int $id Id of the module instance
 * @return bool True if successful, false otherwise
 */
function projetvet_delete_instance($id) {
    global $DB;

    $record = $DB->get_record('projetvet', ['id' => $id]);
    if (!$record) {
        return false;
    }

    // Delete all calendar events.
    $events = $DB->get_records('event', ['modulename' => 'projetvet', 'instance' => $record->id]);
    foreach ($events as $event) {
        calendar_event::load($event)->delete();
    }

    // Delete the substitute tutors history of the instance.
    $DB->delete_records('projetvet_substitutions', ['projetvetid' => $record->id]);

    // Delete the instance.
    $DB->delete_records('projetvet', ['id' => $id]);

    return true;
}

/**
 * Check if the module has any update that affects the current user since a given time.
 *
 * @param  cm_info $cm course module data
 * @param  int $from the time to check updates from
 * @param  array $filter  if we need to check only specific updates
 * @return stdClass an object with the different type of areas indicating if they were updated or not
 */
function mod_projetvet_check_updates_since(cm_info $cm, $from, $filter = []) {
    $updates = course_check_module_updates_since($cm, $from, ['content'], $filter);
    return $updates;
}

/**
 * Serves the files from the mod_projetvet file areas
 *
 * @package mod_projetvet
 * @category files
 *
 * @param stdClass $course the course object
 * @param stdClass $cm the course module object
 * @param stdClass $context the context
 * @param string $filearea the name of the file area
 * @param array $args extra arguments (itemid, path)
 * @param bool $forcedownload whether or not force download
 * @param array $options additional options affecting the file serving
 * @return bool false if the file not found, just send the file otherwise and do not return anything
 */
function projetvet_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, array $options = []) {
    // Handle ECTS guide PDF from admin settings (system context).
    if ($filearea === 'ectsguide') {
        $syscontext = context_system::instance();
        if ($context->id != $syscontext->id) {
            return false;
        }

        require_login();

        $itemid = array_shift($args);
        $filename = array_pop($args);
        $filepath = $args ? '/' . implode('/', $args) . '/' : '/';

        $fs = get_file_storage();
        $file = $fs->get_file($syscontext->id, 'mod_projetvet', $filearea, $itemid, $filepath, $filename);
        if (!$file) {
            return false;
        }

        send_stored_file($file, 86400, 0, $forcedownload, $options);
        return;
    }

    // Handle entry files (module context).
    if ($context->contextlevel != CONTEXT_MODULE) {
        return false;
    }

    if ($filearea !== 'entry_files') {
        return false;
    }

    require_login($course, true, $cm);

    $itemid = array_shift($args);
    $filename = array_pop($args);
    $filepath = $args ? '/' . implode('/', $args) . '/' : '/';

    $fs = get_file_storage();
    $file = $fs->get_file($context->id, 'mod_projetvet', $filearea, $itemid, $filepath, $filename);
    if (!$file) {
        return false;
    }

    send_stored_file($file, 86400, 0, $forcedownload, $options);
}

/**
 * Extends the settings navigation with the mod_projetvet settings.
 *
 * This function is called when the context for the page is a mod_projetvet module.
 * This is not called by AJAX so it is safe to rely on the $PAGE.
 *
 * @param settings_navigation $settingsnav {@see settings_navigation}
 * @param navigation_node $projetvetnode {@see navigation_node}
 */
function projetvet_extend_settings_navigation($settingsnav, $projetvetnode = null) {
    global $PAGE;

    if (has_capability('mod/projetvet:admin', $PAGE->cm->context)) {
        $assignmenturl = new moodle_url('/mod/projetvet/assignments.php', ['id' => $PAGE->cm->id]);
        $assignmentnode = navigation_node::create(
            get_string('assignments_menu_label', 'mod_projetvet'),
            $assignmenturl,
            navigation_node::TYPE_SETTING,
            '',
            'adminpage',
        );
        $projetvetnode->add_node($assignmentnode);

        $dashboardurl = new moodle_url('/mod/projetvet/dashboard.php', ['id' => $PAGE->cm->id]);
        $dashboardnode = navigation_node::create(
            get_string('dashboard', 'mod_projetvet'),
            $dashboardurl,
            navigation_node::TYPE_SETTING,
            '',
            'dashboardpage',
        );
        $projetvetnode->add_node($dashboardnode);
    }
}

/**
 * Extends the user settings navigation with the tutor practical info settings.
 *
 * This function is called by core when building the user settings navigation tree.
 * The node is only added for the logged-in user on their own preferences page,
 * and only if the user is a tutor (primary owner of at least one projetvet group).
 *
 * @param navigation_node $usersetting The user settings navigation node.
 * @param stdClass $user The user whose settings are being built.
 * @param context_user $usercontext The user context.
 * @param stdClass $course The course being displayed.
 * @param context_course $coursecontext The course context.
 * @return void
 */
function mod_projetvet_extend_navigation_user_settings(navigation_node $usersetting, $user, $usercontext, $course, $coursecontext) {
    global $DB, $PAGE, $USER;

    // Only inject the node on the user's own preferences page.
    $onpreferencepage = $PAGE->url->compare(new moodle_url('/user/preferences.php'), URL_MATCH_BASE);
    if (!$onpreferencepage || $user->id != $USER->id) {
        return;
    }

    // Only tutors (primary owners of at least one projetvet group) get the node.
    if (!$DB->record_exists('projetvet_groups', ['ownerid' => $user->id])) {
        return;
    }

    // Add the entry under a dedicated "Projetvet" section so it does not fall
    // into the default "Other" ("Divers") section (same pattern as core badges).
    $section = $usersetting->add(
        get_string('pluginname', 'mod_projetvet'),
        null,
        navigation_node::TYPE_CONTAINER,
        null,
        'projetvet'
    );
    $section->add(
        get_string('practicalinfo_settings', 'mod_projetvet'),
        new moodle_url('/mod/projetvet/tutor_info.php'),
        navigation_node::TYPE_SETTING
    );
}

/**
 * Check whether a user may view a specific student's data in a Projetvet activity.
 *
 * A user may always view their own data. Viewing another student's data requires the
 * viewallactivities capability, held by teachers, tutors and managers. This is the privacy
 * boundary that prevents a student from opening another student's page by guessing the
 * studentid parameter.
 *
 * @param int $viewerid The id of the user attempting to view the data.
 * @param int $studentid The id of the student whose data is to be viewed.
 * @param context_module $context The module context of the activity.
 * @return bool True if the viewer may view the student's data.
 */
function projetvet_user_can_view_student(int $viewerid, int $studentid, context_module $context): bool {
    // A user may always view their own data.
    if ($viewerid === $studentid) {
        return true;
    }

    // Viewing another student's data requires the viewallactivities capability
    // (teachers, tutors and managers).
    return has_capability('mod/projetvet:viewallactivities', $context, $viewerid);
}

/**
 * Merge required/mintags flags into the stored configdata of report-stage fields.
 *
 * This is used by the 2026092501 upgrade savepoint (ticket #912). It merges the
 * new flags into the existing configdata JSON of the affected fields, preserving
 * any site-specific values (e.g. textarea rows) and tolerating missing or invalid
 * JSON. The step is idempotent: re-running it on already-merged rows is a no-op.
 *
 * @return void
 */
function projetvet_merge_report_field_configdata(): void {
    global $DB;

    $requiredfields = [
        'start_date',
        'end_date',
        'actions_summary',
        'progress_on',
        'must_still_progress_on',
    ];
    $mintagsfields = [
        'practiced_competencies',
    ];

    foreach ($requiredfields as $idnumber) {
        $record = $DB->get_record('projetvet_form_field', ['idnumber' => $idnumber]);
        if (!$record) {
            continue;
        }
        $configdata = json_decode($record->configdata ?? '', true);
        if (!is_array($configdata)) {
            $configdata = [];
        }
        $configdata['required'] = true;
        $newconfig = json_encode($configdata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $DB->set_field('projetvet_form_field', 'configdata', $newconfig, ['id' => $record->id]);
    }

    foreach ($mintagsfields as $idnumber) {
        $record = $DB->get_record('projetvet_form_field', ['idnumber' => $idnumber]);
        if (!$record) {
            continue;
        }
        $configdata = json_decode($record->configdata ?? '', true);
        if (!is_array($configdata)) {
            $configdata = [];
        }
        $configdata['mintags'] = 2;
        $newconfig = json_encode($configdata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $DB->set_field('projetvet_form_field', 'configdata', $newconfig, ['id' => $record->id]);
    }

    // Purge the activitystructures cache so the new flags take effect immediately.
    \cache::make('mod_projetvet', 'activitystructures')->purge();
}

/**
 * Pre-fill the final ECTS field with the tutor-agreed credits value.
 *
 * This is used by the 2026100200 upgrade savepoint (ticket #917). It merges the
 * prefillfrom flag into the stored configdata of the final_ects field, preserving
 * any site-specific values and tolerating missing or invalid JSON. The step is
 * idempotent: re-running it on already-merged rows is a no-op.
 *
 * @return void
 */
function projetvet_merge_final_ects_prefill(): void {
    global $DB;

    $record = $DB->get_record('projetvet_form_field', ['idnumber' => 'final_ects']);
    if (!$record) {
        return;
    }
    $configdata = json_decode($record->configdata ?? '', true);
    if (!is_array($configdata)) {
        $configdata = [];
    }
    if (($configdata['prefillfrom'] ?? null) === 'credits') {
        return;
    }
    $configdata['prefillfrom'] = 'credits';
    $newconfig = json_encode($configdata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $DB->set_field('projetvet_form_field', 'configdata', $newconfig, ['id' => $record->id]);

    // Purge the activitystructures cache so the new flag takes effect immediately.
    \cache::make('mod_projetvet', 'activitystructures')->purge();
}
