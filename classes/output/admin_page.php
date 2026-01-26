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

use core_reportbuilder\system_report_factory;
use mod_projetvet\reportbuilder\local\systemreports\admin_students;
use mod_projetvet\reportbuilder\local\systemreports\admin_teachers;
use renderer_base;
use renderable;
use templatable;

/**
 * Admin page renderable class.
 *
 * @package    mod_projetvet
 * @copyright  2025 Bas Brands <bas@sonsbeekmedia.nl>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class admin_page implements renderable, templatable {
    /**
     * @var object $moduleinstance The module instance.
     */
    protected $moduleinstance;

    /**
     * @var object $cm The course module.
     */
    protected $cm;

    /**
     * @var object $context The context.
     */
    protected $context;

    /**
     * @var bool $filterstudents Whether to filter students without teachers.
     */
    protected $filterstudents;

    /**
     * @var bool $filterteachers Whether to filter teachers with capacity.
     */
    protected $filterteachers;

    /**
     * Constructor.
     *
     * @param object $moduleinstance The module instance
     * @param object $cm The course module
     * @param object $context The context
     * @param bool $filterstudents Whether to filter students without teachers
     * @param bool $filterteachers Whether to filter teachers with capacity
     */
    public function __construct($moduleinstance, $cm, $context, $filterstudents = false, $filterteachers = false) {
        $this->moduleinstance = $moduleinstance;
        $this->cm = $cm;
        $this->context = $context;
        $this->filterstudents = $filterstudents;
        $this->filterteachers = $filterteachers;
    }

    /**
     * Export data for template.
     *
     * @param renderer_base $output
     * @return array
     */
    public function export_for_template(renderer_base $output) {
        // Prepare students toggle context.
        $studentstogglecontext = [
            'id' => 'filter-students-without-teacher',
            'checked' => (bool)$this->filterstudents,
            'label' => get_string('filter_students_without_teacher', 'mod_projetvet'),
            'dataattributes' => [
                ['name' => 'action', 'value' => 'filter-students'],
                ['name' => 'cmid', 'value' => $this->cm->id],
                ['name' => 'projetvetid', 'value' => $this->moduleinstance->id],
            ],
        ];

        // Prepare teachers toggle context.
        $teacherstogglecontext = [
            'id' => 'filter-teachers-with-capacity',
            'checked' => (bool)$this->filterteachers,
            'label' => get_string('filter_teachers_with_capacity', 'mod_projetvet'),
            'dataattributes' => [
                ['name' => 'action', 'value' => 'filter-teachers'],
                ['name' => 'cmid', 'value' => $this->cm->id],
                ['name' => 'projetvetid', 'value' => $this->moduleinstance->id],
            ],
        ];

        // Generate students report.
        $studentsreport = system_report_factory::create(admin_students::class, $this->context, '', '', 0, [
            'cmid' => $this->cm->id,
            'projetvetid' => $this->moduleinstance->id,
            'filterwithoutteacher' => $this->filterstudents,
        ]);

        // Generate teachers report.
        $teachersreport = system_report_factory::create(admin_teachers::class, $this->context, '', '', 0, [
            'cmid' => $this->cm->id,
            'projetvetid' => $this->moduleinstance->id,
            'filterwithcapacity' => $this->filterteachers,
        ]);

        return [
            'cmid' => $this->cm->id,
            'projetvetid' => $this->moduleinstance->id,
            'studentstoggle' => $studentstogglecontext,
            'studentsreport' => $studentsreport->output(),
            'teacherstoggle' => $teacherstogglecontext,
            'teachersreport' => $teachersreport->output(),
        ];
    }
}
