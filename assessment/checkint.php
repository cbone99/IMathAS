<html>
<body>
<form action="checkint.php" method=post>
<textarea name=txt cols=80 rows=10><?php $cleaned = stripslashes($_POST['txt']); echo $cleaned;?></textarea>
<BR>
<input type=submit value=submit>
</form>
<?php
	//just a development testing program, to test question interpreter
	$mathfuncs = array("sin","cos","tan","sinh","cosh","arcsin","arccos","arctan","arcsinh","arccosh","sqrt","ceil","floor","round","log","ln","abs","max","min","count");
	$allowedmacros = $mathfuncs;
	require('interpret.php');
	require("macros.php");
	if (isset($_POST['txt'])) {
		echo "Post: $cleaned<BR>\n";
		$res = interpret('answer','numfunc',$cleaned);
		echo str_replace("\n","<BR>",$res);
		//eval("\$res = {$_POST['txt']};");
		//echo "$res<BR>\n";
	}
?>
</body>
</html>
