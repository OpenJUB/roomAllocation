<?php

  require_once 'config.php';
  require_once 'models/Model.php';

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
   * - random_password
   */
  class Person_Model extends Model {

    public function __construct ($table = TABLE_PEOPLE) {
      parent::__construct($table);
    }

    public function username_exists ($user) {
      $user = $this->select('id', "WHERE account='$user'");
      if($user) {
        return (mysql_num_rows() > 0);
      } else {
        return Model::SQL_FAILED;
      }
    }

    public function get ($eid) {
      return Model::get_first_row($this->select('*', "WHERE eid='$eid'"));
    }

    public function get_by_account ($account) {
      return Model::get_first_row($this->select('*', "WHERE account='$account'"));
    }

    public function get_all ($fields = '*', $query = '') {
      return $this->to_array($this->select($fields, $query));
    }

    public function search ($columns, $min_year, $clause) {
      return $this->to_array($this->select($columns, "WHERE (
                                        (status='undergrad' AND year>'$min_year')
                                        OR (status='foundation-year' AND year='$min_year')
                                      )
                                      AND $clause"));
    }

    public function get_countries () {
      return $this->to_array($this->select("DISTINCT country"));
    }

    public function random_password_login ($account, $password) {
      return Model::get_first_row($this->select('*', "WHERE account='$account' AND random_password='$password'"));
    }

    public function set_password ($eid, $password) {
      return $this->update("random_password='$password'", "WHERE eid='$eid'");
    }

    public function set_absent ($eid, $value) {
      return $this->update("absent='$value'", "WHERE eid='$eid'");
    }

  }

?>