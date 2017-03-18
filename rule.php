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
 * Implementaton of the quizaccess_delay plugin.
 *
 * @package    quizaccess
 * @subpackage delay
 * @copyright  Flatorb <contact@flatorb.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/quiz/accessrule/accessrulebase.php');


/**
 * A rule enforcing open and close dates.
 *
 * @copyright  2009 Tim Hunt
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class quizaccess_delay extends quiz_access_rule_base {

    public static function make(quiz $quizobj, $timenow, $canignoretimelimits) {
        // This rule is always used, even if the quiz has no open or close date.
        return new self($quizobj, $timenow);
    }

    public function description() {
        $result = array();
        if ($this->timenow < $this->quiz->timeopen) {
            $result[] = get_string('quiznotavailable', 'quizaccess_delay',
                    userdate($this->quiz->timeopen));
            if ($this->quiz->timeclose) {
                $result[] = get_string('quizcloseson', 'quiz', userdate($this->quiz->timeclose));
            }

        } else if ($this->quiz->timeclose && $this->timenow > $this->quiz->timeclose) {
            $result[] = get_string('quizclosed', 'quiz', userdate($this->quiz->timeclose));

        } else {
            if ($this->quiz->timeopen) {
                $result[] = get_string('quizopenedon', 'quiz', userdate($this->quiz->timeopen));
            }
            if ($this->quiz->timeclose) {
                $result[] = get_string('quizcloseson', 'quiz', userdate($this->quiz->timeclose));
            }
        }

        return $result;
    }

    public function prevent_access() {
	    GLOBAL $DB;
	    GLOBAL $USER;
	    
	    $lastCompTimestamp = 0;
	
	    $message = get_string('notavailable', 'quizaccess_delay');
        
        $courseId = $this->quiz->course;
	    $context = context_course::instance($courseId, MUST_EXIST);
	
	    $sections = $DB->get_records('course_sections', array('course' => $courseId), 'section');
	    
	    foreach ($sections as $section) {
	    	$modules = $DB->get_records('course_modules', array('course' => $courseId, 'section' => $section->id, 'module' => 16));
	    	
	    	foreach ($modules as $module) {
			    $lastComp = $DB->get_record('course_modules_completion', array('coursemoduleid' => $module->id));
			    if(count($lastComp) > 0) {
			    	if($lastCompTimestamp < $lastComp->timemodified) {
			    		$lastCompTimestamp = (int)$lastComp->timemodified;
				    }
			    }
		    }
	    }
	
	    $delay = $DB->get_record('quizaccess_delay', array('course' => $courseId));
	
	    if ($lastCompTimestamp > 0) {
		    $delayReachAt = $lastCompTimestamp + (int)$delay->delay;
	    } else {
		    $enrolledDate = 0;
	    	$myCourses = get_user_roles($context);
	    
            foreach ($myCourses as $myCourse) {
	    		if((int)$USER->id == (int)$myCourse->userid) {
				    $enrolledDate = (int)$myCourse->timemodified;
			    }
		    }
		    
		    $delayReachAt = $enrolledDate + (int)$delay->delay;
	    }
        
        if ($this->timenow < $delayReachAt) {
        	return $message;
        }
	    
        return false;
    }

    
	
}
