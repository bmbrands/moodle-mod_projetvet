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

namespace mod_projetvet\output;

use mod_projetvet\local\persistent\form_set;
use mod_projetvet\local\persistent\form_entry;
use mod_projetvet\local\api\entries;
use core\url as moodle_url;
use renderer_base;
use renderable;
use templatable;

/**
 * Student info renderable class.
 *
 * @package    mod_projetvet
 * @copyright  2025 Bas Brands <bas@sonsbeekmedia.nl>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class student_info implements renderable, templatable {
    /**
     * @var object $moduleinstance The module instance.
     */
    protected $moduleinstance;

    /**
     * @var object $cm The course module.
     */
    protected $cm;

    /**
     * @var int $studentid The student ID.
     */
    protected $studentid;

    /**
     * @var bool $isteacher Whether the user is a teacher.
     */
    protected $isteacher;

    /**
     * Constructor.
     *
     * @param object $moduleinstance The module instance
     * @param object $cm The course module
     * @param int $studentid The student ID
     * @param bool $isteacher Whether the user is a teacher
     */
    public function __construct($moduleinstance, $cm, $studentid, $isteacher) {
        $this->moduleinstance = $moduleinstance;
        $this->cm = $cm;
        $this->studentid = $studentid;
        $this->isteacher = $isteacher;
    }

    /**
     * Export data for template.
     *
     * @param renderer_base $output
     * @return array
     */
    public function export_for_template(renderer_base $output) {
        // Get chart data using the chart_data class.
        $totalects = chart_data::get_total_ects($this->moduleinstance->id, $this->studentid);
        $targetects = get_config('mod_projetvet', 'target_ects') ?: 20;

        $ectsbyrank = chart_data::get_ects_by_rank($this->moduleinstance->id, $this->studentid);
        $targetranka = get_config('mod_projetvet', 'target_rank_a_percentage') ?: 75;
        $targetrankb = get_config('mod_projetvet', 'target_rank_b_percentage') ?: 25;

        $completedinterviews = chart_data::get_completed_interviews($this->moduleinstance->id, $this->studentid);
        $targetinterviews = get_config('mod_projetvet', 'target_interviews') ?: 20;

        // Get the student's tutor.
        $tutor = \mod_projetvet\local\api\groups::get_student_primary_tutor($this->studentid, $this->moduleinstance->id);
        $tutorname = $tutor ? fullname($tutor) : get_string('notutorassigned', 'mod_projetvet');

        // Get the student's promotion from custom profile field.
        $promotion = \mod_projetvet\utils::get_user_profile_field($this->studentid, 'promotion');
        $promoyear = $promotion ?: 'Not set';

        // Get the student's cohort (year in course).
        $cohort = \mod_projetvet\utils::get_user_cohort($this->studentid);
        $yearincourse = $cohort ?: 'Not set';

        $data = [
            'infotable' => [
                'rows' => [
                    [
                        'label' => get_string('studentname', 'mod_projetvet'),
                        'value' => fullname(\core_user::get_user($this->studentid)),
                    ],
                    [
                        'label' => get_string('promoyear', 'mod_projetvet'),
                        'value' => $promoyear,
                    ],
                    [
                        'label' => get_string('yearincourse', 'mod_projetvet'),
                        'value' => $yearincourse,
                    ],
                ],
            ],
            'charts' => [
                $this->get_chart_data($totalects, $targetects, get_string('totalcredits', 'mod_projetvet')),
                $this->get_rank_chart_data($ectsbyrank, $targetranka, $targetrankb, get_string('creditsbyrank', 'mod_projetvet')),
                $this->get_chart_data($completedinterviews, $targetinterviews, get_string('tutorinterview', 'mod_projetvet')),
            ],
        ];

        // Teacher row.
        $teacherrow = ['label' => get_string('tutor', 'mod_projetvet')];
        $teacherrow['value'] = $tutorname;
        $teacherrow['hasbutton'] = true;
        $teacherrow['buttontext'] = get_string('practicalinfo', 'mod_projetvet');
        $teacherrow['buttonaction'] = 'activity-entry-form';
        $teacherrow['cmid'] = $this->cm->id;
        $teacherrow['projetvetid'] = $this->moduleinstance->id;
        $teacherrow['studentid'] = $this->studentid;
        $teacherrow['formsetidnumber'] = 'teacherinfo';
        $teacherrow['readonly'] = $this->isteacher ? 0 : 1;

        $teacherformset = form_set::get_record(['idnumber' => 'teacherinfo']);
        if ($teacherformset) {
            $teacherentry = form_entry::get_record([
                'projetvetid' => $this->moduleinstance->id,
                'studentid' => $this->studentid,
                'formsetid' => $teacherformset->get('id'),
            ]);
            if ($teacherentry) {
                $teacherrow['entryid'] = $teacherentry->get('id');
            }
        }

        $data['infotable']['rows'][] = $teacherrow;

        // Thesis subject row.
        $thesisrow = ['label' => get_string('thesissubject', 'mod_projetvet')];
        $thesisrow['hasbutton'] = !$this->isteacher;
        $thesisrow['buttontext'] = $this->isteacher ? get_string('view') : get_string('setsubject', 'mod_projetvet');
        $thesisrow['buttonaction'] = 'activity-entry-form';
        $thesisrow['cmid'] = $this->cm->id;
        $thesisrow['projetvetid'] = $this->moduleinstance->id;
        $thesisrow['studentid'] = $this->studentid;
        $thesisrow['formsetidnumber'] = 'thesis';
        $thesisrow['readonly'] = $this->isteacher ? 1 : 0;

        // Get the thesis entry if it exists.
        $checkedicon = '<i class="fa fa-sm text-success fa-check-square"></i>';
        $thesisformset = form_set::get_record(['idnumber' => 'thesis']);
        if ($thesisformset) {
            $thesisentry = form_entry::get_record([
                'projetvetid' => $this->moduleinstance->id,
                'studentid' => $this->studentid,
                'formsetid' => $thesisformset->get('id'),
            ]);
            if ($thesisentry) {
                $thesisrow['hasbutton'] = true;
                $thesisrow['entryid'] = $thesisentry->get('id');

                // Get the thesis subject field value.
                $entrycontent = entries::get_entry($thesisentry->get('id'));
                $thesissubject = $this->get_field_value($entrycontent, 'thesissubject_field');
                if ($thesissubject) {
                    $thesisrow['value'] = $checkedicon . ' ' . format_text($thesissubject, FORMAT_PLAIN);
                }
            } else {
                $thesisrow['value'] = get_string('nothesissubjectset', 'mod_projetvet');
            }
        }

        $data['infotable']['rows'][] = $thesisrow;

        // Mobility row.
        $mobilityrow = ['label' => get_string('internationalmobility', 'mod_projetvet')];
        $mobilityrow['hasbutton'] = !$this->isteacher;
        $mobilityrow['buttontext'] = $this->isteacher ? get_string('view') : get_string('settitle', 'mod_projetvet');
        $mobilityrow['buttonaction'] = 'activity-entry-form';
        $mobilityrow['cmid'] = $this->cm->id;
        $mobilityrow['projetvetid'] = $this->moduleinstance->id;
        $mobilityrow['studentid'] = $this->studentid;
        $mobilityrow['formsetidnumber'] = 'mobility';
        $mobilityrow['readonly'] = $this->isteacher ? 1 : 0;

        $mobilityrealized = false;

        // Check the mobility formset for mobilityerasmus or mobilityfmp fields.
        $mobilityformset = form_set::get_record(['idnumber' => 'mobility']);
        if ($mobilityformset) {
            $mobilityentry = form_entry::get_record([
                'projetvetid' => $this->moduleinstance->id,
                'studentid' => $this->studentid,
                'formsetid' => $mobilityformset->get('id'),
            ]);
            if ($mobilityentry) {
                $mobilityrow['hasbutton'] = true;
                $mobilityrow['entryid'] = $mobilityentry->get('id');

                // Get the mobility title field value.
                $entrycontent = entries::get_entry($mobilityentry->get('id'));
                $mobilityerasmus = $this->get_field_value($entrycontent, 'mobilityerasmus');
                $mobilityfmp = $this->get_field_value($entrycontent, 'mobilityfmp');
                if ($mobilityerasmus || $mobilityfmp) {
                    $mobilityrealized = true;
                }
            }
        }

        // Also check the activity formset for mobility_completed checkbox.
        if (!$mobilityrealized) {
            $activityformset = form_set::get_record(['idnumber' => 'activities']);
            if ($activityformset) {
                // Get all activity entries for this student with entrystatus >= 2.
                $activityentries = form_entry::get_records([
                    'projetvetid' => $this->moduleinstance->id,
                    'studentid' => $this->studentid,
                    'formsetid' => $activityformset->get('id'),
                ]);

                foreach ($activityentries as $activityentry) {
                    if ($activityentry->get('entrystatus') >= 2) {
                        $entrycontent = entries::get_entry($activityentry->get('id'));
                        $mobilitycompleted = $this->get_field_value($entrycontent, 'mobility_completed');
                        if ($mobilitycompleted) {
                            $mobilityrealized = true;
                            break;
                        }
                    }
                }
            }
        }

        // Set the mobility row value based on whether mobility was realized.
        if ($mobilityrealized) {
            $mobilityrow['value'] = $checkedicon . ' ' . get_string('mobilityrealized', 'mod_projetvet');
        } else {
            $mobilityrow['value'] = get_string('mobilitynotrealizedyet', 'mod_projetvet');
        }

        $data['infotable']['rows'][] = $mobilityrow;
                // Show back link for teachers viewing a student.
        if ($this->isteacher) {
            $data['showbacklink'] = true;
            $data['backurl'] = new moodle_url('/mod/projetvet/view.php', ['id' => $this->cm->id]);
        }

        return $data;
    }

    /**
     * Get chart data for a circular progress chart.
     *
     * @param int $current The current value
     * @param int $total The total value
     * @param string $label The label
     * @return array
     */
    protected function get_chart_data($current, $total, $label) {
        $radius = 45;
        $circumference = 2 * pi() * $radius;
        $percentage = $total > 0 ? round(($current / $total) * 100) : 0;
        $offset = $circumference - ($percentage / 100) * $circumference;

        return [
            'current' => $current,
            'total' => $total,
            'percentage' => $percentage,
            'label' => $label,
            'radius' => $radius,
            'circumference' => $circumference,
            'offset' => $offset,
            'isrank' => false,
        ];
    }

    /**
     * Get chart data for a rank-based circular progress chart.
     *
     * @param array $ectsbyrank Array with 'rank_a' and 'rank_b' values
     * @param int $targetranka Target percentage for rank A
     * @param int $targetrankb Target percentage for rank B
     * @param string $label The label
     * @return array
     */
    protected function get_rank_chart_data($ectsbyrank, $targetranka, $targetrankb, $label) {
        $radius = 45;
        $circumference = 2 * pi() * $radius;

        $ranka = $ectsbyrank['rank_a'];
        $rankb = $ectsbyrank['rank_b'];

        // Get total target ECTS from settings (same as first chart).
        $targetects = get_config('mod_projetvet', 'target_ects') ?: 20;

        // Calculate fill percentages based on total target ECTS.
        // E.g., 3 ECTS out of 20 target = 15% fill.
        $percentagea = $targetects > 0 ? round(($ranka / $targetects) * 100) : 0;
        $percentageb = $targetects > 0 ? round(($rankb / $targetects) * 100) : 0;

        // Offset for rank A (clockwise from top).
        $offseta = $circumference - ($percentagea / 100) * $circumference;

        // Offset for rank B (counterclockwise).
        $offsetb = $circumference - ($percentageb / 100) * $circumference;

        return [
            'isrank' => true,
            'label' => $label,
            'radius' => $radius,
            'circumference' => $circumference,
            'rank_a' => $ranka,
            'rank_b' => $rankb,
            'target_rank_a' => $targetranka,
            'target_rank_b' => $targetrankb,
            'percentage_a' => $percentagea,
            'percentage_b' => $percentageb,
            'offset_a' => $offseta,
            'offset_b' => $offsetb,
        ];
    }

    /**
     * Get a field value from entry content by field idnumber.
     *
     * @param \stdClass $entrycontent The entry content from entries::get_entry()
     * @param string $fieldidnumber The field idnumber to search for
     * @return mixed|null The field value or null if not found
     */
    protected function get_field_value($entrycontent, $fieldidnumber) {
        foreach ($entrycontent->categories as $category) {
            foreach ($category->fields as $field) {
                if ($field->idnumber === $fieldidnumber) {
                    return $field->value;
                }
            }
        }
        return null;
    }
}
