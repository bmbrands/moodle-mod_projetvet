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

use mod_projetvet\local\api\archive;
use renderer_base;
use renderable;
use templatable;

/**
 * View page renderable class.
 *
 * @package    mod_projetvet
 * @copyright  2025 Bas Brands <bas@sonsbeekmedia.nl>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class view_page implements renderable, templatable {
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
     * @var int $studentid The student ID being viewed.
     */
    protected $studentid;

    /**
     * @var bool $isteacher Whether the viewer is a teacher.
     */
    protected $isteacher;

    /**
     * Constructor.
     *
     * @param object $moduleinstance The module instance
     * @param object $cm The course module
     * @param object $context The context
     * @param int $studentid The student ID being viewed
     * @param bool $isteacher Whether the viewer is a teacher
     */
    public function __construct($moduleinstance, $cm, $context, $studentid, $isteacher) {
        $this->moduleinstance = $moduleinstance;
        $this->cm = $cm;
        $this->context = $context;
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
        global $USER;

        $data = [
            'isteacher' => $this->isteacher,
            'cmid' => $this->cm->id,
            'projetvetid' => $this->moduleinstance->id,
            'studentid' => $this->studentid,
        ];

        // Activities section.
        $data['activitiesheading'] = get_string('activities', 'mod_projetvet');
        if (!$this->isteacher) {
            $data['showactivitiesbutton'] = true;
            $data['activitiesbuttonlabel'] = get_string('newactivities', 'mod_projetvet');
        }

        $data['activitiesinfobox'] = get_string('activitiesinfobox', 'mod_projetvet');

        $data += $this->get_archive_data();
        $data['showactionbuttons'] = !empty($data['showactivitiesbutton']) || !empty($data['showarchivebutton']);

        // Create activities report.
        $activitiesreport = \core_reportbuilder\system_report_factory::create(
            \mod_projetvet\reportbuilder\local\systemreports\entries::class,
            $this->context,
            'mod_projetvet',
            'entries',
            1,
            [
                'cmid' => $this->cm->id,
                'projetvetid' => $this->moduleinstance->id,
                'studentid' => $this->studentid,
                'formsetidnumber' => 'activities',
            ]
        );
        $data['activitiesreport'] = $activitiesreport->output();

        // Face-to-face section.
        $data['facetofaceheading'] = get_string('facetofacesessions', 'mod_projetvet');
        if (!$this->isteacher) {
            $data['showfacetofacebutton'] = true;
            $data['facetofacebuttonlabel'] = get_string('newfacetoface', 'mod_projetvet');
        }

        $data['facetofaceinfobox'] = get_string('facetofaceinfobox', 'mod_projetvet');

        // Create facetoface report.
        $facetofacereport = \core_reportbuilder\system_report_factory::create(
            \mod_projetvet\reportbuilder\local\systemreports\entries::class,
            $this->context,
            'mod_projetvet',
            'entries',
            2,
            [
                'cmid' => $this->cm->id,
                'projetvetid' => $this->moduleinstance->id,
                'studentid' => $this->studentid,
                'formsetidnumber' => 'facetoface',
            ]
        );
        $data['facetofacereport'] = $facetofacereport->output();

        return $data;
    }

    /**
     * Template data for the archive of projects already validated with the previous system.
     *
     * @return array
     */
    protected function get_archive_data(): array {
        global $USER;

        $projetvetid = $this->moduleinstance->id;
        $status = archive::get_status($projetvetid, $this->studentid);
        $data = [];

        if (!$this->isteacher && $status === archive::STATUS_SUBMITTED) {
            // The student is responsible for contacting the tutor: remind them while waiting.
            $data['archivewaitingmessage'] = get_string('archivewaitingtutor', 'mod_projetvet');
        }

        if (!archive::can_open($this->context, $projetvetid, $this->studentid, $USER->id)) {
            return $data;
        }

        if ($this->studentid == $USER->id) {
            $label = get_string('archivebutton_student', 'mod_projetvet');
        } else if ($status === archive::STATUS_SUBMITTED) {
            $label = get_string('archivebutton_review', 'mod_projetvet');
        } else {
            $label = get_string('archivebutton_view', 'mod_projetvet');
        }

        $data['showarchivebutton'] = true;
        $data['archivebuttonlabel'] = $label;
        $data['archiveformsetidnumber'] = archive::FORMSET;
        return $data;
    }
}
