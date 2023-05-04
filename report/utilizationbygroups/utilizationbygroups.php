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
   global $_SERVER, $_GET, $CFG_GLPI;

   echo "<form action='" . $_SERVER["PHP_SELF"] . "' method='post'>";
   echo "<table class='tab_cadre' cellpadding='5'>";
   echo "<tr class='tab_bg_2'>";
   echo "<td colspan='4' class='center'>";
   echo "<div align='center'>";
   echo "<table>";
   echo "<tr class='tab_bg_2'>";
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
   echo "</tr>";
   echo "</table>";
   echo "</div>";

   echo "</td>";
   echo "</tr>";
   // Display Reset search
   echo "<td class='center'>";
   echo "<a href='" . Plugin::getPhpDir('reports', $full = false)."/report/utilizationbygroups/utilizationbygroups.php?reset_search=reset_search' class='btn btn-outline-secondary'>".
   "Reset Search</a>";
   echo "&nbsp;";
   echo "&nbsp;";
   echo Html::submit('Submit', ['value' => 'Valider', 'class' => 'btn btn-primary']);
   echo "</td>";

   echo "</table>";
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
 * Display all devices by group
 *
 * @param $entity    the current entity
**/
function getObjectsbyEntity($entity) {
   global $DB, $CFG_GLPI, $_GET;
   $display_header = false;
   foreach ($CFG_GLPI["asset_types"] as $key => $itemtype) {
      if (($itemtype == 'Certificate') || ($itemtype == 'SoftwareLicense')) {
         unset($CFG_GLPI["asset_types"][$key]);
      }
      if ($itemtype == 'Computer'){
       $query = $DB->request("SELECT	
       `subquery`.`groups_id`,	
       `completename`,	
       SUM(diff),	
       SUM(true_diff),	
       SUM(CASE WHEN subquery.states_id IN (2,3,4,5,6) THEN 0 ELSE diff END) AS diff_sum, 	
       SUM(CASE WHEN subquery.states_id IN (2,3,4,5,6) THEN 0 ELSE true_diff END) AS true_diff_sum, 	
      COUNT(CASE WHEN subquery.states_id IN (2,3,4,5,6) THEN 1 ELSE NULL END) AS excluded_computers_count	
     FROM	
       (	
         SELECT	
           `glpi_computers`.`id`,	
           `glpi_computers`.`name`,	
           `glpi_computers`.`groups_id`,	
           `serial`,	
           `begin`,	
           `end`,	
           `glpi_computers`.`states_id`,	
           TIMESTAMPDIFF(MINUTE, '{$_GET['date1']}', '{$_GET['date2']}') as diff,	
           SUM(	
             CASE WHEN begin <= '{$_GET['date2']}'	
             AND end >= '{$_GET['date1']}' THEN TIMESTAMPDIFF(	
               MINUTE,	
               GREATEST(begin, '{$_GET['date1']}'),	
               LEAST(end, '{$_GET['date2']}')	
             ) ELSE CAST(0 AS INTEGER) END	
           ) AS true_diff	
         FROM	
           `glpi_computers`	
           LEFT JOIN (	
             SELECT	
               `items_id`,	
               `begin`,	
               `end`,	
               `glpi_reservations`.`comment`,	
               `realname`,	
               `firstname`	
             FROM	
               `glpi_reservations`	
               LEFT JOIN `glpi_reservationitems` ON (	
                 `glpi_reservationitems`.`id` = `glpi_reservations`.`reservationitems_id`	
               )	
               LEFT JOIN `glpi_users` ON (	
                 `glpi_reservations`.`users_id` = `glpi_users`.`id`	
               )	
           ) AS `data` ON (glpi_computers.id = data.items_id)	
         WHERE	
           `glpi_computers`.`entities_id` = '0'	
           AND `is_template` = '0'	
           AND `is_deleted` = '0'	
         GROUP BY	
           glpi_computers.id	
         ORDER BY	
           `glpi_computers`.`name` ASC	
       ) as subquery	
       LEFT JOIN `glpi_groups` ON (`subquery`.`groups_id` = `glpi_groups`.`id`)	
     GROUP BY	
       `groups_id`	
     ");

        if (count($query) > 0) {
            if (!$display_header) {
                echo "<br><table class='tab_cadre_fixehov'>";
                echo "<tr><th class='center'>" .__('Group'). "</th>";
                echo "<th class='center'>" .__('# of Assigned/Checked Out Machines'). "</th>";
                echo "<th class='center'>" .__('Utilization of Available Machines'). "</th>";
                echo "</tr>";
                $display_header = true;
            }
            displayUserDevices($itemtype, $query);
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
function displayUserDevices($type, $result) {
   global $DB, $CFG_GLPI, $_GET;
   $item = new $type();
   foreach ($result as $data) {
    if(isset($data["completename"])) {
      echo "<td class='center'>";
      if (!empty ($data["completename"])) {
         echo $data["completename"];
      } else {
         echo '&nbsp;';
      }
      echo "<td class='center'>";
      if (isset ($data["excluded_computers_count"]) && !empty ($data["excluded_computers_count"])) {
         echo $data["excluded_computers_count"];
      } else {
         echo '&nbsp;';
      }
      echo "</td><td class='center'>";
      echo round($data["true_diff_sum"] *100 / $data["diff_sum"], 1)."%";
      echo "</td></tr>";
   }
  }
}