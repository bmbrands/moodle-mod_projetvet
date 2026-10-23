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

namespace mod_projetvet\reportbuilder\local\entities;

use core_reportbuilder\local\entities\base;
use core_reportbuilder\local\filters\select;
use core_reportbuilder\local\helpers\database;
use core_reportbuilder\local\report\column;
use core_reportbuilder\local\report\filter;
use lang_string;
use mod_projetvet\local\persistent\group_member;
use mod_projetvet\local\persistent\substitution;
use mod_projetvet\local\persistent\teacher_rating;

/**
 * Teacher capacity entity for projetvet reports
 *
 * Provides the rating, target capacity, current student count and gap columns shared by the
 * assignments teachers reports. The values are computed with correlated sub-queries against the
 * rating and group tables, scoped to the projetvet instance the report was created for, so no
 * temporary table or cache is required.
 *
 * The report must assign its own user table alias to this entity before calling
 * {@see base::add_entity()} so that the sub-queries correlate against the report's main user table.
 *
 * @package    mod_projetvet
 * @copyright  2026 Laurent David <laurent@call-learning.fr>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class teacher extends base {
    /**
     * Constructor.
     *
     * @param int $projetvetid The projetvet instance id the teacher data is scoped to.
     */
    public function __construct(
        /** @var int $projetvetid The projetvet instance id the teacher data is scoped to. */
        private readonly int $projetvetid,
    ) {
    }

    /**
     * Initialise the entity.
     *
     * @return self
     */
    public function initialise(): self {
        foreach ($this->get_available_columns() as $column) {
            $this->add_column($column);
        }

        foreach ($this->get_available_filters() as $filter) {
            $this->add_filter($filter);
        }

        foreach ($this->get_available_conditions() as $condition) {
            $this->add_condition($condition);
        }

        return $this;
    }

    /**
     * The list of table names this entity uses.
     *
     * The user table is declared because the sub-queries correlate against the report's main
     * user table alias, assigned by the report via {@see base::set_table_alias()}.
     *
     * @return string[]
     */
    protected function get_default_tables(): array {
        return [
            'user',
            teacher_rating::TABLE,
            group_member::TABLE,
            'projetvet_groups',
            'user_info_data',
            'user_info_field',
            substitution::TABLE,
        ];
    }

    /**
     * The default machine-readable name for this entity.
     *
     * @return lang_string
     */
    protected function get_default_entity_title(): lang_string {
        return new lang_string('teacher', 'mod_projetvet');
    }

    /**
     * Returns the columns this entity makes available to reports.
     *
     * @return column[]
     */
    protected function get_available_columns(): array {
        $columns = [];

        // Rating column. The raw rating value is localized at render time so sorting happens on the
        // stable value, not on a translated string. Teachers without an explicit rating default to average.
        $ratingpvparam = database::generate_param_name();
        $columns[] = (new column(
            'rating',
            new lang_string('teacher_rating', 'mod_projetvet'),
            $this->get_entity_name()
        ))
            ->set_type(column::TYPE_TEXT)
            ->add_field($this->get_rating_sql($ratingpvparam), 'rating', [$ratingpvparam => $this->projetvetid])
            ->set_is_sortable(true)
            ->add_callback(static function (?string $value): string {
                return teacher_rating::get_rating_string_for((string)$value);
            });

        // Tutor availability status. The raw numeric value is used for sorting, while the
        // callback displays a short, colour-coded label with the full status as its accessible
        // label and tooltip.
        $statuspvparam = database::generate_param_name();
        $columns[] = (new column(
            'status',
            new lang_string('teacher_status', 'mod_projetvet'),
            $this->get_entity_name()
        ))
            ->set_type(column::TYPE_INTEGER)
            ->add_field($this->get_status_sql($statuspvparam), 'status', [$statuspvparam => $this->projetvetid])
            ->set_is_sortable(true)
            ->add_callback(static function (?int $value): string {
                $status = (int) $value;
                $shortlabel = teacher_rating::get_status_short_string_for($status);
                $longlabel = teacher_rating::get_status_string_for($status);
                $badgeclass = teacher_rating::get_status_badge_class_for($status);

                if ($shortlabel === '' || $longlabel === '' || $badgeclass === '') {
                    return '';
                }

                return \html_writer::span($shortlabel, 'badge ' . $badgeclass, [
                    'aria-label' => $longlabel,
                    'title' => $longlabel,
                ]);
            });

        // Target capacity column, derived from the rating.
        $targetpvparam = database::generate_param_name();
        $columns[] = (new column(
            'target',
            new lang_string('teacher_target', 'mod_projetvet'),
            $this->get_entity_name()
        ))
            ->set_type(column::TYPE_INTEGER)
            ->add_field($this->get_capacity_sql($targetpvparam), 'target', [$targetpvparam => $this->projetvetid])
            ->set_is_sortable(true);

        // Current student count column.
        $currentpvparam = database::generate_param_name();
        $currentmtparam = database::generate_param_name();
        $columns[] = (new column(
            'current',
            new lang_string('teacher_current', 'mod_projetvet'),
            $this->get_entity_name()
        ))
            ->set_type(column::TYPE_INTEGER)
            ->add_field(
                $this->get_current_students_sql($currentpvparam, $currentmtparam),
                'current',
                [
                    $currentpvparam => $this->projetvetid,
                    $currentmtparam => group_member::TYPE_STUDENT,
                ]
            )
            ->set_is_sortable(true);

        // Gap column = target capacity - current student count. The capacity part and the
        // current student count part each need their own instance id parameter, because a
        // named query parameter may only occur once in a query.
        $capacitypvparam = database::generate_param_name();
        $groupspvparam = database::generate_param_name();
        $mtparam = database::generate_param_name();
        $columns[] = (new column(
            'gap',
            new lang_string('teacher_gap', 'mod_projetvet'),
            $this->get_entity_name()
        ))
            ->set_type(column::TYPE_INTEGER)
            ->add_field(
                $this->get_gap_sql($capacitypvparam, $groupspvparam, $mtparam),
                'gap',
                [
                    $capacitypvparam => $this->projetvetid,
                    $groupspvparam => $this->projetvetid,
                    $mtparam => group_member::TYPE_STUDENT,
                ]
            )
            ->set_is_sortable(true);

        // A1 acceptance column. The raw stored value (1/0) is localized at render time so
        // sorting happens on the stable value, and tutors without an explicit rating record
        // default to accepting A1 students. The A1 student count is carried as a helper
        // field so the render callback can report the existing A1 assignments that became
        // inconsistent when a tutor switched the value to "no".
        $acceptsa1pvparam = database::generate_param_name();
        $a1countpvparam = database::generate_param_name();
        $acceptsa1mtparam = database::generate_param_name();
        $acceptsa1fieldparam = database::generate_param_name();
        $acceptsa1valueparam = database::generate_param_name();
        $columns[] = (new column(
            'acceptsa1',
            new lang_string('tutor_a1_acceptance', 'mod_projetvet'),
            $this->get_entity_name()
        ))
            ->set_type(column::TYPE_INTEGER)
            ->add_field($this->get_acceptsa1_sql($acceptsa1pvparam), 'acceptsa1', [$acceptsa1pvparam => $this->projetvetid])
            ->add_field(
                $this->get_a1_students_sql($a1countpvparam, $acceptsa1mtparam, $acceptsa1fieldparam, $acceptsa1valueparam),
                'a1studentscount',
                [
                    $a1countpvparam => $this->projetvetid,
                    $acceptsa1mtparam => group_member::TYPE_STUDENT,
                    $acceptsa1fieldparam => \mod_projetvet\utils::PROMOTION_FIELD,
                    $acceptsa1valueparam => \mod_projetvet\utils::PROMOTION_A1,
                ]
            )
            ->set_is_sortable(true)
            ->add_callback(static function (?int $value, $row): string {
                $accepts = ((int) $value) === teacher_rating::ACCEPTS_A1_YES;
                $label = $accepts
                    ? get_string('a1accepts_yes', 'mod_projetvet')
                    : get_string('a1accepts_no', 'mod_projetvet');

                // Inconsistency warning for managers: the tutor no longer accepts A1
                // students but still has A1 students assigned.
                $a1studentscount = (int) ($row->a1studentscount ?? 0);
                if (!$accepts && $a1studentscount > 0) {
                    $title = get_string('a1inconsistency_warning', 'mod_projetvet', $a1studentscount);
                    $label .= ' ' . \html_writer::span($a1studentscount . ' A1', 'badge bg-warning text-dark', ['title' => $title]);
                }

                return $label;
            });

        // Open substitutions of the tutor's students. The status is carried as a helper field to
        // flag the return of the principal tutor: available again while substitutions are still open.
        $substitutionspvparam = database::generate_param_name();
        $substitutionsstatusparam = database::generate_param_name();
        $substitutionsteacherstatusparam = database::generate_param_name();
        $columns[] = (new column(
            'substitutions',
            new lang_string('substitution_open_column', 'mod_projetvet'),
            $this->get_entity_name()
        ))
            ->set_type(column::TYPE_INTEGER)
            ->add_field(
                $this->get_open_substitutions_sql($substitutionspvparam, $substitutionsstatusparam),
                'opensubstitutions',
                [
                    $substitutionspvparam => $this->projetvetid,
                    $substitutionsstatusparam => substitution::STATUS_OPEN,
                ]
            )
            ->add_field(
                $this->get_status_sql($substitutionsteacherstatusparam),
                'substitutionsteacherstatus',
                [$substitutionsteacherstatusparam => $this->projetvetid]
            )
            ->set_is_sortable(true)
            ->add_callback(static function (?int $value, $row): string {
                $count = (int) $value;
                if ($count === 0) {
                    return '';
                }
                $label = (string) $count;
                $status = (int) ($row->substitutionsteacherstatus ?? teacher_rating::STATUS_ACTIVE_OPEN);
                if (\mod_projetvet\output\substitutions_page::is_available_status($status)) {
                    $label .= ' ' . \html_writer::span(
                        get_string('substitution_return_badge', 'mod_projetvet'),
                        'badge bg-warning text-dark',
                        ['title' => get_string('substitution_return_badge_help', 'mod_projetvet')]
                    );
                }
                return $label;
            });

        return $columns;
    }

    /**
     * Returns the SQL expression counting the open substitutions of the students of the teacher
     * referenced by the report's main user table, as principal tutor.
     *
     * @param string $pvparam Reportbuilder parameter name holding the projetvet instance id
     * @param string $statusparam Reportbuilder parameter name holding the open substitution status
     * @return string
     */
    public function get_open_substitutions_sql(string $pvparam, string $statusparam): string {
        $useralias = $this->get_table_alias('user');

        return '(SELECT COUNT(1) FROM {' . substitution::TABLE . '} '
            . "WHERE primarytutorid = {$useralias}.id AND projetvetid = :{$pvparam} AND status = :{$statusparam})";
    }

    /**
     * Returns the filters this entity makes available to reports.
     *
     * @return filter[]
     */
    protected function get_available_filters(): array {
        $statuspvparam = database::generate_param_name();

        return [
            (new filter(
                select::class,
                'status',
                new lang_string('teacher_status', 'mod_projetvet'),
                $this->get_entity_name(),
                $this->get_status_sql($statuspvparam),
                [$statuspvparam => $this->projetvetid]
            ))->set_options([
                teacher_rating::STATUS_ACTIVE_OPEN => get_string('teacher_status_active_open', 'mod_projetvet'),
                teacher_rating::STATUS_ACTIVE_CLOSED => get_string('teacher_status_active_closed', 'mod_projetvet'),
                teacher_rating::STATUS_TEMPORARILY_UNAVAILABLE => get_string(
                    'teacher_status_temporarily_unavailable',
                    'mod_projetvet'
                ),
                teacher_rating::STATUS_INACTIVE => get_string('teacher_status_inactive', 'mod_projetvet'),
            ]),
        ];
    }

    /**
     * Returns the SQL expression resolving the rating of the teacher referenced by the report's
     * main user table, defaulting to the average rating when no explicit rating exists.
     *
     * @param string $pvparam Reportbuilder parameter name holding the projetvet instance id,
     *     see {@see database::generate_param_name()}.
     * @return string
     */
    public function get_rating_sql(string $pvparam): string {
        $useralias = $this->get_table_alias('user');

        return "COALESCE((SELECT rating FROM {" . teacher_rating::TABLE . "} "
            . "WHERE userid = {$useralias}.id AND projetvetid = :{$pvparam}), '"
            . teacher_rating::RATING_AVERAGE . "')";
    }

    /**
     * Returns the SQL expression resolving the availability status of the teacher referenced by
     * the report's main user table, defaulting to active and open when no explicit settings exist.
     *
     * @param string $pvparam Reportbuilder parameter name holding the projetvet instance id,
     *     see {@see database::generate_param_name()}.
     * @return string
     */
    public function get_status_sql(string $pvparam): string {
        $useralias = $this->get_table_alias('user');

        return 'COALESCE((SELECT status FROM {' . teacher_rating::TABLE . '} '
            . "WHERE userid = {$useralias}.id AND projetvetid = :{$pvparam}), "
            . teacher_rating::STATUS_ACTIVE_OPEN . ')';
    }

    /**
     * Returns the SQL expression resolving the target capacity of the teacher referenced by the
     * report's main user table. The mapping from rating to capacity is generated from the
     * teacher_rating capacity constants so it stays in sync with {@see teacher_rating::get_capacity()}.
     *
     * @param string $pvparam Reportbuilder parameter name holding the projetvet instance id,
     *     see {@see database::generate_param_name()}.
     * @return string
     */
    public function get_capacity_sql(string $pvparam): string {
        $capacities = [
            teacher_rating::RATING_EXPERT => teacher_rating::CAPACITY_EXPERT,
            teacher_rating::RATING_AVERAGE => teacher_rating::CAPACITY_AVERAGE,
            teacher_rating::RATING_NOVICE => teacher_rating::CAPACITY_NOVICE,
        ];

        $whens = [];
        foreach ($capacities as $rating => $capacity) {
            $whens[] = "WHEN '{$rating}' THEN {$capacity}";
        }

        return 'CASE ' . $this->get_rating_sql($pvparam) . ' ' . implode(' ', $whens)
            . ' ELSE ' . teacher_rating::CAPACITY_AVERAGE . ' END';
    }

    /**
     * Returns the SQL expression counting the students assigned to the teacher referenced by the
     * report's main user table in the given projetvet instance.
     *
     * @param string $pvparam Reportbuilder parameter name holding the projetvet instance id,
     *     see {@see database::generate_param_name()}.
     * @param string $mtparam Reportbuilder parameter name holding the member type,
     *     see {@see database::generate_param_name()}.
     * @return string
     */
    public function get_current_students_sql(string $pvparam, string $mtparam): string {
        $useralias = $this->get_table_alias('user');

        return "(SELECT COUNT(1) FROM {" . group_member::TABLE . "} gm "
            . "JOIN {projetvet_groups} g ON g.id = gm.groupid "
            . "WHERE g.ownerid = {$useralias}.id AND g.projetvetid = :{$pvparam} "
            . "AND gm.membertype = :{$mtparam})";
    }

    /**
     * Returns the SQL expression for the remaining capacity of the teacher referenced by the
     * report's main user table: target capacity minus current student count.
     *
     * The capacity part and the current student count part each receive their own instance id
     * parameter, because a named query parameter may only occur once in a query even though
     * both hold the same value.
     *
     * @param string $capacitypvparam Reportbuilder parameter name holding the projetvet instance id
     *     used by the capacity part, see {@see database::generate_param_name()}.
     * @param string $groupspvparam Reportbuilder parameter name holding the projetvet instance id
     *     used by the current student count part, see {@see database::generate_param_name()}.
     * @param string $mtparam Reportbuilder parameter name holding the member type,
     *     see {@see database::generate_param_name()}.
     * @return string
     */
    public function get_gap_sql(string $capacitypvparam, string $groupspvparam, string $mtparam): string {
        return $this->get_capacity_sql($capacitypvparam) . ' - ' . $this->get_current_students_sql($groupspvparam, $mtparam);
    }

    /**
     * Returns the SQL expression resolving the A1 student acceptance value of the teacher
     * referenced by the report's main user table, defaulting to "yes" when no explicit
     * rating exists.
     *
     * @param string $pvparam Reportbuilder parameter name holding the projetvet instance id,
     *     see {@see database::generate_param_name()}.
     * @return string
     */
    public function get_acceptsa1_sql(string $pvparam): string {
        $useralias = $this->get_table_alias('user');

        return "COALESCE((SELECT acceptsa1 FROM {" . teacher_rating::TABLE . "} "
            . "WHERE userid = {$useralias}.id AND projetvetid = :{$pvparam}), "
            . teacher_rating::ACCEPTS_A1_YES . ")";
    }

    /**
     * Returns the SQL expression counting the A1 students assigned to the teacher referenced
     * by the report's main user table in the given projetvet instance.
     *
     * A student is considered A1 when the value of the "promotion" custom profile field is
     * "A1", as configured by the institution (see {@see \mod_projetvet\utils::is_a1_student()}).
     *
     * @param string $pvparam Reportbuilder parameter name holding the projetvet instance id,
     *     see {@see database::generate_param_name()}.
     * @param string $mtparam Reportbuilder parameter name holding the member type,
     *     see {@see database::generate_param_name()}.
     * @param string $fieldnameparam Reportbuilder parameter name holding the custom profile
     *     field shortname, see {@see database::generate_param_name()}.
     * @param string $fieldvalueparam Reportbuilder parameter name holding the promotion value
     *     identifying A1 students, see {@see database::generate_param_name()}.
     * @return string
     */
    public function get_a1_students_sql(
        string $pvparam,
        string $mtparam,
        string $fieldnameparam,
        string $fieldvalueparam
    ): string {
        $useralias = $this->get_table_alias('user');

        return "(SELECT COUNT(1) FROM {" . group_member::TABLE . "} gm "
            . "JOIN {projetvet_groups} g ON g.id = gm.groupid "
            . "JOIN {user_info_data} uid ON uid.userid = gm.userid "
            . "JOIN {user_info_field} uif ON uif.id = uid.fieldid AND uif.shortname = :{$fieldnameparam} "
            . "WHERE g.ownerid = {$useralias}.id "
            . "AND g.projetvetid = :{$pvparam} "
            . "AND gm.membertype = :{$mtparam} "
            . "AND uid.data = :{$fieldvalueparam})";
    }
}
