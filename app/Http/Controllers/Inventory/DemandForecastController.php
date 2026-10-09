<?php

namespace App\Http\Controllers\Inventory;

use App\Enums\MovementType;
use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreDemandForecastPlanRequest;
use App\Models\DemandPlan;
use App\Models\InventoryItem;
use App\Models\ItemCategory;
use App\Models\StockMovement;
use App\Models\StorageLocation;
use App\Services\AiDemandForecastService;
use App\Services\DemandForecastService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Str;
use Illuminate\View\View;
use RuntimeException;
use Throwable;

/**
 * Replaces the hand-typed demand plan form that used to sit on the
 * procurement page. The numbers now come from recorded stock movements
 * instead of from whoever was filling in the form.
 */
class DemandForecastController extends Controller implements HasMiddleware
{
    public function __construct(
        private readonly DemandForecastService $forecasts,
        private readonly AiDemandForecastService $aiForecasts,
    ) {}

    /**
     * @return array<int, Middleware|string>
     */
    public static function middleware(): array
    {
        return [
            'auth:web,admin,super_admin',
            // Reading the forecast is a reporting activity; saving a plan
            // commits a reorder decision and needs the planning permission.
            new Middleware('can:'.Permission::ViewReports->value, only: ['index']),
            new Middleware('can:'.Permission::GenerateForecasts->value, only: ['store', 'refresh']),
        ];
    }

    public function index(Request $request): View
    {
        $analysisDays = (int) $request->integer('analysis_days', DemandForecastService::DEFAULT_ANALYSIS_DAYS);
        $forecastDays = (int) $request->integer('forecast_days', DemandForecastService::DEFAULT_FORECAST_DAYS);

        // Clamp rather than reject: these arrive from a <select> on the page,
        // and a nonsense value in the query string should not 500 the screen.
        $analysisDays = max(7, min(365, $analysisDays));
        $forecastDays = max(7, min(180, $forecastDays));

        $analysisStartsAt = now()->subDays($analysisDays);
        $forecasts = $this->forecasts->forecastAll($analysisDays, $forecastDays, $analysisStartsAt);

        $datasetDates = $request->validate([
            'dataset_from' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:'.$analysisStartsAt->toDateString(), 'before_or_equal:today'],
            'dataset_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:dataset_from', 'after_or_equal:'.$analysisStartsAt->toDateString(), 'before_or_equal:today'],
        ], [
            'dataset_from.after_or_equal' => 'The dataset start date must be within the selected analysis window.',
            'dataset_from.before_or_equal' => 'The dataset start date cannot be in the future.',
            'dataset_to.after_or_equal' => 'The dataset end date must be on or after the start date and within the selected analysis window.',
            'dataset_to.before_or_equal' => 'The dataset end date cannot be in the future.',
        ]);

        $datasetSearch = Str::limit(trim((string) $request->query('dataset_search')), 100, '');
        $datasetCategoryId = filter_var($request->query('dataset_category_id'), FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE);
        $datasetLocationId = filter_var($request->query('dataset_location_id'), FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE);
        $datasetMovementType = in_array($request->query('dataset_movement_type'), MovementType::consumptionValues(), true)
            ? (string) $request->query('dataset_movement_type')
            : null;
        $datasetFrom = $this->validDatasetDate($datasetDates['dataset_from'] ?? null);
        $datasetTo = $this->validDatasetDate($datasetDates['dataset_to'] ?? null);

        $datasetMovements = StockMovement::query()
            ->with([
                'item:id,name,sku,unit,category_id',
                'item.category:id,name',
                'fromLocation:id,name,code',
                'toLocation:id,name,code',
            ])
            ->whereHas('item', fn ($query) => $query->active())
            ->whereIn('movement_type', MovementType::consumptionValues())
            ->where('moved_at', '>=', $analysisStartsAt)
            ->when($datasetSearch !== '', fn ($query) => $query->whereHas('item', fn ($item) => $item
                ->where('name', 'like', "%{$datasetSearch}%")
                ->orWhere('sku', 'like', "%{$datasetSearch}%")))
            ->when($datasetCategoryId !== null, fn ($query) => $query->whereHas(
                'item',
                fn ($item) => $item->where('category_id', $datasetCategoryId),
            ))
            ->when($datasetMovementType !== null, fn ($query) => $query->where('movement_type', $datasetMovementType))
            ->when($datasetLocationId !== null, fn ($query) => $query->where('from_location_id', $datasetLocationId))
            ->when($datasetFrom !== null, fn ($query) => $query->whereDate('moved_at', '>=', $datasetFrom))
            ->when($datasetTo !== null, fn ($query) => $query->whereDate('moved_at', '<=', $datasetTo))
            ->latest('moved_at')
            ->latest('id')
            ->paginate(15, ['*'], 'dataset_page')
            ->withQueryString();

        // Opening the screen fills an empty cache by itself. Anyone who may read
        // this page already sees the same recorded consumption in the table
        // below, and the run is claimed under a lock, so the model is asked for
        // at most once per window however many people arrive at once.
        $aiForecast = $this->aiForecasts->ensure($request->user(), $analysisDays, $forecastDays);
        $aiItems = collect($aiForecast['items'] ?? []);

        $risk = in_array($request->query('risk'), ['high', 'medium', 'low'], true)
            ? (string) $request->query('risk')
            : null;
        $categoryId = filter_var($request->query('category_id'), FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE);
        $search = Str::limit(trim((string) $request->query('search')), 100, '');
        $attentionOnly = $request->boolean('attention_only');

        if ($risk !== null) {
            $aiItems = $aiItems->where('risk_level', $risk);
        }
        if ($categoryId !== null) {
            $aiItems = $aiItems->where('category_id', $categoryId);
        }
        if ($attentionOnly) {
            $aiItems = $aiItems->whereIn('risk_level', ['high', 'medium']);
        }
        if ($search !== '') {
            $needle = Str::lower($search);
            $aiItems = $aiItems->filter(fn (array $item) => Str::contains(
                Str::lower(($item['item_name'] ?? '').' '.($item['sku'] ?? '')),
                $needle,
            ));
        }

        return view('inventory.demand_forecast.index', [
            'forecasts' => $forecasts,
            'analysisDays' => $analysisDays,
            'analysisStartsAt' => $analysisStartsAt,
            'forecastDays' => $forecastDays,
            'aiForecast' => $aiForecast,
            'aiItems' => $aiItems->values(),
            'categories' => ItemCategory::query()->active()->orderBy('name')->get(['id', 'name']),
            'aiFilters' => compact('risk', 'categoryId', 'search', 'attentionOnly'),
            'datasetFilters' => [
                'search' => $datasetSearch,
                'category_id' => $datasetCategoryId,
                'movement_type' => $datasetMovementType,
                'location_id' => $datasetLocationId,
                'from' => $datasetFrom,
                'to' => $datasetTo,
            ],
            'datasetLocations' => StorageLocation::query()->active()->orderBy('name')->get(['id', 'name', 'code']),
            'datasetMovementTypes' => MovementType::consumptionCases(),
            'plans' => DemandPlan::with(['item', 'generatedBy'])
                ->latest('generated_at')
                ->latest('id')
                ->paginate(10, ['*'], 'plan_page')
                ->withQueryString(),
            'datasetMovements' => $datasetMovements,
            'summary' => [
                'items' => $forecasts->count(),
                'needs_reorder' => $forecasts->where('needs_reorder', true)->count(),
                'no_usage' => $forecasts->where('average_daily_usage', 0.0)->count(),
                'suggested_units' => (int) $forecasts->sum('suggested_order_quantity'),
            ],
        ]);
    }

    private function validDatasetDate(mixed $value): ?string
    {
        $value = is_string($value) ? trim($value) : '';

        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1
            && checkdate((int) substr($value, 5, 2), (int) substr($value, 8, 2), (int) substr($value, 0, 4))
                ? $value
                : null;
    }

    public function refresh(Request $request): RedirectResponse|JsonResponse
    {
        $validated = $request->validate([
            'analysis_days' => ['nullable', 'integer', 'min:7', 'max:365'],
            'forecast_days' => ['nullable', 'integer', 'min:7', 'max:180'],
            'return_to' => ['nullable', 'in:dashboard,forecast'],
            'reuse_cached' => ['nullable', 'boolean'],
        ]);

        $analysisDays = (int) ($validated['analysis_days'] ?? DemandForecastService::DEFAULT_ANALYSIS_DAYS);
        $forecastDays = (int) ($validated['forecast_days'] ?? DemandForecastService::DEFAULT_FORECAST_DAYS);
        $route = ($validated['return_to'] ?? 'forecast') === 'dashboard'
            ? 'dashboard'
            : 'inventory.demand-forecast';
        $usedCachedForecast = false;

        try {
            $forecast = null;

            // Automatic dashboard updates reuse a period-specific result when
            // one is available. Manual refreshes keep their
            // existing meaning and always ask for a fresh forecast.
            if ($request->boolean('reuse_cached')) {
                $forecast = $this->aiForecasts->cached($analysisDays, $forecastDays);
                $usedCachedForecast = $forecast !== null;

                if (! $usedCachedForecast) {
                    $forecast = $this->aiForecasts->ensure($request->user(), $analysisDays, $forecastDays);
                }
            }

            $forecast ??= $this->aiForecasts->generate($request->user(), $analysisDays, $forecastDays);
        } catch (RuntimeException $exception) {
            if ($exception->getMessage() === 'No active inventory items are available to forecast.') {
                if ($request->expectsJson()) {
                    return response()->json(['message' => $exception->getMessage()], 422);
                }

                return redirect()->route($route)->with('error', $exception->getMessage());
            }

            report($exception);

            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'The forecast could not be generated. No inventory records were changed.',
                ], 500);
            }

            return redirect()->route($route)->with(
                'error',
                'The forecast could not be generated. No inventory records were changed.'
            );
        } catch (Throwable $exception) {
            report($exception);

            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'The forecast could not be generated. No inventory records were changed.',
                ], 500);
            }

            return redirect()->route($route)->with(
                'error',
                'The forecast could not be generated. No inventory records were changed.'
            );
        }

        $message = $usedCachedForecast
            ? "The {$forecastDays}-day forecast was loaded from the latest saved result."
            : ($forecast['source'] === 'ai'
                ? 'AI demand forecast generated from the latest inventory history.'
                : 'The forecast was generated from recorded inventory history.');

        if ($request->expectsJson()) {
            return response()->json([
                'message' => $message,
                'forecast' => $forecast,
            ]);
        }

        return redirect()
            ->route($route, $route === 'inventory.demand-forecast' ? [
                'analysis_days' => $analysisDays,
                'forecast_days' => $forecastDays,
            ] : [])
            ->with($forecast['source'] === 'ai' ? 'success' : 'info', $message);
    }

    /**
     * Freeze one item's forecast as a DemandPlan.
     */
    public function store(StoreDemandForecastPlanRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $item = InventoryItem::findOrFail($validated['item_id']);

        // Recomputed here rather than taken from the form: the posted numbers
        // came from a page that may have been open for a while, and a plan is
        // meant to record what was true when it was saved.
        $forecast = $this->forecasts->forecast(
            $item,
            (int) ($validated['analysis_days'] ?? DemandForecastService::DEFAULT_ANALYSIS_DAYS),
            (int) ($validated['forecast_days'] ?? DemandForecastService::DEFAULT_FORECAST_DAYS),
            isset($validated['lead_time_days']) ? (int) $validated['lead_time_days'] : null,
        );

        $plan = $this->forecasts->storePlan($forecast, $request->user()?->id, $validated['notes'] ?? null);

        return redirect()
            ->route('inventory.demand-forecast')
            ->with('success', sprintf(
                '%s saved for %s — suggested order %s %s.',
                $plan->plan_number,
                $item->name,
                number_format($plan->suggested_order_quantity),
                $item->unit ?? 'units'
            ));
    }
}
