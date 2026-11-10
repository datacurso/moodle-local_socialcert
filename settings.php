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
 * Plugin version and other meta-data are defined here.
 *
 * @package     local_socialcert
 * @copyright   2025 Manuel Bojaca <manuel@buendata.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $settings = new admin_settingpage(
        'local_socialcert',
        get_string('pluginname', 'local_socialcert')
    );
    $ADMIN->add('localplugins', $settings);

    $settings->add(new admin_setting_configtext(
        'local_socialcert/organizationid',
        get_string('organizationid', 'local_socialcert'),
        get_string('organizationid_desc', 'local_socialcert'),
        '',
        PARAM_RAW_TRIMMED
    ));

    $settings->add(new admin_setting_configtext(
        'local_socialcert/organizationname',
        get_string('organizationname', 'local_socialcert'),
        get_string('organizationname_desc', 'local_socialcert'),
        '',
        PARAM_RAW_TRIMMED
    ));

    // Only hides the AI assistant card; the share option stays. Not named 'enableai' on purpose: the
    // Datacurso AI provider reads that former setting as the legacy state of the whole plugin.
    $settings->add(new admin_setting_configcheckbox(
        'local_socialcert/aiassistant_enabled',
        get_string('aiassistant_enabled', 'local_socialcert'),
        get_string('aiassistant_enabled_desc', 'local_socialcert'),
        1
    ));
}
