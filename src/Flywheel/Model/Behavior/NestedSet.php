<?php
/*
 * Nested Set Behavior
 * API List
 * @method int getLeftValue()
 * @method void setLeftValue()
 * @method int getRightValue()
 * @method void setRightValue()
 * @method mixed getScopeValue(boolean $withQuote)
 * @method void setScopeValue(int $scope)
 * @method int getLevelValue()
 * @method void setLevelValue()
 * @method {owner} makeRoot()
 * @method boolean isInTree()
 * @method boolean isRoot()
 * @method boolean isLeaf()
 * @method boolean isDescendantOf($parent)
 * @method boolean isAncestorOf($child)
 * @method boolean hasParent()
 * @method boolean hasPrevSibling(\Flywheel\Db\Query $query = null)
 * @method boolean hasNextSibling(\Flywheel\Db\Query $query = null)
 * @method boolean hasChildren()
 * @method int countChildren(\Flywheel\Db\Query $query = null)
 * @method int countDescendants(\Flywheel\Db\Query $query = null)
 * @method null|{$owner} getParent()
 * @method bool|{$owner} getPrevSibling(\Flywheel\Db\Query $query = null)
 * @method bool|{$owner} getNextSibling(\Flywheel\Db\Query $query = null)
 * @method array getChildren(\Flywheel\Db\Query $query = null)
 * @method null|{$owner} getFirstChild(\Flywheel\Db\Query $query = null)
 * @method null|{$owner} getLastChild(\Flywheel\Db\Query $query = null)
 * @method {$owner}[] getSiblings($query = null)
 * @method {$owner}[] getDescendants($query = null)
 * @method {$owner}[] getBranch($query = null)
 * @method {$owner}[] getAncestors($query = null)
 * @method {$owner} addChild($node)
 * @method {$owner} insertAsFirstChildOf($node)
 * @method {$owner} insertAsLastChildOf($node
 * @method {$owner} insertAsPrevSiblingOf($node)
 * @method {$owner} insertAsNextSiblingOf($node)
 * @method {$owner} moveToFirstChildOf($node)
 * @method {$owner} moveToLastChildOf($node)
 * @method {$owner} moveToPrevSiblingOf($node)
 * @method {$owner} moveToNextSiblingOf($node)
 * @method int deleteDescendants()
 * @method void shiftRLValues($delta, $first, $last = null, $scope = null)
 * @method void shiftLevel($delta, $first, $last = null, $scope = null)
 * @method void setNegativeScope($scope)
 * @method {$owner} findRoot($scope = null)
 * @method {$owner}[] findRoots()
 * @method boolean isNodeValid()
 * @method void makeRoomForLeaf(int $level, $scope = null)
 */
namespace Flywheel\Model\Behavior;
use Flywheel\Event\Event;
use Flywheel\Db\Connection;
use Flywheel\Db\Expression;
use Flywheel\Model\Exception;

class NestedSet extends ModelBehavior {
    public $left_attr = 'lft';
    public $right_attr = 'rgt';
    public $level_attr = 'level';
    public $scope_attr = '';

    protected $_parent;

    protected $_nestedSetChildren = array();

    /**
     * Queries to be executed in the save transaction
     * @var        array
     */
    protected $nestedSetQueries = array();

    public function init() {
        /** @var \Flywheel\Model\ActiveRecord $owner */
        $owner = $this->getOwner();
        $owner->getPrivateEventDispatcher()->addListener('onBeforeSave', array($this, 'checkBeforeSave'));
        $owner->getPrivateEventDispatcher()->addListener('onBeforeDelete', array($this, 'beforeDelete'));
        $owner->getPrivateEventDispatcher()->addListener('onAfterDelete', array($this, 'afterDelete'));
    }

    public function beforeDelete(Event $event) {
        /** @var \Flywheel\Model\ActiveRecord $owner */
        $owner = $event->sender;

        if ($owner->isRoot()) {
            throw new Exception('Deletion of a root node is disabled for nested sets.');
        }

        if ($owner->isInTree()) {
            $owner->deleteDescendants();
        }
    }

    public function afterDelete(Event $event) {
        /** @var \Flywheel\Model\ActiveRecord $owner */
        $owner = $event->sender;

        if (!$owner->isDeleted()) {
            return;
        }

        if ($owner->isInTree()) {
            $scope = ($this->scope_attr)? $owner->{$this->scope_attr} : null;
            // fill up the room that was used by the node
            $owner->shiftRLValues(-2, $owner->{$this->right_attr} + 1, null, $scope);
        }
    }

    public function checkBeforeSave(Event $event) {
        /** @var \Flywheel\Model\ActiveRecord $owner */
        $owner = $event->sender;
        if ($owner->isNew() && $owner->isRoot()) {
            // check if no other root exist in, the tree
            $query = $owner->read()
                ->count()
                ->where($owner->quote($this->left_attr) .'=1');
            if ($this->scope_attr) {
                $query->andWhere($owner->quote($this->scope_attr) .' = ' .$this->getScopeValue(true));
            }

            $nbRoots = $query->execute();
            if ($nbRoots > 0) {
                throw new Exception(sprintf('A root node already exists in this tree with scope "%s".', $this->getScopeValue()));
            }
        }

        $this->_processNestedSetQueries();
    }

    // storage columns accessors

    public function getLeftValue() {
        return $this->getOwner()->{$this->left_attr};
    }

    /**
     * Proxy setter method for the left value of the nested set model.
     * It provides a generic way to set the value, whatever the actual column name is.
     *
     * @param      int $left The nested set left value
     * @return     \Flywheel\Model\ActiveRecord The current object (for fluent API support)
     */
    public function setLeftValue($left) {
        if (null !== $left) {
            $left = (int) $left;
        }

        $owner = $this->getOwner();
        /* @var \Flywheel\Model\ActiveRecord $owner; */

        if ($left != $owner->{$this->left_attr}) {
            $owner->{$this->left_attr} = $left;
        }

        return $owner;
    }

    /**
     * Get tree right value
     * @return integer
     */
    public function getRightValue() {
        return $this->getOwner()->{$this->right_attr};
    }

    /**
     * @param $right
     * @return \Flywheel\Model\ActiveRecord
     */
    public function setRightValue($right) {
        if (null !== $right) {
            $right = (int) $right;
        }

        $owner = $this->getOwner();
        /* @var \Flywheel\Model\ActiveRecord $owner; */
        if ($right != $owner->{$this->right_attr}) {
            $owner->{$this->right_attr} = $right;
        }

        return $owner;
    }

    /**
     * @param $scope
     * @return \Flywheel\Model\ActiveRecord
     */
    public function setScopeValue($scope) {
        if (!$this->scope_attr) {
            return null;
        }

        $owner = $this->getOwner();
        /* @var \Flywheel\Model\ActiveRecord $owner; */
        if ($scope != $owner->{$this->scope_attr}) {
            $owner->{$this->scope_attr} = $scope;
        }

        return $owner;
    }

    /**
     * @param bool $withQuote
     * @return string
     */
    public function getScopeValue($withQuote = false) {
        if (!$this->scope_attr) {
            return ($withQuote)? '""' : null;
        }

        /** @var \Flywheel\Model\ActiveRecord $owner */
        $owner = $this->getOwner();
        if ($withQuote && !is_numeric($owner->{$this->scope_attr})) {
            return '"' .$owner->{$this->scope_attr} .'"';
        }

        return $owner->{$this->scope_attr};
    }

    public function getLevelValue() {
        return $this->getOwner()->{$this->level_attr};
    }

    public function setLevelValue($level) {
        $level = (int) $level;

        $owner = $this->getOwner();
        /* @var \Flywheel\Model\ActiveRecord $owner; */
        if ($level != $owner->{$this->level_attr}) {
            $owner->{$this->level_attr} = $level;
        }

        return $owner;
    }

    // root maker (requires calling save() afterwards)
    public function makeRoot() {
        if ($this->getLeftValue() || $this->getRightValue()) {
            throw new Exception('Cannot turn an existing node into a root node.');
        }

        $this->setLeftValue(1);
        $this->setRightValue(2);
        $this->setLevelValue(0);

        return $this->getOwner();
    }

    // inspection methods
    public function isInTree() {
        return $this->getLeftValue() > 0 && $this->getRightValue() > $this->getLeftValue();
    }

    public function isRoot() {
        return $this->isInTree() && $this->getLeftValue() == 1;
    }

    public function isLeaf() {
        return $this->isInTree() &&  ($this->getRightValue() - $this->getLeftValue()) == 1;
    }

    public function isDescendantOf($parent) {
        if ($this->getScopeValue() !== $parent->getScopeValue()) {
            return false; //since the `this` and $parent are in different scopes, there's no way that `this` is be a descendant of $parent.
        }

        return $this->isInTree() && $this->getLeftValue() > $parent->getLeftValue() && $this->getRightValue() < $parent->getRightValue();
    }

    public function isAncestorOf($child) {
        return $child->isDescendantOf($this);
    }

    public function hasParent() {
        return $this->getLevelValue() > 0;
    }

    public function hasPrevSibling($query = null) {
        if (!$this->isNodeValid()) {
            return false;
        }

        /** @var \Flywheel\Model\ActiveRecord $owner */
        $owner = $this->getOwner();

        if(null == $query) {
            $query = $owner->read();
        }

        $query->select('COUNT(*) AS result');

        $query->andWhere($owner->quote($this->right_attr) .' = ' .($this->getLeftValue() - 1));
        if ($this->scope_attr) {
            $query->andWhere($owner->quote($this->scope_attr) .'=' .$this->getScopeValue(true));
        }
        $result = $query->execute()->fetch(\PDO::FETCH_ASSOC);

        return $result['result'] > 0;
    }

    public function hasNextSibling($query = null) {
        if (!$this->isNodeValid()) {
            return false;
        }

        /** @var \Flywheel\Model\ActiveRecord $owner */
        $owner = $this->getOwner();

        if(null == $query) {
            $query = $owner->read();
        }

        $query->select('COUNT(*) AS result');

        $query->andWhere($owner->quote($this->left_attr) .' = ' .($this->getRightValue() + 1));
        if ($this->scope_attr) {
            $query->andWhere($owner->quote($this->scope_attr) .'=' .$this->getScopeValue(true));
        }
        $result = $query->execute()->fetch(\PDO::FETCH_ASSOC);

        return $result['result'] > 0;
    }

    public function hasChildren() {
        return ($this->getRightValue() - $this->getLeftValue()) > 1;
    }

    public function countChildren($query = null) {
        /** @var \Flywheel\Model\ActiveRecord $owner */
        $owner = $this->getOwner();
        if ($this->isLeaf() || $owner->isNew()) {
            return 0;
        }

        if (null == $query) {
            $query = $owner->read();
        }

        $query->select('COUNT(*) AS result')
            ->andWhere($owner->quote($this->left_attr) .' > ' .$owner->{$this->left_attr} .'
                    AND ' .$owner->quote($this->right_attr) .' < ' .$owner->{$this->right_attr})
            ->andWhere($owner->quote($this->level_attr) .' = ' .($this->getLeftValue() + 1));

        if ($this->scope_attr) {
            $query->andWhere($owner->quote($this->scope_attr) .'=' .$this->getScopeValue(true));
        }

        $result = $query->execute()->fetch(\PDO::FETCH_ASSOC);
        return $result['result'];
    }

    public function countDescendants($query = null) {
        /** @var \Flywheel\Model\ActiveRecord $owner */
        $owner = $this->getOwner();
        if ($this->isLeaf() || $owner->isNew()) {
            return 0;
        }

        if (null == $query) {
            $query = $owner->read();
        }

        $query->select('COUNT(*) AS result')
            ->andWhere($owner->quote($this->left_attr) .' > ' .$owner->{$this->left_attr} .'
                    AND ' .$owner->quote($this->right_attr) .' < ' .$owner->{$this->right_attr});

        if ($this->scope_attr) {
            $query->andWhere($owner->quote($this->scope_attr) .'=' .$this->getScopeValue(true));
        }

        $result = $query->execute()->fetch(\PDO::FETCH_ASSOC);
        return $result['result'];
    }

    // tree traversal methods

    public function getParent() {
        if (null == $this->_parent && $this->hasParent()) {
            /** @var \Flywheel\Model\ActiveRecord $owner */
            $owner = $this->getOwner();

            $query = $owner->read()
                ->andWhere($owner->quote($this->left_attr) .' < ' .$owner->{$this->left_attr} .'
                        AND ' .$owner->quote($this->right_attr) .' > ' .$owner->{$this->right_attr})
                ->orderBy($owner->quote($this->right_attr))
                ->setFirstResult(1);

            if ($this->scope_attr) {
                $query->andWhere($owner->quote($this->scope_attr) .'=' .$this->getScopeValue(true));
            }
            $this->_parent = $query->execute()->fetchObject(get_class($owner), array(null, false));
        }

        return $this->_parent;
    }

    public function getPrevSibling($query = null) {
        if (!$this->isNodeValid()) {
            return false;
        }

        /** @var \Flywheel\Model\ActiveRecord $owner */
        $owner = $this->getOwner();

        if(null == $query) {
            $query = $owner->read();
        }

        $query->andWhere($owner->quote($this->right_attr) .' = ' .($this->getLeftValue() - 1));
        if ($this->scope_attr) {
            $query->andWhere($owner->quote($this->scope_attr) .'=' .$this->getScopeValue(true));
        }
        return $query->execute()->fetchObject(get_class($owner), array(null, false));
    }

    public function getNextSibling($query = null) {
        if (!$this->isNodeValid()) {
            return false;
        }

        /** @var \Flywheel\Model\ActiveRecord $owner */
        $owner = $this->getOwner();

        if(null == $query) {
            $query = $owner->read();
        }

        $query->andWhere($owner->quote($this->left_attr) .' = ' .($this->getRightValue() + 1));
        if ($this->scope_attr) {
            $query->andWhere($owner->quote($this->scope_attr) .'=' .$this->getScopeValue(true));
        }
        return $query->execute()->fetchObject(get_class($owner), array(null, false));
    }

    public function getChildren($query = null) {
        /** @var \Flywheel\Model\ActiveRecord $owner */
        $owner = $this->getOwner();
        if ($this->isLeaf() || $owner->isNew()) {
            return 0;
        }

        if (null == $query) {
            $query = $owner->read();
        }

        $query->andWhere($owner->quote($this->left_attr) .' > ' .$owner->{$this->left_attr} .'
                    AND ' .$owner->quote($this->right_attr) .' < ' .$owner->{$this->right_attr})
            ->andWhere($owner->quote($this->level_attr) .' = ' .($this->getLevelValue() + 1))
            ->orderBy($owner->quote($this->left_attr));

        if ($this->scope_attr) {
            $query->andWhere($owner->quote($this->scope_attr) .'=' .$this->getScopeValue(true));
        }

        return $query->execute()->fetchAll(\PDO::FETCH_CLASS, get_class($owner), array(null, false));
    }

    public function getFirstChild($query = null) {
        if ($this->isLeaf()) {
            return null;
        }

        /** @var \Flywheel\Model\ActiveRecord $owner */
        $owner = $this->getOwner();
        if ($this->isLeaf() || $owner->isNew()) {
            return 0;
        }

        if (null == $query) {
            $query = $owner->read();
        }

        $query->andWhere($owner->quote($this->left_attr) .' > ' .$owner->{$this->left_attr} .'
                    AND ' .$owner->quote($this->right_attr) .' < ' .$owner->{$this->right_attr})
            ->andWhere($owner->quote($this->level_attr) .' = ' .($this->getLevelValue() + 1))
            ->setFirstResult(1)
            ->orderBy($owner->quote($this->left_attr));

        if ($this->scope_attr) {
            $query->andWhere($owner->quote($this->scope_attr) .'=' .$this->getScopeValue(true));
        }

        return $query->execute()->fetchObject(get_class($owner), array(null, false));
    }

    public function getLastChild($query = null) {
        if ($this->isLeaf()) {
            return null;
        }

        /** @var \Flywheel\Model\ActiveRecord $owner */
        $owner = $this->getOwner();
        if ($this->isLeaf() || $owner->isNew()) {
            return 0;
        }

        if (null == $query) {
            $query = $owner->read();
        }

        $query->andWhere($owner->quote($this->left_attr) .' > ' .$owner->{$this->left_attr} .'
                    AND ' .$owner->quote($this->right_attr) .' < ' .$owner->{$this->right_attr})
            ->andWhere($owner->quote($this->level_attr) .' = ' .($this->getLevelValue() + 1))
            ->setFirstResult(1)
            ->orderBy($owner->quote($this->left_attr), 'DESC');

        if ($this->scope_attr) {
            $query->andWhere($owner->quote($this->scope_attr) .'=' .$this->getScopeValue(true));
        }

        return $query->execute()->fetchObject(get_class($owner), array(null, false));
    }

    public function getSiblings($includeCurrent = false, $query = null) {
        if ($this->isRoot()) {
            return array();
        }

        /** @var \Flywheel\Model\ActiveRecord $owner */
        $owner = $this->getOwner();
        if (null == $query) {
            $query = $owner->read();
        }

        $parent = $this->getParent($query);

        $query->andWhere($owner->quote($this->left_attr) .' > ' .$parent->{$this->left_attr} .'
                    AND ' .$owner->quote($this->right_attr) .' < ' .$parent->{$this->right_attr})
            ->andWhere($owner->quote($this->level_attr) .' = ' .($parent->getLevelValue() + 1))
            ->addOrderBy($this->level_attr);

        if (!$includeCurrent) {
            $query->andWhere($owner->quote($owner->getPrimaryKeyField()) .'!=' .$owner->getPkValue());
        }

        if ($this->scope_attr) {
            $query->andWhere($owner->quote($this->scope_attr) .'=' .$this->getScopeValue(true));
        }

        return $query->execute()->fetchAll(\PDO::FETCH_CLASS, get_class($owner), array(null, false));
    }

    public function getDescendants($query = null) {
        if ($this->isLeaf()) {
            return array();
        }

        /** @var \Flywheel\Model\ActiveRecord $owner */
        $owner = $this->getOwner();
        if (null == $query) {
            $query = $owner->read();
        }

        $query->andWhere($owner->quote($this->left_attr) .' > ' .$owner->{$this->left_attr} .'
                AND ' .$owner->quote($this->right_attr) .' < ' .$owner->{$this->right_attr});

        if ($this->scope_attr) {
            $query->andWhere($owner->quote($this->scope_attr) .'=' .$this->getScopeValue(true));
        }

        $query->addOrderBy($owner->quote($this->left_attr));
        return $query->execute()
            ->fetchAll(\PDO::FETCH_CLASS, get_class($owner), array(null, false));
    }

    public function getBranch($query = null) {
        /** @var \Flywheel\Model\ActiveRecord $owner */
        $owner = $this->getOwner();
        if (null == $query) {
            $query = $owner->read();
        }

        $query->andWhere($owner->quote($this->left_attr) .' >= ' .$owner->{$this->left_attr} .'
                AND ' .$owner->quote($this->right_attr) .' <= ' .$owner->{$this->right_attr});

        if ($this->scope_attr) {
            $query->andWhere($owner->quote($this->scope_attr) .'=' .$this->getScopeValue(true));
        }

        $query->addOrderBy($owner->quote($this->left_attr));
        return $query->execute()
            ->fetchAll(\PDO::FETCH_CLASS, get_class($owner), array(null, false));
    }

    public function getAncestors($query = null) {
        if ($this->isRoot()) {
            return array();
        }

        /** @var \Flywheel\Model\ActiveRecord $owner */
        $owner = $this->getOwner();
        if (null == $query) {
            $query = $owner->read();
        }

        $parent = $this->getParent($query);

        $query->andWhere($owner->quote($this->left_attr) .' < ' .$parent->{$this->left_attr} .'
                    AND ' .$owner->quote($this->right_attr) .' > ' .$parent->{$this->right_attr})
            ->addOrderBy($this->level_attr);

        if ($this->scope_attr) {
            $query->andWhere($owner->quote($this->scope_attr) .'=' .$this->getScopeValue(true));
        }

        return $query->execute()->fetchAll(\PDO::FETCH_CLASS, get_class($owner), array(null, false));
    }


    // node insertion methods (require calling save() afterwards)
    public function addChild($node) {
        /** @var \Flywheel\Model\ActiveRecord $owner */
        $owner = $this->getOwner();

        if ($owner->isNew()) {
            throw new Exception('Object must not be new to accept children.');
        }

        $node->insertAsFirstChildOf($owner);

        return $owner;
    }

    public function insertAsFirstChildOf($node) {
        if ($this->isInTree()) {
            throw new Exception('Oject must not already be in the tree to be inserted. Use the moveToFirstChildOf() instead.');
        }

        $left = $node->getLeftValue()+1;

        // Update node properties
        $this->setLeftValue($left);
        $this->setRightValue($left + 1);
        $this->setLevelValue($node->getLevelValue() + 1);
        $scope = $node->getScopeValue();
        $this->setScopeValue($scope);

        /** @var \Flywheel\Model\ActiveRecord $owner */
        $owner = $this->getOwner();

        if (!$owner->validate()) {//check validate before save
            return $owner;
        }
        // Keep the tree modification query for the save() transaction
        $this->nestedSetQueries []= array(
            'callable'  => array($this, 'makeRoomForLeaf'),
            'arguments' => array($left, $scope)
        );

        $owner->save();
        $node->reload();

        return $this->_owner;

    }

    public function insertAsLastChildOf($node) {
        if ($this->isInTree()) {
            throw new Exception('Oject must not already be in the tree to be inserted. Use the moveToFirstChildOf() instead.');
        }

        $left = $node->getRightValue();

        // Update node properties
        $this->setLeftValue($left);
        $this->setRightValue($left + 1);
        $this->setLevelValue($node->getLevelValue() + 1);
        $scope = $node->getScopeValue();
        $this->setScopeValue($scope);

        /** @var \Flywheel\Model\ActiveRecord $owner */
        $owner = $this->getOwner();
        if (!$owner->validate()) {//check validate before save
            return $owner;
        }
        // Keep the tree modification query for the save() transaction
        $this->nestedSetQueries []= array(
            'callable'  => array($this, 'makeRoomForLeaf'),
            'arguments' => array($left, $scope)
        );

        $owner->save();
        $node->reload();

        return $this->_owner;
    }

    public function insertAsPrevSiblingOf($node) {
        if ($this->isInTree()) {
            throw new Exception('Oject must not already be in the tree to be inserted. Use the moveToFirstChildOf() instead.');
        }

        $left = $node->getLeftValue();

        // Update node properties
        $this->setLeftValue($left);
        $this->setRightValue($left + 1);
        $this->setLevelValue($node->getLevelValue());
        $scope = $node->getScopeValue();
        $this->setScopeValue($scope);

        /** @var \Flywheel\Model\ActiveRecord $owner */
        $owner = $this->getOwner();
        if (!$owner->validate()) {//check validate before save
            return $owner;
        }

        $this->nestedSetQueries []= array(
            'callable'  => array($this, 'makeRoomForLeaf'),
            'arguments' => array($left, $scope)
        );

        $owner->save();
        $node->reload();

        return $this->getOwner();
    }

    public function insertAsNextSiblingOf($node) {
        if ($this->isInTree()) {
            throw new Exception('Oject must not already be in the tree to be inserted. Use the moveToFirstChildOf() instead.');
        }

        $left = $node->getRightValue() + 1;

        // Update node properties
        $this->setLeftValue($left);
        $this->setRightValue($left + 1);
        $this->setLevelValue($node->getLevelValue());
        $scope = $node->getScopeValue();
        $this->setScopeValue($scope);

        /** @var \Flywheel\Model\ActiveRecord $owner */
        $owner = $this->getOwner();
        if (!$owner->validate()) {//check validate before save
            return $owner;
        }

        $this->nestedSetQueries []= array(
            'callable'  => array($this, 'makeRoomForLeaf'),
            'arguments' => array($left, $scope)
        );

        $owner->save();
        $node->reload();

        return $this->getOwner();
    }

    // node move methods (immediate, no need to save() afterwards)
    public function moveToFirstChildOf($node) {

        if (!$this->isInTree()) {
            throw new Exception('Object must be already in the tree to be moved. Use the insertAsFirstChildOf() instead.');
        }
        if ($node->isDescendantOf($this->getOwner())) {
            throw new Exception('Cannot move a node as child of one of its subtree nodes.');
        }

        /** @var \Flywheel\Model\ActiveRecord $owner */
        $owner = $this->getOwner();
        if (!$owner->validate()) {//check validate before save
            return $owner;
        }

        $owner->beforeSave();
        $this->_moveSubtreeTo($node->getLeftValue() + 1, $node->getLevelValue() - $this->getLevelValue() + 1, $node->getScopeValue());
        $owner->afterSave();
        $owner->reload();
        $node->reload();

        return $owner;
    }

    public function moveToLastChildOf($node) {
        if (!$this->isInTree()) {
            throw new Exception('Object must be already in the tree to be moved. Use the insertAsFirstChildOf() instead.');
        }
        if ($node->isDescendantOf($this->getOwner())) {
            throw new Exception('Cannot move a node as child of one of its subtree nodes.');
        }

        /** @var \Flywheel\Model\ActiveRecord $owner */
        $owner = $this->getOwner();
        if (!$owner->validate()) {//check validate before save
            return $owner;
        }

        $owner->beforeSave();
        $this->_moveSubtreeTo($node->getRightValue(), $node->getLevelValue() - $this->getLevelValue() + 1, $node->getScopeValue());
        $owner->afterSave();
        $owner->reload();
        $node->reload();

        return $owner;
    }

    public function moveToPrevSiblingOf($node) {
        if (!$this->isInTree()) {
            throw new Exception('Object must be already in the tree to be moved. Use the insertAsPrevSiblingOf() instead.');
        }
        if ($node->isRoot()) {
            throw new Exception('Cannot move to previous sibling of a root node.');
        }
        if ($node->isDescendantOf($this->getOwner())) {
            throw new Exception('Cannot move a node as sibling of one of its subtree nodes.');
        }

        /** @var \Flywheel\Model\ActiveRecord $owner */
        $owner = $this->getOwner();
        if (!$owner->validate()) {//check validate before save
            return $owner;
        }

        $this->beforeSave();
        $this->_moveSubtreeTo($node->getLeftValue(), $node->getLevelValue() - $this->getLevelValue(), $node->getScopeValue());
        $owner->afterSave();
        $owner->reload();
        $node->reload();

        return $owner;
    }

    public function moveToNextSiblingOf($node) {
        if (!$this->isInTree()) {
            throw new Exception('Object must be already in the tree to be moved. Use the insertAsPrevSiblingOf() instead.');
        }
        if ($node->isRoot()) {
            throw new Exception('Cannot move to previous sibling of a root node.');
        }
        if ($node->isDescendantOf($this->getOwner())) {
            throw new Exception('Cannot move a node as sibling of one of its subtree nodes.');
        }

        /** @var \Flywheel\Model\ActiveRecord $owner */
        $owner = $this->getOwner();
        if (!$owner->validate()) {//check validate before save
            return $owner;
        }

        $this->_moveSubtreeTo($node->getRightValue() + 1, $node->getLevelValue() - $this->getLevelValue(), $node->getScopeValue());
        $owner->reload();
        $node->reload();

        return $owner;
    }

    // deletion methods
    public function deleteDescendants() {
        if ($this->isLeaf()) {
            // save one query
            return;
        }
        /** @var \Flywheel\Model\ActiveRecord $owner */
        $owner = $this->getOwner();

        $left = $this->getLeftValue();
        $right = $this->getRightValue();
        $scope = $this->getScopeValue();

        $owner->beginTransaction();
        try {
            $owner->beforeSave();
            // delete descendant nodes (will empty the instance pool)
            $query = $owner->write()
                ->delete($owner->getTableName())
                ->where($owner->quote($this->left_attr) .' > ' .$owner->{$this->left_attr} .'
                        AND ' .$owner->quote($this->right_attr) .' < ' .$owner->{$this->right_attr});

            if ($this->scope_attr) {
                $query->andWhere($owner->quote($this->scope_attr) .'=' .$this->getScopeValue(true));
            }

            $ret = $query->execute();

            // fill up the room that was used by descendants
            $this->shiftRLValues($left - $right + 1, $right, null, $scope);

            // fix the right value for the current node, which is now a leaf
            $this->setRightValue($left + 1);

            $owner->afterSave();
            $owner->commit();
            $owner->reload();
        } catch (Exception $e) {
            $owner->rollback();
            throw $e;
        }

        return $ret;
    }

    /**
     * Move current node and its children to location $destLeft and updates rest of tree
     *
     * @param      int $destLeft Destination left value
     * @param      int $levelDelta Delta to add to the levels
     * @param null $targetScope
     * @throws \Exception
     */
    protected function _moveSubtreeTo($destLeft, $levelDelta, $targetScope = null) {
        /** @var \Flywheel\Model\ActiveRecord $owner */
        $owner = $this->getOwner();

        $preventDefault = false;
        $left  = $this->getLeftValue();
        $right = $this->getRightValue();
        $scope = $this->getScopeValue();

        if ($targetScope === null){
            $targetScope = $scope;
        }


        $treeSize = $right - $left +1;

        $owner->beginTransaction();
        try {
            // make room next to the target for the subtree
            $this->shiftRLValues($treeSize, $destLeft, null, $targetScope);

            if ($targetScope != $scope) {
                //move subtree to < 0, so the items are out of scope.
                $this->shiftRLValues(-$right, $left, $right, $scope);
                //update scopes
                $this->setNegativeScope($targetScope);
                //update levels
                $this->shiftLevel($levelDelta, $left - $right, 0, $targetScope);
                //move the subtree to the target
                $this->shiftRLValues(($right - $left) + $destLeft, $left - $right, 0, $targetScope);

                $preventDefault = true;
            }


            if (!$preventDefault){
                if ($left >= $destLeft) { // src was shifted too?
                    $left += $treeSize;
                    $right += $treeSize;
                }

                if ($levelDelta) {
                    // update the levels of the subtree
                    $this->shiftLevel($levelDelta, $left, $right, $scope);
                }

                // move the subtree to the target
                $this->shiftRLValues($destLeft - $left, $left, $right, $scope);
            }

            // remove the empty room at the previous location of the subtree
            $this->shiftRLValues(-$treeSize, $right + 1, null, $scope);

            // update all loaded nodes
            $owner->clearPool();

            $owner->commit();
        } catch (\Exception $e) {
            $owner->rollback();
            throw $e;
        }
    }

    public function shiftRLValues($delta, $first, $last = null, $scope = null) {
        /** @var \Flywheel\Model\ActiveRecord $owner */
        $owner = $this->getOwner();

        // Shift left column values
        $updateQuery = $owner->write()
            ->update($owner->getTableName())
            ->set($owner->quote($this->left_attr), $owner->quote($this->left_attr) .' + ?')
            ->setParameter(1, $delta, \PDO::PARAM_INT)
            ->where($owner->quote($this->left_attr) .'>=' .$first);
        if (null !== $last) {
            $updateQuery->andWhere($owner->quote($this->left_attr) .'<=' .$last);
        }
        if ($this->scope_attr) {
            $updateQuery->andWhere($owner->quote($this->scope_attr) .' = ' .$this->getScopeValue(true));
        }
        $updateQuery->execute();

        // Shift right column values
        $updateQuery = $owner->write()
            ->update($owner->getTableName())
            ->set($owner->quote($this->right_attr), $owner->quote($this->right_attr) .' + ?')
            ->setParameter(1, $delta, \PDO::PARAM_INT)
            ->where($owner->quote($this->right_attr) .'>=' .$first);
        if (null !== $last) {
            $updateQuery->andWhere($owner->quote($this->right_attr) .'<=' .$last);
        }
        if ($this->scope_attr) {
            $updateQuery->andWhere($owner->quote($this->scope_attr) .' = ' .$this->getScopeValue(true));
        }
        $updateQuery->execute();
    }

    public function shiftLevel($delta, $first, $last, $scope = null) {
        /** @var \Flywheel\Model\ActiveRecord $owner */
        $owner = $this->getOwner();
        $updateQuery = $owner->write()
            ->update($owner->getTableName())
            ->set($owner->quote($this->level_attr), $owner->quote($this->level_attr) .' + ?')
            ->setParameter(1, $delta, \PDO::PARAM_INT)
            ->where($owner->quote($this->left_attr) .'>=' .$first)
            ->andWhere($owner->quote($this->right_attr) .'<=' .$last);

        if ($this->scope_attr) {
            $updateQuery->andWhere($owner->quote($this->scope_attr) .' = ' .$this->getScopeValue(true));
        }
        $updateQuery->execute();
    }

    public function setNegativeScope($scope) {
        /** @var \Flywheel\Model\ActiveRecord $owner */
        $owner = $this->getOwner();

        $updateQuery = $owner->write()
            ->update($owner->getTableName())
            ->set($owner->quote($this->scope_attr), '?')
            ->setParameter(1, $scope)
            ->where($owner->quote($this->left_attr) .'<=0')
            ->execute();
    }

    public function findRoot($scope = null) {
        /** @var \Flywheel\Model\ActiveRecord $owner */
        $owner = $this->getOwner();

        $query = $owner->read()->where($owner->quote($this->left_attr) .'=1');
        if ($scope) {
            $query->andWhere($owner->quote($this->scope_attr) .' = "' .$scope .'"');
        }

        return $query->execute()->fetchObject(get_class($owner), array(null, false));
    }

    public function findRoots() {
        /** @var \Flywheel\Model\ActiveRecord $owner */
        $owner = $this->getOwner();

        return $owner->read()
            ->where($owner->quote($this->left_attr) .'=1')
            ->execute()
            ->fetchAll(\PDO::FETCH_CLASS, get_class($owner), array());
    }

    public function isNodeValid() {
        return $this->getRightValue() > $this->getLeftValue();
    }

    /**
     * Update the tree to allow insertion of a leaf at the specified position
     *
     * @param      int $left    left column value
     * @param      integer $scope    scope column value
     */
    public function makeRoomForLeaf($left, $scope)
    {
        // Update database nodes
        $this->shiftRLValues(2, $left, null, $scope);
    }

    /**
     * Execute queries that were saved to be run inside the save transaction
     */
    protected function _processNestedSetQueries() {
        foreach ($this->nestedSetQueries as $query) {
            call_user_func_array($query['callable'], $query['arguments']);
        }
        $this->nestedSetQueries = array();
    }
}