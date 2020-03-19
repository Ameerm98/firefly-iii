<?php
/**
 * AccountController.php
 * Copyright (c) 2019 james@firefly-iii.org
 *
 * This file is part of Firefly III (https://github.com/firefly-iii).
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

declare(strict_types=1);

namespace FireflyIII\Api\V1\Controllers;

use FireflyIII\Api\V1\Requests\AccountStoreRequest;
use FireflyIII\Api\V1\Requests\AccountUpdateRequest;
use FireflyIII\Helpers\Collector\GroupCollectorInterface;
use FireflyIII\Models\Account;
use FireflyIII\Repositories\Account\AccountRepositoryInterface;
use FireflyIII\Support\Http\Api\AccountFilter;
use FireflyIII\Support\Http\Api\TransactionFilter;
use FireflyIII\Transformers\AccountTransformer;
use FireflyIII\Transformers\AttachmentTransformer;
use FireflyIII\Transformers\PiggyBankTransformer;
use FireflyIII\Transformers\TransactionGroupTransformer;
use FireflyIII\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use League\Fractal\Pagination\IlluminatePaginatorAdapter;
use League\Fractal\Resource\Collection as FractalCollection;
use League\Fractal\Resource\Item;

/**
 * Class AccountController.
 *
 */
class AccountController extends Controller
{
    use AccountFilter, TransactionFilter;
    /** @var AccountRepositoryInterface The account repository */
    private $repository;

    /**
     * AccountController constructor.
     *
     * @codeCoverageIgnore
     */
    public function __construct()
    {
        parent::__construct();
        $this->middleware(
            function ($request, $next) {
                /** @var User $user */
                $user = auth()->user();
                // @var AccountRepositoryInterface repository
                $this->repository = app(AccountRepositoryInterface::class);
                $this->repository->setUser($user);

                return $next($request);
            }
        );
    }

    /**
     * @param Account $account
     *
     * @return JsonResponse
     * @codeCoverageIgnore
     */
    public function attachments(Account $account): JsonResponse
    {
        $manager    = $this->getManager();
        $pageSize   = (int) app('preferences')->getForUser(auth()->user(), 'listPageSize', 50)->data;
        $collection = $this->repository->getAttachments($account);

        $count       = $collection->count();
        $attachments = $collection->slice(($this->parameters->get('page') - 1) * $pageSize, $pageSize);

        // make paginator:
        $paginator = new LengthAwarePaginator($attachments, $count, $pageSize, $this->parameters->get('page'));
        $paginator->setPath(route('api.v1.accounts.attachments', [$account->id]) . $this->buildParams());

        /** @var AttachmentTransformer $transformer */
        $transformer = app(AttachmentTransformer::class);
        $transformer->setParameters($this->parameters);

        $resource = new FractalCollection($attachments, $transformer, 'attachments');
        $resource->setPaginator(new IlluminatePaginatorAdapter($paginator));

        return response()->json($manager->createData($resource)->toArray())->header('Content-Type', 'application/vnd.api+json');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param Account $account
     *
     * @codeCoverageIgnore
     * @return JsonResponse
     */
    public function delete(Account $account): JsonResponse
    {
        $this->repository->destroy($account, null);

        return response()->json([], 204);
    }

    /**
     * Display a listing of the resource.
     *
     * @param Request $request
     *
     * @codeCoverageIgnore
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $manager = $this->getManager();
        $type    = $request->get('type') ?? 'all';
        $this->parameters->set('type', $type);

        // types to get, page size:
        $types    = $this->mapAccountTypes($this->parameters->get('type'));
        $pageSize = (int) app('preferences')->getForUser(auth()->user(), 'listPageSize', 50)->data;

        // get list of accounts. Count it and split it.
        $collection = $this->repository->getAccountsByType($types);
        $count      = $collection->count();
        $accounts   = $collection->slice(($this->parameters->get('page') - 1) * $pageSize, $pageSize);

        // make paginator:
        $paginator = new LengthAwarePaginator($accounts, $count, $pageSize, $this->parameters->get('page'));
        $paginator->setPath(route('api.v1.accounts.index') . $this->buildParams());


        /** @var AccountTransformer $transformer */
        $transformer = app(AccountTransformer::class);
        $transformer->setParameters($this->parameters);

        $resource = new FractalCollection($accounts, $transformer, 'accounts');
        $resource->setPaginator(new IlluminatePaginatorAdapter($paginator));

        return response()->json($manager->createData($resource)->toArray())->header('Content-Type', 'application/vnd.api+json');
    }


    /**
     * List all piggies.
     *
     * @param Account $account
     *
     * @return JsonResponse
     * @codeCoverageIgnore
     *
     */
    public function piggyBanks(Account $account): JsonResponse
    {
        // create some objects:
        $manager = $this->getManager();

        // types to get, page size:
        $pageSize = (int) app('preferences')->getForUser(auth()->user(), 'listPageSize', 50)->data;

        // get list of budgets. Count it and split it.
        $collection = $this->repository->getPiggyBanks($account);
        $count      = $collection->count();
        $piggyBanks = $collection->slice(($this->parameters->get('page') - 1) * $pageSize, $pageSize);

        // make paginator:
        $paginator = new LengthAwarePaginator($piggyBanks, $count, $pageSize, $this->parameters->get('page'));
        $paginator->setPath(route('api.v1.accounts.piggy_banks', [$account->id]) . $this->buildParams());

        /** @var PiggyBankTransformer $transformer */
        $transformer = app(PiggyBankTransformer::class);
        $transformer->setParameters($this->parameters);

        $resource = new FractalCollection($piggyBanks, $transformer, 'piggy_banks');
        $resource->setPaginator(new IlluminatePaginatorAdapter($paginator));

        return response()->json($manager->createData($resource)->toArray())->header('Content-Type', 'application/vnd.api+json');

    }

    /**
     * Show single instance.
     *
     * @param Account $account
     *
     * @return JsonResponse
     */
    public function show(Account $account): JsonResponse
    {
        $manager = $this->getManager();

        /** @var AccountTransformer $transformer */
        $transformer = app(AccountTransformer::class);
        $transformer->setParameters($this->parameters);
        $resource = new Item($account, $transformer, 'accounts');

        return response()->json($manager->createData($resource)->toArray())->header('Content-Type', 'application/vnd.api+json');
    }

    /**
     * Store a new instance.
     *
     * @param AccountStoreRequest $request
     *
     * @return JsonResponse
     */
    public function store(AccountStoreRequest $request): JsonResponse
    {
        $data    = $request->getAllAccountData();
        $account = $this->repository->store($data);
        $manager = $this->getManager();

        /** @var AccountTransformer $transformer */
        $transformer = app(AccountTransformer::class);
        $transformer->setParameters($this->parameters);

        $resource = new Item($account, $transformer, 'accounts');

        return response()->json($manager->createData($resource)->toArray())->header('Content-Type', 'application/vnd.api+json');
    }

    /**
     * Show all transaction groups related to the account.
     *
     * @codeCoverageIgnore
     *
     * @param Request $request
     * @param Account $account
     *
     * @return JsonResponse
     *
     */
    public function transactions(Request $request, Account $account): JsonResponse
    {
        $pageSize = (int) app('preferences')->getForUser(auth()->user(), 'listPageSize', 50)->data;
        $type     = $request->get('type') ?? 'default';
        $this->parameters->set('type', $type);

        // user can overrule page size with limit parameter.
        $limit = $this->parameters->get('limit');
        if (null !== $limit && $limit > 0) {
            $pageSize = $limit;
        }
        $types   = $this->mapTransactionTypes($this->parameters->get('type'));
        $manager = $this->getManager();
        /** @var User $admin */
        $admin = auth()->user();

        // use new group collector:
        /** @var GroupCollectorInterface $collector */
        $collector = app(GroupCollectorInterface::class);
        $collector->setUser($admin)->setAccounts(new Collection([$account]))
                  ->withAPIInformation()->setLimit($pageSize)->setPage($this->parameters->get('page'))->setTypes($types);

        if (null !== $this->parameters->get('start') && null !== $this->parameters->get('end')) {
            $collector->setRange($this->parameters->get('start'), $this->parameters->get('end'));
        }

        $paginator = $collector->getPaginatedGroups();
        $paginator->setPath(route('api.v1.accounts.transactions', [$account->id]) . $this->buildParams());
        $groups = $paginator->getCollection();

        /** @var TransactionGroupTransformer $transformer */
        $transformer = app(TransactionGroupTransformer::class);
        $transformer->setParameters($this->parameters);

        $resource = new FractalCollection($groups, $transformer, 'transactions');
        $resource->setPaginator(new IlluminatePaginatorAdapter($paginator));

        return response()->json($manager->createData($resource)->toArray())->header('Content-Type', 'application/vnd.api+json');
    }

    /**
     * Update account.
     *
     * @param AccountUpdateRequest $request
     * @param Account              $account
     *
     * @return JsonResponse
     */
    public function update(AccountUpdateRequest $request, Account $account): JsonResponse
    {
        $data         = $request->getUpdateData();
        $data['type'] = config('firefly.shortNamesByFullName.' . $account->accountType->type);
        $this->repository->update($account, $data);
        $manager = $this->getManager();

        /** @var AccountTransformer $transformer */
        $transformer = app(AccountTransformer::class);
        $transformer->setParameters($this->parameters);
        $resource = new Item($account, $transformer, 'accounts');

        return response()->json($manager->createData($resource)->toArray())->header('Content-Type', 'application/vnd.api+json');
    }
}
