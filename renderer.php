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
 * Defines renderer for course format flexsections
 *
 * @package    format_flexsections
 * @copyright  2012 Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

require_once($CFG->dirroot.'/course/format/renderer.php');

/**
 * Renderer for flexsections format.
 *
 * @copyright 2012 Marina Glancy
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class format_flexsections_renderer extends format_section_renderer_base {
    /** @var core_course_renderer Stores instances of core_course_renderer */
    protected $courserenderer = null;

    /**
     * Constructor
     *
     * @param moodle_page $page
     * @param type $target
     */
    public function __construct(moodle_page $page, $target) {
        parent::__construct($page, $target);
        $this->courserenderer = $page->get_renderer('core', 'course');
    }

    /**
     * Generate the starting container html for a list of sections
     * @return string HTML to output.
     */
    protected function start_section_list() {
        return html_writer::start_tag('ul', array('class' => 'flexsections'));
    }

    /**
     * Generate the closing container html for a list of sections
     * @return string HTML to output.
     */
    protected function end_section_list() {
        return html_writer::end_tag('ul');
    }

    /**
     * Generate the title for this section page
     * @return string the page title
     */
    protected function page_title() {
        return get_string('topicoutline');
    }


    /**
     * Generate the section title (with link if section is collapsed)
     *
     * @param int|section_info $section
     * @param stdClass $course The course entry from DB
     * @return string HTML to output.
     */
    public function section_title($section, $course, $supresslink = false) {
        global $CFG;
        if ((float)$CFG->version >= 2016052300) {
            // For Moodle 3.1 or later use inplace editable for displaying section name.
            $section = course_get_format($course)->get_section($section);
            return $this->render(course_get_format($course)->inplace_editable_render_section_name($section, !$supresslink));
        }
        $title = get_section_name($course, $section);
        if (!$supresslink) {
            $url = course_get_url($course, $section, array('navigation' => true));
            if ($url) {
                $title = html_writer::link($url, $title);
            }
        }
        return $title;
    }

    /**
     * Generate html for a section summary text
     *
     * @param stdClass $section The course_section entry from DB
     * @return string HTML to output.
     */
    protected function format_summary_text($section) {
        $context = context_course::instance($section->course);
        $summarytext = file_rewrite_pluginfile_urls($section->summary, 'pluginfile.php',
            $context->id, 'course', 'section', $section->id);

        $options = new stdClass();
        $options->noclean = true;
        $options->overflowdiv = true;
        return format_text($summarytext, $section->summaryformat, $options);
    }


    public function print_section0($course, $contentvisible = True) {
      global $PAGE;

      $modinfo = get_fast_modinfo($course);
      $course = course_get_format($course)->get_course();
      $section = course_get_format($course)->get_section(0);
      $context = context_course::instance($course->id);

      // 0-section is displayed a little different then the others
      if ($section->summary or !empty($modinfo->sections[0]) or $PAGE->user_is_editing()) {
	echo $this->section_header($section, $course, false, 0);
	echo $this->courserenderer->course_section_cm_list($course, $section, 0);
	echo $this->courserenderer->course_section_add_cm_control($course, 0, 0);
	echo $this->section_footer();
      }
    }

    /**
     * Output the html for a multiple section page
     *
     * @param stdClass $course The course entry from DB
     * @param array $sections (argument not used)
     * @param array $mods (argument not used)
     * @param array $modnames (argument not used)
     * @param array $modnamesused (argument not used)
     */
    public function print_sections_recursive($course, $sections) {
        global $PAGE;

	echo "<pre>" ; echo "Print sections: "; print_r($sections); echo "</pre>";

        $modinfo = get_fast_modinfo($course);
        $course = course_get_format($course)->get_course();

	$movingsection = course_get_format($course)->is_moving_section();
        $numsections = course_get_format($course)->get_last_section_number();

        foreach ($sections as $thissection) {
	  $sectionnum = $thissection->section;
	  if ($sectionnum == 0) {
	    // Should not be displayed here, only at start
	    continue;
	  }
	  if ($sectionnum > $numsections) {
	    // activities inside this section are 'orphaned', this section will be printed as 'stealth' below
	    continue;
	  }
	  // Show the section if the user is permitted to access it, OR if it's not available
	  // but there is some available info text which explains the reason & should display.
	  $showsection = $thissection->uservisible ||
	    ($thissection->visible && !$thissection->available &&
	     !empty($thissection->availableinfo));
	  if (!$showsection) {
	    // If the hiddensections option is set to 'show hidden sections in collapsed
	    // form', then display the hidden section message - UNLESS the section is
	    // hidden by the availability system, which is set to hide the reason.
	    if (!$course->hiddensections && $thissection->available) {
	      echo $this->section_hidden($thissection, $course->id);
	    }
	    continue;
	  }

	  if (!$PAGE->user_is_editing()) {
	    // Display section summary only.
	    echo $this->section_summary($thissection, $course, null);
	  } else {
	    echo $this->section_header($thissection, $course, false, 0);
	    if ($thissection->uservisible) {
	      echo $this->courserenderer->course_section_cm_list($course, $thissection, 0);
	      echo $this->courserenderer->course_section_add_cm_control($course, $thissection, 0);
	    }
	    echo $this->section_footer();
	  }
	  
	  $children = course_get_format($course)->get_subsections($sectionnum);
	  if (!empty($children) || $movingsection) {
	    echo $this -> start_section_list();
	    $this-> print_sections_recursive($course, $children);
	    echo $this -> end_section_list();
	  }

	}
	    
	if ($PAGE->user_is_editing() and has_capability('moodle/course:update', $context)) {
	  // Print stealth sections if present.
	  foreach ($modinfo->get_section_info_all() as $section => $thissection) {
	    if ($section <= $numsections or empty($modinfo->sections[$section])) {
	      // this is not stealth section or it is empty
	      continue;
	    }
	    echo $this->stealth_section_header($section);
	    echo $this->courserenderer->course_section_cm_list($course, $thissection, 0);
	    echo $this->stealth_section_footer();
	  }

	  echo $this->end_section_list();

	  echo $this->change_number_sections($course, 0);

	} else {
	  echo $this->end_section_list();
	}
    }


    /**
     * Display section and all its activities and subsections (called recursively)
     *
     * @param int|stdClass $course
     * @param int|section_info $section
     * @param int $sr section to return to (for building links)
     * @param int $level nested level on the page (in case of 0 also displays additional start/end html code)
     */

    public function display_section($course, $section, $sr, $level = 0) {
        global $PAGE;
        $course = course_get_format($course)->get_course();
        $section = course_get_format($course)->get_section($section);
        $context = context_course::instance($course->id);


	// Title with completion help icon.
	$completioninfo = new completion_info($course);
	echo $completioninfo->display_help_icon();
	echo $this->output->heading($this->page_title(), 2, 'accesshide');

	// Copy activity clipboard..
	echo $this->course_activity_clipboard($course, 0);

	// Now the list of sections..
	echo $this->start_section_list();

	$this->print_section0($course);

	$numsections = course_get_format($course)->get_last_section_number();
        $sectionnum = $section->section;
        $movingsection = course_get_format($course)->is_moving_section();

	if ($sectionnum == 0) {
	  $sections = course_get_format($course)->get_subsections($sectionnum);
	} else {
	  $sections = [ $section ];
	}

	$this -> print_sections_recursive($course, $sections, 0);

	echo $this -> end_section_list();

	return '';


        if ($contentvisible && ($section->collapsed == FORMAT_FLEXSECTIONS_EXPANDED || !$level )) {

            // display subsections
            $children = course_get_format($course)->get_subsections($sectionnum);
            if (!empty($children) || $movingsection) {
                echo html_writer::start_tag('ul', array('class' =>
							'flexsections flexsections-level-'.($level+1)));

                foreach ($children as $num) {
                    $this->display_insert_section_here($course, $section, $num, $sr);
		    if ($num -> section > 0) {
		      echo $this -> print_single_section_page_no_block0($course, NULL, NULL, NULL, NULL,
									 $num -> section);
		    }
                }
                $this->display_insert_section_here($course, $section, null, $sr);

                echo html_writer::end_tag('ul'); // .flexsections

            }
            if ($addsectioncontrol = course_get_format($course)->get_add_section_control($sectionnum)) {
                echo $this->render($addsectioncontrol);
            }
        }

	return '';
    }


    public function foodisplay_section($course, $section, $sr, $level = 0) {

        global $PAGE;
        $course = course_get_format($course)->get_course();
        $section = course_get_format($course)->get_section($section);
        $context = context_course::instance($course->id);

        $contentvisible = true;
        if (!$section->uservisible || !course_get_format($course)->is_section_real_available($section)) {
            if ($section->visible && !$section->available && $section->availableinfo) {
                // Still display section but without content.
                $contentvisible = false;
            } else {
                return '';
            }
        }
        $sectionnum = $section->section;
        $movingsection = course_get_format($course)->is_moving_section();


	if ($sectionnum == 0 ) {
	  if ( $level == 0) {
	    echo $this -> print_single_section_page($course, NULL, NULL, NULL, NULL, $sectionnum);
	  }
	}

        $modinfo = get_fast_modinfo($course);
	echo "<pre>";  echo print_r($modinfo->sections); echo "</pre>";

        if ($contentvisible && ($section->collapsed == FORMAT_FLEXSECTIONS_EXPANDED || !$level )) {

            // display subsections
            $children = course_get_format($course)->get_subsections($sectionnum);
            if (!empty($children) || $movingsection) {
                echo html_writer::start_tag('ul', array('class' =>
							'flexsections flexsections-level-'.($level+1)));

                foreach ($children as $num) {
                    $this->display_insert_section_here($course, $section, $num, $sr);
		    if ($num -> section > 0) {
		      echo $this -> print_single_section_page_no_block0($course, NULL, NULL, NULL, NULL,
									 $num -> section);
		    }
                }
                $this->display_insert_section_here($course, $section, null, $sr);

                echo html_writer::end_tag('ul'); // .flexsections

            }
            if ($addsectioncontrol = course_get_format($course)->get_add_section_control($sectionnum)) {
                echo $this->render($addsectioncontrol);
            }
        }

	return '';


        if ($level === 0) {
            $cancelmovingcontrols = course_get_format($course)->get_edit_controls_cancelmoving();
            foreach ($cancelmovingcontrols as $control) {
                echo $this->render($control);
            }
            echo html_writer::start_tag('ul', array('class' => 'flexsections flexsections-level-0'));
            if ($section->section) {
                $this->display_insert_section_here($course, $section->parent, $section->section, $sr);
            }
        }
        echo html_writer::start_tag('li',
                array('class' => "section main".
                    ($movingsection === $sectionnum ? ' ismoving' : '').
                    (course_get_format($course)->is_section_current($section) ? ' current' : '').
                    (($section->visible && $contentvisible) ? '' : ' hidden'),
                    'id' => 'section-'.$sectionnum));

        // display controls except for expanded/collapsed
        $controls = course_get_format($course)->get_section_edit_controls($section, $sr);
        $collapsedcontrol = null;
        $controlsstr = '';
        foreach ($controls as $idxcontrol => $control) {
            if ($control->class === 'expanded' || $control->class === 'collapsed') {
                $collapsedcontrol = $control;
            } else {
                $controlsstr .= $this->render($control);
            }
        }
        if (!empty($controlsstr)) {
            echo html_writer::tag('div', $controlsstr, array('class' => 'controls'));
        }

        // display section content
        echo html_writer::start_tag('div', array('class' => 'content'));
        // display section name and expanded/collapsed control
        if ($sectionnum && ($title = $this->section_title($sectionnum, $course, ($level == 0) || !$contentvisible))) {
            if ($collapsedcontrol) {
                $title = $this->render($collapsedcontrol). $title;
            }
            echo html_writer::tag('h3', $title, array('class' => 'sectionname'));
        }

        echo $this->section_availability_message($section,
            has_capability('moodle/course:viewhiddensections', $context));

        // display section description (if needed)
        if ($contentvisible && ($summary = $this->format_summary_text($section))) {
            echo html_writer::tag('div', $summary, array('class' => 'summary'));
        } else {
            echo html_writer::tag('div', '', array('class' => 'summary nosummary'));
        }

        // display section contents (activities and subsections)
        if ($contentvisible && ($section->collapsed == FORMAT_FLEXSECTIONS_EXPANDED || !$level)) {
            // display resources and activities
            echo $this->courserenderer->course_section_cm_list($course, $section, $sr);
            if ($PAGE->user_is_editing()) {
                // a little hack to allow use drag&drop for moving activities if the section is empty
                if (empty(get_fast_modinfo($course)->sections[$sectionnum])) {
                    echo "<ul class=\"section img-text\">\n</ul>\n";
                }
                echo $this->courserenderer->course_section_add_cm_control($course, $sectionnum, $sr);
            }


            // display subsections
            $children = course_get_format($course)->get_subsections($sectionnum);
            if (!empty($children) || $movingsection) {
                echo html_writer::start_tag('ul', array('class' => 'flexsections flexsections-level-'.($level+1)));
                foreach ($children as $num) {
                    $this->display_insert_section_here($course, $section, $num, $sr);
                    $this->display_section($course, $num, $sr, $level+1);
                }
                $this->display_insert_section_here($course, $section, null, $sr);
                echo html_writer::end_tag('ul'); // .flexsections
            }
            if ($addsectioncontrol = course_get_format($course)->get_add_section_control($sectionnum)) {
                echo $this->render($addsectioncontrol);
            }
        }
        echo html_writer::end_tag('div'); // .content
        echo html_writer::end_tag('li'); // .section
        if ($level === 0) {
            if ($section->section) {
                $this->display_insert_section_here($course, $section->parent, null, $sr);
            }
            echo html_writer::end_tag('ul'); // .flexsections
        }
    }

    /**
     * Displays the target div for moving section (in 'moving' mode only)
     *
     * @param int|stdClass $courseorid current course
     * @param int|section_info $parent new parent section
     * @param null|int|section_info $before number of section before which we want to insert (or null if in the end)
     */
    protected function display_insert_section_here($courseorid, $parent, $before = null, $sr = null) {
      if ($control = course_get_format($courseorid)->get_edit_control_movehere($parent, $before, $sr)) {
            echo $this->render($control);
      }
    }

    /**
     * If section is not visible, display the message about that ('Not available
     * until...', that sort of thing). Otherwise, returns blank.
     *
     * For users with the ability to view hidden sections, it shows the
     * information even though you can view the section and also may include
     * slightly fuller information (so that teachers can tell when sections
     * are going to be unavailable etc). This logic is the same as for
     * activities.
     *
     * @param stdClass $section The course_section entry from DB
     * @param bool $canviewhidden True if user can view hidden sections
     * @return string HTML to output
     */
    protected function section_availability_message($section, $canviewhidden) {
        global $CFG;
        $o = '';
        if (!$section->uservisible) {
            // Note: We only get to this function if availableinfo is non-empty,
            // so there is definitely something to print.
            $formattedinfo = \core_availability\info::format_info(
                $section->availableinfo, $section->course);
            $o .= html_writer::div($formattedinfo, 'availabilityinfo');
        } else if ($canviewhidden && !empty($CFG->enableavailability) && $section->visible) {
            $ci = new \core_availability\info_section($section);
            $fullinfo = $ci->get_full_information();
            if ($fullinfo) {
                $formattedinfo = \core_availability\info::format_info(
                    $fullinfo, $section->course);
                $o .= html_writer::div($formattedinfo, 'availabilityinfo');
            }
        }
        return $o;
    }

    /**
     * Displays a confirmation dialogue when deleting the section (for non-JS mode)
     *
     * @param stdClass $course
     * @param int $sectionreturn
     * @param int $deletesection
     */
    public function confirm_delete_section($course, $sectionreturn, $deletesection) {
        echo $this->box_start('noticebox');
        $courseurl = course_get_url($course, $sectionreturn);
        $optionsyes = array('confirm' => 1, 'deletesection' => $deletesection, 'sesskey' => sesskey());
        $formcontinue = new single_button(new moodle_url($courseurl, $optionsyes), get_string('yes'));
        $formcancel = new single_button($courseurl, get_string('no'), 'get');
        echo $this->confirm(get_string('confirmdelete', 'format_flexsections'), $formcontinue, $formcancel);
        echo $this->box_end();
    }
}
