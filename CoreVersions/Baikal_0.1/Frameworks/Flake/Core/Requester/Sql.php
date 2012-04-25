<?php
/***************************************************************
*  Copyright notice
*
*  (c) 2012 Jérôme Schneider <mail@jeromeschneider.fr>
*  All rights reserved
*
*  http://baikal.codr.fr
*
*  This script is part of the Baïkal Server project. The Baïkal
*  Server project is free software; you can redistribute it
*  and/or modify it under the terms of the GNU General Public
*  License as published by the Free Software Foundation; either
*  version 2 of the License, or (at your option) any later version.
*
*  The GNU General Public License can be found at
*  http://www.gnu.org/copyleft/gpl.html.
*
*  This script is distributed in the hope that it will be useful,
*  but WITHOUT ANY WARRANTY; without even the implied warranty of
*  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
*  GNU General Public License for more details.
*
*  This copyright notice MUST APPEAR in all copies of the script!
***************************************************************/

namespace Flake\Core\Requester;

class Sql extends \Flake\Core\FLObject {

	protected $sDataTable = "";
	protected $aClauses = array();
	protected $sModelClass = "";
	protected $sOrderField = "";
	protected $sOrderDirection = "ASC";
	protected $iLimitStart = FALSE;
	protected $iLimitNumber = FALSE;
	protected $bHasBeenExecuted = FALSE;

	public function __construct($sModelClass) {
		$this->sModelClass = $sModelClass;
	}

	public function setDataTable($sDataTable) {
		$this->sDataTable = $sDataTable;
		return $this;
	}

	public function addClauseEquals($sField, $sValue) {
		$sWrap = "{field}='{value}'";
		$this->addClauseWrapped($sField, $sValue, $sWrap);
		return $this;
	}

	public function addClauseNotEquals($sField, $sValue) {
		$sWrap = "{field}!='{value}'";
		$this->addClauseWrapped($sField, $sValue, $sWrap);
		return $this;
	}

	public function addClauseLike($sField, $sValue) {
		$sWrap = "{field} LIKE '%{value}%'";
		$this->addClauseWrapped($sField, $sValue, $sWrap);
		return $this;
	}

	public function addClauseLikeBeginning($sField, $sValue) {
		$sWrap = "{field} LIKE '{value}%'";
		$this->addClauseWrapped($sField, $sValue, $sWrap);
		return $this;
	}

	public function addClauseLikeEnd($sField, $sValue) {
		$sWrap = "{field} LIKE '%{value}'";
		$this->addClauseWrapped($sField, $sValue, $sWrap);
		return $this;
	}

	public function addClauseNotLike($sField, $sValue) {
		$sWrap = "{field} NOT LIKE '%{value}%'";
		$this->addClauseWrapped($sField, $sValue, $sWrap);
		return $this;
	}

	public function addClauseNotLikeBeginning($sField, $sValue) {
		$sWrap = "{field} NOT LIKE '{value}%'";
		$this->addClauseWrapped($sField, $sValue, $sWrap);
		return $this;
	}

	public function addClauseNotLikeEnd($sField, $sValue) {
		$sWrap = "{field} NOT LIKE '%{value}'";
		$this->addClauseWrapped($sField, $sValue, $sWrap);
		return $this;
	}

	public function addClauseIn($sField, $sValue) {
		$sWrap = "{field} IN ({value})";
		$this->addClauseWrapped($sField, $sValue, $sWrap);
		return $this;
	}

	public function addClauseNotIn($sField, $sValue) {
		$sWrap = "{field} NOT IN ({value})";
		$this->addClauseWrapped($sField, $sValue, $sWrap);
		return $this;
	}
	
	public function orderBy($sOrderField, $sOrderDirection = "ASC") {
		$this->sOrderField = $sOrderField;
		$this->sOrderDirection = $sOrderDirection;
		return $this;
	}
	
	public function limit($iStart, $iNumber = FALSE) {
		if($iNumber !== FALSE) {
			return $this->setLimitStart($iStart)->setLimitNumber($iLimitNumber);
		}
		
		return $this->setLimitStart($iStart);
	}

	public function setLimitStart($iLimitStart) {
		$this->iLimitStart = $iLimitStart;
		return $this;
	}

	public function setLimitNumber($iLimitNumber) {
		$this->iLimitNumber = $iLimitNumber;
		return $this;
	}

	protected function addClauseWrapped($sField, $sValue, $sWrap) {
		$sValue = $this->escapeSqlValue($sValue);
		$sClause = str_replace(
			array(
				"{field}",
				"{value}",
			),
			array(
				$sField,
				$sValue
			),
			$sWrap
		);

		$this->addClauseLiteral($sClause);
		return $this;
	}

	public function addClauseLiteral($sClause) {
		$this->aClauses[] = $sClause;
		return $this;
	}

	protected function escapeSqlValue($sValue) {
		return $GLOBALS["DB"]->quote(
			$sValue,
			$this->sDataTable
		);
	}

	protected function &reify($aData) {
		$sTemp = $this->sModelClass;
		return new $sTemp($aData[$sTemp::getPrimaryKey()]);
	}

	public function hasBeenExecuted() {
		return $this->bHasBeenExecuted;
	}

	public function getQuery() {
		if(empty($this->aClauses)) {
			$sWhere = "1=1";
		} else {
			$sWhere = implode(" AND ", $this->aClauses);
		}

		if(trim($this->sOrderField) !== "") {
			$sOrderBy = $this->sOrderField . " " . $this->sOrderDirection;
		}

		$sLimit = "";

		if($this->iLimitStart !== FALSE) {
			if($this->iLimitNumber !== FALSE) {
				$sLimit = $this->iLimitStart . ", " . $this->iLimitNumber;
			} else {
				$sLimit = $this->iLimitStart;
			}
		} elseif($this->iLimitNumber !== FALSE) {
			$sLimit = "0, " . $this->iLimitNumber;
		}

		return $GLOBALS["DB"]->SELECTquery(
			"*",
			$this->sDataTable,
			$sWhere,
			"",
			$sOrderBy,
			$sLimit
		);
	}

	public function execute() {
		$oCollection = new \Flake\Core\CollectionTyped($this->sModelClass);
		$sSql = $this->getQuery();

		$rSql = $GLOBALS["DB"]->query($sSql);
		while(($aRs = $rSql->fetch()) !== FALSE) {
			$oCollection->push(
				$this->reify($aRs)
			);
		}

		$this->bHasBeenExecuted = TRUE;

		return $oCollection;
	}
}