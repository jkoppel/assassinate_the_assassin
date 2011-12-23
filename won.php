<?php
  header('P3P: CP="CAO PSA OUR"');
  require_once('./client/facebook.php');
  require_once('config.php');
	$facebook = new Facebook($api_key, $secret);
	$facebook->require_frame();
	$user = $facebook->require_login();
  session_start();
  
  $args = "fb_sig_in_iframe={$_GET['fb_sig_in_iframe']}&fb_sig_in_iframe={$_GET['fb_sig_in_iframe']}&fb_sig_time={$_GET['fb_sig_time']}&fb_sig_added={$_GET['fb_sig_added']}&fb_sig_user={$_GET['fb_sig_user']}&fb_sig_profile_update_time={$_GET['fb_sig_profile_update_time']}&fb_sig_session_key={$_GET['fb_sig_session_key']}&fb_sig_expires={$_GET['fb_sig_expires']}&fb_sig_api_key={$_GET['fb_sig_api_key']}&fb_sig={$_GET['fb_sig']}";

	function get_db_conn()
	{
		$conn = mysql_connect($GLOBALS['db_ip'], $GLOBALS['db_user'], $GLOBALS['db_pass']);
		mysql_select_db($GLOBALS['db_name'], $conn);
		return $conn;
	}
  
  function get_name($uid)
  {
    global $facebook;
    
    $info = $facebook->api_client->fql_query("SELECT name FROM user WHERE uid = $uid");
    return $info[0]['name'];
  }
  
  $conn = get_db_conn();
  if(!isset($_SESSION['game_id']) || !isset($_SESSION['user']))
  {
    header("Location: http://yvonnelbaenre.110mb.com/manage.php?$args");
    exit();
  }
  
  $data = mysql_fetch_assoc(mysql_query(
          "SELECT winner,gameover,p1id,p2id FROM assassinatetheassassin WHERE ".
          "gameid={$_SESSION['game_id']}",$conn));
  $player = ($_SESSION['user'] == $data['p1id']) ? 1 : 2;
  
  session_destroy();
  if($player == $data['winner'])
  {
    $opp_name = get_name($data['p'.(3-$player).'id']);
    $message = "";
    if($data['gameover']=="sword")
    {
      $message = "Congratulations! You slashed up $opp_name real good!";
    }
    else if($data['gameover']=="dagger")
    {
      $message = "Congratulations! You backstabbed $opp_name like a real pro!";
    }
    else
    {
      $message = "Congratulations! Your sucessful assassination of $opp_name proves that your knives are truly something to fear!";
    }
    $message .= "<br/><br/>As a result of your accomplishments, you have been inducted into the Hashashin!";
  }
  else
  {
    $opp_name = get_name($data['p'.(3-$player).'id']);
    $message = "";
    if($data['gameover']=="sword")
    {
      $message = "Ouch! $opp_name caught you off guard with his sword.";
    }
    else if($data['gameover']=="dagger")
    {
      $message = "Ouch! $opp_name sneaked up on you with his dagger.";
    }
    else
    {
      $message = "Ouch! $opp_name managed to get a throwing knife into you.";
    }
    $message .= "<br/><br/>Sorry you didn't make it into the Hashashin. Better luck next time!";    
  }
  ?>
<html>
<title>Game over</title>
</head>
<body>
<?=$message?>
</body>
</html>