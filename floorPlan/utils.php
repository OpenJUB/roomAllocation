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
  
  /**
   * @brief A room is for one person
   */
  class Room extends AutoSetGet{
    protected $eid      = '';
    protected $fname    = '';
    protected $lname    = '';
    protected $phone    = '';
    protected $number   = '';
    protected $country  = '';
    protected $status   = '';

    public function __construct( array $info = array() ){
      foreach( $info as $k => $v ){
        $this->{"set$k"}($v);
      }
    }
		
    public function toString( $classes = '' ){
      foreach( array('eid', 'fname', 'lname', 'phone', 'number', 'country', 'status') as $v ){
        $$v = $this->{"get$v"}();
      }
      return <<<HTML
        <table cellspacing="0" cellpadding="0" id="room-$number" class="room $status $classes">
          <tr>
            <td class="info">
              <div class="number">$number</div>
              <div class="phone">$phone</div>
            </td>
            <td class="person">
              <div class="name">$fname $lname</div>
              <div class="country">$country</div>
            </td>
          </tr>
        </table>
HTML;
    }
    
  }
  
  class AutoSetGet{
    public function __call( $name, $arguments ){
      $action   = substr( $name, 0, 3 );
      $property = strtolower( substr( $name, 3 ) );
      if( property_exists( get_class( $this ), $property ) ){
        switch( $action ){
          case 'set':
            $this->{$property} = $arguments[0];
            return $this;
            break;
          case 'get':
            return $this->{$property};
          default:
            return $this;
        }
      } else {
        return null;
      }
    }
  }
  
  function get_floorplan_rooms (array $map) {
    $result = array();
    foreach ($map as $name => $block) {
      foreach ($block as $number => $floor) {
        foreach ($floor as $columns) {
          foreach ($columns as $apartment) {
            if (!$apartment[0]) {
              continue;
            }
            $result[] = $name."-".$apartment[0];
          }
        }
      }
    }
    return $result;
  }

  function renderMap( array $map, array $classes = array(), array $args = array() ){
    $h = '';
    $emptyRoom = new Room();
    $h .= '<div class="floorPlan">';
    foreach( $map as $name => $block ){
      $h .= '<table class="level">';
      $h .= '<tr>';
      foreach( $block as $number => $floor ){
        $h .= '<td class="block-wrapper">';
        $h .= '<table cellspacing="0" cellpadding="0" class="block">';
        foreach( $floor[0] as $k => $room ){
          $h .= '<tr>';
          for( $i=0; $i<count($floor); ++$i ){
            $h .= '<td class="room-wrapper">';
            list( $no, $tel ) = $floor[$i][$k];
            $roomNumber = "$name-$no";
            switch( getRoomType( $no, $tel ) ){
              case 'blank': $h .= '&nbsp;'; break;
              case 'empty': $h .= $emptyRoom->toString( 'disabled' ); break;
              default:
                $arguments = array( 'number'=>$roomNumber, 'phone'=>$tel );
                if( isset($args[$roomNumber]) ){
                  $arguments = array_merge( $args[$roomNumber], $arguments );
                }
                $tmp = new Room( $arguments );
                if( isset($classes[$roomNumber]) )
                  $h .= $tmp->toString( $classes[$roomNumber] );
                else
                  $h .= $tmp->toString();
            }
            $h .= '</td>';
          }
          $h .= '</tr>';
        }
        $h .= '</table>';
        $h .= '</td>';
      }
      $h .= '</tr>';
      $h .= '</table>';
    }
    $h .= '</div>';
    return $h;
  }
  
  function getRoomType( $no, $tel ){
    if( $no === null && $tel === null ){
      return 'blank';
    } else if( $no === null || $no === '' ){
      return 'empty';
    }
    return "normal";
  }
  
  function trunk( $phone_start, $room_start ){
    $phones = array(
      apartment_pattern( 4, $phone_start, 0, -1 ),
      array_reverse( apartment_pattern( 4, $phone_start-8, 0, -1 ) )
    );
    
    $numbers = array(
      apartment_pattern( 4, $room_start, -2, -1 ),
      array_reverse( apartment_pattern( 4, $room_start-16, -2, -1 ) ) 
    );
    
    return array(
      zip_arrays( $numbers[0], $phones[0] ),
      zip_arrays( $numbers[1], $phones[1] )
    );
  }
  
  function bottom_floor( 
    $phone_start,
    $phone_1 = null,
    $phone_2 = null,
    $phone_3 = null
  ){
    $phone_1  = $phone_1 !== null ? $phone_1 : $phone_start - 18;
    $phone_2  = $phone_2 !== null ? $phone_2 : $phone_start - 17;
    $phone_3  = $phone_3 !== null ? $phone_3 : $phone_start - 16;
    $trunk = trunk( $phone_start, 137 );
    return array(
      array_merge(
        array( array('',''), array(101,$phone_1), array(null,null) ),
        $trunk[0]
      ),
      array_merge(
        array( array(102,$phone_2), array(103,$phone_3), array(null,null) ),
        $trunk[1]
      )
    );
  }
  
  function upper_floor( 
    $floor, 
    $phone_start, 
    $phone_1 = null, 
    $phone_2 = null, 
    $phone_3 = null, 
    $phone_4 = null 
  ){
    $phone_1  = $phone_1 !== null ? $phone_1 : $phone_start + 2;
    $phone_2  = $phone_2 !== null ? $phone_2 : $phone_start + 1;
    $phone_3  = $phone_3 !== null ? $phone_3 : $phone_start - 17;
    $phone_4  = $phone_4 !== null ? $phone_4 : $phone_start - 16;
    $p        = $floor * 100;
    $trunk    = trunk( $phone_start, $p+37 );
    return array(
      array_merge(
        array( array(null,null), array($p+41,$phone_1), array($p+40,$phone_2) ),
        $trunk[0]
      ),
      array_merge(
        array( array($p+2,$phone_3), array($p+3,$phone_4), array(null,null) ),
        $trunk[1]
      )
    );
  }
  
  /**
   * @brief Generates an array with values for a floor
   */
  function apartment_pattern( $apartments, $start, $skip = 0, $increment = 0 ){
    $res = array();
    for( $i=0; $i<$apartments*2; ++$i ){
      $res[] = $start + ($i*$increment) + $skip * floor($i/2);
    }
    return $res;
  }
  
  /**
   * @brief Takes two arrays and creates an array of arrays of size 2 with the values from each
   * @param {array} $arr_1
   * @param {array} $arr_2
   * @returns the newly created array
   */
  function zip_arrays( array $arr_1, array $arr_2 ){
    if( count( $arr_1 ) != count( $arr_2 ) ){
      trigger_error( 'The two arrays do not have the same size', E_USER_WARNING );
      return null;
    }
    $result = array();
    for( $i=0; $i<count($arr_1); ++$i ){
      $result[] = array( $arr_1[$i], $arr_2[$i] );
    }
    return $result;
  }
  
  /** NORDMETALL FUNCTIONS **/
  
  function NM_apartments_to_rooms( array $apartments ){
    $rooms = array();
    foreach( $apartments as $ap ){
      foreach( $ap as $room ){
        $rooms[] = $room;
      }
    }
    return $rooms;
  }
  
  function NM_B_upper_floor( $number ){
    $number *= 100;
    $tmp = array(
      apartment_pattern( 5, ($number+02), 2, 1 ),
      apartment_pattern( 4, ($number+26), 2, 1 ),
      apartment_pattern( 2, ($number+46), 2, 1 ),
      apartment_pattern( 4, ($number+62), 2, 1 ),
      apartment_pattern( 5, ($number+80), 2, 1 )
    );
    $apartments = array();
    foreach( $tmp as $room ){
      $chunked = array_chunk( $room, 2 );
      foreach( $chunked as $ap ){
        $apartments[] = array_map(function($v){return "B-$v";}, $ap);
      }
    }
    return $apartments;
  }
  
  function NM_B_ground_floor(){
    $number = 100;
    $tmp = array(
      apartment_pattern( 5, ($number+02), 2, 1 ),
      apartment_pattern( 4, ($number+26), 2, 1 ),
      apartment_pattern( 2, ($number+46), 2, 1 ),
      apartment_pattern( 4, ($number+62), 2, 1 ),
      apartment_pattern( 5, ($number+80), 2, 1 )
    );
    $apartments = array();
    foreach( $tmp as $room ){
      $chunked = array_chunk( $room, 2 );
      foreach( $chunked as $ap ){
        $apartments[] = array_map(function($v){return "B-$v";}, $ap);
      }
    }
    return $apartments;
  }
  
  /**
   * @brief Returns all room numbers in the apartment with the given room
   * @param {string} $number  The given room number
   * @returns {array}
   */
  function get_apartment( $number ){
    list( $block, $number ) = explode('-', $number);
    $number = (int)$number;
    if( $number <= 103 ){
      return array( "$block-101", "$block-102", "$block-103" );
    } else if( $number % 2 == 0 ){
      return array( "$block-$number", "$block-".($number+1) );
    } else {
      return array( "$block-".($number-1), "$block-$number" );
    }
  }
  
  /**
   * @brief Returns all room numbers in the apartment with the given room
   * @param {string} $number  The given room number
   * @returns {array}
   */
  function get_apartment_NM( $number ){
    list( $block, $number ) = explode('-', $number);
    $number = (int)$number;
    if( $number % 2 == 0 ){
      return array( "$block-$number", "$block-".($number+1) );
    } else {
      return array( "$block-".($number-1), "$block-$number" );
    }
  }
  
?>