<?php
//IMathAS:  Frontend of testing engine - manages administration of assessments
//(c) 2006 David Lippman

	require("../validate.php");
	if (!isset($sessiondata['sessiontestid']) && !isset($teacherid) && !isset($studentid)) {
		echo "<html><body>";

		echo "You are not authorized to view this page.  If you are trying to reaccess a test you've already ";
		echo "started, access it from the course page</body></html>\n";
		exit;
	}
	include("displayq2.php");
	//error_reporting(0);  //prevents output of error messages
	
	//check to see if test starting test or returning to test
	if (isset($_GET['id'])) {
		//check dates, determine if review
		$aid = $_GET['id'];
		$isreview = false;
		
		$query = "SELECT deffeedback,startdate,enddate,reviewdate,shuffle,itemorder,password FROM imas_assessments WHERE id='$aid'";
		$result = mysql_query($query) or die("Query failed : $query: " . mysql_error());
		$adata = mysql_fetch_array($result, MYSQL_ASSOC);
		$now = time();
		
		if ($now < $adata['startdate'] || $adata['enddate']<$now) { //outside normal range for test
			$query = "SELECT startdate,enddate FROM imas_exceptions WHERE userid='$userid' AND assessmentid='$aid'";
			$result2 = mysql_query($query) or die("Query failed : " . mysql_error());
			$row = mysql_fetch_row($result2);
			if ($row!=null) {
				if ($now<$row[0] || $row[1]<$now) { //outside exception dates
					if ($now > $adata['startdate'] && $now<$adata['reviewdate']) {
						$isreview = true;
					} else {
						if (!isset($teacherid)) {
							echo "Assessment is closed";
							exit;
						}
					}
				}
			} else { //no exception
				if ($now > $adata['startdate'] && $now<$adata['reviewdate']) {
					$isreview = true;
				} else {
					if (!isset($teacherid)) {
						echo "Assessment is closed";
						exit;
					}
				}
			}
		}
		
		//check for password
		if (trim($adata['password'])!='' && !isset($teacherid)) { //has passwd
			$pwfail = true;
			if (isset($_POST['password'])) {
				if (trim($_POST['password'])==trim($adata['password'])) {
					$pwfail = false;
				} else {
					$out = "<p>Password incorrect.  Try again.<p>";
				}
			} 
			if ($pwfail) {
				require("../header.php");
				echo $out;
				echo "<p>Password required for access.</p>";
				echo "<form method=post action=\"showtest.php?cid={$_GET['cid']}&id={$_GET['id']}\">";
				echo "<p>Password: <input type=text name=\"password\" /></p>";
				echo "<input type=submit value=\"Submit\" />";
				echo "</form>";
				require("../footer.php");
				exit;
			}
		}
		
		$query = "SELECT id FROM imas_assessment_sessions WHERE userid='$userid' AND assessmentid='{$_GET['id']}'";
		$result = mysql_query($query) or die("Query failed : " . mysql_error());
		$line = mysql_fetch_array($result, MYSQL_ASSOC);
		
		if ($line == null) { //starting test
			//get question set
			//$query = "SELECT id FROM imas_questions WHERE assessmentid={$_GET['id']};";
			//$result = mysql_query($query) or die("Query failed : " . mysql_error());
			//while($line = mysql_fetch_array($result, MYSQL_ASSOC)) {
			
			//if ($adata['enddate']<$now && $adata['reviewdate']<$now && !isset($teacherid)) { //past enddate and reviewdate
			//	echo "Assessment is closed.";
			//	exit;
			//} 
			if (trim($adata['itemorder'])=='') {
				echo "No questions in assessment!";
				exit;
			}
			$questions = explode(",",$adata['itemorder']);
			foreach($questions as $k=>$q) {
				if (strpos($q,'~')!==false) {
					$sub = explode('~',$q);
					$questions[$k] = $sub[array_rand($sub,1)];
				}
			}
			if ($adata['shuffle']&1) {shuffle($questions);}
			
			if ($adata['shuffle']&2) { //all questions same random seed
				if ($adata['shuffle']&4) { //all students same seed
					$seeds = array_fill(0,count($questions),$aid);
				} else {
					$seeds = array_fill(0,count($questions),rand(1,9999));
				}
			} else {
				if ($adata['shuffle']&4) { //all students same seed
					for ($i = 0; $i<count($questions);$i++) {
						$seeds[] = $aid + $i;
					}
				} else {
					for ($i = 0; $i<count($questions);$i++) {
						$seeds[] = rand(1,9999);
					}
				}
			}


			$scores = array_fill(0,count($questions),-1);
			$attempts = array_fill(0,count($questions),0);
			$lastanswers = array_fill(0,count($questions),'');
			
			$starttime = time();
			
			if (!isset($questions)) {  //assessment has no questions!
				echo "<html><body>Assessment has no questions!";
				echo "</body></html>\n";
				exit;
			} 
			
			$qlist = implode(",",$questions);
			$seedlist = implode(",",$seeds);
			$scorelist = implode(",",$scores);
			$attemptslist = implode(",",$attempts);
			$lalist = implode("~",$lastanswers);
			
			$bestscorelist = implode(',',$scores);
			$bestattemptslist = implode(',',$attempts);
			$bestseedslist = implode(',',$seeds);
			$bestlalist = implode('~',$lastanswers);
			
			$query = "INSERT INTO imas_assessment_sessions (userid,assessmentid,questions,seeds,scores,attempts,lastanswers,starttime,bestscores,bestattempts,bestseeds,bestlastanswers) ";
			$query .= "VALUES ('$userid','{$_GET['id']}','$qlist','$seedlist','$scorelist','$attemptslist','$lalist',$starttime,'$bestscorelist','$bestattemptslist','$bestseedslist','$bestlalist');";
			$result = mysql_query($query) or die("Query failed : " . mysql_error());
			$sessiondata['sessiontestid'] = mysql_insert_id();
			$sessiondata['isreview'] = $isreview;
			if (isset($teacherid)) {
				$sessiondata['isteacher']=true;
			} else {
				$sessiondata['isteacher']=false;
			}
			$query = "SELECT name FROM imas_courses WHERE id='{$_GET['cid']}'";
			$result = mysql_query($query) or die("Query failed : $query: " . mysql_error());
			$sessiondata['coursename'] = mysql_result($result,0,0);
			writesessiondata();
			session_write_close();
			header("Location: http://" . $_SERVER['HTTP_HOST'] . rtrim(dirname($_SERVER['PHP_SELF']), '/\\') . "/showtest.php");
		} else { //returning to test
			//check if Practice test.  If so, clear existing assessment session
			
			if ($isreview) { //past enddate, before reviewdate
				//clear out test for review.
				$questions = explode(",",$adata['itemorder']);
				if ($line['shuffle']&2) {
					$seeds = array_fill(0,count($questions),rand(1,9999));	
				} else {
					for ($i = 0; $i<count($questions);$i++) {
						$seeds[] = rand(1,9999);
					}
				}
				$scores = array_fill(0,count($questions),-1);
				$attempts = array_fill(0,count($questions),0);
				$lastanswers = array_fill(0,count($questions),'');
				$seedlist = implode(",",$seeds);
				$scorelist = implode(",",$scores);
				$attemptslist = implode(",",$attempts);
				$lalist = implode("~",$lastanswers);
				
				$query = "UPDATE imas_assessment_sessions SET scores='$scorelist',seeds='$seedlist',attempts='$attemptslist',lastanswers='$lalist' WHERE userid='$userid' AND assessmentid='$aid' LIMIT 1";
				mysql_query($query) or die("Query failed : $query: " . mysql_error());
			}
			$deffeedback = explode('-',$adata['deffeedback']);
			//removed: $deffeedback[0] == "Practice" || 
			if ($myrights<6 || isset($teacherid)) {  // is teacher or guest - delete out out assessment session
				$query = "DELETE FROM imas_assessment_sessions WHERE userid='$userid' AND assessmentid='$aid' LIMIT 1";
				$result = mysql_query($query) or die("Query failed : $query: " . mysql_error());
				header("Location: http://" . $_SERVER['HTTP_HOST'] . rtrim(dirname($_SERVER['PHP_SELF']), '/\\') . "/showtest.php?cid={$_GET['cid']}&id=$aid");
				exit;
			}
			//Return to test.
			$sessiondata['sessiontestid'] = $line['id'];
			$sessiondata['isreview'] = $isreview;
			if (isset($teacherid)) {
				$sessiondata['isteacher']=true;
			} else {
				$sessiondata['isteacher']=false;
			}
			$query = "SELECT name FROM imas_courses WHERE id='{$_GET['cid']}'";
			$result = mysql_query($query) or die("Query failed : $query: " . mysql_error());
			$sessiondata['coursename'] = mysql_result($result,0,0);
			writesessiondata();
			session_write_close();
			header("Location: http://" . $_SERVER['HTTP_HOST'] . rtrim(dirname($_SERVER['PHP_SELF']), '/\\') . "/showtest.php");
		}
		exit;
	} else { //already started test
		if (!isset($sessiondata['sessiontestid'])) {
			echo "<html><body>Error.  Access test from course page</body></html>\n";
			exit;
		}
		$testid = addslashes($sessiondata['sessiontestid']);
		$isteacher = $sessiondata['isteacher'];
		$query = "SELECT * FROM imas_assessment_sessions WHERE id='$testid'";
		$result = mysql_query($query) or die("Query failed : $query: " . mysql_error());
		$line = mysql_fetch_array($result, MYSQL_ASSOC);
		$questions = explode(",",$line['questions']);
		$seeds = explode(",",$line['seeds']);
		$scores = explode(",",$line['scores']);
		$attempts = explode(",",$line['attempts']);
		$lastanswers = explode("~",$line['lastanswers']);
		$bestseeds = explode(",",$line['bestseeds']);
		$bestscores = explode(",",$line['bestscores']);
		$bestattempts = explode(",",$line['bestattempts']);
		$bestlastanswers = explode("~",$line['bestlastanswers']);
		$starttime = $line['starttime'];
		
		$query = "SELECT * FROM imas_assessments WHERE id='{$line['assessmentid']}'";
		$result = mysql_query($query) or die("Query failed : $query: " . mysql_error());
		$testsettings = mysql_fetch_array($result, MYSQL_ASSOC);
		list($testsettings['testtype'],$testsettings['showans']) = explode('-',$testsettings['deffeedback']);
		$now = time();
		//check for dates - kick out student if after due date
		if (!$isteacher) {
			if ($now < $testsettings['startdate'] || $testsettings['enddate']<$now) { //outside normal range for test
				$query = "SELECT startdate,enddate FROM imas_exceptions WHERE userid='$userid' AND assessmentid='{$line['assessmentid']}'";
				$result2 = mysql_query($query) or die("Query failed : " . mysql_error());
				$row = mysql_fetch_row($result2);
				if ($row!=null) {
					if ($now<$row[0] || $row[1]<$now) { //outside exception dates
						if ($now > $testsettings['startdate'] && $now<$testsettings['reviewdate']) {
							$isreview = true;
						} else {
							if (!isset($teacherid)) {
								echo "Assessment is closed";
								echo "<br/><a href=\"../course/course.php?cid={$testsettings['courseid']}\">Return to course page</a>";
								exit;
							}
						}
					}
				} else { //no exception
					if ($now > $testsettings['startdate'] && $now<$testsettings['reviewdate']) {
						$isreview = true;
					} else {
						if (!isset($teacherid)) {
							echo "Assessment is closed";
							echo "<br/><a href=\"../course/course.php?cid={$testsettings['courseid']}\">Return to course page</a>";
							exit;
						}
					}
				}
			}
		}
		//$isreview = $sessiondata['isreview'];
		if ($isreview) {
			$testsettings['displaymethod'] = "SkipAround";
			$testsettings['testtype']="Practice";
			$testsettings['defattempts'] = 0;
			$testsettings['defpenalty'] = 0;
			$testsettings['showans'] = '0';
		}
		$allowregen = ($testsettings['testtype']=="Practice" || $testsettings['testtype']=="Homework");
		$showeachscore = ($testsettings['testtype']=="Practice" || $testsettings['testtype']=="AsGo" || $testsettings['testtype']=="Homework");
		$showansduring = (($testsettings['testtype']=="Practice" || $testsettings['testtype']=="Homework") && $testsettings['showans']!='N');
		$noindivscores = ($noindivscores || $testsettings['testtype']=="NoScores");
		
		
		if (isset($_GET['reattempt'])) {
			if ($_GET['reattempt']=="all") {
				$allowed = getallallowedattempts($testsettings['id'],$testsettings['defattempts']);
				for ($i = 0; $i<count($questions); $i++) {
					if ($attempts[$i]<$allowed[$questions[$i]] || $allowed[$questions[$i]]==0) {
						if ($noindivscores || getpts($scores[$i])<getremainingpossible($questions[$i],$testsettings,$attempts[$i])) {
							$scores[$i] = -1;
							if ($testsettings['shuffle']&8) {
								$seeds[$i] = rand(1,9999);
							}
						}
					}
				}
			} else {
				$toclear = $_GET['reattempt'];
				$allowed = getallowedattempts($questions[$toclear],$testsettings['defattempts']);
				if ($attempts[$toclear]<$allowed || $allowed==0) {
					$scores[$toclear] = -1;	
					if ($testsettings['shuffle']&8) {
						$seeds[$toclear] = rand(1,9999);
					}
				}
			}
			$scorelist = implode(",",$scores);
			$seedlist = implode(",",$seeds);
			$query = "UPDATE imas_assessment_sessions SET scores='$scorelist',seeds='$seedlist' WHERE id='$testid' LIMIT 1";
			$result = mysql_query($query) or die("Query failed : $query:" . mysql_error());
		}
		if (isset($_GET['regen']) && $allowregen) {
			srand();
			$toregen = $_GET['regen'];
			$seeds[$toregen] = rand(1,9999);
			$scores[$toregen] = -1;
			$attempts[$toregen] = 0;
			$newla = array();
			$laarr = explode('##',$lastanswers[$toregen]);
			foreach ($laarr as $lael) {
				if ($lael=="ReGen") {
					$newla[] = "ReGen";
				}
			}
			$newla[] = "ReGen";
			$lastanswers[$toregen] = implode('##',$newla);
			$lalist = implode('~',$lastanswers);
			$seedlist = implode(",",$seeds);
			$scorelist = implode(",",$scores);
			$attemptslist = implode(",",$attempts);
			$lalist = addslashes(stripslashes($lalist));
			$query = "UPDATE imas_assessment_sessions SET seeds='$seedlist',scores='$scorelist',attempts='$attemptslist',lastanswers='$lalist' WHERE id='$testid' LIMIT 1";
			mysql_query($query) or die("Query failed : $query:" . mysql_error());
		}
		if (isset($_GET['regenall']) && $allowregen) {
			srand();
			if ($_GET['regenall']=="missed") {
				for ($i = 0; $i<count($questions); $i++) {
					if (getpts($scores[$i])<getpointspossible($questions[$i],$testsettings['defpoints'])) {
						$scores[$i] = -1;
						$attempts[$i] = 0;
						$seeds[$i] = rand(1,9999);
						$newla = array();
						$laarr = explode('##',$lastanswers[$i]);
						foreach ($laarr as $lael) {
							if ($lael=="ReGen") {
								$newla[] = "ReGen";
							}
						}
						$newla[] = "ReGen";
						$lastanswers[$i] = implode('##',$newla);
					}
				}
			} else if ($_GET['regenall']=="all") {
				for ($i = 0; $i<count($questions); $i++) {
					$scores[$i] = -1;
					$attempts[$i] = 0;
					$seeds[$i] = rand(1,9999);
					$newla = array();
					$laarr = explode('##',$lastanswers[$i]);
					foreach ($laarr as $lael) {
						if ($lael=="ReGen") {
							$newla[] = "ReGen";
						}
					}
					$newla[] = "ReGen";
					$lastanswers[$i] = implode('##',$newla);	
				}
			} else if ($_GET['regenall']=="fromscratch" && $testsettings['testtype']=="Practice" && !$isreview) {
				$query = "DELETE FROM imas_assessment_sessions WHERE userid='$userid' AND assessmentid='{$testsettings['id']}' LIMIT 1";
				$result = mysql_query($query) or die("Query failed : $query: " . mysql_error());
				header("Location: http://" . $_SERVER['HTTP_HOST'] . rtrim(dirname($_SERVER['PHP_SELF']), '/\\') . "/showtest.php?cid={$testsettings['courseid']}&id={$testsettings['id']}");
				exit;	
			}
			$lalist = implode('~',$lastanswers);
			$seedlist = implode(",",$seeds);
			$scorelist = implode(",",$scores);
			$attemptslist = implode(",",$attempts);
			$lalist = addslashes(stripslashes($lalist));
			$query = "UPDATE imas_assessment_sessions SET seeds='$seedlist',scores='$scorelist',attempts='$attemptslist',lastanswers='$lalist' WHERE id='$testid' LIMIT 1";
			mysql_query($query) or die("Query failed : $query:" . mysql_error());
			 	
		}
			
	}
	
	$isdiag = isset($sessiondata['isdiag']);
	if ($isdiag) {
		$diagid = $sessiondata['isdiag'];
	}

	header("Cache-Control: no-cache, must-revalidate"); // HTTP/1.1
	header("Expires: Mon, 26 Jul 1997 05:00:00 GMT"); // Date in the past
	require("header.php");
	
	if (isset($_GET['recordgrp'])) {
		$query = "SELECT assessmentid,questions,seeds,scores,attempts,lastanswers,starttime,endtime,bestseeds,bestattempts,bestscores,bestlastanswers ";
		$query .= "FROM imas_assessment_sessions WHERE id='$testid'";
		$result = mysql_query($query) or die("Query failed : $query:" . mysql_error());
		$row = mysql_fetch_row($result);
		$insrow = "'".implode("','",$row)."'";
		$errcnt = 0;
		for ($i=1;$i<7;$i++) {
			if ($_POST['user'.$i]!=0) {
				$md5pw = md5($_POST['pw'.$i]);
				$query = "SELECT password,LastName,FirstName FROM imas_users WHERE id='{$_POST['user'.$i]}'";
				$result = mysql_query($query) or die("Query failed : $query:" . mysql_error());
				$thisusername = mysql_result($result,0,2) . ' ' . mysql_result($result,0,1);
				if (mysql_result($result,0,0)!=$md5pw) {
					echo "<p>$thisusername: password incorrect</p>";
					$errcnt++;
				} else {
					$thisuser = mysql_result($result,0,0);
					$query = "SELECT id FROM imas_assessment_sessions WHERE userid='{$_POST['user'.$i]}' AND assessmentid={$testsettings['id']}";
					$result = mysql_query($query) or die("Query failed : $query:" . mysql_error());
					if (mysql_num_rows($result)>0) {
						echo "<p>$thisusername already has a score for this assessment.  No change made</p>";
					} else {
						$query = "INSERT INTO imas_assessment_sessions (userid,assessmentid,questions,seeds,scores,attempts,lastanswers,starttime,endtime,bestseeds,bestattempts,bestscores,bestlastanswers) ";
						$query .= "VALUES ('{$_POST['user'.$i]}',$insrow)";
						mysql_query($query) or die("Query failed : $query:" . mysql_error());
						echo "<p>Score for $thisusername recorded.</p>";
					}
				}
			}
		}
		if ($errcnt>0) {
			$selops = '<option value="0">Select a name..</option>';
			$query = "SELECT imas_users.id,imas_users.FirstName,imas_users.LastName FROM imas_users,imas_students ";
			$query .= "WHERE imas_users.id=imas_students.userid AND imas_students.courseid='{$testsettings['courseid']}' ORDER BY imas_users.LastName,imas_users.FirstName";
			$result = mysql_query($query) or die("Query failed : $query;  " . mysql_error());
			while ($row = mysql_fetch_row($result)) {
				$selops .= "<option value=\"{$row[0]}\">{$row[2]}, {$row[1]}</option>";
			}
			echo '<h4>Retry Record grade for other group members</h4>';
			echo '<p>Each group member (other than the currently logged in student) whose password was incorrect should select their name and enter their password.</p>';
			echo '<form method=post action="showtest.php?recordgrp=1">';
			echo 'Username: <select name="user1">'.$selops.'</select> Password: <input type=password name="pw1" /> <br />';
			echo 'Username: <select name="user2">'.$selops.'</select> Password: <input type=password name="pw2" /> <br />';
			echo 'Username: <select name="user3">'.$selops.'</select> Password: <input type=password name="pw3" /> <br />';
			echo 'Username: <select name="user4">'.$selops.'</select> Password: <input type=password name="pw4" /> <br />';
			echo 'Username: <select name="user5">'.$selops.'</select> Password: <input type=password name="pw5" /> <br />';
			echo 'Username: <select name="user6">'.$selops.'</select> Password: <input type=password name="pw6" /> <br />';
			echo '<input type=submit value="Record Grade for group members"/>';
			echo '</form>';
			
		}
						
		echo "<p><a href=\"../course/course.php?cid={$testsettings['courseid']}\">Return to course page.</a></p>";
		require("../footer.php");
		exit;
	}
	
	if (!$isdiag) {
		echo "<div class=breadcrumb><a href=\"../index.php\">Home</a> &gt; <a href=\"../course/course.php?cid={$testsettings['courseid']}\">{$sessiondata['coursename']}</a> ";
	 echo "&gt; Assessment</div>";
	}
	echo "<h2>{$testsettings['name']}</h2>\n";
	
	if ($testsettings['testtype']=="Practice" && !$isreview) {
		echo "<div class=right><span style=\"color:#f00\">Practice Test.</span>  <a href=\"showtest.php?regenall=fromscratch\">Create new version.</a></div>";
	}
	if ($testsettings['timelimit']>0 && !$isreview) {
		$now = time();
		$remaining = $testsettings['timelimit']*60-($now - $starttime);
		if ($testsettings['timelimit']>60) {
			$tlhrs = floor($testsettings['timelimit']/60);
			$tlmin = $testsettings['timelimit'] % 60;
			$tlwrds = "$tlhrs hour";
			if ($tlhrs > 1) { $tlwrds .= "s";}
			if ($tlmin > 0) { $tlwrds .= ", $tlmin minute";}
			if ($tlmin > 1) { $tlwrds .= "s";}
		} else {
			$tlwrds = $testsettings['timelimit'] . " minute(s)";
		}
		if ($remaining < 0) {
			echo "<div class=right>Timelimit: $tlwrds.  Time Expired</div>\n";
		} else {
		if ($remaining > 3600) {
			$hours = floor($remaining/3600);
			$remaining = $remaining - 3600*$hours;
		} else { $hours = 0;}
		if ($remaining > 60) {
			$minutes = floor($remaining/60);
			$remaining = $remaining - 60*$minutes;
		} else {$minutes=0;}
		$seconds = $remaining;
		echo "<div class=right>Timelimit: $tlwrds. <span id=timeremaining>$hours:$minutes:$seconds</span> remaining</div>\n";
		echo "<script type=\"text/javascript\">\n";
		echo " hours = $hours; minutes = $minutes; seconds = $seconds; done=false;\n";
		echo " function updatetime() {\n";
		echo "	  seconds--;\n";
		echo "    if (seconds==0 && minutes==0 && hours==0) {done=true; alert(\"Time Limit has elapsed\");}\n";
		echo "    if (seconds==0 && minutes==5 && hours==0) {document.getElementById('timeremaining').style.color=\"#f00\";}\n";
		echo "    if (seconds < 0) { seconds=59; minutes--; }\n";
		echo "    if (minutes < 0) { minutes=59; hours--;}\n";
		echo "	  str = '';\n";
		echo "	  if (hours > 0) { str += hours + ':';}\n";
		echo "    if (hours > 0 && minutes <10) { str += '0';}\n";
		echo "	  if (minutes >0) {str += minutes + ':';}\n";
		echo "    if (seconds<10) { str += '0';}\n";
		echo "	  str += seconds + '';\n";
		echo "	  document.getElementById('timeremaining').innerHTML = str;\n";
		echo "    if (!done) {setTimeout(\"updatetime()\",1000);}\n";
		echo " }\n";
		echo " updatetime();\n";
		echo "</script>\n";
		}
	} else if ($isreview) {
		echo "<div class=right style=\"color:#f00\">In Review Mode - no scores will be saved<br/><a href=\"showtest.php?regenall=all\">Create new versions of all questions.</a></div>\n";	
	} else {
		echo "<div class=right>No time limit</div>\n";
	}
	if ($_GET['action']=="skip") {
		echo "<div class=right><span onclick=\"document.getElementById('intro').className='intro';\"><a href=\"#\">Show Instructions</a></span></div>\n";
	}
	if (isset($_GET['action'])) {
		
		if ($_GET['action']=="scoreall") {
			//score test
			for ($i=0; $i < count($questions); $i++) {
				if (isset($_POST["qn$i"]) || isset($_POST['qn'.(1000*($i+1))]) || isset($_POST["qn$i-0"]) || isset($_POST['qn'.(1000*($i+1)).'-0'])) {
					list($qsetid,$cat) = getqsetid($questions[$i]);
					$scores[$i] = getpointsafterpenalty(scoreq($i,$qsetid,$seeds[$i],$_POST["qn$i"]),$questions[$i],$testsettings,$attempts[$i]);
					$attempts[$i]++;
				}
			}
			//record scores
			if (!$isreview) {
				for ($i=0;$i<count($questions); $i++) {
					if (getpts($scores[$i])>getpts($bestscores[$i])) {
						$bestseeds[$i] = $seeds[$i];
						$bestscores[$i] = $scores[$i];
						$bestattempts[$i] = $attempts[$i];
						$bestlastanswers[$i] = $lastanswers[$i];
					}
				}
			}
			$bestscorelist = implode(',',$bestscores);
			$bestattemptslist = implode(',',$bestattempts);
			$bestseedslist = implode(',',$bestseeds);
			$bestlalist = implode('~',$bestlastanswers);
			$bestlalist = addslashes(stripslashes($bestlalist));
			
			$scorelist = implode(",",$scores);
			$attemptslist = implode(",",$attempts);
			$lalist = implode("~",$lastanswers);
			$lalist = addslashes(stripslashes($lalist));
			$now = time();
			if (isset($_POST['saveforlater'])) {
				$query = "UPDATE imas_assessment_sessions SET lastanswers='$lalist' WHERE id='$testid' LIMIT 1";
				mysql_query($query) or die("Query failed : " . mysql_error());
				echo "<p>Answers saved, but not submitted for grading.  You may continue with the test, or ";
				echo "come back to it later. ";
				if ($testsettings['timelimit']>0) {echo "The timelimit will continue to count down";}
				echo "</p><p><a href=\"showtest.php\">Return to test</a> or ";
				if (!$isdiag) {
					echo "<a href=\"../course/course.php?cid={$testsettings['courseid']}\">Return to Course Page</a></p>\n";
				} else {
					echo "<a href=\"../diag/index.php?id=$diagid\">Return to Diagnostics Page</a></p>\n";
				}
			} else {
				$query = "UPDATE imas_assessment_sessions SET scores='$scorelist',attempts='$attemptslist',lastanswers='$lalist',";
				$query .= "bestseeds='$bestseedslist',bestattempts='$bestattemptslist',bestscores='$bestscorelist',bestlastanswers='$bestlalist',";
				$query .= "endtime=$now WHERE id='$testid' LIMIT 1";
				$result = mysql_query($query) or die("Query failed : " . mysql_error());
				showscores($scores,$bestscores,$questions,$attempts,$testsettings);
			
				endtest($testsettings);
				if (!$isdiag) {
					echo "<p><A href=\"../course/course.php?cid={$testsettings['courseid']}\">Return to Course Page</a></p>\n";
				} else {
					echo "<p><a href=\"../diag/index.php?id=$diagid\">Return to Diagnostics Page</a></p>\n";
				}
			}
		} else if ($_GET['action']=="shownext") {
			if (isset($_GET['score'])) {
				$last = $_GET['score'];
				list($qsetid,$cat) = getqsetid($questions[$last]);
				$scores[$last] = getpointsafterpenalty(scoreq($last,$qsetid,$seeds[$last],$_POST["qn$last"]),$questions[$last],$testsettings,$attempts[$last]);
				$attempts[$last]++;
				if (getpts($scores[$last])>getpts($bestscores[$last]) && !$isreview) {
					$bestseeds[$last] = $seeds[$last];
					$bestscores[$last] = $scores[$last];
					$bestattempts[$last] = $attempts[$last];
					$bestlastanswers[$last] = $lastanswers[$last];
				}
				//record score
				$bestscorelist = implode(',',$bestscores);
				$bestattemptslist = implode(',',$bestattempts);
				$bestseedslist = implode(',',$bestseeds);
				$bestlalist = implode('~',$bestlastanswers);
				$bestlalist = addslashes(stripslashes($bestlalist));
				
				$scorelist = implode(",",$scores);
				$attemptslist = implode(",",$attempts);
				$lalist = implode("~",$lastanswers);
				$lalist = addslashes(stripslashes($lalist));
				$now = time();
				$query = "UPDATE imas_assessment_sessions SET scores='$scorelist',attempts='$attemptslist',lastanswers='$lalist',";
				$query .= "bestseeds='$bestseedslist',bestattempts='$bestattemptslist',bestscores='$bestscorelist',bestlastanswers='$bestlalist',";
				$query .= "endtime=$now WHERE id='$testid' LIMIT 1";
				$result = mysql_query($query) or die("Query failed : " . mysql_error());
			
				if ($showeachscore) {
					$possible = getpointspossible($questions[$last],$testsettings['defpoints']);
					echo "<p>Previous Question:<br/>Score on last attempt: ";
					printscore($scores[$last],$possible);
					if ($allowregen && !$isreview) {
						echo "<br/>Score in gradebook: ";
						printscore($bestscores[$last],$possible);
					} 
					echo "</p>\n";
					$allowed = getallowedattempts($questions[$last],$testsettings['defattempts']);
					if ($attempts[$last]<$allowed || $allowed==0) {
						if (getpts($scores[$last])<getremainingpossible($questions[$last],$testsettings,$attempts[$last])) {
							echo "<p><a href=\"showtest.php?action=shownext&to=$last&reattempt=$last\">Reattempt last question</a>.  If you do not reattempt now, you will have another chance once you complete the test.</p>\n";
						}
					}
				}
				//working now page not cached
				if ($allowregen) {
					echo "<p><a href=\"showtest.php?action=shownext&to=$last&regen=$last\">Try another similar question</a></p>\n";
				}
				//show next
				unset($toshow);
				for ($i=$last+1;$i<count($questions);$i++) {
					if (unans($scores[$i])) {
						$toshow=$i;
						$done = false;
						break;
					}
				}
				if (!isset($toshow)) { //no more to show
					$done = true;
				} 
			} else if (isset($_GET['to'])) {
				$toshow = addslashes($_GET['to']);
				$done = false;
			}
			
			if (!$done) { //can show next
				echo "<form method=post action=\"showtest.php?action=shownext&score=$toshow\" onsubmit=\"return doonsubmit(this)\">\n";
				list($qsetid,$cat) = getqsetid($questions[$toshow]);
				if ($showansduring && $attempts[$toshow]>=$testsettings['showans']) {$showa = true;} else {$showa=false;}
				displayq($toshow,$qsetid,$seeds[$toshow],$showa,false,(($testsettings['shuffle']&8)==8));
				echo "<div class=review>Points possible: " . getpointspossible($questions[$toshow],$testsettings['defpoints']);
				$allowed = getallowedattempts($questions[$toshow],$testsettings['defattempts']);
				if ($allowed==0) {
					echo "<br/>Unlimited attempts";
				} else {
					echo '<br/>'.($allowed-$attempts[$toshow])." attempts of ".$allowed." remaining.";
				}
				if ($testsettings['showcat']>0 && $cat!='0') {
					echo "  Category: $cat.";
				}
				echo "</div>";
				echo "<input type=submit class=btn value=Continue>\n";
			} else { //are all done
				showscores($scores,$bestscores,$questions,$attempts,$testsettings);
				endtest($testsettings);
				if (!$isdiag) {
					echo "<p><A HREf=\"../course/course.php?cid={$testsettings['courseid']}\">Return to Course Page</a></p>\n";
				} else {
					echo "<p><a href=\"../diag/index.php?id=$diagid\">Return to Diagnostics Page</a></p>\n";
				}
			}
		} else if ($_GET['action']=="skip") {

			if (isset($_GET['score'])) { //score a problem
				$qn = $_GET['score'];
				list($qsetid,$cat) = getqsetid($questions[$qn]);
				$scores[$qn] = getpointsafterpenalty(scoreq($qn,$qsetid,$seeds[$qn],$_POST["qn$qn"]),$questions[$qn],$testsettings,$attempts[$qn]);
				$attempts[$qn]++;
				
				if (getpts($scores[$qn])>getpts($bestscores[$qn]) && !$isreview) {
					$bestseeds[$qn] = $seeds[$qn];
					$bestscores[$qn] = $scores[$qn];
					$bestattempts[$qn] = $attempts[$qn];
					$bestlastanswers[$qn] = $lastanswers[$qn];
				}
				//record score
				$bestscorelist = implode(',',$bestscores);
				$bestattemptslist = implode(',',$bestattempts);
				$bestseedslist = implode(',',$bestseeds);
				$bestlalist = implode('~',$bestlastanswers);
				$bestlalist = addslashes(stripslashes($bestlalist));
				
				$scorelist = implode(",",$scores);
				$attemptslist = implode(",",$attempts);
				$lalist = implode("~",$lastanswers);
				$lalist = addslashes(stripslashes($lalist));
				$now = time();
				$query = "UPDATE imas_assessment_sessions SET scores='$scorelist',attempts='$attemptslist',lastanswers='$lalist',";
				$query .= "bestseeds='$bestseedslist',bestattempts='$bestattemptslist',bestscores='$bestscorelist',bestlastanswers='$bestlalist',";
				$query .= "endtime=$now WHERE id='$testid' LIMIT 1";
				$result = mysql_query($query) or die("Query failed : $query " . mysql_error());
				$lefttodo = shownavbar($questions,$scores,$qn,$testsettings['showcat']);
				
				echo "<div class=inset>\n";
				echo "<a name=\"beginquestions\"></a>\n";
				if ($showeachscore) {
					$possible = getpointspossible($questions[$qn],$testsettings['defpoints']);
					echo "<p>Score on last attempt: ";
					printscore($scores[$qn],$possible);
					echo "</p>\n";
					if ($allowregen && !$isreview) {
						echo "<p>Score in gradebook: ";
						printscore($bestscores[$qn],$possible);
						echo "</p>";
					} 
					$allowed = getallowedattempts($questions[$qn],$testsettings['defattempts']);
					if ($attempts[$qn]<$allowed || $allowed==0) {
						if (getpts($scores[$qn])<getremainingpossible($questions[$qn],$testsettings,$attempts[$qn])) {
							echo "<p><a href=\"showtest.php?action=skip&to=$qn&reattempt=$qn\">Reattempt last question</a></p>\n";
						} 
					}
				}
				if ($allowregen) {
					echo "<p><a href=\"showtest.php?action=skip&to=$qn&regen=$qn\">Try another similar question</a></p>\n";
				}
				if ($lefttodo > 0) {
					echo "Question scored.  <b>Select another question</b>";
					echo "<p>or click <a href=\"showtest.php?action=skip&done=true\">here</a> to end and score test now</p>\n";
				} else {
					echo "<a href=\"showtest.php?action=skip&done=true\">Click here to finalize and score test</a>\n";
				}
				echo "</div>\n";
			} else if (isset($_GET['to'])) { //jump to a problem
				$next = $_GET['to'];
				echo filter("<div id=intro class=hidden>{$testsettings['intro']}</div>\n");
				
				$lefttodo = shownavbar($questions,$scores,$next,$testsettings['showcat']);
				if (unans($scores[$next])) {
					echo "<div class=inset>\n";
					echo "<form method=post action=\"showtest.php?action=skip&score=$next\" onsubmit=\"return doonsubmit(this)\">\n";
					echo "<a name=\"beginquestions\"></a>\n";
					list($qsetid,$cat) = getqsetid($questions[$next]);
					if ($showansduring && $attempts[$next]>=$testsettings['showans']) {$showa = true;} else {$showa=false;}
					displayq($next,$qsetid,$seeds[$next],$showa,false,(($testsettings['shuffle']&8)==8));
					echo "<div class=review>Points possible: " . getpointspossible($questions[$next],$testsettings['defpoints']);
					$allowed = getallowedattempts($questions[$next],$testsettings['defattempts']);
					if ($allowed==0) {
						echo "<br/>Unlimited attempts";
					} else {
						echo '<br/>'.($allowed-$attempts[$next])." attempts of ".$allowed." remaining.";
					}
					if ($testsettings['showcat']>0 && $cat!='0') {
						echo "  Category: $cat.";
					}
					echo "</div>";
					echo "<input type=submit class=btn value=Submit>\n";
					echo "</div>\n";
					echo "</form>\n";
				} else {
					echo "<div class=inset>\n";
					echo "<a name=\"beginquestions\"></a>\n";
					echo "You've already done this problem.\n";
					if ($showeachscore) {
						$possible = getpointspossible($questions[$next],$testsettings['defpoints']);
						echo "<p>Score on last attempt: ";
						printscore($scores[$next],$possible);
						echo "</p>\n";
						if ($isreview || $allowregen) {
							echo "<p>Score in gradebook: ";
							printscore($bestscores[$next],$possible);
							echo "</p>";
						} 
						$allowed = getallowedattempts($questions[$next],$testsettings['defattempts']);
						if ($attempts[$next]<$allowed || $allowed==0) {
							if (getpts($scores[$next])<getremainingpossible($questions[$next],$testsettings,$attempts[$next])) {
								echo "<p><a href=\"showtest.php?action=skip&to=$next&reattempt=$next\">Reattempt this question</a></p>\n";
							} 
						}
					}
					if ($allowregen) {
						echo "<p><a href=\"showtest.php?action=skip&to=$next&regen=$next\">Try another similar question</a></p>\n";
					}
					if ($lefttodo == 0) {
						echo "<a href=\"showtest.php?action=skip&done=true\">Click here to finalize and score test</a>\n";
					}
					echo "</div>\n";
				}
			} else if (isset($_GET['done'])) { //are all done

				showscores($scores,$bestscores,$questions,$attempts,$testsettings);
				endtest($testsettings);
				if (!$isdiag) {
					echo "<p><A HREf=\"../course/course.php?cid={$testsettings['courseid']}\">Return to Course Page</a></p>\n";
				} else {
					echo "<p><a href=\"../diag/index.php?id=$diagid\">Return to Diagnostics Page</a></p>\n";
				}
			}
		}
	} else { //starting test display
		$moreattempts = false;
		$canimprove = false;
		$ptsearned = 0;
		$perfectscore = false;
		for ($j=0; $j<count($questions);$j++) {
			$ptsearned += getpts($scores[$j]);
			$allowed[$j] = getallowedattempts($questions[$j],$testsettings['defattempts']);
			if ($attempts[$j]<$allowed[$j] || $allowed[$j]==0) {
				$moreattempts = true;
				if (getpts($scores[$j])<getremainingpossible($questions[$j],$testsettings,$attempts[$j])) {
					$canimprove = true;	
				}
			}

		}
		$testsettings['intro'] .= "<p>Total Points Possible: " . array_sum(getallpointspossible($testsettings['id'],$testsettings['defpoints'],$questions)) . "</p>";
		if ($ptsearned==array_sum(getallpointspossible($testsettings['id'],$testsettings['defpoints'],$questions))) {
			$perfectscore = true;
		} 
		if ($testsettings['displaymethod'] == "AllAtOnce") {
			if ($sessiondata['graphdisp']==0) {
				$testsettings['intro'] = preg_replace('/<embed[^>]*alt="([^"]*)"[^>]*>/',"[$1]", $testsettings['intro']);
			}
			echo filter("<div class=intro>{$testsettings['intro']}</div>\n");
			echo "<form method=post action=\"showtest.php?action=scoreall\" onsubmit=\"return doonsubmit(this,true)\">\n";
			$numdisplayed = 0;
			for ($i = 0; $i < count($questions); $i++) {
				list($qsetid,$cat) = getqsetid($questions[$i]);
				if (unans($scores[$i])) {
					if ($showansduring && $attempts[$i]>=$testsettings['showans']) {$showa = true;} else {$showa=false;}
					displayq($i,$qsetid,$seeds[$i],$showa,false,(($testsettings['shuffle']&8)==8));
					echo "<div class=review>Points possible: " . getpointspossible($questions[$i],$testsettings['defpoints']);
					if ($allowed[$i]==0) {
						echo "<br/>Unlimited attempts";
					} else {
						echo '<br/>'.($allowed[$i]-$attempts[$i])." attempts of ".$allowed[$i]." remaining.";
					}
					if ($testsettings['showcat']>0 && $cat!='0') {
						echo "  Category: $cat.";
					}
					echo "</div>";
					$numdisplayed++;
				}
			}	
			if ($numdisplayed > 0) {
				echo "<BR><input type=submit class=btn value=Submit>\n";
				echo "<input type=submit class=btn name=\"saveforlater\" value=\"Save answers\">\n";
			} else {
				
				if ($moreattempts && $canimprove) {
					if ($noindivscores) {
						echo "<p><a href=\"showtest.php?reattempt=all\">Reattempt test</a> on questions allowed (note: all scores, correct and incorrect, will be cleared)</p>";
					} else {
						echo "<p><a href=\"showtest.php?reattempt=all\">Reattempt test</a> on questions missed where allowed</p>";
					}
					if ($allowregen) {
						echo "<p><a href=\"showtest.php?regenall=missed\">Try similar problems</a> for all questions with less than perfect scores.</p>";
					}
				} else {
					if ($perfectscore) {
						echo "<p>Assessment is complete with perfect score.</p>";
						if ($allowregen) {
							echo "<p><a href=\"showtest.php?regenall=all\">Try similar problems</a> for all questions.</p>";
						}
					} else if ($canimprove) { //no more attempts
						if ($allowregen) {
							echo "<p>No attempts left on current versions of questions.</p>\n";
							echo "<p><a href=\"showtest.php?regenall=missed\">Try similar problems</a> for all questions with less than perfect scores.</p>";
						} else {
							echo "<p>No attempts left on this test</p>\n";
						}
					} else { //more attempts, but can't be improved.
						if ($allowregen) {
							echo "<p>Assessment cannot be improved with current versions of questions.</p>\n";
							echo "<p><a href=\"showtest.php?regenall=missed\">Try similar problems</a> for all questions with less than perfect scores.</p>";
						} else {
							echo "<p>Assessment is complete, and cannot be improved with reattempts.</p>\n";
						}
					}
					if (!$isdiag) {
						echo "<a href=\"../course/course.php?cid={$testsettings['courseid']}\">Return to Course Page</a>\n";
					} else {
						echo "<a href=\"../diag/index.php?id=$diagid\">Return to Diagnostics Page</a>\n";
					}
				}
			}
		} else if ($testsettings['displaymethod'] == "OneByOne") {
			for ($i = 0; $i<count($questions);$i++) {
				if (unans($scores[$i])) {
					break;
				}
			}
			if ($i == count($questions)) {
				if ($moreattempts && $canimprove) {
					if ($noindivscores) {
						echo "<p><a href=\"showtest.php?reattempt=all\">Reattempt test</a> on questions allowed (note: all scores, correct and incorrect, will be cleared)</p>";
					} else {
						echo "<p><a href=\"showtest.php?reattempt=all\">Reattempt test</a> on questions missed where allowed</p>";
					}
					if ($allowregen) {
						echo "<p><a href=\"showtest.php?regenall=missed\">Try similar problems</a> for all questions with less than perfect scores.</p>";
					}
				} else {
					if ($perfectscore) {
						echo "<p>Assessment is complete with perfect score.</p>";
						if ($allowregen) {
							echo "<p><a href=\"showtest.php?regenall=all\">Try similar problems</a> for all questions.</p>";
						}
					} else if ($canimprove) { //no more attempts
						echo "<p>No attempts left on this test</p>\n";	
					}  else { //more attempts, but can't be improved
						if ($allowregen) {
							echo "<p>Assessment cannot be improved with current versions of questions.</p>\n";
							echo "<p><a href=\"showtest.php?regenall=missed\">Try similar problems</a> for all questions with less than perfect scores.</p>";
						} else {
							echo "<p>Assessment is complete, and cannot be improved with reattempts.</p>\n";
						}
					}
					if (!$isdiag) {
						echo "<a href=\"../course/course.php?cid={$testsettings['courseid']}\">Return to Course Page</a>\n";
					} else {
						echo "<a href=\"../diag/index.php?id=$diagid\">Return to Diagnostics Page</a>\n";
					}
				}
			} else {
				echo "<div class=intro>{$testsettings['intro']}</div>\n";
				echo "<form method=post action=\"showtest.php?action=shownext&score=$i\" onsubmit=\"return doonsubmit(this)\">\n";
				list($qsetid,$cat) = getqsetid($questions[$i]);
				if ($showansduring && $attempts[$i]>=$testsettings['showans']) {$showa = true;} else {$showa=false;}
				displayq($i,$qsetid,$seeds[$i],$showa,false,(($testsettings['shuffle']&8)==8));
				echo "<div class=review>Points possible: " . getpointspossible($questions[$i],$testsettings['defpoints']); 
				if ($allowed[$i]==0) {
					echo "<br/>Unlimited attempts";
				} else {
					echo '<br/>'.($allowed[$i]-$attempts[$i])." attempts of ".$allowed[$i]." remaining.";
				}
				if ($testsettings['showcat']>0 && $cat!='0') {
					echo "  Category: $cat.";
				}
				echo "</div>";
				echo "<input type=submit class=btn value=Next>\n";
			}
		} else if ($testsettings['displaymethod'] == "SkipAround") {
			echo "<div class=intro>{$testsettings['intro']}</div>\n";
			
			for ($i = 0; $i<count($questions);$i++) {
				if (unans($scores[$i])) {
					break;
				}
			}
			shownavbar($questions,$scores,$i,$testsettings['showcat']);
			if ($i == count($questions)) {
				if ($moreattempts && $canimprove) {
					echo "<div class=inset><br>\n";
					echo "<a name=\"beginquestions\"></a>\n";
					if ($noindivscores) {
						echo "<p><a href=\"showtest.php?reattempt=all\">Reattempt test</a> on questions allowed (note: all scores, correct and incorrect, will be cleared)</p>";
					} else {
						echo "<p><a href=\"showtest.php?reattempt=all\">Reattempt test</a> on questions missed where allowed</p>";
					}
					if ($allowregen) {
						echo "<p>To try a similar problem, select a question</p>";	
					}
					echo "</div>\n";
				} else {
					echo "<div class=inset>";
					echo "<a name=\"beginquestions\"></a>\n";
					
					if ($perfectscore) {
						echo "<p>Assessment is complete with perfect score.</p>";
						echo "<p>To try a similar problem, select a question.</p>";
					} else if ($canimprove) { //no more attempts
						if ($allowregen) {
							echo "<p>No attempts left on current versions of questions.</p>\n";
							echo "<p>To try a similar problem, select a question.</p>";
						} else {
							echo "<p>No attempts left on this test.</p>\n";
						}
					} else { //more attempts, but cannot be improved
						if ($allowregen) {
							echo "<p>Assessment cannot be improved with current versions of questions.</p>\n";
							echo "<p>To try a similar problem, select a question.</p>";
						} else {
							echo "<p>Assessment is complete, and cannot be improved with reattempts.</p>\n";
						}
					}
					
					if (!$isdiag) {
						echo "<a href=\"../course/course.php?cid={$testsettings['courseid']}\">Return to Course Page</a></div>\n";
					} else {
						echo "<a href=\"../diag/index.php?id=$diagid\">Return to Diagnostics Page</a></div>\n";
					}
				}
			} else {
				echo "<form method=post action=\"showtest.php?action=skip&score=$i\" onsubmit=\"return doonsubmit(this)\">\n";
				echo "<div class=inset>\n";
				echo "<a name=\"beginquestions\"></a>\n";
				list($qsetid,$cat) = getqsetid($questions[$i]);
				if ($showansduring && $attempts[$i]>=$testsettings['showans']) {$showa = true;} else {$showa=false;}
				displayq($i,$qsetid,$seeds[$i],$showa,false,(($testsettings['shuffle']&8)==8));
				echo "<div class=review>Points possible: " . getpointspossible($questions[$i],$testsettings['defpoints']); 
				if ($allowed[$i]==0) {
					echo "<br/>Unlimited attempts";
				} else {
					echo '<br/>'.($allowed[$i]-$attempts[$i])." attempts of ".$allowed[$i]." remaining.";
				}
				if ($testsettings['showcat']>0 && $cat!='0') {
					echo "  Category: $cat.";
				}
				echo "</div>";
				echo "<input type=submit class=btn value=Submit>\n";
				echo "</div>\n";
				echo "</form>\n";
			}
		}
	}
	require("../footer.php");
	
	function shownavbar($questions,$scores,$current,$showcat) {
		global $imasroot,$isdiag;
		$todo = 0;
		if ($showcat>1) {
			$qslist = "'".implode("','",$questions)."'";
			$query = "SELECT imas_questions.id,imas_questions.category,imas_libraries.name FROM imas_questions ";
			$query .= "LEFT JOIN imas_libraries ON imas_questions.category=imas_libraries.id WHERE imas_questions.id IN ($qslist)";
			$result = mysql_query($query) or die("Query failed : $query " . mysql_error());
			while ($row = mysql_fetch_row($result)) {
				if ($row[2]==null) {
					$cats[$row[0]] = $row[1];
				} else {
					$cats[$row[0]] = $row[2];
				}
			}
		}
		echo "<a href=\"#beginquestions\"><img class=skipnav src=\"$imasroot/img/blank.gif\" alt=\"Skip Navigation\" /></a>\n";
		echo "<div class=navbar>";
		echo "<h4>Questions</h4>\n";
		echo "<ul class=qlist>\n";
		for ($i = 0; $i < count($questions); $i++) {
			echo "<li>";
			if ($current == $i) { echo "<span class=current>";}
			if (unans($scores[$i])) {
				$todo++;
				if ($showcat>1 && $cats[$questions[$i]]!='0') {
					echo "<a href=\"showtest.php?action=skip&to=$i\">". ($i+1) . ") {$cats[$questions[$i]]}</a>";
				} else {
					echo "<a href=\"showtest.php?action=skip&to=$i\">Question ". ($i+1) . "</a>";
				}
			} else {
				//echo "Question ". ($i+1);
				if ($showcat>1 && $cats[$questions[$i]]!='0') {
					echo "<span class=done><a href=\"showtest.php?action=skip&to=$i\">". ($i+1) . ") {$cats[$questions[$i]]}</a></span>";
				} else {
					echo "<span class=done><a href=\"showtest.php?action=skip&to=$i\">Question ". ($i+1) . "</a></span>";
				}
			}
			if ($current == $i) { echo "</span>";}
			echo "</li>\n";
		}
		echo "</ul>";
		if (!$isdiag) {
			echo "<p><a href=\"#\" onclick=\"window.open('$imasroot/assessment/printtest.php','printver','width=400,height=300,menubar=1,scrollbars=1,resizable=1,status=1,top=20,left='+(screen.width-420))\">Print Version</a></p> ";
		}

		echo "</div>\n";
		return $todo;
	}
	
	function showscores($scores,$bestscores,$questions,$attempts,$testsettings) {
		global $isdiag,$allowregen,$isreview,$noindivscores;
		if ($isdiag) {
			global $userid;
			$query = "SELECT * from imas_users WHERE id='$userid'";
			$result = mysql_query($query) or die("Query failed : " . mysql_error());
			$userinfo = mysql_fetch_array($result, MYSQL_ASSOC);
			echo "<h3>{$userinfo['LastName']}, {$userinfo['FirstName']}: ";
			echo substr($userinfo['SID'],0,strpos($userinfo['SID'],'d'));
			echo "</h3>\n";
		}
		
		echo "<h3>Scores:</h3>\n";
		
		$possible = getallpointspossible($testsettings['id'],$testsettings['defpoints'],$questions);
		if (!$noindivscores) {
			echo "<table class=scores>";
			for ($i=0;$i < count($scores);$i++) {
				echo "<tr><td>";
				if ($bestscores[$i] == -1) {
					$bestscores[$i] = 0;
				}
				if ($scores[$i] == -1) {
					$scores[$i] = 0;
					echo 'Question '. ($i+1) . ': </td><td>';
					if ($isreview || $allowregen) {
						echo "Last attempt: ";
					}
					echo "Not answered";
					echo "</td>";
					if ($isreview || $allowregen) {
						echo "<td>  Score in gradebook: ";
						printscore($bestscores[$i],$possible[$questions[$i]]);
						echo "</td>";
					}
					echo "</tr>\n";
				} else {
					echo 'Question '. ($i+1) . ': </td><td>';
					if ($isreview || $allowregen) {
						echo "Last attempt: ";
					}
					printscore($scores[$i],$possible[$questions[$i]]);
					echo "</td>";
					if ($isreview || $allowregen) {
						echo "<td>  Score in Gradebook: ";
						printscore($bestscores[$i],$possible[$questions[$i]]);
						echo "</td>";
					}
					echo "</tr>\n";
				}
			}
			echo "</table>";
		}
		global $testid;
		$scorelist = implode(",",$scores);
		$bestscorelist = implode(",",$bestscores);
		$query = "UPDATE imas_assessment_sessions SET scores='$scorelist',bestscores='$bestscorelist' WHERE id='$testid' LIMIT 1";
		$result = mysql_query($query) or die("Query failed : " . mysql_error());
			
		if ($testsettings['testtype']!="NoScores") {
			$total = 0;
			$lastattempttotal = 0;
			for ($i =0; $i < count($bestscores);$i++) {
				if (getpts($bestscores[$i])>0) { $total += getpts($bestscores[$i]);}
				if (getpts($scores[$i])>0) { $lastattempttotal += getpts($scores[$i]);}
			}
			$totpossible = array_sum($possible);
			
			if ($allowregen || $isreview) {
				echo "<p>Total Points on Last Attempts:  $lastattempttotal out of $totpossible possible</p>\n";
			}
			
			if ($total<$testsettings['minscore']) {
				echo "<p><b>Total Points Earned:  $total out of $totpossible possible: ";	
			} else {
				echo "<p><b>Total Points in Gradebook: $total out of $totpossible possible: ";
			}
			
			$average = round(100*((float)$total)/((float)$totpossible),1);
			echo "$average % </b></p>\n";	
			
			if ($total<$testsettings['minscore']) {
				echo "<p><span style=\"color:red;\"><b>A score of {$testsettings['minscore']} is required to receive credit for this assessment<br/>Grade in Gradebook: No Credit (NC)</span></p> ";	
			}
		} else {
			echo "<p><b>Your scores have been recorded for this assessment.</b></p>";
		}
		
		//if timelimit is exceeded
		$now = time();
		if (($testsettings['timelimit']>0) && (($now-$GLOBALS['starttime'])/60 > $testsettings['timelimit'])) {
			$over = $now-$GLOBALS['starttime'] - $testsettings['timelimit']*60;
			echo "<p>Time limit exceeded by ";
			if ($over > 60) {
				$overmin = floor($over/60);
				echo "$overmin minutes, ";
				$over = $over - $overmin*60;
			}
			echo "$over seconds.<BR>\n";
			echo "Grade is subject to acceptance by the instructor</p>\n";
		}
		
		
		if ($total < $possible) {
			$numcanredo = 0;
			for ($i = 0; $i<count($questions); $i++) {
				$allowed = getallowedattempts($questions[$i],$testsettings['defattempts']);
				if ($attempts[$i]<$allowed || $allowed==0) {
					if (getpts($scores[$i])<getremainingpossible($questions[$i],$testsettings,$attempts[$i])) {
						$numcanredo++;	
					}
				}
			}
			if ($numcanredo > 0) {
				if ($noindivscores) {
					echo "<p><a href=\"showtest.php?reattempt=all\">Reattempt test</a> on questions allowed (note: where reattempts are allowed, all scores, correct and incorrect, will be cleared)</p>";
				} else {
					echo "<p><a href=\"showtest.php?reattempt=all\">Reattempt test</a> on questions missed where allowed</p>";
				}
			}
			if ($allowregen) {
				echo "<p><a href=\"showtest.php?regenall=missed\">Try similar problems</a> for all questions with less than perfect scores.</p>";
				echo "<p><a href=\"showtest.php?regenall=all\">Try similar problems</a> for all questions.</p>";
			}
		}
		if ($testsettings['testtype']!="NoScores") {
			$query = "SELECT COUNT(id) from imas_questions WHERE assessmentid='{$testsettings['id']}' AND category<>'0'";
			$result = mysql_query($query) or die("Query failed : $query;  " . mysql_error());
			if (mysql_result($result,0,0)>0) {
				include("../assessment/catscores.php");
				catscores($questions,$bestscores,$testsettings['defpoints']);
			}
		}
		if ($testsettings['isgroup']==1) {
			$selops = '<option value="0">Select a name..</option>';
			$query = "SELECT imas_users.id,imas_users.FirstName,imas_users.LastName FROM imas_users,imas_students ";
			$query .= "WHERE imas_users.id=imas_students.userid AND imas_students.courseid='{$testsettings['courseid']}' ORDER BY imas_users.LastName,imas_users.FirstName";
			$result = mysql_query($query) or die("Query failed : $query;  " . mysql_error());
			while ($row = mysql_fetch_row($result)) {
				$selops .= "<option value=\"{$row[0]}\">{$row[2]}, {$row[1]}</option>";
			}
			echo '<h4>Record grade for other group members</h4>';
			echo '<p>Each group member (other than the currently logged in student) should select their name and enter their password once this assessment is complete.</p>';
			echo '<form method=post action="showtest.php?recordgrp=1">';
			echo 'Username: <select name="user1">'.$selops.'</select> Password: <input type=password name="pw1" /> <br />';
			echo 'Username: <select name="user2">'.$selops.'</select> Password: <input type=password name="pw2" /> <br />';
			echo 'Username: <select name="user3">'.$selops.'</select> Password: <input type=password name="pw3" /> <br />';
			echo 'Username: <select name="user4">'.$selops.'</select> Password: <input type=password name="pw4" /> <br />';
			echo 'Username: <select name="user5">'.$selops.'</select> Password: <input type=password name="pw5" /> <br />';
			echo 'Username: <select name="user6">'.$selops.'</select> Password: <input type=password name="pw6" /> <br />';
			echo '<input type=submit value="Record Grade for group members"/>';
			echo '</form>';
		}
			
		
	}
	
	function getallpointspossible($aid,$def,$qarr) {
		$qlist = "'".implode("','",$qarr)."'";
		$query = "SELECT id,points FROM imas_questions WHERE assessmentid='$aid' AND id IN ($qlist)" ;
		$result = mysql_query($query) or die("Query failed : $query: " . mysql_error());
		while ($row = mysql_fetch_row($result)) {
		 	if ($row[1] == 9999) {
				$possible[$row[0]] = $def;
			} else {
				$possible[$row[0]] = $row[1];

			}
		}
		return $possible;
	}
	
	function getpointspossible($qn,$def) {
		$query = "SELECT points FROM imas_questions WHERE id='$qn'";
		$result = mysql_query($query) or die("Query failed : $query: " . mysql_error());
		while ($row = mysql_fetch_row($result)) {
		 	if ($row[0] == 9999) {
				$possible = $def;
			} else {
				$possible = $row[0];
			}
		}
		return $possible;
	}
	
	function endtest($testsettings) {
		
		//unset($sessiondata['sessiontestid']);
	}
	
	function getpointsafterpenalty($frac,$qn,$testsettings,$attempts) {
		$query = "SELECT points,penalty,attempts FROM imas_questions WHERE id='$qn'";
		$result = mysql_query($query) or die("Query failed: $query: " . mysql_error());
		$row = mysql_fetch_row($result);
		$points = $row[0];
		if ($points == 9999) { $points = $testsettings['defpoints'];}
		$penalty = $row[1];
		$lastonly = $false;
		if ($penalty{0}==='L') {
			$lastonly = true;
			$penalty = substr($penalty,1);
		}
		if ($penalty == 9999) { 
			$penalty = $testsettings['defpenalty'];
			if ($penalty{0}==='L') {
				$lastonly = true;
				$penalty = substr($penalty,1);
			}
		}
		if ($row[2]==9999) {
			$row[2] = $testsettings['defattempts'];
		}
		
		if ($lastonly && $row[2]>0 && $attempts+1<$row[2]) {
			$penalty = 0;
		} else if ($lastonly && $row[2]>0) {
			$attempts = 1;
		}
		if ($lastonly && $row[2]==1) { //no penalty if only one attempt is allowed!
			$penalty = 0;
		}
		if (strpos($frac,'~')===false) {
			$after = round($frac*$points - $points*$attempts*$penalty/100.0,1);
			if ($after < 0) { $after = 0;}
		} else {
			$fparts = explode('~',$frac);
			foreach ($fparts as $k=>$fpart) {
				$after[$k] = round($fpart*$points*(1 - $attempts*$penalty/100.0),2);
				if ($after[$k]<0) {$after[$k]=0;}
			}
			$after = implode('~',$after);
		}
		return $after;
	}
	/*
	function getremainingpossible($qn,$testsettings,$attempts) {
		$query = "SELECT points,penalty FROM imas_questions WHERE id='$qn'";
		$result = mysql_query($query) or die("Query failed : $query: " . mysql_error());
		$def = $testsettings['defpoints'];
		while ($row = mysql_fetch_row($result)) {
			if ($row[1] == 9999) { $pen = $testsettings['defpenalty'];} else {$pen = $row[1];}
		 	if ($row[0] == 9999) {
				$possible = round($def - $def*$attempts*$pen/100.0,1);
			} else {
				$possible = round($row[0] - $row[0]*$attempts*$pen/100.0,1);
			}
		}
		if ($possible < 0) { $possible = 0;}
		return $possible;
	}
	*/
	function getremainingpossible($qn,$testsettings,$attempts) { 
                $query = "SELECT points,penalty,attempts FROM imas_questions WHERE id='$qn'"; 
                $result = mysql_query($query) or die("Query failed : $query: " . mysql_error()); 
                $def = $testsettings['defpoints']; 
                while ($row = mysql_fetch_row($result)) { 
                        
                        $pen = $row[1]; 
                        $lastonly = $false; 
                        if ($pen{0}==='L') { 
                                $lastonly = true; 
                                $pen= substr($pen,1); 
                        } 
                        if ($pen == 9999) { 
                                $pen = $testsettings['defpenalty']; 
                                if ($pen{0}==='L') { 
                                        $lastonly = true; 
                                        $pen = substr($pen,1); 
                                } 
                        } 
                        if ($row[2]==9999) { 
                                $row[2] = $testsettings['defattempts']; 
                        } 
                        
                        if ($lastonly) {
				$pen = 0;
                        } 
                        
                        //if ($row[1] == 9999) { $pen = $testsettings['defpenalty'];} else {$pen = $row[1];} 
                        if ($row[0] == 9999) { 
                                $possible = round($def - $def*$attempts*$pen/100.0,1); 
                        } else { 
                                $possible = round($row[0] - $row[0]*$attempts*$pen/100.0,1); 
                        } 
                } 
                if ($possible < 0) { $possible = 0;} 
                return $possible; 
        } 


	function getpoints($frac,$qn,$def) {
		$query = "SELECT points FROM imas_questions WHERE id='$qn'";
		$result = mysql_query($query) or die("Query failed: $query: " . mysql_error());
		$points = mysql_result($result,0,0);
		if ($points ==9999) { $points = $def;}
		return round($frac*$points,2);
	}
	
	function getpenalty($qn,$def) {
		$query = "SELECT penalty FROM imas_questions WHERE id='$qn'";
		$result = mysql_query($query) or die("Query failed : $query:" . mysql_error());
		$penalty = mysql_result($result,0,0);
		if ($penalty == 9999) { $penalty = $def;}
		return $penalty;
	}
	
	function getallallowedattempts($aid,$def) {
		$query = "SELECT id,attempts FROM imas_questions WHERE assessmentid='$aid'";
		$result = mysql_query($query) or die("Query failed : $query:" . mysql_error());
		while ($row = mysql_fetch_row($result)) {
			if ($row[1] == 9999) { 
				$attempts[$row[0]] = $def;
			} else {
				$attempts[$row[0]] = $row[1];
			}
		}
		return $attempts;
	}
	
	function getallowedattempts($qn,$def) {
		$query = "SELECT attempts FROM imas_questions WHERE id='$qn'";
		$result = mysql_query($query) or die("Query failed : $query:" . mysql_error());
		$attempts = mysql_result($result,0,0);
		if ($attempts == 9999) { $attempts = $def;}
		return $attempts;
	}
	
	function getpts($sc) {
		if (strpos($sc,'~')===false) {
			return $sc;
		} else {
			$sc = explode('~',$sc);
			$tot = 0;
			foreach ($sc as $s) {
				if ($s>0) { 
					$tot+=$s;
				}
			}
			return round($tot,1);
		}
	}
	
	function unans($sc) {
		if (strpos($sc,'~')===false) {
			return ($sc<0);
		} else {
			return (strpos($sc,'-1'));
		}
	}
	
	function printscore($sc,$poss) {
		if (strpos($sc,'~')===false) {
			echo "$sc out of $poss";
		} else {
			$pts = getpts($sc);
			$sc = str_replace('-1','N/A',$sc);
			$sc = str_replace('~',', ',$sc);
			echo "$pts out of $poss (parts: $sc)";
		}		
	}


?>
