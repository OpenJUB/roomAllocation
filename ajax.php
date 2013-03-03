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

  recursive_escape( $_GET );

  $Search = new Search( array( 'fname', 'lname' ) );
  $Allocation_Model = new Allocation_Model();

  define('MIN_LIMIT', 2);

  e_assert( isset($_GET['action']) && strlen($_GET['action']) >= 2, 'No action set' );
  if( !isset( $_SESSION['eid'] ) ){
    jsonOutput(array(
      'error' => 'You were logged off due to timeout',
      'rpc'   => 'RPC.reload();'
    ));
  }

  $eid = $_SESSION['eid'];

  $tmp_group_id = mysql_fetch_assoc( mysql_query( "SELECT group_id FROM ".TABLE_IN_GROUP." WHERE eid='$eid'") );
  $_SESSION['info']['group_id'] = $tmp_group_id['group_id'];
  $college  = get_college_by_eid( $eid );

  $colleges = array( 'Mercator', 'Krupp', 'College-III', 'Nordmetall' );

  $output = array(
    'result'  => false,
    'rpc'     => null,
    'error'   => '',
    'warning' => '',
    'info'    => '',
    'success' => ''
  );

  switch($_GET['action']){
    case 'autoComplete':
      e_assert_isset( $_GET, 'str' );
      e_assert( strlen( $_GET['str'] ) >= MIN_LIMIT, 'Query too short. Must have at least '.MIN_LIMIT.' chars' );
      $min_year = (int)date('Y') % 100;
      $columns  = "id,eid,fname,lname,country,college";
      if( $clause = $Search->getQuery( $_GET['str'] ) ){
        $res      = mysql_query( "SELECT $columns FROM ".TABLE_PEOPLE."
                                WHERE (
                                  (status='undergrad' AND year>'$min_year')
                                  OR (status='foundation-year' AND year='$min_year')
                                )
                                AND $clause"
                    );
        sqlToJsonOutput( $res );
      } else {
        outputError( 'Invalid query' );
      }
      break;
    case 'addRoommate':
      e_assert( C('round.active'), 'No round is currently active' );
      e_assert_isset( $_GET, array('eid'=>'Roommate not specified') );
      $eid_to       = $_GET['eid'];
      $q_exists     = "SELECT * FROM ".TABLE_PEOPLE." WHERE eid='$eid_to'";
      $q_sameReq    = "SELECT id FROM ".TABLE_REQUESTS." WHERE (eid_from='$eid' AND eid_to='$eid_to') OR (eid_from='$eid_to' AND eid_to='$eid')";

      $sql_exists         = mysql_query( $q_exists );
      $info_to            = mysql_fetch_assoc( $sql_exists );
      $info_to['college'] = get_college_by_eid( $info_to['eid'] );

      e_assert( $eid != $eid_to, "Don't be narcissistic, you can't add yourself as a roommate d'oh!" );
      e_assert( mysql_num_rows( $sql_exists ) > 0, "Person does not exist?!?!" );
      e_assert( $info_to['college'] == $college, '<b>'.$info_to['fname'].'</b> is in another college ('.$info_to['college'].') !' );

      e_assert( count($Allocation_Model->get_room($eid)) == 0, "You already have a room" );
      e_assert( count($Allocation_Model->get_room($eid_to)) == 0, "Your roommate  already has a room" );

      e_assert( mysql_num_rows( mysql_query( $q_sameReq ) ) == 0, "A requests between you two already exists! You need to check your notifications and accept/reject it..." );

      $q = "INSERT INTO ".TABLE_REQUESTS."(eid_from,eid_to) VALUES ('$eid', '$eid_to')";
      @mysql_query( $q );
      $output['result']   = getFaceHTML_sent( $info_to );
      $output['error']    = mysql_error();
      $output['success']  = 'Roommate request sent successfully!';
      break;
    case 'requestSent':
      e_assert( C('round.active'), 'No round is currently active' );
      e_assert_isset( $_GET, 'eid,msg' );
      $eid_to = $_GET['eid'];
      $q = "DELETE FROM ".TABLE_REQUESTS." WHERE (eid_from='$eid' AND eid_to='$eid_to') OR (eid_from='$eid_to' AND eid_to='$eid')";
      mysql_query( $q );
      $output['result'] = mysql_query( $q );
      $output['error']  = mysql_error();
      break;
    case 'requestReceived':
      e_assert( C('round.active'), 'No round is currently active' );
      e_assert_isset( $_GET, 'eid,msg' );
      $eid_to = $_GET['eid'];

      $q_isRequest = "SELECT id FROM ".TABLE_REQUESTS." WHERE eid_from='$eid_to' AND eid_to='$eid'";
      e_assert( mysql_num_rows( mysql_query($q_isRequest) ) > 0, 'The person has not sent you any requests' );
      if( $_GET['msg'] == 'yes' ){
        $q_exists   = "SELECT * FROM ".TABLE_PEOPLE." WHERE eid='$eid_to'";
        $sql_exists = mysql_query( $q_exists );
        e_assert( mysql_num_rows( $sql_exists ) > 0, "Person does not exist?!?!" );

        $info_to  = mysql_fetch_assoc( $sql_exists );
        $g_from   = group_info( $_SESSION['info']['eid'] );
        $g_to     = group_info( $info_to['eid'] );

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

        $output['roommates']  = array_map(function($v){ return getFaceHTML($v); }, $new_roommates);
        $output['points']     = print_score( array_merge( array($_SESSION['info']), $new_roommates ) );
        $output['info']       = 'You and <b>'.$info_to['fname'].'</b> are now roommates!
                                  You need to reload the page in order to apply for rooms.';
          $output['rpc']        = 'RPC.reload();';

        notifyPerson( $eid_to, $_SESSION['info']['fname']." accepted your roommate request" );

        $q = "DELETE FROM ".TABLE_REQUESTS." WHERE eid_to='$eid'";
        $output['result'] = mysql_query( $q );

        $q = "DELETE FROM ".TABLE_APARTMENT_CHOICES." WHERE group_id='".$_SESSION['info']['group_id']."'";
        @mysql_query( $q );

        /* NOTE:  must check if limit is reach and all that bull
        $q    = "SELECT eid FROM ".TABLE_REQUESTS." WHERE eid_to='$eid'";
        $res  = sqlToArray( mysql_query( $q_getRejected ) );
        foreach( $res as $person ){
          notifyPerson( $person['eid'], $_SESSION['fname']." has choosen another roommate" );
        }
        */
      } else {
        notifyPerson( $_GET['eid'], $_SESSION['info']['fname']." rejected your roommate request" );
      }

      $q = "DELETE FROM ".TABLE_REQUESTS." WHERE (eid_from='$eid' AND eid_to='$eid_to') OR (eid_from='$eid_to' AND eid_to='$eid')";
      $output['error'] .= mysql_query( $q ) ? '' : '<div>'.mysql_error().'</div>';
      break;
    case 'addFreshman':
      e_assert( C('roommates.freshman'), 'You cannot choose a freshman as a roommate this round' );
      e_assert( $_SESSION['info']['group_id'] === null, 'You are already in a group with someone' );
      $_SESSION['info']['group_id'] = add_to_group( FRESHMAN_EID, add_to_group( $_SESSION['info']['eid'] ) );
      $output['info'] = 'Successfully added a freshman roommate';
      $output['rpc']  = 'RPC.reload();';
      break;
    case 'removeFreshman':
      $roommates = get_roommates( $_SESSION['info']['eid'], $_SESSION['info']['group_id'] );
      e_assert( $roommates[0]['eid'] == FRESHMAN_EID, 'You have not chosen a freshman as your roommate' );
      $q_delete = "DELETE FROM ".TABLE_IN_GROUP." WHERE group_id='".$_SESSION['info']['group_id']."'";
      $output['info']   = 'Freshman slaughtered successfully!';
      $output['error']  = mysql_query($q_delete) ? false : 'Unable to slaughter freshman! ('.mysql_error().')';
      $output['rpc']    = 'RPC.reload();';
      break;
    case 'getFaceHTML':
      e_assert_isset( $_GET, 'eid,fname,lname,country,year' );
      $output['result'] = getFaceHTML( $_GET );
      break;
    case 'chooseRooms':
      e_assert( C('round.active'), 'No round is currently active' );
      e_assert_isset( $_GET, 'choices' );
      e_assert( is_array( $_GET['choices'] ), "Invalid format for room choices" );
      e_assert( count($_GET['choices']) <= MAX_ROOM_CHOICES, "Too many room selections. You are allowed a max of '".MAX_ROOM_CHOICES."'!");

      $roommates = get_roommates( $_SESSION['info']['eid'], $_SESSION['info']['group_id'] );

      $disabled = array_map( 'trim', explode( ',', C("disabled.$college") ) );

      $rooms          = array();
      $invalid_rooms  = array();
      $bitmask        = array();
      foreach( $_GET['choices'] as $k => $v ){
        if( $v && $v != '' ){
          $tmp = explode(',', $v);
          $tmp = array_map( 'trim', $tmp );
          if(
            count($tmp) > MAX_ROOMMATES+1
            || count($roommates)+1 != count($tmp)
            || in_array( $tmp[0], $disabled )
            || (C('round.restrictions') && !in_array( $tmp[0], $allowed_rooms[$college] ) )
          ){
            $invalid_rooms[] = "($v)";
          } else {
            sort($tmp);
            $hash = implode(',',$tmp);
            if( !isset($bitmask[$hash]) ){
              $rooms[] = $tmp;
              $bitmask[$hash] = true;
            }
          }
        }
      }

      e_assert( count($rooms) > 0, "You have not submitted any room choice!");

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
        $output['error'] .= '<div>The following rooms are already taken by someone else:
                              '.implode(', ',$intersect).'.
                            </div>';
      }

      if( count($invalid_rooms) > 0 ){
        $output['error'] .= '<div>You are not allowed to apply for these apartments:
                              <b>'.implode(', ', $invalid_rooms).'</b>.</div>';
      }

      e_assert( $output['error'] == '', $output['error'] );

      $group_id = $_SESSION['info']['group_id'];
      $values   = array();
      foreach( $rooms as $k => $v ){
        foreach( $v as $room ){
          $values[] = "('$room','$college','$group_id','$k')";
        }
      }
      $values = implode(', ', $values);

      mysql_query( "DELETE FROM ".TABLE_APARTMENT_CHOICES." WHERE group_id='$group_id'" );
      $q = "INSERT INTO ".TABLE_APARTMENT_CHOICES."(number,college,group_id,choice) VALUES $values";
      $output['result'] = mysql_query($q);
      $output['error'] .= mysql_error();
      $output['info']   = 'Rooms updated successfully!';
      break;
    case 'selectRooms':
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
    default:
      outputError( 'Unknown action' );
  }

  jsonOutput( $output );

  function notifyPerson( $eid, $message ){
    //TODO: me
  }

?>
