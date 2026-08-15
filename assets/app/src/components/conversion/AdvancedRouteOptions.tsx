import { Bike, Car, ChevronDown, Footprints, GripVertical, Info, Motorbike, TrainFront } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { Checkbox } from '@/components/ui/checkbox';
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible';
import { RadioGroup, RadioGroupItem } from '@/components/ui/radio-group';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import {
    DEFAULT_ROUTE_OPTIONS,
    PRESET_OPTIONS,
    type ParsedWaypoint,
    type RouteOptionsState,
    type RoutePreset,
    type RoutingProviderCapabilities,
    type TravelMode,
    type WaypointType,
} from './routing/types';

const TRAVEL_MODE_ICON: Record<TravelMode, typeof Car> = {
    DRIVE: Car,
    TWO_WHEELER: Motorbike,
    BICYCLE: Bike,
    WALK: Footprints,
    TRANSIT: TrainFront,
};

interface AdvancedRouteOptionsProps {
    capabilities: RoutingProviderCapabilities;
    options: RouteOptionsState;
    onChange: (next: RouteOptionsState) => void;
    waypoints: ParsedWaypoint[];
    onWaypointsChange: (next: ParsedWaypoint[]) => void;
    open: boolean;
    onOpenChange: (open: boolean) => void;
    disabled: boolean;
}

export function AdvancedRouteOptions({
    capabilities,
    options,
    onChange,
    waypoints,
    onWaypointsChange,
    open,
    onOpenChange,
    disabled,
}: AdvancedRouteOptionsProps) {
    const { t } = useTranslation();

    const trafficCapableMode = 'DRIVE' === options.travelMode || 'TWO_WHEELER' === options.travelMode;
    const canShowTraffic = capabilities.trafficAware && trafficCapableMode;
    const canShowAvoid =
        (capabilities.avoidHighways || capabilities.avoidTolls || capabilities.avoidFerries) && trafficCapableMode;

    const applyPreset = (preset: RoutePreset): void => {
        if ('CUSTOM' === preset) {
            onChange({ ...options, preset: 'CUSTOM' });

            return;
        }

        onChange({ ...DEFAULT_ROUTE_OPTIONS, ...PRESET_OPTIONS[preset], preset });
    };

    const patch = (partial: Partial<RouteOptionsState>): void => {
        onChange({ ...options, ...partial, preset: 'CUSTOM' });
    };

    const toggleWaypointType = (index: number): void => {
        onWaypointsChange(
            waypoints.map((waypoint) =>
                waypoint.index === index
                    ? { ...waypoint, type: 'STOP' === waypoint.type ? ('VIA' satisfies WaypointType) : ('STOP' satisfies WaypointType) }
                    : waypoint,
            ),
        );
    };

    const moveWaypoint = (from: number, direction: -1 | 1): void => {
        const to = from + direction;

        if (to < 0 || to >= waypoints.length) {
            return;
        }

        const next = [...waypoints];
        [next[from], next[to]] = [next[to], next[from]];
        onWaypointsChange(next);
    };

    return (
        <TooltipProvider delayDuration={200}>
            <Collapsible open={open} onOpenChange={onOpenChange} className="mt-3">
                <div className="flex items-center justify-between gap-3 rounded-md border border-border px-3 py-2 text-sm">
                    <span className="flex items-center gap-2 text-muted-foreground">
                        {(() => {
                            const Icon = TRAVEL_MODE_ICON[options.travelMode];

                            return <Icon className="size-4" aria-hidden="true" />;
                        })()}
                        {t(`convert.travel_mode.${options.travelMode.toLowerCase()}`)}
                    </span>
                    <CollapsibleTrigger
                        disabled={disabled}
                        className="flex items-center gap-1 font-medium text-foreground hover:text-primary disabled:pointer-events-none disabled:opacity-50"
                    >
                        {t('convert.advanced.toggle')}
                        <ChevronDown className="size-4 transition-transform data-[state=open]:rotate-180" aria-hidden="true" />
                    </CollapsibleTrigger>
                </div>

                <CollapsibleContent className="mt-3 space-y-5 rounded-md border border-border p-4">
                    <p className="font-mono text-xs tracking-[0.15em] text-muted-foreground">{t('convert.advanced.heading')}</p>

                    <section>
                        <p className="text-xs font-medium text-muted-foreground">{t('convert.advanced.presets.label')}</p>
                        <ToggleGroup
                            type="single"
                            value={options.preset}
                            onValueChange={(value) => value && applyPreset(value as RoutePreset)}
                            className="mt-2"
                        >
                            {(['FASTEST', 'ROAD_TRIP', 'MOTORCYCLE'] as const).map((preset) => (
                                <ToggleGroupItem key={preset} value={preset} disabled={disabled}>
                                    {t(`convert.advanced.presets.${preset.toLowerCase()}`)}
                                </ToggleGroupItem>
                            ))}
                            <ToggleGroupItem value="CUSTOM" disabled={disabled}>
                                {t('convert.advanced.presets.custom')}
                            </ToggleGroupItem>
                        </ToggleGroup>
                    </section>

                    <section>
                        <p className="text-xs font-medium text-muted-foreground">{t('convert.travel_mode_label')}</p>
                        <ToggleGroup
                            type="single"
                            value={options.travelMode}
                            onValueChange={(value) => value && patch({ travelMode: value as TravelMode })}
                            className="mt-2"
                        >
                            {capabilities.supportedTravelModes.map((mode) => {
                                const Icon = TRAVEL_MODE_ICON[mode];

                                return (
                                    <ToggleGroupItem key={mode} value={mode} disabled={disabled}>
                                        <Icon className="size-4" aria-hidden="true" />
                                        {t(`convert.travel_mode.${mode.toLowerCase()}`)}
                                    </ToggleGroupItem>
                                );
                            })}
                        </ToggleGroup>
                    </section>

                    {canShowAvoid && (
                        <section>
                            <p className="text-xs font-medium text-muted-foreground">{t('convert.advanced.avoid.label')}</p>
                            <div className="mt-2 flex flex-col gap-2">
                                {(
                                    [
                                        ['avoidHighways', capabilities.avoidHighways],
                                        ['avoidTolls', capabilities.avoidTolls],
                                        ['avoidFerries', capabilities.avoidFerries],
                                    ] as const
                                )
                                    .filter(([, supported]) => supported)
                                    .map(([key]) => (
                                        <label key={key} className="flex items-center gap-2 text-sm">
                                            <Checkbox
                                                checked={options[key]}
                                                disabled={disabled}
                                                onCheckedChange={(checked) => patch({ [key]: true === checked })}
                                            />
                                            {t(`convert.advanced.avoid.${key}`)}
                                            <Tooltip>
                                                <TooltipTrigger type="button" className="text-muted-foreground hover:text-foreground">
                                                    <Info className="size-3.5" aria-hidden="true" />
                                                </TooltipTrigger>
                                                <TooltipContent>{t(`convert.advanced.avoid.${key}_tooltip`)}</TooltipContent>
                                            </Tooltip>
                                        </label>
                                    ))}
                            </div>
                        </section>
                    )}

                    {canShowTraffic && (
                        <section>
                            <p className="text-xs font-medium text-muted-foreground">{t('convert.advanced.routing_preference.label')}</p>
                            <RadioGroup
                                value={options.routingPreference}
                                onValueChange={(value) => patch({ routingPreference: value as RouteOptionsState['routingPreference'] })}
                                className="mt-2"
                            >
                                {(
                                    [
                                        ['TRAFFIC_UNAWARE', true],
                                        ['TRAFFIC_AWARE', capabilities.trafficAware],
                                        ['TRAFFIC_AWARE_OPTIMAL', capabilities.trafficAwareOptimal],
                                    ] as const
                                )
                                    .filter(([, supported]) => supported)
                                    .map(([value]) => (
                                        <label key={value} className="flex items-center gap-2 text-sm">
                                            <RadioGroupItem value={value} disabled={disabled} />
                                            {t(`convert.advanced.routing_preference.${value.toLowerCase()}`)}
                                            <Tooltip>
                                                <TooltipTrigger type="button" className="text-muted-foreground hover:text-foreground">
                                                    <Info className="size-3.5" aria-hidden="true" />
                                                </TooltipTrigger>
                                                <TooltipContent>
                                                    {t(`convert.advanced.routing_preference.${value.toLowerCase()}_tooltip`)}
                                                </TooltipContent>
                                            </Tooltip>
                                        </label>
                                    ))}
                            </RadioGroup>
                        </section>
                    )}

                    <section>
                        <p className="text-xs font-medium text-muted-foreground">{t('convert.advanced.stops.label')}</p>
                        {capabilities.waypointOptimization && (
                            <label className="mt-2 flex items-center gap-2 text-sm">
                                <Checkbox
                                    checked={options.optimizeWaypointOrder}
                                    disabled={disabled}
                                    onCheckedChange={(checked) => patch({ optimizeWaypointOrder: true === checked })}
                                />
                                {t('convert.advanced.stops.optimize')}
                            </label>
                        )}

                        {waypoints.length > 0 && (
                            <ul className="mt-3 space-y-1.5">
                                {waypoints.map((waypoint, position) => (
                                    <li
                                        key={waypoint.index}
                                        className="flex items-center gap-2 rounded-md border border-border px-2 py-1.5 text-sm"
                                    >
                                        <GripVertical className="size-3.5 shrink-0 text-muted-foreground" aria-hidden="true" />
                                        <span className="flex-1 truncate">{waypoint.label}</span>
                                        <button
                                            type="button"
                                            disabled={disabled || 0 === position}
                                            onClick={() => moveWaypoint(position, -1)}
                                            className="rounded p-1 text-muted-foreground hover:text-foreground disabled:pointer-events-none disabled:opacity-30"
                                            aria-label={t('convert.advanced.stops.move_up')}
                                        >
                                            ↑
                                        </button>
                                        <button
                                            type="button"
                                            disabled={disabled || position === waypoints.length - 1}
                                            onClick={() => moveWaypoint(position, 1)}
                                            className="rounded p-1 text-muted-foreground hover:text-foreground disabled:pointer-events-none disabled:opacity-30"
                                            aria-label={t('convert.advanced.stops.move_down')}
                                        >
                                            ↓
                                        </button>
                                        <button
                                            type="button"
                                            disabled={disabled}
                                            onClick={() => toggleWaypointType(waypoint.index)}
                                            className="rounded-full border border-border px-2 py-0.5 font-mono text-xs text-muted-foreground hover:text-foreground"
                                        >
                                            {t(`convert.advanced.stops.type_${waypoint.type.toLowerCase()}`)}
                                        </button>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </section>

                    <section>
                        <p className="text-xs font-medium text-muted-foreground">{t('convert.advanced.output.label')}</p>
                        <div className="mt-2 flex flex-col gap-2">
                            <label className="flex items-center gap-2 text-sm">
                                <Checkbox
                                    checked={'HIGH_QUALITY' === options.routeDetail}
                                    disabled={disabled}
                                    onCheckedChange={(checked) => patch({ routeDetail: checked ? 'HIGH_QUALITY' : 'STANDARD' })}
                                />
                                {t('convert.advanced.output.high_quality')}
                            </label>
                            {capabilities.alternativeRoutes && (
                                <label className="flex items-center gap-2 text-sm">
                                    <Checkbox
                                        checked={options.showAlternativeRoutes}
                                        disabled={disabled}
                                        onCheckedChange={(checked) => patch({ showAlternativeRoutes: true === checked })}
                                    />
                                    {t('convert.advanced.output.show_alternatives')}
                                </label>
                            )}
                            {capabilities.fuelEfficientRoute && 'DRIVE' === options.travelMode && (
                                <label className="flex items-center gap-2 text-sm">
                                    <Checkbox
                                        checked={options.showFuelEfficientRoute}
                                        disabled={disabled}
                                        onCheckedChange={(checked) => patch({ showFuelEfficientRoute: true === checked })}
                                    />
                                    {t('convert.advanced.output.show_fuel_efficient')}
                                </label>
                            )}
                            {capabilities.tollEstimation && trafficCapableMode && (
                                <label className="flex items-center gap-2 text-sm">
                                    <Checkbox
                                        checked={options.showTollEstimates}
                                        disabled={disabled}
                                        onCheckedChange={(checked) =>
                                            patch({
                                                showTollEstimates: true === checked,
                                                vehicleEmissionType: true === checked ? options.vehicleEmissionType : null,
                                            })
                                        }
                                    />
                                    {t('convert.advanced.output.show_toll_estimates')}
                                </label>
                            )}
                        </div>

                        {/* Progressive disclosure : uniquement affiché quand les péages sont
                           demandés, jamais 15 réglages véhicule à tout le monde (voir le brief). */}
                        {options.showTollEstimates && (
                            <div className="mt-3 pl-7">
                                <p className="text-xs text-muted-foreground">{t('convert.advanced.output.vehicle_type_label')}</p>
                                <ToggleGroup
                                    type="single"
                                    value={options.vehicleEmissionType ?? ''}
                                    onValueChange={(value) =>
                                        patch({ vehicleEmissionType: (value || null) as RouteOptionsState['vehicleEmissionType'] })
                                    }
                                    className="mt-1.5"
                                >
                                    {(['GASOLINE', 'DIESEL', 'ELECTRIC', 'HYBRID'] as const).map((type) => (
                                        <ToggleGroupItem key={type} value={type} disabled={disabled}>
                                            {t(`convert.advanced.output.vehicle_type.${type.toLowerCase()}`)}
                                        </ToggleGroupItem>
                                    ))}
                                </ToggleGroup>
                            </div>
                        )}
                    </section>

                    <button
                        type="button"
                        disabled={disabled}
                        onClick={() => onChange(DEFAULT_ROUTE_OPTIONS)}
                        className="text-xs font-medium text-muted-foreground underline decoration-dotted underline-offset-4 hover:text-foreground"
                    >
                        {t('convert.advanced.reset')}
                    </button>
                </CollapsibleContent>
            </Collapsible>
        </TooltipProvider>
    );
}
