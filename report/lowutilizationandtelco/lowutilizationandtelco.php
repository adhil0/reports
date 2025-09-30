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

includeLocales("lowutilizationandtelco");
//TRANS: The name of the report = Low Utilization and Telco Machines
Html::header(__('lowutilizationandtelco_report_title', 'reports'), $_SERVER['PHP_SELF'], "utils", "report");

Report::title();

if (isset ($_GET["reset_search"])) {
   resetSearch();
}
$_GET = getValues($_GET, $_POST);

displaySearchForm();

// Get all groups for processing
$where = ['entities_id' => [$_SESSION["glpiactive_entity"]]];
$result = $DB->request('glpi_groups', ['SELECT' => ['id', 'name'],
                                       'WHERE'  => $where,
                                       'ORDER'  => 'name']);

echo "<table class='tab_cadre' cellpadding='5'>";
echo "<tr><th>".sprintf(__('%1$s'), __('Low Utilization and Telco Machines Report'))."</th></tr>";
echo "</table>";

$display_header = false;
$total_machines_processed = 0;

foreach ($result as $datas) {
   $group_machines = getObjectsByGroupAndEntity($datas["id"], $_SESSION["glpiactive_entity"], $datas["name"]);
   
      // Process machines for this group
   foreach ($group_machines as $machine) {
      $total_machines_processed++;
      $should_display = false;
      $reason = "";
      
      // Check if it's a Telco group machine
      if (strtolower($datas["name"]) == "telco") {
         $should_display = true;
         $reason = "Telco Group";
      }
      // Check if it's a low utilization machine (and not already included as Telco)
       elseif ($machine['utilization'] !== null && $machine['utilization'] < 25) {
          $should_display = true;
          $reason = "Low Utilization (<25%)";
       }
      
      if ($should_display) {
         if (!$display_header) {
            echo "<br><table class='tab_cadre_fixehov' id='lowutilizationandtelco'>";
            echo "<thead><tr><th class='center'>" .__('Group'). "</th><th class='center'>" .__('Model'). "</th><th class='center'>" .__('Name'). "</th>";
            echo "<th class='center'>" .__('Serial number'). "</th>";
            echo "<th class='center'>" .__('Location'). "</th>";
            echo "<th class='center'>" .__('Utilization'). "</th>";
            echo "<th class='center'>" .__('Reason'). "</th>";
            echo "</tr></thead>";
            $display_header = true;
         }
         displayMachineRow($machine, $reason);
      }
   }
}

if ($display_header) {
   echo "</table>";
} else {
   echo "<br><div class='alert alert-info'>No machines found matching the criteria. Total machines processed: $total_machines_processed</div>";
}

Html::footer();


/**
 * Display search form
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
   echo "<a href='" . Plugin::getPhpDir('reports', $full = false)."/report/lowutilizationandtelco/lowutilizationandtelco.php?reset_search=reset_search' class='btn btn-outline-secondary'>".
   "Reset Search</a>";
   echo "&nbsp;";
   echo "&nbsp;";
   echo Html::submit('Submit', ['value' => 'Valider', 'class' => 'btn btn-primary']);
   echo "</td>";

   echo "</tr></table>";
   echo "<div class='alert alert-primary mt-3 text-center'>This report lists all machines in the Telco group and machines with utilization under 25% over a given time period.</div>";
   Html::closeForm();
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
 * Reset search
**/
function resetSearch() {
   $_GET["date1"] = date("Y-m-d", time() - (30 * 24 * 60 * 60));
   $_GET["date2"] = date("Y-m-d");
}


/**
 * Get all devices by group and entity
 *
 * @param $group_id  the group ID
 * @param $entity    the current entity
 * @param $group_name the group name
 * @return array     array of machine data
**/
function getObjectsByGroupAndEntity($group_id, $entity, $group_name) {
   global $DB, $CFG_GLPI, $_GET;
   
   $machines = [];
   $itemtype = 'Computer';
   
   $item = new $itemtype();
   if ($item->isField('groups_id')) {
      $query = $DB->request("SELECT   `glpi_computers`.`id`,
        `glpi_computers`.`name`,                                      
        `groups_id`,                 
        `serial`,
        `glpi_reservations`.`begin`,                                     
        `glpi_reservations`.`end`,
        `glpi_reservationitems`.`is_active`,
        `glpi_locations`.`name` as location_name,
        `glpi_computermodels`.`name` as model_name,
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
        LEFT JOIN `glpi_locations` ON (
          `glpi_locations`.`id` = `glpi_computers`.`locations_id`
        )
        LEFT JOIN `glpi_computermodels` ON (
          `glpi_computermodels`.`id` = `glpi_computers`.`computermodels_id`
        ) 
       WHERE   
        `groups_id` = $group_id                      
        AND `glpi_computers`.`entities_id` = '{$_SESSION["glpiactive_entity"]}'
        AND `is_template` = '0'
        AND `is_deleted` = '0'
        GROUP BY glpi_computers.id       
        ORDER BY `glpi_computers`.`name` ASC");

      foreach ($query as $data) {
         $utilization = null;
         if ($data["is_active"] != null && $data["diff"] > 0) {
            $utilization = round($data["true_diff"] * 100 / $data["diff"], 1);
         }
         
         $machines[] = [
            'group_name' => $group_name,
            'model_name' => $data["model_name"],
            'id' => $data["id"],
            'name' => $data["name"],
            'groups_id' => $data["groups_id"],
            'serial' => $data["serial"],
            'location_name' => $data["location_name"],
            'utilization' => $utilization,
            'is_active' => $data["is_active"]
         ];
      }
   }
   
   return $machines;
}


/**
 * Display a machine row
 *
 * @param $machine   machine data array
 * @param $reason    reason for inclusion in report
**/
function displayMachineRow($machine, $reason) {
   global $CFG_GLPI;

   $link = $machine["name"];
   $url  = Toolbox::getItemTypeFormURL('Computer');
   $link = "<a href='" . $url . "?id=" . $machine["id"] . "'>" . $link .
            (($CFG_GLPI["is_ids_visible"] || empty ($link)) ? " (" . $machine["groups_id"] . ")" : "") .
            "</a>";

   echo "<tr class='tab_bg_1'>";
   echo "<td class='center'>".$machine['group_name']."</td>";
   echo "<td class='center'>";
   if (isset($machine["model_name"]) && !empty($machine["model_name"])) {
      echo $machine["model_name"];
   } else {
      echo 'NA';
   }
   echo "</td>";
   echo "<td class='center'>$link</td>";

   echo "<td class='center'>";
   if (isset ($machine["serial"]) && !empty ($machine["serial"])) {
      echo $machine["serial"];
   } else {
      echo 'NA';
   }

   echo "</td><td class='center'>";
   if (isset ($machine["location_name"]) && !empty ($machine["location_name"])) {
      echo $machine["location_name"];
   } else {
      echo 'NA';
   }

   echo "</td><td class='center'>";
   if ($machine["is_active"] != null) {
      echo $machine["utilization"]."%";
   } else {
      echo "NA";
   }
   echo "</td>";
   echo "<td class='center'>".$reason."</td>";
   echo "</tr>";
} 