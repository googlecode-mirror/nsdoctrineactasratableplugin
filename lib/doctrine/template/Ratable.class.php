<?php
/**
 * Copyright (c) 2010 NetService.ru, Andrei Dziahel aka develop7 <develop7@develop7.info>
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 */

/**
 * Ratable template implementation
 *
 * @author Andrei Dziahel <develop7@develop7.info>
 * @author Vitaly Alyokhin <228vit@gmail.com>
 *
 * @throws Doctrine_Exception
 */
class Doctrine_Template_Ratable extends Doctrine_Template
{

  /**
   * Template default options
   *
   * @var array $options
   */
  protected $_options = array(
    'owner_component' => 'User',
    'rating_column' => array(
      'name' => 'rate',
      'type' => 'integer',
      'size' => 2,
      'unsigned' => false,
      'default' => 0
    ),
    'owner_id' => array('local' => '', 'foreign' => ''),
    'component_id' => array('local' => '', 'foreign' => ''),
  );

  /**
   * __construct
   *
   * @param string $array
   * @return void
   */
  public function __construct(array $options = array())
  {
    $this->_options = Doctrine_Lib::arrayDeepMerge($this->_options, $options);
    $this->_options['owner_table'] = Doctrine::getTable($this->_options['owner_component']);
    $this->_plugin = new Doctrine_Rating($this->_options);
  }

  /**
   * Initialize the Ratable plugin for the template
   *
   * @return void
   */
  public function setUp()
  {
    if ($this->getTable()->isIdentifierComposite())
    {
      throw new Doctrine_Exception('Composite identifiers are not supported yet');
    }

    $ids = array('related' => $this->getTable()->getIdentifier(),
      'owner' => $this->_options['owner_table']->getIdentifier());
    $this->_options['component_id'] = array('foreign' => $this->getTable()->getTableName() . '_' . $ids['related'],
      'local' => $ids['related']);
    $this->_options['owner_id'] = array('foreign' => $this->_options['owner_table']->getTableName() . '_' . $ids['owner'],
      'local' => $ids['owner']);

    $this->_plugin->setOption('component_id', $this->_options['component_id']);
    $this->_plugin->setOption('owner_id', $this->_options['owner_id']);

    $this->_plugin->initialize($this->_table);
  }

  /**
   * Get the plugin instance for the Ratable template
   *
   * @return Doctrine_Rating
   */
  public function getRatable()
  {
    return $this->_plugin;
  }

  /**
   * Returns actual object rating
   *
   * Rating is returned in following format array('rate1' => 'count', 'rate2' => 'count');
   *
   * @return array() with according rating
   */
  public function getRating()
  {
    $q = $this->getRatesQuery();

    $q->select('COUNT(*) rating, r.'. $this->_options['rating_column']['name'] .' as rate')->groupBy('r.'. $this->_options['rating_column']['name']);

    $rates = $q->fetchArray();

    $result = array(
      '1'   => 0,
      '-1'  => 0
    );
    foreach ($rates as $key => $value)
    {
      $result[$value['rate']] = $value['rating'];
    }

    return $result;
  }

  /**
   * returns number of votes
   *
   * @return int number of votes
   */
  public function getRateCount()
  {
    return $this->getRatesQuery()->count();
  }

  /**
   * Rates object with rate by user
   *
   * @param int $rate
   * @param Doctrine_Record|int $user
   * @return boolean true if ok, false else
   */
  public function rateBy($rate, $user)
  {
    if (! $user instanceof $this->_options['owner_component'])
    {
      $user = Doctrine::getTable($this->_options['owner_component'])->findOneById($user);

      if (! $user)
      {
        throw new Doctrine_Exception('User does not exist');
      }
    }

    $q = $this->getRatesQuery()->andWhere('r.' . $this->_options['owner_id']['foreign'] . ' = ?', $user->get($this->_options['owner_id']['local']));
    if (!$q->count())
    {
      //$cname = $this->getRatable()->getTable()->getComponentName();
      //ugly hack
      $cname = 'PostRating';
      $rate_obj = new $cname;
      $arr = array(
        $this->_options['component_id']['foreign'] => $this->getInvoker()->id,
        $this->_options['owner_id']['foreign'] => $user->id,
        $this->_options['rating_column']['name'] => $rate,
      );
      $rate_obj->fromArray($arr);

      $rate_obj->save();
      
      return true;
    }
    else
    {
      return false; //don't allow user to change his vote
    }
  }

  /**
   *
   * @return boolean true if ok, false else
   */
  public function removeRatings()
  {
    return $this->getRatesQuery()->delete()->execute();
  }

  public function getRatings($hydration = Doctrine::HYDRATE_RECORD)
  {
    return $this->getRatesQuery()->execute(array(), $hydration);
  }

  /**
   * Returns rates query draft
   *
   * @return Doctrine_Query
   */
  public function getRatesQuery($alias = 'r')
  {
    return Doctrine_Query::create()
      //->from($this->getRatable()->getTable()->getComponentName() . ' as ' . $alias)
      //ugly hack
      ->from('PostRating as ' . $alias)
      ->where($alias . '.' . $this->_options['component_id']['foreign'] . ' = ?', array($this->getInvoker()->id));
  }
}