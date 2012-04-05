
<style>
  .floorPlan{
  }
  .level{
    border      : 1px solid #666;
    background  : #eaeaea;
    margin      : 5px 0;
  }
  .block{
    margin      : 10px;
  }
  .block td{
    width   : 80px;
    height  : 40px;
  }
  .room{
    border      : 1px solid #666;
    background  : #fff;
    width       : 100%;
    font        : 10pt sans-serif arial;
  }
  .room td{
    padding : 4px;
  }
  .room .info{
    text-align  : center;
  }
  .room .person{
    display : none;
  }
  .room.disabled{
    background  : #ddd;
  }
</style>

<?php

  require_once 'utils.php';
  require_once 'Mercator.php';
  
  echo renderMap( $Mercator );
  
?>