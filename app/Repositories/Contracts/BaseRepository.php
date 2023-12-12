<?php
/**
 * Created by PhpStorm.
 * User: Denis Kostaev
 * Date: 11/12/2023
 * Time: 12:12
 */

namespace App\Repositories\Contracts;

interface BaseRepository
{
    /**
     * Find a resource by id
     *
     * @param $id
     * @return mixed
     */
    public function findOne($id): mixed;

    /**
     * Find a resource by criteria
     *
     * @param array $criteria
     * @return mixed
     */
    public function findOneBy(array $criteria): mixed;

    /**
     * Search All resources
     *
     * @param array $searchCriteria
     * @return mixed
     */
    public function findBy(array $searchCriteria = []): mixed;

    /**
     * Get all the records by searchCriteria
     * This method is used where we don't need pagination
     *
     * @param array $searchCriteria
     * @return mixed
     */
    public function findAllBy(array $searchCriteria = []): mixed;

    /**
     * Search All resources by any values of a key
     *
     * @param  string  $key
     * @param array $values
     * @return mixed
     */
    public function findIn(string $key, array $values): mixed;

    /**
     * Save a resource
     *
     * @param array $data
     * @return mixed
     */
    public function save(array $data): mixed;

    /**
     * Batch Insert
     *
     * @param array $data
     * @return mixed
     */
    public function batchInsert(array $data): mixed;

    /**
     * Update a resource
     *
     * @param $model
     * @param array $data
     * @return mixed
     */
    public function update($model, array $data): mixed;

    /**
     * delete a resource
     *
     * @param $model
     * @return mixed
     */
    public function delete($model): mixed;
}