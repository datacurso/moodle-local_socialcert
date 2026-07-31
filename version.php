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

$plugin->component = 'local_socialcert';
$plugin->release = '1.1.3';
// The release stays at 1.1.3 on purpose: this is the same functional release. The version number
// still has to grow because Moodle only installs the capabilities of db/access.php and the new event
// classes while upgrading the plugin, which it decides by comparing this number with the installed
// one.
$plugin->version = 2026073002;
$plugin->requires = 2024100700;
$plugin->maturity = MATURITY_STABLE;
$plugin->supported = [405, 501];
$plugin->dependencies = [
    'mod_customcert' => 2024042212,
    'aiprovider_datacurso' => 2025100201,
];
