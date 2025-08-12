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

includeLocales("allmachinessbygroups");
//TRANS: The name of the report = All Computers
Html::header(__('allmachinesbygroups_report_title', 'reports'), $_SERVER['PHP_SELF'], "utils", "report");

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
      if ($itemtype == 'Computer') {
         $item = new $itemtype();
         if ($item->isField('groups_id')) {
            $query = $DB->request("SELECT 
               MAX(`glpi_reservations`.`end`) as `latest_reservation`,
               `glpi_computers`.`id`,
               `glpi_computers`.`name`,                                      
               `glpi_computers`.`groups_id` AS `group_id`,
               `glpi_groups`.`name` AS `group_name`,
               `glpi_computers`.`serial`,
               `glpi_reservations`.`begin`,                                     
               `glpi_reservations`.`end`,
               `glpi_reservations`.`comment` AS `reservation_comment`, 
               `glpi_computers`.`comment` AS `computer_comment`,
               `glpi_states`.`completename`,
               `glpi_users`.`realname`,
               `glpi_users`.`firstname`                
            FROM `glpi_computers`
               LEFT JOIN `glpi_groups` ON `glpi_computers`.`groups_id` = `glpi_groups`.`id`
               LEFT JOIN `glpi_states` ON `glpi_computers`.`states_id` = `glpi_states`.`id`
               LEFT JOIN `glpi_reservationitems` ON (
                  `glpi_reservationitems`.`items_id` = `glpi_computers`.`id` 
                  AND `glpi_reservationitems`.`itemtype` = 'Computer'
               )
               LEFT JOIN `glpi_reservations` ON (
                  `glpi_reservations`.`reservationitems_id` = `glpi_reservationitems`.`id`
               )
               LEFT JOIN `glpi_users` ON (
                  `glpi_reservations`.`users_id` = `glpi_users`.`id`
               )
            WHERE   
               `glpi_computers`.`entities_id` = '0'
               AND `glpi_computers`.`is_template` = '0'
               AND `glpi_computers`.`is_deleted` = '0' 
            GROUP BY `glpi_computers`.`id`
            ORDER BY `glpi_computers`.`name` ASC");

            if (count($query) > 0) {
               if (!$display_header) {
                  echo "<br><table class='tab_cadre_fixehov' id='allmachinesbygroups'>";
                  echo "<thead><tr>";
                  echo "<th class='center'>" .__('Group'). "</th>";
                  echo "<th class='center'>" .__('Type'). "</th>";
                  echo "<th class='center'>" .__('Name'). "</th>";
                  echo "<th class='center'>" .__("Status"). "</th>";
                  echo "<th class='center'>" .__('Serial number'). "</th>";
                  echo "<th class='center'>" .__('Computer Comment'). "</th>";
                  echo "<th class='center'>" .__('Reserved?')."</th>";
                  echo "<th class='center'>" .__('Reservation Made By'). "</th>";
                  echo "<th class='center'>" .__('Reservation Comment'). "</th>";
                  echo "<th class='center'>" .__('Reservation Start Date'). "</th>";
                  echo "<th class='center'>" .__('Reservation End Date'). "</th>";
                  echo "</tr></thead>";
                  echo "<tbody>";
                  $display_header = true;
               }
               displayUserDevices($itemtype, $query);
            } else {
               echo __('No computers found');
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
   global $CFG_GLPI;
   $time = time();
   $now = date("Y-m-d H:i:s", $time);
   $item = new $type();
   foreach ($result as $data) {
      echo "<tr class='tab_bg_1'>";
      
      // Group column
      echo "<td class='center'>";
      if (isset ($data["group_name"]) && !empty ($data["group_name"])) {
         echo $data["group_name"];
      } else {
         echo '';
      }
      echo "</td>";

      $link = $data["name"];
      $url  = Toolbox::getItemTypeFormURL("$type");
      $link = "<a href='" . $url . "?id=" . $data["id"] . "'>" . $link .
               (($CFG_GLPI["is_ids_visible"] || empty ($link)) ? " (" . $data["group_id"] . ")" : "") .
               "</a>";
      
      echo "<td class='center'>".$item->getTypeName()."</td>";

      echo "<td class='center'>$link</td>";

      echo "<td class='center'>";
      if (isset ($data["completename"]) && !empty ($data["completename"])) {
         echo $data["completename"];
      } else {
         echo '';
      }
      echo "</td>";

      echo "<td class='center'>";
      if (isset ($data["serial"]) && !empty ($data["serial"])) {
         echo $data["serial"];
      } else {
         echo '';
      }
      echo "</td>";

      echo "<td class='center'>";
      if (isset ($data["computer_comment"]) && !empty ($data["computer_comment"])) {
         echo $data["computer_comment"];
      } else {
         echo '';
      }
      echo "</td>";

      echo "<td class='center'>";
      if (isset ($data["latest_reservation"]) && !empty ($data["latest_reservation"]) ) {
         if ($data["latest_reservation"] >= $now) {
            echo "Yes";
         }
         else {
            echo "No";
         }
      } else {
         echo 'No';
      }
      echo "</td>";

      echo "<td class='center'>";
      if (isset ($data["realname"]) && !empty ($data["realname"]) && isset ($data["firstname"]) && !empty ($data["firstname"])) {
         if ($data["latest_reservation"] >= $now) {
            echo $data["firstname"]." ".$data["realname"];
         } else {
            echo '';
         }
      } else {
         echo '';
      }
      echo "</td>";

      echo "<td class='center'>";
      if (isset ($data["reservation_comment"]) && !empty ($data["reservation_comment"])) {
        if ($data["latest_reservation"] >= $now) {
         echo $data["reservation_comment"];
        } else {
            echo '';
        }
      } else {
         echo '';
      }
      echo "</td>";

      echo "<td class='center'>";
      if (isset ($data["begin"]) && !empty ($data["begin"])) {
        if ($data["latest_reservation"] >= $now) {
            echo $data["begin"];
        } else {
            echo '';
        }
      } else {
         echo '';
      }
      echo "</td>";
      
      echo "<td class='center'>";
      if (isset ($data["end"]) && !empty ($data["end"])) {
         if ($data["latest_reservation"] >= $now) {
            echo $data["end"];
        } else {
            echo '';
        }
      } else {
         echo '';
      }
      echo "</td></tr>";
   }
}