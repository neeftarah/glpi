<?php
/*
 * @version $Id$
 -------------------------------------------------------------------------
 GLPI - Gestionnaire Libre de Parc Informatique
 Copyright (C) 2003-2006 by the INDEPNET Development Team.

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
 along with GLPI; if not, write to the Free Software
 Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 --------------------------------------------------------------------------
 */

// ----------------------------------------------------------------------
// Original Author of file:
// Purpose of file:
// ----------------------------------------------------------------------



$NEEDED_ITEMS=array("user","profile","group","setup","tracking","computer","printer","networking","peripheral","monitor","software","enterprise","phone", "reservation");

define('GLPI_ROOT', '..');
include (GLPI_ROOT . "/inc/includes.php");

if(empty($_GET["ID"])) $_GET["ID"] = "";

$start=0;
if (isset($_GET["start"])) {
	$start=$_GET["start"];
}


$user=new User();
if (empty($_GET["ID"])&&isset($_GET["name"])){

	$user->getFromDBbyName($_GET["name"]);
	glpi_header($CFG_GLPI["root_doc"]."/front/user.form.php?ID=".$user->fields['ID']);
}

if(empty($_GET["name"])) $_GET["name"] = "";

if (isset($_POST["add"])) {
	checkRight("user","w");

	// Pas de nom pas d'ajout	
	if (!empty($_POST["name"])){
		$newID=$user->add($_POST);
		logEvent($newID, "users", 4, "setup", $_SESSION["glpiname"]." ".$LANG["log"][20]." ".$_POST["name"].".");
	}
	glpi_header($_SERVER['HTTP_REFERER']);
} else if (isset($_POST["delete"])) {
	checkRight("user","w");

	$user->delete($_POST);
	logEvent(0,"users", 4, "setup", $_SESSION["glpiname"]."  ".$LANG["log"][22]." ".$_POST["ID"].".");
	glpi_header($CFG_GLPI["root_doc"]."/front/user.php");
} else if (isset($_POST["update"])) {
	checkRight("user","w");

	$user->update($_POST);
	logEvent(0,"users", 5, "setup", $_SESSION["glpiname"]."  ".$LANG["log"][21]."  ".$_POST["name"].".");
	glpi_header($_SERVER['HTTP_REFERER']);
} else if (isset($_POST["addgroup"]))
{
	checkRight("user","w");

	addUserGroup($_POST["FK_users"],$_POST["FK_groups"]);

	logEvent($_POST["FK_users"], "users", 4, "setup", $_SESSION["glpiname"]." ".$LANG["log"][48]);
	glpi_header($_SERVER['HTTP_REFERER']);
}
else if (isset($_POST["deletegroup"]))
{
	checkRight("user","w");
	if (count($_POST["item"]))
		foreach ($_POST["item"] as $key => $val)
			deleteUserGroup($key);

	logEvent($_POST["FK_users"], "users", 4, "setup", $_SESSION["glpiname"]." ".$LANG["log"][49]);
	glpi_header($_SERVER['HTTP_REFERER']);
} else {


	if (!isset($_SESSION['glpi_onglet'])) $_SESSION['glpi_onglet']=1;
	if (isset($_GET['onglet'])) {
		$_SESSION['glpi_onglet']=$_GET['onglet'];
	}


	if (!isset($_GET["ext_auth"])){
		checkRight("user","r");

		commonHeader($LANG["title"][13],$_SERVER['PHP_SELF']);

		if ($user->showForm($_SERVER['PHP_SELF'],$_GET["ID"])){
			if (!empty($_GET["ID"]))
			switch($_SESSION['glpi_onglet']){
				case -1:
					showGroupAssociated($_SERVER['PHP_SELF'],$_GET["ID"]);
					showDeviceUser($_GET["ID"]);
					showTrackingList($_SERVER['PHP_SELF'],$start,"","","all",$_GET["ID"],-1);
					showUserReservations($_SERVER['PHP_SELF'],$_GET["ID"]);
					display_plugin_action(USER_TYPE,$_GET["ID"],$_SESSION['glpi_onglet']);
					break;
				case 1 :
					showGroupAssociated($_SERVER['PHP_SELF'],$_GET["ID"]);
					break;
				case 2 :
					showDeviceUser($_GET["ID"]);
					break;
				case 3 :
					showTrackingList($_SERVER['PHP_SELF'],$start,"","","all",$_GET["ID"],-1);
					break;
				case 11 :
					showUserReservations($_SERVER['PHP_SELF'],$_GET["ID"]);
					break;
				default : 
					if (!display_plugin_action(USER_TYPE,$_GET["ID"],$_SESSION['glpi_onglet']))
						showGroupAssociated($_SERVER['PHP_SELF'],$_GET["ID"]);
					break;
			}
			
		}
		commonFooter();
	} else {
		if (isset($_GET['add_ext_auth'])){
			if (isset($_GET['login'])&&!empty($_GET['login'])){
				$ldap_method = $user->getAuthMethods();
				// LDAP case : get all informations
				if (!empty($ldap_method["ldap_host"])){

					// Get dn without testing login
					$ds=connect_ldap($ldap_method["ldap_host"],$ldap_method["ldap_port"],$ldap_method["ldap_rootdn"],$ldap_method["ldap_pass"],$ldap_method["ldap_use_tls"]);
					if ($ds){
						$user_dn = ldap_search_user_dn($ds,$ldap_method["ldap_basedn"],$ldap_method["ldap_login"],utf8_decode($_GET['login']),$ldap_method["ldap_condition"]); 
						if ($user_dn) {
							$identificat = new Identification();
							$identificat->user->getFromLDAP($ldap_method["ldap_host"],$ldap_method["ldap_port"],$user_dn,$ldap_method["ldap_rootdn"],$ldap_method["ldap_pass"],$ldap_method['ldap_fields'],utf8_decode($_GET['login']),"",$CFG_GLPI["ldap_use_tls"]);
							$identificat->user->fields["_extauth"]=1;
							$input=$identificat->user->fields;
							unset($identificat->user->fields);
							$identificat->user->add($input);
						}
					}
				} else {
					$user=new User();
					$input["name"]=$_GET['login'];
					$input["_extauth"]=1;
					$user->add($input);
				}
			}
			glpi_header($_SERVER['HTTP_REFERER']);
		}
		checkRight("user","w");
		commonHeader($LANG["title"][13],$_SERVER['PHP_SELF']);
		showAddExtAuthUserForm($_SERVER['PHP_SELF']);
		commonFooter();
	}
}





?>
