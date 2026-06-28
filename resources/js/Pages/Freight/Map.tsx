import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { useFreightTranslation } from '@/hooks/useFreightTranslation';
import { Head } from '@inertiajs/react';
import axios from 'axios';
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';
import { useCallback, useEffect, useMemo, useRef, useState } from 'react';

type MapConfig = {
    defaultLat: number;
    defaultLng: number;
    defaultZoom: number;
    refreshSeconds: number;
    tileUrl: string;
    attribution: string;
};

type MapObject = {
    id: number;
    type: 'load' | 'vehicle';
    title: string;
    lat: number;
    lng: number;
    city?: string;
    route?: string;
    body_type?: string;
    is_online?: boolean;
    url?: string;
};

type MapFilters = {
    showLoads: boolean;
    showVehicles: boolean;
    bodyType: string;
    onlineOnly: boolean;
    limit: string;
};

type AcceptedRoute = {
    geometry: [number, number][];
    distance_m?: number | null;
    duration_s?: number | null;
    load: { title: string; lat: number; lng: number; url?: string };
    vehicle: { title: string; lat: number; lng: number; url?: string };
};

function escapeHtml(value: string) {
    const element = document.createElement('div');
    element.textContent = value;

    return element.innerHTML;
}

function popupHtml(title: string, body?: string, url?: string, linkLabel?: string) {
    return [
        `<strong>${escapeHtml(title)}</strong>`,
        body ? `<div>${escapeHtml(body)}</div>` : '',
        url && linkLabel ? `<a href="${url}">${escapeHtml(linkLabel)}</a>` : '',
    ]
        .filter(Boolean)
        .join('');
}

function objectIcon(type: 'load' | 'vehicle', color: string) {
    const path = type === 'load'
        ? '<path d="M4 7.5 12 3l8 4.5v9L12 21l-8-4.5v-9Z"/><path d="M12 12 4.4 7.7"/><path d="M12 12l7.6-4.3"/><path d="M12 12v8.5"/>'
        : '<path d="M5 17h14"/><path d="M6 17V9h8l3 4h2v4"/><path d="M8 17a2 2 0 1 0 4 0"/><path d="M16 17a2 2 0 1 0 4 0"/><path d="M14 9v4h3"/>';

    return L.divIcon({
        className: '',
        iconSize: [34, 34],
        iconAnchor: [17, 17],
        popupAnchor: [0, -18],
        html: `
            <div style="width:34px;height:34px;border-radius:8px;background:${color};border:2px solid white;box-shadow:0 8px 18px rgba(15,23,42,.28);display:grid;place-items:center">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">${path}</svg>
            </div>
        `,
    });
}

function clusterIcon(count: number) {
    return L.divIcon({
        className: '',
        iconSize: [38, 38],
        iconAnchor: [19, 19],
        popupAnchor: [0, -20],
        html: `
            <div style="width:38px;height:38px;border-radius:999px;background:#111827;color:white;border:2px solid white;box-shadow:0 8px 18px rgba(15,23,42,.28);display:grid;place-items:center;font:700 13px/1 system-ui">
                ${count}
            </div>
        `,
    });
}

function clusteredObjects(objects: MapObject[], zoom: number) {
    if (zoom >= 8) {
        return objects.map((item) => ({ type: 'object' as const, item }));
    }

    const cellSize = zoom <= 4 ? 2 : zoom <= 6 ? 0.75 : 0.35;
    const buckets = new globalThis.Map<string, MapObject[]>();

    objects.forEach((item) => {
        const key = `${Math.floor(item.lat / cellSize)}:${Math.floor(item.lng / cellSize)}`;
        buckets.set(key, [...(buckets.get(key) ?? []), item]);
    });

    return Array.from(buckets.values()).map((items) => {
        if (items.length === 1) {
            return { type: 'object' as const, item: items[0] };
        }

        return {
            type: 'cluster' as const,
            lat: items.reduce((sum, item) => sum + item.lat, 0) / items.length,
            lng: items.reduce((sum, item) => sum + item.lng, 0) / items.length,
            loads: items.filter((item) => item.type === 'load').length,
            vehicles: items.filter((item) => item.type === 'vehicle').length,
            count: items.length,
        };
    });
}

export default function Map({ map }: { map: MapConfig }) {
    const t = useFreightTranslation();
    const ref = useRef<HTMLDivElement>(null);
    const mapRef = useRef<L.Map | null>(null);
    const objectLayerRef = useRef<L.LayerGroup | null>(null);
    const routeLayerRef = useRef<L.LayerGroup | null>(null);
    const routeFitRef = useRef(false);
    const useBoundsRef = useRef(false);
    const [status, setStatus] = useState(t('map.loading'));
    const [filters, setFilters] = useState<MapFilters>({
        showLoads: true,
        showVehicles: true,
        bodyType: '',
        onlineOnly: false,
        limit: '250',
    });

    const routeSearchParams = new URLSearchParams(window.location.search);
    const routeLoadId = routeSearchParams.get('load_id');
    const shouldShowAcceptedRoute = Boolean(
        routeLoadId && routeSearchParams.get('route') === '1',
    );
    const filterKey = useMemo(() => JSON.stringify(filters), [filters]);

    const renderObjects = useCallback(() => {
        if (!mapRef.current || !objectLayerRef.current || !routeLayerRef.current) return;

        const bounds = mapRef.current.getBounds();
        const params = {
            types: [
                filters.showLoads ? 'loads' : null,
                filters.showVehicles ? 'vehicles' : null,
            ].filter(Boolean),
            body_type: filters.bodyType || undefined,
            online: filters.onlineOnly ? true : undefined,
            limit: filters.limit,
            bounds: useBoundsRef.current ? {
                north: bounds.getNorth(),
                south: bounds.getSouth(),
                east: bounds.getEast(),
                west: bounds.getWest(),
            } : undefined,
        };

        setStatus(t('map.loading'));

        axios
            .get(route('api.map.objects'), { params })
            .then(({ data }) => {
                if (!mapRef.current || !objectLayerRef.current || !routeLayerRef.current) return;

                const objectLayer = objectLayerRef.current;
                const routeLayer = routeLayerRef.current;
                const objects: MapObject[] = [...data.loads, ...data.vehicles];
                objectLayer.clearLayers();
                routeLayer.clearLayers();

                clusteredObjects(objects, mapRef.current.getZoom()).forEach((entry) => {
                    if (entry.type === 'cluster') {
                        L.marker([entry.lat, entry.lng], {
                            icon: clusterIcon(entry.count),
                        })
                            .bindPopup(
                                popupHtml(
                                    t('map.cluster_title', { count: entry.count }),
                                    t('map.cluster_body', {
                                        loads: entry.loads,
                                        vehicles: entry.vehicles,
                                    }),
                                ),
                            )
                            .addTo(objectLayer);

                        return;
                    }

                    const item = entry.item;
                    const color =
                        item.type === 'load'
                            ? '#2563eb'
                            : item.is_online
                              ? '#059669'
                              : '#71717a';
                    const cardLabel =
                        item.type === 'load'
                            ? t('map.load_card')
                            : t('map.vehicle_card');
                    const body =
                        item.route ||
                        [item.city, item.body_type].filter(Boolean).join(' ');

                    L.marker([item.lat, item.lng], {
                        icon: objectIcon(item.type, color),
                    })
                        .bindPopup(popupHtml(item.title, body, item.url, cardLabel))
                        .addTo(objectLayer);
                });

                if (shouldShowAcceptedRoute && routeLoadId) {
                    axios
                        .get(route('api.map.accepted-route', routeLoadId))
                        .then(({ data }: { data: AcceptedRoute }) => {
                            if (!mapRef.current || !routeLayerRef.current || !data.geometry.length) return;

                            const routeLayer = routeLayerRef.current;

                            const polyline = L.polyline(data.geometry, {
                                color: '#f97316',
                                weight: 5,
                                opacity: 0.9,
                            }).bindPopup(
                                [
                                    data.vehicle.title,
                                    ' -> ',
                                    data.load.title,
                                    data.distance_m
                                        ? ` (${(data.distance_m / 1000).toFixed(1)} km)`
                                        : '',
                                ].join(''),
                            );

                            L.marker([data.vehicle.lat, data.vehicle.lng], {
                                icon: objectIcon('vehicle', '#059669'),
                            })
                                .bindPopup(popupHtml(data.vehicle.title, t('map.route_start')))
                                .addTo(routeLayer);

                            L.marker([data.load.lat, data.load.lng], {
                                icon: objectIcon('load', '#f97316'),
                            })
                                .bindPopup(popupHtml(data.load.title, t('map.route_finish')))
                                .addTo(routeLayer);

                            polyline.addTo(routeLayer);

                            if (!routeFitRef.current) {
                                routeFitRef.current = true;
                                mapRef.current.fitBounds(polyline.getBounds(), {
                                    padding: [56, 56],
                                });
                            }

                            setStatus(
                                t('map.route_status', {
                                    distance: data.distance_m
                                        ? (data.distance_m / 1000).toFixed(1)
                                        : '-',
                                    duration: data.duration_s
                                        ? Math.round(data.duration_s / 60).toLocaleString('ru-RU')
                                        : '-',
                                }),
                            );
                        })
                        .catch(() => setStatus(t('map.route_failed')));
                    return;
                }

                setStatus(
                    t('map.status', {
                        loads: t('map.load_count', { count: data.loads.length }),
                        vehicles: t('map.vehicle_count', { count: data.vehicles.length }),
                        limit: data.filters.limit,
                        time: new Date().toLocaleTimeString('ru-RU'),
                    }),
                );
            })
            .catch(() => setStatus(t('map.failed')));
    }, [filterKey, filters.bodyType, filters.limit, filters.onlineOnly, filters.showLoads, filters.showVehicles, routeLoadId, shouldShowAcceptedRoute, t]);

    useEffect(() => {
        if (!ref.current || mapRef.current) return;

        mapRef.current = L.map(ref.current, {
            center: [map.defaultLat, map.defaultLng],
            zoom: map.defaultZoom,
            zoomControl: true,
        });
        mapRef.current.attributionControl.setPrefix(false);

        L.tileLayer(map.tileUrl, {
            attribution: map.attribution,
            maxZoom: 19,
        }).addTo(mapRef.current);

        objectLayerRef.current = L.layerGroup().addTo(mapRef.current);
        routeLayerRef.current = L.layerGroup().addTo(mapRef.current);

        return () => {
            mapRef.current?.remove();
            mapRef.current = null;
            objectLayerRef.current = null;
            routeLayerRef.current = null;
        };
    }, [map.attribution, map.defaultLat, map.defaultLng, map.defaultZoom, map.tileUrl]);

    useEffect(() => {
        if (!mapRef.current) return;

        renderObjects();
        const renderVisibleObjects = () => {
            useBoundsRef.current = true;
            renderObjects();
        };

        mapRef.current.on('moveend zoomend', renderVisibleObjects);

        return () => {
            mapRef.current?.off('moveend zoomend', renderVisibleObjects);
        };
    }, [renderObjects]);

    useEffect(() => {
        const id = window.setInterval(renderObjects, map.refreshSeconds * 1000);

        return () => window.clearInterval(id);
    }, [map.refreshSeconds, renderObjects]);

    return (
        <AuthenticatedLayout breadcrumbs={[{ title: t('common.map') }]}>
            <Head title={t('map.title')} />
            <div className="grid gap-3 px-4 py-4">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <h1 className="text-2xl font-semibold">
                        {t('map.title')}
                    </h1>
                    <p className="text-sm text-muted-foreground">{status}</p>
                </div>
                <div className="grid gap-2 rounded-md border p-3 sm:grid-cols-2 lg:grid-cols-[repeat(5,minmax(0,auto))] lg:items-center">
                    <label className="flex min-h-10 items-center gap-2 rounded-md border px-3 text-sm">
                        <input
                            type="checkbox"
                            checked={filters.showLoads}
                            onChange={(event) => setFilters((current) => ({ ...current, showLoads: event.target.checked }))}
                        />
                        {t('map.filters.loads')}
                    </label>
                    <label className="flex min-h-10 items-center gap-2 rounded-md border px-3 text-sm">
                        <input
                            type="checkbox"
                            checked={filters.showVehicles}
                            onChange={(event) => setFilters((current) => ({ ...current, showVehicles: event.target.checked }))}
                        />
                        {t('map.filters.vehicles')}
                    </label>
                    <select
                        className="min-h-10 rounded-md border bg-background px-3 text-sm"
                        value={filters.bodyType}
                        onChange={(event) => setFilters((current) => ({ ...current, bodyType: event.target.value }))}
                    >
                        <option value="">{t('map.filters.any_body')}</option>
                        {['тент', 'рефрижератор', 'изотерм', 'бортовой'].map((bodyType) => (
                            <option key={bodyType} value={bodyType}>
                                {bodyType}
                            </option>
                        ))}
                    </select>
                    <label className="flex min-h-10 items-center gap-2 rounded-md border px-3 text-sm">
                        <input
                            type="checkbox"
                            checked={filters.onlineOnly}
                            disabled={!filters.showVehicles}
                            onChange={(event) => setFilters((current) => ({ ...current, onlineOnly: event.target.checked }))}
                        />
                        {t('map.filters.online')}
                    </label>
                    <select
                        className="min-h-10 rounded-md border bg-background px-3 text-sm"
                        value={filters.limit}
                        onChange={(event) => setFilters((current) => ({ ...current, limit: event.target.value }))}
                    >
                        {[100, 250, 500].map((limit) => (
                            <option key={limit} value={String(limit)}>
                                {t('map.filters.limit', { limit })}
                            </option>
                        ))}
                    </select>
                </div>
                <div
                    ref={ref}
                    className="h-[72vh] min-h-[520px] overflow-hidden rounded-md border"
                />
            </div>
        </AuthenticatedLayout>
    );
}
