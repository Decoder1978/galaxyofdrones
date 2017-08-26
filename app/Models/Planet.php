<?php

namespace Koodilab\Models;

use Illuminate\Database\Eloquent\Model;
use Koodilab\Contracts\Models\Behaviors\Positionable as PositionableContract;
use Koodilab\Models\Behaviors\Positionable;
use Koodilab\Models\Relations\BelongsToResource;
use Koodilab\Models\Relations\BelongsToUser;
use Koodilab\Models\Relations\HasManyGrid;
use Koodilab\Models\Relations\HasManyMission;
use Koodilab\Models\Relations\HasManyPopulation;
use Koodilab\Models\Relations\HasManyStock;

/**
 * Planet.
 *
 * @property int $id
 * @property int $resource_id
 * @property int|null $user_id
 * @property string $name
 * @property string $custom_name
 * @property int $x
 * @property int $y
 * @property int $size
 * @property int|null $capacity
 * @property int|null $supply
 * @property int|null $mining_rate
 * @property int|null $production_rate
 * @property float|null $defense_bonus
 * @property float|null $construction_time_bonus
 * @property \Carbon\Carbon|null $created_at
 * @property \Carbon\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection|Construction[] $constructions
 * @property-read string $display_name
 * @property-read int $resource_quantity
 * @property-read int $used_capacity
 * @property-read int $used_supply
 * @property-read int $used_training_supply
 * @property-read \Illuminate\Database\Eloquent\Collection|Grid[] $grids
 * @property-read \Illuminate\Database\Eloquent\Collection|Movement[] $incomingMovements
 * @property-read \Illuminate\Database\Eloquent\Collection|Mission[] $missions
 * @property-read \Illuminate\Database\Eloquent\Collection|Movement[] $outgoingMovements
 * @property-read \Illuminate\Database\Eloquent\Collection|Population[] $populations
 * @property-read resource $resource
 * @property-read \Illuminate\Database\Eloquent\Collection|Stock[] $stocks
 * @property-read \Illuminate\Database\Eloquent\Collection|Training[] $trainings
 * @property-read \Illuminate\Database\Eloquent\Collection|Upgrade[] $upgrades
 * @property-read User|null $user
 *
 * @method static \Illuminate\Database\Eloquent\Builder|Planet inBounds(\Koodilab\Support\Bounds $bounds)
 * @method static \Illuminate\Database\Eloquent\Builder|Planet whereCapacity($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Planet whereConstructionTimeBonus($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Planet whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Planet whereCustomName($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Planet whereDefenseBonus($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Planet whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Planet whereMiningRate($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Planet whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Planet whereProductionRate($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Planet whereResourceId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Planet whereSize($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Planet whereSupply($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Planet whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Planet whereUserId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Planet whereX($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Planet whereY($value)
 * @mixin \Eloquent
 */
class Planet extends Model implements PositionableContract
{
    use Positionable, BelongsToResource, BelongsToUser, HasManyStock, HasManyPopulation, HasManyGrid, HasManyMission;

    /**
     * The small size.
     *
     * @var int
     */
    const SIZE_SMALL = 0;

    /**
     * The medium size.
     *
     * @var int
     */
    const SIZE_MEDIUM = 1;

    /**
     * The large size.
     *
     * @var int
     */
    const SIZE_LARGE = 2;

    /**
     * The resource count.
     *
     * @var int
     */
    const RESOURCE_COUNT = 3;

    /**
     * The settler count.
     *
     * @var int
     */
    const SETTLER_COUNT = 1;

    /**
     * {@inheritdoc}
     */
    protected $perPage = 30;

    /**
     * {@inheritdoc}
     */
    protected $guarded = [
        'id', 'created_at', 'updated_at',
    ];

    /**
     * {@inheritdoc}
     */
    protected static function boot()
    {
        parent::boot();

        static::updating(function (self $planet) {
            if ($planet->isDirty('user_id')) {
                $planet->custom_name = null;

                $originalUserId = $planet->getOriginal('user_id');

                $planet->incomingMovements()->where('user_id', $originalUserId)->get()->each->delete();
                $planet->outgoingMovements()->where('user_id', $originalUserId)->get()->each->delete();

                $planet->constructions->each->delete();
                $planet->upgrades->each->delete();
                $planet->trainings->each->delete();
            }

            if ($planet->user_id) {
                $planet->user->syncProduction();
            }
        });
    }

    /**
     * Is occupiable?
     *
     * @param User $user
     *
     * @return bool
     */
    public function isOccupiable(User $user)
    {
        if (User::where('capital_id', $this->id)->exists()) {
            return false;
        }

        if (!$user->resources()->where('resources.id', $this->resource_id)->exists()) {
            return false;
        }

        return true;
    }

    /**
     * Get the incoming movements.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function incomingMovements()
    {
        return $this->hasMany(Movement::class, 'end_id');
    }

    /**
     * Get the outgoing movements.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function outgoingMovements()
    {
        return $this->hasMany(Movement::class, 'start_id');
    }

    /**
     * Get the constructions.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasManyThrough
     */
    public function constructions()
    {
        return $this->hasManyThrough(Construction::class, Grid::class);
    }

    /**
     * Get the upgrades.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasManyThrough
     */
    public function upgrades()
    {
        return $this->hasManyThrough(Upgrade::class, Grid::class);
    }

    /**
     * Get the trainings.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasManyThrough
     */
    public function trainings()
    {
        return $this->hasManyThrough(Training::class, Grid::class);
    }

    /**
     * Get the display name attribute.
     *
     * @return string
     */
    public function getDisplayNameAttribute()
    {
        return $this->custom_name ?: $this->name;
    }

    /**
     * Set the custom name attribute.
     *
     * @param string $value
     */
    public function setCustomNameAttribute($value)
    {
        $this->attributes['custom_name'] = $this->name != $value
            ? $value
            : null;
    }

    /**
     * Get the resource quantity attribute.
     *
     * @return int
     */
    public function getResourceQuantityAttribute()
    {
        return static::RESOURCE_COUNT + $this->size;
    }

    /**
     * Get the used capacity attribute.
     *
     * @return int
     */
    public function getUsedCapacityAttribute()
    {
        return $this->stocks()
            ->get(['resource_id', 'quantity', 'updated_at'])
            ->reduce(function ($carry, Stock $stock) {
                return $carry + $stock->setRelation('planet', $this)->quantity;
            }, 0);
    }

    /**
     * Get the used supply attribute.
     *
     * @return int
     */
    public function getUsedSupplyAttribute()
    {
        return $this->populations()
            ->with([
                'unit' => function ($query) {
                    $query->select('id', 'supply');
                },
            ])
            ->get(['unit_id', 'quantity'])
            ->reduce(function ($carry, Population $population) {
                return $carry + $population->quantity * $population->unit->supply;
            }, 0);
    }

    /**
     * Get the used training supply attribute.
     *
     * @return int
     */
    public function getUsedTrainingSupplyAttribute()
    {
        return $this->trainings()
            ->with([
                'unit' => function ($query) {
                    $query->select('id', 'supply');
                },
            ])
            ->get(['unit_id', 'quantity'])
            ->reduce(function ($carry, Training $training) {
                return $carry + $training->quantity * $training->unit->supply;
            }, 0);
    }

    /**
     * Occupy.
     *
     * @param User $user
     */
    public function occupy(User $user)
    {
        if ($this->user_id && $this->user->current_id == $this->id) {
            $this->user->current()->associate($this->user->capital_id);
            $this->user->save();
        }

        $this->user()->associate($user);
        $this->save();

        $building = Building::where('type', Building::TYPE_CENTRAL)
            ->first(['id', 'start_level']);

        /** @var Grid $grid */
        $grid = $this->grids()
            ->where('type', Grid::TYPE_CENTRAL)
            ->first();

        $grid->fill([
            'level' => $building->start_level,
        ]);

        $grid->building()->associate($building);
        $grid->save();
    }

    /**
     * Has required buildings.
     *
     * @param int $except
     *
     * @return bool
     */
    public function hasRequiredBuildings($except = null)
    {
        $constructedIds = $this->grids()
            ->whereNotNull('building_id')
            ->pluck('building_id');

        if ($except) {
            $constructedIds->forget($except);
        }

        foreach (Building::whereIsRoot()->pluck('id') as $id) {
            if (!$constructedIds->contains($id)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Synchronize the buildings.
     */
    public function syncBuildings()
    {
        /** @var Stock $stock */
        $stock = $this->stocks()->firstOrNew([
            'resource_id' => $this->resource_id,
        ]);

        $stock->setRelation('planet', $this)->syncQuantity();

        /** @var \Illuminate\Database\Eloquent\Collection|Grid[] $grids */
        $grids = $this->grids()
            ->whereNotNull('building_id')
            ->where('enabled', true)
            ->get(['building_id', 'level']);

        $attributes = collect($this->attributes)->only([
            'capacity', 'supply', 'mining_rate', 'production_rate', 'defense_bonus', 'construction_time_bonus',
        ])->transform(function () {
            return 0;
        });

        foreach ($grids as $grid) {
            $grid->building->applyModifiers([
                'level' => $grid->level,
            ]);

            $attributes->transform(function ($value, $key) use ($grid) {
                return $value + $grid->building->{$key};
            });
        }

        $this->fill($attributes->filter()->toArray());

        if (!empty($this->attributes['mining_rate']) && !empty($this->attributes['production_rate'])) {
            if ($this->attributes['mining_rate'] > $this->attributes['production_rate']) {
                $this->attributes['mining_rate'] -= $this->attributes['production_rate'];
            } else {
                $this->attributes['production_rate'] = $this->attributes['mining_rate'];
                $this->attributes['mining_rate'] = null;
            }
        }

        $this->save();
    }

    /**
     * {@inheritdoc}
     */
    public function toFeature()
    {
        $status = 'free';

        if ($this->user_id) {
            /** @var User $user */
            $user = auth()->user();
            $status = 'hostile';

            if ($user) {
                if ($user->current_id == $this->id) {
                    $status = 'current';
                } elseif ($user->id == $this->user_id) {
                    $status = 'friendly';
                }
            } elseif ($this->user->capital_id == $this->id) {
                $status = 'capital';
            }
        }

        return [
            'type' => 'Feature',
            'properties' => [
                'id' => $this->id,
                'name' => $this->display_name,
                'type' => 'planet',
                'size' => 32 + ($this->size * 16),
                'status' => $status,
            ],
            'geometry' => [
                'type' => 'Point',
                'coordinates' => [
                    $this->x, $this->y,
                ],
            ],
        ];
    }
}