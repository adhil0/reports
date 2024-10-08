
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
include("../../../../inc/includes.php");

includeLocales("weekoverweekutilization");
//TRANS: The name of the report = [BETA] Week Over Week Utilization
Html::header(__('weekoverweekutilization_report_title', 'reports'), $_SERVER['PHP_SELF'], "utils", "report");

Report::title();

$where = ['entities_id' => [$_SESSION["glpiactive_entity"]]];

getObjectsbyEntity($_SESSION["glpiactive_entity"]);


Html::footer();

/**
 * Display all devices by group
 *
 * @param $entity    the current entity
 **/
function getObjectsbyEntity()
{
   global $DB, $CFG_GLPI, $_GET;
   $display_header = false;
   foreach ($CFG_GLPI["asset_types"] as $key => $itemtype) {
      if (($itemtype == 'Certificate') || ($itemtype == 'SoftwareLicense')) {
         unset($CFG_GLPI["asset_types"][$key]);
      }
      if ($itemtype == 'Computer') {
         $query = $DB->request("SELECT
         glpi_groups.id AS groups_id,
         glpi_groups.completename,
         glpi_computers.id AS computer_id,
         glpi_computers.name AS computer_name,
         glpi_computers.states_id,
         glpi_reservationitems.is_active,
         glpi_reservations.begin,
         glpi_reservations.end
         FROM
         glpi_groups
         LEFT JOIN glpi_computers ON glpi_computers.groups_id = glpi_groups.id AND glpi_computers.entities_id = '0' AND glpi_computers.is_template = '0' AND glpi_computers.is_deleted = '0'
         LEFT JOIN glpi_reservationitems ON glpi_computers.id = glpi_reservationitems.items_id
         LEFT JOIN glpi_reservations ON glpi_reservationitems.id = glpi_reservations.reservationitems_id
         ORDER BY
         glpi_groups.completename ASC,
         glpi_computers.name ASC");

         if (count($query) > 0) {
            if (!$display_header) {
               echo "<div class='alert alert-primary mt-3 text-center'>This report lists each group's average asset reservation percentage over the last 9 weeks.</div>";
               echo "<br><table class='tab_cadre_fixehov' id='datatable'>";
               echo "<thead><tr>";
               echo "<th class='center'>" . __('Group') . "</th>";
               echo "<th class='center'>" . __("# of Reservable") . "</th>";
               echo "<th class='center'>" . __("# of Non-Reservable") . "</th>";
               $week_dates = calculateWeekDates();
               foreach ($week_dates as $week => $dates) {
                  echo "<th class='center'>" . __($week) . __(" (" . gmdate("Y-m-d", $dates["start_date"]) . " - " . gmdate("Y-m-d", $dates["end_date"]) . ")") . "</th>";
               }
               echo "<th class='center'>" . __('Average') . "</th>";
               echo "<th></th><th></th></tr></thead>";
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
 * @param $type      the objet type
 * @param $result    the resultset of all the devices found
 **/
function calculateData($result)
{
   $groupData = [
      'total' => []
   ];
   for ($i = 1; $i <= 9; $i++) {
      $weekKey = 'Week ' . $i;
      $groupData['total'][$weekKey] = [
         'completename' => 'Total',
         'active_computers' => 0,
         'reservation_length' => 0,
         'time_diff' => 0,
         'usage_percentage' => 0,
         'inactive_computers' => 0,
      ];
   }
   $computers =[];
   foreach ($result as $row) {

      $groupId = $row['groups_id'];
      $groupKey = 'Group ' . $groupId;
      $begin = (new DateTime($row['begin']))->getTimestamp();
      $end = (new DateTime($row['end']))->getTimestamp();
      $computerId = $row["computer_id"];
      if (!isset($computers[$groupKey][$computerId]) && $row['is_active']) {
         $computers[$groupKey][$computerId] = true;
      } elseif (!isset($computers[$groupKey][$computerId]) && !$row['is_active']) {
         $computers[$groupKey][$computerId] = false;
      }
      $weekStartEndDates = calculateWeekDates();
      for ($i = 1; $i <= 9; $i++) {
         $weekKey = 'Week ' . $i;

         // Initialize group data array if not already set
         if (!isset($groupData[$groupKey][$weekKey])) {
            $groupData[$groupKey][$weekKey] = [
               'completename' => $row['completename'],
               'active_computers' => 0,
               'reservation_length' => 0,
               'time_diff' => 0,
               'usage_percentage' => 0,
               'inactive_computers' => 0,
            ];
         }
         $weekStart = $weekStartEndDates[$weekKey]['start_date'];
         $weekEnd = $weekStartEndDates[$weekKey]['end_date'];
         if ((($begin <= $weekEnd) && ($end >= $weekStart)) && $row['is_active'] === 1) {
            $groupData[$groupKey][$weekKey]['reservation_length'] += (min($end, $weekEnd) - max($begin, $weekStart))/60;
            $groupData['total'][$weekKey]['reservation_length'] += (min($end, $weekEnd) - max($begin, $weekStart))/60;
         }
         $groupData[$groupKey][$weekKey]['completename'] = $row['completename'];
         $activeComputers = count(array_filter($computers[$groupKey]));
         $groupData[$groupKey][$weekKey]['active_computers'] = $activeComputers;
         $groupData[$groupKey][$weekKey]['inactive_computers'] = count($computers[$groupKey]) - $activeComputers;
         $groupData[$groupKey][$weekKey]['time_diff'] = 6 * 24 * 60 * $groupData[$groupKey][$weekKey]['active_computers'];

      }
   }
   for ($i=1; $i <= 9; $i++) { 
      $weekKey = 'Week ' . $i;
      foreach ($groupData as $groupKey => $group) { 
         if ($groupKey != "total") {
            $activeComputers = count(array_filter($computers[$groupKey]));
            $groupData['total'][$weekKey]['active_computers'] += $activeComputers;
            $groupData['total'][$weekKey]['inactive_computers'] += count($computers[$groupKey]) - $activeComputers;
         }
      }
      $groupData['total'][$weekKey]['time_diff'] = 6 * 24 * 60 * $groupData['total'][$weekKey]['active_computers'];

   }

   foreach ($groupData as $group => $weeks) {
      $groupData[$group]["Average"] = 0;
      foreach ($weeks as $week => $data) {
         if ($data['time_diff'] > 0) { // To prevent division by zero
            $usagePercentage = ($data['reservation_length'] / $data['time_diff']) * 100;
            $groupData[$group][$week]['usage_percentage'] = (number_format($usagePercentage, 2)) . "%";
            $groupData[$group]["Average"] += $usagePercentage;
         } elseif ($data['active_computers'] > 0) {
            $groupData[$group][$week]['usage_percentage'] = "0.00%";
         } else {
            $groupData[$group][$week]['usage_percentage'] = "NA";
         }
      }
      $groupData[$group]['active_computers'] = $data['active_computers'];
      $groupData[$group]['inactive_computers'] = $data['inactive_computers'];
      $groupData[$group]["Average"] = number_format($groupData[$group]["Average"] / 9) . "%";
   }
   return $groupData;
}

/**
 * Display all device for a group 
 *
 * @param $type      the object type
 * @param $result    the resultset of all the devices found
 **/
function displayUserDevices($type, $result)
{
   foreach ($result as $key => $group) {
      if ($key != 'total') {
         // Display Group Name
         if (isset($group["Week 1"]["completename"])) {
            echo "<td class='center'>";
            if (!empty($group["Week 1"]["completename"])) {
               echo $group["Week 1"]["completename"];
            } else {
               echo '&nbsp;';
            }
            echo "</td>";
         }

         // Display Active/Inactive Computers
         if (isset($group["active_computers"])) {
            echo "<td class='center'>";
            echo $group["active_computers"];
            echo "</td>";
         }

         if (isset($group["inactive_computers"])) {
            echo "<td class='center'>";
            echo $group["inactive_computers"];
            echo "</td>";
         }
         // Display Weekly Percentage Usage
         foreach ($group as $week => $stats) {
            echo "</td><td class='center'>";
            if (isset($stats["usage_percentage"])) {
               echo $stats["usage_percentage"]; // TODO: Why? why not change data structure
            } elseif ($week === 'Average') {
               $groupWeeklyPercentages = array_column($group, 'usage_percentage');
               if (count(array_unique($groupWeeklyPercentages)) === 1 && end($groupWeeklyPercentages) === 'NA') {
                  echo "NA";
               } else {
                  echo $stats;
               }
            } else {
               echo '&nbsp;';
            }
         }
         echo "</td></tr>";
      }
   }

   // Display "Total" Row
   if (isset($result['total'])) {
      echo "<td class='center'>";
      echo "<p class='fw-bold'>" . $result["total"]["Week 1"]["completename"] . "</p";
      echo "</td>";
      // Display Active/Inactive Computers

      if (isset($result["total"]["active_computers"])) {
         echo "<td class='center'>";
         echo "<p class='fw-bold'>" . $result["total"]["active_computers"] . "</p";
         echo "</td>";
      }

      if (isset($result["total"]["inactive_computers"])) {
         echo "<td class='center'>";
         echo "<p class='fw-bold'>" . $result["total"]["inactive_computers"] . "</p";
         echo "</td>";
      }
      foreach ($result["total"] as $week => $utilization) {
         echo "<td class='center'>";
         if ($week != 'Average') { // TODO: why? why not change data structure
            echo "<p class='fw-bold'>" . $utilization["usage_percentage"] . "</p";
            echo "</td>";
         } else {
            echo "<p class='fw-bold'>" . $utilization . "</p";
            echo "</td>";
         }
      }
   }
   echo "</tr>";
}


function calculateWeekDates()
{
   $weekStartEndDates = array();

   // Loop through the last nine weeks
   for ($i = 1; $i <= 9; $i++) {
      $weekRewind = 9 - $i;
      // Calculate the start and end dates for each week
      $weekStartDate = date("Y-m-d", strtotime("-{$weekRewind} weeks", strtotime('this week Monday')));
      $weekEndDate = date("Y-m-d", strtotime("-{$weekRewind} weeks", strtotime('this week Sunday')));

      // Add the start and end dates to the array
      $weekStartEndDates['Week ' . $i] = array(
         'start_date' => strtotime($weekStartDate),
         'end_date' => strtotime($weekEndDate)
      );
   }
   return $weekStartEndDates;
}
