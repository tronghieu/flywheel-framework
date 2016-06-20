<?php
/**
 * Created by PhpStorm.
 * User: Piggat
 * Date: 6/3/16
 * Time: 4:22 PM
 */

namespace Flywheel\OAuth2\Resources;

use Flywheel\Model\ActiveRecord;
use Flywheel\OAuth2\Controllers\BaseApiResourceController;

abstract class BaseActiveRecordResourceRepository implements IResourceRepository {
    const MAX_PAGE_SIZE = 200;

    /** @var \Flywheel\Model\ActiveRecord */
    private $_owner;

    /**
     * @param ActiveRecord $owner
     */
    function __construct(ActiveRecord $owner) {
        $this->_owner = $owner;
    }

    /**
     * @param BaseApiResourceController $controller
     * @param array $defaultFields
     * @internal param \Flywheel\Db\Query $query
     * @return mixed
     */
    function getOwnedResources($controller, $defaultFields = []) {
        $pageSize = $controller->get('page_size');
        $page = $controller->get('page');

        if ($pageSize > self::MAX_PAGE_SIZE) {
            $pageSize = self::MAX_PAGE_SIZE;
        }

        //count object
        $query = $this->_owner->select();
        $this->createCriteriaFromParams($query, $controller);
        $count = $query->count()->execute();
        $maxPage = (int) ($count / $pageSize);
        if ($count % $pageSize != 0) {
            $maxPage++;
        }

        //get list
        if ($page > $maxPage) {
            $list = [];
        }
        else {
            $query = $this->_owner->select();
            $this->createCriteriaFromParams($query, $controller);
            $list = $query->setMaxResults($pageSize)
                ->setFirstResult($page * $pageSize)
                ->execute();
        }

        //convert to array
        $result = [];
        foreach ($list as $object) {
            $result[] = $controller->restrictFields($this->toArray($object), $defaultFields);
        }

        //return data
        return $controller->jsonResult([
            'data' => $list,
            'meta_data' => [
                'total' => $count,
                'total_page' => $maxPage,
                'current_page' => $page
                ]
        ]);
    }

    /**
     * Convert object to assoc array to return to client
     * @param $object
     * @return mixed
     */
    abstract function toArray($object);

    /**
     * Add WHERE criteria to $query to form search condition, included but not limited to
     * owner criteria
     * @param $query
     * @param $controller
     * @return mixed
     */
    abstract function createCriteriaFromParams($query, $controller);
} 