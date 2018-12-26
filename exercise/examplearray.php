<?php

use eftec\chaosmachineone\ChaosMachineOne;
use eftec\chaosmachineone\MiniLang;

include "../lib/ChaosMachineOne.php";
include "../lib/ChaosField.php";      
include "../lib/MiniLang.php";

$chaos=new ChaosMachineOne();
$chaos->values['_index']=100;
                                   
include "../lib/en_US/Person.php";



// skip next day
// skip next workingday
// skip next weekend
// skip next monday
// skip add 8 hours
// skip next month (first month)


$chaos->table('table',1000)
	->field('name','string','database','',0,200)
	->field('fullname','string','database','',0,200)
	->field('text','string','database','',0,40)
	->field('sex','int','local',0,0,1)
	->array('firstNameMale',PersonContainer::$firstNameMale)
	->array('lastName',PersonContainer::$lastName)
	->array('titleMale',PersonContainer::$titleMale)
	->array('suffix',PersonContainer::$suffix)
	->array('firstNameFemale',PersonContainer::$firstNameFemale)
	->array('titleFemale',PersonContainer::$titleFemale)
	->array('loremIpsum',PersonContainer::$loremIpsum)
	
	->format('maleNameFormats',PersonContainer::$maleNameFormats)
	->format('femaleNameFormats',PersonContainer::$femaleNameFormats)
	
	->gen('when always then sex=random(0,1)')
	->gen('when sex=0 set name.value=randomarray("firstNameMale")')
	->gen('when sex=0 set fullname.value=randomformat("maleNameFormats")')
	->gen('when sex=1 set name.value=randomarray("firstNameFemale")')
	->gen('when sex=1 set fullname.value=randomformat("femaleNameFormats")')
	->gen('when always then text.value=randomtext("Lorem ipsum dolor","loremIpsum",1,4,30)')
	->show(['name','fullname','text']);
	