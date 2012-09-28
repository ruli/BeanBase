<?php

/**
 * Copyright (c) 2012 ruli.
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions
 * are met:
 *
 *   * Redistributions of source code must retain the above copyright
 *     notice, this list of conditions and the following disclaimer.
 *
 *   * Redistributions in binary form must reproduce the above copyright
 *     notice, this list of conditions and the following disclaimer in
 *     the documentation and/or other materials provided with the
 *     distribution.
 *
 *   * Neither the names of the copyright holders nor the names of the
 *     contributors may be used to endorse or promote products derived
 *     from this software without specific prior written permission.
 *
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS
 * "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT
 * LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS
 * FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
 * COPYRIGHT OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT,
 * INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING,
 * BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
 * LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER
 * CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT
 * LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN
 * ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
 * POSSIBILITY OF SUCH DAMAGE.
 *
 * @package     BeanBase
 * @author      ruli <runzhou.li@gmail.com>
 * @copyright   2012 ruli.
 * @license     http://www.opensource.org/licenses/bsd-license.php  BSD License
 * @link        https://github.com/ruli/BeanBase
 */

/**
 * Relation constants
 *
 * @package BeanBase
 * @subpackage Constants
 */
interface BeanBase_Constants_Relation {
  const RB_HAS_ONE    = 0;
  const RB_HAS_MANY   = 1;
  const RB_HAVE_MANY  = 2;
  const RB_BELONGS_TO = 3;
}

/**
 * CRUD constants
 *
 * @package BeanBase
 * @subpackage Constants
 */
interface BeanBase_Constants_CRUD {
  const RB_CREATED = "created";
  const RB_UPDATED = "updated";
  const RB_DELETED = "deleted";
}

/**
 * Aggregates all the constants for easy access
 *
 * Poor man's namespacing
 *
 * @package BeanBase
 * @subpackage Constants
 */
class C implements BeanBase_Constants_Relation, BeanBase_Constants_CRUD {
}

/**
 * The BeanBase utility class
 *
 * @package BeanBase
 * @subpackage Util
 */
class RBB {

  // ==================================================================
  //
  // Soft CURDs (avoids actual write to DB)
  //
  // ------------------------------------------------------------------
  /**
   * Create a new bean with given type, data and an optional filter.
   *
   * This method uses array_diff_key( $data, $filter ) to filter out unwanted keys mentioned in $filter
   *
   * @param  string $type   The bean type (table name) to be created
   * @param  array  $data   Data kv-array
   * @param  array  $filter Contains all the keys to be filtered out of $data
   *
   * @return RedBean_OODBBean The created bean
   */
  public static function create( $type, array $data, array $filter=null ) {
    if ( !self::is_assoc($data) ) {
      throw new InvalidArgumentException( 'Data array must be associative' );
    }

    $bean = R::dispense( $type );

    if ( !empty($filter) ) {
      $data = array_diff_key( $data, $filter );
    }

    $bean = $bean->import( $data );

    return $bean;
  }

  /**
   * Read a bean with given ID and type
   *
   * @param  integer  $id   The bean ID
   * @param  string   $type The bean type
   *
   * @return RedBean_OODBBean The loaded bean
   */
  public static function read( $id, $type ) {
    $bean = R::load( $type, $id);

    if ( !$bean->id ) {
      $m = "Cannot find bean by ID = ".$id." in type = ".$type;

      throw new BeanBase_Exception_CRUD( $m, BeanBase_Exception_CRUD::READ );
    }

    return $bean;
  }

  /**
   * Update a bean with given data and an optional filter
   *
   * This method uses array_diff_key( $data, $filter ) to filter out unwanted keys mentioned in $filter
   *
   * This method does not validate the fed in data ($data)
   *
   * @param  RedBean_OODBBean $bean   The bean to be updated
   * @param  array            $data   Data kv-array
   * @param  array            $filter Contains all the keys to be filtered out of $data
   *
   * @return RedBean_OODBBean         The updated bean
   */
  public static function update( RedBean_OODBBean $bean, array $data, array $filter=null ) {
    if ( !self::is_assoc($data) ) {
      throw new InvalidArgumentException( 'Data array must be associative' );
    }

    if ( !empty($filter) ) {
      $data = array_diff_key( $data, $filter );
    }

    $bean->import( $data );

    return $bean;
  }

  // ==================================================================
  //
  // Relational Operations
  //
  // ------------------------------------------------------------------
  /**
   * Relate a bean with potential rel_bean contained in data, and a given filter.
   *
   * Since RBB::associate() method writes back to DB, this method would do it too.
   *
   * @param  RedBean_OOOBBean $bean   The bean
   * @param  array            $data   The data
   * @param  array            $filter KV array with a format of rel_type => code
   */
  public static function relate( RedBean_OOOBBean $bean, array $data, array $filter ) {
    if ( !self::is_assoc($data) ) {
      throw new InvalidArgumentException( 'Data array must be associative' );
    }

    foreach ( $filter as $rel_type => $code ) {
      $id_key = $rel_type.'_id';

      if ( array_key_exists($id_key, $data) ) {
        $rel_bean = self::read( $data[$id_key] );

        self::associate( $bean, $rel_bean, $code );
      }
    }
  }

  /**
   * Associate bean and rel_bean. This method writes back to DB after association has been established.
   *
   * @todo   Fully implement all associations mentioned in http://www.redbeanphp.com/manual/docs/connectingbeans01
   *
   * @param  RedBean_OODBBean  $bean     The bean
   * @param  RedBean_OODBBean  $rel_bean The rel_bean to be associated
   * @param  const             $code     The constant representing one of the defined relationship codes
   */
  public static function associcate( RedBean_OODBBean $bean, RedBean_OODBBean $rel_bean, $code) {
    switch ( $code ) {
      case C::RB_HAS_ONE: // ONE-TO-ONE: http://redbeanphp.com/manual/association_api
        $v1 = R::relatedOne($bean, $rel_bean->_type);
        $v2 = R::relatedOne($rel_bean, $bean->_type);

        if ( !isset($v1) or !isset($v2) ) {
          throw new BeanBase_Exception_Relation( 'Relationship already exists with one or both of beans',
            BeanBase_Exception_Relation::ONE_TO_ONE );
        }

        R::associate( $bean, $rel_bean );

        break;
      case C::RB_HAS_MANY: // ONE-TO-MANY: http://redbeanphp.com/manual/adding_lists
        $own_phrase = 'own'.ucfirst( $rel_bean->_type );

        // An ugly work around of not being able to use $array[] = new_entry
        // TODO: Need a better solution
        if ( isset($bean->$own_phrase) ) {
          if ( in_array($rel_bean, $bean->$own_phrase) ) {
            throw new BeanBase_Exception_Relation( 'Relationship already established',
              BeanBase_Exception_Relation::ONE_TO_MANY );
          }

          array_push( $bean->$own_phrase, $rel_bean );
        } else {
          $bean->$own_phrase = array( $rel_bean );
        }

        break;
      case C::RB_HAVE_MANY: // MANY-TO-MANY: http://redbeanphp.com/manual/association_api
        if ( R::areRelated( $bean, $rel_bean ) ) {
          throw new BeanBase_Exception_Relation( 'Two beans already associated',
            BeanBase_Exception_Relation::MANY_TO_MANY );
        }

        R::associate( $bean, $rel_bean );

        break;
      case C::RB_BELONGS_TO: // MANY-TO-ONE: reversed ONE-TO-MANY
        $rel_type = $rel_bean->_type;

        if ( isset($bean->$rel_type) ) {
          throw new BeanBase_Exception_Relation( 'Parent already exists',
            BeanBase_Exception_Relation::BELONGS_TO );
        }

        $own_phrase = 'own'.ucfirst( $bean->_type );

        if ( isset($rel_bean->$own_phrase) ) {
          if ( in_array($bean, $rel_bean->$own_phrase) ) {
            throw new BeanBase_Exception_Relation( 'Relationship already established',
              BeanBase_Exception_Relation::BELONGS_TO );
          }

          array_push( $rel_bean->$own_phrase, $bean );
        } else {
          $rel_bean->$own_phrase = array( $bean );
        }

        break;
      default:
        throw new BeanBase_Exception_Relation( 'Unknown error when trying to establish relationship between beans',
          BeanBase_Exception_Relation::UNKNOWN );
    }

    if ( self::is_modified($bean) ) {
      R::store( $bean );
    }

    if ( self::is_modified($rel_bean) ) {
      R::store( $rel_bean );
    }
  }

  // ==================================================================
  //
  // Other Utils
  //
  // ------------------------------------------------------------------
  /**
   * Get the given bean's type (table name)
   *
   * @param  RedBean_OODBBean $bean The bean
   * @return string                 The type name
   */
  public static function get_bean_type( RedBean_OODBBean $bean ) {
    return $bean->getMeta( 'type' );
  }

  /**
   * Determines whether the bean is modified and unsaved
   * @todo  Implement before_store trigger somewhere
   *
   * @param  RedBean_OODBBean  $bean The bean
   * @return boolean           True if the bean is modified and unsaved; false otherwise
   */
  public static function is_modified( RedBean_OODBBean $bean ) {
    return $bean->getMeta( 'tainted' );
  }

  /**
   * Checks whether a given bean complete, against a key array filter
   *
   * @param  RedBean_OODBBean $bean   [description]
   * @param  array            $filter [description]
   */
  public static function completeness_check( RedBean_OODBBean $bean, array $filter ) {
    $data = $bean->export();

    foreach ( $filter as $key ) {
      if ( !isset($data[$key]) || empty($data[$key]) ) {
        throw new BeanBase_Exception( 'Missing '.$key.' from given data', BeanBase_Exception::INCOMPLETE );
      }
    }
  }

  /**
   * Checks whether a bean is unique, against a key array filter
   *
   * Make sure to use this before saving the bean into the database
   *
   * @param  RedBean_OODBBean  $bean   The bean to be checked
   * @param  array             $filter Key array that contains all the unique fields to be verified
   */
  public static function uniqueness_check( RedBean_OODBBean $bean, array $filter ) {
    $filtered = self::strip_data( $bean->export(), $filter );

    foreach ($filtered as $key => $value) {
      $verify = R::findOne( self::get_bean_type($bean), $key.'=?', array($value) );

      if ( isset($verify) ) {
        throw new BeanBase_Exception( 'A bean already exists with '.$key.' = '.$value, BeanBase_Exception::UNIQUE );
      }
    }
  }

  /**
   * Strip a data array against a filter.
   *
   * This method behaves pretty much like the opposite of
   * the built-in array_diff_key() function
   *
   * @param  array $data   Data kv-array
   * @param  array $filter Key array that contains the only keys needed from $data
   * @return array         Filtered kv-array only with kv's mentioned by the filter
   */
  public static function strip_data( array $data, array $filter ) {
    if ( !self::is_assoc($data) ) {
      throw new InvalidArgumentException( 'Data array must be associative' );
    }

    $filtered = array();

    foreach ( $filter as $key ) {
      if ( array_key_exists($key, $data) ) {
        $filtered[$key] = $data[$key];
      }
    }

    return $filtered;
  }

  /**
   * Set a timestamp to desired bean property
   *
   * @param  RedBean_OODBBean $bean        The bean
   * @param  string           $property    The property of the bean
   * @param  string           $time        Time, default: 'now'
   * @param  string           $time_format Time format, default: 'Y-m-d H:i:s'
   *
   * @return RedBean_OODBBean              The modified bean
   */
  public static function insert_time_stamp( RedBean_OODBBean $bean, $property, $time='now', $time_format='Y-m-d H:i:s' ) {
    if ( !is_string($property) ) {
      throw new InvalidArgumentException( 'Timestamp property must be a string' );
    }

    $timestamp          = new DateTime( $time );
    $bean->$property = $timestamp->format( $time_format );

    return $bean;
  }

  /**
   * Set unique meta for the given bean
   *
   * @param RedBean_OODBBean $bean   The Bean
   * @param array            $unique Array with unique field keys
   */
  public static function set_unique( RedBean_OODBBean $bean, array $unique ) {
    $bean->setMeta( 'buildcommand.unique', array($unique) ) ;

    return $bean;
  }

  /**
   * Checks if an array is associative (string keys) or sequential (numeric keys)
   *
   * Found at http://stackoverflow.com/a/4254008
   *
   * @param  array   $array The array to be checked
   *
   * @return boolean        True if this is associative, otherwise false
   */
  public static function is_assoc( array $array ) {
    return (bool)count( array_filter(array_keys($array), 'is_string') );
  }

}

/**
 * BeanBase Base Exception
 *
 * @package BeanBase
 * @subpackage Exception
 */
class BeanBase_Exception extends Exception {

  const INCOMPLETE = "INCOMPLETE";
  const UNIQUE     = "UNIQUE";
  const UNKNOWN    = "UNKNOWN";

  /**
   * Override default constructor that makes message required
   * and adding a type string
   *
   * @param string     $message  The error message
   * @param string     $type     Specific error type
   * @param integer    $code     Error code, default: 0
   * @param Exception  $previous Previous exception trace, default: null
   */
  public function __construct( $message, $type, $code=0, Exception $previous=null ) {
    parent::__construct( "(Error Type - {$type}) {$message}", $code, $previous );
  }

  /**
   * Override default __toString() magic method. Hides
   * unecessary information
   *
   * @return string The custom exception string
   */
  public function __toString() {
    return get_called_class().": ".$this->message;
  }

}

/**
 * BeanBase CRUD Exception
 *
 * @package BeanBase
 * @subpackage Exception
 */
class BeanBase_Exception_CRUD extends BeanBase_Exception {

  const CREATE = "CREATE";
  const READ   = "READ";
  const UPDATE = "UPDATE";
  const DELETE = "DELETE";

}

/**
 * BeanBase Relation Exception
 *
 * @package BeanBase
 * @subpackage Exception
 */
class BeanBase_Exception_Relation extends BeanBase_Exception {

  const HAS_ONE    = "ONE-TO-ONE";
  const HAS_MANY   = "ONE-TO-MANY";
  const HAVE_MANY  = "MANY-TO-MANY";
  const BELONGS_TO = "BELONGS-TO";

}
