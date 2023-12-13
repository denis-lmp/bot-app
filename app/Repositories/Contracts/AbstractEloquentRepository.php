<?php
/**
 * Created by PhpStorm.
 * User: Denis Kostaev
 * Date: 11/12/2023
 * Time: 12:17
 */

namespace App\Repositories\Contracts;

use App\Models\CryptoTrading;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

abstract class AbstractEloquentRepository implements BaseRepository
{
    /**
     * Name of the Model with absolute namespace
     *
     * @var string
     */
    protected string $modelName;

    /**
     * Instance that extends Illuminate\Database\Eloquent\Model
     *
     * @var Model
     */
    protected Model $model;

    /**
     * Constructor
     *
     * AbstractEloquentRepository constructor.
     * @param  Model  $model
     */
    public function __construct(Model $model)
    {
        $this->model = $model;
    }

    /**
     * Get Model instance
     *
     * @return Model
     */
    public function getModel(): Model
    {
        return $this->model;
    }

    /**
     * @param $id
     * @return mixed
     */
    public function findOne($id): mixed
    {
        return $this->model->find($id);
    }

    /**
     * @param  array  $criteria
     * @return mixed
     */
    public function findOneBy(array $criteria): mixed
    {
        return $this->model->where($criteria)->first();
    }

    /**
     * @param  array  $searchCriteria
     * @return mixed
     */
    public function findBy(array $searchCriteria = []): mixed
    {
        $limit = !empty($searchCriteria['per_page']) ? (int)$searchCriteria['per_page'] : 15; // it's needed for pagination

        $queryBuilder = $this->bindSearchCriteria($searchCriteria);

        return $queryBuilder->paginate($limit);
    }

    /**
     * @param  array  $searchCriteria
     * @return mixed
     */
    public function bindSearchCriteria(array $searchCriteria = []): mixed
    {
        return $this->model->where(function ($query) use ($searchCriteria) {
            $this->applySearchCriteriaInQueryBuilder($query, $searchCriteria);
        });
    }

    /**
     * Apply condition on query builder based on search criteria
     *
     * @param  Object  $queryBuilder
     * @param  array  $searchCriteria
     * @return object
     */
    protected function applySearchCriteriaInQueryBuilder(object $queryBuilder, array $searchCriteria = []): object
    {
        foreach ($searchCriteria as $key => $value) {
            //skip pagination related query params
            if (in_array($key, ['page', 'per_page'])) {
                continue;
            }

            //we can pass multiple params for a filter with commas
            $allValues = explode(',', $value);

            if (count($allValues) > 1) {
                $queryBuilder->whereIn($key, $allValues);
            } else {
                $operator = '=';
                $queryBuilder->where($key, $operator, $value);
            }
        }

        return $queryBuilder;
    }

    /**
     * @param  array  $searchCriteria
     * @return mixed
     */
    public function findAllBy(array $searchCriteria = []): mixed
    {
        $queryBuilder = $this->bindSearchCriteria($searchCriteria);

        return $queryBuilder->get();
    }

    /**
     * @param  string  $orderBy
     * @param  string  $orderDirection
     * @return mixed
     */
    private function applyOrder(string $orderBy, string $orderDirection): mixed
    {
        return $this->model->orderBy($orderBy, $orderDirection);
    }

    /**
     * @param  string  $orderBy
     * @param  string  $orderDirection
     * @return Collection
     */
    public function getAllOrdered(string $orderBy = 'id', string $orderDirection = 'asc'): Collection
    {
        return $this->applyOrder($orderBy, $orderDirection)->get();
    }

    /**
     * @param  string  $orderBy
     * @param  string  $orderDirection
     * @return CryptoTrading
     */
    public function getLastMadeTrade(string $orderBy = 'id', string $orderDirection = 'asc'): CryptoTrading
    {
       return $this->applyOrder($orderBy, $orderDirection)->first();
    }

    /**
     * @param  array  $data
     * @return mixed
     */
    public function batchInsert(array $data): mixed
    {
        return $this->model->insert($data);
    }

    /**
     * @param $model
     * @param  array  $data
     * @return mixed
     */
    public function update($model, array $data): mixed
    {
        $fillAbleProperties = $this->model->getFillable();

        foreach ($data as $key => $value) {
            // update only fillAble properties
            if (in_array($key, $fillAbleProperties)) {
                $model->$key = $value;
            }
        }

        // update the model
        $model->save();

        return $model;
    }

    /**
     * @param  array  $data
     * @return mixed
     */
    public function save(array $data): mixed
    {
        return $this->model->create($data);
    }

    /**
     * @param  string  $key
     * @param  array  $values
     * @return mixed
     */
    public function findIn(string $key, array $values): mixed
    {
        return $this->model->whereIn($key, $values)->get();
    }

    /**
     * @param $model
     * @return mixed
     */
    public function delete($model): mixed
    {
        return $model->delete();
    }

    /**
     * @param $ticker
     * @param $period
     * @return mixed
     */
    public function getRowsForDateRange($ticker, $period): mixed
    {
        $queryBuilder = $this->bindSearchCriteria($ticker);

        if (is_array($period)) {
            return $this->getRowsForCustomDateRange($queryBuilder, $period);
        }

        $startDate = $this->getStartDate($period);
        $endDate   = $this->getEndDate($period);

        return $this->applyDateCriteria($queryBuilder, $startDate, $endDate);
    }

    /**
     * @param $queryBuilder
     * @param $period
     * @return bool|array
     */
    protected function getRowsForCustomDateRange($queryBuilder, $period): bool|array
    {
        list($from, $to) = $period;

        $fromDate = Carbon::parse($from);
        $toDate   = Carbon::parse($to);

        if ($fromDate->isValid() && $toDate->isValid()) {
            return $this->applyDateCriteria($queryBuilder, $from, $to);
        } else {
            return false;
        }
    }

    /**
     * @param $queryBuilder
     * @param $startDate
     * @param $endDate
     * @return mixed
     */
    protected function applyDateCriteria($queryBuilder, $startDate, $endDate): mixed
    {
        return $queryBuilder->whereBetween('created_at', [$startDate, $endDate])->get();
    }

    /**
     * @param $period
     * @return Carbon
     */
    protected function getStartDate($period): Carbon
    {
        $startDate = Carbon::now();

        switch ($period) {
            case 'current_month':
                $startDate->startOfMonth();
                break;
            case 'previous_month':
                $startDate->startOfMonth()->subMonth();
                break;
            case 'current_week':
                $startDate->startOfWeek();
                break;
            case 'previous_week':
                $startDate->startOfWeek()->subWeek();
                break;
        }

        return $startDate;
    }

    /**
     * @param $period
     * @return Carbon
     */
    protected function getEndDate($period): Carbon
    {
        $endDate = Carbon::now();

        switch ($period) {
            case 'current_month':
                $endDate->endOfMonth();
                break;
            case 'previous_month':
                $endDate->subMonth()->endOfMonth();
                break;
            case 'current_week':
                $endDate->endOfWeek();
                break;
            case 'previous_week':
                $endDate->subWeek()->endOfWeek();
                break;
        }

        return $endDate;
    }
}