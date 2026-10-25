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

namespace mod_projetvet\reportbuilder\local\systemreports;

use core_cohort\reportbuilder\local\entities\cohort;
use core_reportbuilder\local\entities\user;
use core_reportbuilder\local\helpers\database;
use core_reportbuilder\local\report\action;
use core_reportbuilder\system_report;
use lang_string;
use moodle_url;
use pix_icon;

/**
 * Student list system report for projetvet
 *
 * @package    mod_projetvet
 * @copyright  2025 Bas Brands <bas@sonsbeekmedia.nl>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class students extends system_report {
    /**
     * Initialise report
     */
    protected function initialise(): void {
        global $DB, $USER;

        $cmid = $this->get_parameter('cmid', 0, PARAM_INT);
        $projetvetid = $this->get_parameter('projetvetid', 0, PARAM_INT);

        // Get course module and context.
        $cm = get_coursemodule_from_id('projetvet', $cmid, 0, false, MUST_EXIST);
        $context = \context_module::instance($cm->id);

        // Main user entity.
        $entityuser = new user();
        $entityuseralias = $entityuser->get_table_alias('user');

        $this->set_main_table('user', $entityuseralias);
        $this->add_entity($entityuser);

        // Join the cohort entity to the user through cohort membership.
        $entitycohort = new cohort();
        $cohortalias = $entitycohort->get_table_alias('cohort');
        $cohortmemberalias = database::generate_alias('cohortmember');
        $this->add_entity($entitycohort
            ->add_join("LEFT JOIN {cohort_members} {$cohortmemberalias}
                            ON {$cohortmemberalias}.userid = {$entityuseralias}.id")
            ->add_join("LEFT JOIN {cohort} {$cohortalias}
                            ON {$cohortalias}.id = {$cohortmemberalias}.cohortid"));

        // Base fields needed for actions.
        $this->add_base_fields("{$entityuseralias}.id, {$entityuseralias}.firstname, {$entityuseralias}.lastname,
            {$entityuseralias}.email");

        // Restrict the base user table to the students the current user may view.
        // Admins can see every student in the course; tutors only their own students.
        if (has_capability('mod/projetvet:admin', $context)) {
            // Scope to all enrolled students via a JOIN (no in-memory id list).
            $studentsjoin = \mod_projetvet\local\api\groups::get_all_students_join($cmid, "{$entityuseralias}.id");

            if ($studentsjoin->cannotmatchanyrows) {
                // No students can match, add an impossible condition.
                $this->add_base_condition_sql("1 = 0");
            } else {
                $this->add_student_scope_join($studentsjoin);
            }
        } else {
            // Their own students and the students they follow as a substitute.
            $studentids = \mod_projetvet\local\api\substitutions::get_students_followed_by_tutor($USER->id, $projetvetid);

            if (empty($studentids)) {
                // No students for this tutor, add impossible condition.
                $this->add_base_condition_sql("1 = 0");
            } else {
                // Show only students assigned to this tutor.
                [$insql, $inparams] = $DB->get_in_or_equal($studentids, SQL_PARAMS_NAMED, database::generate_param_name());
                $this->add_base_condition_sql("{$entityuseralias}.id $insql", $inparams);
            }
        }

        $this->add_columns();
        $this->add_filters();
        $this->add_actions();

        $this->set_downloadable(false);

        // Set pagination (default is 30, you can change this).
        $this->set_default_per_page(30);
    }

    /**
     * Apply a core {@see \core\dml\sql_join} fragment as the report student scope.
     *
     * Reportbuilder requires every parameter to carry a rbparam-style name, but the core join
     * fragments use their own prefixes (ej1_*, eu1_*, ...). This helper renames the fragment's
     * parameters to unique reportbuilder names and applies both the joins and the where clauses
     * using that single consistent mapping.
     *
     * @param \core\dml\sql_join $join The core join fragment to apply.
     */
    private function add_student_scope_join(\core\dml\sql_join $join): void {
        // Map each core parameter name to a unique reportbuilder parameter name.
        $parammap = [];
        $params = [];
        foreach ($join->params as $name => $value) {
            $newname = database::generate_param_name();
            $parammap[$name] = $newname;
            $params[$newname] = $value;
        }

        $rename = static function (string $name) use ($parammap): string {
            return $parammap[$name] ?? $name;
        };

        $joinsql  = database::sql_replace_parameter_names($join->joins, array_keys($join->params), $rename);
        $wheresql = database::sql_replace_parameter_names($join->wheres, array_keys($join->params), $rename);

        $this->add_join($joinsql, $params);
        $this->add_base_condition_sql($wheresql, $params);
    }

    /**
     * Validates access to view this report
     *
     * @return bool
     */
    protected function can_view(): bool {
        $cmid = $this->get_parameter('cmid', 0, PARAM_INT);
        if (!$cmid) {
            return false;
        }

        $cm = get_coursemodule_from_id('projetvet', $cmid);
        if (!$cm) {
            return false;
        }

        $context = \context_module::instance($cm->id);
        return has_capability('mod/projetvet:viewallactivities', $context);
    }

    /**
     * Add columns to the report
     */
    protected function add_columns(): void {
        global $DB;

        $entityuser = $this->get_entity('user');
        $entityuseralias = $entityuser->get_table_alias('user');
        $entitycohort = $this->get_entity('cohort');
        $projetvetid = $this->get_parameter('projetvetid', 0, PARAM_INT);
        $cmid = $this->get_parameter('cmid', 0, PARAM_INT);

        // Fullname with picture.
        $this->add_column($entityuser->get_column('fullnamewithpicture'));

        // Promotion custom profile field.
        if (array_key_exists('profilefield_promotion', $entityuser->get_columns())) {
            $this->add_column($entityuser->get_column('profilefield_promotion')
                ->set_title(new lang_string('promotion', 'mod_projetvet')));
        }

        // Cohort name.
        $this->add_column($entitycohort->get_column('name')
            ->set_title(new lang_string('cohort', 'core_cohort')));

        // Email.
        $this->add_column($entityuser->get_column('email'));

        // Tutoring: principal tutor and active substitute, relative to the viewing tutor.
        $tutoringcolumn = (new \core_reportbuilder\local\report\column(
            'tutoring',
            new lang_string('substitution_tutoring', 'mod_projetvet'),
            $entityuser->get_entity_name()
        ))
            ->add_joins($entityuser->get_joins())
            ->add_field("{$entityuseralias}.id", 'userid_tutoring')
            ->set_type(\core_reportbuilder\local\report\column::TYPE_TEXT)
            ->set_is_sortable(false)
            ->add_callback(static function ($value, $row) use ($projetvetid): string {
                global $USER;
                return self::get_tutoring_label((int) $row->userid_tutoring, $projetvetid, (int) $USER->id);
            });

        $this->add_column($tutoringcolumn);

        // Total ECTS - create custom column.
        $totalectscolumn = (new \core_reportbuilder\local\report\column(
            'totalects',
            new lang_string('totalcredits', 'mod_projetvet'),
            $entityuser->get_entity_name()
        ))
            ->add_joins($entityuser->get_joins())
            ->add_field("{$entityuseralias}.id", 'userid_ects')
            ->set_type(\core_reportbuilder\local\report\column::TYPE_INTEGER)
            ->set_is_sortable(true)
            ->add_callback(static function ($value, $row) use ($projetvetid): int {
                return \mod_projetvet\utils::get_student_total_ects($projetvetid, $row->userid_ects);
            });

        $this->add_column($totalectscolumn);

        // Get the date of the latest validated tutor interview for each student.
        $lastinterviewparam = database::generate_param_name();
        $this->add_join(
            "LEFT JOIN (
                SELECT pfe.studentid, MAX(pfd.intvalue) AS lastinterviewdate
                  FROM {projetvet_form_entry} pfe
                  JOIN {projetvet_form_set} pfs ON pfs.id = pfe.formsetid
                  JOIN {projetvet_form_field} pff ON pff.idnumber = 'date_facetoface'
                  JOIN {projetvet_form_data} pfd ON pfd.entryid = pfe.id AND pfd.fieldid = pff.id
                 WHERE pfe.projetvetid = :{$lastinterviewparam}
                   AND pfs.idnumber = 'facetoface'
                   AND pfe.entrystatus >= 2
                 GROUP BY pfe.studentid
            ) lastinterview ON lastinterview.studentid = {$entityuseralias}.id",
            [$lastinterviewparam => $projetvetid]
        );

        $lastinterviewcolumn = (new \core_reportbuilder\local\report\column(
            'lastinterviewdate',
            new lang_string('lastinterviewdate', 'mod_projetvet'),
            $entityuser->get_entity_name()
        ))
            ->add_field('lastinterview.lastinterviewdate')
            ->set_type(\core_reportbuilder\local\report\column::TYPE_TIMESTAMP)
            ->set_is_sortable(true)
            ->add_callback(static function ($value): string {
                if (empty($value)) {
                    return get_string('lastinterviewdate_empty', 'mod_projetvet');
                }
                return userdate($value, get_string('strftimedatefullshort', 'core_langconfig'));
            });

        $this->add_column($lastinterviewcolumn);

        // Face-to-face count - create custom column.
        $facetofacecolumn = (new \core_reportbuilder\local\report\column(
            'facetofacesessions',
            new lang_string('facetofacesessions', 'mod_projetvet'),
            $entityuser->get_entity_name()
        ))
            ->add_joins($entityuser->get_joins())
            ->add_field("{$entityuseralias}.id", 'userid2')
            ->set_type(\core_reportbuilder\local\report\column::TYPE_INTEGER)
            ->set_is_sortable(true)
            ->add_callback(static function ($value, $row) use ($projetvetid): int {
                global $DB;
                // Get all form entries for this student in facetoface.
                $entries = $DB->get_records_sql(
                    "SELECT pfe.id
                     FROM {projetvet_form_entry} pfe
                     JOIN {projetvet_form_set} pfs ON pfe.formsetid = pfs.id
                     WHERE pfe.studentid = :studentid
                     AND pfe.projetvetid = :projetvetid
                     AND pfs.idnumber = :idnumber
                     AND pfe.entrystatus > 0",
                    ['studentid' => $row->userid2, 'projetvetid' => $projetvetid, 'idnumber' => 'facetoface']
                );
                return count($entries);
            });

        $this->add_column($facetofacecolumn);

        // Action required column - shows icon if there are entries needing teacher action.
        $actionrequiredcolumn = (new \core_reportbuilder\local\report\column(
            'actionrequired',
            new lang_string('actionrequired', 'mod_projetvet'),
            $entityuser->get_entity_name()
        ))
            ->add_joins($entityuser->get_joins())
            ->add_field("{$entityuseralias}.id", 'userid_action')
            ->set_type(\core_reportbuilder\local\report\column::TYPE_TEXT)
            ->set_is_sortable(false)
            ->add_callback(static function ($value, $row) use ($projetvetid): string {
                if (\mod_projetvet\utils::student_has_pending_teacher_action($projetvetid, $row->userid_action)) {
                    return \html_writer::tag(
                        'i',
                        '',
                        [
                            'class' => 'icon fa fa-exclamation-circle text-info',
                            'title' => get_string('actionrequired', 'mod_projetvet'),
                        ]
                    );
                }
                return '';
            });

        $this->add_column($actionrequiredcolumn);

        $this->set_initial_sort_column('user:fullnamewithpicture', SORT_ASC);
    }

    /**
     * Describe how a student is tutored, from the point of view of the viewing user.
     *
     * - followed as a substitute by the viewer: the principal tutor being replaced;
     * - the viewer's student temporarily followed by another tutor: the active substitute;
     * - otherwise the principal tutor (or "Principal tutor" for the viewer's own students).
     *
     * @param int $studentid
     * @param int $projetvetid
     * @param int $viewerid
     * @return string HTML
     */
    public static function get_tutoring_label(int $studentid, int $projetvetid, int $viewerid): string {
        $primary = \mod_projetvet\local\api\groups::get_student_primary_tutor($studentid, $projetvetid);
        $primaryname = $primary ? fullname($primary) : get_string('notutorassigned', 'mod_projetvet');
        $isprimary = $primary && (int) $primary->id === $viewerid;
        $substitution = \mod_projetvet\local\api\substitutions::get_open_substitution($studentid, $projetvetid);

        if (!$substitution) {
            return $isprimary ? get_string('substitution_tutoring_primary', 'mod_projetvet') : s($primaryname);
        }

        $summary = \mod_projetvet\output\student_info::get_substitution_summary($substitution);
        if ((int) $substitution->get('substitutetutorid') === $viewerid) {
            $badge = \html_writer::span(get_string('substitution_tutoring_badge', 'mod_projetvet'), 'badge bg-info');
            return $badge . ' ' . s(get_string('substitution_tutoring_replacing', 'mod_projetvet', (object) [
                'name' => $primaryname,
                'period' => \mod_projetvet\output\student_info::get_substitution_period($substitution),
            ]));
        }

        $label = $isprimary ? get_string('substitution_tutoring_primary', 'mod_projetvet') : $primaryname;
        return s($label) . \html_writer::div(
            s(get_string('substitution_tutoring_followedby', 'mod_projetvet', $summary)),
            'small text-muted'
        );
    }

    /**
     * Add filters to the report
     */
    protected function add_filters(): void {
        $entityuser = $this->get_entity('user');

        // Fullname filter.
        $this->add_filter($entityuser->get_filter('fullname'));

        // Email filter.
        $this->add_filter($entityuser->get_filter('email'));

        // Promotion custom profile field filter.
        if (array_key_exists('profilefield_promotion', $entityuser->get_filters())) {
            $this->add_filter($entityuser->get_filter('profilefield_promotion')
                ->set_header(new lang_string('promotion', 'mod_projetvet')));
        }

        // Cohort filter.
        $entitycohort = $this->get_entity('cohort');
        $this->add_filter($entitycohort->get_filter('name')
            ->set_header(new lang_string('cohort', 'core_cohort')));
    }

    /**
     * Add actions to the report
     */
    protected function add_actions(): void {
        $cmid = $this->get_parameter('cmid', 0, PARAM_INT);

        // View student activities action.
        $this->add_action((new action(
            new moodle_url('/mod/projetvet/view.php', ['id' => $cmid, 'studentid' => ':id']),
            new pix_icon('i/search', ''),
            [],
            false,
            new lang_string('viewactivities', 'mod_projetvet'),
        )));
    }

    /**
     * Get CSS class for each row to make it clickable via JavaScript.
     *
     * @param \stdClass $row
     * @return string
     */
    public function get_row_class(\stdClass $row): string {
        return 'clickable-row';
    }
}
