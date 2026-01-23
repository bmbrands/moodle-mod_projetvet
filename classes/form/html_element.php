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

use MoodleQuickForm_static;
use renderer_base;
use mod_projetvet\utils;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/form/static.php');

/**
 * HTML display element - displays dynamic HTML content with filter processing
 *
 * This element displays HTML content that can include filters (e.g., [gettutor])
 * which are processed to insert dynamic data.
 *
 * @package    mod_projetvet
 * @copyright  2025 Bas Brands <bas@sonsbeekmedia.nl>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class html_element extends MoodleQuickForm_static {
    /** @var string The language string key for content */
    protected $stringkey = '';

    /** @var int The student ID */
    protected $studentid = 0;

    /** @var int The course module ID */
    protected $cmid = 0;

    /** @var string The filter name */
    protected $filter = '';

    /** @var array All data-* attributes */
    protected $dataattributes = [];

    /**
     * Constructor
     *
     * @param string $elementname Element name
     * @param mixed $elementlabel Label(s) for an element
     * @param string $text The static text
     * @param array $attributes Element attributes
     */
    public function __construct($elementname = null, $elementlabel = null, $text = '', $attributes = null) {
        parent::__construct($elementname, $elementlabel, $text);

        // Extract custom attributes.
        if (is_array($attributes)) {
            if (isset($attributes['stringkey'])) {
                $this->stringkey = $attributes['stringkey'];
            }
            if (isset($attributes['studentid'])) {
                $this->studentid = $attributes['studentid'];
            }
            if (isset($attributes['cmid'])) {
                $this->cmid = $attributes['cmid'];
            }
            if (isset($attributes['filter'])) {
                $this->filter = $attributes['filter'];
            }

            // Extract all data-* attributes.
            foreach ($attributes as $key => $value) {
                if (strpos($key, 'data-') === 0) {
                    $this->dataattributes[$key] = $value;
                }
            }
        }

        $this->_type = 'html';
    }

    /**
     * Returns the HTML for this form element.
     *
     * @return string
     */
    public function toHtml() { // @codingStandardsIgnoreLine
        global $OUTPUT;

        $context = $this->export_for_template($OUTPUT);
        return $OUTPUT->render_from_template('mod_projetvet/form/element_html', $context);
    }

    /**
     * Export for template
     *
     * @param renderer_base $output
     * @return array
     */
    public function export_for_template(renderer_base $output) {
        $this->_generateId();

        // Get the content from language string.
        $content = '';
        if ($this->stringkey) {
            $content = get_string($this->stringkey, 'mod_projetvet');
        }

        // Process filters in the content.
        $content = $this->process_filters($content);

        // Convert data attributes to array format for Mustache.
        $dataattributeslist = [];
        foreach ($this->dataattributes as $key => $value) {
            $dataattributeslist[] = [
                'key' => $key,
                'value' => $value,
            ];
        }

        $context = [
            'name' => $this->getName(),
            'id' => $this->getAttribute('id'),
            'label' => $this->getLabel(),
            'content' => $content,
            'dataattributes' => $dataattributeslist,
            'hasaction' => isset($this->dataattributes['data-action']),
        ];

        return $context;
    }

    /**
     * Process filters in the content string
     *
     * Replaces filter placeholders like [gettutor] with actual data.
     *
     * @param string $content The content to process
     * @return string The processed content
     */
    protected function process_filters(string $content): string {
        // Find all filter tags in the format [filtername].
        if (preg_match_all('/\[([a-z]+)\]/', $content, $matches)) {
            foreach ($matches[1] as $filter) {
                $filtervalue = utils::get_filter($filter, $this->studentid, $this->cmid);
                $content = str_replace('[' . $filter . ']', $filtervalue, $content);
            }
        }

        return $content;
    }
}
