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
defined('MOODLE_INTERNAL') || die();
/*
 * @package    tool
 * @subpackage mergeusers
 * @author     Jordi Pujol-Ahulló <jordi.pujol@urv.cat>
 * @copyright  2013 Servei de Recursos Educatius (http://www.sre.urv.cat)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
spl_autoload_register(
        function($class) {

            $filename = strtolower($class) . '.php';
            $filedirname = dirname(__FILE__);
            $dirs = [$filedirname, $filedirname . '/table', $filedirname . '/local', $filedirname . '/../classes'];

            foreach ($dirs as $dir) {
                if (is_file($dir . '/' . $filename)) {
                    // 0 /** @noinspection PhpIncludeInspection */.
                    include_once($dir . '/' . $filename);
                    if (class_exists($class)) {
                        return true;
                    }
                }
            }
            return false;
        }
);
