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
if (isset($_GET["groups_id"]) && $_GET["groups_id"]) {
   $where = ['entities_id' => [$_SESSION["glpiactive_entity"]],
             'id'          => $_GET['groups_id']];
}

$result = $DB->request('glpi_groups', ['SELECT' => ['id', 'name'],
                                       'WHERE'  => $where,
                                       'ORDER'  => 'name']);
$last_group_id = -1;

foreach ($result as $datas) {
   if ($last_group_id != $datas["id"]) {
      echo "<table class='tab_cadre' cellpadding='5'>";
      echo "<tr><th>".sprintf(__('%1$s: %2$s'), __('Group'), $datas['name'])."</th></th></tr>";
      $last_group_id = $datas["id"];
      echo "</table>";
   }

   getObjectsByGroupAndEntity($datas["id"], $_SESSION["glpiactive_entity"]);
}

Html::footer();


/**
 * Display group form
**/
function displaySearchForm() {
   global $_SERVER, $_GET, $CFG_GLPI;

   echo "<form action='" . $_SERVER["PHP_SELF"] . "' method='post'>";
   echo "<table class='tab_cadre' cellpadding='5'>";
   echo "<tr class='tab_bg_1 center'>";
   echo "<td colspan='2'>";
   echo __('Group')."&nbsp;&nbsp;";
   Group::dropdown(['name =>'  => "group",
                    'value'    => isset($_GET["groups_id"]) ? $_GET["groups_id"] : 0,
                    'entity'   => $_SESSION["glpiactive_entity"],
                    'condition' => ['is_itemgroup' => 1]]);
   echo "</td>";

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
   echo "<td>";
   echo "<a href='" . Plugin::getPhpDir('reports', $full = false)."/report/utilizationbygroups/utilizationbygroups.php?reset_search=reset_search'>".
         "<img title='" . __s('Reset Search') . "' alt='" . __s('Reset Search') . "' src='" .
         $CFG_GLPI["root_doc"] . "/pics/reset.png' class='calendrier'></a>";
   echo "</td>";
   echo "<td>";
   echo Html::submit('Submit', ['value' => 'Valider', 'class' => 'btn btn-primary']);
   echo "</td>";

   echo "</tr></table>";
   Html::closeForm();
}


function getValues($get, $post) {

   $get = array_merge($get, $post);

   if (!isset ($get["group"])) {
      $get["group"] = 0;
   }

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
   $_GET["group"] = 0;
   $_GET["date1"] = date("Y-m-d", time() - (30 * 24 * 60 * 60));
   $_GET["date2"] = date("Y-m-d");
}


/**
 * Display all devices by group
 *
 * @param $group_id  the group ID
 * @param $entity    the current entity
**/
function getObjectsByGroupAndEntity($group_id, $entity) {
   global $DB, $CFG_GLPI, $_GET;
   $display_header = false;
   foreach ($CFG_GLPI["asset_types"] as $key => $itemtype) {
      if (($itemtype == 'Certificate') || ($itemtype == 'SoftwareLicense')) {
         unset($CFG_GLPI["asset_types"][$key]);
      }
      if ($itemtype == 'Computer'){
      $item = new $itemtype();
      if ($item->isField('groups_id')) {
        $inner_query = new \QuerySubQuery(['SELECT' => ['items_id', 'begin', 'end'],
            'FROM' => 'glpi_reservations',
            'LEFT JOIN' => ['glpi_reservationitems' => ['FKEY' => ['glpi_reservations' => 'reservationitems_id', 'glpi_reservationitems' => 'id',
            ]]]], 'data');

       $query = $DB->request("SELECT   `glpi_computers`.`id`,
         `glpi_computers`.`name`,                                      
         `groups_id`,                 
         `serial`,
         `begin`,                                     
         `end`,
         TIMESTAMPDIFF(MINUTE,'{$_GET['date1']}','{$_GET['date2']}') as diff,
           SUM(CASE WHEN begin<='{$_GET['date2']}' AND end >= '{$_GET['date1']}' THEN TIMESTAMPDIFF(MINUTE,GREATEST(begin,'{$_GET['date1']}'),LEAST(end,'{$_GET['date2']}'))
                ELSE CAST(0 AS INTEGER)
        END) AS true_diff
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
         `groups_id` = $group_id                      
         AND `glpi_computers`.`entities_id` = '0'
         AND `is_template` = '0'
         AND `is_deleted` = '0'
         GROUP BY glpi_computers.id       
         ORDER BY `glpi_computers`.`name` ASC");

        if (count($query) > 0) {
            if (!$display_header) {
                echo "<br><table class='tab_cadre_fixehov'>";
                echo "<tr><th class='center'>" .__('Type'). "</th><th class='center'>" .__('Name'). "</th>";
                echo "<th class='center'>" .__('Serial number'). "</th>";
                echo "<th class='center'>" .__('Utilization'). "</th>";
                echo "</tr>";
                $display_header = true;
            }
            displayUserDevices($itemtype, $query);
        }
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
   $time = time();
   $now = date("Y-m-d H:i:s", $time);
   $item = new $type();
   foreach ($result as $data) {
      $link = $data["name"];
      $url  = Toolbox::getItemTypeFormURL("$type");
      $link = "<a href='" . $url . "?id=" . $data["id"] . "'>" . $link .
               (($CFG_GLPI["is_ids_visible"] || empty ($link)) ? " (" . $data["groups_id"] . ")" : "") .
               "</a>";
      $linktype = "";
      if (isset ($groups[$data["id"]])) {
         $linktype = sprintf(__('%1$s %2$s'), __('Group'), $groups[$data["groups_id"]]);
      }

      echo "<tr class='tab_bg_1'><td class='center'>".$item->getTypeName()."</td>".
            "<td class='center'>$link</td>";

      echo "<td class='center'>";
      if (isset ($data["serial"]) && !empty ($data["serial"])) {
         echo $data["serial"];
      } else {
         echo '&nbsp;';
      }

      echo "</td><td class='center'>";
      echo round($data["true_diff"] *100 / $data["diff"], 1)."%";
      echo "</td></tr>";
   }
}