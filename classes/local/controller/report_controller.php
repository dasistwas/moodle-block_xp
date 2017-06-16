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
 * Report controller.
 *
 * @package    block_xp
 * @copyright  2017 Branch Up Pty Ltd
 * @author     Frédéric Massart <fred@branchup.tech>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_xp\local\controller;
defined('MOODLE_INTERNAL') || die();

use core_user;
use html_writer;
use block_xp\local\routing\url;

/**
 * Report controller class.
 *
 * @package    block_xp
 * @copyright  2017 Branch Up Pty Ltd
 * @author     Frédéric Massart <fred@branchup.tech>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class report_controller extends page_controller {

    /** @var bool The page supports groups. */
    protected $supportsgroups = true;
    /** @var string The route name. */
    protected $routename = 'report';

    /** @var moodleform The form. */
    protected $form;
    /** @var flexible_table The table. */
    protected $table;

    protected function define_optional_params() {
        return [
            ['userid', null, PARAM_INT],
            ['resetdata', 0, PARAM_INT, false],
            ['action', null, PARAM_ALPHA],
            ['confirm', 0, PARAM_INT, false],
            ['page', 0, PARAM_INT],     // To keep the table page in URL.
        ];
    }

    protected function pre_content() {
        // Reset data.
        if ($this->get_param('resetdata') && confirm_sesskey()) {
            if ($this->get_param('confirm')) {
                if ($this->get_groupid()) {
                    $this->world->get_store()->reset_by_group($this->get_groupid());
                    $this->world->get_user_event_collection_logger()->reset_by_group($this->get_groupid());
                } else {
                    $this->world->get_store()->reset();
                    $this->world->get_user_event_collection_logger()->reset();
                }
                $this->redirect(new url($this->pageurl));
            }
        }

        // Use edit form.
        $userid = $this->get_param('userid');
        $action = $this->get_param('action');
        if ($action === 'edit' && !empty($userid)) {
            $form = $this->get_form($userid);
            $nexturl = new url($this->pageurl, ['userid' => null]);
            if ($data = $form->get_data()) {
                $this->world->get_store()->set($userid, $data->xp);
                $this->redirect($nexturl);
            } else if ($form->is_cancelled()) {
                $this->redirect($nexturl);
            }
        }
    }

    protected function get_page_html_head_title() {
        return get_string('coursereport', 'block_xp');
    }

    protected function get_page_heading() {
        return get_string('coursereport', 'block_xp');
    }

    protected function get_form($userid) {
        if (!$this->form) {
            $state = $this->world->get_store()->get_state($userid);
            $form = new \block_xp\form\user_xp($this->pageurl->out(false));
            $form->set_data(['userid' => $userid, 'level' => $state->get_level()->get_level(), 'xp' => $state->get_xp()]);
            $this->form = $form;
        }
        return $form;
    }

    protected function get_table() {
        if (!$this->table) {
            $this->table = new \block_xp\output\report_table(
                $this->world,
                $this->get_renderer(),
                $this->world->get_store(),
                $this->get_groupid()
            );
            $this->table->define_baseurl($this->pageurl);
        }
        return $this->table;
    }

    protected function page_content() {
        $output = $this->get_renderer();
        $groupid = $this->get_groupid();

        // Confirming reset data.
        if ($this->get_param('resetdata')) {
            echo $this->get_renderer()->confirm(
                empty($groupid) ? get_string('reallyresetdata', 'block_xp') : get_string('reallyresetgroupdata', 'block_xp'),
                new url($this->pageurl->get_compatible_url(), ['resetdata' => 1, 'confirm' => 1,
                    'sesskey' => sesskey(), 'group' => $groupid]),
                new url($this->pageurl->get_compatible_url())
            );
            return;
        }

        // Use edit form.
        if (!empty($this->form)) {
            $user = core_user::get_user($this->get_param('userid'));
            echo $output->heading(fullname($user), 3);
            $this->form->display();
        }

        // Displaying the report.
        $this->print_group_menu();
        echo $this->get_table()->out(20, true);

        if (empty($groupid)) {
            $strreset = get_string('resetcoursedata', 'block_xp');
        } else {
            $strreset = get_string('resetgroupdata', 'block_xp');
        }

        echo html_writer::tag('p',
            $output->single_button(
                new url($this->pageurl->get_compatible_url(), [
                    'resetdata' => 1,
                    'sesskey' => sesskey(),
                    'group' => $groupid
                ]),
                $strreset,
                'get'
            )
        );
    }

}
