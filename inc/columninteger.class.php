<?php
/*
  ----------------------------------------------------------------------
  GLPI - Gestionnaire Libre de Parc Informatique
  Copyright (C) 2003-2008 by the INDEPNET Development Team.

  http://indepnet.net/   http://glpi-project.org/
  ----------------------------------------------------------------------

  LICENSE

  This file is part of GLPI.

  GLPI is free software; you can redistribute it and/or modify
  it under the terms of the GNU General Public License as published by
  the Free Software Foundation; either version 2 of the License, or
  (at your option) any later version.

  GLPI is distributed in the hope that it will be useful,
  but WITHOUT ANY WARRANTY; without even the implied warranty of
  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
  GNU General Public License for more details.

  You should have received a copy of the GNU General Public License
  along with GLPI; if not, write to the Free Software
  Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
  ------------------------------------------------------------------------
 */

/**
 * class PluginReportsColumn to manage output
 */
class PluginReportsColumnInteger extends PluginReportsColumn {

   private $total;

   function __construct($name, $title, $options=array()) {

      $options['extras'] = "class='right'";
      $options['totextras'] = "class='b right'";

      parent::__construct($name, $title, $options);

      $this->total = 0;
   }

   function displayValue($output_type, $row) {
      if (isset($row[$this->name])) {
         $this->total += intval($row[$this->name]);
         return $row[$this->name];
      }
      return '';
   }

   function displayTotal($output_type) {
      return $this->total;
   }
}
?>
