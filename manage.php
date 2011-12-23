<?php
  header('P3P: CP="CAO PSA OUR"');
  require_once('./client/facebook.php');
  require_once('config.php');
	$facebook = new Facebook($api_key, $secret);
	$facebook->require_frame();
	$user = $facebook->require_login();
  session_start();
?>
<html>
<head>
<title>Assassinate the Assassin management screen</title>
</head>
<body>
<blockquote>
<i>
  <p>In this year, 1191 AD, you have sought to join one of the most powerful and fearsome groups of assassin's to ever grace history:
  <b>The Hashashin</b></p>
  <p>Tis' a great honor, a fountain of pride to be deemed capable of joining this elite group. The thrill, the smugness, the glory that comes with acceptance are
  all worth a lifetime's pursuit.</p>

  <p>You have done well, displaying skills far superior for your age than anyone else you've ever met. You must pass one last trial to be accepted into this organization:</p>
</i>
</blockquote>
<b><span style="font-size:x-large; color: rgb(150,0,0);">Assassinate the Assassin!</span></b>
<br/><br/><br/>
<b>Assassinate the Assassin Game Management Screen</b><br/><br/>
<?php
   $args = "fb_sig_in_iframe={$_GET['fb_sig_in_iframe']}&fb_sig_in_iframe={$_GET['fb_sig_in_iframe']}&fb_sig_time={$_GET['fb_sig_time']}&fb_sig_added={$_GET['fb_sig_added']}&fb_sig_user={$_GET['fb_sig_user']}&fb_sig_profile_update_time={$_GET['fb_sig_profile_update_time']}&fb_sig_session_key={$_GET['fb_sig_session_key']}&fb_sig_expires={$_GET['fb_sig_expires']}&fb_sig_api_key={$_GET['fb_sig_api_key']}&fb_sig={$_GET['fb_sig']}";

  function get_db_conn()
	{
		$conn = mysql_connect($GLOBALS['db_ip'], $GLOBALS['db_user'], $GLOBALS['db_pass']);
		mysql_select_db($GLOBALS['db_name'], $conn);
		return $conn;
	}
  
  function get_names($uids)
  {
    global $facebook;
    
    $info = $facebook->api_client->fql_query("SELECT uid,name FROM user WHERE uid IN ($uids)");
    return $info;
  }
  
  $_SESSION['user'] = $user;
  
  $conn = get_db_conn();
  
  $res = mysql_query("SELECT `gameid`,`p1id`,`p2id` FROM assassinatetheassassin WHERE winner=0 AND (`p2id`=$user OR `p1id`=$user)",$conn);
  while($row = mysql_fetch_assoc($res))
  {
    $names_arr = get_names($row['p1id'].",".$row['p2id']);
    echo "Click <a href=\"http://yvonnelbaenre.110mb.com/index.php?gid=" .$row['gameid'] .
        "&$args\">here</a> to enter the game \"". $names_arr[0]['name'] .
            " versus ".$names_arr[1]['name'] ."\"<br/>";
  }
  echo "<form method=\"post\" action=\"http://yvonnelbaenre.110mb.com/index.php?$args\">Start a game with ";
  echo "<select name=\"startagainst\">";
  $friends = $facebook->api_client->friends_get();
  $names = get_names(implode(",",$friends));
  
  foreach($names as $name_arr)
  {
   echo "<option value=\"".$name_arr['uid']."\">".$name_arr['name']."</option>";
  }
  echo "</select>";
  echo "<input type=\"submit\" value=\"Challenge to game!\"/></form>";
?>
<a href="manual.html">Manual</a>
</body>
</html>