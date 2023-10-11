<?php

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

use Spatie\QueryBuilder\AllowedInclude;

use Spatie\QueryBuilder\Exceptions\InvalidIncludeQuery;
use Spatie\QueryBuilder\Includes\IncludeInterface;
use Spatie\QueryBuilder\Includes\IncludedCount;
use Spatie\QueryBuilder\QueryBuilder;
use Spatie\QueryBuilder\Tests\TestClasses\Models\MorphModel;
use Spatie\QueryBuilder\Tests\TestClasses\Models\RelatedModel;
use Spatie\QueryBuilder\Tests\TestClasses\Models\TestModel;
use function PHPUnit\Framework\assertObjectHasProperty;

beforeEach(function () {
    $this->models = TestModel::factory()->count(5)->create();

    $this->models->each(function (TestModel $model) {
        $model
            ->relatedModels()->create(['name' => 'Test'])
            ->nestedRelatedModels()->create(['name' => 'Test']);

        $model->relatedModels()->create(['name' => 'Test']);

        $model->morphModels()->create(['name' => 'Test']);

        $model->relatedThroughPivotModels()->create([
            'id' => $model->id + 1,
            'name' => 'Test',
        ]);
    });
});

it('does not require includes', function () {
    $models = QueryBuilder::for(TestModel::class, new Request())
        ->allowedIncludes('relatedModels')
        ->get();

    expect($models)->toHaveCount(TestModel::count());
});

it('can handle empty includes', function () {
    $models = QueryBuilder::for(TestModel::class, new Request())
        ->allowedIncludes([
            null,
            [],
            '',
        ])
        ->get();

    expect($models)->toHaveCount(TestModel::count());
});

it('can include model relations', function () {
    $models = createQueryFromIncludeRequest('relatedModels')
        ->allowedIncludes('relatedModels')
        ->get();

    assertRelationLoaded($models, 'relatedModels');
});

it('can lazy include model relations', function () {
    $model = createLazyQueryFromIncludeRequest('relatedModels')
        ->allowedIncludes('relatedModels');

    assertRelationLoaded(collect([$model]), 'relatedModels');
});

it('can include model relations by alias', function () {
    $models = createQueryFromIncludeRequest('include-alias')
        ->allowedIncludes(AllowedInclude::relationship('include-alias', 'relatedModels'))
        ->get();

    assertRelationLoaded($models, 'relatedModels');
});

it('can lazy model relations by alias', function () {
    $model = createLazyQueryFromIncludeRequest('include-alias')
        ->allowedIncludes(AllowedInclude::relationship('include-alias', 'relatedModels'));

    assertRelationLoaded(collect([$model]), 'relatedModels');
});

it('can include an includes callback', function () {
    $models = createQueryFromIncludeRequest('relatedModels')
        ->allowedIncludes([
            AllowedInclude::callback('relatedModels', fn ($query) => $query->whereKey(RelatedModel::first())),
        ])
        ->get();

    assertRelationLoaded($models, 'relatedModels');

    $models = $models->reverse();
    expect($models->pop()->relatedModels)->toHaveCount(1);
    expect($models)->each(
        fn ($model) => $model->relatedModels->toHaveCount(0)
    );
});

it('can lazy include an includes callback', function () {
    $model = createLazyQueryFromIncludeRequest('relatedModels')
        ->allowedIncludes([
            AllowedInclude::callback('relatedModels', fn ($query) => $query->whereKey(RelatedModel::first())),
        ]);

    assertRelationLoaded(collect([$model]), 'relatedModels');

    expect($model->relatedModels)->toHaveCount(1);
});

it('can include an includes count', function () {
    $model = createQueryFromIncludeRequest('relatedModelsCount')
        ->allowedIncludes('relatedModelsCount')
        ->first();

    $this->assertNotNull($model->related_models_count);
});

it('can lazy include an includes count', function () {
    $model = createLazyQueryFromIncludeRequest('relatedModelsCount')
        ->allowedIncludes('relatedModelsCount');

    $this->assertNotNull($model->related_models_count);
});

test('allowing an include also allows the include count', function () {
    $model = createQueryFromIncludeRequest('relatedModelsCount')
        ->allowedIncludes('relatedModels')
        ->first();

    $this->assertNotNull($model->related_models_count);
});

test('allowing an include also allows the lazy include count', function () {
    $model = createLazyQueryFromIncludeRequest('relatedModelsCount')
        ->allowedIncludes('relatedModels');

    $this->assertNotNull($model->related_models_count);
});

it('can include an includes exists', function () {
    $model = createQueryFromIncludeRequest('relatedModelsExists')
        ->allowedIncludes('relatedModelsExists')
        ->first();

    $this->assertNotNull($model->related_models_exists);
    $this->assertIsBool($model->related_models_exists);
});

it('can lazy include an includes exists', function () {
    $model = createLazyQueryFromIncludeRequest('relatedModelsExists')
        ->allowedIncludes('relatedModelsExists');

    $this->assertNotNull($model->related_models_exists);
    $this->assertIsBool($model->related_models_exists);
});

test('allowing an include also allows the include exists', function () {
    $model = createQueryFromIncludeRequest('relatedModelsExists')
        ->allowedIncludes('relatedModels')
        ->first();

    $this->assertNotNull($model->related_models_exists);
});

test('allowing an include also allows the lazy include exists', function () {
    $model = createLazyQueryFromIncludeRequest('relatedModelsExists')
        ->allowedIncludes('relatedModels');

    $this->assertNotNull($model->related_models_exists);
});

it('can include nested model relations', function () {
    $models = createQueryFromIncludeRequest('relatedModels.nestedRelatedModels')
        ->allowedIncludes('relatedModels.nestedRelatedModels')
        ->get();

    $models->each(function (Model $model) {
        assertRelationLoaded($model->relatedModels, 'nestedRelatedModels');
    });
});

it('can lazy include nested model relations', function () {
    $model = createLazyQueryFromIncludeRequest('relatedModels.nestedRelatedModels')
        ->allowedIncludes('relatedModels.nestedRelatedModels');

    assertRelationLoaded($model->relatedModels, 'nestedRelatedModels');
});

it('can include nested model relations by alias', function () {
    $this->markTestSkipped('See #522');

    $models = createQueryFromIncludeRequest('nested-alias')
        ->allowedIncludes(
            AllowedInclude::relationship('nested-alias', 'relatedModels.nestedRelatedModels')
        )
        ->get();

    $models->each(function (Model $model) {
        assertRelationLoaded($model->relatedModels, 'nestedRelatedModels');
    });
});

it('can lazy include nested model relations by alias', function () {
    $this->markTestSkipped('See #522');

    $model = createLazyQueryFromIncludeRequest('nested-alias')
        ->allowedIncludes(
            AllowedInclude::relationship('nested-alias', 'relatedModels.nestedRelatedModels')
        );

    assertRelationLoaded($model->relatedModels, 'nestedRelatedModels');
});

it('can include model relations from nested model relations', function () {
    $models = createQueryFromIncludeRequest('relatedModels')
        ->allowedIncludes('relatedModels.nestedRelatedModels')
        ->get();

    assertRelationLoaded($models, 'relatedModels');
});

it('can lazy include model relations from nested model relations', function () {
    $model = createLazyQueryFromIncludeRequest('relatedModels')
        ->allowedIncludes('relatedModels.nestedRelatedModels');

    assertRelationLoaded(collect([$model]), 'relatedModels');
});

test('allowing a nested include only allows the include count for the first level', function () {
    $model = createQueryFromIncludeRequest('relatedModelsCount')
        ->allowedIncludes('relatedModels.nestedRelatedModels')
        ->first();

    $this->assertNotNull($model->related_models_count);

    $this->expectException(InvalidIncludeQuery::class);

    createQueryFromIncludeRequest('nestedRelatedModelsCount')
        ->allowedIncludes('relatedModels.nestedRelatedModels')
        ->first();

    $this->expectException(InvalidIncludeQuery::class);

    createQueryFromIncludeRequest('related-models.nestedRelatedModelsCount')
        ->allowedIncludes('relatedModels.nestedRelatedModels')
        ->first();
});

test('allowing a nested include only allows the lazy include count for the first level', function () {
    $model = createLazyQueryFromIncludeRequest('relatedModelsCount')
        ->allowedIncludes('relatedModels.nestedRelatedModels');

    $this->assertNotNull($model->related_models_count);

    $this->expectException(InvalidIncludeQuery::class);

    createLazyQueryFromIncludeRequest('nestedRelatedModelsCount')
        ->allowedIncludes('relatedModels.nestedRelatedModels');

    $this->expectException(InvalidIncludeQuery::class);

    createLazyQueryFromIncludeRequest('related-models.nestedRelatedModelsCount')
        ->allowedIncludes('relatedModels.nestedRelatedModels');
});

test('allowing a nested include only allows the include exists for the first level', function () {
    $model = createQueryFromIncludeRequest('relatedModelsExists')
        ->allowedIncludes('relatedModels.nestedRelatedModels')
        ->first();

    $this->assertNotNull($model->related_models_exists);

    $this->expectException(InvalidIncludeQuery::class);

    createQueryFromIncludeRequest('nestedRelatedModelsExists')
        ->allowedIncludes('relatedModels.nestedRelatedModels')
        ->first();

    $this->expectException(InvalidIncludeQuery::class);

    createQueryFromIncludeRequest('related-models.nestedRelatedModelsExists')
        ->allowedIncludes('relatedModels.nestedRelatedModels')
        ->first();
});

test('allowing a nested include only allows the lazy include exists for the first level', function () {
    $model = createLazyQueryFromIncludeRequest('relatedModelsExists')
        ->allowedIncludes('relatedModels.nestedRelatedModels');

    $this->assertNotNull($model->related_models_exists);

    $this->expectException(InvalidIncludeQuery::class);

    createLazyQueryFromIncludeRequest('nestedRelatedModelsExists')
        ->allowedIncludes('relatedModels.nestedRelatedModels');

    $this->expectException(InvalidIncludeQuery::class);

    createLazyQueryFromIncludeRequest('related-models.nestedRelatedModelsExists')
        ->allowedIncludes('relatedModels.nestedRelatedModels');
});

it('can include morph model relations', function () {
    $models = createQueryFromIncludeRequest('morphModels')
        ->allowedIncludes('morphModels')
        ->get();

    assertRelationLoaded($models, 'morphModels');
});

it('can lazy include morph model relations', function () {
    $model = createLazyQueryFromIncludeRequest('morphModels')
        ->allowedIncludes('morphModels');

    assertRelationLoaded(collect([$model]), 'morphModels');
});

it('can include reverse morph model relations', function () {
    $request = new Request([
        'include' => 'parent',
    ]);

    $models = QueryBuilder::for(MorphModel::class, $request)
        ->allowedIncludes('parent')
        ->get();

    assertRelationLoaded($models, 'parent');
});

it('can lazy include reverse morph model relations', function () {
    $request = new Request([
        'include' => 'parent',
    ]);

    $model = QueryBuilder::for(MorphModel::first(), $request)
        ->allowedIncludes('parent');

    assertRelationLoaded(collect([$model]), 'parent');
});

it('can include camel case includes', function () {
    $models = createQueryFromIncludeRequest('relatedModels')
        ->allowedIncludes('relatedModels')
        ->get();

    assertRelationLoaded($models, 'relatedModels');
});

it('can lazy include camel case includes', function () {
    $model = createLazyQueryFromIncludeRequest('relatedModels')
        ->allowedIncludes('relatedModels');

    assertRelationLoaded(collect([$model]), 'relatedModels');
});

it('can include models on an empty collection', function () {
    TestModel::query()->delete();

    $models = createQueryFromIncludeRequest('relatedModels')
        ->allowedIncludes('relatedModels')
        ->get();

    expect($models)->toHaveCount(0);
});

it('guards against invalid lazy includes', function () {
    $this->expectException(InvalidIncludeQuery::class);

    createLazyQueryFromIncludeRequest('random-model')
        ->allowedIncludes('relatedModels');
});

it('can allow multiple includes', function () {
    $models = createQueryFromIncludeRequest('relatedModels')
        ->allowedIncludes('relatedModels', 'otherRelatedModels')
        ->get();

    assertRelationLoaded($models, 'relatedModels');
});

it('can allow multiple lazy includes', function () {
    $model = createLazyQueryFromIncludeRequest('relatedModels')
        ->allowedIncludes('relatedModels', 'otherRelatedModels');

    assertRelationLoaded(collect([$model]), 'relatedModels');
});

it('can allow multiple includes as an array', function () {
    $models = createQueryFromIncludeRequest('relatedModels')
        ->allowedIncludes(['relatedModels', 'otherRelatedModels'])
        ->get();

    assertRelationLoaded($models, 'relatedModels');
});

it('can allow multiple lazy includes as an array', function () {
    $model = createLazyQueryFromIncludeRequest('relatedModels')
        ->allowedIncludes(['relatedModels', 'otherRelatedModels']);

    assertRelationLoaded(collect([$model]), 'relatedModels');
});

it('can remove duplicate includes from nested includes', function () {
    $query = createQueryFromIncludeRequest('relatedModels')
        ->allowedIncludes('relatedModels.nestedRelatedModels', 'relatedModels');

    $property = (new ReflectionClass($query))->getProperty('allowedIncludes');
    $property->setAccessible(true);

    $includes = $property->getValue($query)->map(function (AllowedInclude $allowedInclude) {
        return $allowedInclude->getName();
    });

    expect($includes->contains('relatedModels'))->toBeTrue();
    expect($includes->contains('relatedModelsCount'))->toBeTrue();
    expect($includes->contains('relatedModels.nestedRelatedModels'))->toBeTrue();
});

it('can include multiple model relations', function () {
    $models = createQueryFromIncludeRequest('relatedModels,otherRelatedModels')
        ->allowedIncludes(['relatedModels', 'otherRelatedModels'])
        ->get();

    assertRelationLoaded($models, 'relatedModels');
    assertRelationLoaded($models, 'otherRelatedModels');
});

it('can lazy include multiple model relations', function () {
    $model = createLazyQueryFromIncludeRequest('relatedModels,otherRelatedModels')
        ->allowedIncludes(['relatedModels', 'otherRelatedModels']);

    assertRelationLoaded(collect([$model]), 'relatedModels');
    assertRelationLoaded(collect([$model]), 'otherRelatedModels');
});

it('can query included many to many relationships', function () {
    DB::enableQueryLog();

    createQueryFromIncludeRequest('relatedThroughPivotModels')
        ->allowedIncludes('relatedThroughPivotModels')
        ->get();

    // Based on the following query: TestModel::with('relatedThroughPivotModels')->get();
    // Without where-clause as that differs per Laravel version
    //dump(DB::getQueryLog());
    $this->assertQueryLogContains('select `related_through_pivot_models`.*, `pivot_models`.`test_model_id` as `pivot_test_model_id`, `pivot_models`.`related_through_pivot_model_id` as `pivot_related_through_pivot_model_id` from `related_through_pivot_models` inner join `pivot_models` on `related_through_pivot_models`.`id` = `pivot_models`.`related_through_pivot_model_id` where `pivot_models`.`test_model_id` in (1, 2, 3, 4, 5)');
});

it('can query lazy included many to many relationships', function () {
    DB::enableQueryLog();

    createLazyQueryFromIncludeRequest('relatedThroughPivotModels')
        ->allowedIncludes('relatedThroughPivotModels');

    // Based on the following query: TestModel::with('relatedThroughPivotModels')->get();
    // Without where-clause as that differs per Laravel version
    $this->assertQueryLogContains('select `related_through_pivot_models`.*, `pivot_models`.`test_model_id` as `pivot_test_model_id`, `pivot_models`.`related_through_pivot_model_id` as `pivot_related_through_pivot_model_id` from `related_through_pivot_models` inner join `pivot_models` on `related_through_pivot_models`.`id` = `pivot_models`.`related_through_pivot_model_id` where `pivot_models`.`test_model_id` in (1)');
});

it('returns correct id when including many to many relationship', function () {
    $models = createQueryFromIncludeRequest('relatedThroughPivotModels')
        ->allowedIncludes('relatedThroughPivotModels')
        ->get();

    $relatedModel = $models->first()->relatedThroughPivotModels->first();

    expect($relatedModel->pivot->related_through_pivot_model_id)->toEqual($relatedModel->id);
});

it('returns correct id when lazy including many to many relationship', function () {
    $model = createLazyQueryFromIncludeRequest('relatedThroughPivotModels')
        ->allowedIncludes('relatedThroughPivotModels');

    $relatedModel = $model->relatedThroughPivotModels->first();

    expect($relatedModel->pivot->related_through_pivot_model_id)->toEqual($relatedModel->id);
});

test('an invalid include query exception contains the unknown and allowed includes', function () {
    $exception = new InvalidIncludeQuery(collect(['unknown include']), collect(['allowed include']));

    expect($exception->unknownIncludes->all())->toEqual(['unknown include']);
    expect($exception->allowedIncludes->all())->toEqual(['allowed include']);
});

it('can alias multiple allowed includes', function () {
    $request = new Request([
        'include' => 'relatedModelsCount,relationShipAlias',
    ]);

    $models = QueryBuilder::for(TestModel::class, $request)
        ->allowedIncludes([
            AllowedInclude::count('relatedModelsCount'),
            AllowedInclude::relationship('relationShipAlias', 'otherRelatedModels'),
        ])
        ->get();

    assertRelationLoaded($models, 'otherRelatedModels');
    $models->each(function ($model) {
        $this->assertNotNull($model->related_models_count);
    });
});

it('can alias multiple allowed lazy includes', function () {
    $request = new Request([
        'include' => 'relatedModelsCount,relationShipAlias',
    ]);

    $model = QueryBuilder::for(TestModel::first(), $request)
        ->allowedIncludes([
            AllowedInclude::count('relatedModelsCount'),
            AllowedInclude::relationship('relationShipAlias', 'otherRelatedModels'),
        ]);

    assertRelationLoaded(collect([$model]), 'otherRelatedModels');
    $this->assertNotNull($model->related_models_count);
});

it('can include custom include class', function () {
    $includeClass = new class () implements IncludeInterface {
        public function __invoke(Builder $query, string $include): Builder
        {
            // TODO:
            // when drop laravel 6 and 7
            // use withAggregate instead withCount
            return $query->withCount($include);
        }
    };

    $modelResult = createQueryFromIncludeRequest('relatedModels')
        ->allowedIncludes(AllowedInclude::custom('relatedModels', $includeClass))
        ->first();

    $this->assertNotNull($modelResult->related_models_count);
});

it('can lazy include custom include class', function () {
    $includeClass = new class () implements IncludeInterface {
        public function __invoke(Builder|Model $query, string $include): Model
        {
            // TODO:
            // when drop laravel 6 and 7
            // use withAggregate instead withCount
            return $query instanceof Model
                ? $query->loadCount($include)
                : $query->withCount($include);
        }
    };

    $modelResult = createLazyQueryFromIncludeRequest('relatedModels')
        ->allowedIncludes(AllowedInclude::custom('relatedModels', $includeClass));

    $this->assertNotNull($modelResult->related_models_count);
});

it('can include custom include class by alias', function () {
    $includeClass = new class () implements IncludeInterface {
        public function __invoke(Builder $query, string $include): Builder
        {
            // TODO:
            // when drop laravel 6 and 7
            // use withAggregate instead withCount
            return $query->withCount($include);
        }
    };

    $modelResult = createQueryFromIncludeRequest('relatedModelsCount')
        ->allowedIncludes(AllowedInclude::custom('relatedModelsCount', $includeClass, 'relatedModels'))
        ->first();

    $this->assertNotNull($modelResult->related_models_count);
});

it('can lazy include custom include class by alias', function () {
    $includeClass = new class () implements IncludeInterface {
        public function __invoke(Builder|Model $query, string $include): Builder|Model
        {
            // TODO:
            // when drop laravel 6 and 7
            // use withAggregate instead withCount
            return $query instanceof Model
                ? $query->loadCount($include)
                : $query->withCount($include);
        }
    };

    $modelResult = createLazyQueryFromIncludeRequest('relatedModelsCount')
        ->allowedIncludes(AllowedInclude::custom('relatedModelsCount', $includeClass, 'relatedModels'));

    $this->assertNotNull($modelResult->related_models_count);
});

it('can take an argument for custom column name resolution', function () {
    $include = AllowedInclude::custom('property_name', new IncludedCount(), 'property_column_name');

    expect($include)->toBeInstanceOf(Collection::class);
    expect($include->first())->toBeInstanceOf(AllowedInclude::class);
    assertObjectHasProperty('internalName', $include->first());
    assertObjectHasProperty('internalName', $include->first());
});

it('can include a custom base query with select', function () {
    $request = new Request([
        'include' => 'relatedModelsCount',
    ]);

    $modelResult = QueryBuilder::for(TestModel::select('id', 'name'), $request)
        ->allowedIncludes(AllowedInclude::custom('relatedModelsCount', new IncludedCount(), 'relatedModels'))
        ->first();

    $this->assertNotNull($modelResult->related_models_count);
});

// Helpers
function createQueryFromIncludeRequest(string $includes): QueryBuilder
{
    $request = new Request([
        'include' => $includes,
    ]);

    return QueryBuilder::for(TestModel::class, $request);
}

function createLazyQueryFromIncludeRequest(string $includes): QueryBuilder
{
    $request = new Request([
        'include' => $includes,
    ]);

    return QueryBuilder::for(TestModel::first(), $request);
}

function assertRelationLoaded(Collection $collection, string $relation)
{
    $hasModelWithoutRelationLoaded = $collection
        ->contains(function (Model|QueryBuilder $model) use ($relation) {
            return ! $model->relationLoaded($relation);
        });

    test()->assertFalse($hasModelWithoutRelationLoaded, "The `{$relation}` relation was expected but not loaded.");
}
