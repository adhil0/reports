<?php
/**
 -------------------------------------------------------------------------
  LICENSE

 This file is part of Reports plugin for GLPI.

 Reports is free software: you can redistribute it and/or modify
 it under the terms of the GNU Affero General Public License as published by
 the Free Software Foundation, either version 3 of the License, or
 (at your option) any later version.

 Reports is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 GNU Affero General Public License for more details.

 You should have received a copy of the GNU Affero General Public License
 along with Reports. If not, see <http://www.gnu.org/licenses/>.

 @package   reports
 @authors    Nelly Mahu-Lasson, Remi Collet
 @copyright Copyright (c) 2009-2022 Reports plugin team
 @license   AGPL License 3.0 or (at your option) any later version
            http://www.gnu.org/licenses/agpl-3.0-standalone.html
 @link      https://forge.glpi-project.org/projects/reports
 @link      http://www.glpi-project.org/
 @since     2009
 --------------------------------------------------------------------------
 */

$USEDBREPLICATE         = 1;
$DBCONNECTION_REQUIRED  = 0; // Not really a big SQL request

include ("../../../../inc/includes.php");

includeLocales("nonreservablebygroups");
//TRANS: The name of the report = Unavailable Computers
Html::header(__('nonreservablebygroups_report_title', 'reports'), $_SERVER['PHP_SELF'], "utils", "report");

Report::title();

getObjectsByGroupAndEntity();

Html::footer();

/**
 * Display all devices by group
**/
function getObjectsByGroupAndEntity() {
   global $DB, $CFG_GLPI;
   $display_header = false;
   foreach ($CFG_GLPI["asset_types"] as $key => $itemtype) {
      if (($itemtype == 'Certificate') || ($itemtype == 'SoftwareLicense')) {
         unset($CFG_GLPI["asset_types"][$key]);
      }
      if ($itemtype == 'Computer'){
      $item = new $itemtype();
      if ($item->isField('groups_id')) {

       $query = $DB->request("SELECT 
                                 glpi_computers.id,
                                 glpi_computers.name,
                                 `glpi_groups`.`id` AS `groups_id`,                 
                                 `glpi_groups`.`completename` AS `group_name`,
                                 glpi_computers.comment,
                                 glpi_computers.states_id,
                                 glpi_computers.serial,
                                 glpi_states.completename AS `status`,
                                 glpi_reservationitems.items_id,
                                 glpi_reservationitems.is_active
                              FROM 
                                 glpi_computers
                              LEFT JOIN 
                                 glpi_states ON glpi_computers.states_id = glpi_states.id
                              LEFT JOIN 
                                 glpi_reservationitems ON glpi_computers.id = glpi_reservationitems.items_id
                              LEFT JOIN glpi_groups ON glpi_computers.groups_id = glpi_groups.id
                              WHERE 
                              (glpi_reservationitems.is_active IS NULL OR glpi_reservationitems.is_active = 0)");
        if (count($query) > 0) {
            if (!$display_header) {
                echo "<br><table class='tab_cadre_fixehov' id='nonreservablebygroups'>";
                echo "<thead><tr>";
                echo "<th class='center'>" .__('Type'). "</th>";
                echo "<th class='center'>" .__('Name'). "</th>";
                echo "<th class='center'>" .__('Group'). "</th>";
                echo "<th class='center'>" .__('Serial number'). "</th>";
                echo "<th class='center'>" .__('Comment')."</th>";
                echo "<th class='center'>" .__('Status')."</th>";
                echo "</tr></thead>";
                echo "</tbody>";
                $display_header = true;
            }
            displayUserDevices($itemtype, $query);
        }
     }
    }
   }
   echo "</tbody>";
   echo "</table>";
}


/**
 * Display all device for a group 
 *
 * @param $type      the objet type
 * @param $result    the resultset of all the devices found
**/
function displayUserDevices($type, $result) {
   global $DB, $CFG_GLPI;
   $item = new $type();
   foreach ($result as $data) {
      $link = $data["name"];
      $url  = Toolbox::getItemTypeFormURL("$type");
      $link = "<a href='" . $url . "?id=" . $data["id"] . "'>" . $link .
               (($CFG_GLPI["is_ids_visible"] || empty ($link)) ? " (" . $data["groups_id"] . ")" : "") .
               "</a>";

      echo "<tr class='tab_bg_1'>";
      echo "<td class='center'>".$item->getTypeName()."</td>";
      echo "<td class='center'>$link</td>";
      echo "<td class='center'>";
      if (isset ($data["group_name"]) && !empty ($data["group_name"])) {
         echo $data["group_name"];
      } else {
         echo 'NA';
      }

      echo "<td class='center'>";
      if (isset ($data["serial"]) && !empty ($data["serial"])) {
         echo $data["serial"];
      } else {
         echo 'NA';
      }

      echo "</td><td class='center'>";
      if (isset ($data["comment"]) && !empty ($data["comment"])) {
         echo $data["comment"];
      } else {
         echo 'NA';
      }
      
      echo "</td><td class='center'>";
      if (isset ($data["status"]) && !empty ($data["status"])) {
         echo $data["status"];
      } else {
         echo 'NA';
      }
      echo "</td></tr>";
   }
}