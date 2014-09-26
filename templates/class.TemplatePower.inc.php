<?php

/* vim: set expandtab tabstop=4 shiftwidth=4: */
// +----------------------------------------------------------------------+
// | TemplatePower:                                                       |
// | offers you the ability to separate your PHP code and your HTML       |
// +----------------------------------------------------------------------+
// |                                                                      |
// | Copyright (C) 2001,2002  R.P.J. Velzeboer, The Netherlands           |
// |                                                                      |
// | This program is free software; you can redistribute it and/or        |
// | modify it under the terms of the GNU General Public License          |
// | as published by the Free Software Foundation; either version 2       |
// | of the License, or (at your option) any later version.               |
// |                                                                      |
// | This program is distributed in the hope that it will be useful,      |
// | but WITHOUT ANY WARRANTY; without even the implied warranty of       |
// | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the        |
// | GNU General Public License for more details.                         |
// |                                                                      |
// | You should have received a copy of the GNU General Public License    |
// | along with this program; if not, write to the Free Software          |
// | Foundation, Inc., 59 Temple Place - Suite 330, Boston, MA            |
// | 02111-1307, USA.                                                     |
// |                                                                      |
// | Author: R.P.J. Velzeboer, rovel@codocad.nl   The Netherlands         |
// |                                                                      |
// +----------------------------------------------------------------------+
// | http://templatepower.codocad.com                                     |
// +----------------------------------------------------------------------+
//
// $Id: Version 3.0.1$

/**
* PP 3.01 ajout d'une m�thode lastMinuteFixup() qui peut �tre surcharg�e par
une sous-classe pour finir des traductions ...
* PP 3.02 ajout du marqueur <!-- VAR : xxx=vvvv --> qui permet de definir des
*   variables de templates voir http://templatepower.codocad.com/phpBB/viewtopic.php?t=19
* et  START COMMENT  END COMMENT
* PP 3.03 01/12/2008 correction pour les blocs CDATA emis pour certains javascript
*    comme ceux de HTM_Quickform  ou les templates "inline"
* PP 3.04 10/02/2009 ajout info de debug si la constante $CFG->debug_templates et activ�e.
*
* renomm� la classe TemplatePower en TemplatePower304
* cr�� une classe TemplatePower qui en fait beaucoup plus 'ainsi on n'a pas
* besoin de changer les new dans tous les scripts
* PP 3.05 ajout qq m�thodes sympas (report�es depuis C2ITemplate en attendant migration totale V2
* PP 3.06  prepare accepte un chemin et fait tout le boulot
**/

define("T_BYFILE", 0);
define("T_BYVAR", 1);

define("TP_ROOTBLOCK", '_ROOT');

class TemplatePowerParser {
	var $tpl_base; //Array( [filename/varcontent], [T_BYFILE/T_BYVAR] )
	var $tpl_include; //Array( [filename/varcontent], [T_BYFILE/T_BYVAR] )
	var $tpl_count;

	var $parent = Array (); // $parent[{blockname}] = {parentblockname}
	var $defBlock = Array ();

	var $rootBlockName;
	var $ignore_stack;

	var $version;

	/**
	 * TemplatePowerParser::TemplatePowerParser()
	 *
	 * @param $tpl_file
	 * @param $type
	 * @return
		*
		* @access private
	 */
	function TemplatePowerParser($tpl_file, $type) {
		$this->version = '3.0.6';

		$this->tpl_base = Array (
			$tpl_file,
			$type
		);
		$this->tpl_count = 0;
		$this->ignore_stack = Array (
			false
		);
	}

	/**
	 * TemplatePowerParser::__errorAlert()
	 *
	 * @param $message
	 * @return
		*
		* @access private
	 */
	function __errorAlert($message) {
		print ('<br>' . $message . '<br>\r\n');
	}

	/**
	 * TemplatePowerParser::__prepare()
	 *
	 * @return
		*
		* @access private
	 */
	function __prepare() {
		$this->defBlock[TP_ROOTBLOCK] = Array ();
		$tplvar = $this->__prepareTemplate($this->tpl_base[0], $this->tpl_base[1]);

		$initdev["varrow"] = 0;
		$initdev["coderow"] = 0;
		$initdev["index"] = 0;
		$initdev["ignore"] = false;

		$this->__parseTemplate($tplvar, TP_ROOTBLOCK, $initdev);
		$this->__cleanUp();
	}

	/**
	 * TemplatePowerParser::__cleanUp()
	 *
	 * @return
	 *
	 * @access private
	 */
	function __cleanUp() {
		for ($i = 0; $i <= $this->tpl_count; $i++) {
			$tplvar = 'tpl_rawContent' . $i;
			unset ($this-> {
				$tplvar });
		}
	}

	/**
	 * TemplatePowerParser::__prepareTemplate()
	 *
	 * @param $tpl_file
	 * @param $type
	 * @return
	 *
	 * @access private
	 */
	function __prepareTemplate($tpl_file, $type) {
		$tplvar = 'tpl_rawContent' . $this->tpl_count;

		if ($type == T_BYVAR) {
			// pb avec des fichiers issus de mac/windows
			// cette manip casse tout et redonne le bug de php 5.2.9 avec php 5.2.10
			$tpl_file = preg_replace("/\r\n|\r/", "\n", $tpl_file);
			$this->{$tplvar}["content"] = preg_split("/\n/" , $tpl_file, -1, PREG_SPLIT_DELIM_CAPTURE);

            // rev 1045 php = 5.2.9  remettre les sauts de lignes
//mais casse les HTMl_tree (acces, parcours) donc annul�e

            for ($i=0; $i<sizeof($this->{$tplvar}["content"]);$i++)
               $this-> {$tplvar}["content"][$i] .="\n";


		} else {
			$this-> {
				$tplvar }
			["content"] = @ file($tpl_file) or die($this->__errorAlert('TemplatePower Error: Couldn\'t open [ ' . $tpl_file . ' ]!'));
		}

		$this-> {
			$tplvar }
		["size"] = sizeof($this-> {
			$tplvar }
		["content"]);




		$this->tpl_count++;

		return $tplvar;
	}

	/**
	 * TemplatePowerParser::__parseTemplate()
	 *
	 * @param $tplvar
	 * @param $blockname
	 * @param $initdev
	 * @return
	 *
	 * @access private
	 */
	function __parseTemplate($tplvar, $blockname, $initdev) {
		$coderow = $initdev["coderow"];
		$varrow = $initdev["varrow"];
		$index = $initdev["index"];
		$ignore = $initdev["ignore"];
		$inComment = false;
		$inCdata = false;
		while ($index < $this-> {
			$tplvar }
		["size"]) {
			/*
			PP 01/12/2008 traitement sp�cial des blocs CDATA emis par HTML_quickform
			less accolades ouvrantes sont sur des lignes seules ET
			il faut ABSOLUMENT que les sauts de lignes soient remis sinon le script est ignor� par le navigateur !
			exemple
			<script type="text/javascript">
			//<![CDATA[                <------------ ce saut de ligne est capital
			function _hs_findOptions(ary, keys)
			{
			var key = keys.shift();
			if (!key in ary) {
			 return {};
			} else if (0 == keys.length) {
			 return ary[key];
			} else {
			 return _hs_findOptions(ary[key], keys);
			}
			}


			var _hs_prevOnload = null;
			if (window.onload) {
			_hs_prevOnload = window.onload;
			}
			window.onload = _hs_onReload;

			var _hs_options = {};
			var _hs_defaults = {};

			_hs_options['location'] = [
			{ '0': { '0': 'London', '1': 'Manchester', '2': 'Liverpool' }, '1': { '3': 'Edinburgh', '4': 'Glasgow' }, '2': { '5': 'Fort Worth', '6': 'Boston', '7': 'Los Angles' } }
			];
			_hs_defaults['location'] = ['0', '0'];
			//]]>                 <------------ et celui ci aussi
			</script>
			*/
			if (preg_match('/\/\/<!\[CDATA\[/', $this-> {
				$tplvar }
			["content"][$index], $tmp)) {
				$inCdata = true;
				$this->defBlock[$blockname]["_C:$coderow"] = "\n" . $this-> {
					$tplvar }
				["content"][$index] . "\n";
				$coderow++;
				$index++;
				//print "cdata";
				continue;
			} else
				if (preg_match('/\/\/]]>/', $this-> {
					$tplvar }
			["content"][$index], $tmp)) {
				$inCdata = false;
				$this->defBlock[$blockname]["_C:$coderow"] = "\n" . $this-> {
					$tplvar }
				["content"][$index] . "\n";
				$coderow++;
				$index++;
				continue;
			}
			if ($inCdata) {
				$this->defBlock[$blockname]["_C:$coderow"] = "\n" . $this-> {
					$tplvar }
				["content"][$index] . "\n";
				$coderow++;
				$index++;
				continue;
			}
			// rev 3.03 PP de vrais blocks comment�s on n'emet pas le HTML
			if (preg_match('/<!--[ ]?(START|END) COMMENT -->/', $this-> {
				$tplvar }
			["content"][$index], $ignreg)) {
				if ($ignreg[1] == 'START') {
					$inComment = true;

				} else {
					$inComment = false;

				}
				//$coderow++;
				$index++;
				continue;
			}
			if ($inComment) {
				//$coderow++;
				$index++;
				continue;
			}
			// rev 3.02 from here  variables globales
			if (preg_match('/<!--[ ]?(VAR) : (.+)-->/', $this-> {
				$tplvar }
			["content"][$index], $valregs)) {
				$value = explode(":=", $valregs[2]);
				$name = trim($value[0]);
				$val = trim($value[1]);
				$this-> {
					$name }
				= $val;
			} //to here
			if (preg_match('/<!--[ ]?(START|END) IGNORE -->/', $this-> {
				$tplvar }
			["content"][$index], $ignreg)) {
				if ($ignreg[1] == 'START') {
					//$ignore = true;
					array_push($this->ignore_stack, true);
				} else {
					//$ignore = false;
					array_pop($this->ignore_stack);
				}
			} else {
				if (!end($this->ignore_stack)) {
					if (preg_match('/<!--[ ]?(START|END|INCLUDE|INCLUDESCRIPT|REUSE) BLOCK : (.+)-->/', $this-> {
						$tplvar }
					["content"][$index], $regs)) {
						//remove trailing and leading spaces
						$regs[2] = trim($regs[2]);

						if ($regs[1] == 'INCLUDE') {
							$include_defined = true;

							//check if the include file is assigned
							if (isset ($this->tpl_include[$regs[2]])) {
								$tpl_file = $this->tpl_include[$regs[2]][0];
								$type = $this->tpl_include[$regs[2]][1];
							} else
								if (file_exists($regs[2])) //check if defined as constant in template
									{
									$tpl_file = $regs[2];
									$type = T_BYFILE;
								} else {
									$include_defined = false;
								}

							if ($include_defined) {
								//initialize startvalues for recursive call
								$initdev["varrow"] = $varrow;
								$initdev["coderow"] = $coderow;
								$initdev["index"] = 0;
								$initdev["ignore"] = false;

								$tplvar2 = $this->__prepareTemplate($tpl_file, $type);
								$initdev = $this->__parseTemplate($tplvar2, $blockname, $initdev);

								$coderow = $initdev["coderow"];
								$varrow = $initdev["varrow"];
							}
						} else
							if ($regs[1] == 'INCLUDESCRIPT') {
								$include_defined = true;

								//check if the includescript file is assigned by the assignInclude function
								if (isset ($this->tpl_include[$regs[2]])) {
									$include_file = $this->tpl_include[$regs[2]][0];
									$type = $this->tpl_include[$regs[2]][1];
								} else
									if (file_exists($regs[2])) //check if defined as constant in template
										{
										$include_file = $regs[2];
										$type = T_BYFILE;
									} else {
										$include_defined = false;
									}

								if ($include_defined) {
									ob_start();

									if ($type == T_BYFILE) {
										if (!@ include_once ($include_file)) {
											$this->__errorAlert('TemplatePower Error: Couldn\'t include script [ ' . $include_file . ' ]!');
											exit ();
										}
									} else {
										eval ("?>" . $include_file);
									}

									$this->defBlock[$blockname]["_C:$coderow"] = ob_get_contents();
									$coderow++;

									ob_end_clean();
								}
							} else
								if ($regs[1] == 'REUSE') {
									//do match for 'AS'
									if (preg_match('/(.+) AS (.+)/', $regs[2], $reuse_regs)) {
										$originalbname = trim($reuse_regs[1]);
										$copybname = trim($reuse_regs[2]);

										//test if original block exist
										if (isset ($this->defBlock[$originalbname])) {
											//copy block
											$this->defBlock[$copybname] = $this->defBlock[$originalbname];

											//tell the parent that he has a child block
											$this->defBlock[$blockname]["_B:" . $copybname] = '';

											//create index and parent info
											$this->index[$copybname] = 0;
											$this->parent[$copybname] = $blockname;
										} else {
											$this->__errorAlert('TemplatePower Error: Can\'t find block \'' . $originalbname . '\' to REUSE as \'' . $copybname . '\'');
										}
									} else {
										//so it isn't a correct REUSE tag, save as code
										$this->defBlock[$blockname]["_C:$coderow"] = $this-> {
											$tplvar }
										["content"][$index];
										$coderow++;
									}
								} else {
									if ($regs[2] == $blockname) //is it the end of a block
										{
										break;
									} else //its the start of a block
										{
										//make a child block and tell the parent that he has a child
										$this->defBlock[$regs[2]] = Array ();
										$this->defBlock[$blockname]["_B:" . $regs[2]] = '';

										//set some vars that we need for the assign functions etc.
										$this->index[$regs[2]] = 0;
										$this->parent[$regs[2]] = $blockname;

										//prepare for the recursive call
										$index++;
										$initdev["varrow"] = 0;
										$initdev["coderow"] = 0;
										$initdev["index"] = $index;
										$initdev["ignore"] = false;

										$initdev = $this->__parseTemplate($tplvar, $regs[2], $initdev);

										$index = $initdev["index"];
									}
								}
					} else //is it code and/or var(s)  BUG avec certains javascripts mal indent�s ou ayant une accolade seule sur une ligne comme ceux emis par HTL_Quickform
						{
						//si la ligne se termine par une accolade ouvrante

						//explode current template line on the curly bracket '{'

						$sstr = explode('{', $this-> {
							$tplvar }
						["content"][$index]);
						// print_r($sstr);print("<br>");
						reset($sstr);

						if (current($sstr) != '') {
							//the template didn't start with a '{',
							//so the first element of the array $sstr is just code
							$this->defBlock[$blockname]["_C:$coderow"] = current($sstr);
							$coderow++;
						}

						while (next($sstr)) {
							//find the position of the end curly bracket '}'
							$pos = strpos(current($sstr), "}");

							if (($pos !== false) && ($pos > 0)) {
								//a curly bracket '}' is found
								//and at least on position 1, to eliminate '{}'

								//note: position 1 taken without '{', because we did explode on '{'

								$strlength = strlen(current($sstr));
								$varname = substr(current($sstr), 0, $pos);

								if (strstr($varname, ' ')) {
									//the varname contains one or more spaces
									//so, it isn't a variable, save as code
									$this->defBlock[$blockname]["_C:$coderow"] = '{' . current($sstr);
									$coderow++;
								} else {
									//save the variable
									$this->defBlock[$blockname]["_V:$varrow"] = $varname;
									$varrow++;

									//is there some code after the varname left?
									if (($pos +1) != $strlength) {
										//yes, save that code
										$this->defBlock[$blockname]["_C:$coderow"] = substr(current($sstr), ($pos +1), ($strlength - ($pos +1)));
										$coderow++;
									}
								}
							} else {
								//no end curly bracket '}' found
								//so, the curly bracket is part of the text. Save as code, with the '{'
								$this->defBlock[$blockname]["_C:$coderow"] = '{' . current($sstr);
								$coderow++;
							}
						}
					}
				} else {
					$this->defBlock[$blockname]["_C:$coderow"] = $this-> {
						$tplvar }
					["content"][$index];
					$coderow++;
				}
			}

			$index++;
		}

		$initdev["varrow"] = $varrow;
		$initdev["coderow"] = $coderow;
		$initdev["index"] = $index;

		return $initdev;
	}

	/**
	 * TemplatePowerParser::version()
	 *
	 * @return
	 * @access public
	 */
	function version() {
		return $this->version;
	}

	/**
	 * TemplatePowerParser::assignInclude()
	 *
	 * @param $iblockname
	 * @param $value
	 * @param $type
	 *
	 * @return
	 *
	 * @access public
	 */
	function assignInclude($iblockname, $value, $type = T_BYFILE) {
		$this->tpl_include["$iblockname"] = Array (
			$value,
			$type
		);
	}
}

class TemplatePower304 extends TemplatePowerParser {
	var $index = Array (); // $index[{blockname}]  = {indexnumber}
	var $content = Array ();
	/**
	 * ajout PP
	 */
	var $doublons = Array (); // PP v 1.41
	var $inconnus = Array (); // PP v 1.41

	/**
	 * end PP
	 */

	var $currentBlock;
	var $showUnAssigned = true;
	var $serialized;
	var $globalvars = Array ();
	var $prepared;

	/**
	 * TemplatePower::TemplatePower()
	 *
	 * @param $tpl_file
	 * @param $type
	 * @return
	 *
	 * @access public
	 */
	function TemplatePower304($tpl_file = '', $type = T_BYFILE) {
		global $CFG;
		TemplatePowerParser :: TemplatePowerParser($tpl_file, $type);

		$this->prepared = false;
		$this->showUnAssigned = false;
		// PP v 1.41 pour tests
		if (@ $CFG->debug_templates)
			$this->showUnAssigned = true;

		$this->serialized = false; //added: 26 April 2002
	}

	/**
	 * TemplatePower::__deSerializeTPL()
	 *
	 * @param $stpl_file
	 * @param $tplvar
	 * @return
	 *
	 * @access private
	 */
	function __deSerializeTPL($stpl_file, $type) {
		if ($type == T_BYFILE) {
			$serializedTPL = @ file($stpl_file) or die($this->__errorAlert('TemplatePower Error: Can\'t open [ ' . $stpl_file . ' ]!'));
		} else {
			$serializedTPL = $stpl_file;
		}

		$serializedStuff = unserialize(join('', $serializedTPL));

		$this->defBlock = $serializedStuff["defBlock"];
		$this->index = $serializedStuff["index"];
		$this->parent = $serializedStuff["parent"];
	}

	/**
	 * TemplatePower::__makeContentRoot()
	 *
	 * @return
	 *
	 * @access private
	 */
	function __makeContentRoot() {
		$this->content[TP_ROOTBLOCK . "_0"][0] = Array (
			TP_ROOTBLOCK
		);
		$this->currentBlock = & $this->content[TP_ROOTBLOCK . "_0"][0];
	}

	/**
	 * TemplatePower::__assign()
	 *
	 * @param $varname
	 * @param $value
	 * @return
	 *
	 * @access private
	 */
	function __assign($varname, $value) {
		if (sizeof($regs = explode('.', $varname)) == 2) //this is faster then preg_match
			{
			$ind_blockname = $regs[0] . '_' . $this->index[$regs[0]];

			$lastitem = sizeof($this->content[$ind_blockname]);

			$lastitem > 1 ? $lastitem-- : $lastitem = 0;

			$block = & $this->content[$ind_blockname][$lastitem];
			$varname = $regs[1];
		} else {
			$block = & $this->currentBlock;
		}

		if (isset ($block["_V:$varname"])) // PP v 1.41 recherche d'assign multiple
			$doublons[] = $varname;

		$block["_V:$varname"] = $value;
		$block["_PP:$varname"] = false; // drapeau utilis�e ou non
	}

	/**
	 * TemplatePower::__assignGlobal()
	 *
	 * @param $varname
	 * @param $value
	 * @return
	 *
	 * @access private
	 */
	function __assignGlobal($varname, $value) {

		if (isset ($this->globalvars[$varname])) // PP v 1.41 recherche d'assign multiple
			$doublons[] = $varname;

		$this->globalvars[$varname] = $value;
	}

	/**
	 * TemplatePower::__outputContent()
	 *
	 * @param $blockname
	 * @return
	 *
	 * @access private
	 */
	function __outputContent($blockname) {
		global $CFG; //PP

		$numrows = sizeof($this->content[$blockname]);

		for ($i = 0; $i < $numrows; $i++) {
			$defblockname = $this->content[$blockname][$i][0];

			for (reset($this->defBlock[$defblockname]); $k = key($this->defBlock[$defblockname]); next($this->defBlock[$defblockname])) {
				if ($k[1] == 'C') {
					print ($this->defBlock[$defblockname][$k]);
				} else
					if ($k[1] == 'V') {
						$defValue = $this->defBlock[$defblockname][$k];

						if (!isset ($this->content[$blockname][$i]["_V:" . $defValue])) {
							if (isset ($this->globalvars[$defValue])) {
								$value = $this->globalvars[$defValue];
							} else {
								$value = $this->lastMinuteFixup($defValue); //PP  traduction auto comme en V2
								if (!isset ($value)) { //null =echec

									if ($this->showUnAssigned) {
										//$value = '{'. $this->defBlock[ $defblockname ][$k] .'}';
										$value = '[[' . $defValue . ']]';
									} else {
										$value = '';
									}
								} else // elle revenue vide mais pas null de la traduction OK
									$this->content[$blockname][$i]["_PP:" . $defValue] = true; // utilis�e une fois au moins;
							}
						} else {
							$value = $this->content[$blockname][$i]["_V:" . $defValue];
							$this->content[$blockname][$i]["_PP:" . $defValue] = true; // utilis�e une fois au moins;
						}

						if (@ $CFG->tpl_montrer_balises)
							print "{" . $defValue . "}";
						else
							print ($value);

					} else
						if ($k[1] == 'B') {
							if (isset ($this->content[$blockname][$i][$k])) {
								$this->__outputContent($this->content[$blockname][$i][$k]);
							}
						}
			}
		}
		//$this->__printVars();
	}

	function __printVars() {
		var_dump($this->defBlock);
		print ("<br>--------------------<br>");
		var_dump($this->content);
	}

	/**
	 * TemplatePower::__listeInutiles PP v 1.41
	 *  similaires a l'emission mais en notant les variables assing�es et pas utilis�es !
	 * @param $blockname
	 * @return
	 *
	 * @access private
	 */
	function __listeInutiles($blockname) {
		$numrows = sizeof($this->content[$blockname]);

		for ($i = 0; $i < $numrows; $i++) {
			$defblockname = $this->content[$blockname][$i][0];

			for (reset($this->defBlock[$defblockname]); $k = key($this->defBlock[$defblockname]); next($this->defBlock[$defblockname])) {
				if ($k[1] == 'C') {
					//print( $this->defBlock[ $defblockname ][$k] );
				} else
					if ($k[1] == 'V') {

						foreach ($this->content[$blockname][$i] as $key => $value) {
							if (substr($key, 0, 4) == '_PP:')
								if (!$value) {
									$this->inconnus[] = "$blockname:$i " . substr($key, 4);
									// evite de le revoir plusieurs fois
									$this->content[$blockname][$i][$key] = true;
								}
						}

					} else
						if ($k[1] == 'B') {
							if (isset ($this->content[$blockname][$i][$k])) {
								$this->__listeInutiles($this->content[$blockname][$i][$k]);
							}
						}
			}
		}
		//$this->__printVars();
	}

	/**********
	    public members
	          ***********/

	/**
	 * TemplatePower::serializedBase()
	 *
	 * @return
	 *
	 * @access public
	 */
	function serializedBase() {
		$this->serialized = true;
		$this->__deSerializeTPL($this->tpl_base[0], $this->tpl_base[1]);
	}

	/**
	 * TemplatePower::showUnAssigned()
	 *
	 * @param $state
	 * @return
	 *
	 * @access public
	 */
	function showUnAssigned($state = true) {
		$this->showUnAssigned = $state;
	}

	/**
	 * TemplatePower::prepare()
	 *
	 * @return
	 *
	 * @access public
	 */
	function prepare() {

		if (!$this->serialized) {
			TemplatePowerParser :: __prepare();
		}

		$this->prepared = true;

		$this->index[TP_ROOTBLOCK] = 0;
		$this->__makeContentRoot();

	}

	/**
	 * TemplatePower::newBlock()
	 *
	 * @param $blockname
	 * @return
	 *
	 * @access public
	 */
	function newBlock($blockname) {
		$parent = & $this->content[$this->parent[$blockname] . '_' . $this->index[$this->parent[$blockname]]];

		$lastitem = sizeof($parent);
		$lastitem > 1 ? $lastitem-- : $lastitem = 0;

		$ind_blockname = $blockname . '_' . $this->index[$blockname];

		if (!isset ($parent[$lastitem]["_B:$blockname"])) {
			//ok, there is no block found in the parentblock with the name of {$blockname}

			//so, increase the index counter and create a new {$blockname} block
			$this->index[$blockname] += 1;

			$ind_blockname = $blockname . '_' . $this->index[$blockname];

			if (!isset ($this->content[$ind_blockname])) {
				$this->content[$ind_blockname] = Array ();
			}

			//tell the parent where his (possible) children are located
			$parent[$lastitem]["_B:$blockname"] = $ind_blockname;
		}

		//now, make a copy of the block defenition
		$blocksize = sizeof($this->content[$ind_blockname]);

		$this->content[$ind_blockname][$blocksize] = Array (
			$blockname
		);

		//link the current block to the block we just created
		$this->currentBlock = & $this->content[$ind_blockname][$blocksize];
	}

	/**
	 * TemplatePower::assignGlobal()
	 *
	 * @param $varname
	 * @param $value
	 * @param stripSlashes ajout PP toujours suaf si balise commence vraiment par  par js_ !!!
	 * @return
	 *
	 * @access public
	 */
	function assignGlobal($varname, $value) {
		if (is_array($varname)) {
			foreach ($varname as $var => $value) {
				$this->__assignGlobal($var, $value);
			}
		} else {
			$this->__assignGlobal($varname, $value);
		}
	}

	/**
	 * TemplatePower::assign()
	 *
	 * @param $varname
	 * @param $value
	 * @param stripSlashes ajout PP toujours sauf si balise commence par js_ !!!
	 * @return
	 *
	 * @access public
	 */
	function assign($varname, $value = '') {
		if (is_array($varname)) {
			foreach ($varname as $var => $value) {
				$this->__assign($var, $value);
			}
		} else {
			$this->__assign($varname, $value);
		}
	}

	/**
	 * TemplatePower::gotoBlock()
	 *
	 * @param $blockname
	 * @return
	 *
	 * @access public
	 */
	function gotoBlock($blockname) {
		if (isset ($this->defBlock[$blockname])) {
			$ind_blockname = $blockname . '_' . $this->index[$blockname];

			//get lastitem indexnumber
			$lastitem = sizeof($this->content[$ind_blockname]);

			$lastitem > 1 ? $lastitem-- : $lastitem = 0;

			//link the current block
			$this->currentBlock = & $this->content[$ind_blockname][$lastitem];
		}
	}

	/**
	 * TemplatePower::getVarValue()
	 *
	 * @param $varname
	 * @return
	 *
	 * @access public
	 */
	function getVarValue($varname) {
		if (sizeof($regs = explode('.', $varname)) == 2) //this is faster then preg_match
			{
			$ind_blockname = $regs[0] . '_' . $this->index[$regs[0]];

			$lastitem = sizeof($this->content[$ind_blockname]);

			$lastitem > 1 ? $lastitem-- : $lastitem = 0;

			$block = & $this->content[$ind_blockname][$lastitem];
			$varname = $regs[1];
		} else {
			$block = & $this->currentBlock;
		}

		return $block["_V:$varname"];
	}

	/**
	     * TemplatePower::getOutputContent()
	     *
	     * @return
	     *
	     * @access public
	     */
	function getOutputContent() {
		ob_start();

		$this->printToScreen();

		$content = ob_get_contents();

		ob_end_clean();

		return $content;
	}

	/**
	 * TemplatePower::printToScreen()
	 *
	 * @return
	 *
	 * @access public
	 */
	function printToScreen() {

		if ($this->prepared) {
			$this->__outputContent(TP_ROOTBLOCK . '_0');
		} else {
			$this->__errorAlert('TemplatePower Error: Template isn\'t prepared!');
		}
		//end PP
	}

}

/**
 * la classe initiale (quoique modifi�e pour un meilleur debug a �t renomm�e
 * rn TemplatePower304  . dans cette classe on ajoute toutes les infos
 * sp�cifiques a la plateforme C2I. en proc�dant ainsi il n'a pas �t� n�cessaire
 * de modifier les new TemplatePower(...) dans tous les scripts ....'
 */
class TemplatePower extends TemplatePower304 {
	var $chemin;
	var $chemin_images;
	var $chemin_commun;
	var $chemin_theme;
	var $extra_js = array (); // voir add_javascript() de weblib  et this->printtoscreen
	var $extra_css = array (); // voir add_css() de weblib
	var $onload = "";

	/**
	*  options pass�es � la m�thode prepare();
	*   reversv� V2
	*/
	var $options = array ();

	function TemplatePower($tpl_file = '', $type = T_BYFILE) {
		global $CFG;
		TemplatePower304 :: TemplatePower304($tpl_file, $type);
	}

	function prepare($chemin = false) {

		global $CFG;

		TemplatePower304 :: prepare();

		//PP v 1.41 comme ca on en parle plus !
		//rev 970 on passe en absolu et on ignore $chemin (utile pour lightwindow)
		// casse l'installation
		if ($chemin) {
			$this->chemin = $chemin;
			if (@ !$CFG->theme)
				$CFG->theme = "v14";
			$this->chemin_theme = "$chemin/themes/{$CFG->theme}";
			$this->chemin_images = "$this->chemin_theme/images";
			$this->chemin_commun = "$chemin/commun";

			$this->assignGlobal("chemin", $this->chemin);
			$this->assignGlobal("chemin_images", $this->chemin_images);
			$this->assignGlobal("chemin_commun", $this->chemin_commun);
			$this->assignGlobal("chemin_theme", $this->chemin_theme);
			@ $this->assignGlobal("encodage", $CFG->encodage);
			@ $this->assignGlobal("bodydir", $CFG->bodydir);
            @ $this->assignGlobal("lang", $CFG->langue);


		}
		//pas de warning si la page n'a pas besoin de garder la session ou n'en a pas encore
		@ $this->assignGlobal('lp', $CFG->largeur_popups);
		@ $this->assignGlobal('hp', $CFG->hauteur_popups);

		// PP 07/02/2009 retouche � la taille des minipopups
		@ $this->assignGlobal('lpm', $CFG->largeur_minipopups);
		@ $this->assignGlobal('hpm', $CFG->hauteur_minipopups);

		// pour les templates version 2
		@ $this->assignGlobal("global_jsvoid", "javascript:void(0)");
		@ $this->assignGlobal("global_popupsrwh", "scrollbars=yes,resizable=yes,width=" . $CFG->largeur_popups . ",height=" . $CFG->hauteur_popups);
		@ $this->assignGlobal("global_minipopupsrwh", "scrollbars=yes,resizable=yes,width=" . $CFG->largeur_minipopups . ",height=" . $CFG->hauteur_minipopups);

		//rev 970
		@ $this->assignGlobal("global_windowclose", "javascript:window.close();");

		@ form_session($this); // pour ne plus oublier
		$this->gotoBlock("_ROOT");

	}

	// ajouts PP en attendant usage syst�matique de C2ITemplate

	/**
	 * TemplatePower::lastMinuteFixup($varname)  PP
	 *
	 * @param $varname
	 * @return null
	 * en V2 on le fait dans une sousclasse pas ici
	 * @access public

	 */
	function lastMinuteFixUp($varname) {
		global $CFG, $USER;
		switch ($varname) {
			case "CFG" :
				return print_r($CFG, true);
				break;
				//debug est dans un commentaire dans le source du C2Ifooter
			case "SESSION" :
				return print_r($_SESSION, true);
				break;
			case "USER" :
				return print_r($USER, true);
				break;
			case "POST" :
				return print_r($_POST, true);
				break;
			case "GET" :
				return print_r($_GET, true);
				break;
			case "FILES" :
				return print_r($_FILES, true);
				break;
			case "REQUEST" :
				return print_r($_REQUEST, true);
				break;
			case "ENV" :
				return print_r($_ENV, true);
				break;
			case "SERVER" :
				return print_r($_SERVER, true);
				break;
			case "PU" :
				if (!empty ($_SERVER['HTTP_REFERER']))
					return print_r(parse_url($_SERVER['HTTP_REFERER']), true);
				else
					return "pas de HTTP_REFERER";
				break;
			case "session_id" :
				return session_id();
				break;
			case "session_nom" :
				return $CFG->session_nom;
				break;
			default :
				break;
		}

		// balises sp�ciales avec un deux points dans le nom
		$table = explode(":", $varname);
		if (count($table) >= 2) {
			switch ($table[0]) {
				case 'CFG' :
					return @ $CFG-> $table[1];
					break; //variable de conf (ex CFG:version)
				case 'USER' : //variable utilisateur ex {USER:fullname}

					switch ($table[1]) {
						case 'fullname' :
							return whoisconnected();
							break;
					}
					break; //TODO
				case 'bulle' :
					return get_bulle_aide($varname);
					break; //bulle ex {bulle:astuce:info_tri}
				case 'bouton' :
					return get_bouton_standard($varname);
					break;
			}

		}
		if (!isset($CFG->tpl_pas_trad_auto) || !$CFG->tpl_pas_trad_auto)
			return traduction($varname);
		else
			return null;
	}

	function printToScreen() {

		//PP 1.41
		global $CFG;
		if (@ $CFG->dump_vars) {
			$this->gotoBlock("_ROOT");
			@ $this->newBlock("DUMP"); //pas grave si oubli� sur la page
		}
 
		// PP 1.5
		// ne sont pas en config, mais mis si besoin par les scripts
		//donc pas de notice PHP (sauf infobullee ou overlib)
		// d'abord prototype qui est requis par les deux autres !
		if (@ $CFG->utiliser_scriptacoulous_js || @ $CFG->utiliser_validation_js || @ $CFG->enregistre_reponses_ajax || @ $CFG->utiliser_fabtabulous_js)
			$CFG->utiliser_prototype_js = 1;
/*
		if (@ $CFG->utiliser_lightwindow_js) {
			$CFG->utiliser_prototype_js = 1;
			$CFG->utiliser_scriptacoulous_js = 1;
		}
*/
		if (@ $CFG->utiliser_prototype_js)
			$this->extra_js[] = $this->chemin_commun .
			"/js/prototype.js";

		if (@ $CFG->utiliser_scriptacoulous_js)
			$this->extra_js[] = $this->chemin_commun .
			"/js/scriptaculous/scriptaculous.js";

		if (@ $CFG->utiliser_fabtabulous_js) {
			$this->extra_js[] = $this->chemin_commun .
			"/js/fabtabulous.js";
             // rev 981 ajout� automatiquement peremttra changement en jQuery
           // $this->newBlock("fab_tabs");   INUTILE fait automatiquement par fabtabulous.js !!!
        }

		if (@ $CFG->utiliser_validation_js) {
			$this->extra_js[] = $this->chemin_commun .
			"/js/validation.js";
            $this->newBlock ("validation");   // rev 981 ajout� automatiquement peremttra changement en jQuery
        }

		//pas vraiment dans la table c2iconfig ...
		if (@ $CFG->enregistre_reponses_ajax)
			$this->extra_js[] = $this->chemin_commun .
			"/js/enregistre.js";

		if (@ $CFG->utiliser_inlinemod_js) {
			// $this->extra_js[]=$this->chemin_commun."/js/inlinemod.js";
			$scripts = array (
				"utils.js",
				"/inlinemod.class.texte.js",
				"/inlinemod.class.texteNV.js",
				"/inlinemod.class.nombre.js",
				"/inlinemod.class.texteMulti.js",
				"/inlinemod.class.texteMultiNV.js",
				"/inlinemod.class.email.js",
				"/inlinemod.class.entier.js",
				"/inlinemod.class.url.js",

				"inlinemod.js"
			);
			foreach ($scripts as $script)
				$this->extra_js[] = $this->chemin_commun .
				"/js/inlinemod.v2/$script";

			$this->newBlock("inline_mod");
		}
		if (@ $CFG->utiliser_tables_sortables_js) {
			$this->extra_js[] = $this->chemin_commun . "/js/table_sorter.js";
			$this->newBlock("table_sorter");
			//$this->onload .="; initalizeTableSort();";
		}



			if (@$CFG->utiliser_infobulle_js) {
				$this->extra_js[] = $this->chemin_commun . "/js/infobulle.js";
				$this->newBlock("info_bulle");
				//plante le naviagteur !
				//$this->onload .="; InitBulle('#000000','#FBFFD9','red',1);";
			}

		if (@ $CFG->utiliser_js_calendar) {
			add_css($this, $this->chemin_commun . "/js/jscalendar/calendar-" . $CFG->theme_js_calendar . ".css");
			add_javascript($this, $this->chemin_commun . "/js/jscalendar/calendar.js");
			add_javascript($this, $this->chemin_commun . "/js/jscalendar/lang/calendar-{$CFG->langue}.js");
			add_javascript($this, $this->chemin_commun . "/js/jscalendar/calendar-setup.js");
		}

		// rev 1013 support des navigateur mobiles
		if (detectMobileDevice()){
		    add_css($this, $CFG->chemin .'/themes/mobiles/mobile.css');
		}
		

		if (count($this->extra_css) > 0) {
			$this->newBlock("EXTRA_CSS"); // a mettre dans les pages principale et popup
			foreach ($this->extra_css as $path) {
				$this->newBlock("ligne_css");
				$this->assign("path", $path);
			}
		}

		if (count($this->extra_js) > 0) {
			$this->newBlock("EXTRA_JS"); // a mettre dans les pages principale et popup
			foreach ($this->extra_js as $path) {
				$this->newBlock("ligne_js");
				$this->assign("path", $path);
			}
		}

		$this->assignGlobal("global_onload", $this->onload);

		if (isset ($this->options['multip']))
			print_multipagination($this);

        if (!empty($CFG->W3C_validateurs)) {
            $this->newBlock("validators");
            $this->assignGlobal("me", urlencode(qualified_me()));
        }

		if (!empty($CFG->utiliser_form_actions)) {
			$this->newBlock('form_actions');
			print_form_actions($this,'form_actions');
		}

		//end PP 1.5
		if ($this->prepared) {
			$this->__outputContent(TP_ROOTBLOCK . '_0');
		} else {
			$this->__errorAlert('TemplatePower Error: Template isn\'t prepared!');
		}
		// PP v 1.41
		if ($this->showUnAssigned && count($this->doublons)) {
			print "<b>Doublons </b></br>";
			//print_r($this->doublons);
			foreach ($this->doublons as $k => $v)
				print $v . "<br/>";
		}
		// PP v 1.41
		if ($this->showUnAssigned) {
			$this->inconnus = array ();
			$this->__listeInutiles(TP_ROOTBLOCK . '_0');
			if (count($this->inconnus)) {
				print "<b>Inconnus </b><br/>";
				// print_r($this->inconnus);
				foreach ($this->inconnus as $k => $v)
					print $v . "<br/>";
			}
		} //end PP
	}

	/**
	 * REV 980 ne pas virer les slashes par d�faut, c'est d�ja fait par lib_bd et si on le fait deux fois
     * ca casse les formules Latex inline !!!!!
	 * rempli un template avec les champs d'un objet (extrait de la bd souvent)
	 * les variables du template DOIVENT avoir le meme nom que les proprietes
	 * de l'objet, donc probablement des colonnes de la BD
	 * @param ligne : l'objet contenant les valeurs
	 *
     *
	 */
	function assignObjet($ligne, $stripSlashes = false) {
		//conversion objet en tableau associatif
		$a = get_object_vars($ligne);
		foreach ($a as $key => $value) {
			if (!is_array($value) && !is_object($value)) { //rev 916 certains attibuts sont des tableaux (stats a ne pas afficher)
				if ($stripSlashes)
					$this->assign($key, stripslashes(nl2br(str_replace('"', "&quot;", $value))), false); //pas le peine de le faire 2 fois ...
				else
					$this->assign($key, nl2br(str_replace('"', "&quot;", $value)));
			} else
				$this->assign($key, print_r($value, true));
		}
	}

	/**
	 * cree un bloc avec un numéro unique
	 * était utile pour les images animées avec MM_swapimage
	 * depreci� en V2
	 * remplace les deux appels
	 *   $tpl->newBlock("question");
	 *   $tpl->assign("n",$compteur_ligne);
	 */
	function newBlockNum($blockname, $compteur, $varname = "n") {
		global $CFG;
		$this->newBlock($blockname);
		$this->assign($varname, $compteur);
	}

	/**
	 * cree un bloc si la condition est vraie
	 * remplace le classique
	 *   if (xxxxx)
	 $tpl->newBlock("zzzz");

	 * @return la condition pour suite des tests
	 */
	function newBlockSi($blockname, $condition) {
		if ($condition)
			$this->newBlock($blockname);
		return $condition;
	}

	/**
	 * cree un bloc selon  la condition
	 * remplace le classique
	 *   if (xxxxx)
	 $tpl->newBlock("ooooo");
	 else
	 $tpl->newBlock("nnnn");

	 * @return la condition pour suite des tests
	 */
	function newBlockSiSinon($blocknameOui, $blocknameNon, $condition) {
		if ($condition)
			$this->newBlock($blocknameOui);
		else
			$this->newBlock($blocknameNon);
		return $condition;
	}

	/**
	 * alternance des couleurs
	 @param $num : num�ro de ligne
	 @param string $var: nom du marqueur, d�faut paire_impaire
	 @TODO  remplacer par une classe "paire" "impaire" pour un th�me CSS
	 */
	function setCouleurLigne($num, $var = "paire_impaire") {

		$class = ($num % 2 == 0) ? "paire" : "impaire";
		$this->assign($var, $class);
	}

	/**
	 * Traduction manuelle
	 * L'appel � cette fonction est INUTILE si la variable de template a le
	 * m�me nom que la clé dans les fichiers de langue.
	 * @param varname :string la variable du template (entre {})
	 * @param  cle      :string la cl� dans le fichier de langue
	 */
	function traduit($varname, $cle, $ucfirst = 1) {
		/*
		if ($cle && strstr($cle,".")) {
		    //gere le cas d'un nom du type _ROOT.xxx
		    // la cl� de la traduction est xxx  FAUX
		    $elements=explode('.',$varname);
		    $dernier=array_pop($elements);
		    $this->assign($varname,traduction($dernier));
		*/
		$this->assign($varname, traduction($cle, $ucfirst));
	}

	/**
	 * Traduction manuelle globale
	 * L'appel a cette fonction est INUTILE si la variable de template a le
	 * meme nom que la cl� dans les fichiers de langue.
	 *
	 * @param  varname :string la variable du template (entre {})
	 * @param  cle      :string la cl� dans le fichier de langue
	 * @param  majuscule:boolean conversion en majuscule
	 */
	function traduitGlobal($varname, $cle, $ucfirst = 1) {
		$this->assignGlobal($varname, traduction($cle, $ucfirst));
	}

	/**
	*
	* ajoute l'attribut checked a DEUX cases a  cocher.
	* normalement dans un groupe
	* @param boolean $valTest : valeur boolenne vraie ou fausse
	* @param string  $var1 : la variable de template dans le cas oui
	* @param string  $var2 : la variable de template dans le cas non (optionnelle)
    * rev 1047  comptaible w3c  checked="checked"

	*/
	function setChecked($valTest, $var1, $var2 = false) {
		if ($valTest) {
			$this->assign($var1, " checked=\"checked\" ");
			if ($var2)
				$this->assign($var2, "");
		} else {
			$this->assign($var1, "");
			if ($var2)
				$this->assign($var2, " checked=\"checked\" ");
		}
	}

	/**
	*
	* ajoute l'attribut selected a une option de select.
	* @param boolean $valTest : valeur boolénne vraie ou fausse
	* @param string  $var : la variable de template dans le cas oui
	*                         défaut ="selected"

	*/
	function setSelected($valTest, $var = "selected") {
		if ($valTest)
			$this->assign($var, " selected=\"selected\" ");
		else
			$this->assign($var, "");

	}

	/**
	* @param résultat du test
	* @param nom de la variable du template
	* @param valeur à assigner dans le cas "oui"
	* @param valeur à assigner dans le cas "non"
	*/

	function setConditionalValue($valTest, $var, $valeurOui, $valeurNon) {
		if ($valTest)
			$this->assign($var, $valeurOui);
		else
			$this->assign($var, $valeurNon);
	}

	/**
	*
	* ajoute à l'URL un appel à p_session pour ne plus oublier !
	* remplace les anciens $tpl->assign("url_n",p_session("ajout.php",1));
	* ou $tpl->assign("url_consult",p_session("fiche.php?idq=".$ligne->id."&ide=".$ligne->id_etab,1)
	* @param $var la valeur du marqueur
	* @param $url l'URL a affecter
	* @param $js si vrai, c'est dans un javascript
	*/

	function assignURL($var, $url, $js = 0) {
		$url = p_session($url, $js);
		$this->assign($var, $url);
	}

	function assignGlobalURL($var, $url, $js = 1) {
		$url = p_session($url, $js);
		$this->assignGlobal($var, $url);
	}

}

class SubTemplatePower extends TemplatePower {

	function SubtemplatePower($tpl_file = '', $type = T_BYFILE) {
		global $CFG;
		TemplatePower :: TemplatePower($tpl_file, $type);
		$this->showUnAssigned = false; //jamais
	}

	/**
	 * TemplatePower::printToScreen()
	 *
	 * @return
	 *
	 * @access public
	 */
	function printToScreen() {

		if ($this->prepared) {
			$this->__outputContent(TP_ROOTBLOCK . '_0');
		} else {
			$this->__errorAlert('TemplatePower Error: Template isn\'t prepared!');
		}
		//end PP
	}

}

class C2ITemplate extends TemplatePower {



	function prepare($chemin = "../", $options = array ()) {
		global $CFG;

		if (isset ($options['liste'])) { // options globales pour une fiche de type liste
			$options['multip'] = true;
		//	$options['icones_action'] = true;  deprecié v 2.0
			$options['icones_tri'] = true;
			
			// actions aussi possibles dans certains popup
			$CFG->utiliser_form_actions=1; // rev 981
		}
		$this->options = $options; //m�morise les
		//options a traiter AVANT prepare (assignInclude)

		if (isset ($options['multip']))
			assignIncludeMultipagination($this);
/***  DEPRECIE V 2.0 on passe par des scripts action_js
		if (isset ($options['icones_action'])) {
				$this->assignInclude("icones_action_liste", $CFG->chemin_templates ."/icones_action_liste2.tpl");
		}
****/
		if (isset ($options['corps']))
			$this->assignInclude("corps", $options['corps']);
		else
			if (isset ($options['corps_byvar'])) //bug avec le footer qui est emis mais n'apparait pas ?
				$this->assignInclude("corps", $options['corps_byvar'], T_BYVAR);

		//en dernier
		TemplatePower :: prepare($chemin);
		//maintenant on peut commencer les Assign ...
		logo($this);
	}

	/**
	 * affiche les boutons de fermeture ou de retour pour un popup
	 * @param url_retour : ou retourner . Si vide -> fermer
	 * @param ou texte du bouton "retour_fiche" ou "retour_liste" ou ....(une cl� de traduction)
	 * @uses $CFG->boutons_retour_fermer_haut pour d�cider si on en met aussi en haut � droite
	 */
	function print_boutons_fermeture($url_retour = "", $ou = "retour_fiche") {
		global $CFG;
		$this->gotoBlock("_ROOT"); //pour ne plus oublier !
		if ($CFG->isPopup)
			if ($url_retour) {
				if ($CFG->boutons_retour_fermer_haut) {
					$this->newBlock("retour"); //image anim�e en haut
					//je n'ajoute pas le p_session, via assignURL c'est volonataire car l'URL
					//se termine par un #tab pour revenir � un onglet !'
					$this->assign("url_retour", $url_retour);

				}
				$this->newBlock("retour_bas");
				print_bouton_retour($this, $url_retour, $ou, "bouton_retour");

			} else {
				if (isset($CFG->boutons_retour_fermer_haut) && $CFG->boutons_retour_fermer_haut)
					$this->newBlock("fermer");
				$this->newblock("retour_bas");
				print_bouton_fermer($this, "bouton_retour");
			}
	}

}

class C2IPrincipale extends C2ITemplate {

	/**
	* constructeur
	* ajoute automatiquement les quatre blocs de base (bandeau, barre_navigation, menu_retour et C2Ifooter)
	* le bloc C2Iheader est  ajout� par la classe C2ITemplate
	* @param $template : une page alternative a principale.tpl
	*/
	function C2IPrincipale($template = "", $type = T_BYFILE) {
		global $CFG;
		if (!$template) {
            $template = $CFG->chemin_templates . "/principale.tpl";
			$type = T_BYFILE;
		}
		TemplatePower :: TemplatePower($template, $type);
		$this->assignInclude("C2Iheader", $CFG->chemin_templates . "/C2Iheader.tpl");
		$this->assignInclude("C2Ilogo", $CFG->chemin_templates . "/C2Ilogo.tpl");
		$this->assignInclude("C2Ifooter", $CFG->chemin_templates . "/C2Ifooter.tpl");
		$CFG->isPopup = false; //rev 977
	}

}

class C2IPopup extends C2ITemplate {

	/**
	* constructeur
	* ajoute automatiquement les quatre blocs de base (bandeau, barre_navigation, menu_retour et C2Ifooter)
	* le bloc C2Iheader est  ajout� par la classe C2ITemplate
	* @param $template : une page alternative a principale.tpl
	*/
	function C2IPopup($template = "", $type = T_BYFILE) {
		global $CFG;
		if (!$template) {
            $template = $CFG->chemin_templates . "/popup.tpl";
			$type = T_BYFILE;
		}
		$CFG->utiliser_form_actions=0; // rev 981
		TemplatePower :: TemplatePower($template, $type);
		$this->assignInclude("C2Iheader", $CFG->chemin_templates . "/C2Iheader.tpl");
		$this->assignInclude("C2Ilogo", $CFG->chemin_templates . "/C2Ilogo.tpl");

		$this->assignInclude("C2Ifooter", $CFG->chemin_templates . "/C2IfooterPopup.tpl");
		$CFG->isPopup = true;
	}

	function prepare($chemin = "../", $options = array ()) {
		C2ITemplate :: prepare($chemin, $options);
		$this->assignGlobal("url_accueil", "#"); // pas de retour accueil
	}

}

class C2IMiniPopup extends C2ITemplate {

	function C2IMiniPopup($template = "", $type = T_BYFILE) {
		global $CFG;
		if (!$template) {
            $template = $CFG->chemin_templates . "/mini_popup.tpl";
			$type = T_BYFILE;
		}
		$CFG->utiliser_form_actions=0; // rev 981
		TemplatePower :: TemplatePower($template, $type);
		$this->assignInclude("C2Iheader", $CFG->chemin_templates . "/C2Iheader.tpl");
		$this->assignInclude("C2Ilogo", $CFG->chemin_templates . "/C2Iminilogo.tpl");
		$this->assignInclude("C2Ifooter", $CFG->chemin_templates . "/C2IfooterMiniPopup.tpl");
		$CFG->isPopup = true;
	}

}
