import { Button } from '@/Components/ui/button';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import axios from 'axios';
import { Head } from '@inertiajs/react';
import { useEffect, useState } from 'react';

type Vehicle = { id: number; title: string; is_location_visible: boolean };

export default function Location({ vehicles, intervalSeconds }: { vehicles: Vehicle[]; intervalSeconds: number }) {
    const [vehicleId, setVehicleId] = useState(vehicles[0]?.id ?? 0);
    const [status, setStatus] = useState('Ожидание запуска');
    const [enabled, setEnabled] = useState(false);

    useEffect(() => {
        if (!enabled || !vehicleId || !navigator.geolocation) {
            return;
        }

        const send = () => {
            navigator.geolocation.getCurrentPosition(
                (position) => {
                    axios.post(route('vehicles.location.update', vehicleId), {
                        lat: position.coords.latitude,
                        lng: position.coords.longitude,
                        accuracy_meters: position.coords.accuracy,
                        speed_kmh: position.coords.speed ? position.coords.speed * 3.6 : null,
                        heading_degrees: position.coords.heading,
                    }).then(() => setStatus(`Координаты обновлены: ${new Date().toLocaleTimeString('ru-RU')}`));
                },
                () => setStatus('Браузер не дал доступ к геолокации'),
                { enableHighAccuracy: true },
            );
        };

        send();
        const id = window.setInterval(send, intervalSeconds * 1000);
        return () => window.clearInterval(id);
    }, [enabled, intervalSeconds, vehicleId]);

    return (
        <AuthenticatedLayout breadcrumbs={[{ title: 'Геолокация транспорта' }]}>
            <Head title="Геолокация транспорта" />
            <div className="mx-auto grid max-w-3xl gap-5 px-4 py-6">
                <h1 className="text-2xl font-semibold">Геолокация транспорта</h1>
                <select value={vehicleId} onChange={(e) => setVehicleId(Number(e.target.value))} className="rounded-md border bg-background px-3 py-2">
                    {vehicles.map((vehicle) => <option key={vehicle.id} value={vehicle.id}>{vehicle.title}</option>)}
                </select>
                <div className="flex gap-2">
                    <Button onClick={() => setEnabled(true)}>Старт</Button>
                    <Button variant="secondary" onClick={() => setEnabled(false)}>Стоп</Button>
                </div>
                <p className="rounded-md border p-4 text-sm">{status}</p>
            </div>
        </AuthenticatedLayout>
    );
}
