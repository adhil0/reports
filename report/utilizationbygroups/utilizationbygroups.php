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

includeLocales("utilizationbygroups");
//TRANS: The name of the report = All Computers
Html::header(__('utilizationbygroups_report_title', 'reports'), $_SERVER['PHP_SELF'], "utils", "report");

Report::title();

if (isset ($_GET["reset_search"])) {
   resetSearch();
}
$_GET = getValues($_GET, $_POST);

displaySearchForm();

$where = ['entities_id' => [$_SESSION["glpiactive_entity"]]];

getObjectsbyEntity($_SESSION["glpiactive_entity"]);


Html::footer();


/**
 * Display datetime form
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
   $date2 = date("Y-m-d");
   Html::showDateField("date2", ['value'      =>  isset($_GET["date2"]) ? $_GET["date2"] : date("Y-m-d"),
                                 'maybeempty' => true]);
   echo "</td>";
   echo "</div>";
   echo "</tr>";
   // Display Reset search
   echo "<td class='center' colspan='4'>";
   echo "<a href='" . Plugin::getPhpDir('reports', $full = false)."/report/utilizationbygroups/utilizationbygroups.php?reset_search=reset_search' class='btn btn-outline-secondary'>".
   "Reset Search</a>";
   echo "&nbsp;";
   echo "&nbsp;";
   echo Html::submit('Submit', ['value' => 'Valider', 'class' => 'btn btn-primary']);
   echo "</td>";

   echo "</table>";
   echo "<div class='alert alert-primary mt-3 text-center'>This report lists the proportion of assets each group has reserved over a given time period.</div>";
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
   $secondsInMonth = 30 * 24 * 60 * 60;
   $_GET["date1"] = date("Y-m-d", time() - $secondsInMonth);
   $_GET["date2"] = date("Y-m-d");
}


/**
 * Display all devices by group
 *
 * @param $entity    the current entity
**/
function getObjectsbyEntity() {
   global $DB, $CFG_GLPI, $_GET;
   $display_header = false;
   foreach ($CFG_GLPI["asset_types"] as $key => $itemtype) {
      if (($itemtype == 'Certificate') || ($itemtype == 'SoftwareLicense')) {
         unset($CFG_GLPI["asset_types"][$key]);
      }
      if ($itemtype == 'Computer'){
       $query = $DB->request("SELECT
       glpi_groups.id AS groups_id,
       glpi_groups.completename,
       glpi_computers.id AS computer_id,
       glpi_computers.name AS computer_name,
       glpi_computers.states_id,
       glpi_reservationitems.is_active,
       TIMESTAMPDIFF(MINUTE, '{$_GET['date1']}', '{$_GET['date2']}') as time_diff,
       COALESCE(SUM(
         CASE
           WHEN glpi_reservations.begin <= '{$_GET['date2']}' AND glpi_reservations.end >= '{$_GET['date1']}'
           THEN TIMESTAMPDIFF(
             MINUTE,
             GREATEST(glpi_reservations.begin, '{$_GET['date1']}'),
             LEAST(glpi_reservations.end, '{$_GET['date2']}')
           )
           ELSE 0
         END
       ), 0) AS reservation_length
     FROM
       glpi_groups
       LEFT JOIN glpi_computers ON glpi_computers.groups_id = glpi_groups.id AND glpi_computers.entities_id = '0' AND glpi_computers.is_template = '0' AND glpi_computers.is_deleted = '0'
       LEFT JOIN glpi_reservationitems ON glpi_computers.id = glpi_reservationitems.items_id
       LEFT JOIN glpi_reservations ON glpi_reservationitems.id = glpi_reservations.reservationitems_id
     GROUP BY
       glpi_groups.id,
       glpi_computers.id
     ORDER BY
       glpi_groups.completename ASC, 
       glpi_computers.name ASC
     ");

        if (count($query) > 0) {
            if (!$display_header) {
                echo "<br><table class='tab_cadre_fixehov'>";
                echo "<tr><th class='center'>" .__('Group'). "</th>";
                echo "<th class='center'>" .__('# of Statically Assigned Machines'). "</th>";
                echo "<th class='center'>" .__('# of Reservable Machines'). "</th>";
                echo "<th class='center'>" .__('Utilization of Reservable Machines'). "</th>";
                echo "</tr>";
                $display_header = true;
            }
            $groupData = calculateData($query);
            displayUserDevices($itemtype, $groupData);
        }
     
    }
   }
   echo "</table>";
}

/**
 * Display all device for a group 
 *
 * @param $result    the result set of the SQL query
**/
function calculateData($result) {
   $groupData = [];

   foreach ($result as $data) {
      $groupId = $data['groups_id'];
      $isActive = $data['is_active'];

      // Initialize group data array if not already set
      if (!isset($groupData[$groupId])) {
         $groupData[$groupId] = [
               'completename' => $data['completename'],
               'active_computers' => 0,
               'inactive_computers' => 0,
               'summed_true_diff' => 0,
               'total_diff' => 0,
               'usage_percentage' => 0,
         ];
      }

      // Increment the count of active/inactive computers
      if ($isActive === 1) {
         $groupData[$groupId]['active_computers']++;
      } elseif ($data["computer_id"] != null) {
         $groupData[$groupId]['inactive_computers']++;
      }

      // Add up the true_diff and diff for each group
      if ($isActive === 1) {
         $groupData[$groupId]['summed_true_diff'] += $data['reservation_length'];
         $groupData[$groupId]['total_diff'] += $data['time_diff'];
      }
   }

   // Calculate the usage percentage for each group
   foreach ($groupData as $groupId => $data) {
      if ($data['total_diff'] > 0) { // To prevent division by zero
         $usagePercentage = ($data['summed_true_diff'] / $data['total_diff']) * 100;
         $groupData[$groupId]['usage_percentage'] = (number_format($usagePercentage, 2)) . "%";
      } else {
         $groupData[$groupId]['usage_percentage'] = "0.00%";
      }
   }
   return $groupData;
}


/**
 * Display all device for a group 
 *
 * @param $type      the objet type
 * @param $result    the resultset of all the devices found
**/
function displayUserDevices($type, $result) {
   foreach ($result as $data) {
    if(isset($data["completename"])) {
      echo "<td class='center'>";
      if (!empty ($data["completename"])) {
         echo $data["completename"];
      } else {
         echo '&nbsp;';
      }
      echo "</td><td class='center'>";
      if (isset ($data["inactive_computers"])) {
         echo $data["inactive_computers"];
      } else {
         echo '&nbsp;';
      }
      echo "</td><td class='center'>";
      if (isset ($data["active_computers"])) {
         echo $data["active_computers"];
      } else {
         echo '&nbsp;';
      }
      echo "</td><td class='center'>";
      if (isset ($data["usage_percentage"])) {
         echo $data["usage_percentage"];
      } else {
         echo '&nbsp;';
      }
      echo "</td></tr>";
   }
  }
}