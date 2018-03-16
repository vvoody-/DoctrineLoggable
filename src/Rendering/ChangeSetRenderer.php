<?php

namespace Adt\DoctrineLoggable\Rendering;

use Adt\DoctrineLoggable\ChangeSet\ChangeSet;
use Adt\DoctrineLoggable\ChangeSet\Id;
use Adt\DoctrineLoggable\ChangeSet\Scalar;
use Adt\DoctrineLoggable\ChangeSet\ToMany;
use Adt\DoctrineLoggable\ChangeSet\ToOne;
use Nette\Utils\Strings;

class ChangeSetRenderer
{
	
	/** @var [] */
	protected $changeSetsRendered = [];

	public function render(ChangeSet $changeSet)
	{
		$this->renderChangeSet($changeSet);
		$this->changesetRendered = [];
	}

	protected function renderChangeSet(ChangeSet $changeSet)
	{
		$changeSetId = spl_object_hash($changeSet);
		if (isset($this->changeSetsRendered[$changeSetId])) {
			$this->renderRecursion($changeSet);
		    return;
		} else {
			$this->changeSetsRendered[$changeSetId] = $changeSet;
		}
		
		if (count($changeSet->getChangedProperties())) {
		    
			echo "<table>";
			echo "<thead><tr><th>položka</th><th>stará hodnota</th><th>nová hodnota</th><th>změny</th></tr></thead>";
		
			foreach ($changeSet->getChangedProperties() as $propertyChangeSet) {
				if (!$propertyChangeSet->isChanged()) {
					continue;
				}
				echo "<tr>";
				if ($propertyChangeSet instanceof Scalar) {
					$this->renderScalar($propertyChangeSet);
				} elseif ($propertyChangeSet instanceof ToOne) {
					$this->renderToOne($propertyChangeSet);
				} elseif ($propertyChangeSet instanceof ToMany) {
					$this->renderToMany($propertyChangeSet);
				}
				echo "</tr>";
			}
			
			echo "</table>";
		}
		
	}

	protected function renderRecursion(ChangeSet $changeSet)
	{
		echo "recursion(";
		$this->renderIdentification($changeSet->getIdentification());
		echo ")";
	}
	
	protected function renderIdentification(Id $identification = NULL)
	{
		if ($identification === NULL) {
			echo "NULL";
			return;
		}
		$class = $identification->getClass();
		$class = explode('\\', $class);
		$class = $class[count($class)-1];
		if ($identification->getIdentification()) {
			$parts = [];
			foreach ($identification->getIdentification() as $key => $value) {
				$parts[] = "$key: $value";
			}
			$parts = implode(', ', $parts);
		} else {
			$parts = 'no identification data';
		}
		if ($parts) {
		    echo $parts;
		} else {
			echo "<span title=\"{$parts}\">{$class} ({$identification->getId()})</span>";
		}
	}

	protected function renderScalar(Scalar $scalar)
	{
		echo "<td>{$scalar->getName()}</td>";
		echo "<td>";
		$this->renderValue($scalar->getOld()); 
		echo "</td>"; 
		echo "<td>";
		$this->renderValue($scalar->getNew());
		echo "</td>"; 
		echo "<td>";
		echo "</td>"; 
	}

	public function renderValue($value)
	{
		if (is_scalar($value)) {
			echo "<span title=\"{$value}\">" . Strings::truncate($value, 200) . "</span>";
		} elseif ($value instanceof \DateTime) {
			echo $value->format('j.n.Y H:i');
		} elseif (is_null($value)) {
			echo "NULL";
		} elseif (is_array($value)) {
			print_r($value);
		} else {
			echo "?" . gettype($value);
		}
	}

	protected function renderToOne(ToOne $toOne)
	{
		echo "<td>{$toOne->getName()}</td>";
		echo "<td>";
		$this->renderIdentification($toOne->getOld());
		echo "</td>";
		echo "<td>";
		$this->renderIdentification($toOne->getNew());
		echo "</td>";
		echo "<td>";
		if ($toOne->getChangeSet()) {
			$this->renderChangeSet($toOne->getChangeSet());
		}
		echo "</td>";
	}

	protected function renderToMany(ToMany $toMany)
	{
		$rCount = count($toMany->getRemoved());
		$aCount = count($toMany->getAdded());
		$chCount = count($toMany->getChangeSets());
		
		$rows = max($rCount, $aCount, $chCount);
		
		foreach (range(1, $rows) as $i) {
			echo "<tr>";
			if ($i == 1) {
				echo "<td rowspan='$rows'>{$toMany->getName()}</td>";
			}
			echo "<td>";
			if ($rCount >= $i) {
			    $this->renderIdentification(array_values($toMany->getRemoved())[$i-1]);
			}
			echo "</td>";
			echo "<td>";
			if ($aCount >= $i) {
			    $this->renderIdentification(array_values($toMany->getAdded())[$i-1]);
			}
			echo "</td>";
			echo "<td>";
			if ($chCount >= $i) {
			    $this->renderChangeSet(array_values($toMany->getChangeSets())[$i-1]);
			}
			echo "</td>";
			echo "</tr>";
		}
	}

}
