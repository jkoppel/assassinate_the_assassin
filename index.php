<?php

  header('P3P: CP="CAO PSA OUR"');
  
	require_once 'config.php';
	require_once './client/facebook.php';
  
  $interior_wall_gaps = array(0=>array(0=>array(0=>5,1=>4),1=>array(0=>4,1=>4)),
        1=>array(0=>array(0=>10,1=>2),1=>array(0=>9,1=>2)),
        2=>array(0=>array(0=>2,1=>5),1=>array(0=>2,1=>4)),
        3=>array(0=>array(0=>7,1=>5),1=>array(0=>7,1=>4)),
        4=>array(0=>array(0=>10,1=>5),1=>array(0=>10,1=>4)),
        5=>array(0=>array(0=>5,1=>7),1=>array(0=>4,1=>7)),
        6=>array(0=>array(0=>10,1=>5),1=>array(0=>9,1=>5)),
        7=>array(0=>array(0=>4,1=>10),1=>array(0=>4,1=>9)),
        8=>array(0=>array(0=>9,1=>10),1=>array(0=>9,1=>9)),
        9=>array(0=>array(0=>12,1=>10),1=>array(0=>12,1=>9)),
        10=>array(0=>array(0=>5,1=>12),1=>array(0=>4,1=>12)),
        11=>array(0=>array(0=>10,1=>10),1=>array(0=>9,1=>10)));
  $orientations = array(0 => array(0=>0,1=>1),
                                    1 => array(0=>1,1=>0),
                                    2 => array(0=>0,1=>-1),
                                    3 => array(0=>-1,1=>0));
	$conn = get_db_conn();
  $message = "";
	$gamestate = array();
  $args = "fb_sig_in_iframe={$_GET['fb_sig_in_iframe']}&fb_sig_in_iframe={$_GET['fb_sig_in_iframe']}&fb_sig_time={$_GET['fb_sig_time']}&fb_sig_added={$_GET['fb_sig_added']}&fb_sig_user={$_GET['fb_sig_user']}&fb_sig_profile_update_time={$_GET['fb_sig_profile_update_time']}&fb_sig_session_key={$_GET['fb_sig_session_key']}&fb_sig_expires={$_GET['fb_sig_expires']}&fb_sig_api_key={$_GET['fb_sig_api_key']}&fb_sig={$_GET['fb_sig']}";

	$facebook = new Facebook($api_key, $secret);
	//$facebook->require_frame();
	$user = $facebook->require_login();
  
  
	session_start();
  
  if(isset($_POST['startagainst']))
  {
    $content = <<<EOT
    <!--<fb:name uid="{$_SESSION['user']}"/>--> has challenged you to a game of Assassinate the Assassin. First go to
    <a href="http://www.facebook.com/apps/application.php?id=7147650975">the app's page</a> and
    add the app if you have not done so already. To enter this game, go <a href="http://apps.facebook.com/assassinateassassin/">here</a> 
    and click the link to enter the game.
EOT;
    $url=$facebook->api_client->notifications_send("{$_POST['startagainst']}",
      "<fb:name uid=\"{$_SESSION['user']}\"/> has challenged you to a game of Assassinate the Assassin.",$content);
    header("Location: $url?$args");
    new_game($_SESSION['user'],$_POST['startagainst']);
    $exit_content ="<p>If the challengee has yet to install Assassinate the Assassin, you will be redirected to a confirmation screen momentarily.</p><p>Otherwise, go back to <a href=\"http://apps.facebook.com/assassinateassassin/\">the management screen</a> to enter the game youjust created.</p>";
    exit($exit_content);
  }
  else if(isset($_GET['gid']))
  {
    enter_game($_GET['gid']);
    if($_SESSION['user'] != $gamestate['p1id'] && $_SESSION['user'] != $gamestate['p2id'])
    {
      $gamestate = array();
      unset($_SESSION['game_id']);
      die("It is rude to attempt to barge in on other people's tests.<br/>".
        "Wait, are you supposed to be here? If we've gotten your identity confused, why don't you <a href=\"manage.php?$args\">try coming in again?</a>");
    }
  }  

	function get_db_conn()
	{
		$conn = mysql_connect($GLOBALS['db_ip'], $GLOBALS['db_user'], $GLOBALS['db_pass']);
		mysql_select_db($GLOBALS['db_name'], $conn);
		return $conn;
	}
  
  function new_game($p1id,$p2id)
  {
		global $conn;
		$id = mysql_fetch_assoc(mysql_query("SELECT max(gameid) FROM assassinatetheassassin",$conn));   
    $id = $id['max(gameid)'] + 1;
    
		mysql_query("INSERT INTO assassinatetheassassin (`p1id`,`p2id`,`p1actions`) VALUES ($p1id,$p2id,8)",$conn);    
  }
  
  function enter_game($gameid)
  {
    global $conn;
    global $gamestate;
  
		$res = mysql_query("SELECT * FROM assassinatetheassassin WHERE `gameid`=".$gameid,$conn);
		$gamestate = mysql_fetch_assoc($res);
		$_SESSION['game_id'] = $gamestate['gameid'];    
  }
  
  //Detects if weapons hit a wall or their target
  function move_to_kill($x1,$z1,$x2,$z2)
  {
    global $interior_wall_gaps;
    
      $x = $x1;
      $z = $z1;
      while($x != $x2 || $z != $z2)
      {
        $dx = ($x2 == $x1) ? 0 : ($x2-$x1)/abs($x2-$x1);
        $dz = ($z2 == $z1) ? 0 : ($z2-$z1)/abs($z2-$z1);
        $newx = $x + $dx;
        $newz = $z+$dz;
        
        if($newx < 0 || $newz < 0 || $newx >= 15 || $newz >= 15)
        {
          return "wall";
        }
        
        if($newx == $gamestate["p".(3-$player)."x"] && $newz == $gamestate["p".(3-$player)."z"])
        {
          return true;
        }
        
        if(($x % 5 == 0 && $newx % 5 == 4) || ($x % 5 == 4 && $newx % 5 == 0) || ($z % 5 == 0 && $newz % 5 == 4) || 
          ($z % 5 == 4 && $newz % 5 == 0))
        {
          $legal = false;
          foreach($interior_wall_gaps as $gap)
          {
            if($gap == array(0 => array(0 => $x, 1=> $z), 1 => array(0 => $newx, 1=> $newz)))
            {
              $legal = true;
              break;
            }
            if($gap == array(1 => array(0 => $x, 1=> $z), 0 => array(0 => $newx, 1=> $newz)))
            {
              $legal = true;
              break;
            }
          }
          if(!$legal)
          {
            return "wall";
          }
        }
        $x = $newx;
        $z = $newz;
      }
      return false;
  }
  
  function is_facing($x1,$z1,$orient,$x2,$z2,$lateral)
  {
    global $orientations;
    
    if(($orientations[$orient][0] == 0 || ($x2-$x1) / $orientations[$orient][0] > ($lateral ? -1 : 0)) &&
      ($orientations[$orient][1] == 0 || ($z2-$z1)  / $orientations[$orient][1] > ($lateral ? -1 : 0))
    )
    {
      return true;
    }
    return false;
  }
  
  function turn_ended()
  {
    global $gamestate;
    global $player;
  
    if($gamestate["p{$player}actions"] <= 0)
    {
      $gamestate["p".(3-$player)."actions"] += 8;
      if($gamestate["p".(3-$player)."actions"] <= 0)
      {
        $message .= "<br/>Your foe is stunned.";
        $gamestate["p{$player}actions"] += 8;
      }
    }
  }
  
  function spend_actions($actions)
  {
    global $gamestate;
    global $player;
    
    if($gamestate["p{$player}actions"] >= $actions)
    {
      $gamestate["p{$player}actions"] -= $actions;
      return null;
    }
    else if($gamestate["p{$player}actions"] == 0)
    {
      return "You're not in the Hashashin yet. For now, let other people take their turn.";
    }
    else if($gamestate["p{$player}actions"] < 0)
    {
      return "You are stunned.";
    }
    else
    {
      return "You're a little too tired to do that.";
    }
  }
  
  function do_action()
  {
    global $conn;
    global $gamestate;
    global $message;
    global $interior_wall_gaps;
    global $player;
    global $orientations;
  
    if(isset($_POST['dx']) && $_POST['dx'] != ""&&isset($_POST['dz']) && $_POST['dz'] != "")
    {
      $x = $gamestate["p{$player}x"];
      $z = $gamestate["p{$player}z"];
      $newx = $gamestate["p{$player}x"] + $_POST['dx'];
      $newz = $gamestate["p{$player}z"] + $_POST['dz'];
      
      if($newx < 0 || $newz < 0 || $newx >= 15 || $newz >= 15)
      {
        $message = "Ouch! If you keep bumping into walls like that, you'll never get inducted.";
        return;
      }
      
      if($newx == $gamestate["p".(3-$player)."x"] && $newz == $gamestate["p".(3-$player)."z"])
      {
        $message = "You're supposed to kill your opponent, not spoon.";
        return;
      }
      
      if(($x % 5 == 0 && $newx % 5 == 4) || ($x % 5 == 4 && $newx % 5 == 0) || ($z % 5 == 0 && $newz % 5 == 4) || 
        ($z % 5 == 4 && $newz % 5 == 0))
      {
        $legal = false;
        foreach($interior_wall_gaps as $gap)
        {
          if($gap == array(0 => array(0 => $x, 1=> $z), 1 => array(0 => $newx, 1=> $newz)))
          {
            $legal = true;
            break;
          }
          if($gap == array(1 => array(0 => $x, 1=> $z), 0 => array(0 => $newx, 1=> $newz)))
          {
            $legal = true;
            break;
          }
        }
        if(!$legal)
        {
          $message = "Ouch! If you keep bumping into walls like that, you'll never get inducted.";
          return;
        }
      }
     
      if(($action_message = spend_actions(abs($_POST['dx'])+abs($_POST['dz']))) != null)
      {
        $message = $action_message;
        return;
      }
      
      $gamestate["p{$player}x"] = $newx;
      $gamestate["p{$player}z"] = $newz;
      $gamestate["p{$player}preppeddetect"] = 0;
      $gamestate["p{$player}throwingknife"] = 0;
      
      if($newx == $gamestate['p1bombx'] && $newz==$gamestate['p1bombz'])
      {
        $message = "<b>You were hit by a stun bomb!</b>";
        $gamestate["p{$player}actions"] = -16;
        $gamestate['p1bombx'] = -1;
        $gamestate['p1bombz'] = -1;
      }
      else if($newx == $gamestate['p2bombx'] && $newz==$gamestate['p2bombz'])
      {
        $message = "<b>You were hit by a stun bomb!</b>";
        $gamestate["p{$player}actions"] = -16;
        $gamestate['p2bombx'] = -1;
        $gamestate['p2bombz'] = -1;
      }
    }
    else if(isset($_POST['theta']) && $_POST['theta']!="")
    {
      if(($action_message = spend_actions(3)) != null)
      {
        $message = $action_message;
        return;
      }
      $gamestate["p{$player}orient"] = ($gamestate["p{$player}orient"] + $_POST['theta'] + 4) % 4;
      $gamestate["p{$player}preppeddetect"] = 0;
      $gamestate["p{$player}throwingknife"] = 0;
    }
    else if(isset($_POST['other_action']) && $_POST['other_action']=="cloak")
    {
      if($gamestate["p{$player}weap"]==2)
      {
        $message = "Sorry, but it's extremely difficult to maintain a cloak with a sword out.";
        return;
      }
      
      if(($action_message = spend_actions(8)) != null)
      {
        $message = $action_message;
        return;
      }
      
      $gamestate["p{$player}cloaked"] = 1;
      $gamestate["p{$player}preppeddetect"] = 0;
      $gamestate["p{$player}throwingknife"] = 0;
      $message = "Succesfully cloaked.";
    }
    else if(isset($_POST['other_action']) && $_POST['other_action']=="wield_dagger")
    {
      if(($action_message = spend_actions(4)) != null)
      {
        $message = $action_message;
        return;
      }
      
      $gamestate["p{$player}cloaked"] = 0;
      $gamestate["p{$player}weap"] = 1;
      $gamestate["p{$player}preppeddetect"] = 0;
      $gamestate["p{$player}throwingknife"] = 0;
      
      $message = "Dagger wielded.";
    }
    else if(isset($_POST['other_action']) && $_POST['other_action']=="wield_sword")
    {
      if(($action_message = spend_actions(4)) != null)
      {
        $message = $action_message;
        return;
      }
      
      $gamestate["p{$player}cloaked"] = 0;
      $gamestate["p{$player}weap"] = 2;
      $gamestate["p{$player}preppeddetect"] = 0;
      $gamestate["p{$player}throwingknife"] = 0;
      
      $message = "Sword wielded.";
    }
    else if(isset($_POST['other_action']) && $_POST['other_action']=="lay_stun")
    {
      if(($action_message = spend_actions(7)) != null)
      {
        $message = $action_message;
        return;
      }
      
      $gamestate["p{$player}cloaked"] = 0;
      $gamestate["p{$player}preppeddetect"] = 0;
      $gamestate["p{$player}throwingknife"] = 0;
      $gamestate["p{$player}bombx"] = $gamestate["p{$player}x"]+$orientations[$gamestate["p{$player}orient"]][0];
      $gamestate["p{$player}bombz"] = $gamestate["p{$player}z"]+$orientations[$gamestate["p{$player}orient"]][1];
      
      $message = "Laid bomb.";
    }
    else if(isset($_POST['other_action']) && $_POST['other_action']=="prep_amulet")
    {
      if(($action_message = spend_actions(8)) != null)
      {
        $message = $action_message;
        return;
      }
      
      $gamestate["p{$player}cloaked"] = 0;
      $gamestate["p{$player}preppeddetect"] = 1;
      $gamestate["p{$player}throwingknife"] = 0;
      
      $message = "Amulet of Detect Life prepared.";
    }
    else if(isset($_POST['other_action']) && $_POST['other_action']=="detect_life")
    {
      if($gamestate["p{$player}preppeddetect"] != 1)
      {
        $message = "You can't use an unprepared amulet!";
      }
    
      if(($action_message = spend_actions(8)) != null)
      {
        $message = $action_message;
        return;
      }
      
      $gamestate["p{$player}cloaked"] = 0;
      $gamestate["p{$player}preppeddetect"] = 0;
      $gamestate["p{$player}throwingknife"] = 0;
      
      $dx = $gamestate["p".(3-$player)."x"]-$gamestate["p{$player}x"];
      $dz = $gamestate["p".(3-$player)."z"]-$gamestate["p{$player}z"];
      $xdir = ($dx < 0) ? "west" : "east";
      $zdir = ($dz < 0) ? "south" : "north";
      $dx = abs($dx);
      $dz = abs($dz);
      $message = "The one you seek lies $dx paces to the $xdir and $dz paces to the $zdir";
    }
    else if(isset($_POST['other_action']) && $_POST['other_action']=="slash")
    {
      if($gamestate["p{$player}weap"] != 2)
      {
        $message = "You have the wrong weapon out.";
        return;
      }
    
      if(($action_message = spend_actions(4)) != null)
      {
        $message = $action_message;
        return;
      }
      
      $gamestate["p{$player}cloaked"] = 0;
      $gamestate["p{$player}preppeddetect"] = 0;
      $gamestate["p{$player}throwingknife"] = 0;
      
     $x1 = $gamestate["p{$player}x"];
     $z1 = $gamestate["p{$player}z"];
     $x2 = $gamestate["p".(3-$player)."x"];
     $z2 = $gamestate["p".(3-$player)."z"];
      
      if(abs($x2-$x1)+abs($z2-$z1)>2)
      {
        $message="Your foe is safely out of range. Your sword swipes harmlessly through thin air";
      }      
      else if(is_facing($x1,$z1,$gamestate["p{$player}orient"],$x2,$z2,true))
      {
        if(move_to_kill($x1,$z1,$x2,$z2)=="wall")
        {
          $message =  "Your weapon bounces harmlessly off the wall";
        }
        else
        {
          if($gamestate["p".(3-$player)."weap"]==2&&is_facing($x2,$z2,$gamestate["p".(3-$player)."orient"],$x1,$z1,true))
          {
            $message = "Your foe easily parries your blow.";
          }
          else
          {
            end_game("sword");
          }
        }
      }
      else
      {
        $message ="Your sword swipes harmlessly through thin air.";
      }
    }
    else if(isset($_POST['other_action']) && $_POST['other_action']=="backstab")
    {
      if($gamestate["p{$player}weap"] != 1)
      {
        $message = "You have the wrong weapon out.";
        return;
      }
    
      if(($action_message = spend_actions(4)) != null)
      {
        $message = $action_message;
        return;
      }
      
      $gamestate["p{$player}cloaked"] = 0;
      $gamestate["p{$player}preppeddetect"] = 0;
      $gamestate["p{$player}throwingknife"] = 0;
      
     $x1 = $gamestate["p{$player}x"];
     $z1 = $gamestate["p{$player}z"];
     $x2 = $gamestate["p".(3-$player)."x"];
     $z2 = $gamestate["p".(3-$player)."z"];
      
      if(max(abs($x2-$x1),abs($z2-$z1))>1)
      {
        $message="Your foe is safely out of range. Your dagger swishes harmlessly through thin air";
      }      
      else if(is_facing($x1,$z1,$gamestate["p{$player}orient"],$x2,$z2,false))
      {
        if(move_to_kill($x1,$z1,$x2,$z2)=="wall")
        {
          $message =  "Your weapon bounces harmlessly off the wall";
        }
        else
        {
          if(is_facing($x2,$z2,$gamestate["p".(3-$player)."orient"],$x1,$z1,true))
          {
            $message = "Backstabbing is called \"back-stabbing\" for a reason. Your attack barely penetrates the ribcage.";
          }
          else
          {
            end_game("dagger");
          }
        }
      }
      else
      {
        $message = "Your dagger swishes harmlessly through thin air.";
      }
    }
    else if(isset($_POST['other_action']) && $_POST['other_action']=="prepknife")
    {
    
      if(($action_message = spend_actions(4)) != null)
      {
        $message = $action_message;
        return;
      }
      
      $gamestate["p{$player}cloaked"] = 0;
      $gamestate["p{$player}preppeddetect"] = 0;
      
      $gamestate["p{$player}throwingknife"] = 1;
      $gamestate["p{$player}knifex"] = $gamestate["p".(3-$player)."x"];
      $gamestate["p{$player}knifez"] = $gamestate["p".(3-$player)."z"];
      
      $message = "You eagerly whip out a knife and prime yourself to throw it into your opponent's heart.";
    }
    else if(isset($_POST['other_action']) && $_POST['other_action']=="throwknife")
    {
      if($gamestate["p{$player}throwingknife"] != 1)
      {
        $message = "You need to ready your throwing knife first.";
        return;
      }
    
      if(($action_message = spend_actions(8)) != null)
      {
        $message = $action_message;
        return;
      }
      
      $gamestate["p{$player}cloaked"] = 0;
      $gamestate["p{$player}preppeddetect"] = 0;
      $gamestate["p{$player}throwingknife"] = 0;
      
      $x1 = $gamestate["p{$player}x"];
      $z1 = $gamestate["p{$player}z"];
      $x2 = $gamestate["p{$player}knifex"];
      $z2 = $gamestate["p{$player}knifez"];
      
      
        $hit = move_to_kill($x1,$z1,$x2,$z2);
      
        if($hit == "wall")
        {
          $message = "Your weapon bounces harmlessly off the wall.";
        }
        else if(!$hit)
        {
          $message = "Your knife cleanly misses your opponent";
        }
        else
        {
          end_game("knife");
        }
    }
    
    turn_ended();
    
    $query = "UPDATE assassinatetheassassin SET  gameover='{$gamestate['gameover']}'";
    foreach($gamestate as $key => $val)
    {
      if($key=='gameid'||$key=='p1id'||$key=='p2id'||$key=='gameover')
      {
        continue;
      }
      $query .= ",$key=$val ";
    }
    $query .= " WHERE gameid={$gamestate['gameid']}";
    mysql_query($query,$conn);
  }
  
  function end_game($cause)
  {
    global $gamestate;
    global $args;
    global $conn;
    global $player;
    
    $gamestate['gameover'] = $cause;
    $gamestate['winner'] = $player;
    
    $query = "UPDATE assassinatetheassassin SET  gameover='{$gamestate['gameover']}'";
    foreach($gamestate as $key => $val)
    {
      if($key=='gameid'||$key=='p1id'||$key=='p2id'||$key=='gameover')
      {
        continue;
      }
      $query .= ",$key=$val ";
    }
    $query .= " WHERE gameid={$gamestate['gameid']}";
    
    mysql_query($query,$conn);
    header("Location: http://yvonnelbaenre.110mb.com/won.php?$args");
  }

	enter_game($_SESSION['game_id']);
  if($gamestate['winner'] != 0)
  {
    header("Location: http://yvonnelbaenre.110mb.com/won.php?$args");
    exit();
  }
  $player = ($_SESSION['user'] == $gamestate['p1id']) ? 1 : 2;
  
  
  if(isset($_POST['action_taken']) && $_POST['action_taken']=="true")
  {
    do_action();
  }
  else
  {
    if($gamestate['p1actions'] <= 0 && $gamestate['p2actions'] <= 0)
    {
      $message = "You and your foe are stunned.";
      do_action();
    }
  }

?>

<!--
	Copyright 2006 Google Inc.

	Licensed under the Apache License, Version 2.0 (the "License");
	you may not use this file except in compliance with the License.
	You may obtain a copy of the License at

	  http://www.apache.org/licenses/LICENSE-2.0

	Unless required by applicable law or agreed to in writing, software
	distributed under the License is distributed on an "AS IS" BASIS,
	WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
	See the License for the specific language governing permissions and
	limitations under the License.


	This page has been heavily, heavily altered by James Koppel
-->
<html>
<head>
	<title>Assassinate the Assassin</title>
	<!--[if IE]><script type="text/javascript" src="excanvas.js"></script><![endif]-->
  <script type="text/javascript" src="sylvester.js"></script>
	<script type="text/javascript"><!--
		/* -------------------------------------------------------------------- */

		var canvas, ctx;
		var canvasWidth, halfCanvasWidth;
		var canvasHeight, halfCanvasHeight;

		var space;  // 3D Engine
		var scene;  // 3D Scene

		/* -------------------------------------------------------------------- */

		/**
		 * Space is a simple 3D system.
		 *
		 * Y+ = up
		 * Z+ = into screen
		 * X+ = right
		 */
		function Space() {
			this.m = this.createMatrixIdentity();
			this.mStack = [];
		}

		Space.prototype.createMatrixIdentity = function() {
			return [
				[1, 0, 0, 0],
				[0, 1, 0, 0],
				[0, 0, 1, 0],
				[0, 0, 0, 1]
			];
		}

		/**
		 * Multiplies two 4x4 matricies together.
		 */
		Space.prototype.matrixMultiply = function(m1, m2) {
			var result = this.createMatrixIdentity();

			var width = m1[0].length;
			var height = m1.length;

			if (width != m2.length) {
				// error
			}

			for (var x = 0; x < width; x++) {
				for (var y = 0; y < height; y++) {
					var sum = 0;

					for (var z = 0; z < width; z++) {
						sum += m1[y][z] * m2[z][x];
					}

					result[y][x] = sum;
				}
			}

			return result;
		}

		/**
		 * Transforms a coordinate using the current transformation
		 * matrix, then flattens it using the projection matrix.
		 */
		Space.prototype.flatten = function(point) {
			var p = [[point.x, point.y, point.z, 1]];
			var pm = this.matrixMultiply(p, this.m);

			point.tx = pm[0][0];
			point.ty = pm[0][1];
			point.tz = pm[0][2];

			// lazy projection
			point.fx = halfCanvasWidth + (canvasWidth * point.tx / point.tz);
			point.fy = halfCanvasHeight -(canvasWidth * point.ty / point.tz);
		}

		/**
		 * Translate (move) the current transformation matrix
		 */
		Space.prototype.translate = function(x, y, z) {
			var m = [
				[1, 0, 0, 0],
				[0, 1, 0, 0],
				[0, 0, 1, 0],
				[x, y, z, 1]
			];

			this.m = this.matrixMultiply(m, this.m);
		}

		/**
		 * Rotate the current transformation matrix. Rotations are
		 * world-oriented, and occur in y,x,z order.
		 */
		Space.prototype.rotate = function(x, y, z) {
			if (y) {
				var cosY = snapTo(Math.cos(y),0);
				var sinY = snapTo(Math.sin(y),0);
				var rotY = [
					[cosY, 0, sinY, 0],
					[0, 1, 0, 0],
					[-sinY, 0, cosY, 0],
					[0, 0, 0, 1]
				];

				this.m = this.matrixMultiply(this.m, rotY);
			}

			if (x) {
				var cosX = snapTo(Math.cos(x),0);
				var sinX = snapTo(Math.sin(x),0);
				var rotX = [
					[1, 0, 0, 0],
					[0, cosX, -sinX, 0],
					[0, sinX, cosX,0],
					[0, 0, 0, 1]
				];
				this.m = this.matrixMultiply(this.m, rotX);
			}

			if (z) {
				var cosZ = snapTo(Math.cos(z),0);
				var sinZ = snapTo(Math.sin(z),0);
				var rotZ = [
					[cosZ, -sinZ, 0, 0],
					[sinZ, cosZ, 0, 0],
					[0, 0, 1, 0],
					[0, 0, 0, 1]
				];

				this.m = this.matrixMultiply(this.m, rotZ);
			}
		}

		/**
		 * Pushes the current transformation onto the stack
		 */
		Space.prototype.push = function() {
			this.mStack.push(this.m);
			this.m = [
				[this.m[0][0], this.m[0][1], this.m[0][2], this.m[0][3]],
				[this.m[1][0], this.m[1][1], this.m[1][2], this.m[1][3]],
				[this.m[2][0], this.m[2][1], this.m[2][2], this.m[2][3]],
				[this.m[3][0], this.m[3][1], this.m[3][2], this.m[3][3]]
			];
		}

		/**
		 * Pops the end off the transformation stack
		 */
		Space.prototype.pop = function() {
			this.m = this.mStack.pop();
		}

		/* -------------------------------------------------------------------- */

		/**
		 * A 3d coordinate
		 */
		function Point(x, y, z) {
			this.x = x;
			this.y = y;
			this.z = z;

			// Relative to camera coordinates
			this.tx;
			this.ty;
			this.tz;

			// Flattened coordinates
			this.fx;
			this.fy;
		}

		/**
		 * A Shape is made up of polygons
		 */
		function Shape() {
			this.points = [];
			this.polygons = [];
		}

		/**
		 * Draws the shape
		 */
		Shape.prototype.draw = function(drawlist) {
			for (var i = 0; i< this.points.length; i++) {
				space.flatten(this.points[i]);
			}

			for (var i = 0; i< this.polygons.length; i++) {
				var poly = this.polygons[i]; // convenience

				space.flatten(poly.origin);

				// lazy backface culling
				if (poly.normal && this.backface) {
					space.flatten(poly.normal);

					var originDist = Math.pow(poly.origin.tx, 2)
												 + Math.pow(poly.origin.ty, 2)
												 + Math.pow(poly.origin.tz, 2);

					var normalDist = Math.pow(poly.normal.tx, 2)
												 + Math.pow(poly.normal.ty, 2)
												 + Math.pow(poly.normal.tz, 2);

					if(originDist > normalDist) {
						drawlist.push(poly);
					}
				} else {
					drawlist.push(poly);
				}
			}
		}

		/**
		 * A polygon is a connection of points in the shape object. You
		 * should probably try to make them coplanar.
		 */
		function Polygon(points, normal, backface, type, color) {
			this.points = points;

			this.origin = new Point(0, 0, 0);
			for(var i = 0; i < this.points.length; i++) {
				this.origin.x += this.points[i].x;
				this.origin.y += this.points[i].y;
				this.origin.z += this.points[i].z;
			}

			this.origin.x /= this.points.length;
			this.origin.y /= this.points.length;
			this.origin.z /= this.points.length;

			if (normal) {
				this.normal = new Point(this.origin.x + normal.x,
																this.origin.y + normal.y,
																this.origin.z + normal.z);
			} else {
				this.normal = null;
			}

			this.backface = backface;
			this.type = type;
			this.color = color;
		}

		Polygon.SOLID = 0;
		Polygon.WIRE = 1;

		/**
		 * Draws the polygon. Assumes that the points have already been
		 * flattened.
		 */
		Polygon.prototype.draw = function() {
      
      if(!new Number(this.points[0].fx).toString().match(/\d+/))
      {
        return;
      }
      if(!new Number(this.points[0].fy).toString().match(/\d+/))
      {
        return;
      }
      if(!new Number(this.points[1].fx).toString().match(/\d+/))
      {
        return;
      }
      if(!new Number(this.points[1].fy).toString().match(/\d+/))
      {
        return;
      }
      if(!new Number(this.points[2].fx).toString().match(/\d+/))
      {
        return;
      }
      if(!new Number(this.points[2].fy).toString().match(/\d+/))
      {
        return;
      }
      try
      {
          if(!new Number(this.points[3].fx).toString().match(/\d+/))
          {
            return;
          }
          if(!new Number(this.points[3].fy).toString().match(/\d+/))
          {
            return;
          }
          if(this.points[0].tz < 0 && this.points[1].tz < 0 && this.points[2].tz < 0 && (this.points[3]&&this.points[3].tz < 0))
          {
            return;
          }
      }
      catch(e){}
      /*if(this.points[0].fy < 10 || this.points[1].fy < 10 || this.points[2].fy < 10 || this.points[3].fy < 10)
      {
        //alert([this.points[0].tx,this.points[1].tx , this.points[2].tx , this.points[3].tx])
      }*/
    //Cheap workaround
    var vis = false;
    for(var n = 0; n < this.points.length; n++)
    {
      if((this.points[n].fx > -200 && this.points[n].fx < canvasWidth+200) &&  (this.points[n].fy > -200 && this.points[n].fy < canvasHeight+200))
      {
        vis = true;
        break;
      }
    }
    if(!vis)
    {
      return;
    }
      
     /* if(selfIntersectingQuadrilateral(this.points))
      {
        return;
      }*/
      
     //alert([this.points[0].tx,this.points[0].tz,this.points[1].tx,this.points[1].tz,this.points[2].tx,this.points[2].tz,this.points[3].tx,this.points[3].tz])
      
			ctx.beginPath();
			ctx.moveTo(this.points[0].fx, this.points[0].fy);

			for(var i = 0; i < this.points.length; i++) {
				ctx.lineTo(this.points[i].fx, this.points[i].fy);
			}

			ctx.closePath();

			var color = this.color;

			/*
			// Do lighting here
			lightvector = Math.abs(this.normal.x + this.normal.y);
			if(lightvector > 1) {
				lightvector = 1;
			}

			color[0] = (color[0] * lightvector).toString();
			color[1] = (color[1] * lightvector).toString();
			color[2] = (color[2] * lightvector).toString();
			*/

			if (color.length > 3) {
				var style = ["rgba(",
				             color[0], ",",
				             color[1], ",",
				             color[2], ",",
				             color[3], ")"].join("");
			} else {
				var style = ["rgb(",
				             color[0], ",",
				             color[1], ",",
				             color[2], ")"].join("");
			}

			if (this.type == Polygon.SOLID) {
				ctx.fillStyle = style;
				ctx.fill();
			} else if (this.type == Polygon.WIRE) {
				ctx.strokeStyle = style;
				ctx.stroke();
			}
		}

		/* -------------------------------------------------------------------- */

		/**
		 * Scene describes the 3D environment
		 */
		function Scene() {
			this.shapes = {};
			this.camera = new Point(0, 0, 0);
			this.cameraTarget = new Point(0, 0, 0);
			this.cameraRotation = 0;

			this.drawlist = [];
		}

		/**
		 * Draw the world
		 */
		Scene.prototype.draw = function() {
			space.push();

			// Camera transformation
			space.translate(
				-this.camera.x,
				-this.camera.y,
				-this.camera.z
			);

			// Camera rotation
			var xdiff = this.cameraTarget.x - this.camera.x;
			var ydiff = this.cameraTarget.y - this.camera.y;
			var zdiff = this.cameraTarget.z - this.camera.z;

			var xzdist = Math.sqrt(Math.pow(xdiff, 2) + Math.pow(zdiff, 2));

			var xrot = snapTo(-Math.atan2(ydiff, xzdist),0); // up/down rotation
			var yrot =  snapTo(Math.atan2(xdiff, zdiff),0);  // left/right rotation

			space.rotate(xrot, yrot, this.cameraRotation);

			// Drawing
			this.drawlist = [];

			for(var i in this.shapes) {
				this.shapes[i].draw(this.drawlist);
			}

			// Depth sorting (warning: this is only enough to drive this demo - feel
			// free to contribute a better system).
			this.drawlist.sort(function (poly1, poly2) {
				return poly2.origin.tz - poly1.origin.tz;
			});

			for (var i = 0; i < this.drawlist.length; i++) {
				this.drawlist[i].draw();
			}

			space.pop();
		}

		/* -------------------------------------------------------------------- */



    var gameState= {};
    gameState['selfX'] = <?=$gamestate["p{$player}x"]?>;
    gameState['selfZ'] = <?=$gamestate["p{$player}z"]?>;
    gameState['selfOrient'] = <?=$gamestate["p{$player}orient"]?>;
    gameState['selfWeapon'] = <?=$gamestate["p{$player}weap"]?>;
    gameState['selfCloaked'] = <?=$gamestate["p{$player}cloaked"]?>;
    <?php
      if(abs($gamestate["p".(3-$player)."x"] - $gamestate["p{$player}x"]) <= 5 &&
        abs($gamestate["p".(3-$player)."z"] - $gamestate["p{$player}z"]) <= 5 &&
        $gamestate["p".(3-$player)."cloaked"] == 0)
      {
        echo "gameState['otherX']=".$gamestate["p".(3-$player)."x"].";";
        echo "gameState['otherZ']=".$gamestate["p".(3-$player)."z"].";";
        echo "gameState['otherOrient']=".$gamestate["p".(3-$player)."orient"].";";
        echo "gameState['otherWeapon']=".$gamestate["p".(3-$player)."weap"].";";
      }
    ?>
      
		var playerHeight = 15;
		var tileWidth = 15;
    var roomHeight = 30;
    
    var floorColor = [75,0,0];
    var ceilingColor = [0,55,0];
    var wallColor = [54,54,54];
    
    var weaponColor = (gameState['selfCloaked'] == 0) ? [88,79,79] : [88,79,79,200];
    var ownWeaponColor = [88,79,79,160];
    
    var playerHeadColor = [82,78,42];
    var playerTorsoColor = [51,38,38];
    
    var arenaSize = 15;
    var roomSize = 5;		

    var walls = [];

    var minimapRadius = 3;
    var minimapTileWidth = 15;
    var minimapOffset = 25;
      
    var orientations = {
              0 : [0,1],
              1 : [1,0],
              2 : [0,-1],
              3 : [-1,0]
    }
    
    var interiorWallGaps = [[[5,4],[4,4]],[[10,2],[9,2]],[[2,5],[2,4]],[[7,5],[7,4]],[[10,5],[10,4]],[[5,7],[4,7]],[[10,5],[9,5]],[[4,10],[4,9]],
                                    [[9,10],[9,9]],[[12,10],[12,9]],[[5,12],[4,12]],[[10,10],[9,10]]];
                                    
    var interiorWallsWithoutGaps = [];
                                    
    
    var epsilon = 1e-6;
    
    function snapTo(num, val)
    {
      if(Math.abs(num - val) < epsilon)
      {
        return val;
      }
      return num
    }
    
    function Player(x, z, direction)
    {
        this.x = x;
        this.z = z;
        this.dir = direction; //radians
    }
    
    function Dimension(width,height,depth)
    {
      this.width = width;
      this.height = height;
      this.depth = depth;
    }
    
    Dimension.prototype.toVector = function()
    {
      return Vector.create([this.width,this.height,this.depth]);
    }
    
    Vector.prototype.toPoint = function()
    {
      return new Point(this.elements[0],this.elements[1],this.elements[2]);
    }
    
    Point.prototype.toVector = function()
    {
      return $V([this.x,this.y,this.z]);
    }
    
    Point.prototype.toFlattenedVector = function()
    {
      return $V([this.fx,this.fy]);
    }
    
    Math.signum = function(n)
    {
      if(n < 0)
        return -1;
      else if(n > 0)
        return 1;
      else
        return 0;
    }
    
    Array.prototype.equals = function(oth)
    {
      for(var i = 0; i < this.length; i++)
      {
        if(this[i].equals)
        {
          if(!this[i].equals(oth[i]))
            return false;
        }
        else
        {
          if(this[i] != oth[i])
            return false
        }
      }
      return true;
    }
  
  function makeWeapon(playerCenter, playerOrientation, weaponType, backface, type, color)
  {
    var weapCenter = playerCenter.add($V([orientations[playerOrientation][0]*tileWidth/6,0,orientations[playerOrientation][1]*tileWidth/6]));
    weapCenter.elements[1] = playerHeight-playerHeight/4*weaponType;
    var arrangements = [[.5,.5],[-.5,.5],[0,0],[0,0]];
    var p1 = [];
    var p2 = [];
    for(var i = 0; i < arrangements.length; i++)
    {
      if(orientations[playerOrientation][0]==0)
      {
        arrangements[i][1] = arrangements[i][1]/5;
      }
      else
      {
        arrangements[i][0] = arrangements[i][0]/5;
      }
      p1.push(weapCenter.add($V([tileWidth/12*arrangements[i][0],0,tileWidth/12*arrangements[i][1]])));
      p2.push(weapCenter.add($V([-tileWidth/12*arrangements[i][0],0,-tileWidth/12*arrangements[i][1]])));
    }
    var t = weapCenter.add($V([orientations[playerOrientation][0]*tileWidth/8,playerHeight/2*weaponType*1.5,orientations[playerOrientation][1]*tileWidth/8]));
    if(weaponType==1)
    {
      t.elements[1] = playerHeight;
    }
    return eightPointedSolid(p1[0],p1[1],p1[2],p1[3],t.toPoint().toVector(),t.toPoint().toVector(),t.toPoint().toVector(),t.toPoint().toVector(),backface,type,color,2).concat(
          eightPointedSolid(p2[0],p2[1],p2[2],p2[3],t.toPoint().toVector(),t.toPoint().toVector(),t.toPoint().toVector(),t.toPoint().toVector(),backface,type,color,2));
  }
  
  /*p1-----p4
  *| \        |  \
  *|   \      |    \
  *p2--\----p3    \
  *  \    \     \      \
  *    \    \     \      \
  *     \     p5--\-----p8
  *       \  /       \   /
  *         p6------p7
  *Returns as one shape.
  */
   function eightPointedSolid(p1,p2,p3,p4,p5,p6,p7,p8,backface,type,color)
   {
      var shapes = [];
      var arrangements = [[p1,p2,p3,p4],[p1,p2,p6,p5],[p2,p3,p7,p6],[p3,p4,p8,p7],[p4,p1,p5,p8],[p5,p6,p7,p8]];
      
      for(var i = 0; i < arrangements.length; i++)
      {
        var shape = new Shape();
        var p = shape.points;
      
        var arr = arrangements[i];
        
        var a = arr[0];
        var b = arr[1];
        var c = arr[2];
        var d = arr[3];
                        
                  
        p.push(a.toPoint());
        p.push(b.toPoint());
        p.push(c.toPoint());
        p.push(d.toPoint());
        
        shape.polygons.push(new Polygon(
        [p[0],p[1],p[2],p[3]],
        b.subtract(d).cross(c.subtract(d)).toUnitVector(),
        backface,
        type,
        color)
        );
        shapes.push(shape);
      }
      return shapes;
    }
  
   function sphericalApproximation(center, radius, eps, backface, type, color)
   {
    var shapes = [];
    
    var sin = Math.sin;
    var cos = Math.cos;
    var r = radius;
    for(var azimuth = 0; azimuth < Math.PI*2; azimuth+=eps)
    {
      for(var zenith=0; zenith<Math.PI; zenith+=eps)
      {
                var az = azimuth;
                var z = zenith;
                
                var s = new Shape();
                var p = s.points;
                
                var a = $V([r*sin(z)*cos(az),r*sin(z)*sin(az),r*cos(z)]);
                var b = $V([r*sin(z+eps)*cos(az),r*sin(z+eps)*sin(az),r*cos(z+eps)]);
                var c = $V([r*sin(z+eps)*cos(az+eps),r*sin(z+eps)*sin(az+eps),r*cos(z+eps)]);
                var d = $V([r*sin(z)*cos(az+eps),r*sin(z)*sin(az+eps),r*cos(z)]);
                
                a = a.add(center);
                b = b.add(center);
                c = c.add(center);
                d = d.add(center);
                
                
                p[0] = a.toPoint();
                p[1] = b.toPoint();
                p[2] = c.toPoint();
                p[3] = d.toPoint();
                
                s.polygons.push(new Polygon(
                  [p[0],p[1],p[2],p[3]],
                 b.subtract(d).cross(c.subtract(d)).toUnitVector(),
                 backface,
                 type,
                 color)
                );
                
                shapes.push(s);             
      }
    }
    return shapes;
   }
    
    //Start is vector
    //Dir is Vector
    //Dim is Dimension
    //Normal is vector
    function segmentedFlatSurface(start,dir,dim, normal, backface, type, color)
    {
        var rotatedDir = null;
        var rotatedDim;
        var angle = normal.angleFrom(Vector.create([0,1,0]));
        if(angle != 0)
        {
          var axis = Line.create([0,0,0],normal.cross(Vector.create([0,1,0])));
          rotatedDir = dir.rotate(angle,axis);
          rotatedDim = dim.toVector().rotate(angle,axis);
        }
        else
        {
          rotatedDir = dir;
          rotatedDim = dim.toVector();
        }
        
        for(var i = 0; i <= Math.max(dim.width,dim.height,dim.depth); i++)
        {
          rotatedDim = rotatedDim.snapTo(i);
          rotatedDim = rotatedDim.snapTo(-i);
        }
        
        rotatedDim = $V([Math.abs(rotatedDim.elements[0]),Math.abs(rotatedDim.elements[1]),Math.abs(rotatedDim.elements[2])]);
        
        var dx = rotatedDir.elements[0];
        var dy = rotatedDir.elements[1];
        var dz = rotatedDir.elements[2];
        
        var shapes = [];
        
        var xs = Math.signum(rotatedDim.elements[0]);
        var ys = Math.signum(rotatedDim.elements[1]);
        var zs = Math.signum(rotatedDim.elements[2]);
        
        //nx/ny/nz=number of times moved in the x/y/z direction
        for(var nx = 0; nx < rotatedDim.elements[0]; nx++)
        {
          for(var ny = 0; ny < rotatedDim.elements[1]; ny++)
          {
              for(var nz = 0; nz < rotatedDim.elements[2]; nz++)
              {
                var s = new Shape();
                var p = s.points;
                
                var a = $V([nx*dx,0,nz*dz]);
                var b = $V([nx*dx,0,(nz+1)*dz]);
                var c = $V([(nx+1)*dx,0,(nz+1)*dz]);
                var d = $V([(nx+1)*dx,0,nz*dz]);
                
                if(angle!=0)
                {
                  a = a.rotate(-angle,axis);
                  b = b.rotate(-angle,axis);
                  c = c.rotate(-angle,axis);
                  d = d.rotate(-angle,axis);
                }
                
                a = a.add(start);
                b = b.add(start);
                c = c.add(start);
                d = d.add(start);
                
                
                p[0] = a.toPoint();
                p[1] = b.toPoint();
                p[2] = c.toPoint();
                p[3] = d.toPoint();
                
                s.polygons.push(new Polygon(
                  [p[0],p[1],p[2],p[3]],
                 normal.toPoint(),
                 backface,
                 type,
                 color)
                );
                
                shapes.push(s);                 
              }
          }
        }
        return shapes;
    }
    
    /*
    *Interior walls is really all walls. I'm in a hurry, shut up!
    */
    
		function load()
    {
    
			// Init drawing system
			canvas = document.getElementById("cv");
			ctx = canvas.getContext("2d");

			canvasWidth = canvas.width;
			canvasHeight = canvas.height;
			halfCanvasWidth = canvasWidth * 0.5;
			halfCanvasHeight = canvasHeight * 0.5;

			// Init 3D components
			space = new Space();
			scene = new Scene();
      
      var weapon = [];
      if(gameState['selfWeapon']!=0)
      {
        weapon = makeWeapon($V([gameState['selfX']*tileWidth+tileWidth/2+orientations[gameState['selfOrient']][0]*tileWidth/3,
                                        0,
                                        gameState['selfZ']*tileWidth+tileWidth/2+orientations[gameState['selfOrient']][1]*tileWidth/3]
                                  ),
                                  gameState['selfOrient'],
                                   gameState['selfWeapon'],true,Polygon.SOLID,ownWeaponColor);
      }
      
      var enemy = [];
      if(gameState['otherX'])
      {
        var ox = gameState['otherX']*tileWidth+tileWidth/2;
        var oz = gameState['otherZ']*tileWidth+tileWidth/2;
        
        var center = $V([ox,playerHeight,oz]);        
        enemy = enemy.concat(sphericalApproximation(center,tileWidth/6,Math.PI/16,true,Polygon.SOLID,playerHeadColor));
        
        //torso
       /* var arrangements = [[1,1,playerHeight-tileWidth/8],[1,-1,playerHeight-tileWidth/8],[-1,-1,playerHeight-tileWidth/8],[-1,1,playerHeight-tileWidth/8],
                                [1,1,playerHeight/3],[1,-1,playerHeight/3],[-1,-1,playerHeight/3],[-1,1,playerHeight/3]];
        var p = [];
        for(var i = 0; i <arrangements.length; i++)
        {
          p.push($V([arrangements[i][0]*tileWidth/8+tileWidth/12*orientations[gameState['otherOrient']][0]+ox,arrangements[i][2],arrangements[i][1]*tileWidth/8+tileWidth/12*orientations[gameState['otherOrient']][1]+oz]));
        }*/
        var tl = $V([gameState['otherX']*tileWidth+tileWidth/2+orientations[(gameState['otherOrient']+3)%4][0]*tileWidth/8+orientations[(gameState['otherOrient']+2)%4][0]*tileWidth/8,
                        playerHeight-tileWidth/8,
                        gameState['otherZ']*tileWidth+tileWidth/2+orientations[(gameState['otherOrient']+3)%4][1]*tileWidth/8+orientations[(gameState['otherOrient']+2)%4][1]*tileWidth/8]);
        var vr = $V([-2*(orientations[(gameState['otherOrient']+3)%4][0]*tileWidth/8+orientations[(gameState['otherOrient']+2)%4][0]*tileWidth/8),
                      0,
                      -2*(orientations[(gameState['otherOrient']+3)%4][1]*tileWidth/8+orientations[(gameState['otherOrient']+2)%4][1]*tileWidth/8)]);
        var top = segmentedFlatSurface(tl,vr,new Dimension(1,1,1),$V([0,1,0]),true,Polygon.Solid,playerTorsoColor);
        var p = top.pop().points;
                      
       enemy = enemy.concat(eightPointedSolid(p[0].toVector(),p[1].toVector(),p[2].toVector(),p[3].toVector(),
                                            p[0].toVector().add($V([0,playerHeight/3-(playerHeight-tileWidth/8),0])),
                                            p[1].toVector().add($V([0,playerHeight/3-(playerHeight-tileWidth/8),0])),
                                            p[2].toVector().add($V([0,playerHeight/3-(playerHeight-tileWidth/8),0])),
                                            p[3].toVector().add($V([0,playerHeight/3-(playerHeight-tileWidth/8),0])),
                                            true,Polygon.SOLID,playerTorsoColor));
        //leg 1
        tl = $V([gameState['otherX']*tileWidth+tileWidth/2+orientations[(gameState['otherOrient']+3)%4][0]*tileWidth/8+orientations[(gameState['otherOrient'])%4][0]*tileWidth/16,
                        playerHeight/3,
                        gameState['otherZ']*tileWidth+tileWidth/2+orientations[(gameState['otherOrient']+3)%4][1]*tileWidth/8+orientations[(gameState['otherOrient'])%4][1]*tileWidth/16]);
        vr = $V([vr.elements[0]/3,0,vr.elements[2]/3]);
        top = segmentedFlatSurface(tl,vr,new Dimension(1,1,1),$V([0,1,0]),true,Polygon.Solid,playerTorsoColor);
        p = top.pop().points;
                      
        enemy = enemy.concat(eightPointedSolid(p[0].toVector(),p[1].toVector(),p[2].toVector(),p[3].toVector(),
                                            p[0].toVector().add($V([0,-playerHeight/3,0])),
                                            p[1].toVector().add($V([0,-playerHeight/3,0])),
                                            p[2].toVector().add($V([0,-playerHeight/3,0])),
                                            p[3].toVector().add($V([0,-playerHeight/3,0])),
                                            true,Polygon.SOLID,playerTorsoColor));
                                            
        //leg 2
        tl = tl.add($V([(orientations[(gameState['otherOrient']+1)%4][0]*tileWidth/8),
                          0,
                          (orientations[(gameState['otherOrient']+1)%4][1]*tileWidth/8)]));
        top = segmentedFlatSurface(tl,vr,new Dimension(1,1,1),$V([0,1,0]),true,Polygon.Solid,playerTorsoColor);
        p = top.pop().points;
                      
        enemy = enemy.concat(eightPointedSolid(p[0].toVector(),p[1].toVector(),p[2].toVector(),p[3].toVector(),
                                            p[0].toVector().add($V([0,-playerHeight/3,0])),
                                            p[1].toVector().add($V([0,-playerHeight/3,0])),
                                            p[2].toVector().add($V([0,-playerHeight/3,0])),
                                            p[3].toVector().add($V([0,-playerHeight/3,0])),
                                            true,Polygon.SOLID,playerTorsoColor));
                                            
        if(gameState['otherWeapon'] != 0)
        {
          enemy = enemy.concat(makeWeapon($V([gameState['otherX']*tileWidth+tileWidth/2,0,gameState['otherZ']*tileWidth+tileWidth/2]),gameState['otherOrient'],
                                        gameState['otherWeapon'],true,Polygon.SOLID,weaponColor));
        }
      }

      var whole_floor = segmentedFlatSurface($V([0,0,0]),$V([tileWidth,0,tileWidth]),new Dimension(arenaSize,1,arenaSize),$V([0,1,0]),true,Polygon.SOLID, floorColor);      
      var floor = [];
      for(var i = 0; i < whole_floor.length; i++)
      {
        if((orientations[gameState['selfOrient']][0] == 0 || (whole_floor[i].polygons[0].origin.x-gameState['selfX']*tileWidth-tileWidth/2)
                      / orientations[gameState['selfOrient']][0] >  tileWidth) &&
           (orientations[gameState['selfOrient']][1] == 0 || (whole_floor[i].polygons[0].origin.z-gameState['selfZ']*tileWidth-tileWidth/2)
                      / orientations[gameState['selfOrient']][1] >  tileWidth)
          )
        {
          floor.push(whole_floor[i]);
        }
      }
      
      
      //walls = walls.concat(segmentedFlatSurface($V([0,0,0]),$V([0,roomHeight/2,tileWidth/2]), new Dimension(1,2,arenaSize*2),$V([1,0,0]),true,Polygon.SOLID,wallColor));
      //walls = walls.concat(segmentedFlatSurface($V([0,0,tileWidth*arenaSize]),$V([tileWidth/2,roomHeight/2,0]), new Dimension(arenaSize*2,2,1),$V([0,0,-1]),true,Polygon.SOLID,wallColor));
      //walls = walls.concat(segmentedFlatSurface($V([tileWidth*arenaSize,0,0]),$V([0,roomHeight/2,tileWidth/2]), new Dimension(1,2,arenaSize*2),$V([-1,0,0]),true,Polygon.SOLID,wallColor));
      //walls = walls.concat(segmentedFlatSurface($V([0,0,0]),$V([tileWidth/2,roomHeight/2,0]), new Dimension(arenaSize*2,2,1),$V([0,0,1]),true,Polygon.SOLID,wallColor));
      
      var interiorWalls = [];
      
      for(var i = 0; i <= arenaSize; i += roomSize)
      {
        for(var j = 0; j < arenaSize; j++)
        {
          interiorWalls.push([[i,j],[i-1,j]]);
        }
      }
      
      for(var i = 0; i <= arenaSize; i += roomSize)
      {
        for(var j = 0; j < arenaSize; j++)
        {
          interiorWalls.push([[j,i],[j,i-1]]);
        }
      }
      
      for(var i = 0; i < interiorWalls.length; i++)
      {
        var wall = interiorWalls[i];
        var gap = false;
        for(var j = 0; j < interiorWallGaps.length; j++)
        {
          if(wall.equals(interiorWallGaps[j]))
          {
            gap = true;
            break;
          }
        }
        if(gap)
        {
          continue;
        }
        
        interiorWallsWithoutGaps.push(wall);
       
        if(wall[0][0] == wall[1][0]) // horizontal wall
        {
          var x = wall[0][0]
          var y = wall[1][1]+1
          walls = walls.concat(segmentedFlatSurface($V([x*tileWidth,0,y*tileWidth]),$V([tileWidth/2,roomHeight/2,0]), new Dimension(2,2,1),$V([0,0,1]),true,Polygon.SOLID,wallColor));
        }
        else //vertical wall
        {
          var x = wall[0][0]
          var y = wall[0][1]
          walls = walls.concat(segmentedFlatSurface($V([x*tileWidth,0,y*tileWidth]),$V([0,roomHeight/2,tileWidth/2]), new Dimension(1,2,2),$V([1,0,0]),true,Polygon.SOLID,wallColor));
        }
        
      }
      
      
      var whole_ceiling = segmentedFlatSurface($V([0,roomHeight,0]),$V([tileWidth,0,tileWidth]),new Dimension(arenaSize,1,arenaSize),$V([0,1,0]),true,Polygon.SOLID, ceilingColor);
      var ceiling = [];
      for(var i = 0; i < whole_ceiling.length; i++)
      {
        if((orientations[gameState['selfOrient']][0] == 0 || (whole_ceiling[i].polygons[0].origin.x-gameState['selfX']*tileWidth-tileWidth/2)
                      / orientations[gameState['selfOrient']][0] >  tileWidth) &&
           (orientations[gameState['selfOrient']][1] == 0 || (whole_ceiling[i].polygons[0].origin.z-gameState['selfZ']*tileWidth-tileWidth/2)
                      / orientations[gameState['selfOrient']][1] >  tileWidth)
          )
        {
          ceiling.push(whole_ceiling[i]);
        }
      }
     
      var surfaces = floor.concat(ceiling).concat(walls).concat(enemy).concat(weapon);
      for(var i = 0; i < surfaces.length; i++)
      {
        scene.shapes[i] = surfaces[i];
      }

      draw();
      drawMinimap();
		}
    
    function draw()
    {
			ctx.clearRect(0, 0, canvasWidth, canvasHeight);

			scene.camera.x = gameState['selfX']*tileWidth+tileWidth/2;
			scene.camera.y = playerHeight;
			scene.camera.z = gameState['selfZ']*tileWidth+tileWidth/2;
      
      scene.cameraTarget.x = gameState['selfX']*tileWidth+tileWidth/2 + 5*orientations[gameState['selfOrient']][0]
      scene.cameraTarget.y = playerHeight;
      scene.cameraTarget.z = gameState['selfZ']*tileWidth+tileWidth/2 + 5*orientations[gameState['selfOrient']][1]

			scene.cameraRotation = 0;
			scene.draw();
    }
    
    function drawMinimap()
    {
      ctx.fillStyle = "rgb(0,0,0)";
      //minimap area
      ctx.fillRect(minimapOffset-1,minimapOffset-1,(minimapRadius*2+1)*minimapTileWidth+2,(minimapRadius*2+1)*minimapTileWidth+2);
    
      ctx.fillStyle = "rgb("+floorColor[0]+","+floorColor[1]+","+floorColor[2]+")";
      for(var x = Math.max(gameState['selfX'] - minimapRadius,0); x <= Math.min(gameState['selfX'] + minimapRadius,arenaSize-1); x++)
      {
        for(var z = Math.max(gameState['selfZ'] - minimapRadius,0); z <= Math.min(gameState['selfZ'] + minimapRadius,arenaSize-1,arenaSize); z++)
        {
          var ox = x - gameState['selfX'] + minimapRadius;
          var oz = minimapRadius*2+1-(z - gameState['selfZ'] + minimapRadius);
          ctx.fillRect(minimapOffset+ox*minimapTileWidth,minimapOffset+oz*minimapTileWidth,minimapTileWidth,-minimapTileWidth);
        }
      }
      
      ctx.strokeStyle = "rgb("+wallColor[0]+","+wallColor[1]+","+wallColor[2]+")";
      for(var i = 0; i < interiorWallsWithoutGaps.length; i++)
      {
        var wall = interiorWallsWithoutGaps[i];
        if(wall[0][0] == wall[1][0]) //horizontal
        {
          var x = wall[0][0];
          var z = Math.max(wall[0][1],wall[1][1]);
          
          if(Math.abs(gameState['selfX']-x) > minimapRadius || gameState['selfZ']-z > minimapRadius || gameState['selfZ']-z-1 < -minimapRadius)
            continue;
          
          var ox = x-gameState['selfX']+minimapRadius
          var oz = minimapRadius*2+1-(z-gameState['selfZ']+minimapRadius);
          ctx.moveTo(minimapOffset+ox*minimapTileWidth,minimapOffset+oz*minimapTileWidth);
          ctx.lineTo(minimapOffset+(ox+1)*minimapTileWidth,minimapOffset+oz*minimapTileWidth);
          ctx.stroke();
        }
        else //vertical
        {
          var x = Math.max(wall[0][0],wall[1][0]);
          var z = wall[0][1]
          
          if(Math.abs(gameState['selfZ']-z) > minimapRadius || gameState['selfX']-x > minimapRadius || gameState['selfX']-x-1 < -minimapRadius)
            continue;
          
          var ox = x-gameState['selfX']+minimapRadius
          var oz = minimapRadius*2-(z-gameState['selfZ']+minimapRadius);
          
          ctx.moveTo(minimapOffset+ox*minimapTileWidth,minimapOffset+oz*minimapTileWidth);
          ctx.lineTo(minimapOffset+ox*minimapTileWidth,minimapOffset+(oz+1)*minimapTileWidth);
          ctx.stroke();
        }
      }
      
      ctx.fillStyle = "rgb("+playerHeadColor[0]+","+playerHeadColor[1]+","+playerHeadColor[2]+")";
      ctx.beginPath();
      
      //Just gotta get it to work; don't care how
      var angle ;
      if(gameState['selfOrient'] % 2 == 0)
      {
        angle=((gameState['selfOrient']+1)%4)*Math.PI/2+Math.PI/6;
      }
      else
      {
        angle=((gameState['selfOrient']+3)%4)*Math.PI/2+Math.PI/6;
      }
      var center = minimapOffset+minimapRadius*minimapTileWidth+minimapTileWidth/2;
      ctx.arc(center,center, minimapTileWidth/2, -angle, -angle+Math.PI/3,1);
      ctx.lineTo(center,center);
      ctx.closePath();
      ctx.fill();
      
      if(gameState['otherX'])
      {
        if(Math.abs(gameState['otherX']-gameState['selfX'])>minimapRadius||Math.abs(gameState['otherZ']-gameState['selfZ'])>minimapRadius)
        {
          return;
        }
        ctx.beginPath();
      
        //Just gotta get it to work; don't care how
        if(gameState['otherOrient'] % 2 == 0)
        {
          angle=((gameState['otherOrient']+1)%4)*Math.PI/2+Math.PI/6;
        }
        else
        {
          angle=((gameState['otherOrient']+3)%4)*Math.PI/2+Math.PI/6;
        }
        var x = minimapOffset+(gameState['otherX']-gameState['selfX']+minimapRadius+0.5)*minimapTileWidth;
        var z = minimapOffset+(minimapRadius*2+1-(gameState['otherZ']-gameState['selfZ']+minimapRadius+0.5))*minimapTileWidth;
        ctx.arc(x,z, minimapTileWidth/2, -angle, -angle+Math.PI/3,1);
        ctx.lineTo(x,z);
        ctx.closePath();
        ctx.fill();
      
      }
      
    }
    
    function left()
    {
      document.getElementById("dx").value = orientations[(gameState['selfOrient']+3)%4][0];
      document.getElementById("dz").value = orientations[(gameState['selfOrient']+3)%4][1];
      
      document.getElementById("action_taken").value = "true";
      
      document.getElementById("action").submit();
    }
    
    function right()
    {
      document.getElementById("dx").value = orientations[(gameState['selfOrient']+1)%4][0];
      document.getElementById("dz").value = orientations[(gameState['selfOrient']+1)%4][1];
      
      document.getElementById("action_taken").value = "true";
      
      document.getElementById("action").submit();

    }
    
    function forwardRight()
    {
      document.getElementById("dx").value = orientations[gameState['selfOrient']][0]+orientations[(gameState['selfOrient']+1)%4][0];
      document.getElementById("dz").value = orientations[gameState['selfOrient']][1]+orientations[(gameState['selfOrient']+1)%4][1];
      
      document.getElementById("action_taken").value = "true";
      
      document.getElementById("action").submit();
    }
    
    function forwardLeft()
    {
      document.getElementById("dx").value = orientations[gameState['selfOrient']][0]+orientations[(gameState['selfOrient']+3)%4][0];
      document.getElementById("dz").value = orientations[gameState['selfOrient']][1]+orientations[(gameState['selfOrient']+3)%4][1];
      
      document.getElementById("action_taken").value = "true";
      
      document.getElementById("action").submit();
    }
    
    function forward()
    {
      document.getElementById("dx").value = orientations[gameState['selfOrient']][0];
      document.getElementById("dz").value = orientations[gameState['selfOrient']][1];
      
      document.getElementById("action_taken").value = "true";
      
      document.getElementById("action").submit();
    }
    
    function back()
    {
      document.getElementById("dx").value = -orientations[gameState['selfOrient']][0];
      document.getElementById("dz").value = -orientations[gameState['selfOrient']][1];
      
      document.getElementById("action_taken").value = "true";
      
      document.getElementById("action").submit();
    }
    
    function backRight()
    {
      document.getElementById("dx").value = -orientations[gameState['selfOrient']][0]+orientations[(gameState['selfOrient']+1)%4][0];
      document.getElementById("dz").value = -orientations[gameState['selfOrient']][1]+orientations[(gameState['selfOrient']+1)%4][1];
      
      document.getElementById("action_taken").value = "true";
      
      document.getElementById("action").submit();
    }
    
    function backLeft()
    {
      document.getElementById("dx").value = -orientations[gameState['selfOrient']][0]+orientations[(gameState['selfOrient']+3)%4][0];
      document.getElementById("dz").value = -orientations[gameState['selfOrient']][1]+orientations[(gameState['selfOrient']+3)%4][1];
      
      document.getElementById("action_taken").value = "true";
      
      document.getElementById("action").submit();
    }
    
    function turnLeft()
    {
      document.getElementById("theta").value = -1;
      document.getElementById("action_taken").value = "true";
      document.getElementById("action").submit();
    }
    
    function turnRight()
    {
      document.getElementById("theta").value = 1;
      document.getElementById("action_taken").value = "true";
      document.getElementById("action").submit();
    }
    
    
    function cloak()
    {
      document.getElementById("other_action").value = "cloak";
      document.getElementById("action_taken").value = "true";
      document.getElementById("action").submit();
    }
    
    function wieldDagger()
    {
      document.getElementById("other_action").value = "wield_dagger";
      document.getElementById("action_taken").value = "true";
      document.getElementById("action").submit();
    }
    
    function wieldSword()
    {
      document.getElementById("other_action").value = "wield_sword";
      document.getElementById("action_taken").value = "true";
      document.getElementById("action").submit();
    }
    
    function layStun()
    {
      document.getElementById("other_action").value = "lay_stun";
      document.getElementById("action_taken").value = "true";
      document.getElementById("action").submit();
    }
    
    function detectLife()
    {
      document.getElementById("other_action").value = "detect_life";
      document.getElementById("action_taken").value = "true";
      document.getElementById("action").submit();
    }
    
    function prepAmulet()
    {
      document.getElementById("other_action").value = "prep_amulet";
      document.getElementById("action_taken").value = "true";
      document.getElementById("action").submit();
    }
    
    function slash()
    {
      document.getElementById("other_action").value = "slash";
      document.getElementById("action_taken").value = "true";
      document.getElementById("action").submit();
    }
    
    function backstab()
    {
      document.getElementById("other_action").value = "backstab";
      document.getElementById("action_taken").value = "true";
      document.getElementById("action").submit();
    }
    
    function throwKnife()
    {
      document.getElementById("other_action").value = "throwknife";
      document.getElementById("action_taken").value = "true";
      document.getElementById("action").submit();
    }
    
    function prepKnife()
    {
      document.getElementById("other_action").value = "prepknife";
      document.getElementById("action_taken").value = "true";
      document.getElementById("action").submit();
    }
    

		/* -------------------------------------------------------------------- */
	//--></script>
	<style>
	body {
		background-color:white;
		margin:0px;
		text-align:center;
	}
  
  .button{
    cursor: pointer
  }
	</style>
</head>
<body onload="load();">
  <?=$message?>
  <?=($gamestate["p{$player}cloaked"]==1)?"<br/>You are cloaked.":""?>
  <br/><br/>
  Be warned, the lighting here is treacherous! If you see nothing, then <a href="index.php?<?=$args?>">blink</a> your eyes - reality may have returned.
  <br/>
  <canvas id="cv" width="400" height="400"></canvas>
  <table style="width: 200px; height: 200px;" cellspacing="0" align="center">
  <span style="text-align: center"><br/>Remaining actions: <?=$gamestate["p{$player}actions"]?></span>
  <caption>Move: 1 action</caption>
  <tr>
    <td><a class="button"  id="fl_butt" onclick="forwardLeft()"><img src="arrow_fl.gif"/></a></td>
    <td><a class="button" id="up_butt" onclick="forward()"><img src="arrow_f.gif"/></a></td>
    <td><a class="button" id="fr_butt" onclick="forwardRight()"><img src="arrow_fr.gif"/></a><td>
  </tr>
  <tr>
    <td><a class="button" id="left_butt" onclick="left()"><img src="arrow_l.gif"/></a></td>
    <td>&nbsp;</td>
    <td><a class="button" id="right_butt" onclick="right()"><img src="arrow_r.gif"/></a></td>
  </tr>
  <tr>
    <td><a class="button" id="bl_butt" onclick="backLeft()"><img src="arrow_bl.gif"/></a></td>
    <td><a class="button" id="down_butt" onclick="back()"><img src="arrow_b.gif"/></a></td>
    <td><a class="button" id="br_butt" onclick="backRight()"><img src="arrow_br.gif"/></a></td>
  </tr>
  <tr><td colspan="3">Turn: 3 actions</td></tr>
  <tr>
  <td><a class="button" id="tl_butt" onclick="turnLeft()"><img src="turn_l.gif"/></a></td>
  <td><a class="button" id="tr_butt" onclick="turnRight()"><img src="turn_r.gif"/></a></td>
  </tr>
  <tr><td colspan="3">Wield weapon: 4 actions</td></tr>
  <tr>
  <td><a class="button" id="tl_butt" onclick="wieldDagger()"><img src="wield_dagger.gif"/></a></td>
  <td><a class="button" id="tl_butt" onclick="wieldSword()"><img src="wield_sword.gif"/></a></td>
  </tr>
  <tr><td colspan="3">Cloak: 8 actions</td></tr>
  <tr>
  <td><a class="button" id="tl_butt" onclick="cloak()"><img src="cloak.gif"/></a></td>
  </tr>
  <tr><td colspan="3">Lay stun bomb in front of you: 7 actions</td></tr>
  <tr>
  <td><a class="button" id="tl_butt" onclick="layStun()"><img src="stun.gif"/></a></td>
  </tr>
  <tr><td colspan="3"><?=($gamestate["p{$player}preppeddetect"]==1)?"Use amulet of detect life":"Prepare amulet of detect life"?> :8 actions</td></tr>
  <tr>
  <td><a class="button" id="tl_butt" onclick="<?=($gamestate["p{$player}preppeddetect"]==1)?"detectLife()":"prepAmulet()"?> ">
        <img src="<?=($gamestate["p{$player}preppeddetect"]==1)?"detect_life":"prep_amulet"?>.gif"/></a></td>
  </tr>
  <?
    if($gamestate["p{$player}weap"]==1)
    {
  ?>
  <tr><td colspan="3">Backstab: 4 actions</td></tr>
    <tr>
  <td><a class="button" id="tl_butt" onclick="backstab()"><img src="backstab.gif"/></a></td>
  </tr>
  <?
  }
  else if($gamestate["p{$player}weap"]==2)
  {
  ?>
  <tr><td colspan="3">Slash with sword: 4 actions</td></tr>
    <tr>
  <td><a class="button" id="tl_butt" onclick="slash()"><img src="slash.gif"/></a></td>
  </tr>
  <?
  }
  ?> 
    <tr>
  <tr><td colspan="3"><?=($gamestate["p{$player}throwingknife"]==1)?"Throw knife":"Ready throwing knife"?> :<?=($gamestate["p{$player}throwingknife"]==1)?8:4?> actions</td></tr>
  <td><a class="button" id="tl_butt" onclick="<?=($gamestate["p{$player}throwingknife"]==1)?"throwKnife()":"prepKnife()"?> ">
        <img src="<?=($gamestate["p{$player}throwingknife"]==1)?"throw_knife":"prepknife"?>.gif"/></a></td>
  </tr>
  </table>
  <form name="action" method="post" action="index.php?<?=$args?>"  id="action">
    <input type="hidden" value="" name="dx" id="dx"/>
    <input type="hidden" value="" name="dz" id="dz"/>
    <input type = "hidden" value="" name="theta" id="theta"/>
    <input type = "hidden" value="" name="other_action" id="other_action"/>
    <input type = "hidden" value="false" name="action_taken" id="action_taken"/>
  </form>
</body>
</html>