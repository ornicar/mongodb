<?php
/*
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS
 * "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT
 * LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR
 * A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT
 * OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL,
 * SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT
 * LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE,
 * DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY
 * THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
 * (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE
 * OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 *
 * This software consists of voluntary contributions made by many individuals
 * and is licensed under the LGPL. For more information, see
 * <http://www.doctrine-project.org>.
 */

namespace Doctrine\MongoDB;

use Doctrine\ODM\MongoDB\Mapping\ClassMetadata,
    Doctrine\Common\EventManager,
    Doctrine\ODM\MongoDB\Mapping\Types\Type,
    Doctrine\ODM\MongoDB\Event\CollectionEventArgs,
    Doctrine\ODM\MongoDB\Event\CollectionUpdateEventArgs;

/**
 * Wrapper for the PHP MongoCollection class.
 *
 * @license     http://www.opensource.org/licenses/lgpl-license.php LGPL
 * @link        www.doctrine-project.org
 * @since       1.0
 * @author      Jonathan H. Wage <jonwage@gmail.com>
 */
class Collection
{
    /**
     * The PHP MongoCollection being wrapped.
     *
     * @var \MongoCollection
     */
    protected $mongoCollection;

    /**
     * The Database instance this collection belongs to.
     *
     * @var Database
     */
    protected $db;

    /**
     * The event manager that is the central point of the event system.
     *
     * @var Doctrine\Common\EventManager
     */
    protected $eventManager;

    /**
     * A callable for logging statements.
     *
     * @var mixed
     */
    protected $loggerCallable;

    /**
     * Mongo command prefix
     *
     * @var string
     */
    protected $cmd;

    /**
     * Create a new MongoCollection instance that wraps a PHP MongoCollection instance
     * for a given ClassMetadata instance.
     *
     * @param MongoCollection $mongoCollection The MongoCollection instance.
     * @param Database $db The Database instance.
     * @param EventManager $evm The EventManager instance.
     * @param mixed $loggerCallable The logger callable.
     */
    public function __construct(\MongoCollection $mongoCollection, Database $db, EventManager $evm, $loggerCallable, $cmd)
    {
        $this->mongoCollection = $mongoCollection;
        $this->db = $db;
        $this->eventManager = $evm;
        $this->loggerCallable = $loggerCallable;
        $this->cmd = $cmd;
    }

    /**
     * Log something using the configured logger callable.
     *
     * @param array $log The array of data to log.
     */
    public function log(array $log)
    {
        $log['db'] = $this->db->getName();
        $log['collection'] = $this->getName();
        call_user_func_array($this->loggerCallable, array($log));
    }

    /**
     * Returns the wrapped MongoCollection instance.
     *
     * @return \MongoCollection
     */
    public function getMongoCollection()
    {
        return $this->mongoCollection;
    }

    public function getDatabase()
    {
        return $this->db;
    }

    /** @override */
    public function batchInsert(array &$a, array $options = array())
    {
        if ($this->eventManager->hasListeners(Events::preBatchInsert)) {
            $this->eventManager->dispatchEvent(Events::preBatchInsert, new CollectionEventArgs($this, $a));
        }

        $this->doBatchInsert($a, $options);

        if ($this->loggerCallable) {
            $this->log(array(
                'batchInsert' => true,
                'num' => count($a),
                'data' => $a,
                'options' => $options
            ));
        }

        if ($this->eventManager->hasListeners(Events::postBatchInsert)) {
            $this->eventManager->dispatchEvent(Events::postBatchInsert, new CollectionEventArgs($this, $result));
        }

        return $a;
    }

    protected function doBatchInsert(array &$a, array $options = array())
    {
        return $this->mongoCollection->batchInsert($a, $options);
    }

    /** @override */
    public function update($criteria, array $newObj, array $options = array())
    {
        if ($this->eventManager->hasListeners(Events::preUpdate)) {
            $this->eventManager->dispatchEvent(Events::preUpdate, new CollectionUpdateEventArgs($this, $criteria, $newObj, $options));
        }

        if ($this->loggerCallable) {
            $this->log(array(
                'update' => true,
                'criteria' => $criteria,
                'newObj' => $newObj,
                'options' => $options
            ));
        }

        $result = $this->doUpdate($criteria, $newObj, $options);

        if ($this->eventManager->hasListeners(Events::postUpdate)) {
            $this->eventManager->dispatchEvent(Events::postUpdate, new CollectionEventArgs($this, $result));
        }

        return $result;
    }

    protected function doUpdate($criteria, array $newObj, array $options)
    {
        if (is_scalar($criteria)) {
            $criteria = array('_id' => $criteria);
        }
        return $this->mongoCollection->update($criteria, $newObj, $options);
    }

    /** @override */
    public function find(array $query = array(), array $fields = array())
    {
        if ($this->eventManager->hasListeners(Events::preFind)) {
            $this->eventManager->dispatchEvent(Events::preFind, new CollectionEventArgs($this, $query));
        }

        if ($this->loggerCallable) {
            $this->log(array(
                'find' => true,
                'query' => $query,
                'fields' => $fields
            ));
        }

        $result = $this->doFind($query, $fields);

        if ($this->eventManager->hasListeners(Events::postFind)) {
            $this->eventManager->dispatchEvent(Events::postFind, new CollectionEventArgs($this, $result));
        }

        return new Cursor($result);
    }

    public function doFind(array $query, array $fields)
    {
        return $this->mongoCollection->find($query, $fields);
    }

    /** @override */
    public function findOne(array $query = array(), array $fields = array())
    {
        if ($this->eventManager->hasListeners(Events::preFindOne)) {
            $this->eventManager->dispatchEvent(Events::preFindOne, new CollectionEventArgs($this, $query));
        }

        if ($this->loggerCallable) {
            $this->log(array(
                'findOne' => true,
                'query' => $query,
                'fields' => $fields
            ));
        }

        $result = $this->doFindOne($query, $fields);

        if ($this->eventManager->hasListeners(Events::postFindOne)) {
            $this->eventManager->dispatchEvent(Events::postFindOne, new CollectionEventArgs($this, $result));
        }

        return $result;
    }

    protected function doFindOne(array $query, array $fields)
    {
        return $this->mongoCollection->findOne($query, $fields);
    }

    public function findAndRemove(array $query, array $options = array())
    {
        $command = array();
        $command['findandmodify'] = $this->mongoCollection->getName();
        $command['query'] = $query;
        $command['remove'] = true;
        $command['options'] = $options;

        $result = $this->db->command($command);
        if (isset($result['value'])) {
            $document = $result['value'];
            if ($this->mongoCollection instanceof \MongoGridFS) {
                // Remove the file data from the chunks collection
                $this->mongoCollection->chunks->remove(array('files_id' => $document['_id']), $options);
            }
            return $document;
        }
        return null;
    }

    public function findAndModify(array $query, array $newObj, array $options = array())
    {
        $command = array();
        $command['findandmodify'] = $this->mongoCollection->getName();
        $command['query'] = $query;
        $command['update'] = $newObj;
        if (isset($options['upsert'])) {
            $command['upsert'] = true;
            unset($options['upsert']);
        }
        if (isset($options['new'])) {
            $command['new'] = true;
            unset($options['new']);
        }
        $command['options'] = $options;
        $result = $this->db->command($command);
        return $result['value'];
    }

    /** @proxy */
    public function count(array $query = array(), $limit = 0, $skip = 0)
    {
        if ($this->loggerCallable) {
            $this->log(array(
                'count' => true,
                'query' => $query,
                'limit' => $limit,
                'skip' => $skip
            ));
        }

        return $this->mongoCollection->count($query, $limit, $skip);
    }

    /** @proxy */
    public function createDBRef(array $a)
    {
        if ($this->loggerCallable) {
            $this->log(array(
                'createDBRef' => true,
                'reference' => $a
            ));
        }

        return $this->mongoCollection->createDBRef($a);
    }

    /** @proxy */
    public function deleteIndex($keys)
    {
        if ($this->loggerCallable) {
            $this->log(array(
                'deleteIndex' => true,
                'keys' => $keys
            ));
        }

        return $this->mongoCollection->deleteIndex($keys);
    }

    /** @proxy */
    public function deleteIndexes()
    {
        if ($this->loggerCallable) {
            $this->log(array(
                'deleteIndexes' => true
            ));
        }

        return $this->mongoCollection->deleteIndexes();
    }

    /** @proxy */
    public function drop()
    {
        if ($this->loggerCallable) {
            $this->log(array(
                'drop' => true
            ));
        }

        return $this->mongoCollection->drop();
    }

    /** @proxy */
    public function ensureIndex(array $keys, array $options)
    {
        if ($this->loggerCallable) {
            $this->log(array(
                'ensureIndex' => true,
                'keys' => $keys,
                'options' => $options
            ));
        }

        return $this->mongoCollection->ensureIndex($keys, $options);
    }

    /** @proxy */
    public function __get($name)
    {
        return $this->mongoCollection->__get($name);
    }

    /** @proxy */
    public function getDBRef(array $ref)
    {
        if ($this->loggerCallable) {
            $this->log(array(
                'getDBRef' => true,
                'reference' => $ref
            ));
        }

        return $this->mongoCollection->getDBRef($ref);
    }

    /** @proxy */
    public function getIndexInfo()
    {
        return $this->mongoCollection->getIndexInfo();
    }

    /** @proxy */
    public function getName()
    {
        return $this->mongoCollection->getName();
    }

    /** @proxy */
    public function group($keys, array $initial, $reduce, array $options = array())
    {
        if ($this->loggerCallable) {
            $this->log(array(
                'group' => true,
                'keys' => $keys,
                'initial' => $initial,
                'reduce' => $reduce,
                'options' => $options
            ));
        }

        $result = $this->mongoCollection->group($keys, $initial, $reduce, $options);
        return new ArrayIterator($result);
    }

    /** @proxy */
    public function insert(array &$a, array $options = array())
    {
        if ($this->eventManager->hasListeners(Events::preInsert)) {
            $this->eventManager->dispatchEvent(Events::preInsert, new CollectionEventArgs($this, $a));
        }

        $result = $this->doInsert($a, $options);

        if ($this->loggerCallable) {
            $this->log(array(
                'insert' => true,
                'document' => $a,
                'options' => $options
            ));
        }

        if ($this->eventManager->hasListeners(Events::postInsert)) {
            $this->eventManager->dispatchEvent(Events::postInsert, new CollectionEventArgs($this, $result));
        }
        return $result;
    }

    protected function doInsert(array &$a, array $options)
    {
        $docs = array(&$a);
        return $this->mongoCollection->batchInsert($docs, $options);
    }

    /** @proxy */
    public function remove(array $criteria, array $options = array())
    {
        if ($this->loggerCallable) {
            $this->log(array(
                'remove' => true,
                'criteria' => $criteria,
                'options' => $options
            ));
        }

        return $this->mongoCollection->remove($criteria, $options);
    }

    /** @proxy */
    public function save(array &$a, array $options = array())
    {
        $result = $this->doSave($a, $options);

        if ($this->loggerCallable) {
            $this->log(array(
                'save' => true,
                'document' => $a,
                'options' => $options
            ));
        }

        return $result;
    }

    protected function doSave(array &$a, array $options)
    {
        return $this->mongoCollection->save($a, $options);
    }

    /** @proxy */
    public function validate($scanData = false)
    {
        if ($this->loggerCallable) {
            $this->log(array(
                'validate' => true,
                'scanData' => $scanData
            ));
        }

        return $this->mongoCollection->validate($scanData);
    }

    /** @proxy */
    public function __toString()
    {
        return $this->mongoCollection->__toString();
    }
}