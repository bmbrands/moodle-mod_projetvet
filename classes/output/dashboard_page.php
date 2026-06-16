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
use mod_projetvet\reportbuilder\local\systemreports\dashboard;
use renderable;
use renderer_base;
use templatable;

/**
 * Dashboard page renderable class.
 *
 * @package    mod_projetvet
 * @copyright  2026 Laurent David <laurent@call-learning.fr>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class dashboard_page implements renderable, templatable {
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
     * Constructor.
     *
     * @param object $moduleinstance The module instance
     * @param object $cm The course module
     * @param object $context The context
     */
    public function __construct($moduleinstance, $cm, $context) {
        $this->moduleinstance = $moduleinstance;
        $this->cm = $cm;
        $this->context = $context;
    }

    /**
     * Export data for template.
     *
     * @param renderer_base $output
     * @return array
     */
    public function export_for_template(renderer_base $output) {
        $report = system_report_factory::create(dashboard::class, $this->context, '', '', 0, [
            'cmid' => $this->cm->id,
            'projetvetid' => $this->moduleinstance->id,
        ]);

        return [
            'dashboardreport' => $report->output(),
        ];
    }
}
