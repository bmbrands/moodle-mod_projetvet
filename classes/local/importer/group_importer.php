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

namespace mod_projetvet\local\importer;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/csvlib.class.php');
use csv_import_reader;
use moodle_exception;
use mod_projetvet\local\api\groups;
use mod_projetvet\local\persistent\projetvet_group;
use mod_projetvet\local\persistent\group_member;
use mod_projetvet\local\persistent\teacher_rating;

/**
 * Group importer for CSV uploads
 *
 * @package   mod_projetvet
 * @copyright 2026 Bas Brands <bas@sonsbeekmedia.nl>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class group_importer {
    /**
     * @var int $courseid
     */
    protected $courseid;

    /**
     * @var int $cmid
     */
    protected $cmid;

    /**
     * @var int $projetvetid
     */
    protected $projetvetid;

    /**
     * Constructor
     *
     * @param int $courseid
     * @param int $cmid
     * @param int $projetvetid
     */
    public function __construct(int $courseid, int $cmid, int $projetvetid) {
        $this->courseid = $courseid;
        $this->cmid = $cmid;
        $this->projetvetid = $projetvetid;
    }

    /**
     * Import groups from a CSV file
     *
     * Expected CSV format:
     * teacher,teacherrating,secondaryteacher,student1,student2,student3,...
     *
     * @param string $filepath
     * @param string $delimiter
     * @param string $encoding
     * @throws moodle_exception
     */
    public function import(string $filepath, string $delimiter = 'comma', string $encoding = 'UTF-8') {
        global $DB;

        $iid = csv_import_reader::get_new_iid('projetvet_groups');
        $csvreader = new csv_import_reader($iid, 'projetvet_groups');

        $content = file_get_contents($filepath);
        if ($content === false) {
            throw new moodle_exception('cannotreadfile', 'mod_projetvet', '', $filepath);
        }

        try {
            $csvreader->load_csv_content($content, $encoding, $delimiter);
            $columns = $csvreader->get_columns();

            if (empty($columns) || !in_array('teacher', $columns)) {
                throw new moodle_exception('invalidcsvstructure', 'mod_projetvet');
            }

            // Find column indices.
            $teacheridx = array_search('teacher', $columns);
            $ratingidx = array_search('teacherrating', $columns);
            $secondaryteacheridx = array_search('secondaryteacher', $columns);

            // Find student columns (student1, student2, etc.).
            $studentindices = [];
            foreach ($columns as $idx => $colname) {
                if (preg_match('/^student(\d+)$/', $colname)) {
                    $studentindices[] = $idx;
                }
            }

            $csvreader->init();
            while ($row = $csvreader->next()) {
                if (empty($row[$teacheridx])) {
                    continue; // Skip rows without a teacher.
                }

                $teacherusername = trim($row[$teacheridx]);
                $teacher = $DB->get_record('user', ['username' => $teacherusername], '*', IGNORE_MISSING);

                if (!$teacher) {
                    debugging("Teacher not found: $teacherusername", DEBUG_DEVELOPER);
                    continue;
                }

                // Set teacher rating if provided.
                if ($ratingidx !== false && !empty($row[$ratingidx])) {
                    $ratingvalue = trim($row[$ratingidx]);
                    // Validate rating value.
                    $validratings = [
                        teacher_rating::RATING_EXPERT,
                        teacher_rating::RATING_AVERAGE,
                        teacher_rating::RATING_NOVICE,
                    ];
                    if (in_array($ratingvalue, $validratings)) {
                        groups::set_teacher_rating($teacher->id, $this->projetvetid, $ratingvalue);
                    }
                }

                // Get or create group for this teacher.
                $existinggroups = projetvet_group::get_by_owner($teacher->id, $this->projetvetid);
                if (!empty($existinggroups)) {
                    $group = reset($existinggroups);
                } else {
                    // Create new group.
                    $groupname = fullname($teacher);
                    $group = new projetvet_group(0, (object)[
                        'name' => $groupname,
                        'ownerid' => $teacher->id,
                        'projetvetid' => $this->projetvetid,
                    ]);
                    $group->create();
                }

                // Get secondary teacher if provided.
                $secondaryteacherid = null;
                if ($secondaryteacheridx !== false && !empty($row[$secondaryteacheridx])) {
                    $secondaryusername = trim($row[$secondaryteacheridx]);
                    $secondaryteacher = $DB->get_record('user', ['username' => $secondaryusername], '*', IGNORE_MISSING);
                    if ($secondaryteacher) {
                        $secondaryteacherid = $secondaryteacher->id;
                    }
                }

                // Add secondary teacher as member if provided.
                if ($secondaryteacherid) {
                    $this->add_or_update_member(
                        $group->get('id'),
                        $secondaryteacherid,
                        group_member::TYPE_SECONDARY_TUTOR
                    );
                }

                // Add students.
                foreach ($studentindices as $idx) {
                    if (empty($row[$idx])) {
                        continue;
                    }

                    $studentusername = trim($row[$idx]);
                    $student = $DB->get_record('user', ['username' => $studentusername], '*', IGNORE_MISSING);

                    if (!$student) {
                        debugging("Student not found: $studentusername", DEBUG_DEVELOPER);
                        continue;
                    }

                    // Add student as member.
                    $this->add_or_update_member(
                        $group->get('id'),
                        $student->id,
                        group_member::TYPE_STUDENT
                    );
                }
            }
        } finally {
            $csvreader->cleanup();
            $csvreader->close();
        }
    }

    /**
     * Add or update a group member
     *
     * @param int $groupid
     * @param int $userid
     * @param string $membertype
     * @return void
     */
    protected function add_or_update_member(int $groupid, int $userid, string $membertype): void {
        // Check if member already exists in this group.
        $existing = group_member::get_records([
            'groupid' => $groupid,
            'userid' => $userid,
        ]);

        if (!empty($existing)) {
            // Update existing member if type changed.
            $member = reset($existing);
            if ($member->get('membertype') !== $membertype) {
                $member->set('membertype', $membertype);
                $member->update();
            }
        } else {
            // Create new member.
            $member = new group_member(0, (object)[
                'groupid' => $groupid,
                'userid' => $userid,
                'membertype' => $membertype,
            ]);
            $member->create();
        }
    }
}
