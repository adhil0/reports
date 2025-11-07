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

includeLocales("utilizationbymachines");
//TRANS: The name of the report = Utilization By Machines
Html::header(__('utilizationbymachines_report_title', 'reports'), $_SERVER['PHP_SELF'], "utils", "report");

Report::title();

if (isset ($_GET["reset_search"])) {
   resetSearch();
}

$_GET = getValues($_GET, $_POST);
displaySearchForm();
getObjectsByGroupAndEntity();

Html::footer();



/**
 * Reset search
**/
function resetSearch() {
   $_GET["date1"] = date("Y-m-d", time() - (30 * 24 * 60 * 60));
   $_GET["date2"] = date("Y-m-d");
}


function getValues($get, $post) {

   $get = array_merge($get, $post);

   if (!isset ($get["date1"])) {
      $get["date1"] = date("Y-m-d", time() - (30 * 24 * 60 * 60));
   }

   if (!isset ($get["date2"])) {
      $get["date2"] = date("Y-m-d");
   }
   return $get;
}

/**
 * Display group form
**/
function displaySearchForm() {
   global $_SERVER, $_GET;

   echo "<form action='" . $_SERVER["PHP_SELF"] . "' method='post'>";
   echo "<table class='tab_cadre' cellpadding='5'>";

   echo "<tr class='tab_bg_2'>";
   echo "<div align='center'>";
   echo "<td>".__("<b>Begin date</b>")."</td>";
   echo "<td>";
   Html::showDateField("date1", ['value'      =>  isset($_GET["date1"]) ? $_GET["date1"] : date("Y-m-d", time() - (30 * 24 * 60 * 60)),
                                 'maybeempty' => true]);
   echo "</td>";
   echo "<td>".__("<b>End date</b>")."</td>";
   echo "<td>";
   Html::showDateField("date2", ['value'      =>  isset($_GET["date2"]) ? $_GET["date2"] : date("Y-m-d"),
                                 'maybeempty' => true]);
   echo "</td>";
   echo "</div>";
   echo "</tr>";
   // Display Reset search
   echo "<td class='center' colspan='4'>";
   echo "<a href='" . Plugin::getPhpDir('reports', $full = false)."/report/utilizationbymachines/utilizationbymachines.php?reset_search=reset_search' class='btn btn-outline-secondary'>".
   "Reset Search</a>";
   echo "&nbsp;";
   echo "&nbsp;";
   echo Html::submit('Submit', ['value' => 'Valider', 'class' => 'btn btn-primary']);
   echo "</td>";

   echo "</tr></table>";
   echo "<div class='alert alert-primary mt-3 text-center'>This report lists the proportion of time each asset has been reserved for over a given time period, by group.</div>";
   Html::closeForm();
}

/**
 * Display all devices by group
**/
function getObjectsByGroupAndEntity() {
   global $DB, $CFG_GLPI, $_GET;
   $display_header = false;
   foreach ($CFG_GLPI["asset_types"] as $key => $itemtype) {
      if (($itemtype == 'Certificate') || ($itemtype == 'SoftwareLicense')) {
         unset($CFG_GLPI["asset_types"][$key]);
      }
      if ($itemtype == 'Computer'){
      $item = new $itemtype();
      if ($item->isField('groups_id')) {
       $query = $DB->request("SELECT   `glpi_computers`.`id`,
         `glpi_computers`.`name`,                                      
         `glpi_groups`.`id` AS `groups_id`,
         `glpi_groups`.`completename` AS `group_name`,                 
         `serial`,
         `glpi_reservations`.`begin`,                                     
         `glpi_reservations`.`end`,
         `glpi_reservationitems`.`is_active`,
         TIMESTAMPDIFF(MINUTE,'{$_GET['date1']}','{$_GET['date2']}') as diff,
         SUM(CASE WHEN `glpi_reservations`.`begin`<='{$_GET['date2']}' AND `glpi_reservations`.`end` >= '{$_GET['date1']}' THEN TIMESTAMPDIFF(MINUTE,GREATEST(`glpi_reservations`.`begin`,'{$_GET['date1']}'),LEAST(`glpi_reservations`.`end`,'{$_GET['date2']}'))
               ELSE CAST(0 AS INTEGER)
        END) AS true_diff
       FROM                     
         `glpi_computers`                                           
         LEFT JOIN `glpi_reservationitems` ON (
           `glpi_reservationitems`.`items_id` = `glpi_computers`.`id`
           AND `glpi_reservationitems`.`itemtype` = 'Computer'
         )
         LEFT JOIN `glpi_reservations` ON (
           `glpi_reservations`.`reservationitems_id` = `glpi_reservationitems`.`id`
         )
         LEFT JOIN `glpi_groups` ON `glpi_computers`.`groups_id` = `glpi_groups`.`id`
        WHERE   
         `glpi_computers`.`entities_id` = '0'
         AND `is_template` = '0'
         AND `is_deleted` = '0'
         GROUP BY glpi_computers.id       
         ORDER BY `glpi_computers`.`name` ASC");

        if (count($query) > 0) {
            if (!$display_header) {
                echo "<br><table class='tab_cadre_fixehov' id='utilizationbymachines'>";
                echo "<thead><tr>";
                echo "<th class='center'>" .__('Type'). "</th>";
                echo "<th class='center'>" .__('Name'). "</th>";
                echo "<th class='center'>" .__('Group'). "</th>";
                echo "<th class='center'>" .__('Serial number'). "</th>";
                echo "<th class='center'>" .__('Utilization'). "</th>";
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

   $item = new $type();
   foreach ($result as $data) {
      $link = $data["name"];
      $url  = Toolbox::getItemTypeFormURL("$type");
      $link = "<a href='" . $url . "?id=" . $data["id"] . "'>" . $link .
               (($CFG_GLPI["is_ids_visible"] || empty ($link)) ? " (" . $data["groups_id"] . ")" : "") .
               "</a>";

      echo "<tr class='tab_bg_1'><td class='center'>".$item->getTypeName()."</td>";
      echo "<td class='center'>$link</td>";

      echo "<td class='center'>";
      if (isset ($data["group_name"]) && !empty ($data["group_name"])) {
         echo $data["group_name"];
      } else {
         echo "NA";
      }
      echo "</td>";
      echo "<td class='center'>";
      if (isset ($data["serial"]) && !empty ($data["serial"])) {
         echo $data["serial"];
      } else {
         echo "NA";
      }

      echo "</td><td class='center'>";
      if ($data["is_active"] != null) {
         echo round($data["true_diff"] *100 / $data["diff"], 1)."%";
      } else {
         echo "NA";
      }
      echo "</td></tr>";
   }
}