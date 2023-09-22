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
   echo "<td width='300'>";
   echo __('Group')."&nbsp;&nbsp;";
   Group::dropdown(['name =>'  => "group",
                    'value'    => isset($_GET["groups_id"]) ? $_GET["groups_id"] : 0,
                    'entity'   => $_SESSION["glpiactive_entity"],
                    'condition' => ['is_itemgroup' => 1]]);
   echo "</td>";

   // Display Reset search
   echo "<td>";
   echo "<a href='" . Plugin::getPhpDir('reports', $full = false)."/report/nonreservablebygroups/nonreservablebygroups.php?reset_search=reset_search' class='btn btn-outline-secondary'>".
   "Reset Search</a>";
   echo "</td>";

   echo "<td>";
   echo Html::submit('Submit', ['value' => 'Valider', 'class' => 'btn btn-primary']);
   echo "</td>";

   echo "</tr></table>";
   echo "<div class='alert alert-primary mt-3 text-center'>This report lists assets that have been made unavailable for reservation via the 'Make Unavailable' or 'Prohibit Reservations' button.</div>";
   Html::closeForm();
}


function getValues($get, $post) {

   $get = array_merge($get, $post);

   if (!isset ($get["group"])) {
      $get["group"] = 0;
   }
   return $get;
}


/**
 * Reset search
**/
function resetSearch() {
   $_GET["group"] = 0;
}


/**
 * Display all devices by group
 *
 * @param $group_id  the group ID
 * @param $entity    the current entity
**/
function getObjectsByGroupAndEntity($group_id, $entity) {
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
                                 groups_id,
                                 glpi_computers.comment,
                                 glpi_computers.states_id,
                                 glpi_states.completename,
                                 glpi_reservationitems.items_id,
                                 glpi_reservationitems.id,
                                 glpi_reservationitems.is_active
                              FROM 
                                 glpi_computers
                              LEFT JOIN 
                                 glpi_states ON glpi_computers.states_id = glpi_states.id
                              LEFT JOIN 
                                 glpi_reservationitems ON glpi_computers.id = glpi_reservationitems.items_id
                              WHERE 
                                 groups_id = $group_id
                                 AND (glpi_reservationitems.is_active IS NULL OR glpi_reservationitems.is_active = 0)");
        if (count($query) > 0) {
            if (!$display_header) {
                echo "<br><table class='tab_cadre_fixehov'>";
                echo "<tr><th class='center'>" .__('Type'). "</th><th class='center'>" .__('Name'). "</th>";
                echo "<th class='center'>" .__('Serial number'). "</th>";
                echo "<th class='center'>" .__('Comment')."</th>";
                echo "<th class='center'>" .__('Status')."</th>";
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
   global $DB, $CFG_GLPI;
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
      if (isset ($data["comment"]) && !empty ($data["comment"])) {
         echo $data["comment"];
      } else {
         echo '&nbsp;';
      }
      
      echo "</td><td class='center'>";
      if (isset ($data["completename"]) && !empty ($data["completename"])) {
         echo $data["completename"];
      } else {
         echo '&nbsp;';
      }
      echo "</td></tr>";
   }
}