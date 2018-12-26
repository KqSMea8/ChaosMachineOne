<?php
namespace eftec\chaosmachineone;


/**
 * It's a mini language parser. It uses the build-in token_get_all function to performance purpose.
 * Class MiniLang
 * @package eftec\chaosmachineone
 * @author   Jorge Patricio Castro Castillo <jcastro arroba eftec dot cl>
 * @version 1.13 2018-12-23
 * * now function allows parameters fnname(1,2,3)
 * * now set allows operators (+,-,*,/). set field=a1+20+40
 * @link https://github.com/EFTEC/StateMachineOne
 */
class MiniLang
{
	/**
	 * When operators (if any)
	 * @var array 
	 */
	var $logic=[];
	/**
	 * Set operators (if any
	 * @var array 
	 */
	var $set=[];
	private $specialCom=[];
	private $areaName=[];
	/** @var array values per the special area */
	var $areaValue=[];
	var $serviceClass=null;
	private $langCounter=0;

	/**
	 * MiniLang constructor.
	 * @param array $specialCom Special commands. it calls a function of the caller.
	 * @param array $areaName It marks special areas that could be called as "<namearea> somevalue"
	 * @param null $serviceClass
	 */
	public function __construct(array $specialCom=[],$areaName=[],$serviceClass=null)
	{
		$this->specialCom = $specialCom;
		$this->areaName=$areaName;
		$this->serviceClass=$serviceClass;
		$this->langCounter=-1;
		$this->logic=[];
		$this->set=[];
	}


	public function reset() {


		//$this->areaName=[];
		//$this->areaValue=[];
	}

	/**
	 * @param $text
	 */
	public function separate($text) {
		$this->reset();
		$this->langCounter++;

		$this->logic[$this->langCounter]=[];
		$this->set[$this->langCounter]=[];
		$rToken=token_get_all("<?php ".$text);
		/*echo "<pre>";
		var_dump($rToken);
		echo "</pre>";*/
		//die(1);
		$rToken[]=''; // avoid last operation
		$count=count($rToken)-1;
		$first=true;
		$inFunction=false;
		$inSet=false;
		for($i=0;$i<$count;$i++) {
			$v=$rToken[$i];
			if(is_array($v)) {
				switch ($v[0]) {
					case T_CONSTANT_ENCAPSED_STRING:
						$this->addBinOper($first,$inSet,$inFunction,'string'
							,substr($v[1],1,-1),null);
						break;
					case T_VARIABLE:
						if (is_string($rToken[$i+1]) && $rToken[$i+1]=='.') {
							// $var.vvv
							$this->addBinOper($first,$inSet,$inFunction,'subvar'
								,substr($v[1],1),$rToken[$i+2][1]);
							$i+=2;
						} else {
							// $var
							$this->addBinOper($first,$inSet,$inFunction,'var'
								,substr($v[1],1),null);
						}
						break;
					case T_LNUMBER:
					case T_DNUMBER:
						$this->addBinOper($first,$inSet,$inFunction, 'number'
							, $v[1], null);
						break;
					case T_STRING:
						if (in_array($v[1],$this->areaName)) {
							// its an area. <area> <somvalue>
							if (count($rToken)>$i+2) {
								$tk=$rToken[$i + 2];
								
								switch ($tk[0]) {
									case T_VARIABLE:
										$this->areaValue[$v[1]]=['var',$tk[1],null];
										break;
									case T_STRING:
										$this->areaValue[$v[1]]=['field',$tk[1],null];
										break;
									case T_LNUMBER:
										$this->areaValue[$v[1]]=$tk[1];
										break;
								}
							}
							$i+=2;
						} else {
							switch ($v[1]) {
								case 'where':
								case 'when':
									// adding a new when
									$inSet=false;
									break;
								case 'then':
								case 'set':
									//adding a new set
									$inSet=true;
									break;
								default:
									if (is_string($rToken[$i + 1])) {
										if ($rToken[$i + 1] == '.') {
											// field.vvv
											$this->addBinOper($first,$inSet,$inFunction, 'subfield', $v[1], $rToken[$i + 2][1]);
											$i += 2;
										} elseif ($rToken[$i + 1] == '(') {
											// function()
											$this->addBinOper($first,$inSet,$inFunction, 'fn', $v[1], null);
											$inFunction=true;
											$i+=1;
										} else {
											// field
											if (in_array($v[1], $this->specialCom)) {
												$this->addBinOper($first,$inSet,$inFunction, 'special', $v[1], null);
												$first = true;
											} else {
												$this->addBinOper($first,$inSet,$inFunction, 'field', $v[1], null);
											}

										}
									} else {
										// field
										$this->addBinOper($first,$inSet,$inFunction, 'field', $v[1], null);
									}
									break;
							}
						}
						break;
					case T_IS_EQUAL:
						$this->addOp($inSet,$first,'=');
						break;
					case T_IS_GREATER_OR_EQUAL:
						$this->addOp($inSet,$first,'>=');
						break;
					case T_IS_SMALLER_OR_EQUAL:
						$this->addOp($inSet,$first,'<=');
						break;
					case T_IS_NOT_EQUAL:
						$this->addOp($inSet,$first,'<>');
						break;
					case T_LOGICAL_AND:
					case T_BOOLEAN_AND:
						if ($inSet) {
							$first=true;
						} else {
							$this->addLogic($inSet, $first, 'and');
						}
						break;
					case T_BOOLEAN_OR:
					case T_LOGICAL_OR:
						$this->addLogic($inSet,$first,'or');
						break;
				}
			} else {
				switch ($v) {
					case '-':
						if (is_array($rToken[$i+1]) && ($rToken[$i+1][0]==T_LNUMBER || $rToken[$i+1][0]==T_DNUMBER )) {
							// it's a negative value
							$this->addBinOper($first,$inSet,$inFunction, 'number', -$rToken[$i+1][1], null);
							$i++;
						} else {
							// its a minus
							$this->addOp($inSet, $first, $v);
						}
						break;
					case ')':
						$inFunction=false;
						break;
					case ',':
						if (!$inFunction) {
							if ($inSet) {
								$first = true;
							} else {
								$this->addLogic($inSet, $first, ',');
							}
						}
						break;
					case '=':
					case '+':
					case '*':
					case '/':
					case '<':
					case '>':
						$this->addOp($inSet,$first,$v);
						break;
				}
			}
		}
	}

	/**
	 * @param mixed $caller
	 * @param array $dictionary
	 * @param int $idx
	 * @return bool|string it returns the evaluation of the logic or it returns the value special (if any).
	 */
	public function evalLogic(&$caller,$dictionary,$idx=0) {
		$prev=true;
		$r=false;
		$addType='';
		foreach($this->logic[$idx] as $k=> $v) {
			if($v[0]==='pair') {
				if ($v[1]=='special') {
					if (count($v)>=7) {
						return $caller->{$v[2]}($v[6]);
					} else {
						return $caller->{$v[2]}();
					}
				}
				$field0=$this->getValue($v[1],$v[2],$v[3],$caller,$dictionary);
				if (count($v)>=8) {
					$field1 = $this->getValue($v[5], $v[6], $v[7], $caller, $dictionary);
				} else {
					$field1=null;
				}
				switch ($v[4]) {
					case '=':
						$r = ($field0 == $field1);
						break;
					case '<>':
						$r = ($field0 != $field1);
						break;
					case '<':
						$r = ($field0 < $field1);
						break;
					case '<=':
						$r = ($field0 <= $field1);
						break;
					case '>':
						$r = ($field0 > $field1);
						break;
					case '>=':
						$r = ($field0 >= $field1);
						break;
					case 'contain':
						$r = (strpos($field0, $field1) !== false);
						break;
					default:
						trigger_error("comparison {$v[4]} not defined for eval logic.");
				}
				switch ($addType) {
					case 'and':
						$r=$prev && $r;
						break;
					case 'or':
						$r=$prev || $r;
						break;
					case '':
						break;
				}
				$prev=$r;
			} else {
				// logic
				$addType=$v[1];
			}
		} // for
		return $r;
	}
	public function evalAllLogic(&$caller,$dictionary) {
		for($i=0; $i<=$this->langCounter; $i++) {
			if ($this->evalLogic($caller,$dictionary,$i)) {
				$this->evalSet($caller,$dictionary,$i);
				break;
			}
		}
	}
	
	/**
	 * @param mixed $caller
	 * @param array $dic
	 * @param int $idx
	 * @return void
	 */
	public function evalSet(&$caller,&$dic,$idx=0) {
		foreach($this->set[$idx] as $k=>$v) {
			if($v[0]==='pair') {
				$name=$v[2];
				$ext=$v[3];
				$op=$v[4];
				//$field0=$this->getValue($v[1],$v[2],$v[3],$caller,$dictionary);
				$field1 = $this->getValue($v[5], $v[6], $v[7], $caller, $dic);
				for($i=8;$i<count($v);$i+=4) {
					switch ($v[$i]) {
						case '+':
							$field1 += $this->getValue($v[$i+1], $v[$i+2], $v[$i+3], $caller, $dic);
							break;
						case '-':
							$field1 -= $this->getValue($v[$i+1], $v[$i+2], $v[$i+3], $caller, $dic);
							break;
						case '*':
							$field1 *= $this->getValue($v[$i+1], $v[$i+2], $v[$i+3], $caller, $dic);
							break;
						case '/':
							$field1 /= $this->getValue($v[$i+1], $v[$i+2], $v[$i+3], $caller, $dic);
							break;
					}
				}
				if ($field1==='___FLIP___') {
					$field0=$this->getValue($v[1],$v[2],$v[3],$caller,$dic);
					$field1=(!$field0)?1:0;
				}
				switch ($v[1]) {
					case 'subvar':
						// $a.field
						$rname=@$GLOBALS[$name];
						if (is_object($rname)) {
							$rname->{$ext}=$field1;
						} else {
							$rname[$ext]=$field1;
						}
						break;
					case 'var':
						// $a
						switch ($op) {
							case '=':
								$GLOBALS[$name]=$field1;
								break;
							case '+';
								$GLOBALS[$name]+=$field1;
								break;
							case '-';
								$GLOBALS[$name]-=$field1;
								break;
						}
						break;
					case 'number':
					case 'string':
						trigger_error("comparison {$v[4]} not defined for transaction.");
						break;
					case 'field':
						switch ($op) {
							case '=':
								$dic[$name]=$field1;
								break;
							case '+';
								$dic[$name]+=$field1;
								break;
							case '-';
								$dic[$name]-=$field1;
								break;
						}
						break;
					case 'subfield':
						$args=[$dic[$name]];
						$this->callFunctionSet($caller,$ext,$args,$field1);
						break;
					case 'fn':
						// function name($caller,$somevar);
						$args=[];
						foreach($ext as $e) {
							$args[]=$this->getValue($e[0],$e[1],$e[2],$caller,$dic);
						}
						$this->callFunctionSet($caller,$name,$args,$field1);
						break;
					default:
						trigger_error("set {$v[4]} not defined for transaction.");
						break;
				}
			}
		} // for
	}
	private function callFunction($caller, $nameFunction, $args) {
		if (is_object($caller)) {
			if(method_exists($caller,$nameFunction)) {
				return call_user_func_array(array($caller,$nameFunction),$args);
			} 
			if (isset($caller->{$nameFunction})) {
				return $caller->{$nameFunction};
			}
		} else {
			if(is_array($caller)) {
				if(isset($caller[$nameFunction])) {
					return $caller[$nameFunction];
				}
			}
		}
		return call_user_func_array(array($this->serviceClass,$nameFunction),$args);
	}

	/**
	 * @param $caller
	 * @param $nameFunction
	 * @param $args
	 * @param $setValue
	 * @return void
	 */
	private function callFunctionSet($caller, $nameFunction, $args, $setValue) {
		if (is_object($caller)) {
			if(method_exists($caller,$nameFunction)) {
				$args[]=$setValue; // it adds a second parameter
				call_user_func_array(array($caller,$nameFunction),$args);
				return;
			
			} elseif (isset($caller->{$nameFunction})) {
				$caller->{$nameFunction}=$setValue;
				return;
			}
		} else {
			if(is_array($caller)) {
				if(isset($caller[$nameFunction])) {
					$caller[$nameFunction]=$setValue;
					return;
				}
			}
		}
		call_user_func_array(array($this->serviceClass,$nameFunction),$args);
	}
	public function getValue($type,$name,$ext,$caller,$dic) {
		switch ($type) {
			case 'subvar':
				// $a.field
				$rname=@$GLOBALS[$name];
				$r=(is_object($rname))?$rname->{$ext}:$rname[$ext];
				break;
			case 'var':
				// $a
				$r=@$GLOBALS[$name];
				break;
			case 'number':
				// 20
				$r=$name;
				break;
			case 'string':
				// 'aaa',"aaa"
				$r=$name;
				break;
			case 'field':
				$r=@$dic[$name];
				break;
			case 'subfield':
				// field.sum is equals to sum(field)
				$args=[@$dic[$name]];
				$r=$this->callFunction($caller,$ext,$args);
				break;
			case 'fn':
				switch ($name) {
					case 'null':
						return null;
					case 'false':
						return false;
					case 'true':
						return true;
					case 'on':
						return 1;
					case 'off':
						return 0;
					case 'undef':
						return -1;
					case 'flip':
						return "___FLIP___"; // value must be flipped (only for set).
					case 'now':
					case 'timer':
						return time();
					case 'interval':
						return time() - $caller->dateLastChange;
					case 'fullinterval':
						return time() - $caller->dateInit;
					default:
						$args=[];
						foreach($ext as $e) {
							$args[]=$this->getValue($e[0],$e[1],$e[2],$caller,$dic);
						}
						return $this->callFunction($caller,$name,$args);
				}
				break;
			case 'special':
				return $name;
				break;
			default:
				trigger_error("value with type[$type] not defined");
				return null;
		}
		return $r;
	}

	/**
	 * It adds part of a pair of operation.
	 * @param bool $first if it is the first part or second part of the expression.
	 * @param bool $inSet
	 * @param bool $inFunction
	 * @param string $type =['string','var','subvar','number','field','subfield','fn','special'][$i]
	 * @param string $name name of the field
	 * @param null|string $ext extra parameter.
	 */
	private function addBinOper(&$first,$inSet,$inFunction, $type, $name, $ext=null) {
		if ($inFunction) {
			$this->addParam($inSet,$type, $name, $ext);
			return;
		}
		if ($first) {
			if ($inSet) {
				$this->set[$this->langCounter][] = ['pair', $type, $name, $ext];
			} else {
				$this->logic[$this->langCounter][] = ['pair', $type, $name, $ext];
			}
		} else {
			if($inSet) {
				$f=count($this->set[$this->langCounter])-1;
				$f2=count($this->set[$this->langCounter][$f]);
				$this->set[$this->langCounter][$f][$f2]=$type;
				$this->set[$this->langCounter][$f][$f2+1]=$name;
				$this->set[$this->langCounter][$f][$f2+2]=$ext;
			} else {
				$f=count($this->logic[$this->langCounter])-1;
				$f2=count($this->logic[$this->langCounter][$f]);
				$this->logic[$this->langCounter][$f][$f2]=$type;
				$this->logic[$this->langCounter][$f][$f2+1]=$name;
				$this->logic[$this->langCounter][$f][$f2+2]=$ext;
				$first=true;
			}

		}
	}

	/**
	 * Add params of a function
	 * @param bool $inSet
	 * @param string $type =['string','var','subvar','number','field','subfield','fn','special'][$i]
	 * @param string $name name of the field
	 * @param null|string $ext extra parameter.
	 */
	private function addParam($inSet,$type, $name, $ext=null) {
		if($inSet) {
			$f = count($this->set[$this->langCounter]) - 1;
			$idx = count($this->set[$this->langCounter][$f]) - 1;
			if (!isset($this->set[$this->langCounter][$f][$idx])) {
				$this->set[$this->langCounter][$f][$idx] = [];

			}
			$this->set[$this->langCounter][$f][$idx][] = [$type, $name, $ext];
		} else {
			$f = count($this->logic[$this->langCounter]) - 1;
			$idx = count($this->logic[$this->langCounter][$f]) - 1;
			if (!isset($this->logic[$this->langCounter][$f][$idx])) {
				$this->logic[$this->langCounter][$f][$idx] = [];

			}
			$this->logic[$this->langCounter][$f][$idx][] = [$type, $name, $ext];	
		}
	}

	/**
	 * It adds an operation (such as =,<,+,etc.)
	 * @param bool $inSet
	 * @param bool $first If it's true then it is the first value of a binary
	 * @param string $opName
	 */
	private function addOp($inSet, &$first, $opName) {
		if($inSet) {
			if ($first) {
				$f = count($this->set[$this->langCounter]) - 1;
				$this->set[$this->langCounter][$f][4] = $opName;
				$first = false;
			} else {
				$f = count($this->set[$this->langCounter]) - 1;
				$this->set[$this->langCounter][$f][] = $opName;
			}
		} else {
			if ($first) {
				$f = count($this->logic[$this->langCounter]) - 1;
				$this->logic[$this->langCounter][$f][4] = $opName;
				$first = false;
			} else {
				$f = count($this->logic[$this->langCounter]) - 1;
				$this->logic[$this->langCounter][$f][] = $opName;
			}
		}
	}

	/**
	 * It adds a logic
	 * @param bool $inSet
	 * @param bool $first If it's true then it is the first value of a binary
	 * @param string $name name of the logic
	 */
	private function addLogic($inSet, &$first, $name) {
		if ($first) {
			if ($inSet) {
				$this->set[$this->langCounter][] = ['logic', $name];
			} else {
				$this->logic[$this->langCounter][] = ['logic', $name];
			}
		} else {
			trigger_error("Error: Logic operation in the wrong place");
		}
	}
}