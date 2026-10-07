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
 * Plugin administration pages are defined here.
 *
 * @package     local_socialcert
 * @category    admin
 * @copyright   2025 Manuel Bojaca <manuel@buendata.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

// The settings apply to the tenant the user is currently in (tenant administrators: their own
// tenant; site administrators: the tenant they switched to). The page is registered outside the
// $hassiteconfig check so tenant administrators, who have no site configuration rights, reach it,
// gated by its own plugin capability. The "Local plugins" category only exists for users holding
// moodle/site:config; for anyone else the page hangs from "Courses".
$settings = new admin_settingpage(
    'local_socialcert',
    get_string('pluginname', 'local_socialcert'),
    'local/socialcert:managetenantsettings'
);
$ADMIN->add($ADMIN->locate('localplugins') ? 'localplugins' : 'courses', $settings);

$settings->add(new \local_socialcert\admin\setting_tenant_scope_notice('local_socialcert/tenantscopenotice'));

$settings->add(new \local_socialcert\admin\setting_configtext(
    'local_socialcert/organizationid',
    get_string('organizationid', 'local_socialcert'),
    get_string('organizationid_desc', 'local_socialcert'),
    '',
    PARAM_RAW_TRIMMED
));

$settings->add(new \local_socialcert\admin\setting_configtext(
    'local_socialcert/organizationname',
    get_string('organizationname', 'local_socialcert'),
    get_string('organizationname_desc', 'local_socialcert'),
    '',
    PARAM_RAW_TRIMMED
));

$settings->add(new \local_socialcert\admin\setting_configcheckbox(
    'local_socialcert/enableai',
    get_string('enableai', 'local_socialcert'),
    get_string('enableai_desc', 'local_socialcert'),
    1
));
