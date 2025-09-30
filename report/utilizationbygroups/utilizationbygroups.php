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

includeLocales("utilizationbygroups");
//TRANS: The name of the report = Utilization By Groups
Html::header(__('utilizationbygroups_report_title', 'reports'), $_SERVER['PHP_SELF'], "utils", "report");

Report::title();

$_GET = getValues($_GET, $_POST);

getObjectsbyEntity();


Html::footer();


function getValues($get, $post)
{

   $get = array_merge($get, $post);

   if (!isset($get["date1"])) {
      $get["date1"] = date("Y-m-d", time() - (30 * 24 * 60 * 60));
   }

   if (!isset($get["date2"])) {
      $get["date2"] = date("Y-m-d");
   }
   return $get;
}

/**
 * Display all devices by group
 *
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
               echo "<br><table class='tab_cadre_fixehov' id='utilizationbygroups'>";
               echo "<thead><tr>";
               echo "<th class='center'>" . __('Group') . "</th>";
               echo "<th class='center'>" . __('# of Statically Assigned Machines') . "</th>";
               echo "<th class='center'>" . __('# of Reservable Machines') . "</th>";
               echo "<th class='center'>" . __('Utilization of Reservable Machines') . "</th>";
               echo "</tr></thead>";
               echo "<tbody>";
               $display_header = true;
            }
            $groupData = calculateData($query);
            displayUserDevices($itemtype, $groupData);
         } else {
            echo __('No computers found');
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
function calculateData($result)
{
   $groupData = [
      'total' => [
         'completename' => 'Total',
         'active_computers' => 0,
         'inactive_computers' => 0,
         'reservation_length' => 0,
         'time_diff' => 0,
         'usage_percentage' => 0,
      ]
   ];

   foreach ($result as $data) {
      $groupId = $data['groups_id'];
      $isActive = $data['is_active'];

      // Initialize group data array if not already set
      if (!isset($groupData[$groupId])) {
         $groupData[$groupId] = [
            'completename' => $data['completename'],
            'active_computers' => 0,
            'inactive_computers' => 0,
            'reservation_length' => 0,
            'time_diff' => 0,
            'usage_percentage' => 0,
         ];
      }

      // Increment the count of active/inactive computers
      if ($isActive === 1) {
         $groupData[$groupId]['active_computers']++;
         $groupData['total']['active_computers']++;
      } elseif ($data["computer_id"] != null) {
         $groupData[$groupId]['inactive_computers']++;
         $groupData['total']['inactive_computers']++;
      }

      // Add up the true_diff and diff for each group
      if ($isActive === 1) {
         $groupData[$groupId]['reservation_length'] += $data['reservation_length'];
         $groupData[$groupId]['time_diff'] += $data['time_diff'];
         $groupData['total']['reservation_length'] += $data['reservation_length'];
         $groupData['total']['time_diff'] += $data['time_diff'];
      }
   }

   // Calculate the usage percentage for each group
   foreach ($groupData as $groupId => $data) {
      if ($data['time_diff'] > 0) { // To prevent division by zero
         $usagePercentage = ($data['reservation_length'] / $data['time_diff']) * 100;
         $groupData[$groupId]['usage_percentage'] = (number_format($usagePercentage, 2)) . "%";
      } elseif ($groupData[$groupId]['active_computers'] > 0) {
         $groupData[$groupId]['usage_percentage'] = "0.00%";
      } else {
         $groupData[$groupId]['usage_percentage'] = "NA";
      }
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
   foreach ($result as $key => $data) {
      if ($key != 'total') {
         if (isset($data["completename"])) {
            echo "<td class='center'>";
            if (!empty($data["completename"])) {
               echo $data["completename"];
            } else {
               echo 'NA';
            }
            echo "</td><td class='center'>";
            if (isset($data["inactive_computers"])) {
               echo $data["inactive_computers"];
            } else {
               echo 'NA';
            }
            echo "</td><td class='center'>";
            if (isset($data["active_computers"])) {
               echo $data["active_computers"];
            } else {
               echo 'NA';
            }
            echo "</td><td class='center'>";
            if (isset($data["usage_percentage"])) {
               echo $data["usage_percentage"];
            } else {
               echo 'NA';
            }
            echo "</td></tr>";
         }
      }
   }
   if (isset($result['total'])) {
      echo "<td class='center'>";
      echo "<p class='fw-bold'>" . $result["total"]["completename"] . "</p";
      echo "</td>";
      echo "<td class='center'>";
      echo "<p class='fw-bold'>" . $result["total"]["inactive_computers"] . "</p";
      echo "</td>";
      echo "<td class='center'>";
      echo "<p class='fw-bold'>" . $result["total"]["active_computers"] . "</p";
      echo "</td>";
      echo "<td class='center'>";
      echo "<p class='fw-bold'>" . $result["total"]["usage_percentage"] . "</p";
      echo "</td>";
   }
   echo "</tr>";
}
