<?php
/***************************************************************************\
    This file is part of RoomAllocation.

    RoomAllocation is free software: you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation, either version 3 of the License, or
    (at your option) any later version.

    RoomAllocation is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with RoomAllocation.  If not, see <http://www.gnu.org/licenses/>.
\***************************************************************************/
?>
<?php

  require_once 'config.php';
  require_once 'class.Search.php';
  require_once 'utils.php';

  require_once 'models/Allocation_Model.php';
  require_once 'models/Group_Model.php';
  require_once 'models/Request_Model.php';
  require_once 'models/Person_Model.php';
  require_once 'models/Apartment_Choice_Model.php';
  require_once 'models/College_Choice_Model.php';

  recursive_escape( $_GET );

  $Search = new Search( array( 'fname', 'lname' ) );

  $Allocation_Model = new Allocation_Model();
  $Groups_Model = new Group_Model();
  $Request_Model = new Request_Model();
  $Person_Model = new Person_Model();
  $Apartment_Choice_Model = new Apartment_Choice_Model();
  $College_Choice_Model = new College_Choice_Model();

  define('MIN_LIMIT', 2);

  e_assert( isset($_GET['action']) && strlen($_GET['action']) >= 2, 'No action set' );

  /**
   * BEGIN Not Logged-in Actions
   */
  switch ($_GET['action']) {
    case 'send_random_password':
      e_assert_isset($_GET, 'account');
      $person = $Person_Model->get_by_account($_GET['account']);
      e_assert($person, 'Invalid username');
      $subject = '[Jacobs Room Allocation] Your random password has arrived';
      $content = '
        <div style="border:1px solid #ccc; border-radius:5px; background:#D1E1F4; max-width:500px; padding:0!important; font-family:tahoma,Verdana,arial; font-size:11pt; overflow:hidden;">
          <div style="background:rgba(255,255,255,0.9); border-bottom: 1px solid #ccc; padding:10px; font-size:13pt; font-weight:bold;">
            Hello World!
          </div>
          <div style="padding:10px">
            <p style="margin-top:0">
              You have requested your random password. You can use it directly with your CampusNET username instead of your normal CampusNET password. <br />
            </p>
            <p>
              This was mainly implemented due to some login problems of people with funky passwords and with CampusNET failing at providing a proper API, but also keep busy those conspiracy theorists among you that believe that my sole purpose in life is stealing your CampusNET credentials. I mean, even if that would be true, it wouldn\'t be so obvious ;)
            </p>
            <div>Random-Password: <b style="display:inline-block; border:1px solid #ccc; border-radius:5px; background:#fff; padding:5px;">'.$person['random_password'].'</b></div>
          </div>
          <div style="background:rgba(255,255,255,0.9); border-top:1px solid #ccc; padding:10px">
            Enjoy! <br />
            Cheerio, <br />
            Stefan
          </div>
        </div> 
      ';
      jsonOutput(array(
        'result' => send_mail($person['email'], $subject, 'no-point-in-replying@code4fun.de', $content),
        'email' => $person['email']
      ));
      break;
  }

  /**
   * BEGIN Logged-in Actions
   */

  // make sure user is logged in before accessing this file
  e_assert_isset($_SESSION, 'eid,username,info');

  if( !isset( $_SESSION['info'] ) ){
    jsonOutput(array(
      'error' => 'You were logged off due to timeout',
      'rpc'   => 'RPC.reload();'
    ));
  }

  $eid = $_SESSION['info']['eid'];

  $_SESSION['info']['group_id'] = $Groups_Model->get_group_id($eid);
  $allocation = $Allocation_Model->get_allocation($eid);
  $college  = $allocation['college'];

  $colleges = array( 'Mercator', 'Krupp', 'College-III', 'Nordmetall' );

  $output = array(
    'result'  => false,
    'rpc'     => null,
    'error'   => array(),
    'warning' => array(),
    'info'    => array(),
    'success' => array()
  );

  switch($_GET['action']){
    case 'autoComplete':
      e_assert_isset( $_GET, 'str' );
      e_assert( strlen( $_GET['str'] ) >= MIN_LIMIT, 'Query too short. Must have at least '.MIN_LIMIT.' chars' );
      $min_year = (int)date('Y') % 100;
      $columns  = "id,eid,fname,lname,country,college";
      if( $clause = $Search->getQuery( $_GET['str'] ) ){
        $res      = $Person_Model->search($columns, $min_year, $clause);
        jsonOutput( $res );
      } else {
        outputError( 'Invalid query' );
      }
      break;
    case 'addRoommate':
      e_assert(C('round.type') === 'roommate', 'You are not in the roommate round!');
      // e_assert(C('round.active'), 'No round is currently active');
      e_assert_isset( $_GET, array('eid'=>'Roommate not specified') );
      $eid_to             = $_GET['eid'];
      $info_to            = $Person_Model->get($eid_to);
      $allocation_from    = $Allocation_Model->get_allocation($eid);
      $allocation_to      = $Allocation_Model->get_allocation($eid_to);
      $info_to['college'] = $allocation_to['college'];

      e_assert( $eid != $eid_to, "Don't be narcissistic, you can't add yourself as a roommate d'oh!" );
      e_assert( $info_to, "Person does not exist?!?!" );
      e_assert( $info_to['college'] == $college, '<b>'.$info_to['fname'].'</b> is in another college ('.$info_to['college'].') !' );

      e_assert( !$allocation_from['room'], "You already have a room" );
      e_assert( !$allocation_to['room'], "Your roommate  already has a room" );

      e_assert( !$Request_Model->request_exists($eid, $eid_to), "A requests between you two already exists! You need to check your notifications and accept/reject it..." );

      $Request_Model->send_request($eid, $eid_to);

      $output['result']   = getFaceHTML_sent( $info_to );
      $output['error']    = mysql_error();
      $output['success']  = 'Roommate request sent successfully!';
      break;
    case 'requestSent':
      // e_assert(C('round.type') === 'roommate', 'You are not in the roommate round!');
      e_assert_round_is_active();
      e_assert_isset( $_GET, 'eid,msg' );
      $eid_to = $_GET['eid'];
      $output['result'] = $Request_Model->accept_request($eid, $eid_to);
      $output['error']  = mysql_error();
      break;
    case 'requestReceived':
      //e_assert(C('round.type') === 'roommate', 'You are not in the roommate round!');
      // e_assert_round_is_active();
      e_assert_isset( $_GET, 'eid,msg' );
      $eid_to = $_GET['eid'];

      e_assert( $Request_Model->is_request($eid_to, $eid), 'The person has not sent you any requests' );
      if( $_GET['msg'] == 'yes' ){
        $info_to  = $Person_Model->get($eid_to);
        $g_from   = group_info( $_SESSION['info']['eid'] );
        $g_to     = group_info( $info_to['eid'] );

        e_assert($info_to, "Person does not exist?!?!");

        $msg_tooMany  = "Too many roommates. The maximum allowed in this phase
                            is <b>'".MAX_ROOMMATES."'</b> roommate(s) !";

        if( $g_from['group_id'] === null && $g_to['group_id'] === null ){
          e_assert( 2 <= MAX_ROOMMATES + 1, $msg_tooMany);
          $_SESSION['info']['group_id'] = add_to_group( $info_to['eid'], add_to_group( $_SESSION['info']['eid'] ) );
        } else if( $g_from['group_id'] === null && $g_to['group_id'] !== null ){
          e_assert(
            $g_to['members'] <= MAX_ROOMMATES,
            $msg_tooMany.
            '<br /><b>'.$info_to['fname'].'</b> has '.($g_to['members']-1).' roommate(s)'
          );
          $_SESSION['info']['group_id'] = add_to_group( $_SESSION['info']['eid'], $g_to['group_id'] );
        } else if( $g_from['eid'] === null && $g_to['eid'] !== null ){
          e_assert( $g_from['members'] <= MAX_ROOMMATES, $msg_tooMany);
          $_SESSION['info']['group_id'] = add_to_group( $info_to['eid'], $g_from['group_id'] );
        } else {
          outputError( $msg_tooMany );
          //TODO: MERGE GROUPS !!! ABOVE SHIT IS HACK
          e_assert(
            $g_from['members'] + $g_to['members'] <= MAX_ROOMMATES + 1,
            $msg_tooMany.
            "<br />You are <b>${g_from['members']} and they are ${group_to['members']}</b> !"
          );
        }
        $new_roommates = get_roommates( $_SESSION['info']['eid'], $_SESSION['info']['group_id'] );

        $output['roommates'] = array_map(function($v){ return getFaceHTML($v); }, $new_roommates);
        $output['points'] = print_score( array_merge( array($_SESSION['info']), $new_roommates ) );
        $output['info'] = 'You and <b>'.$info_to['fname'].'</b> are now roommates! You need to reload the page in order to apply for rooms.';
        // $output['rpc']        = 'RPC.reload();';
        notifyPerson( $eid_to, $_SESSION['info']['fname']." accepted your roommate request" );

        $output['result'] = $Request_Model->remove_remaining($eid);

        $Apartment_Choice_Model->remove_all_choices($_SESSION['info']['group_id']);
      } else {
        notifyPerson( $_GET['eid'], $_SESSION['info']['fname']." rejected your roommate request" );
      }
      $output['error'] = $Request_Model->accept_request($eid, $eid_to) ? '' : '<div>'.mysql_error().'</div>';
      break;
    case 'addFreshman':
      //e_assert(C('round.type') === 'roommate', 'You are not in the roommate round!');
      e_assert( C('roommates.freshman'), 'You cannot choose a freshman as a roommate this round' );
      e_assert( $_SESSION['info']['group_id'] === null, 'You are already in a group with someone' );
      $_SESSION['info']['group_id'] = add_to_group( FRESHMAN_EID, add_to_group( $_SESSION['info']['eid'] ) );
      $output['info'] = 'Successfully added a freshman roommate. Please refresh to see him :)';
      // $output['rpc']  = 'RPC.reload();';
      break;
    case 'removeFreshman':
      //e_assert(C('round.type') === 'roommate', 'You are not in the roommate round!');
      $roommates = get_roommates( $_SESSION['info']['eid'], $_SESSION['info']['group_id'] );
      e_assert( $roommates[0]['eid'] == FRESHMAN_EID, 'You have not chosen a freshman as your roommate' );
      $output['info']   = 'Freshman slaughtered successfully! Refresh if you are tired of his face';
      $output['error']  = $Groups_Model->remove_from_group(null, $_SESSION['info']['group_id']) ? false : 'Unable to slaughter freshman! ('.mysql_error().')';
      $output['sql'] = $Groups_Model->get_last_query();
      // $output['rpc']    = 'RPC.reload();';
      break;
    case 'getFaceHTML':
      e_assert_isset( $_GET, 'eid,fname,lname,country,year' );
      $output['result'] = getFaceHTML( $_GET );
      break;
    case 'chooseRooms':
      e_assert(C('round.type') === 'apartment', 'You are not in the apartment round!');
      e_assert_round_is_active();
      e_assert_isset( $_GET, 'choices' );
      e_assert( is_array( $_GET['choices'] ), "Invalid format for room choices" );
      e_assert( count($_GET['choices']) <= MAX_ROOM_CHOICES, "Too many room selections. You are allowed a max of '".MAX_ROOM_CHOICES."'!");

      $roommates = get_roommates( $_SESSION['info']['eid'], $_SESSION['info']['group_id'] );
      e_assert(count($roommates) <= MAX_ROOMMATES && count($roommates) >= MIN_ROOMMATES, 'You are either too many or too little people applying in this round');

      // check for tall rooms and if people are eligible for tall rooms
      if (C('round.restrictions')) {
        $too_small_images = array('condi.jpg', 'consuela.jpg', 'morpheus.jpg');
        $image_dir = 'images/memes/too-small-';
        function too_small_error ($apartment) {
          global $image_dir;
          global $too_small_images;
          $image = $image_dir.$too_small_images[rand(0, count($too_small_images)-1)];
          return '<div style="clerfix">
            <img src="'.$image.'" alt="Too small image" style="float:left; max-width:300px; max-height:140px; margin-right: 15px;" />
            You are not allowed to apply for <b>'.$apartment.'</b>.<br />Neither your nor your roommate(s) is tall enough for it.<br />
            For a better explanation of this phenomenon, please reference the provided image on the side
            <div style="clear:both"></div>
          </div>';
        }
        $whole_group = array_merge(array($_SESSION['info']), $roommates);
        foreach ($_GET['choices'] as $apartment) {
          if (is_tall_apartment(explode(',', $apartment))) {
            $ok = false;
            foreach ($whole_group as $person) {
              if ($person['isTall']) {
                $ok = true;
                break;
              }
            }
            if (!$ok) {
              $output['error'][] = too_small_error($apartment);
            }
          }
        }
      }

      $disabled = array_map( 'trim', explode( ',', C("disabled.$college") ) );
      $rooms          = array();
      $invalid_rooms  = array();
      $bitmask        = array();
      foreach ($_GET['choices'] as $k => $v) {
        if( $v && $v != '' ){
          $tmp = explode(',', $v);
          $tmp = array_map( 'trim', $tmp );
          sort($tmp);
          e_assert($college == 'Nordmetall' || $tmp == get_apartment($tmp[0]), 'Invalid apartment <b>'.$v.'</b>');
          e_assert(count($tmp) <= MAX_ROOMMATES+1 && count($tmp) >= MIN_ROOMMATES+1, 'Apartment <b>'.$v.'</b> is either too big or too small for you and your group');
          e_assert(count($roommates)+1 === count($tmp), 'You are either too many or too little people applying for the <b>'.$v.'</b> apartment');
          e_assert(!in_array($tmp[0], $disabled), 'The apartment <b>'.$v.'</b> is disabled for this round');
          e_assert(!C('round.restrictions') || in_array($tmp[0], $allowed_rooms[$college]), 'You are not allowed to apply for this apartment (<b>'.$v.'</b>) in this round');
          $hash = implode(',',$tmp);
          if( !isset($bitmask[$hash]) ){
            $rooms[] = $tmp;
            $bitmask[$hash] = true;
          }
        }
      }

      e_assert( count($rooms) > 0, "You have not submitted any room choice. Invalid room choices are: ".implode(',', $invalid_rooms));

      $taken = extract_column(
        'eid',
        Model::to_array(
          $Allocation_Model->get_rooms_from_college(
            $college,
            array_reduce($rooms,'array_merge', array())
          )
        )
      );

      if( count($taken) > 0 ){
        $intersect = array_intersect( $rooms, $taken );
        $output['error'][] = '<div>The following rooms are already taken by someone else:
                              '.implode(', ',$intersect).'.
                            </div>';
      }

      if( count($invalid_rooms) > 0 ){
        $output['error'][] = '<div>You are not allowed to apply for these apartments:
                              <b>'.implode(', ', $invalid_rooms).'</b>.</div>';
      }

      e_assert( empty($output['error']), $output['error'] );

      $group_id = $_SESSION['info']['group_id'];
      $values   = array();
      foreach( $rooms as $k => $v ){
        foreach( $v as $room ){
          $values[] = "('$room','$college','$group_id','$k')";
        }
      }
      $values = implode(', ', $values);

      $Apartment_Choice_Model->remove_all_choices($group_id);
      $output['result'] = $Apartment_Choice_Model->insert("(number,college,group_id,choice) VALUES $values");
      $output['error'] = !empty($output['error']) ? $output['error'] : mysql_error();
      $output['info'] = 'Rooms updated successfully!';
      break;
    case 'selectRooms':
      e_assert(C('round.type') === 'apartment', 'You are not in the apartment round!');
      $roommates = get_roommates( $_SESSION['info']['eid'], $_SESSION['info']['group_id'] );
      $r_eids = array_merge( array($eid), extract_column( 'eid', $roommates ) );
      $r_rooms = extract_column('room', Model::to_array($Allocation_Model->get_multiple_rooms($r_eids)));

      $r_rooms = array_flip( $r_rooms );
      $r_eids = array_flip( $r_eids );
      $bitmask = array();
      foreach ($_GET as $k => $v) {
        if( substr( $k, 0, 5 ) == 'room-' ){
          $room = substr( $k, 5 );
          e_assert( isset( $r_eids[$v] ), "Some people in that list are not your roommates" );
          e_assert( isset( $r_rooms[$room] ), "You are trying to apply for a room that is not yours( <b>$room</b> )" );
          e_assert( !isset( $bitmask[$v] ), "Don't be greedy man, one room per person..." );
          e_assert( array_search( $room, $bitmask ) === false, "I know the rooms are big (if you're not in nordmetall), but 2 people can't be allocated in the same room" );
          $bitmask[$v] = $room;
        }
      }
      e_assert( count($bitmask) === count($r_eids), "Not everyone (or too many people) have selected rooms. Refresh the page and try again!" );
      foreach( $bitmask as $eid => $room ){
        $update_query = $Allocation_Model->update_allocation($eid, "room='$room'");
        if (!$update_query) {
          $output['error'] .= mysql_error().'<br />';
        }
      }
      $output['info'] = 'Rooms update successfully!';
      break;
    case 'setAbsent':
      e_assert_isset($_GET, 'absent');
      $absent = $_GET['absent'] ? 1 : 0;
      $_SESSION['info']['absent'] = $absent;
      $output['result'] = $Person_Model->set_absent($eid, $absent);
      $output['info'] = 'You option has been saved. Refresh to update the score';
      $output['error'] = mysql_error();
      break;
    case 'setCollegeChoices':
      e_assert(C('round.type') === 'college', 'You are not in the college round!');
      e_assert_round_is_active();
      e_assert_isset( $_GET, 'choices' );
      e_assert( is_array( $_GET['choices'] ), "Invalid format for room choices" );
      e_assert( C('round.type') == 'college', 'This is currently not a college round' );
      e_assert(count($_GET['choices']) == 4, "Not enough colleges. It needs to be 4.");

      e_assert(intval($_GET['exchange'], 10) === 0 || intval($_GET['exchange'], 10) === 1, "No valid value for the Exchange parameter.");
      e_assert(intval($_GET['quiet'], 10) === 0 || intval($_GET['quiet'], 10) === 1, "No valid value for the Quiet Floor parameter.");

      $correct = array('Mercator', 'Krupp', 'College-III', 'Nordmetall');

      for ($i=0; $i<count($_GET['choices']); ++$i) {
        $pos_college = array_search($_GET['choices'][$i], $correct);
        e_assert($pos_college !== false, "Invalid college names.");
        array_splice($correct, $pos_college, 1);
      }

      e_assert(count($correct) == 0, "Invalid college names.");

      $choices = array('eid' => $_SESSION['info']['eid']);
      for ($i=0; $i<count($_GET['choices']); ++$i) {
        $choices['choice_' . $i] = $_GET['choices'][$i];
      }
      $choices['exchange'] = intval($_GET['exchange'], 10);
      $choices['quiet'] = intval($_GET['quiet'], 10);
      $output['result'] = $College_Choice_Model->set_choices($choices);
      $output['error'] = mysql_error();
      $output['info'] = 'College prefences updated!';
      break;
    case 'remind':
      e_assert_isset($_GET, 'eids');
      e_assert(is_array($_GET['eids']), 'Must provide an array if eids');
      $subject = '[Jacobs Room Allocation] You did not apply for a college!';
      $emails = array();
      foreach ($_GET['eids'] as $eid) {
        $person = $Person_Model->get($eid);
        e_assert($person, "Invalid eid `$eid`");
        $content = '
          <div style="border:1px solid #ccc; border-radius:5px; background:#D1E1F4; max-width:500px; padding:0!important; font-family:tahoma,Verdana,arial; font-size:11pt; overflow:hidden;">
            <div style="background:rgba(255,255,255,0.9); border-bottom: 1px solid #ccc; padding:10px; font-size:13pt; font-weight:bold;">
              Howdy!
            </div>
            <div style="padding:10px">
              <p style="margin-top:0">
                This is to inform you that you have not specified your college preferences. If you do not do that within the next 24 hours you will be assigned to a college randomly.
              </p>
              <p>
                For more information on college preferences, check the emails sent around by the housing committee.
              </p>
              <p>
                If you have any questions feel free to contact the Jacobs University housing committee directly. If you reply to this email nobody will see it and nothing will happen!
              </p>
            </div>
            <div style="background:rgba(255,255,255,0.9); border-top:1px solid #ccc; padding:10px">
              Enjoy! <br />
              Cheerio, <br />
              Stefan
            </div>
          </div>
        ';
        if (!$person['email']) {
          $warnings[] = 'Invalid email for `'.$person['account'].'`';
        } else {
          $emails[] = $person['email'];
        }
      }
      $output['result'] = send_mail(implode(',', $emails), $subject, 'no-point-in-replying@code4fun.de', $content);
      $output['emails'] = $emails;
      $output['success'][] = 'All emails sent successfully!';
      break;
    default:
      outputError( 'Unknown action' );
  }

  jsonOutput( $output );

  function e_assert_round_is_active () {
    e_assert( C('round.active'), 'No round is currently active' );
  }

  function notifyPerson( $eid, $message ){
    //TODO: me
  }

?>
