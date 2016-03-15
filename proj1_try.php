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
//$wchar = false;
//$rules = false;
//$astring = false;
//$wsfa = false;
$no_params = false;

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

function print_err($err_no){
	if($err_no == 1)
		exit(1);
	else if($err_no == 2)
		exit(2);
	else if($err_no == 3)
		exit(3);
	else if($err_no == 40)
		exit(40);
	else if($err_no == 41)
		exit(41);
	else echo "Wrong error returning value\n";
}

function pars_params($argc, $argv){	
	$shortopts = "d";
	$shortopts .= "i";
	$shortopts .= "e";
//	$shortopts .= "w";	//white char - rozsirenie
//	$shortopts .= "r";	//rules only

	$longopts = array(
			"help", 
			"input:", 
			"output:", 
			"no-epsilon-rules", 
			"case-insensitive",
			"determinization",
//			"white-char", 
//			"rules-only", 
//			"analyze-string:", 
//			"wsfa"
			);

	$params = getopt($shortopts, $longopts);
	//var_dump($params);	!!!!!!!!!CHECK FOR ARRAYS (is_array) !!!!!
	
	//CHECK RIGHT USE OF PARAMETERS
	//too many parameters
	if($argc > 5){
		echo "too many parameters!\n";
		print_err(1);
	}
	//check wrong combinations
	if($argc >= 2){ 
		if(!array_key_exists("help",$params) && !array_key_exists("input",$params)
		&& !array_key_exists("output",$params) && !array_key_exists("e",$params)
		&& !array_key_exists("no-epsilon-rules",$params) && !array_key_exists("d",$params)
		&& !array_key_exists("determinization",$params) && !array_key_exists("i",$params)
		&& !array_key_exists("case-insensitive",$params))
			print_err(1);
		else{
			if (array_key_exists("help", $params)){
				if(count($argv) != 2)	//another parameter used with help
					print_err(1);
				else{	//print help and exit
					echo $GLOBALS['hlp_str'];
					exit(0);
				}
			}
			if(array_key_exists("e",$params)&&array_key_exists("no-epsilon-rules",$params))
				print_err(1);
			if(array_key_exists("e",$params) || array_key_exists("no-epsilon-rules",$params)){
				if((array_key_exists("d",$params)==true) || 
					(array_key_exists("determinization", $params)==true))
					print_err(1);
				$GLOBALS['e'] = true;
			}
			if(array_key_exists("d",$params) && array_key_exists("determinization",$params))
				print_err(1);
			if(array_key_exists("d",$params) ||  array_key_exists("determinization",$params))
				$GLOBALS['d'] = true;
			if(array_key_exists("i",$params) && array_key_exists("case-insensitive",$params))
				print_err(1);
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
//		else if($key == "analyze-string")
//			$a_string = $option;
	}


}

function del_whitechar(&$in_file){
	//find comments and delete them
	$find_comments = "~'\#+'(*SKIP)(*FAIL)|\#.*\n*~";
	$in_file = preg_replace($find_comments, "", $in_file);

	//find comma in the alphabet and replace it with special char
	$find_comma_beg = "~\{(','),\s~";
	$find_comma_end = "~\s(',')\}~";
	$find_comma_mid = "~\s(','),\s~";
	$find_comma_rule = "~\s(',')\s->~";
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

	//find set of states and save them into array
	$find_states = "~\(\{(.*)\},\{'~";
	preg_match($find_states, $in_file, $all_states);
	if(empty($all_states)) exit(40); 
	foreach($all_states as $item) $state = preg_split("~,~", $item);
	
	foreach($state as $item){
		//check if starts with the number
		$starts_num = "~[a-zA-Z]+[a-zA-Z0-9_]*(*SKIP)(*FAIL)|[0-9]+[a-zA-Z0-9_]*~";
		preg_match($starts_num, $item, $error2);
		if(!empty($error2)){
			echo "state starts with number\n";
			exit(40);
		}

		//check for special chars
		$special_char = "~.*\!.*|.*\@.*|.*\#.*|.*\%.*|.*\^.*|
				.*\&.*|.*\*.*|.*\..*|.*;.*|.*\'.*|.*\?.*|
				.*\-.*|.*\+.*|.*\=.*|.*\/.*|.*\|.*|.*\~.*~"; // $, /, " pridat!!!! 
		preg_match($special_char,$item,$error3);
		if(!empty($error3)){
			echo "state include special character\n";
			exit(40);
		}

		//check if state isnt *_state_*
		$wrong_syn = "~.+_+.+(*SKIP)(*FAIL)|_*.+_+|_+.+_*~";		
		preg_match($wrong_syn, $item,$error4);
		if(!empty($error4)){
			echo "state starts or ends with _\n";
			exit(40);
		}
	}
	
	//change every character to lower case if requested
	if($GLOBALS['i']){
		foreach($state as $key => $value)
			$state[$key] = mb_strtolower($value, 'UTF-8');
	}

	//save parsed states into global variable
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
	$GLOBALS['alphabet_arr'] = $alphabet;
}

function rules_check(&$in_file){

	//rules empty
	$rules_empty = "~\(\{.*\},\{.*\},\{\},[a-zA-Z0-9_],\{.*\}\)~";
	preg_match($rules_empty,$in_file,$error1);
	if(!empty($error1)) exit(40);

	//find rules and parse them
	$match_rules = "~\(\{.*\},\{.*\},\{(.*)\},[a-zA-Z0-9_]+,\{.*\}\)~";
	preg_match($match_rules,$in_file,$all_rules);
	if(empty($all_rules)) exit(40);
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
			if($value_p != $value_m){	//hodnoty poly sa nerovnaju
				if($item == (count($merged_states) - 1)){ //je posledny kluc
					$merged_states[$mg] = $value_p;
					$mg = $mg + 1;
				}
				else continue;
			}
			else break;
		}
	}
	//check if all the states were stated in all states array
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
			if($value_p != $value_m){	//hodnoty poly sa nerovnaju
				if($item == (count($merged_alph) - 1)){ //je posledny kluc
					$merged_alph[$mg1] = $value_p;
					$mg1 = $mg1 + 1;
				}
				else continue;
			}
			else break;
		}
	}
	//check if everything was defined in initial alphabeth
	$include_all_alph = count(array_intersect($merged_alph, $GLOBALS['alphabet_arr'])) ==
				count($merged_alph);
	if(!$include_all_alph) exit(41);

	//save rules into global variable
	$GLOBALS['rules_arr'] = $rules;
}

function finstates_check(&$in_file){ //find finite states and check them

	//finite states are empty
	$fin_empty = "~,\{.*\},(*SKIP)(*FAIL)|,\{\}\)~";
	preg_match($fin_empty,$in_file,$error1);
	if(!empty($error1)) exit(40);

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
	$GLOBALS['fin_arr'] = $finites;
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

function normal_form(&$out_file){

	$states = $GLOBALS['states_arr'];
	$alph = $GLOBALS['alphabet_arr'];
	$rules = $GLOBALS['rules_arr'];
	$init = $GLOBALS['init_state'];
	$fin = $GLOBALS['fin_arr'];
	
	fwrite($out_file,"(\n");

	//print states into outfile
	fwrite($out_file,"{");
	foreach($states as $key => $value){
		if((count($states) - 1) == $key)	//last in the array
			fwrite($out_file, "$value},\n");
		else fwrite($out_file, "$value, ");
	}

	//print alphabet into outfile
	fwrite($out_file,"{");
	foreach($alph as $key => $value){
		if((count($alph) - 1) == $key)
			fwrite($out_file, "$value},\n");
		else fwrite($out_file, "$value, ");
	}

	//print rules into outfile
	fwrite($out_file,"{\n");		
	fwrite($out_file,"},\n");	

	//print initial state
	fwrite($out_file, "$init[0],\n");

	//print finite states into outfile
	fwrite($out_file,"{");
	foreach($fin as $key => $value){
		if((count($fin) - 1) == $key)
			fwrite($out_file, "$value}\n");
		else fwrite($out_file, "$value, ");
	}
	

	fwrite($out_file,")\n");

}

/***********************************************************/
/*************		MAIN 		********************/
/***********************************************************/

pars_params($argc, $argv);
//open files
	if($GLOBALS['input'])
		$in = file_get_contents($GLOBALS['input_file']) or exit(2);
	else echo "read from standard input\n";

	if($GLOBALS['output'])
		$out = fopen($GLOBALS['output_file'], "w") or exit(3);
	else echo "print on standard output\n";

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
states_check($in);
alph_check($in);
rules_check($in);
initstate_check($in);
finstates_check($in);

//Call function for output
if($GLOBALS['e']) echo "zmaz epsilon prechody!\n";
else if($GLOBALS['d']) echo "vykonaj determinizaciu\n";
else normal_form($out);	//output in normal form

//close file
fclose($out);

?>
