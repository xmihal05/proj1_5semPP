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
		;
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
	foreach($all_states as $item){
		$state = preg_split("~,~", $item);
	}

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

	$GLOBALS['states_arr'] = $state;
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
preg_match($fail_text, $in, $error);
if(!empty($error)) exit(40);

//parse input file
states_check($in);
//function to parse vstupnu abecedu (prechody)
//function to parse rules
//get starting state
//get set of finite states

//copy $in file to $out file and close files
fwrite($out, $in);
fclose($out);

?>
