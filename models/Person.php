<?php

  require_once 'config.php';
  require_once 'models/Model.php'

  /**
   * Holds the information of a person including:
   * - eid
   * - account
   * - fname
   * - lname
   * - country
   * - college
   * - email
   * - year
   * - status
   */
  class Person extends Model {

    public function __construct ($table = TABLE_PEOPLE) {
      parent::__construct($table);
    }

    public username_exists ($user) {
      $user = $this->select('id', "WHERE account='$user'");
      if($user) {
        return (mysql_num_rows() > 0);
      } else {
        return Model::SQL_FAILED;
      }
    }

    public get ($eid) {
      return $this->to_array($this->select('*', "WHERE eid='$eid'"));
    }

    public search ($columns, $min_year, $clause) {
      return $this->to_array($this->select($columns, "WPHERE (
                                        (status='undergrad' AND year>'$min_year')
                                        OR (status='foundation-year' AND year='$min_year')
                                      )
                                      AND $clause"));
    }

    public get_countries () {
      return $this->to_array($this->select("DISTINCT country"));
    }

  }

?>