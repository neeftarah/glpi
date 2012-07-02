<?php
/*
 * @version $Id$
 -------------------------------------------------------------------------
 GLPI - Gestionnaire Libre de Parc Informatique
 Copyright (C) 2003-2012 by the INDEPNET Development Team.

 http://indepnet.net/   http://glpi-project.org
 -------------------------------------------------------------------------

 LICENSE

 This file is part of GLPI.

 GLPI is free software; you can redistribute it and/or modify
 it under the terms of the GNU General Public License as published by
 the Free Software Foundation; either version 2 of the License, or
 (at your option) any later version.

 GLPI is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 GNU General Public License for more details.

 You should have received a copy of the GNU General Public License
 along with GLPI. If not, see <http://www.gnu.org/licenses/>.
 --------------------------------------------------------------------------
 */

// ----------------------------------------------------------------------
// Original Author of file: Walid Nouh
// Purpose of file:
// ----------------------------------------------------------------------
if (!defined('GLPI_ROOT')) {
   die("Sorry. You can't access directly to this file");
}

class RuleAction extends CommonDBChild {

   // From CommonDBChild
   public $items_id  = 'rules_id';
   public $dohistory = true;


   /**
    * @param $rule_type
   **/
   function __construct($rule_type='Rule') {
      $this->itemtype = $rule_type;
   }


   /**
    * Get title used in rule
    *
    * @param $nb  integer  (default 0)
    *
    * @return Title of the rule
   **/
   static function getTypeName($nb=0) {
      return _n('Action', 'Actions', $nb);
   }


   /**
    * @see inc/CommonDBTM::getName()
   **/
   function getName($with_comment=0) {

      if ($rule = getItemForItemtype($this->itemtype)) {
         return Html::clean($rule->getMinimalActionText($this->fields));
      }
   }


   function getSearchOptions() {

      $tab                     = array();

      $tab[1]['table']         = $this->getTable();
      $tab[1]['field']         = 'action_type';
      $tab[1]['name']          = self::getTypeName(1);
      $tab[1]['massiveaction'] = false;

      $tab[2]['table']         = $this->getTable();
      $tab[2]['field']         = 'field';
      $tab[2]['name']          = _n('Field', 'Fields', 2);
      $tab[2]['massiveaction'] = false;
      $tab[2]['datatype']      = 'string';

      $tab[3]['table']         = $this->getTable();
      $tab[3]['field']         = 'value';
      $tab[3]['name']          = __('Value');
      $tab[3]['massiveaction'] = false;
      $tab[3]['datatype']      = 'string';

      return $tab;
   }


   /**
    * Get all actions for a given rule
    *
    * @param $ID the rule_description ID
    *
    * @return an array of RuleAction objects
   **/
   function getRuleActions($ID) {
      global $DB;

      $sql = "SELECT *
              FROM `".$this->getTable()."`
              WHERE `".$this->items_id."` = '$ID'
              ORDER BY `id`";
      $result = $DB->query($sql);

      $rules_actions = array();
      while ($rule = $DB->fetch_assoc($result)) {
         $tmp             = new self();
         $tmp->fields     = $rule;
         $rules_actions[] = $tmp;
      }
      return $rules_actions;
   }


   /**
    * Add an action
    *
    * @param $action    action type
    * @param $ruleid    rule ID
    * @param $field     field name
    * @param $value     value
   **/
   function addActionByAttributes($action, $ruleid, $field, $value) {

      $input["action_type"]   = $action;
      $input["field"]         = $field;
      $input["value"]         = $value;
      $input[$this->items_id] = $ruleid;
      $this->add($input);
   }


   /**
    * Display a dropdown with all the possible actions
    *
    * @param $sub_type
    * @param $name
    * @param $value     (default '')
   **/
   static function dropdownActions($sub_type, $name, $value='') {

      if ($rule = getItemForItemtype($sub_type)) {
         $actions_options = $rule->getActions();

         $actions         = array("assign");
         if (isset($actions_options[$value]['force_actions'])) {
            $actions = $actions_options[$value]['force_actions'];
         }

         $elements = array();
         foreach ($actions as $action) {
            $elements[$action] = self::getActionByID($action);
         }

         return Dropdown::showFromArray($name, $elements, array('value' => $value));
      }
   }


   static function getActions() {

      return array('assign'              => __('Assign'),
                   'regex_result'        => __('Assign the value from regular expression'),
                   'append_regex_result' => __('Add the result of regular expression'),
                   'affectbyip'          => __('Assign: equipment by IP address'),
                   'affectbyfqdn'        => __('Assign: equipment by name + domain'),
                   'affectbymac'         => __('Assign: equipment by MAC address'),
                   'compute'             => __('Recalculate'),
                   'send'                => __('Send'),
                   'add_validation'      => __('Send'),
                   'fromuser'            => __('Copy from user'),
                   'fromitem'            => __('Copy from item'));
   }


   /**
    * @param $ID
   **/
   static function getActionByID($ID) {

      $actions = self::getActions();
      if (isset($actions[$ID])) {
         return $actions[$ID];
      }
      return '';
   }


   /**
    * @param $action
    * @param $regex_result
   **/
   static function getRegexResultById($action, $regex_result) {

      $results = array();

      if (count($regex_result) > 0) {
         if (preg_match_all("/#([0-9])/", $action, $results) > 0) {
            foreach ($results[1] as $result) {
               $action = str_replace("#$result",
                                     (isset($regex_result[$result])?$regex_result[$result]:''),
                                     $action);
            }
         }
      }
      return $action;
   }


   /**
    * @param $rules_id
    * @param $sub_type
   **/
   function getAlreadyUsedForRuleID($rules_id, $sub_type) {
      global $DB;

      if ($rule = getItemForItemtype($sub_type)) {
         $actions_options = $rule->getActions();

         $actions = array();
         $res     = $DB->query("SELECT `field`
                                FROM `".$this->getTable()."`
                                WHERE `".$this->items_id."` = '".$rules_id."'");

         while ($action = $DB->fetch_assoc($res)) {
            if (isset($actions_options[$action["field"]])) {
               $actions[$action["field"]] = $action["field"];
            }
         }
         return $actions;
      }
   }


   /**
    * @param $options   array
   **/
   function displayActionSelectPattern($options=array()) {

      $display = false;

      switch ($_POST["action_type"]) {
         //If a regex value is used, then always display an autocompletiontextfield
         case "regex_result" :
         case "append_regex_result" :
            Html::autocompletionTextField($this, "value");
            break;

         case 'fromuser' :
         case 'fromitem' :
            Dropdown::showYesNo("value", 0, 0);
            $display = true;
            break;

         default :
            $actions = Rule::getActionsByType($options["sub_type"]);
            if (isset($actions[$options["field"]]['type'])) {

               switch($actions[$options["field"]]['type']) {
                  case "dropdown" :
                     $table   = $actions[$options["field"]]['table'];
                     $param = array('name' => "value");
                     if (isset($actions[$options["field"]]['condition'])) {
                        $param['condition'] = $actions[$options["field"]]['condition'];
                     }
                     Dropdown::show(getItemTypeForTable($table), $param);
                     $display = true;
                     break;

                  case "dropdown_tickettype" :
                     Ticket::dropdownType('value');
                     $display = true;
                     break;

                  case "dropdown_assign" :
                     User::dropdown(array('name'  => 'value',
                                          'right' => 'own_ticket'));
                     $display = true;
                     break;

                  case "dropdown_users" :
                     User::dropdown(array('name'  => 'value',
                                          'right' => 'all'));
                     $display = true;
                     break;

                  case "dropdown_urgency" :
                     Ticket::dropdownUrgency("value");
                     $display = true;
                     break;

                  case "dropdown_impact" :
                     Ticket::dropdownImpact("value");
                     $display = true;
                     break;

                  case "dropdown_priority" :
                     if ($_POST["action_type"] != 'compute') {
                        Ticket::dropdownPriority("value");
                     }
                     $display = true;
                     break;

                  case "dropdown_status" :
                     Ticket::dropdownStatus("value");
                     $display = true;
                     break;

                  case "yesonly" :
                     Dropdown::showYesNo("value",0,0);
                     $display = true;
                     break;

                  case "yesno" :
                     Dropdown::showYesNo("value");
                     $display = true;
                     break;

                  case "dropdown_management":
                     Dropdown::showGlobalSwitch(0, array('name'                => 'value',
                                                         'management_restrict' => 2,
                                                         'withtemplate'        => false));
                     $display = true;
                     break;

                  case "dropdown_users_validate" :
                     User::dropdown(array('name'   => "value",
                                          'right'  => 'validate_ticket'));
                     $display = true;
                     break;

                  default :
                     if ($rule = getItemForItemtype($options["sub_type"])) {
                        $display = $rule->displayAdditionalRuleAction($actions[$options["field"]]);
                     }
                     break;
               }
            }

            if (!$display) {
               Html::autocompletionTextField($this, "value");
            }
      }
   }

}
?>