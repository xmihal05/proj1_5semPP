<?php
#!/usr/bin/php
#DKA:xmihal05

/***********************************************************/
/*************		GLOBALS		********************/
/***********************************************************/
$d = false;
$e = false;
$input = false;
$output = false;
$i = false;
$R_empty = false;
$F_empty = false;

//print help
$hlp_str = <<<EOD
	DKA: Determinization of finite-state machine

Script execution:
	dka.php [--help --input=filename --output=filename -e(--no-epsilon-rules)/-d(--determinization) -i(--case-insensitive)]

Parameters:
    --help			=>	prints out this help message, cannot be combined with any other parameter
    --input=filename		=>	opens input file with filename, if not used reads standard input
    --output=filename		=>	create or rewrite output file with filename, if not used writes on standard output
    -e/--no-epsilon-rules	=>	remove epsilon rules from input, cannot be combined with determinization
    -d/--determinization	=>	determinization without generating unavailable states
    -i/--case-insensitive	=>	reads input as case insensitive, output with only small letters
    no parameter used		=>	rewrite input on output in "normal form"

This script doesn't implement any extensions.

EOD;

/***********************************************************/
/*************		FUNCTIONS	********************/
/***********************************************************/

function pars_params($argc, $argv){	
	//short options for argument pars
	$shortopts = "d";
	$shortopts .= "i";
	$shortopts .= "e";
	
	//longoptions for argument parse (--smth)
	$longopts = array(
			"help", 
			"input:", 
			"output:", 
			"no-epsilon-rules", 
			"case-insensitive",
			"determinization",
			);

	$params = getopt($shortopts, $longopts);
	
	//CHECK RIGHT USE OF PARAMETERS
	//check quantity of parameters
	$d_count = $e_count = $i_count = $in_count = $out_count = $h_count = 0;
	foreach($params as $item){
		if(is_array($item))	//if param is array = was used more than once
			exit(1);
	}

	//too many parameters
	if($argc > 5)
		exit(1);
	//check wrong combinations
	if($argc >= 2){ 
		if(!array_key_exists("help",$params) && !array_key_exists("input",$params)
		&& !array_key_exists("output",$params) && !array_key_exists("e",$params)
		&& !array_key_exists("no-epsilon-rules",$params) && !array_key_exists("d",$params)
		&& !array_key_exists("determinization",$params) && !array_key_exists("i",$params)
		&& !array_key_exists("case-insensitive",$params))
			exit(1);
		else{
			if (array_key_exists("help", $params)){
				if(count($argv) != 2)	//another parameter used with help
					exit(1);
				else{	//print help and exit
					echo $GLOBALS['hlp_str'];
					exit(0);
				}
			}
			if(array_key_exists("e",$params)&&array_key_exists("no-epsilon-rules",$params))
				exit(1);
			if(array_key_exists("e",$params) || array_key_exists("no-epsilon-rules",$params)){
				if((array_key_exists("d",$params)==true) || 
					(array_key_exists("determinization", $params)==true))
					exit(1);
				$GLOBALS['e'] = true;
			}
			if(array_key_exists("d",$params) && array_key_exists("determinization",$params))
				exit(1);
			if(array_key_exists("d",$params) ||  array_key_exists("determinization",$params))
				$GLOBALS['d'] = true;
			if(array_key_exists("i",$params) && array_key_exists("case-insensitive",$params))
				exit(1);
			if(array_key_exists("i",$params) || array_key_exists("case-insensitive",$params))
				$GLOBALS['i'] = true;
		}
	}
	
	//get value of param option
	foreach($params as $key => $option){
		if($key == "input"){
			$GLOBALS['input_file'] = $option;
			$GLOBALS['input'] = true;
		}
		else if($key == "output"){
			$GLOBALS['output_file'] = $option;
			$GLOBALS['output'] = true;
		}
	}


}

function del_whitechar(&$in_file){
	//find comments and delete them
	$find_comments = "~'\#+'(*SKIP)(*FAIL)|\#.*\n*~";
	$in_file = preg_replace($find_comments, "", $in_file);

	//find comma in the alphabet and replace it with special char
	$find_comma_beg = "~\{(','),\s~";	//comma in the beginning
	$find_comma_end = "~\s(',')\}~";	//in the end
	$find_comma_mid = "~\s(','),\s~";	//somewhere in the middle
	$find_comma_rule = "~\s(',')\s->~";	//used in rule
	$in_file = preg_replace($find_comma_beg, "{'comma', ", $in_file);
	$in_file = preg_replace($find_comma_end, " 'comma'}", $in_file);
	$in_file = preg_replace($find_comma_mid, " 'comma', ", $in_file);
	$in_file = preg_replace($find_comma_rule, " 'comma' ->", $in_file);

	//find white characters and delete them
	$find_space = "~'\s+'(*SKIP)(*FAIL)|\s~";
	$in_file = preg_replace($find_space,"",$in_file);
}

function states_check(&$in_file){	

//identifiers of C language 	- must start with letter, can be followd by number
//				- no special chars except _ in the middle

	//states are empty!!
	$empty_states = "~\(\{\},\{'~";
	preg_match($empty_states,$in_file,$error1);
	if(!empty($error1)) exit(41);
	
	//find wrong use of comma
	$wrong_comma = "~\(\{.*,,.*\},\{'*~";
	preg_match($wrong_comma, $in_file, $comma_error);
	if(!empty($comma_error)) exit(40);

	//find set of states and save them into array
	$find_states = "~\(\{([a-zA-Z0-9_,]*)\}~";
	preg_match($find_states, $in_file, $all_states);
	if(empty($all_states)) exit(40);
	foreach($all_states as $item) $state = preg_split("~,~", $item);
	
	foreach($state as $item){
		//check if starts with the number
		$starts_num = "~[a-zA-Z]+[a-zA-Z0-9_]*(*SKIP)(*FAIL)|[0-9]+[a-zA-Z0-9_]*~";
		preg_match($starts_num, $item, $error2);
		if(!empty($error2))
			exit(40);

		//check if state isnt *_state_*
		$wrong_syn = "~.+_+.+(*SKIP)(*FAIL)|_*.+_+|_+.+_*~";		
		preg_match($wrong_syn, $item,$error3);
		if(!empty($error3))
			exit(40);
	}
	
	//change every character to lower case if requested
	if($GLOBALS['i']){
		foreach($state as $key => $value)
			$state[$key] = mb_strtolower($value, 'UTF-8');
	}

	//save parsed states into global variable
	sort($state);	//sort <
	$GLOBALS['states_arr'] = $state;
}

function alph_check(&$in_file){

	//initial alphabet is empty
	$alph_empty = "~\(\{.*\},\{\},\{.*\},[a-zA-Z0-9_]+,\{.*\}\)~";
	preg_match($alph_empty,$in_file,$error1);
	if(!empty($error1)) exit(41);

	//find initial alphabet characters
	$alphabet_chars = "~\(\{.*\},\{(.*)\},\{.*\},[a-zA-Z0-9_]+,\{.*\}\)~";
	preg_match($alphabet_chars,$in_file,$full_alphabet);
	if(empty($full_alphabet)) exit(40);
	//parse alphabet into array
	foreach($full_alphabet as $item) $alphabet = preg_split("~,~", $item);	
	
	//change to lower case if requested
	if($GLOBALS['i']){
		foreach($alphabet as $key => $value)
			$alphabet[$key] = mb_strtolower($value,'UTF-8');
	}

	//save alphabet into global variable
	sort($alphabet); //sort <
	$GLOBALS['alphabet_arr'] = $alphabet;
}

function rules_check(&$in_file){

	//rules empty
	$rules_empty = "~\(\{.*\},\{.*\},\{\},[a-zA-Z0-9_],\{.*\}\)~";
	preg_match($rules_empty,$in_file,$error1);
	if(!empty($error1)){
		$GLOBALS['R_empty'] = true;	//empty rules are valid
		goto rules_end;	
	}
	//find rules and parse them
	$match_rules = "~\(\{.*\},\{.*\},\{(.*)\},[a-zA-Z0-9_]+,\{.*\}\)~";
	preg_match($match_rules,$in_file,$all_rules);
	if(empty($all_rules)) exit(40);	//syntax error in finite state machine
	foreach($all_rules as $item) $rules = preg_split("~,~", $item);

	//change to lower case if requested 
	if($GLOBALS['i']){
		foreach($rules as $key => $value)
			$rules[$key] = mb_strtolower($value, 'UTF-8');
	}

	//check states from rules
	$get_states = "~([a-zA-Z0-9_]+)'.*'->([a-zA-Z0-9_]+)~";
	foreach($rules as $key => $value)
		preg_match($get_states,$value,$rules_states[$key]);
	//save all states into one array
	$no = 0;
	foreach($rules_states as $key){
		foreach($key as $item => $value){
			if($item == "1" || $item == "2"){
				$parsed_states[$no] = $value;
				$no = $no + 1;
			}				
		}
	}
	//merge array by same value
	$merged_states[0] = $parsed_states[0];
	$mg = 1;
	foreach($parsed_states as $key => $value_p){
		foreach($merged_states as $item => $value_m){
			//save into array if value is different
			if($value_p != $value_m){
				if($item == (count($merged_states) - 1)){ //last key
					$merged_states[$mg] = $value_p;
					$mg = $mg + 1;
				}
				else continue;
			}
			else break;
		}
	}
	//check if all the states were defined all states array
	$include_all_states = count(array_intersect($merged_states, $GLOBALS['states_arr']))
				== count($merged_states);
	if(!$include_all_states) exit(41);

	//check alphabet used in rules
	$get_alphabeth = "~[a-zA-Z0-9_]+('.*')->[a-zA-Z0-9_]+~";
	foreach($rules as $key => $value)
		preg_match($get_alphabeth,$value,$rules_alph[$key]);
	//save all the matches into one array
	$pa = 0;
	foreach($rules_alph as $key){
		foreach($key as $item => $value){
			if($item == "1"){
				$parsed_alph[$pa] = $value;
				$pa = $pa + 1;
			}
		}
	}
	//merge aplphabet into array by same value
	$merged_alph[0] = $parsed_alph[0];
	$mg1 = 1;
	foreach($parsed_alph as $key => $value_p){
		foreach($merged_alph as $item => $value_m){
			if($value_p == "''")
				break;
			if($value_p != $value_m){	
				if($item == (count($merged_alph) - 1)){ //last key
					$merged_alph[$mg1] = $value_p;
					$mg1 = $mg1 + 1;
				}
				else continue;
			}
			else break;
		}
	}
	//check if everything was defined in initial alphabeth
	$include_all_alph = count(array_intersect($merged_alph, 
			$GLOBALS['alphabet_arr'])) == count($merged_alph);
	if(!$include_all_alph) exit(41);

	//save rules into global variable
	sort($rules); //sort <
	$GLOBALS['rules_arr'] = $rules;
	rules_end:;
}

function finstates_check(&$in_file){ //find finite states and check them

	//finite states are empty
	$fin_empty = "~,\{.*\},(*SKIP)(*FAIL)|,\{\}\)~";
	preg_match($fin_empty,$in_file,$error1);
	if(!empty($error1)){
		$GLOBALS['F_empty'] = true;	//empty finites are valid
		goto fin_end;
	}

	//find finite states
	$fin_states = "~,\{.*\},(*SKIP)(*FAIL)|,\{(.*)\}\)~";
	preg_match($fin_states,$in_file,$all_finites);
	if(empty($all_finites)) exit(40);
	//save finite sates into array - split them by ,
	foreach($all_finites as $item) $finites = preg_split("~,~", $item);

	//change every character to lower case if requested 
	if($GLOBALS['i']){
		foreach($finites as $key => $value)
			$finites[$key] = mb_strtolower($value, 'UTF-8');
	}

	//check if every finite state was included in all states
	$includes_all = count(array_intersect($finites, $GLOBALS['states_arr'])) ==
			count($finites);
	if(!$includes_all) exit(41);

	//save parsed finite states into global variable
	sort($finites); //sort <
	$GLOBALS['fin_arr'] = $finites;
	fin_end:;
}

function initstate_check(&$in_file){
	//no initial state in finite state machine
	$ini_empty = "~\},,\{.*\}\)~";
	preg_match($ini_empty,$in_file,$error1);
	if(!empty($error1)) exit(40);

	//find initial state
	$init_state = "~\},([a-zA-Z0-9_]+),\{.*\}\)~";
	preg_match($init_state,$in_file,$initial);
	if(empty($initial)) exit(40);
	foreach($initial as $item) $initial = preg_split("~,~",$item);

	//change every character to lower case if requested
	if($GLOBALS['i']){
		foreach($initial as $key => $value)
			$initial[$key] = mb_strtolower($value, 'UTF-8');
	}

	//check if initial state was stated in all states
	$include_all = count(array_intersect($initial,$GLOBALS['states_arr'])) ==
			count($initial);
	if(!$include_all) exit(41);

	//save initial state into global variable
	$GLOBALS['init_state'] = $initial;
}

function normal_form(){

	//opens output file
	if($GLOBALS['output'])
		$out_file = fopen($GLOBALS['output_file'], "w") or exit(3);
	else $out_file = STDOUT;

	//save global variables into local for quicker use
	$states = $GLOBALS['states_arr'];
	$alph = $GLOBALS['alphabet_arr'];
	if(!$GLOBALS['R_empty'])
		$rules = $GLOBALS['rules_arr'];
	$init = $GLOBALS['init_state'];
	if(!$GLOBALS['F_empty'])
		$fin = $GLOBALS['fin_arr'];
	
	//BEGINING OF OUTPUT
	fwrite($out_file,"(\n");

	//print states into outfile
	fwrite($out_file,"{");
//	sort($states);
	foreach($states as $key => $value){
		if((count($states) - 1) == $key)	//last in the array
			fwrite($out_file, "$value},\n");
		else fwrite($out_file, "$value, ");
	}

	//print alphabet into outfile
	fwrite($out_file,"{");
//	sort($alph);
	foreach($alph as $key => $value){
		if((count($alph) - 1) == $key){
			if($value == "'comma'")
				fwrite($out_file, "','},\n");
			else fwrite($out_file, "$value},\n");
		}
		else{
			if($value == "'comma'")
				fwrite($out_file, "',', ");
			 fwrite($out_file, "$value, ");
		}
	}

	//print rules into outfile
	fwrite($out_file,"{\n");
	//parse rules into beginig states, alphabet and end states
	$rules_split = "~([a-zA-Z0-9_]+)'(.*)'->([a-zA-Z0-9_]+)~";
	if(!$GLOBALS['R_empty']){
//		sort($rules);
		foreach($rules as $key => $value){
			preg_match($rules_split, $value, $split_array[$key]);
		}
		$i = 0;
		//print out rules
		foreach($split_array as $item){
			$i++;
			foreach($item as $key => $value){
				if($key == "1")
					fwrite($out_file, "$value ");
				else if($key == "2"){
					if($value == "comma")	//if comma found replace with ,
						fwrite($out_file, "',' -> ");
					else fwrite($out_file, "'$value' -> ");
				}
				else if($key == "3"){
					if($i == count($split_array))	//dont print , behind last rule!
						fwrite($out_file, "$value\n");
					else fwrite($out_file, "$value,\n");
				}
				else continue;
			}
		}
	}
	fwrite($out_file,"},\n");	

	//print initial state
	fwrite($out_file, "$init[0],\n");

	//print finite states into outfile
	fwrite($out_file,"{");
	if(!$GLOBALS['F_empty']){
//		sort($fin);
		foreach($fin as $key => $value){
			if((count($fin) - 1) == $key)
				fwrite($out_file, "$value}\n");
			else fwrite($out_file, "$value, ");
		}
	}
	else fwrite($out_file, "}\n");
	fwrite($out_file,")");
	//END OF OUTPUT

	//close file
	fclose($out_file);
	exit(0);
}

function del_epsilon(&$out_file){
	$i = $o = 0;
	$j = 1;
	$save_this = false;

	if($GLOBALS['R_empty'])	//rules are empty = write output
		normal_form($out_file);
	$rules = $GLOBALS['rules_arr'];

	//split rules (p)(a)->(q)
	$rules_split = "~([a-zA-Z0-9_]+)('.*')->([a-zA-Z0-9_]+)~";
	foreach($rules as $item => $value){
		preg_match($rules_split, $value, $split_array[$item]);
	}
	//find epsilon transitions and save their fin states into array
	foreach($split_array as $item){
		foreach($item as $key => $value){
			if($key == "2"){
				if($value == "''")
					$save_this = true;
			}
			if($key == "3" && $save_this){
				$eps_arr[$value] = "$value";
				$save_this=false;
			}
		}
	}

	//if no epsilon transitions found => print out output
	if(empty($eps_arr)) normal_form($out_file);

	$save_this=false;
	//find transition to replace epsilon
	foreach($eps_arr as $key_e => $val_e){
		$append_e = $val_e;
		$append_t = "";
		foreach($split_array as $item){
			foreach($item as $key => $val_s){
				if($key == "1" && $val_s == $val_e)
					$save_this=true;
				if($key == "2" && $save_this)
					$append_t .= ",$val_s";
				if($key == "3" && $save_this){
					if($val_s != $val_e)
						$append_e .= ",$val_s";
					$save_this = false;
				}
			}
		}
		$eps_arr[$val_e] = $append_e;
		$eps_trans[$val_e] = $append_t;
	}
	//save transitions and states into arrays
	foreach($eps_arr as $item => $value) 
		$eps_states[$item] = preg_split("~,~",$value);
	foreach($eps_trans as $item => $value)
			$eps_transitions[$item] = preg_split("~,~", $value);
	
	//save new rules
	$save_this = false;
	$push_string = false;
	foreach($split_array as $item){
		foreach($item as $key => $value){
			if($key == "1"){
				$start_char = $value;
				$push_char = $value;	//push first value into string
			}
			else if($key == "2"){
				if($value == "''")	//epsilon rule found
					$save_this = true;
				else $push_char .= "$value->";	//append transition into string
			}
			else if($key == "3"){
				if(!$save_this){
					$push_char .= $value;	//append last value into string
					$push_string = true;
				}
				else{	//if epsilon found continue
					$index = $value;
					foreach($eps_states as $state_item => $s_val){
						if($state_item == $index){
						$smth1 = $s_val[$o];
							//save non epsilon rules into array
							foreach($eps_transitions as $tran_item => $t_val){
								if($tran_item == $index){
									while($o < count($s_val)){
										$tran = $t_val[$j];
										$end = $s_val[$o];
										$push_char = "$start_char$tran->$end";
										$new_rules[$i] = $push_char;
										$i++;
										$o++;
										$j++;
									}
								}
							}
						}
					}
					$j = 1;
					$o = 0;
					$save_this = false;
				}
			}
			if($push_string){	//push string into rules array
				$new_rules[$i] = $push_char;
				$i++;
				$push_string = false;
			}
			else continue;
		}
	}
	sort($new_rules);	//sort <
	$GLOBALS['rules_arr'] = $new_rules;	//save new rules into global variable
}

/***********************************************************/
/*************		MAIN 		********************/
/***********************************************************/

pars_params($argc, $argv);
//read input 
	if($GLOBALS['input'])	//--input param as used
		$in = file_get_contents($GLOBALS['input_file']) or exit(2);
	else $in = fgets(STDIN);	//read from standard input

//delete comments and white characters
del_whitechar($in);
//check for text behind paranthesis
$fail_text = "~\(\{.*\}\)(*SKIP)(*FAIL)|.+~";
preg_match($fail_text, $in, $error1);
if(!empty($error1)) exit(40);
//check for wrong use of ,
$wrong_col = "~\{,|,\}|,,~";
preg_match($wrong_col,$in,$error2);
if(!empty($error2)) exit(40);

//parse input file
states_check($in);	//set of states
alph_check($in);	//input characters
rules_check($in);	//rules
initstate_check($in);	//initial state
finstates_check($in);	//finite states

//Call functions for output
if($GLOBALS['e']) del_epsilon();	//erase epsilon transitions
//else if($GLOBALS['d']) determinization($out);
normal_form();	//output in normal form

?>
